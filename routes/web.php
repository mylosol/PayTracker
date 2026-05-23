<?php

declare(strict_types=1);

use PayTracker\Http\Controllers\HealthController;
use PayTracker\Http\Controllers\HomeController;
use PayTracker\Http\Router;

/*
 * Web routes — registered against the Router built in bootstrap/app.php.
 *
 * The route file returns a closure rather than mutating a global so the
 * router stays testable: a test can construct a Router and apply this
 * closure without polluting any container state.
 */
return static function (Router $router): void {
    $router->get('/',         [HomeController::class,   'index']);
    $router->get('/health',   [HealthController::class, 'index']);
    $router->get('/health.json', [HealthController::class, 'json']);
};
