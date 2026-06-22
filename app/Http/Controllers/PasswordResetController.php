<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\PasswordHasher;
use PayTracker\Database\Connection;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;
use PayTracker\Models\PasswordReset;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\MailService;

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
        private readonly Account $accounts,
        private readonly MailService $mail,
    ) {
    }

    /**
     * GET /password-reset — show the self-serve "forgot password"
     * form. Public; the whole point is that the user can't sign in.
     */
    public function requestForm(Request $request): Response
    {
        $this->session->start();
        return $this->view('password-reset/request', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'flash'     => $this->popFlash(),
        ]);
    }

    /**
     * POST /password-reset — handle the form. Always renders the
     * same generic confirmation regardless of whether the email
     * matched a real account (anti-enumeration). When the email DOES
     * match, mint a reset token and email it via Resend.
     *
     * Three deliberate non-features:
     *   1. No rate-limiting here yet. The mint() side invalidates any
     *      prior outstanding token for the same user, so spamming
     *      this endpoint can't flood the user with usable links —
     *      only the most-recent one will work.
     *   2. No "email not configured" branch surfaced to the user.
     *      Local dev / pre-prod just logs the would-be send; the user
     *      sees the same generic confirmation.
     *   3. No CAPTCHA. The 1-hour TTL + invalidate-on-reissue makes
     *      mass abuse low-value; if abuse shows up, a rate-limiter
     *      on the mail-send call is the right next step.
     */
    public function requestSubmit(Request $request): Response
    {
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            $this->session->put('_flash', 'Your session expired. Please try again.');
            return $this->redirect($request->basePath() . '/password-reset');
        }

        $emailRaw = trim((string) $request->input('email', ''));

        // Validate format up-front so a typo gets a usable error
        // rather than a silent "if your email is on file..." dead end.
        if ($emailRaw === '' || ! filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            $this->session->put('_flash', 'Enter a valid email address.');
            return $this->redirect($request->basePath() . '/password-reset');
        }

        $account = $this->accounts->findByEmail($emailRaw);
        if ($account !== null) {
            $mint = $this->resets->mint((int) $account['id']);
            $url  = rtrim($this->absoluteBase($request), '/')
                  . $request->basePath()
                  . '/password-reset/' . $mint['token'];

            $emailStatus = 'mail-disabled';
            if ($this->mail->isConfigured()) {
                $messageId = $this->mail->send(
                    (string) $account['email'],
                    'Reset your PayTracker password',
                    $this->buildResetEmailHtml((string) $account['user'], $url, $mint['expiresAt'])
                );
                $emailStatus = $messageId !== null
                    ? sprintf('emailed (msg %s)', substr($messageId, 0, 12))
                    : 'email-send-failed';
            }

            $this->audit->record(
                AuditLog::ACTION_PASSWORD_RESET_SENT,
                userId: (int) $account['id'],
                ipAddress: $this->clientIp(),
                metadata: [
                    'origin'         => 'self-serve',
                    'target_user_id' => (int) $account['id'],
                    'expires_at'     => $mint['expiresAt'],
                    'email_status'   => $emailStatus,
                ],
            );
        }

        // Always land on the "sent" page — branch above is intentionally
        // a no-op when the email doesn't match, so an attacker can't
        // probe which addresses exist by comparing response shapes.
        return $this->view('password-reset/sent', [
            'base'  => $request->basePath(),
            'email' => $emailRaw,
        ]);
    }

    /**
     * Build the HTML body for the self-serve reset email. Mirrors
     * AdminUsersController::buildResetEmailHtml but reads "you
     * requested" rather than "an administrator issued".
     */
    private function buildResetEmailHtml(string $userName, string $url, string $expiresAt): string
    {
        $safeUser = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $safeUrl  = htmlspecialchars($url,      ENT_QUOTES, 'UTF-8');
        $safeExp  = htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<div style="font:16px/1.5 -apple-system,Segoe UI,sans-serif;color:#101418;max-width:560px;">
    <p>Hi {$safeUser},</p>
    <p>We received a request to reset the password on your PayTracker
       account. Click the button below to choose a new password:</p>
    <p style="margin:1.5rem 0;">
        <a href="{$safeUrl}"
           style="display:inline-block;background:#F97316;color:#fff;text-decoration:none;padding:.6rem 1.4rem;border-radius:6px;">
            Set a new password
        </a>
    </p>
    <p style="font-size:14px;color:#475569;">
        Or paste this URL into your browser:<br>
        <code style="word-break:break-all;">{$safeUrl}</code>
    </p>
    <p style="font-size:14px;color:#475569;">
        The link expires at {$safeExp} UTC (1 hour from when you
        clicked Forgot password).
    </p>
    <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;">
    <p style="font-size:13px;color:#64748b;">
        Didn't request this? You can safely ignore this email —
        your password will not change.
    </p>
</div>
HTML;
    }

    /**
     * Same scheme/host helper used by AdminUsersController. Duplicated
     * rather than extracted because the surface is small and this
     * controller already stands alone otherwise.
     */
    private function absoluteBase(Request $request): string
    {
        $https = ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $proto = $https ? 'https' : 'http';
        $host  = is_string($_SERVER['HTTP_HOST'] ?? null) ? (string) $_SERVER['HTTP_HOST'] : 'paytracker.xyz';
        return $proto . '://' . $host;
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
