<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\PayReconciliation;

/**
 * AdminReconcileController — Super-Admin queue of every open `disputed`
 * row across drivers, with the note + dollar gap + payroll contact
 * for context.
 *
 * Read-only on this branch. Resolving a dispute lives on the driver's
 * own /reconcile surface (the driver either Undoes the dispute or
 * downgrades it to paid / short once it's resolved). Giving the
 * Super Admin a write-side here would create two writers for the
 * same row and we want a single source of truth per claim.
 */
final class AdminReconcileController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PayReconciliation $recon,
    ) {
    }

    public function index(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_SUPER_ADMIN)) !== null) {
            return $denied;
        }
        return $this->view('admin/reconcile/index', [
            'base'   => $request->basePath(),
            'driver' => $account,
            'rows'   => $this->recon->openDisputesForAdmin(),
        ]);
    }
}
