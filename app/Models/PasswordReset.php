<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;
use PDO;

/**
 * `password_resets` — short-lived single-use tokens for the admin-
 * initiated password reset flow.
 *
 * Token security model:
 *   * The CALLER generates a high-entropy raw token (32 bytes of
 *     random_bytes hex-encoded → 64 chars). That raw token is
 *     shown to the admin once and embedded in the reset URL.
 *   * This model stores the SHA-256 hash of the token, never the
 *     raw value. A DB leak therefore can't be replayed.
 *   * Consume is atomic: the WHERE clause checks expiry + unused
 *     state and the same statement marks `used_at`, so two
 *     concurrent submissions of the same token can't both succeed.
 *
 * @see scripts/set-password.php — the CLI-only admin password-set
 *      path that bypasses this flow for the bootstrap admin.
 */
final class PasswordReset extends Model
{
    protected static string $table = 'password_resets';

    /** Lifetime of a newly minted token, in seconds (1 hour). */
    public const TTL_SECONDS = 3600;

    /**
     * Mint a new reset token for $userId. Returns the RAW token
     * (the caller surfaces it to the admin / emails it via Resend).
     *
     * Side effects:
     *   * Invalidates every previously-outstanding token for the
     *     same user by setting used_at = NOW() on them. Belt-and-
     *     suspenders: a stale token from yesterday can't be
     *     redeemed if the admin re-mints today.
     *
     * The high-entropy token is 64 chars of hex (256 bits). The
     * search space rules out brute-force enumeration without
     * needing a separate rate-limiter on the consume endpoint.
     *
     * @return array{token:string, expiresAt:string} The plaintext
     *         token + its UTC expiry timestamp, in YYYY-MM-DD HH:MM:SS
     *         form.
     */
    public function mint(int $userId, ?int $createdById = null): array
    {
        $pdo = $this->connection->pdo();

        // Invalidate prior outstanding tokens for this user. Using
        // used_at = NOW() rather than DELETE so the audit log
        // (branch 3) can still report what happened.
        $invalidate = $pdo->prepare(
            'UPDATE `password_resets`
                SET used_at = UTC_TIMESTAMP()
              WHERE user_id = ? AND used_at IS NULL'
        );
        $invalidate->execute([$userId]);

        // 32 bytes → 64 hex chars. Hex (not base64) so the token
        // round-trips through URL paths without encoding worries.
        $raw       = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $raw);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::TTL_SECONDS);

        $insert = $pdo->prepare(
            'INSERT INTO `password_resets`
                (user_id, token_hash, expires_at, created_by_id, created_at)
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $insert->execute([$userId, $hash, $expiresAt, $createdById]);

        return ['token' => $raw, 'expiresAt' => $expiresAt];
    }

    /**
     * Look up the user_id behind a raw token if (and only if) the
     * token is live: exists, unexpired, unused.
     *
     * Returns null on miss without distinguishing "no such token"
     * from "expired" from "already used" — the failure surface to
     * the user is identical for all three to avoid leaking which
     * tokens are real.
     */
    public function findActiveUserIdFor(string $rawToken): ?int
    {
        if ($rawToken === '') {
            return null;
        }
        $hash = hash('sha256', $rawToken);
        $sql = 'SELECT user_id
                  FROM `password_resets`
                 WHERE token_hash = ?
                   AND used_at    IS NULL
                   AND expires_at > UTC_TIMESTAMP()
                 LIMIT 1';
        $row = $this->prepared($sql, [$hash])->fetch();
        if (! is_array($row)) {
            return null;
        }
        return (int) $row['user_id'];
    }

    /**
     * Mark a token consumed. Caller has ALREADY validated it via
     * findActiveUserIdFor; the WHERE clause re-checks live state
     * so a concurrent second consume of the same token returns
     * zero rows affected and the caller knows to refuse.
     *
     * Returns true when the token was successfully consumed.
     */
    public function consume(string $rawToken): bool
    {
        if ($rawToken === '') {
            return false;
        }
        $hash = hash('sha256', $rawToken);
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE `password_resets`
                SET used_at = UTC_TIMESTAMP()
              WHERE token_hash = ?
                AND used_at    IS NULL
                AND expires_at > UTC_TIMESTAMP()'
        );
        $stmt->execute([$hash]);
        return $stmt->rowCount() > 0;
    }
}
