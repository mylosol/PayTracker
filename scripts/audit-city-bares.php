<?php

declare(strict_types=1);

/*
 * scripts/audit-city-bares.php — read-only audit of legacy "bare-name"
 * duplicates in the `city` table.
 *
 * Background: the original PayTracker stored city names as bare strings
 * ("Bristol", "Andalusia"). Migration 2026_05_23_003 added state-suffixed
 * normalised twins ("Bristol, FL", "Andalusia, AL") but DELIBERATELY did
 * NOT delete the bare rows, fearing unknown legacy references.
 *
 * That left the table carrying both generations side-by-side. The picker
 * dropdowns hide the bares (see City::allForPicker), but /locations
 * surfaces them via City::all(), and any code that does a name lookup
 * has to handle both forms.
 *
 * This script catalogs the damage so we can plan a consolidation without
 * surprises:
 *
 *   1. Every bare row + its state-suffixed twin(s).
 *   2. Reference counts in every table that stores a city by NAME:
 *        - driver_loads.pickup_city / delivery_city / end_empty_city
 *        - terminals.name (Begin Empty Locations)
 *   3. Reference counts in every table that stores a city by ID:
 *        - city_distances.from_city_id / to_city_id
 *        - terminals.city_id
 *   4. Twins that have no bare (sanity check — should be most rows).
 *   5. Bares that have no twin (we'll leave these alone; deleting one
 *      would lose a real city).
 *
 * Output is plain text formatted for the GH Actions log. Pass --json to
 * get the same data as a structured JSON payload for follow-up tooling.
 *
 * READ-ONLY: this script does NOT write to the database. The companion
 * consolidate migration ships in a follow-up commit after Robert eyeballs
 * the report.
 */

use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/audit-city-bares.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$flags  = array_slice($argv, 1);
$asJson = in_array('--json', $flags, true);

try {
    /** @var Connection $connection */
    $connection = $app->make(Connection::class);
    $pdo        = $connection->pdo();

    // ---- Load every city row keyed by id and by lower(name) ----
    $rows = $pdo->query('SELECT id, city FROM `city` ORDER BY city ASC')->fetchAll(PDO::FETCH_ASSOC);
    /** @var array<int, array{id:int,name:string,is_bare:bool}> $byId */
    $byId = [];
    /** @var array<string, list<array{id:int,name:string}>> $byBareLower */
    // Bare name → list of full rows whose city EQUALS that bare (the bare
    // itself) and rows whose city STARTS WITH "bare, " (the twins).
    $byBareLower = [];

    foreach ($rows as $r) {
        $id   = (int) $r['id'];
        $name = trim((string) $r['city']);
        if ($name === '') {
            continue;
        }
        $isBare = ! str_contains($name, ',');
        $byId[$id] = ['id' => $id, 'name' => $name, 'is_bare' => $isBare];
    }

    // Build the twin index: walk bares, find twins whose name matches
    // "<bare>, XX" (case-insensitive).
    /** @var array<int, list<array{id:int,name:string}>> $twinsForBareId */
    $twinsForBareId = [];
    foreach ($byId as $row) {
        if (! $row['is_bare']) {
            continue;
        }
        $twins = [];
        $needle = strtolower($row['name']) . ',';
        foreach ($byId as $candidate) {
            if ($candidate['is_bare'] || $candidate['id'] === $row['id']) {
                continue;
            }
            if (str_starts_with(strtolower($candidate['name']), $needle)) {
                $twins[] = ['id' => $candidate['id'], 'name' => $candidate['name']];
            }
        }
        $twinsForBareId[$row['id']] = $twins;
    }

    // ---- Per-bare reference counts ----
    $countByName = static function (PDO $pdo, string $table, string $column, string $name): int {
        $sql  = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name]);
        return (int) $stmt->fetchColumn();
    };
    $countById = static function (PDO $pdo, string $table, string $column, int $id): int {
        $sql  = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    };
    $tableExists = static function (PDO $pdo, string $table): bool {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return $stmt !== false && $stmt->fetchColumn() !== false;
    };

    $hasDriverLoads   = $tableExists($pdo, 'driver_loads');
    $hasCityDistances = $tableExists($pdo, 'city_distances');
    $hasTerminals     = $tableExists($pdo, 'terminals');

    $report = [
        'bares_with_twins'    => [],
        'bares_without_twins' => [],
        'twins_without_bare'  => [],
        'totals' => [
            'cities_total'           => count($byId),
            'bares_total'            => 0,
            'twins_total'            => 0,
            'bares_with_twins_total' => 0,
            'bares_orphan_total'     => 0,
        ],
    ];

    foreach ($byId as $row) {
        if (! $row['is_bare']) {
            $report['totals']['twins_total']++;
            continue;
        }
        $report['totals']['bares_total']++;
        $twins = $twinsForBareId[$row['id']] ?? [];

        $refs = [
            'driver_loads.pickup_city'   => 0,
            'driver_loads.delivery_city' => 0,
            'driver_loads.end_empty_city' => 0,
            'terminals.name'             => 0,
            'city_distances.from'        => 0,
            'city_distances.to'          => 0,
            'terminals.city_id'          => 0,
        ];
        if ($hasDriverLoads) {
            $refs['driver_loads.pickup_city']    = $countByName($pdo, 'driver_loads', 'pickup_city',    $row['name']);
            $refs['driver_loads.delivery_city']  = $countByName($pdo, 'driver_loads', 'delivery_city',  $row['name']);
            $refs['driver_loads.end_empty_city'] = $countByName($pdo, 'driver_loads', 'end_empty_city', $row['name']);
        }
        if ($hasTerminals) {
            $refs['terminals.name']    = $countByName($pdo, 'terminals', 'name',    $row['name']);
            $refs['terminals.city_id'] = $countById  ($pdo, 'terminals', 'city_id', $row['id']);
        }
        if ($hasCityDistances) {
            $refs['city_distances.from'] = $countById($pdo, 'city_distances', 'from_city_id', $row['id']);
            $refs['city_distances.to']   = $countById($pdo, 'city_distances', 'to_city_id',   $row['id']);
        }
        $entry = [
            'bare'  => $row,
            'twins' => $twins,
            'refs'  => $refs,
            'refs_total' => array_sum($refs),
        ];
        if ($twins === []) {
            $report['bares_without_twins'][] = $entry;
            $report['totals']['bares_orphan_total']++;
        } else {
            $report['bares_with_twins'][] = $entry;
            $report['totals']['bares_with_twins_total']++;
        }
    }

    // Twins without a bare: every twin row whose "bare" prefix has no
    // matching bare row. Useful sanity check — should be the bulk of
    // the twin set (most cities came in cleanly state-suffixed from
    // the start).
    $bareNamesLower = [];
    foreach ($byId as $row) {
        if ($row['is_bare']) {
            $bareNamesLower[strtolower($row['name'])] = true;
        }
    }
    foreach ($byId as $row) {
        if ($row['is_bare']) continue;
        $prefix = explode(',', $row['name'], 2)[0];
        if (! isset($bareNamesLower[strtolower(trim($prefix))])) {
            $report['twins_without_bare'][] = ['id' => $row['id'], 'name' => $row['name']];
        }
    }

    // ---- Render ----
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    }

    $t = $report['totals'];
    echo "═══ city-bare audit ═══\n";
    echo sprintf(
        "city rows:              %d\n  bares:                %d (%d with twins, %d orphan / kept)\n  twins:                %d\n",
        $t['cities_total'],
        $t['bares_total'],
        $t['bares_with_twins_total'],
        $t['bares_orphan_total'],
        $t['twins_total'],
    );
    echo sprintf("  twins without bare:   %d (clean rows — no merge needed)\n\n", count($report['twins_without_bare']));

    if ($report['bares_with_twins'] !== []) {
        echo "── consolidation candidates (bare → twin merge) ──\n";
        foreach ($report['bares_with_twins'] as $e) {
            $twinNames = array_map(static fn (array $t): string => $t['name'] . ' (id=' . $t['id'] . ')', $e['twins']);
            echo sprintf(
                "  %-32s (id=%d)  →  %s\n",
                $e['bare']['name'],
                $e['bare']['id'],
                implode(' | ', $twinNames)
            );
            foreach ($e['refs'] as $k => $v) {
                if ($v > 0) {
                    echo sprintf("      ref %s = %d\n", $k, $v);
                }
            }
            if ($e['refs_total'] === 0) {
                echo "      (no references — safe to delete with no rewrites)\n";
            }
        }
        echo "\n";
    }

    if ($report['bares_without_twins'] !== []) {
        echo "── bares with NO twin (will be left alone) ──\n";
        foreach ($report['bares_without_twins'] as $e) {
            echo sprintf("  %-32s (id=%d)  refs_total=%d\n", $e['bare']['name'], $e['bare']['id'], $e['refs_total']);
        }
        echo "\n";
    }

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf("Audit failed: %s\n", $e->getMessage()));
    exit(1);
}
