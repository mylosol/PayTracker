<?php

declare(strict_types=1);

/*
 * 2026_05_28_002_backfill_pay_rates.php
 *
 * Backfills the unified `pay_rates` table from the six legacy pay tables:
 *
 *   Pensacola, round-trip:  PensacolaPayDefault    → stage='default'
 *                           PensacolaPayCurrent    → stage='current'
 *   Pensacola, long-haul:   LHPensacolaPayDefault  → stage='default'
 *                           LHPensacolaPayCurrent  → stage='current'
 *   Panama City, round-trip:PanamaPay              → BOTH default AND current
 *
 * The Panama edge case: legacy never split PanamaPay into Default/Current,
 * so we backfill it twice (same data into both stages) so the modern
 * "reset to defaults" path works consistently for all terminals. If anyone
 * later customises Panama rates, they edit the draft → promote to current;
 * defaults stay as the backfill snapshot.
 *
 * The draft stage is NOT seeded — drafts only exist when an admin starts
 * editing. Reading a draft for a (terminal, trip_type) with no draft rows
 * is the model's signal to fall back to current.
 *
 * Sample data from the preview DB (captured by scripts/sample-pay-tables.php):
 *   PensacolaPayDefault: 114 rows, miles 10–250, includes one sentinel
 *     rate=999.9999 at miles=122 (pre-existing legacy data — preserved).
 *   PensacolaPayCurrent: 114 rows, DIFFERS from Default (customised).
 *   LHPensacolaPayDefault: 114 rows
 *   LHPensacolaPayCurrent: 114 rows, DIFFERS from Default (customised).
 *   PanamaPay:           40 rows.
 *
 * Idempotency: INSERT IGNORE keyed on the PK (terminal, trip_type, stage,
 * miles). Safe to re-run from a fresh _migrations row.
 *
 * What this migration does NOT do
 *   - It does not modify the legacy tables. The Pi keeps reading them.
 *   - It does not seed any 'draft' rows. Drafts are user-created.
 */

return static function (PDO $pdo): void {
    /**
     * Discover which legacy tables exist on this host. The preview DB has
     * all six; a clean dev box may have none. Either is fine — we just
     * skip whatever isn't there and report at the end.
     */
    $exists = static function (string $table) use ($pdo): bool {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    };

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO `pay_rates` (terminal, trip_type, stage, miles, rate)
         VALUES (?, ?, ?, ?, ?)'
    );

    /**
     * Copy every (miles, rate) row from a legacy table into pay_rates under
     * the given (terminal, trip_type, stage) tuple. Returns insert count.
     */
    $copy = static function (string $legacyTable, string $terminal, string $tripType, string $stage)
        use ($pdo, $insert, $exists): int {
        if (! $exists($legacyTable)) {
            fwrite(STDOUT, "  skip {$legacyTable} (absent on this host)\n");
            return 0;
        }
        $inserted = 0;
        $stmt = $pdo->query("SELECT miles, rate FROM `{$legacyTable}`");
        foreach ($stmt as $row) {
            // Miles are stored as strings in some legacy tables; cast hard.
            $miles = (int) $row['miles'];
            $rate  = (string) $row['rate'];
            if ($miles <= 0) {
                // Defensive: a zero/negative mile row is meaningless as a
                // lookup key and would collide with the SMALLINT UNSIGNED
                // domain. Skip.
                continue;
            }
            $insert->execute([$terminal, $tripType, $stage, $miles, $rate]);
            if ($insert->rowCount() === 1) {
                $inserted++;
            }
        }
        return $inserted;
    };

    $totals = [
        'pensacola_rtb_default' => $copy('PensacolaPayDefault',   'pensacola', 'round_trip', 'default'),
        'pensacola_rtb_current' => $copy('PensacolaPayCurrent',   'pensacola', 'round_trip', 'current'),
        'pensacola_lhb_default' => $copy('LHPensacolaPayDefault', 'pensacola', 'long_haul',  'default'),
        'pensacola_lhb_current' => $copy('LHPensacolaPayCurrent', 'pensacola', 'long_haul',  'current'),
        // Panama has no Default twin — seed both stages from the same source.
        'panama_rtb_default'    => $copy('PanamaPay',             'panama',    'round_trip', 'default'),
        'panama_rtb_current'    => $copy('PanamaPay',             'panama',    'round_trip', 'current'),
    ];

    fwrite(
        STDOUT,
        "  backfill complete: " . json_encode($totals, JSON_UNESCAPED_SLASHES) . "\n",
    );
};
