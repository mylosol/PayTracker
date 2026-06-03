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
