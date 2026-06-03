<?php

declare(strict_types=1);

/*
 * 2026_05_25_002_backfill_driver_loads.php
 *
 * Copies rows from each `loadsNN` per-driver table into the unified
 * `driver_loads` table with `driver_id = N`. The original `loads` table
 * (note: no trailing number) has a completely different schema — it is
 * NOT touched by this migration.
 *
 * Discovery is dynamic: any table named `loads<integer>` in the schema is
 * treated as a per-driver table. This way the backfill works regardless
 * of which accounts happened to have one created.
 *
 * Orphan handling: a `loads65` table exists but `account.id = 65` does
 * not (the account was deleted at some point in the legacy app's life).
 * We still copy those rows — losing real load history because of an
 * accounting cleanup would be worse than carrying forward an orphan
 * driver_id. A future cleanup branch can decide whether to merge them
 * into an active account or archive them.
 *
 * Idempotency: INSERT IGNORE on the composite PK (driver_id, frtl). A
 * forced re-run produces the same final state.
 *
 * What this migration does NOT do
 *   - It does not modify or drop the legacy loadsNN tables. The legacy
 *     Pi app keeps reading and writing them.
 *   - It does not parse the dash-separated `variables` / `loadinfo` /
 *     `paid` strings into proper columns. Those encodings are preserved
 *     verbatim; decoding them is a separate concern.
 *   - It does not touch the original `loads` table (different schema,
 *     unclear relationship to the per-driver tables).
 */

return static function (PDO $pdo): void {
    // Discover every per-driver table. `SHOW TABLES LIKE 'loads%'` returns
    // both the original `loads` and the per-driver `loadsNN`; we filter
    // strictly to the numbered form so the original isn't accidentally
    // mixed in.
    $allLoadTables = $pdo->query("SHOW TABLES LIKE 'loads%'")->fetchAll(PDO::FETCH_COLUMN);

    /** @var array<int,string> $driverTables driver_id → table name */
    $driverTables = [];
    foreach ($allLoadTables as $table) {
        if (preg_match('/^loads(\d+)$/', (string) $table, $m) === 1) {
            $driverTables[(int) $m[1]] = $table;
        }
    }
    ksort($driverTables, SORT_NUMERIC);

    // Verify driver_id → account.id mapping and flag orphans. We do not
    // BLOCK on orphans — orphan tables still get backfilled — but we want
    // the deploy log to record the inconsistency so a follow-up branch
    // can act on it.
    $accountIds = array_map('intval', $pdo->query('SELECT id FROM `account`')->fetchAll(PDO::FETCH_COLUMN));
    $orphans    = [];
    foreach (array_keys($driverTables) as $driverId) {
        if (! in_array($driverId, $accountIds, true)) {
            $orphans[] = $driverId;
        }
    }

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO `driver_loads`
            (driver_id, frtl, date, variables, loadinfo, paid, notPaid, notes, np, op)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $totals = [
        'tables_scanned'   => 0,
        'rows_inserted'    => 0,
        'rows_skipped_dup' => 0,
        'per_driver'       => [],
        'orphan_drivers'   => $orphans,
    ];

    foreach ($driverTables as $driverId => $sourceTable) {
        $totals['tables_scanned']++;
        $countBefore = $totals['rows_inserted'];

        $rows = $pdo->query("SELECT frtl, date, variables, loadinfo, paid, notPaid, notes, np, op FROM `{$sourceTable}`")
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $insert->execute([
                $driverId,
                (int) $row['frtl'],
                (string) $row['date'],
                (string) ($row['variables'] ?? ''),
                (string) ($row['loadinfo']  ?? ''),
                (string) ($row['paid']      ?? '1-0-0-0-0-0-0-0'),
                (int)    ($row['notPaid']   ?? 0),
                $row['notes'] !== null ? (string) $row['notes'] : null,
                (string) ($row['np'] ?? '0.00'),
                (string) ($row['op'] ?? '0.00'),
            ]);
            if ($insert->rowCount() === 1) {
                $totals['rows_inserted']++;
            } else {
                $totals['rows_skipped_dup']++;
            }
        }

        $inserted = $totals['rows_inserted'] - $countBefore;
        $totals['per_driver'][$driverId] = [
            'source'   => $sourceTable,
            'inserted' => $inserted,
            'source_rows' => count($rows),
        ];
    }

    // Emit a structured line to stdout for the deploy log. The Migrator
    // tracking row records "this migration ran"; this line records "what
    // happened when it ran". Both are useful — the latter for verifying
    // the backfill matches the source counts.
    fwrite(STDOUT, "  backfill complete: " . json_encode([
        'tables_scanned'   => $totals['tables_scanned'],
        'rows_inserted'    => $totals['rows_inserted'],
        'rows_skipped_dup' => $totals['rows_skipped_dup'],
        'orphan_drivers'   => $totals['orphan_drivers'],
    ], JSON_UNESCAPED_SLASHES) . "\n");

    if ($orphans !== []) {
        fwrite(STDOUT, "  NOTE: orphan driver_id(s) without matching account.id: " . implode(',', $orphans) . "\n");
    }
};
