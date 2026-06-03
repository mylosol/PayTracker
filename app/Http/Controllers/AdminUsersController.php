<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\PasswordReset;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

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

        return $this->view('admin/index', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'actor'     => $account,
            'users'     => $this->accounts->allForAdmin(),
            'flash'     => $this->popFlash(),
            'isSuperAdmin' => Account::hasRole($account, Account::ROLE_SUPER_ADMIN),
        ]);
    }

    public function ban(Request $request, string $id): Response
    {
        return $this->mutate($request, $id, 'ban', function (array $actor, array $target): string {
            $this->accounts->ban((int) $target['id']);
            return sprintf('Banned %s (id %d).', $target['user'], (int) $target['id']);
        });
    }

    public function unban(Request $request, string $id): Response
    {
        return $this->mutate($request, $id, 'unban', function (array $actor, array $target): string {
            $this->accounts->unban((int) $target['id']);
            return sprintf('Lifted ban on %s (id %d).', $target['user'], (int) $target['id']);
        });
    }

    public function delete(Request $request, string $id): Response
    {
        return $this->mutate($request, $id, 'delete', function (array $actor, array $target): string {
            $this->accounts->deleteAccount((int) $target['id']);
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
            return sprintf(
                'Reset link for %s (expires %s UTC): %s',
                $target['user'],
                $mint['expiresAt'],
                $url
            );
        });
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
