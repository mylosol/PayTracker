<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Security\Csrf;
use PayTracker\Support\Version;

/**
 * HomeController — the landing surface.
 *
 * Two states:
 *   - Anonymous: render a public marketing card and a "Sign in" link.
 *   - Authenticated: render the (still skeletal) dashboard with the
 *     account handle and a CSRF-armed logout form.
 *
 * Both views share the same layout so the chrome (header, footer, version
 * marker) is identical regardless of auth state.
 */
final class HomeController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
    ) {
    }

    public function index(Request $request): Response
    {
        $account = $this->auth->currentAccount();

        return $this->view('home', [
            'appName'   => (string) config('app.name', 'PayTracker'),
            'env'       => (string) config('app.env', 'production'),
            'version'   => Version::string(),
            'account'   => $account,
            'csrfToken' => $this->csrf->token(),
            'base'      => $request->basePath(),
        ]);
    }
}
