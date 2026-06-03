<?php

declare(strict_types=1);

return [
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => (int) env('DB_PORT', 3306),
    'database' => env('DB_DATABASE', 'paytracking'),
    'username' => env('DB_USERNAME', ''),
    'password' => env('DB_PASSWORD', ''),
    /*
     * `utf8mb4` is the only safe choice for new MySQL/MariaDB schemas — the
     * legacy `utf8mb3` charset cannot represent the full Unicode plane
     * (notably emoji and many non-Latin scripts) and is officially deprecated.
     * The existing schema mixes utf8mb3 and ucs2 — those will need migrations
     * in a follow-up phase, but new connections speak utf8mb4 from day one.
     */
    'charset'  => env('DB_CHARSET', 'utf8mb4'),
];
