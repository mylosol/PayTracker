<?php

declare(strict_types=1);

use PayTracker\Foundation\Application;
use PayTracker\Support\Config;
use PayTracker\View\View;

if (! function_exists('app')) {
    /**
     * Resolve the running Application instance, or a binding from its container.
     *
     * Centralising access here keeps the rest of the codebase free of global
     * singletons while still letting helpers reach the container ergonomically.
     */
    function app(?string $abstract = null): mixed
    {
        $app = Application::instance();
        return $abstract === null ? $app : $app->make($abstract);
    }
}

if (! function_exists('base_path')) {
    /**
     * Resolve an absolute path relative to the project root.
     */
    function base_path(string $relative = ''): string
    {
        $root = Application::instance()->basePath();
        return $relative === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

if (! function_exists('config')) {
    /**
     * Read a dot-notated configuration value, e.g. `config('database.host')`.
     */
    function config(string $key, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = Application::instance()->make(Config::class);
        return $config->get($key, $default);
    }
}

if (! function_exists('env')) {
    /**
     * Read a value from the environment, with type coercion for the common
     * truthy / falsy / null sentinels. Always prefer `config()` in app code so
     * that env access is centralised through `config/*.php`.
     */
    function env(string $key, mixed $default = null): mixed
    {
        // `getenv()` returns `string|false` and the superglobal lookups are
        // either set (any scalar) or unset (the `??` skips them) — so the
        // final value can never be literally `null`. We only need to bail
        // on the `false` (missing) and `''` (empty) sentinels.
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (! function_exists('e')) {
    /**
     * Escape a string for safe HTML output. Centralises our XSS defence so a
     * view file never needs to remember the htmlspecialchars argument list.
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (! function_exists('view')) {
    /**
     * Render a view template into an HTTP response body.
     */
    function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }
}

if (! function_exists('layout')) {
    /**
     * Declare the layout template that should wrap the currently rendering
     * view. Templates call this near the top of the file:
     *     `<?php layout('layouts/app'); ?>`
     */
    function layout(string $template): void
    {
        View::setLayout($template);
    }
}
