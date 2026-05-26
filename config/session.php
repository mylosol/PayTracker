<?php

declare(strict_types=1);

return [
    'name'           => env('SESSION_NAME', 'paytracker_session'),
    'lifetime_min'   => (int) env('SESSION_LIFETIME_MIN', 120),
    /*
     * `secure_cookie` must be true in production — the cookie is only sent
     * over HTTPS. Local HTTP development overrides via .env.
     */
    'secure_cookie'  => (bool) env('SESSION_SECURE_COOKIE', true),
    'samesite'       => env('SESSION_SAMESITE', 'Lax'),
];
