<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;
use PayTracker\Models\InviteCode;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\MailService;

/**
 * AdminInvitesController — Admin+ surface for issuing, editing, and
 * revoking invite codes that gate the public /register path.
 *
 * Routes (admin+ gated):
 *   GET  /admin/invites                       list every code
 *   GET  /admin/invites/new                   create form
 *   POST /admin/invites                       create
 *   GET  /admin/invites/{id}/edit             edit form
 *   POST /admin/invites/{id}/edit             update basics
 *   POST /admin/invites/{id}/email            re-send the invite email
 *   POST /admin/invites/{id}/revoke           hard delete
 *
 * Why admin+ (not super_admin): Robert confirmed Admin and above
 * can mint codes. Code-gating registration is a routine operational
 * tool, not the kind of cross-cutting site action (role assignment,
 * announcements) that we restricted to Super Admin.
 *
 * Audit-log every mutate so we can answer "who issued the code
 * this person used to register" later.
 */
final class AdminInvitesController extends Controller
{
    /** Max length for the optional invitee_email field. */
    private const MAX_EMAIL_LEN = 255;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly InviteCode $invites,
        private readonly AuditLog $audit,
        private readonly MailService $mail,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        return $this->view('admin/invites/index', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'rows'      => $this->invites->allForAdmin(),
            'flash'     => $this->popFlash(),
            'baseUrl'   => $this->absoluteBase($request),
            'basePath'  => $request->basePath(),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        return $this->view('admin/invites/new', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'flash'     => $this->popFlash(),
            'old'       => [
                'invitee_email' => $this->session->get('_old_invite_email')       ?? '',
                'expires_at'    => $this->session->get('_old_invite_expires_at')  ?? '',
                'auto_delete'   => $this->session->get('_old_invite_auto_delete') ?? '1',
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $actor = $this->auth->currentAccount();
        // gate() already ensured non-null.

        $email      = trim((string) $request->input('invitee_email', ''));
        $expiresAt  = trim((string) $request->input('expires_at', ''));
        $autoDelete = (string) $request->input('auto_delete', '1') === '1';

        $this->session->put('_old_invite_email',       $email);
        $this->session->put('_old_invite_expires_at',  $expiresAt);
        $this->session->put('_old_invite_auto_delete', $autoDelete ? '1' : '0');

        if (($err = $this->validateBasics($email, $expiresAt)) !== null) {
            return $this->failBack($request->basePath() . '/admin/invites/new', $err);
        }
        $normalizedExpiry = $this->normalizeExpiry($expiresAt);

        try {
            $minted = $this->invites->create(
                $email === '' ? null : $email,
                $normalizedExpiry,
                (int) ($actor['id'] ?? 0),
                $autoDelete
            );
        } catch (\Throwable $e) {
            return $this->failBack($request->basePath() . '/admin/invites/new', 'Could not create invite: ' . $e->getMessage());
        }

        // Compose the shareable URL and try to email it when an
        // invitee_email is set + Resend is configured. Email
        // outcome rolls into the flash + audit metadata exactly
        // like AdminUsersController::resetPassword does.
        $url = $this->buildInviteUrl($request, $minted['code']);
        $emailStatus = $this->maybeEmailInvite($email, $url, $normalizedExpiry);

        $this->audit->record(
            AuditLog::ACTION_INVITE_CREATED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'invite_id'     => $minted['id'],
                'code'          => $minted['code'],
                'invitee_email' => $email !== '' ? $email : null,
                'expires_at'    => $normalizedExpiry,
                'auto_delete'   => $autoDelete,
                'email_status'  => $emailStatus,
            ],
        );

        foreach (['_old_invite_email', '_old_invite_expires_at', '_old_invite_auto_delete'] as $k) {
            $this->session->forget($k);
        }

        $this->session->put('_flash', sprintf(
            'Created invite %s (%s): %s',
            $minted['code'],
            $emailStatus,
            $url
        ));
        return $this->redirect($request->basePath() . '/admin/invites');
    }

    public function editForm(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        return $this->view('admin/invites/edit', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'row'       => $row,
            'flash'     => $this->popFlash(),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        $actor = $this->auth->currentAccount();

        $email      = trim((string) $request->input('invitee_email', ''));
        $expiresAt  = trim((string) $request->input('expires_at', ''));
        $autoDelete = (string) $request->input('auto_delete', '1') === '1';

        if (($err = $this->validateBasics($email, $expiresAt)) !== null) {
            return $this->failBack(
                $request->basePath() . '/admin/invites/' . (int) $row['id'] . '/edit',
                $err
            );
        }
        $normalizedExpiry = $this->normalizeExpiry($expiresAt);

        try {
            $this->invites->update(
                (int) $row['id'],
                $email === '' ? null : $email,
                $normalizedExpiry,
                $autoDelete
            );
        } catch (\Throwable $e) {
            return $this->failBack(
                $request->basePath() . '/admin/invites/' . (int) $row['id'] . '/edit',
                'Could not save: ' . $e->getMessage()
            );
        }

        $this->audit->record(
            AuditLog::ACTION_INVITE_UPDATED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'invite_id'     => (int) $row['id'],
                'code'          => (string) $row['code'],
                'invitee_email' => $email !== '' ? $email : null,
                'expires_at'    => $normalizedExpiry,
                'auto_delete'   => $autoDelete,
            ],
        );

        $this->session->put('_flash', sprintf('Updated invite %s.', (string) $row['code']));
        return $this->redirect($request->basePath() . '/admin/invites');
    }

    public function email(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        $actor = $this->auth->currentAccount();

        $invitee = is_string($row['invitee_email'] ?? null) ? (string) $row['invitee_email'] : '';
        if ($invitee === '') {
            return $this->failBack(
                $request->basePath() . '/admin/invites',
                'Cannot email this invite — no recipient address on the row. Edit and add an email first.'
            );
        }
        if (is_string($row['used_at'] ?? null) && $row['used_at'] !== '') {
            return $this->failBack(
                $request->basePath() . '/admin/invites',
                'Cannot email this invite — it has already been consumed.'
            );
        }

        $url    = $this->buildInviteUrl($request, (string) $row['code']);
        $expiry = is_string($row['expires_at'] ?? null) ? (string) $row['expires_at'] : null;
        $status = $this->maybeEmailInvite($invitee, $url, $expiry);

        $this->audit->record(
            AuditLog::ACTION_INVITE_EMAILED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'invite_id'     => (int) $row['id'],
                'code'          => (string) $row['code'],
                'invitee_email' => $invitee,
                'email_status'  => $status,
            ],
        );

        $this->session->put('_flash', sprintf('Re-sent invite %s: %s', (string) $row['code'], $status));
        return $this->redirect($request->basePath() . '/admin/invites');
    }

    public function revoke(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        $actor = $this->auth->currentAccount();

        try {
            $this->invites->delete((int) $row['id']);
        } catch (\Throwable $e) {
            return $this->failBack($request->basePath() . '/admin/invites', 'Could not revoke: ' . $e->getMessage());
        }

        $this->audit->record(
            AuditLog::ACTION_INVITE_REVOKED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'invite_id' => (int) $row['id'],
                'code'      => (string) $row['code'],
            ],
        );

        $this->session->put('_flash', sprintf('Revoked invite %s.', (string) $row['code']));
        return $this->redirect($request->basePath() . '/admin/invites');
    }

    // ====================================================================
    // Internal helpers
    // ====================================================================

    /**
     * Auth + admin+ RBAC gate plus optional CSRF check for POSTs.
     */
    private function gate(Request $request, bool $csrfCheck = false): ?Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }
        $this->session->start();
        if ($csrfCheck && ! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request->basePath() . '/admin/invites', 'Your session expired. Please try again.');
        }
        return null;
    }

    /** @return array<string,mixed>|Response */
    private function loadOrRedirect(Request $request, string $idRaw): array|Response
    {
        if (! ctype_digit($idRaw) || (int) $idRaw <= 0) {
            return $this->failBack($request->basePath() . '/admin/invites', 'Invalid invite id.');
        }
        $row = $this->invites->findById((int) $idRaw);
        if ($row === null) {
            return $this->failBack(
                $request->basePath() . '/admin/invites',
                sprintf('No invite with id %d.', (int) $idRaw)
            );
        }
        return $row;
    }

    /**
     * Validation shared between create + update. Email is optional;
     * when present it must be valid. Expiry is optional; when
     * present it must be a parseable datetime-local OR full MySQL
     * format.
     */
    private function validateBasics(string $email, string $expiresAt): ?string
    {
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Invitee email is not a valid address.';
        }
        if ($email !== '' && strlen($email) > self::MAX_EMAIL_LEN) {
            return sprintf('Invitee email is too long (max %d chars).', self::MAX_EMAIL_LEN);
        }
        if ($expiresAt !== '') {
            $ok = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $expiresAt) === 1
                || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $expiresAt) === 1;
            if (! $ok) {
                return 'Expiry must be YYYY-MM-DDTHH:MM (datetime-local) or YYYY-MM-DD HH:MM[:SS].';
            }
        }
        return null;
    }

    /**
     * Treat the form's datetime-local value as APP_TIMEZONE local
     * time and convert to UTC for storage. The browser doesn't
     * send tzinfo with datetime-local; we anchor it to the
     * configured app timezone so admins don't have to translate
     * UTC in their head while filling out the form.
     */
    private function normalizeExpiry(string $expiresAt): ?string
    {
        return local_input_to_utc($expiresAt);
    }

    private function buildInviteUrl(Request $request, string $code): string
    {
        return rtrim($this->absoluteBase($request), '/')
            . $request->basePath()
            . '/register?invite=' . $code;
    }

    /**
     * Best-effort send via Resend; returns a human-readable status
     * string used in both flash + audit metadata. Mirrors the
     * pattern in AdminUsersController::resetPassword so admins
     * get consistent feedback.
     */
    private function maybeEmailInvite(string $invitee, string $url, ?string $expiry): string
    {
        if ($invitee === '') {
            return 'no email on file';
        }
        if (! $this->mail->isConfigured()) {
            return 'Resend not configured';
        }
        $messageId = $this->mail->send(
            $invitee,
            'You\'re invited to PayTracker',
            $this->buildInviteEmailHtml($url, $expiry)
        );
        return $messageId !== null
            ? sprintf('emailed to %s (msg %s)', $invitee, substr($messageId, 0, 12))
            : sprintf('email to %s FAILED — see Resend logs', $invitee);
    }

    private function buildInviteEmailHtml(string $url, ?string $expiry): string
    {
        $safeUrl    = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $expiryNote = $expiry !== null
            ? '<p style="color:#5a6470;font-size:14px;">This invite expires <strong>'
                . htmlspecialchars($expiry, ENT_QUOTES, 'UTF-8') . ' UTC</strong>.</p>'
            : '<p style="color:#5a6470;font-size:14px;">This invite has no expiry; use it whenever you\'re ready.</p>';
        return <<<HTML
<div style="font:16px/1.5 -apple-system,Segoe UI,sans-serif;color:#101418;max-width:560px;">
    <p>Hi there,</p>
    <p>You've been invited to create an account on PayTracker. Click
       the button below to set your username and password:</p>
    <p style="margin:1.5rem 0;">
        <a href="{$safeUrl}"
           style="display:inline-block;background:#1f6feb;color:#fff;text-decoration:none;padding:.6rem 1.4rem;border-radius:6px;">
            Create your account
        </a>
    </p>
    {$expiryNote}
    <p style="color:#5a6470;font-size:14px;">
        Trouble with the button? Copy and paste this URL into your
        browser:<br>
        <span style="word-break:break-all;">{$safeUrl}</span>
    </p>
</div>
HTML;
    }

    private function absoluteBase(Request $request): string
    {
        $https = ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $proto = $https ? 'https' : 'http';
        $host  = is_string($_SERVER['HTTP_HOST'] ?? null) ? (string) $_SERVER['HTTP_HOST'] : 'paytracker.xyz';
        return $proto . '://' . $host;
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

    private function failBack(string $url, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($url);
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
