<?php

declare(strict_types=1);

namespace PayTracker\Security;

/**
 * Cross-Site Request Forgery defence.
 *
 * Every POST/PUT/DELETE form must include the value of `token()` in a hidden
 * `_csrf` field; the controller (or a future middleware) calls `verify()` on
 * arrival. Tokens are bound to the session and rotated on consumption to
 * prevent replay.
 */
final class Csrf
{
    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('_csrf');
        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put('_csrf', $token);
        }
        return $token;
    }

    public function verify(?string $submitted): bool
    {
        $expected = $this->session->get('_csrf');
        if (! is_string($expected) || ! is_string($submitted)) {
            return false;
        }
        // `hash_equals` provides constant-time comparison — protects against
        // timing-based discovery of the token.
        $ok = hash_equals($expected, $submitted);
        if ($ok) {
            $this->session->forget('_csrf'); // single-use, rotate after success
        }
        return $ok;
    }
}
