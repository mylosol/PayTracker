<?php

declare(strict_types=1);

use PayTracker\Http\Controllers\DashboardController;
use PayTracker\Http\Controllers\DistancesController;
use PayTracker\Http\Controllers\HealthController;
use PayTracker\Http\Controllers\HomeController;
use PayTracker\Http\Controllers\LoadEntryController;
use PayTracker\Http\Controllers\LoadsController;
use PayTracker\Http\Controllers\LocationController;
use PayTracker\Http\Controllers\LoginController;
use PayTracker\Http\Controllers\AdminAuditController;
use PayTracker\Http\Controllers\AdminUsersController;
use PayTracker\Http\Controllers\PasswordResetController;
use PayTracker\Http\Controllers\PayAdminController;
use PayTracker\Http\Controllers\ProfileController;
use PayTracker\Http\Controllers\StaticPagesController;
use PayTracker\Http\Router;

/*
 * Web routes — registered against the Router built in bootstrap/app.php.
 *
 * The route file returns a closure rather than mutating a global so the
 * router stays testable: a test can construct a Router and apply this
 * closure without polluting any container state.
 */
return static function (Router $router): void {
    // --- Public --------------------------------------------------------
    $router->get('/',            [HomeController::class,   'index']);
    $router->get('/health',      [HealthController::class, 'index']);
    $router->get('/health.json', [HealthController::class, 'jsonResponse']);

    // --- Static housekeeping pages (public, no DB) --------------------
    // These exist outside the auth boundary so a brand-new driver can
    // read the tutorial / FAQ and find contact details before they
    // have an account. None of them touch the database.
    $router->get('/tutorial', [StaticPagesController::class, 'tutorial']);
    $router->get('/faq',      [StaticPagesController::class, 'faq']);
    $router->get('/about',    [StaticPagesController::class, 'about']);
    $router->get('/contact',  [StaticPagesController::class, 'contact']);

    // --- Authentication ------------------------------------------------
    $router->get('/login',  [LoginController::class, 'showForm']);
    $router->post('/login', [LoginController::class, 'submit']);
    $router->post('/logout', [LoginController::class, 'logout']);

    // --- Driver dashboard (signed-in) ---------------------------------
    // "My pay" page: the signed-in driver's loads for a date window
    // (default = today in APP_TIMEZONE) plus the day's pay totals.
    $router->get('/dashboard',            [DashboardController::class, 'index']);
    $router->post('/dashboard/recompute', [DashboardController::class, 'recompute']);

    // --- Driver profile (signed-in) ----------------------------------
    // Hire date (tenure band) + default shift. Both feed PayCalculator
    // via the variables blob snapshotted into driver_loads at insert.
    $router->get('/profile',  [ProfileController::class, 'show']);
    $router->post('/profile', [ProfileController::class, 'save']);

    // --- Locations (signed-in) ----------------------------------------
    $router->get('/locations',     [LocationController::class, 'index']);
    $router->get('/locations/new', [LocationController::class, 'create']);
    $router->post('/locations',    [LocationController::class, 'store']);

    // --- City distances (signed-in, read-only for now) ----------------
    $router->get('/distances',     [DistancesController::class, 'index']);

    // --- Driver loads -------------------------------------------------
    // Read-only summary surface + the modern write form (one load per
    // submit; legacy multi-load cookie batch is intentionally not ported).
    $router->get('/loads',                 [LoadsController::class,     'index']);
    $router->get('/loads/new',             [LoadEntryController::class, 'create']);
    $router->post('/loads',                [LoadEntryController::class, 'store']);
    $router->get('/loads/{frtl}/edit',     [LoadEntryController::class, 'edit']);
    $router->post('/loads/{frtl}',         [LoadEntryController::class, 'update']);
    $router->post('/loads/{frtl}/delete',  [LoadEntryController::class, 'destroy']);

    // --- Admin Panel (admin+) ------------------------------------------
    // The user-management surface: list, ban / unban, delete, mint a
    // 1h single-use password reset link. Role assignment lives behind
    // a Super-Admin-only surface that ships in a follow-up branch.
    $router->get('/admin',                              [AdminUsersController::class, 'index']);
    $router->post('/admin/users/{id}/ban',              [AdminUsersController::class, 'ban']);
    $router->post('/admin/users/{id}/unban',            [AdminUsersController::class, 'unban']);
    $router->post('/admin/users/{id}/delete',           [AdminUsersController::class, 'delete']);
    $router->post('/admin/users/{id}/reset-password',   [AdminUsersController::class, 'resetPassword']);
    // Role assignment — Super Admin only. The controller enforces this
    // separately from the rest of /admin so a base Admin can't even
    // render the form-validation error (the 403 page is what they see).
    $router->post('/admin/users/{id}/role',              [AdminUsersController::class, 'setRole']);

    // Audit + diagnostics — read-only viewers, admin+.
    $router->get('/admin/audit',         [AdminAuditController::class, 'audit']);
    $router->get('/admin/diagnostics',   [AdminAuditController::class, 'diagnostics']);

    // --- Password reset claim (public, token-gated) -------------------
    // No auth requirement — the user can't log in, that's the whole
    // point of the reset link. Security comes from the token itself:
    // 256 bits of entropy, stored hashed, expires in 1 hour, single
    // use. The admin who minted the token sees the URL once in their
    // flash banner (and, in a follow-up branch, gets it emailed via
    // Resend automatically).
    $router->get('/password-reset/{token}',  [PasswordResetController::class, 'show']);
    $router->post('/password-reset/{token}', [PasswordResetController::class, 'submit']);

    // --- Pay-rate admin (signed-in) -----------------------------------
    // Modern replacement for the four legacy pay-admin pages. Manages
    // pay_rates(terminal, trip_type, stage, miles, rate) with a
    // default → current → draft staging model. PayCalculator that
    // consumes these rates ships in a follow-up branch.
    $router->get('/pay-admin',                 [PayAdminController::class, 'index']);
    $router->post('/pay-admin/draft/start',    [PayAdminController::class, 'startDraft']);
    $router->post('/pay-admin/draft/upsert',   [PayAdminController::class, 'upsertDraftTier']);
    $router->post('/pay-admin/draft/delete',   [PayAdminController::class, 'deleteDraftTier']);
    $router->post('/pay-admin/draft/promote',  [PayAdminController::class, 'promoteDraft']);
    $router->post('/pay-admin/reset',          [PayAdminController::class, 'resetCurrent']);
    $router->post('/pay-admin/recompute',      [PayAdminController::class, 'recompute']);
};
