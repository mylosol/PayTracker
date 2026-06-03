<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;

/**
 * Base controller. Concrete controllers extend this class purely so that
 * shared helpers (view, json, redirect, RBAC) can grow here without
 * churning every controller signature.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(view($template, $data), $status);
    }

    protected function json(mixed $payload, int $status = 200): Response
    {
        return Response::json($payload, $status);
    }

    protected function redirect(string $location, int $status = 302): Response
    {
        return Response::redirect($location, $status);
    }

    /**
     * RBAC gate: returns null when the caller has at least the required
     * role, or a 403 Response when they don't. Designed for the early-
     * exit pattern controllers already use for anonymous redirects:
     *
     *     $account = $this->auth->currentAccount();
     *     if ($account === null) { return $this->redirect('/login'); }
     *     if ($denied = $this->requireRole($request, $account, 'admin')) {
     *         return $denied;
     *     }
     *
     * Why a 403 page rather than a redirect:
     *   A signed-in User who hits /pay-admin by URL guessing has
     *   ALREADY proven their identity — sending them to /login would
     *   either confuse them ("but I'm signed in") or, worse, suggest
     *   a fresh login would grant access. A blunt 403 with a "go
     *   home" link surfaces the real answer: this isn't for you.
     *
     * Anonymous callers shouldn't reach this method — callers must
     * filter `currentAccount() === null` first. We treat a null
     * account here as "definitely not authorised" so a missed check
     * fails closed rather than open.
     *
     * @param array<string,mixed>|null $account
     */
    protected function requireRole(Request $request, ?array $account, string $minimumRole): ?Response
    {
        if (Account::hasRole($account, $minimumRole)) {
            return null;
        }
        return $this->view('errors/403', [
            'base'         => $request->basePath(),
            'requiredRole' => $minimumRole,
            'actorRole'    => is_array($account) && is_string($account['role'] ?? null)
                ? (string) $account['role']
                : 'anonymous',
        ], 403);
    }
}
