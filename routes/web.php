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
use PayTracker\Http\Controllers\AdminAnnouncementsController;
use PayTracker\Http\Controllers\AdminAuditController;
use PayTracker\Http\Controllers\AdminInvitesController;
use PayTracker\Http\Controllers\AdminUsersController;
use PayTracker\Http\Controllers\AnnouncementController;
use PayTracker\Http\Controllers\PasswordResetController;
use PayTracker\Http\Controllers\RegistrationController;
use PayTracker\Http\Controllers\AdminReconcileController;
use PayTracker\Http\Controllers\PayAdminController;
use PayTracker\Http\Controllers\ProfileController;
use PayTracker\Http\Controllers\ReconcileController;
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

    // --- Invite-only registration (public; gated by invite code) -------
    $router->get('/register',  [RegistrationController::class, 'show']);
    $router->post('/register', [RegistrationController::class, 'submit']);

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
    $router->post('/loads/preview',        [LoadEntryController::class, 'preview']);
    $router->get('/loads/{frtl}/edit',     [LoadEntryController::class, 'edit']);
    $router->post('/loads/{frtl}',         [LoadEntryController::class, 'update']);
    $router->post('/loads/{frtl}/delete',  [LoadEntryController::class, 'destroy']);

    // --- Reconcile (driver-facing) -------------------------------------
    // Per-load paid / short / disputed flow. Drivers click through
    // their last 5 pay weeks and mark each load. Disputes can be
    // queued for a batch email to their configured payroll contact.
    $router->get('/reconcile',                       [ReconcileController::class, 'index']);
    $router->post('/reconcile/send-batch',           [ReconcileController::class, 'sendBatch']);
    $router->post('/reconcile/{frtl}/paid',          [ReconcileController::class, 'markPaid']);
    $router->post('/reconcile/{frtl}/short',         [ReconcileController::class, 'markShort']);
    $router->post('/reconcile/{frtl}/dispute',       [ReconcileController::class, 'markDisputed']);
    $router->post('/reconcile/{frtl}/undo',          [ReconcileController::class, 'undo']);
    $router->post('/reconcile/{frtl}/notify',        [ReconcileController::class, 'toggleNotify']);

    // --- Reconcile admin queue (super_admin only) ---------------------
    // Read-only list of every open disputed claim across all drivers.
    $router->get('/admin/reconcile',                 [AdminReconcileController::class, 'index']);

    // --- Admin Panel (admin+) ------------------------------------------
    // The user-management surface: list, ban / unban, delete, mint a
    // 1h single-use password reset link. Role assignment lives behind
    // a Super-Admin-only surface that ships in a follow-up branch.
    $router->get('/admin',                              [AdminUsersController::class, 'index']);
    $router->get('/admin/users/{id}/edit',              [AdminUsersController::class, 'edit']);
    $router->post('/admin/users/{id}/edit',             [AdminUsersController::class, 'update']);
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

    // --- Invite codes (admin+) ---------------------------------------
    // Mint / edit / re-send / revoke single-use registration tokens
    // that gate the public /register path. Admin and Super Admin can
    // both reach this surface -- code issuance is a routine
    // operational tool, not the kind of cross-cutting action we
    // restricted to Super Admin (role assignment, announcements).
    $router->get('/admin/invites',                  [AdminInvitesController::class, 'index']);
    $router->get('/admin/invites/new',              [AdminInvitesController::class, 'create']);
    $router->post('/admin/invites',                 [AdminInvitesController::class, 'store']);
    $router->get('/admin/invites/{id}/edit',        [AdminInvitesController::class, 'editForm']);
    $router->post('/admin/invites/{id}/edit',       [AdminInvitesController::class, 'update']);
    $router->post('/admin/invites/{id}/email',      [AdminInvitesController::class, 'email']);
    $router->post('/admin/invites/{id}/revoke',     [AdminInvitesController::class, 'revoke']);

    // --- Announcements (super_admin only on the admin surface; user-
    // -facing dismiss endpoint is auth-required only) -----------------
    $router->get('/admin/announcements',                       [AdminAnnouncementsController::class, 'index']);
    $router->get('/admin/announcements/new',                   [AdminAnnouncementsController::class, 'create']);
    $router->post('/admin/announcements',                      [AdminAnnouncementsController::class, 'store']);
    $router->get('/admin/announcements/{id}',                  [AdminAnnouncementsController::class, 'show']);
    $router->get('/admin/announcements/{id}/edit',             [AdminAnnouncementsController::class, 'editForm']);
    $router->post('/admin/announcements/{id}/edit',            [AdminAnnouncementsController::class, 'update']);
    $router->post('/admin/announcements/{id}/activate',        [AdminAnnouncementsController::class, 'activate']);
    $router->post('/admin/announcements/{id}/deactivate',      [AdminAnnouncementsController::class, 'deactivate']);
    $router->post('/admin/announcements/{id}/use-template',    [AdminAnnouncementsController::class, 'useTemplate']);
    $router->post('/admin/announcements/{id}/delete',          [AdminAnnouncementsController::class, 'delete']);
    $router->post('/announcements/{id}/dismiss',               [AnnouncementController::class,      'dismiss']);

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
