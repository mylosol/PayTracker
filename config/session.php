<?php

declare(strict_types=1);

return [
    'name'           => env('SESSION_NAME', 'paytracker_session'),
    /*
     * Default cookie lifetime: 90 days. Long enough that a driver
     * doesn't get bounced during a shift, a weekend off, or a week
     * of vacation. The device itself (screen-lock passcode, phone
     * fingerprint) is a stronger security boundary than a short
     * session for this app's threat model — a sign-in on a shared
     * kiosk isn't the pattern; drivers use their own phones.
     * "Keep me logged in" at login upgrades the cookie to
     * `remember_lifetime_min` (180d) instead.
     */
    'lifetime_min'           => (int) env('SESSION_LIFETIME_MIN', 129600),        // 90 days
    'remember_lifetime_min'  => (int) env('SESSION_REMEMBER_LIFETIME_MIN', 259200), // 180 days
    /*
     * `secure_cookie` must be true in production — the cookie is only sent
     * over HTTPS. Local HTTP development overrides via .env.
     */
    'secure_cookie'  => (bool) env('SESSION_SECURE_COOKIE', true),
    'samesite'       => env('SESSION_SAMESITE', 'Lax'),
];
