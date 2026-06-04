<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\PasswordHasher;
use PayTracker\Database\Connection;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;
use PayTracker\Models\InviteCode;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * RegistrationController — the only path to a brand-new account.
 *
 * Public (no auth) by design — the whole point of an invite code is
 * that someone WITHOUT an account uses it. Security comes from:
 *   1. The 8-character uppercase alphanumeric code (~2.8 trillion
 *      key space; every miss audit-logs as USER_REGISTER_FAILED).
 *   2. Transactional consume in InviteCode::consume() so concurrent
 *      submissions can't both win the same code.
 *   3. Username + email + password validation reusing the modern
 *      rules from Account::updateBasics and PasswordResetController.
 *
 * Routes:
 *   GET  /register[?invite=CODE]   — render form, pre-fill from URL
 *   POST /register                 — validate, consume, auto-login
 */
final class RegistrationController extends Controller
{
    /** Match the rest of the modern flow's password floor. */
    private const MIN_PASSWORD_LEN = 12;

    public function __construct(
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly InviteCode $invites,
        private readonly PasswordHasher $hasher,
        private readonly AuditLog $audit,
        private readonly Connection $connection,
    ) {
    }

    /**
     * GET /register — show the form. The invite code from the URL
     * pre-fills the field; we deliberately do NOT lock it (the spec
     * suggests we should, but the user-facing benefit is small and
     * a typed-paste recovery path is more useful than a locked one).
     *
     * If no invite param is supplied, render the form anyway with
     * the field empty -- the POST handler will reject the
     * submission cleanly with a "missing invite" error.
     */
    public function show(Request $request): Response
    {
        $this->session->start();

        $inviteRaw = trim((string) $request->input('invite', ''));
        $invitee   = '';

        // Pre-flight: if the URL invite is live, surface the invitee
        // email it was minted for so we can pre-fill the email
        // input. Doesn't refuse a stale URL here (the user might
        // mistype); that happens on POST.
        if ($inviteRaw !== '') {
            $live = $this->invites->findLive($inviteRaw);
            if (is_array($live) && is_string($live['invitee_email'] ?? null)) {
                $invitee = (string) $live['invitee_email'];
            }
        }

        return $this->view('auth/register', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'minLen'    => self::MIN_PASSWORD_LEN,
            'flash'     => $this->popFlash(),
            'old'       => [
                'invite'   => InviteCode::normalize($inviteRaw),
                'username' => (string) ($this->session->get('_old_reg_username') ?? ''),
                'email'    => (string) ($this->session->get('_old_reg_email')    ?? $invitee),
            ],
        ]);
    }

    /**
     * POST /register — validate, consume the invite, create the
     * account, sign the user in, redirect home.
     */
    public function submit(Request $request): Response
    {
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request, 'Your session expired. Please reload and try again.');
        }

        $inviteRaw = trim((string) $request->input('invite', ''));
        $username  = trim((string) $request->input('username', ''));
        $email     = trim((string) $request->input('email', ''));
        $password  = (string) $request->input('password', '');
        $confirm   = (string) $request->input('password_confirmation', '');

        // Preserve typed inputs across the redirect on validation
        // failure. We do NOT preserve the password fields.
        $this->session->put('_old_reg_username', $username);
        $this->session->put('_old_reg_email',    $email);

        $auditMeta = [
            'ip_address'       => $this->clientIp(),
            'attempted_invite' => InviteCode::normalize($inviteRaw),
            'attempted_user'   => substr($username, 0, 120),
            'attempted_email'  => substr($email, 0, 255),
        ];

        // --- Static input validation ------------------------------
        // We check shape first so a malformed code or username
        // never even tries to find an invite. 422-ish semantics
        // surfaced via flash.
        if ($inviteRaw === '') {
            $this->auditFailure('missing_invite', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, 'You need an invite code to register. Ask an admin to send you one.');
        }
        if (strlen(InviteCode::normalize($inviteRaw)) !== InviteCode::CODE_LEN) {
            $this->auditFailure('malformed_invite', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, 'That doesn\'t look like a valid invite code.');
        }
        if (preg_match(Account::USER_PATTERN, $username) !== 1) {
            $this->auditFailure('bad_username', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, 'Username must be 3-32 characters, letters/digits/dot/underscore/dash only (no spaces or @).');
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->auditFailure('bad_email', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, 'Email is required and must be a valid address.');
        }
        if (strlen($password) < self::MIN_PASSWORD_LEN) {
            $this->auditFailure('weak_password', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, sprintf('Password must be at least %d characters.', self::MIN_PASSWORD_LEN));
        }
        if ($password !== $confirm) {
            $this->auditFailure('password_mismatch', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, 'Passwords do not match.');
        }

        // --- Uniqueness pre-checks --------------------------------
        // The DB has UNIQUE on email; user has no DB unique
        // constraint but we enforce it here. Both checks are best-
        // effort -- the transactional INSERT in consume() is the
        // real source of truth (a race could land here past these
        // checks; the UNIQUE constraint catches it).
        if ($this->lookupExisting('user', $username)) {
            $this->auditFailure('user_taken', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, 'That username is already taken.');
        }
        if ($this->lookupExisting('email', $email)) {
            $this->auditFailure('email_taken', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, 'An account already exists for that email.');
        }

        // --- Atomic consume + insert ------------------------------
        try {
            $newAccountId = $this->invites->consume($inviteRaw, [
                'user'          => $username,
                'email'         => $email,
                'password_hash' => $this->hasher->hash($password),
                'role'          => Account::ROLE_USER,
            ]);
        } catch (\PDOException $e) {
            // Likely a unique-email race. Refuse cleanly.
            $this->auditFailure('insert_collision', $auditMeta + ['error' => $e->getMessage()]);
            return $this->failBackUrl($request, $inviteRaw, 'Could not create the account — please try again. If this persists, contact the admin who sent the invite.');
        }

        if ($newAccountId === null) {
            // Invite was no longer live at consume time (consumed
            // by a concurrent submission, deleted by an admin
            // between page-load and submit, or expired mid-flight).
            $this->auditFailure('invite_not_live', $auditMeta);
            return $this->failBackUrl($request, $inviteRaw, 'That invite is no longer valid. It may have been used, expired, or revoked. Ask the admin for a new one.');
        }

        // --- Success ----------------------------------------------
        $this->audit->record(
            AuditLog::ACTION_INVITE_USED,
            userId: $newAccountId,
            ipAddress: $this->clientIp(),
            metadata: ['invite_code' => InviteCode::normalize($inviteRaw)],
        );
        $this->audit->record(
            AuditLog::ACTION_USER_REGISTERED,
            userId: $newAccountId,
            ipAddress: $this->clientIp(),
            metadata: ['username' => $username, 'email' => $email],
        );

        // Auto-login: stash the identity in the session exactly like
        // AuthService::attempt's success path. We DON'T rotate the
        // session id here -- the user has no prior identity to
        // launder. The next request lands logged-in.
        $this->session->regenerate();
        $this->session->put('account_id',   $newAccountId);
        $this->session->put('account_user', $username);
        $this->session->put('account_role', Account::ROLE_USER);
        $this->session->put('announcement_pending', true);

        // Drop any preserved registration form values now that we
        // succeeded.
        $this->session->forget('_old_reg_username');
        $this->session->forget('_old_reg_email');

        $this->session->put('_flash', sprintf('Welcome, %s — your account is ready.', $username));
        return $this->redirect($request->basePath() . '/');
    }

    // ====================================================================
    // Internal helpers
    // ====================================================================

    /**
     * Look up an existing account by an exact column match. Used
     * for the pre-insert uniqueness check on user / email.
     */
    /**
     * Look up an existing account by an exact column match. Used
     * for the pre-insert uniqueness check on user / email. The
     * column allow-list pins the SQL to one of two safe values --
     * the value is bound as a parameter.
     */
    private function lookupExisting(string $column, string $value): bool
    {
        if (! in_array($column, ['user', 'email'], true)) {
            return false;
        }
        $sql  = 'SELECT 1 FROM `account` WHERE ' . $column . ' = ? LIMIT 1';
        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetchColumn() !== false;
    }

    private function auditFailure(string $reason, array $metadata): void
    {
        $this->audit->record(
            'USER_REGISTER_FAILED',
            userId: null,
            reason: $reason,
            ipAddress: $this->clientIp(),
            metadata: $metadata,
        );
    }

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/register');
    }

    /**
     * Same as failBack but preserves the invite param on the
     * redirect so the form re-renders with the code still in the
     * field. A user who typo'd one field shouldn't have to paste
     * the code in again.
     */
    private function failBackUrl(Request $request, string $inviteRaw, string $message): Response
    {
        $this->session->put('_flash', $message);
        $target = $request->basePath() . '/register';
        $code   = InviteCode::normalize($inviteRaw);
        if ($code !== '' && strlen($code) === InviteCode::CODE_LEN) {
            $target .= '?invite=' . urlencode($code);
        }
        return $this->redirect($target);
    }

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

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
