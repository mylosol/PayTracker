<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\PasswordHasher;
use PayTracker\Database\Connection;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\AuditLog;
use PayTracker\Models\PasswordReset;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * PasswordResetController — public claim flow for a reset token
 * minted by an admin via AdminUsersController::resetPassword.
 *
 * Public (no auth) by design — the whole point of a reset link
 * is that someone who can't log in can use it. Security comes
 * from the token: 256 bits of entropy, stored hashed, expires
 * in 1 hour, single-use, invalidated when a fresh token is
 * minted for the same user.
 *
 * Routes:
 *   GET  /password-reset/{token} — show the "set new password" form
 *   POST /password-reset/{token} — submit the new password
 *
 * Token uniformity: every failure mode (no such token, expired,
 * already used, malformed) returns the same generic
 * "link no longer valid" page so an attacker can't probe which
 * tokens exist.
 */
final class PasswordResetController extends Controller
{
    /** Lower bound on the new password's length. Matches set-password.php. */
    private const MIN_PASSWORD_LEN = 12;

    public function __construct(
        private readonly Connection $connection,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly PasswordReset $resets,
        private readonly PasswordHasher $hasher,
        private readonly AuditLog $audit,
    ) {
    }

    /**
     * GET /password-reset/{token} — render the form when the
     * token is live; render an expired-link page otherwise.
     */
    public function show(Request $request, string $token): Response
    {
        // CRITICAL: start the session BEFORE touching CSRF or flash.
        // Session::put writes to $_SESSION directly; without an
        // active session PHP never writes the data to the session
        // store, so the token (and any flash) is lost between GET
        // and POST. Symptom: form submits silently, page reloads to
        // the same URL with no visible error and no password change.
        $this->session->start();

        $userId = $this->resets->findActiveUserIdFor($this->normalize($token));
        if ($userId === null) {
            return $this->view('password-reset/expired', [
                'base' => $request->basePath(),
            ], 410); // 410 Gone — token is no longer valid
        }

        return $this->view('password-reset/claim', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'token'     => $token,
            'minLen'    => self::MIN_PASSWORD_LEN,
            'flash'     => $this->popFlash(),
        ]);
    }

    /**
     * POST /password-reset/{token} — validate the new password,
     * consume the token atomically, write the new hash. Always
     * re-checks token validity inside the consume call so a
     * race between two submissions of the same token results
     * in at most one success.
     */
    public function submit(Request $request, string $token): Response
    {
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request, $token, 'Your session expired. Please request a new reset link.');
        }

        $normalized = $this->normalize($token);
        $userId = $this->resets->findActiveUserIdFor($normalized);
        if ($userId === null) {
            // Don't pretend the form succeeded — but also don't reveal
            // which of "expired / used / no such token" is the real
            // story. The expired view is what the user sees here.
            return $this->view('password-reset/expired', [
                'base' => $request->basePath(),
            ], 410);
        }

        $password = (string) $request->input('password', '');
        $confirm  = (string) $request->input('password_confirmation', '');

        if (strlen($password) < self::MIN_PASSWORD_LEN) {
            return $this->failBack($request, $token, sprintf(
                'Password must be at least %d characters.',
                self::MIN_PASSWORD_LEN
            ));
        }
        if ($password !== $confirm) {
            return $this->failBack($request, $token, 'Passwords do not match.');
        }

        // Consume the token FIRST (atomic UPDATE that re-checks
        // expiry + used_at). If two concurrent submissions race,
        // exactly one of them gets rowCount=1 and wins.
        if (! $this->resets->consume($normalized)) {
            // Lost the race or the token expired between findActive
            // and consume. Treat as a generic expired link.
            return $this->view('password-reset/expired', [
                'base' => $request->basePath(),
            ], 410);
        }

        // Token consumed — write the new hash. We also clear the
        // ban / lockout counters: an admin handing out a reset link
        // implicitly says "this account can log back in", so we
        // shouldn't leave a stale lockout in place.
        $hash = $this->hasher->hash($password);
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE `account`
                SET password_hash      = ?,
                    failed_login_count = 0,
                    locked_until       = NULL
              WHERE id = ?'
        );
        $stmt->execute([$hash, $userId]);

        $this->audit->record(
            AuditLog::ACTION_PASSWORD_RESET_USED,
            userId: $userId,
            ipAddress: $this->clientIp(),
        );

        return $this->view('password-reset/complete', [
            'base' => $request->basePath(),
        ]);
    }

    /**
     * Best-effort client IP for the audit row on a successful
     * reset. Mirrors the helper used by AdminUsersController and
     * AuthService; duplication kept deliberately so this controller
     * doesn't grow a hard dependency on either.
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
     * Strip leading/trailing whitespace and lower-case the token.
     * Tokens are hex (lowercase by construction) but admins
     * copy-pasting from email clients sometimes pick up stray
     * whitespace or uppercase the URL. Normalisation keeps the
     * comparison robust.
     */
    private function normalize(string $token): string
    {
        return strtolower(trim($token));
    }

    private function failBack(Request $request, string $token, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/password-reset/' . urlencode($token));
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
