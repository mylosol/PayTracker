<?php

declare(strict_types=1);

return [
    'name'           => env('SESSION_NAME', 'paytracker_session'),
    /*
     * Default cookie lifetime: 12h. Long enough that a driver on shift
     * doesn't get bounced mid-route, short enough that an unattended
     * browser eventually re-prompts. "Keep me logged in" at login
     * upgrades the cookie to `remember_lifetime_min` instead.
     */
    'lifetime_min'           => (int) env('SESSION_LIFETIME_MIN', 720),
    'remember_lifetime_min'  => (int) env('SESSION_REMEMBER_LIFETIME_MIN', 43200), // 30 days
    /*
     * `secure_cookie` must be true in production — the cookie is only sent
     * over HTTPS. Local HTTP development overrides via .env.
     */
    'secure_cookie'  => (bool) env('SESSION_SECURE_COOKIE', true),
    'samesite'       => env('SESSION_SAMESITE', 'Lax'),
];
