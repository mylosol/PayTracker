<?php

declare(strict_types=1);

namespace PayTracker\Auth;

use PayTracker\Database\Connection;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;
use PayTracker\Security\Session;

/**
 * AuthService — the single entry point for login/logout/identity reads.
 *
 * Design rules enforced here:
 *   - Lookup is by `user` (the legacy login handle) OR `email`, whichever
 *     the caller supplies. Both columns are indexed.
 *   - Failed attempts increment `failed_login_count` and, after a
 *     threshold, set `locked_until` so the account refuses login for a
 *     cool-off period. This is server-side rate limiting; it cannot be
 *     bypassed by the client.
 *   - On any failure path we sleep for a small random jitter before
 *     returning so an attacker cannot distinguish "user not found" from
 *     "bad password" by response time.
 *   - On success the session id is regenerated (defeats session fixation)
 *     and the account id is stored in `$_SESSION['account_id']`. No
 *     PII (password, email, etc.) is stored in the session.
 *   - If the stored hash's cost factor is below the current target we
 *     transparently rehash and persist the new hash — no flag day needed
 *     when we bump cost.
 */
final class AuthService
{
    /** After this many consecutive failures, lock the account briefly. */
    private const LOCKOUT_THRESHOLD = 5;

    /** Lockout duration in seconds (15 minutes). */
    private const LOCKOUT_SECONDS = 900;

    public function __construct(
        private readonly Connection $connection,
        private readonly Session $session,
        private readonly PasswordHasher $hasher,
        private readonly AuditLog $audit,
    ) {
    }

    /**
     * Attempt a login.
     *
     * Returns either the account id (int) on success OR a LoginFailure
     * enum case describing why we said no. The two failure cases are
     * deliberately asymmetric:
     *
     *   BadCredentials  -- catch-all "no" for wrong-handle / wrong-
     *                      password / locked-after-too-many-tries.
     *                      Identical UX so attackers can't distinguish
     *                      "user doesn't exist" from "wrong password"
     *                      via response shape or timing.
     *   AccountSuspended -- ONLY returned AFTER we've verified the
     *                      caller holds the right password. Safe to
     *                      reveal at that point because an attacker
     *                      with the correct password already wins
     *                      in the non-banned case; revealing the
     *                      suspension flag adds no new attack surface
     *                      and lets the suspended legitimate user
     *                      stop re-typing their (correct) password
     *                      in confusion.
     */
    public function attempt(string $handle, string $password): int|LoginFailure
    {
        $this->session->start();
        $pdo = $this->connection->pdo();

        // Positional placeholders — we use the same value twice and PDO's
        // EMULATE_PREPARES=false setting disallows reusing a named
        // placeholder with native MySQL prepared statements (raises
        // HY093 "Invalid parameter number" at execute time).
        $stmt = $pdo->prepare(
            'SELECT id, user, email, password_hash, role, failed_login_count, locked_until, banned_at
             FROM `account`
             WHERE user = ? OR (email IS NOT NULL AND email = ?)
             LIMIT 1'
        );
        $stmt->execute([$handle, $handle]);
        $row = $stmt->fetch();

        // Audit metadata snapshot: IP + UA + attempted handle. Captured once
        // so every failure / success path passes the same context block
        // through to the audit log.
        $auditMeta = $this->auditContext($handle);
        $auditIp   = is_string($auditMeta['ip_address'] ?? null) ? (string) $auditMeta['ip_address'] : null;

        if (! is_array($row) || ! is_string($row['password_hash'] ?? null) || $row['password_hash'] === '') {
            $this->audit->record(
                AuditLog::ACTION_USER_LOGIN_FAILED,
                userId: null,
                reason: AuditLog::REASON_NO_SUCH_USER,
                ipAddress: $auditIp,
                metadata: $auditMeta,
            );
            $this->equalizeFailureLatency();
            return LoginFailure::BadCredentials;
        }

        // Lockout BEFORE password verify -- a locked account refuses
        // attempts without bumping the counter so an attacker can't
        // extend the cool-off period indefinitely. The legitimate
        // user still sees the generic message; surfacing "you're
        // locked" would let an attacker probe state cheaply.
        if ($this->isLocked($row)) {
            $this->audit->record(
                AuditLog::ACTION_USER_LOGIN_FAILED,
                userId: (int) $row['id'],
                reason: AuditLog::REASON_LOCKED,
                ipAddress: $auditIp,
                metadata: $auditMeta,
            );
            $this->equalizeFailureLatency();
            return LoginFailure::BadCredentials;
        }

        if (! $this->hasher->verify($password, $row['password_hash'])) {
            $this->recordFailure((int) $row['id'], (int) $row['failed_login_count']);
            $this->audit->record(
                AuditLog::ACTION_USER_LOGIN_FAILED,
                userId: (int) $row['id'],
                reason: AuditLog::REASON_BAD_PASSWORD,
                ipAddress: $auditIp,
                metadata: $auditMeta,
            );
            $this->equalizeFailureLatency();
            return LoginFailure::BadCredentials;
        }

        // Password verified. NOW check the ban flag. Surfacing the
        // suspension to a caller with the correct password is the
        // friendly UX: a legitimate user who's been suspended sees
        // "your account has been suspended" instead of "wrong
        // password" and stops re-typing. The security implication is
        // bounded -- the attacker has already proved password
        // ownership, so revealing the suspension grants no new
        // attack surface vs. a non-banned account where they'd just
        // be in.
        if (is_string($row['banned_at'] ?? null) && $row['banned_at'] !== '') {
            $this->audit->record(
                AuditLog::ACTION_USER_LOGIN_FAILED,
                userId: (int) $row['id'],
                reason: AuditLog::REASON_BANNED,
                ipAddress: $auditIp,
                metadata: $auditMeta,
            );
            $this->equalizeFailureLatency();
            return LoginFailure::AccountSuspended;
        }

        // Success. Reset counters, persist the new hash if the cost moved,
        // rotate the session id, and store the identity.
        $this->recordSuccess((int) $row['id']);
        $this->audit->record(
            AuditLog::ACTION_USER_LOGIN,
            userId: (int) $row['id'],
            ipAddress: $auditIp,
            metadata: $auditMeta,
        );
        if ($this->hasher->needsRehash($row['password_hash'])) {
            $newHash = $this->hasher->hash($password);
            $upd     = $pdo->prepare('UPDATE `account` SET password_hash = :h WHERE id = :id');
            $upd->execute(['h' => $newHash, 'id' => $row['id']]);
        }
        $this->session->regenerate();
        $this->session->put('account_id', (int) $row['id']);
        $this->session->put('account_user', (string) $row['user']);
        $this->session->put('account_role', (string) $row['role']);
        return (int) $row['id'];
    }

    /**
     * Tear down the current session. Cookie + server-side state both go.
     */
    public function logout(): void
    {
        $this->session->start();
        // Capture identity BEFORE we wipe the session, so the audit
        // row attributes the logout to the right account.
        $idForAudit = $this->session->get('account_id');
        $idForAudit = is_int($idForAudit) && $idForAudit > 0 ? $idForAudit : null;
        if ($idForAudit !== null) {
            $this->audit->record(
                AuditLog::ACTION_USER_LOGOUT,
                userId: $idForAudit,
                ipAddress: $this->clientIp(),
            );
        }
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            // Expire the cookie at the client too — server-side destruction
            // alone leaves a stale cookie that could be replayed if the
            // session store re-hydrated it.
            // `session_get_cookie_params()` is documented to always return
            // all of these keys (PHP 7.3+), so the `??` defaults previously
            // here on `domain` and `samesite` were dead code. phpstan flags
            // them and is correct.
            setcookie(session_name(), '', [
                'expires'  => time() - 42_000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
            session_destroy();
        }
    }

    /**
     * Look up the currently logged-in account, or null if anonymous. The
     * lookup hits the DB so a locked / deleted account immediately loses
     * access — we do not rely on the cached session role for authorisation.
     *
     * @return array<string, mixed>|null
     */
    public function currentAccount(): ?array
    {
        $this->session->start();
        $id = $this->session->get('account_id');
        if (! is_int($id) || $id <= 0) {
            return null;
        }
        $stmt = $this->connection->pdo()->prepare(
            'SELECT id, user, email, payroll_email, role, last_login_at, locked_until, banned_at,
                    hire_date, shift, pay_week_start_day
             FROM `account` WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (! is_array($row)) {
            return null;
        }
        // Mid-session ban eviction. If an admin banned this account
        // since the cookie was minted, every subsequent currentAccount()
        // call treats them as anonymous — the next request lands at
        // /login (where the ban check refuses re-auth too). Cleaner
        // than waiting on cookie expiry and doesn't require a
        // session-version column.
        if (is_string($row['banned_at'] ?? null) && $row['banned_at'] !== '') {
            return null;
        }
        return $row;
    }

    /** @param array<string, mixed> $row */
    private function isLocked(array $row): bool
    {
        $until = $row['locked_until'] ?? null;
        if (! is_string($until) || $until === '') {
            return false;
        }
        return strtotime($until) > time();
    }

    private function recordFailure(int $accountId, int $currentCount): void
    {
        $newCount = $currentCount + 1;
        $lockedUntil = $newCount >= self::LOCKOUT_THRESHOLD
            ? gmdate('Y-m-d H:i:s', time() + self::LOCKOUT_SECONDS)
            : null;

        $stmt = $this->connection->pdo()->prepare(
            'UPDATE `account`
             SET failed_login_count = :count,
                 locked_until       = :until
             WHERE id = :id'
        );
        $stmt->execute([
            'count' => $newCount,
            'until' => $lockedUntil,
            'id'    => $accountId,
        ]);
    }

    private function recordSuccess(int $accountId): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE `account`
             SET failed_login_count = 0,
                 locked_until       = NULL,
                 last_login_at      = :now
             WHERE id = :id'
        );
        $stmt->execute([
            'now' => gmdate('Y-m-d H:i:s'),
            'id'  => $accountId,
        ]);
    }

    /**
     * Sleep for a small random duration to flatten the timing signature
     * of failure responses. This is NOT a substitute for password_verify
     * being constant-time — it covers the surrounding lookup + DB writes.
     */
    private function equalizeFailureLatency(): void
    {
        usleep(random_int(150_000, 250_000)); // 150–250 ms
    }

    /**
     * Best-effort client IP. Reads X-Forwarded-For first (we trust the
     * upstream DreamHost proxy chain), falling back to REMOTE_ADDR.
     * Returns null in CLI contexts where REMOTE_ADDR is absent.
     *
     * NOTE: X-Forwarded-For can be a comma-separated list when there
     * are multiple proxies. We take the leftmost (original client) value.
     */
    private function clientIp(): ?string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($xff) && $xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if ($first !== '') {
                return substr($first, 0, 45);
            }
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($remote) && $remote !== '') {
            return substr($remote, 0, 45);
        }
        return null;
    }

    /**
     * Context block recorded alongside auth events. Captures the
     * fingerprint info a security investigation actually needs:
     * IP, user-agent, and the typed handle (so failed-login
     * forensics can correlate "what was being tried" across rows
     * where user_id is NULL).
     *
     * @return array<string,mixed>
     */
    private function auditContext(string $attemptedHandle): array
    {
        return [
            'ip_address'        => $this->clientIp(),
            'user_agent'        => isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
                ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
                : null,
            'accept_language'   => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'])
                ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 120)
                : null,
            'attempted_handle'  => substr($attemptedHandle, 0, 120),
        ];
    }
}
