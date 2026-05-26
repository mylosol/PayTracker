<?php

declare(strict_types=1);

use PayTracker\Database\Connection;
use PayTracker\Foundation\Application;
use PayTracker\Http\Kernel;
use PayTracker\Http\Router;
use PayTracker\Logging\Logger;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Support\Config;

/*
 * The bootstrap file is the assembly point. It (1) boots the Application
 * container, (2) registers shared service factories, and (3) loads the route
 * definitions. The front controller (`public/index.php`) consumes the Kernel
 * returned from here and never sees these wiring details.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = Application::boot(dirname(__DIR__));

/*
 * Database connection — single shared PDO handle per request.
 */
$app->bind(Connection::class, static function (Application $app): Connection {
    /** @var Config $config */
    $config = $app->make(Config::class);
    /** @var array{host:string,port:int|string,database:string,username:string,password:string,charset:string} $db */
    $db = $config->get('database', []);
    return new Connection($db);
});

/*
 * Structured logger writing to storage/logs/app.log.
 */
$app->bind(Logger::class, static function (Application $app): Logger {
    return new Logger($app->basePath() . '/storage/logs/app.log');
});

/*
 * Session + CSRF — Session is started lazily by the first controller that
 * needs it; binding them here keeps construction centralised.
 */
$app->bind(Session::class, static fn (): Session => new Session());
$app->bind(Csrf::class, static fn (Application $app): Csrf => new Csrf($app->make(Session::class)));

/*
 * Router — populated by `routes/web.php` and handed to the Kernel.
 */
$app->bind(Router::class, static function (): Router {
    $router = new Router();
    /** @psalm-suppress UnresolvableInclude */
    (require __DIR__ . '/../routes/web.php')($router);
    return $router;
});

$app->bind(Kernel::class, static fn (Application $app): Kernel => new Kernel($app->make(Router::class)));

return $app;
