<?php

declare(strict_types=1);

namespace PayTracker\Security;

/**
 * Cross-Site Request Forgery defence.
 *
 * Every POST/PUT/DELETE form must include the value of `token()` in a hidden
 * `_csrf` field; the controller (or a future middleware) calls `verify()` on
 * arrival. Tokens are bound to the session and stay valid for the session's
 * lifetime — same model Laravel / Symfony / Rails use.
 *
 * We deliberately do NOT rotate on every successful verify. The token's job
 * is to prove a submission came from a form THIS session rendered, so a
 * hostile third-party page can't POST using the user's cookies. That
 * protection works fine with a per-session token; rotating on every use
 * mostly just breaks legitimate flows (back-button, multiple tabs, submit
 * → validation error → resubmit) with a misleading "Your session expired"
 * flash.
 *
 * Session-fixation defence — the concern that motivated single-use — is
 * handled at the session-id layer via Session::regenerate(), called on
 * every privilege boundary (login, role change, password change). That
 * invalidates any pre-existing token bound to the old session id.
 */
final class Csrf
{
    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        // ALWAYS start the session before touching $_SESSION. The
        // Session helper writes directly to $_SESSION; without an
        // active session PHP discards the data at request end. This
        // bit a regression in the password-reset claim flow where
        // show() didn't start the session before calling token() and
        // the persisted token was lost, so submit()'s verify() always
        // failed. Belt-and-suspenders here so no caller has to
        // remember.
        $this->session->start();

        $token = $this->session->get('_csrf');
        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->put('_csrf', $token);
        }
        return $token;
    }

    public function verify(?string $submitted): bool
    {
        $this->session->start();

        $expected = $this->session->get('_csrf');
        if (! is_string($expected) || ! is_string($submitted)) {
            return false;
        }
        // `hash_equals` provides constant-time comparison — protects against
        // timing-based discovery of the token. NOT single-use: see the
        // class docblock for the rationale (back-button / multi-tab
        // ergonomics vs. token-replay theatre when the attacker already
        // owns the session).
        return hash_equals($expected, $submitted);
    }
}
