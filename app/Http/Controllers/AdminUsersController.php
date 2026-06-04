<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;
use PayTracker\Models\PasswordReset;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\MailService;

/**
 * AdminUsersController — the user-management surface of the
 * Admin Panel.
 *
 * Routes (all admin+):
 *   GET  /admin                            — landing + user list
 *   POST /admin/users/{id}/ban             — set banned_at = UTC_NOW()
 *   POST /admin/users/{id}/unban           — clear banned_at + lockout counters
 *   POST /admin/users/{id}/delete          — hard delete
 *   POST /admin/users/{id}/reset-password  — mint a 1h single-use token,
 *                                            return URL in flash (email
 *                                            delivery lands in branch 4 via
 *                                            Resend; for now the URL is
 *                                            shown on screen so admins can
 *                                            copy/paste it)
 *
 * Security posture: every action verifies CSRF AND blocks self-
 * targeted destructive operations. An admin can't ban / delete /
 * reset their OWN account through this surface — that would
 * silently log them out mid-action and is almost never what an
 * admin actually means.
 *
 * Role assignment is intentionally NOT in this controller —
 * Super Admin only, ships in branch 5.
 */
final class AdminUsersController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly Account $accounts,
        private readonly PasswordReset $resets,
        private readonly AuditLog $audit,
        private readonly MailService $mail,
    ) {
    }

    /**
     * GET /admin — landing page. Renders the user table plus any
     * outstanding flash message (success notes, error notes, or a
     * one-time password-reset URL).
     */
    public function index(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }
        $this->session->start();

        // --- Filter / search / pagination params -----------------------
        // Defaults: 50 per page, no search, no role filter, hide spam
        // (the legacy DB has years of bot-registration junk we don't
        // want crowding the table). All toggleable from the form.
        $search       = trim((string) $request->input('search', ''));
        $roleRaw      = (string) $request->input('role', '');
        $roleFilter   = in_array($roleRaw, Account::ROLES, true) ? $roleRaw : null;
        $includeSpam  = (string) $request->input('include_spam', '') === '1';
        $perPageRaw   = (string) $request->input('per_page', '50');
        $perPage      = ctype_digit($perPageRaw) && (int) $perPageRaw > 0 ? min(200, (int) $perPageRaw) : 50;
        $pageRaw      = (string) $request->input('page', '1');
        $page         = ctype_digit($pageRaw) && (int) $pageRaw > 0 ? (int) $pageRaw : 1;
        $offset       = ($page - 1) * $perPage;

        $page_data = $this->accounts->pageForAdmin($perPage, $offset, $search, $roleFilter, $includeSpam);

        return $this->view('admin/index', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'actor'     => $account,
            'users'     => $page_data['rows'],
            'flash'     => $this->popFlash(),
            'isSuperAdmin' => Account::hasRole($account, Account::ROLE_SUPER_ADMIN),
            'filters'   => [
                'search'        => $search,
                'role'          => $roleRaw,
                'include_spam'  => $includeSpam,
                'per_page'      => $perPage,
                'page'          => $page,
            ],
            'counts' => [
                'total'        => $page_data['total'],
                'shown'        => count($page_data['rows']),
                'matching'     => $page_data['totalAfterFilters'],
                'total_pages'  => max(1, (int) ceil($page_data['totalAfterFilters'] / max(1, $perPage))),
            ],
        ]);
    }

    public function ban(Request $request, string $id): Response
    {
        return $this->mutate($request, $id, 'ban', function (array $actor, array $target): string {
            $this->accounts->ban((int) $target['id']);
            $this->audit->record(
                AuditLog::ACTION_USER_BANNED,
                userId: (int) $actor['id'],
                ipAddress: $this->clientIp(),
                metadata: [
                    'target_user_id'   => (int) $target['id'],
                    'target_user_name' => (string) $target['user'],
                ],
            );
            return sprintf('Banned %s (id %d).', $target['user'], (int) $target['id']);
        });
    }

    public function unban(Request $request, string $id): Response
    {
        return $this->mutate($request, $id, 'unban', function (array $actor, array $target): string {
            $this->accounts->unban((int) $target['id']);
            $this->audit->record(
                AuditLog::ACTION_USER_UNBANNED,
                userId: (int) $actor['id'],
                ipAddress: $this->clientIp(),
                metadata: [
                    'target_user_id'   => (int) $target['id'],
                    'target_user_name' => (string) $target['user'],
                ],
            );
            return sprintf('Lifted ban on %s (id %d).', $target['user'], (int) $target['id']);
        });
    }

    /**
     * POST /admin/users/{id}/role — assign a new role.
     *
     * Super-Admin ONLY (stricter than the rest of this controller,
     * which is admin+). Role assignment carries enough blast radius
     * (granting Admin = read+write access to pay rates) that we
     * gate it at the highest tier even within the admin panel.
     *
     * Policy enforcement layered on top of the base mutate() guard:
     *   1. Inside mutate() — the standard CSRF / id / target /
     *      self-target checks.
     *   2. Here — Super Admin requirement (stricter than admin+).
     *   3. Here — sole-super-admin safeguard: cannot demote the
     *      last Super Admin account, period (not even by a
     *      different Super Admin, because there isn't one).
     */
    public function setRole(Request $request, string $id): Response
    {
        // Pre-flight Super Admin gate. We do this BEFORE entering
        // mutate() so a base Admin can't even render the 'invalid
        // role' error — the 403 page is what they see.
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_SUPER_ADMIN)) !== null) {
            return $denied;
        }

        $newRole = (string) $request->input('role', '');
        if (! in_array($newRole, Account::ROLES, true)) {
            $this->session->start();
            $this->session->put('_flash', 'Invalid role. Must be one of: ' . implode(', ', Account::ROLES) . '.');
            return $this->redirect($request->basePath() . '/admin');
        }

        return $this->mutate($request, $id, 'change role', function (array $actor, array $target) use ($newRole): string {
            $previousRole = is_string($target['role'] ?? null) ? (string) $target['role'] : 'user';
            if ($previousRole === $newRole) {
                return sprintf('No change — %s already has role "%s".', $target['user'], $newRole);
            }

            // Sole-super-admin safeguard: refuse to demote the LAST
            // super_admin account. The self-target guard in mutate()
            // already prevents self-demotion, so this only fires when
            // a super_admin tries to demote ANOTHER super_admin who
            // would be the last one (i.e., this actor is now demoting
            // themselves into solitary master status -- still fine --
            // OR the actor is being demoted by no one because there
            // isn't a second super_admin to do so). The
            // mutate() self-guard handles the latter; this branch
            // covers the case where someone tries to demote the only
            // other super_admin AND the actor isn't one. Belt and
            // braces: even Super Admin can't strand the system
            // without a master.
            if ($previousRole === Account::ROLE_SUPER_ADMIN && $newRole !== Account::ROLE_SUPER_ADMIN) {
                $superCount = $this->accounts->countByRole(Account::ROLE_SUPER_ADMIN);
                if ($superCount <= 1) {
                    throw new \RuntimeException(
                        'Refusing to demote the only Super Admin — promote another account to Super Admin first.'
                    );
                }
            }

            $this->accounts->setRole((int) $target['id'], $newRole);
            $this->audit->record(
                AuditLog::ACTION_USER_ROLE_CHANGED,
                userId: (int) $actor['id'],
                ipAddress: $this->clientIp(),
                metadata: [
                    'target_user_id'   => (int) $target['id'],
                    'target_user_name' => (string) $target['user'],
                    'previous_role'    => $previousRole,
                    'new_role'         => $newRole,
                ],
            );
            return sprintf(
                'Changed role of %s (id %d): %s → %s.',
                $target['user'],
                (int) $target['id'],
                $previousRole,
                $newRole
            );
        });
    }

    public function delete(Request $request, string $id): Response
    {
        return $this->mutate($request, $id, 'delete', function (array $actor, array $target): string {
            $this->accounts->deleteAccount((int) $target['id']);
            $this->audit->record(
                AuditLog::ACTION_USER_DELETED,
                userId: (int) $actor['id'],
                ipAddress: $this->clientIp(),
                metadata: [
                    'target_user_id'   => (int) $target['id'],
                    'target_user_name' => (string) $target['user'],
                ],
            );
            return sprintf('Deleted %s (id %d).', $target['user'], (int) $target['id']);
        });
    }

    /**
     * Generate a single-use password-reset token. The raw URL is
     * returned in the flash banner so the admin can copy it; the
     * email-delivery side ships in branch 4 via Resend.
     */
    public function resetPassword(Request $request, string $id): Response
    {
        return $this->mutate($request, $id, 'reset-password', function (array $actor, array $target) use ($request): string {
            $mint = $this->resets->mint((int) $target['id'], (int) $actor['id']);
            $url  = rtrim($this->absoluteBase($request), '/')
                  . $request->basePath()
                  . '/password-reset/' . $mint['token'];

            // Attempt email delivery via Resend. The flash banner still
            // shows the URL so a delivery failure doesn't strand the
            // admin -- they can always copy/paste manually.
            $emailStatus = 'no email on file';
            $targetEmail = is_string($target['email'] ?? null) ? (string) $target['email'] : '';
            if ($targetEmail !== '') {
                if (! $this->mail->isConfigured()) {
                    $emailStatus = 'Resend not configured';
                } else {
                    $messageId = $this->mail->send(
                        $targetEmail,
                        'Reset your PayTracker password',
                        $this->buildResetEmailHtml((string) $target['user'], $url, $mint['expiresAt'])
                    );
                    $emailStatus = $messageId !== null
                        ? sprintf('emailed to %s (msg %s)', $targetEmail, substr($messageId, 0, 12))
                        : sprintf('email to %s FAILED — see Resend logs', $targetEmail);
                }
            }

            $this->audit->record(
                AuditLog::ACTION_PASSWORD_RESET_SENT,
                userId: (int) $actor['id'],
                ipAddress: $this->clientIp(),
                metadata: [
                    'target_user_id'   => (int) $target['id'],
                    'target_user_name' => (string) $target['user'],
                    'expires_at'       => $mint['expiresAt'],
                    'email_status'     => $emailStatus,
                ],
            );
            return sprintf(
                'Reset link for %s (expires %s UTC, %s): %s',
                $target['user'],
                $mint['expiresAt'],
                $emailStatus,
                $url
            );
        });
    }

    /**
     * Build the HTML body for the reset-password email. Kept minimal:
     * an inline-styled card with the link, expiry note, and a
     * "didn't request this?" footer. No external images / CSS so
     * the message renders the same in every client.
     */
    private function buildResetEmailHtml(string $userName, string $url, string $expiresAt): string
    {
        $safeUser = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $safeUrl  = htmlspecialchars($url,      ENT_QUOTES, 'UTF-8');
        $safeExp  = htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<div style="font:16px/1.5 -apple-system,Segoe UI,sans-serif;color:#101418;max-width:560px;">
    <p>Hi {$safeUser},</p>
    <p>An administrator has issued a password reset link for your
       PayTracker account. Click the button below to set a new password:</p>
    <p style="margin:1.5rem 0;">
        <a href="{$safeUrl}"
           style="display:inline-block;background:#1f6feb;color:#fff;text-decoration:none;padding:.6rem 1.4rem;border-radius:6px;">
            Set a new password
        </a>
    </p>
    <p style="color:#5a6470;font-size:14px;">
        The link expires at <strong>{$safeExp} UTC</strong> and can
        only be used once. If you didn't request this, you can safely
        ignore the message &mdash; the link won't grant access to anyone
        who doesn't click it before it expires.
    </p>
    <p style="color:#5a6470;font-size:14px;">
        Trouble with the button? Copy and paste this URL into your
        browser:<br>
        <span style="word-break:break-all;">{$safeUrl}</span>
    </p>
</div>
HTML;
    }

    /**
     * Shared body for mutate-and-flash actions. Centralises:
     *   1. Auth + RBAC.
     *   2. CSRF verification.
     *   3. Numeric-id sanitation.
     *   4. Target lookup + existence check.
     *   5. Self-targeting refusal for the four destructive actions.
     *   6. Exception capture → user-friendly flash.
     *
     * @param callable(array<string,mixed>, array<string,mixed>): string $body
     *        Action body receiving (actor, target) and returning the success
     *        flash message.
     */
    private function mutate(Request $request, string $idRaw, string $action, callable $body): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }
        $this->session->start();

        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request, 'Your session expired. Please try again.');
        }

        if (! ctype_digit($idRaw) || (int) $idRaw <= 0) {
            return $this->failBack($request, 'Invalid user id.');
        }
        $targetId = (int) $idRaw;
        $target   = $this->accounts->findById($targetId);
        if ($target === null) {
            return $this->failBack($request, sprintf('No account with id %d.', $targetId));
        }

        // Self-target guard. Banning / deleting / resetting your own
        // account through the admin panel is almost certainly an
        // accident; refuse it and force the admin to do it some
        // other way (CLI / another admin) if it's genuinely intended.
        if ((int) $account['id'] === $targetId) {
            return $this->failBack($request, sprintf(
                'Cannot %s your own account from the admin panel.',
                $action
            ));
        }

        try {
            $message = $body($account, $target);
        } catch (\Throwable $e) {
            return $this->failBack($request, sprintf('%s failed: %s', $action, $e->getMessage()));
        }

        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/admin');
    }

    /**
     * Reconstruct the absolute URL prefix (scheme + host) for the
     * current request, so the reset link we hand the admin is a
     * full URL they can paste anywhere. We avoid hard-coding the
     * domain so this works on both preview and production.
     */
    private function absoluteBase(Request $request): string
    {
        $https = ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $proto = $https ? 'https' : 'http';
        $host  = is_string($_SERVER['HTTP_HOST'] ?? null) ? (string) $_SERVER['HTTP_HOST'] : 'paytracker.xyz';
        return $proto . '://' . $host;
    }

    /**
     * Best-effort client IP for audit rows. Mirrors AuthService::clientIp;
     * duplicated here rather than promoted to a shared helper because the
     * surface is small and the dependency hierarchy stays simpler this
     * way.
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

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/admin');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
