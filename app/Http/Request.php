<?php

declare(strict_types=1);

namespace PayTracker\Http;

/**
 * Immutable HTTP request representation.
 *
 * We avoid touching the PHP superglobals directly past this class so that
 * controllers receive a typed, predictable surface and so unit tests can
 * construct fake requests without globals.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $server,
        public readonly array $cookies,
    ) {
    }

    /**
     * Build a Request from the active PHP superglobals. The path is normalised
     * to a leading slash with no trailing slash, and any deploy-level URL
     * prefix (e.g. `/preview` on the preview channel) is stripped so the
     * Router only ever sees app-relative paths.
     *
     * The prefix is derived from `SCRIPT_NAME` — after the .htaccess rewrite
     * fires, `SCRIPT_NAME` looks like `/preview/public/index.php` (or just
     * `/index.php` in production), and stripping the trailing `index.php`
     * yields the public base path. This keeps routing identical across both
     * channels without env-var plumbing.
     */
    public static function fromGlobals(): self
    {
        $rawPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $script  = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $prefix  = self::derivePrefix($script);
        if ($prefix !== '' && str_starts_with($rawPath, $prefix)) {
            $rawPath = substr($rawPath, strlen($prefix));
        }
        $path = '/' . trim($rawPath, '/');

        return new self(
            method:  strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            path:    $path,
            query:   $_GET,
            post:    $_POST,
            server:  $_SERVER,
            cookies: $_COOKIE,
        );
    }

    /**
     * Strip the `index.php` (and optional `public/`) suffix from SCRIPT_NAME
     * to find the deployment base. `/preview/public/index.php` → `/preview`.
     */
    private static function derivePrefix(string $scriptName): string
    {
        $prefix = preg_replace('#/(?:public/)?index\.php$#', '', $scriptName) ?? '';
        return rtrim($prefix, '/');
    }

    /**
     * Read a request input value, preferring POST over query string. Returns
     * the default when missing or empty. The caller is responsible for any
     * downstream validation / coercion — this method intentionally does NOT
     * trim or sanitise so HTML-bearing form fields aren't silently mangled.
     */
    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return is_scalar($value) ? (string) $value : $default;
    }

    public function isMethod(string $method): bool
    {
        return strcasecmp($this->method, $method) === 0;
    }

    /**
     * Deploy-relative base path. `""` in production, `"/preview"` on the
     * preview channel. Controllers prepend this to absolute redirect
     * targets so a single route definition works identically across both
     * channels without env-var plumbing.
     *
     * The derivation mirrors `fromGlobals()` so the value is consistent
     * with how the router strips the prefix off `path`.
     */
    public function basePath(): string
    {
        return self::derivePrefix((string) ($this->server['SCRIPT_NAME'] ?? ''));
    }
}
