<?php

declare(strict_types=1);

namespace PayTracker\Security;

/**
 * Wraps PHP's native session in a defensive configuration.
 *
 * Compared to the legacy cookie-driven auth ("monkey" shared password in a
 * client-controlled cookie), this gives us:
 *   - Server-side state, so the client cannot forge identity by setting a
 *     cookie value.
 *   - `HttpOnly` + `SameSite` + (optionally) `Secure` cookies, blocking
 *     trivial XSS exfiltration and most CSRF vectors.
 *   - A regenerated session id on login (handled by AuthService when it's
 *     wired up), defeating session fixation.
 */
final class Session
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $name     = (string) config('session.name', 'paytracker_session');
        $lifetime = (int) config('session.lifetime_min', 120) * 60;
        $secure   = (bool) config('session.secure_cookie', true);
        $samesite = (string) config('session.samesite', 'Lax');

        // Pin session storage to a directory we control. cPanel hosts
        // sometimes inherit session.save_path from the primary domain's
        // tmp directory, which doesn't exist on subdomains; PHP then
        // emits a `No such file or directory` warning at session_start()
        // BEFORE the layout has a chance to render, breaking the page
        // load. Storing under storage/sessions/ keeps the path stable
        // across hosts.
        //
        // Path is resolved against THIS file's location (rather than
        // base_path()) so the override fires before the app's bootstrap
        // is fully wired and survives subcommand contexts where helpers
        // haven't been loaded yet.
        $sessionDir = dirname(__DIR__, 2) . '/storage/sessions';
        if (! is_dir($sessionDir)) {
            @mkdir($sessionDir, 0700, true);
        }
        // Always override, even if the directory check failed -- the
        // alternative is letting PHP fall back to a path that doesn't
        // exist and warning before every page render. A failed
        // session_save_path() at worst leaves the host default in
        // place; the visible regression is identical.
        session_save_path($sessionDir);
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $samesite,
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Rotate the session id without losing the current data. Call this on any
     * privilege boundary — login, role change, password change — to defeat
     * session fixation attacks.
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
