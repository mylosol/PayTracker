<?php

declare(strict_types=1);

/*
 * Third-party service configuration. Keys here are read from .env at
 * bootstrap time; no service code should call env() directly.
 */

return [
    /*
     * Google Maps Distance Matrix API. Used as a fallback by the load-entry
     * write path when city_distances has no recorded mileage for a pair.
     * Empty value disables the fallback — unknown pairs become a validation
     * error instead of a server error.
     */
    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY', ''),
        'timeout' => (int) env('GOOGLE_MAPS_TIMEOUT', 10),
    ],

    /*
     * Resend transactional email relay. Used by AdminUsersController to
     * deliver the password-reset URL when an admin clicks "Reset PW".
     *
     * - api_key: Resend API key. Empty disables email delivery; the URL
     *            is still shown in the admin's flash banner so the action
     *            keeps working.
     * - from:    The verified sender address. Must match a domain you've
     *            verified in your Resend dashboard. Override via MAIL_FROM
     *            in .env if you want a friendly display name, e.g.
     *            "PayTracker <noreply@paytracker.xyz>".
     * - timeout: HTTP timeout in seconds.
     */
    'resend' => [
        'api_key' => env('RESEND_API', ''),
        'from'    => env('MAIL_FROM', 'PayTracker <noreply@paytracker.xyz>'),
        'timeout' => (int) env('RESEND_TIMEOUT', 10),
    ],

    /*
     * Admin-facing notifications. Single recipient for the "please
     * invite me" form (InviteRequestController). Kept as a plain env
     * so rotation is a one-file change with no deploy — and so it
     * isn't hardcoded in a public-visible view where a scraper might
     * grep it out.
     */
    'admin_notifications' => [
        'to' => env('ADMIN_NOTIFY_TO', 'admin@paytracker.xyz'),
    ],
];
