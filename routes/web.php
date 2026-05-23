<?php

declare(strict_types=1);

use PayTracker\Http\Controllers\HealthController;
use PayTracker\Http\Controllers\HomeController;
use PayTracker\Http\Controllers\LocationController;
use PayTracker\Http\Controllers\LoginController;
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
};
