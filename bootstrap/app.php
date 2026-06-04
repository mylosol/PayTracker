<?php

declare(strict_types=1);

use PayTracker\Database\Connection;
use PayTracker\Foundation\Application;
use PayTracker\Http\Kernel;
use PayTracker\Http\Router;
use PayTracker\Logging\Logger;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\GoogleMapsService;
use PayTracker\Services\MailService;
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
 * GoogleMapsService — Distance Matrix fallback for load-entry. The API key
 * is a scalar so it needs an explicit binding; auto-wiring only handles
 * class dependencies.
 */
$app->bind(GoogleMapsService::class, static function (Application $app): GoogleMapsService {
    /** @var Config $config */
    $config = $app->make(Config::class);
    /** @var array{api_key:string,timeout:int} $maps */
    $maps = $config->get('services.google_maps', ['api_key' => '', 'timeout' => 10]);
    return new GoogleMapsService($maps['api_key'], $app->make(Logger::class), $maps['timeout']);
});

/*
 * MailService — Resend transactional email relay. The API key + sender
 * address are scalars so we wire them through an explicit binding;
 * autowiring only resolves class dependencies.
 *
 * Empty api_key disables sending (degrades to logging + no-op return),
 * so a developer without RESEND_API in .env doesn't see surprise errors.
 */
$app->bind(MailService::class, static function (Application $app): MailService {
    /** @var Config $config */
    $config = $app->make(Config::class);
    /** @var array{api_key:string,from:string,timeout:int} $resend */
    $resend = $config->get('services.resend', ['api_key' => '', 'from' => '', 'timeout' => 10]);
    return new MailService(
        $resend['api_key'],
        $resend['from'],
        $app->make(Logger::class),
        $resend['timeout']
    );
});

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
