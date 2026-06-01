<?php

declare(strict_types=1);

use PayTracker\Http\Controllers\DistancesController;
use PayTracker\Http\Controllers\HealthController;
use PayTracker\Http\Controllers\HomeController;
use PayTracker\Http\Controllers\LoadEntryController;
use PayTracker\Http\Controllers\LoadsController;
use PayTracker\Http\Controllers\LocationController;
use PayTracker\Http\Controllers\LoginController;
use PayTracker\Http\Controllers\PayAdminController;
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

    // --- Authentication ------------------------------------------------
    $router->get('/login',  [LoginController::class, 'showForm']);
    $router->post('/login', [LoginController::class, 'submit']);
    $router->post('/logout', [LoginController::class, 'logout']);

    // --- Locations (signed-in) ----------------------------------------
    $router->get('/locations',     [LocationController::class, 'index']);
    $router->get('/locations/new', [LocationController::class, 'create']);
    $router->post('/locations',    [LocationController::class, 'store']);

    // --- City distances (signed-in, read-only for now) ----------------
    $router->get('/distances',     [DistancesController::class, 'index']);

    // --- Driver loads -------------------------------------------------
    // Read-only summary surface + the modern write form (one load per
    // submit; legacy multi-load cookie batch is intentionally not ported).
    $router->get('/loads',         [LoadsController::class,     'index']);
    $router->get('/loads/new',     [LoadEntryController::class, 'create']);
    $router->post('/loads',        [LoadEntryController::class, 'store']);

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
};
