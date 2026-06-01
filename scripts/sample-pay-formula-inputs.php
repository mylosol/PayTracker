<?php

declare(strict_types=1);

/*
 * One-shot diagnostic for the PayCalculator port.
 *
 * Pulls a representative set of driver_loads rows that have stored np/op
 * AND complete typed inputs, joined with each driver's account.variables
 * blob ("168-night--0" form). The output becomes the ground-truth fixture
 * set that the new PayCalculator must reproduce within a rounding
 * tolerance.
 *
 * Also dumps the variablesCurrent + variablesDefault tables so the
 * backfill migration knows the global-constants shape.
 *
 * Read-only. Usage on the preview host:
 *   php scripts/sample-pay-formula-inputs.php
 */

use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/sample-pay-formula-inputs.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var Connection $connection */
$connection = $app->make(Connection::class);
$pdo        = $connection->pdo();

$tableExists = static function (string $name) use ($pdo): bool {
    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($name));
    return $stmt !== false && $stmt->fetchColumn() !== false;
};

// ---------- 1. variablesDefault / variablesCurrent dumps -----------------
foreach (['variablesDefault', 'variablesCurrent'] as $table) {
    echo "\n=== {$table} ===\n";
    if (! $tableExists($table)) {
        echo "  (absent on this host)\n";
        continue;
    }
    $rows = $pdo->query("SELECT variable, amount FROM `{$table}` ORDER BY variable ASC")->fetchAll();
    printf("  rows: %d\n", count($rows));
    foreach ($rows as $r) {
        printf("    %-20s %s\n", (string) $r['variable'], (string) $r['amount']);
    }
}

// Drift check: which variables differ between Default and Current.
if ($tableExists('variablesDefault') && $tableExists('variablesCurrent')) {
    echo "\n=== drift: variablesDefault vs variablesCurrent ===\n";
    $sql = '
        SELECT v.variable, v.amount AS default_amount, c.amount AS current_amount
        FROM variablesDefault v
        JOIN variablesCurrent c ON c.variable = v.variable
        WHERE v.amount <> c.amount
        ORDER BY v.variable ASC';
    $rows = $pdo->query($sql)->fetchAll();
    if (count($rows) === 0) {
        echo "  no drift — Default and Current are identical.\n";
    } else {
        foreach ($rows as $r) {
            printf(
                "    %-20s default=%-10s current=%s\n",
                (string) $r['variable'],
                (string) $r['default_amount'],
                (string) $r['current_amount'],
            );
        }
    }
}

// ---------- 2. Ground-truth load sample ---------------------------------
// Pull loads with non-zero np that have complete typed inputs and a
// recognisable account.variables blob. Limit to a small set so the build
// log doesn't explode.
echo "\n=== Ground-truth load fixtures (15 rows, mixed types) ===\n";

$loadSql = '
    SELECT
        dl.driver_id, dl.frtl, dl.date, dl.np, dl.op,
        dl.load_type, dl.empty_miles, dl.pickup_city, dl.delivery_city,
        dl.is_split, dl.is_weekend, dl.begin_empty_miles, dl.used_google_maps,
        dl.extra_pay, dl.dem_minutes, dl.break_minutes,
        dl.out_of_route_ind, dl.out_of_route_miles, dl.terminal_pcola,
        a.variables AS account_variables
    FROM driver_loads dl
    JOIN account a ON a.id = dl.driver_id
    WHERE dl.np > 0 AND dl.np <> "" AND dl.op IS NOT NULL
      AND dl.load_type IS NOT NULL
      AND a.variables IS NOT NULL AND a.variables <> ""
    ORDER BY dl.date DESC
    LIMIT 15';

$loads = $pdo->query($loadSql)->fetchAll();
foreach ($loads as $r) {
    echo "  ---\n";
    foreach ($r as $k => $v) {
        printf("  %-20s %s\n", (string) $k, $v === null ? '(null)' : (string) $v);
    }
}
if (count($loads) === 0) {
    echo "  (no rows matched the fixture criteria — relax the WHERE if needed)\n";
}

// ---------- 3. Distribution of load_type across driver_loads -----------
echo "\n=== load_type distribution ===\n";
$dist = $pdo->query('SELECT load_type, COUNT(*) AS n FROM driver_loads GROUP BY load_type ORDER BY load_type')->fetchAll();
foreach ($dist as $r) {
    printf("  load_type=%s  rows=%d\n", $r['load_type'] === null ? '(null)' : (string) $r['load_type'], (int) $r['n']);
}

// ---------- 4. Distinct account.variables shapes -----------------------
// These drive which tenure / shift branches PayCalculator needs to
// support. Truncate at 20 distinct values — that's enough to see the
// pattern.
echo "\n=== distinct account.variables values (top 20) ===\n";
$variants = $pdo->query(
    'SELECT variables, COUNT(*) AS n
     FROM account
     WHERE variables IS NOT NULL AND variables <> ""
     GROUP BY variables
     ORDER BY n DESC
     LIMIT 20'
)->fetchAll();
foreach ($variants as $v) {
    printf("  %-30s drivers=%d\n", (string) $v['variables'], (int) $v['n']);
}
