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
];
