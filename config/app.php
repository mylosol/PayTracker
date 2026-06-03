<?php

declare(strict_types=1);

return [
    /*
     * High-level application identity. `env` selects defensive defaults
     * elsewhere — for example, debug pages and verbose errors are gated on
     * `app.debug` being true.
     */
    'name'     => env('APP_NAME', 'PayTracker'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'https://paytracker.xyz'),
    'timezone' => env('APP_TIMEZONE', 'America/Chicago'),
    'key'      => env('APP_KEY', ''),
];
