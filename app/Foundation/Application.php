<?php

declare(strict_types=1);

namespace PayTracker\Foundation;

use Closure;
use Dotenv\Dotenv;
use PayTracker\Support\Config;
use RuntimeException;

/**
 * Application is the central composition root for the PayTracker rebuild.
 *
 * It owns:
 *   - the absolute project base path,
 *   - the resolved environment configuration,
 *   - a small dependency-injection container that keeps the codebase free of
 *     hard-coded `new` calls and global singletons.
 *
 * The class is deliberately compact — we only need enough container surface
 * to share a PDO connection, logger, and config repository across the
 * router/controllers. Anything richer can be layered on later without rewrites.
 */
final class Application
{
    private static ?self $instance = null;

    /** @var array<string, Closure(self):mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $resolved = [];

    private function __construct(private readonly string $basePath)
    {
    }

    /**
     * Boot a fresh Application rooted at the given absolute base path.
     */
    public static function boot(string $basePath): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $app = new self(rtrim($basePath, DIRECTORY_SEPARATOR));
        self::$instance = $app;

        $app->loadEnvironment();
        $app->loadConfiguration();
        $app->configureErrorHandling();
        $app->configureTimezone();

        return $app;
    }

    /**
     * Retrieve the booted instance. Throws if `boot()` has not yet run — that
     * would indicate the front controller wiring is broken.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Application has not been booted. Call Application::boot() first.');
        }
        return self::$instance;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Register a lazy binding. The closure is invoked once on first resolve
     * and the result memoised for the lifetime of the request.
     *
     * @param Closure(self):mixed $factory
     */
    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
        unset($this->resolved[$abstract]);
    }

    /**
     * Resolve a binding by abstract name. Auto-instantiates classes that have
     * no explicit binding when the class exists and is constructible without
     * arguments — convenient for plain value objects.
     */
    public function make(string $abstract): mixed
    {
        if (array_key_exists($abstract, $this->resolved)) {
            return $this->resolved[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->resolved[$abstract] = ($this->bindings[$abstract])($this);
        }

        if (class_exists($abstract)) {
            return $this->resolved[$abstract] = new $abstract();
        }

        throw new RuntimeException(sprintf('No binding registered for [%s].', $abstract));
    }

    /**
     * Load environment variables from `.env` at the project root, when present.
     * In production the deploy pipeline writes `.env` from CI secrets; locally
     * the developer copies `.env.example` → `.env`. Either way phpdotenv only
     * populates variables that are not already set in the real environment,
     * so server-level vars win over the file (intentional safety).
     */
    private function loadEnvironment(): void
    {
        if (file_exists($this->basePath . DIRECTORY_SEPARATOR . '.env')) {
            Dotenv::createImmutable($this->basePath)->safeLoad();
        }
    }

    /**
     * Eagerly load every `config/*.php` file into the Config repository. Each
     * file returns an array which is registered under its filename — so
     * `config/database.php` is reachable as `config('database.host')`.
     */
    private function loadConfiguration(): void
    {
        $configRepo = new Config();
        $configDir  = $this->basePath . DIRECTORY_SEPARATOR . 'config';
        if (is_dir($configDir)) {
            foreach (glob($configDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
                $key = basename($file, '.php');
                /** @psalm-suppress UnresolvableInclude */
                $configRepo->set($key, require $file);
            }
        }
        $this->resolved[Config::class] = $configRepo;
    }

    /**
     * Production never echoes raw errors to the browser — that would leak
     * file paths and credentials. Local and preview environments can opt in
     * via `APP_DEBUG=true` for fast feedback.
     */
    private function configureErrorHandling(): void
    {
        $debug = (bool) config('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

        set_exception_handler(static function (\Throwable $e) use ($debug): void {
            http_response_code(500);
            error_log(sprintf('[paytracker] uncaught %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
            if ($debug) {
                echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                echo '<h1>Server error</h1><p>An unexpected error occurred. The incident has been logged.</p>';
            }
        });
    }

    private function configureTimezone(): void
    {
        date_default_timezone_set((string) config('app.timezone', 'America/Chicago'));
    }
}
