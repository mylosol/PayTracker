<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Auth\LoginFailure;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * LoginController — three actions:
 *
 *   showForm   GET  /login    render the login form with a fresh CSRF token
 *   submit     POST /login    validate, attempt, redirect
 *   logout     POST /logout   tear the session down, return to /login
 *
 * Failure responses NEVER reveal which step failed (handle vs. password vs.
 * lockout). The form renders one generic "Incorrect login or password"
 * message in all three cases. Lockout is communicated to the legitimate user
 * out-of-band (the admin tells them) so an attacker observing failures
 * can't distinguish "valid account" from "invalid account".
 */
final class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
    ) {
    }

    public function showForm(Request $request): Response
    {
        $this->session->start();
        // If a session is already established, bounce to the dashboard
        // rather than re-prompting. Defeats the case where a logged-in
        // user clicks the browser back button.
        if ($this->auth->currentAccount() !== null) {
            return $this->redirect($request->basePath() . '/');
        }
        return $this->view('auth/login', [
            'csrfToken' => $this->csrf->token(),
            'flash'     => $this->popFlash(),
            'base'      => $request->basePath(),
        ]);
    }

    public function submit(Request $request): Response
    {
        $this->session->start();

        $submittedToken = $request->input('_csrf');
        if (! $this->csrf->verify($submittedToken)) {
            // Treat as a generic failure — don't tell the client whether
            // the token was missing, expired, or wrong.
            return $this->failBack($request, 'Your session expired. Please try again.');
        }

        $handle   = trim((string) $request->input('handle', ''));
        $password = (string) $request->input('password', '');

        if ($handle === '' || $password === '') {
            return $this->failBack($request, 'Enter your login and password.');
        }

        $result = $this->auth->attempt($handle, $password);
        if ($result instanceof LoginFailure) {
            // Map the failure case to a user-facing message. The
            // BadCredentials wording is intentionally identical to
            // the pre-enrichment behaviour so no attacker probe
            // surface widens. AccountSuspended is only reachable
            // after the password verified, so revealing the state
            // is safe AND avoids the "I'm sure my password is
            // right!" rabbit hole a banned user otherwise falls
            // into.
            $message = match ($result) {
                LoginFailure::BadCredentials   => 'Incorrect login or password.',
                LoginFailure::AccountSuspended => 'This account has been suspended. Please contact an administrator at /contact for assistance.',
            };
            return $this->failBack($request, $message);
        }

        // After the instanceof check above, $result is the int account
        // id (success path). We don't currently surface it past this
        // point; the session was already populated inside attempt().

        // Arm the announcement modal: the user just logged in, so they
        // should see the active announcement (if any) on their very
        // next page render. The layout reads this flag, shows the
        // modal once, and the dismiss endpoint clears it.
        $this->session->put('announcement_pending', true);

        // PRG (Post-Redirect-Get) so a refresh on the dashboard doesn't
        // re-POST the login form.
        return $this->redirect($request->basePath() . '/');
    }

    public function logout(Request $request): Response
    {
        $this->session->start();
        // CSRF check still applies to logout — a CSRF that auto-logs you out
        // is annoying but not security-critical; we enforce it anyway because
        // the cost is zero once the helper exists.
        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->redirect($request->basePath() . '/');
        }
        $this->auth->logout();
        return $this->redirect($request->basePath() . '/login');
    }

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/login');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
