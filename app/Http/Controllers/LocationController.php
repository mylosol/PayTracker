<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\City;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * LocationController — manage the canonical `city` list.
 *
 * Replaces the relevant slice of the legacy `addlocation.php` /
 * `alSubmit.php` / `alVerify.php` / `ald.php` chain. Differences from the
 * legacy implementation, in order of impact:
 *
 *   1. NO SQL INJECTION. The legacy alSubmit.php interpolated raw POST
 *      input into ALTER, INSERT, and UPDATE statements (search the file
 *      for `."`). Every query here is parameterised via the Model.
 *
 *   2. NO SCHEMA WRITES. The legacy flow ran `ALTER TABLE largeMiles ADD
 *      "<userInput>" INT NOT NULL` to create a column named after the new
 *      city — letting any authenticated user mutate the schema. This
 *      controller only INSERTs a row into `city`. The matrix update is
 *      deferred to a normalization migration (planned: replace the
 *      column-per-city `largeMiles` table with a `city_distances` table
 *      keyed by (from_id, to_id)).
 *
 *   3. AUTH GATE + CSRF. Every action checks for an authenticated session
 *      and rejects POSTs without a valid CSRF token. The legacy flow gated
 *      on a shared cookie and had no CSRF protection at all.
 *
 *   4. SINGLE PAGE, NO CHAINED REDIRECTS. Legacy: alVerify → alSubmit →
 *      ald with state encoded in 5+ query parameters. Modern: standard
 *      Post-Redirect-Get with a flash message.
 *
 *   5. STRICT VALIDATION. City names must match a small charset
 *      (letters, spaces, periods, hyphens, commas) and end with a 2-letter
 *      state code. The state code is checked against an explicit enum so
 *      "Bad, ZZ" can't make it into the DB.
 */
final class LocationController extends Controller
{
    /** Allowed state codes the legacy app actually uses. */
    private const ALLOWED_STATES = ['FL', 'AL', 'GA', 'MS', 'LA', 'TN'];

    /** Tightest reasonable charset for a city name (no digits, no symbols). */
    private const CITY_NAME_PATTERN = "/^[A-Za-z][A-Za-z .'\\-]{0,48}$/";

    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly City $cities,
    ) {
    }

    /**
     * List all known cities. Read-only, but still behind the auth gate
     * because the legacy app treats this data as logged-in-only.
     */
    public function index(Request $request): Response
    {
        if (($guard = $this->requireAuth($request)) !== null) {
            return $guard;
        }

        return $this->view('locations/index', [
            'cities' => $this->cities->all(),
            'base'   => $request->basePath(),
            'flash'  => $this->popFlash(),
        ]);
    }

    /**
     * Show the "add city" form.
     */
    public function create(Request $request): Response
    {
        if (($guard = $this->requireAuth($request)) !== null) {
            return $guard;
        }

        return $this->view('locations/new', [
            'csrfToken' => $this->csrf->token(),
            'base'      => $request->basePath(),
            'states'    => self::ALLOWED_STATES,
            'flash'     => $this->popFlash(),
            'old'       => [
                'name'  => $this->session->get('_old_name')  ?? '',
                'state' => $this->session->get('_old_state') ?? '',
            ],
        ]);
    }

    /**
     * Handle the form POST. Validates, checks for duplicates, inserts, and
     * redirects with a flash message in either success or failure.
     */
    public function store(Request $request): Response
    {
        if (($guard = $this->requireAuth($request)) !== null) {
            return $guard;
        }

        $this->session->start();

        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request, 'Your session expired. Please try again.');
        }

        $name  = trim((string) $request->input('name', ''));
        $state = strtoupper(trim((string) $request->input('state', '')));

        // Preserve user input across the redirect on failure so the form
        // can pre-fill — improves UX on a long city name.
        $this->session->put('_old_name', $name);
        $this->session->put('_old_state', $state);

        if ($name === '' || $state === '') {
            return $this->failBack($request, 'Enter both a city name and a state.');
        }
        if (! in_array($state, self::ALLOWED_STATES, true)) {
            return $this->failBack($request, sprintf('State must be one of: %s.', implode(', ', self::ALLOWED_STATES)));
        }
        if (preg_match(self::CITY_NAME_PATTERN, $name) !== 1) {
            return $this->failBack($request, 'City name may only contain letters, spaces, periods, hyphens, apostrophes, and commas.');
        }

        $fullName = $name . ', ' . $state;

        if ($this->cities->findByName($fullName) !== null) {
            return $this->failBack($request, sprintf('"%s" is already in the list.', $fullName));
        }

        $newId = $this->cities->insert($fullName);

        // Clear the preserved input on success.
        $this->session->forget('_old_name');
        $this->session->forget('_old_state');

        $this->session->put('_flash', sprintf('Added "%s" (id %d).', $fullName, $newId));
        return $this->redirect($request->basePath() . '/locations');
    }

    /**
     * Redirect anonymous visitors to the login form. Returns null when an
     * account is signed in, so the caller can continue.
     */
    private function requireAuth(Request $request): ?Response
    {
        if ($this->auth->currentAccount() !== null) {
            return null;
        }
        // Capture the desired destination so post-login we can bounce back
        // (not implemented yet — placeholder for a follow-up). For now just
        // land on the home page after login.
        return $this->redirect($request->basePath() . '/login');
    }

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/locations/new');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
