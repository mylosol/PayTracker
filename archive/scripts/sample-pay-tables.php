<?php

declare(strict_types=1);

/*
 * One-shot diagnostic: dump the six legacy pay-rate tables so the pay-admin
 * port can build pay_rates(terminal, trip_type, stage, miles, rate) backfill
 * grounded in real data.
 *
 * Tables sampled:
 *   - PensacolaPayDefault / PensacolaPayCurrent           (Pensacola, round-trip)
 *   - LHPensacolaPayDefault / LHPensacolaPayCurrent       (Pensacola, long-haul)
 *   - PanamaPay                                           (Panama City, single)
 *
 * Read-only. Prints row counts, full (miles, rate) listings per table, and
 * notes whether Default == Current (i.e. whether the terminal has ever had
 * its rates customised vs sitting at factory defaults).
 *
 * Usage (run on the preview host):
 *   php scripts/sample-pay-tables.php
 */

use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/sample-pay-tables.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var Connection $connection */
$connection = $app->make(Connection::class);
$pdo        = $connection->pdo();

$tables = [
    'PensacolaPayDefault',
    'PensacolaPayCurrent',
    'LHPensacolaPayDefault',
    'LHPensacolaPayCurrent',
    'PanamaPay',
];

// Discover which of these exist — preview's schema may differ from production.
$existing = [];
foreach ($tables as $t) {
    $check = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
    if ($check !== false && $check->fetchColumn() !== false) {
        $existing[] = $t;
    } else {
        echo "  (absent on this host: {$t})\n";
    }
}

if ($existing === []) {
    echo "\nNo legacy pay tables found on this host. The schema may have been pruned.\n";
    exit(0);
}

$dump = static function (string $table) use ($pdo): array {
    $sql = "SELECT * FROM `{$table}` ORDER BY CAST(miles AS UNSIGNED)";
    $rows = [];
    foreach ($pdo->query($sql) as $r) {
        $rows[] = ['miles' => (string) $r['miles'], 'rate' => (string) $r['rate']];
    }
    return $rows;
};

foreach ($existing as $t) {
    echo "\n=== {$t} ===\n";
    $rows = $dump($t);
    printf("  rows: %d\n", count($rows));
    foreach ($rows as $r) {
        printf("    miles=%-6s rate=%s\n", $r['miles'], $r['rate']);
    }
}

// Default-vs-Current drift detection: if PensacolaPayDefault == PensacolaPayCurrent
// then no one has ever customised the Pensacola rates and we can safely backfill
// stage='default' and stage='current' from the same source.
$pairs = [
    ['PensacolaPayDefault',   'PensacolaPayCurrent'],
    ['LHPensacolaPayDefault', 'LHPensacolaPayCurrent'],
];
foreach ($pairs as [$d, $c]) {
    if (! in_array($d, $existing, true) || ! in_array($c, $existing, true)) {
        continue;
    }
    $dRows = $dump($d);
    $cRows = $dump($c);
    $same  = $dRows === $cRows;
    echo "\n  drift: {$d} vs {$c}  =>  " . ($same ? 'IDENTICAL' : 'DIFFERS') . "\n";
}
