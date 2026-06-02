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
     * Resolve a binding by abstract name.
     *
     * Resolution order:
     *   1. Already-resolved (memoised) → return as-is.
     *   2. Explicit binding factory → call it.
     *   3. Concrete class with a constructor whose parameters all have
     *      class type-hints we can recursively resolve → instantiate with
     *      those dependencies.
     *   4. Concrete class with a parameter-less constructor → `new`.
     *   5. Anything else → throw.
     *
     * Auto-wiring is deliberately minimal: only class-typed parameters are
     * resolved. Scalars must come from an explicit binding so we never
     * silently inject zeros / empty strings.
     */
    public function make(string $abstract): mixed
    {
        if (array_key_exists($abstract, $this->resolved)) {
            return $this->resolved[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->resolved[$abstract] = ($this->bindings[$abstract])($this);
        }

        if (! class_exists($abstract)) {
            throw new RuntimeException(sprintf('No binding registered for [%s].', $abstract));
        }

        return $this->resolved[$abstract] = $this->autowire($abstract);
    }

    /**
     * Build an instance of `$class` by reflecting its constructor and
     * resolving each parameter through `make()`. The recursion is bounded by
     * the dependency graph; cycles would manifest as an infinite loop and so
     * are forbidden by convention (none exist in the current codebase).
     */
    private function autowire(string $class): object
    {
        $reflection  = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $args[] = $this->make($type->getName());
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            throw new RuntimeException(sprintf(
                'Cannot auto-wire parameter $%s of %s::__construct — register an explicit binding.',
                $param->getName(),
                $class,
            ));
        }
        return $reflection->newInstanceArgs($args);
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
