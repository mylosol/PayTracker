<?php

declare(strict_types=1);

/*
 * CLI entry point: apply pending migrations.
 *
 * Usage:
 *   php scripts/migrate.php           # apply pending migrations
 *   php scripts/migrate.php --dry-run # report what would run, change nothing
 *
 * The CI workflow invokes this over SSH on the DreamHost host so migrations
 * always run with the same `.env` the live app reads. Local developers run
 * it the same way from their working tree.
 */

use PayTracker\Console\Migrator;
use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/migrate.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$dryRun = in_array('--dry-run', $argv, true);

try {
    /** @var Connection $connection */
    $connection = $app->make(Connection::class);
    $migrator   = new Migrator($connection, $app->basePath() . '/database/migrations');

    $applied = $migrator->migrate($dryRun);

    if ($applied === []) {
        echo "Database is up to date — no migrations to apply.\n";
        exit(0);
    }

    echo ($dryRun ? "Would apply:\n" : "Applied:\n");
    foreach ($applied as $name) {
        echo "  - {$name}\n";
    }
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf("Migration failed: %s\n%s\n", $e->getMessage(), $e->getTraceAsString()));
    exit(1);
}
