<?php

declare(strict_types=1);

/*
 * One-shot diagnostic: sample driver_loads.variables / loadinfo / paid blob
 * strings and report field-count distributions, so the load-entry port can
 * write a parser grounded in actual data instead of guesses based on the
 * legacy newload.php cookie shape.
 *
 * Usage (run on the preview host):
 *   php scripts/sample-driver-loads.php
 *
 * Read-only. Safe to run anywhere driver_loads exists.
 */

use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/sample-driver-loads.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var Connection $connection */
$connection = $app->make(Connection::class);
$pdo        = $connection->pdo();

// ---------- 1. Show 12 representative rows ----------------------------------
echo "=== Sample rows (newest 12) ===\n";
$stmt = $pdo->query(
    'SELECT driver_id, frtl, date, variables, loadinfo, paid, np, op
     FROM driver_loads
     ORDER BY date DESC
     LIMIT 12'
);
foreach ($stmt as $r) {
    printf(
        "driver=%-3d frtl=%-5d %s  np=%s op=%s\n",
        (int) $r['driver_id'],
        (int) $r['frtl'],
        (string) $r['date'],
        (string) $r['np'],
        (string) $r['op']
    );
    echo "  vars: " . $r['variables'] . "\n";
    echo "  info: " . $r['loadinfo']  . "\n";
    echo "  paid: " . $r['paid']      . "\n";
}

// ---------- 2. Field-count histograms ---------------------------------------
$histogram = function (string $column) use ($pdo): void {
    echo "\n=== {$column} field-count distribution ===\n";
    $counts = [];
    $stmt   = $pdo->query("SELECT {$column} FROM driver_loads");
    foreach ($stmt as $r) {
        $val = (string) $r[$column];
        $n   = $val === '' ? 0 : substr_count($val, '-') + 1;
        $counts[$n] = ($counts[$n] ?? 0) + 1;
    }
    ksort($counts);
    foreach ($counts as $n => $c) {
        printf("  %2d fields: %6d rows\n", $n, $c);
    }
};
$histogram('variables');
$histogram('loadinfo');
$histogram('paid');

// ---------- 3. Distinct values in low-cardinality positions -----------------
// For each blob column, walk the first 8 positions and show how many distinct
// values appear. Positions with a handful of distinct values are likely flags
// or enums; positions with many distinct values are city names / numbers.
$cardinality = function (string $column, int $positions) use ($pdo): void {
    echo "\n=== {$column} per-position distinct-value counts (first {$positions}) ===\n";
    $rows = $pdo->query("SELECT {$column} FROM driver_loads")->fetchAll(PDO::FETCH_COLUMN);
    for ($i = 0; $i < $positions; $i++) {
        $seen = [];
        foreach ($rows as $blob) {
            $parts = explode('-', (string) $blob);
            $seen[$parts[$i] ?? ''] = true;
        }
        $sample = array_slice(array_keys($seen), 0, 6);
        printf(
            "  pos[%d]  distinct=%-5d  examples: %s\n",
            $i,
            count($seen),
            implode(' | ', array_map(static fn ($v) => $v === '' ? '(empty)' : $v, $sample))
        );
    }
};
$cardinality('variables', 14);
$cardinality('loadinfo', 8);
$cardinality('paid', 8);
