<?php

declare(strict_types=1);

/*
 * 2026_05_27_002_parse_driver_loads_blobs.php
 *
 * Parses the legacy `loadinfo` hyphen-string into the typed columns added by
 * 2026_05_27_001. Position mapping (verified against 3,313 live rows on
 * preview — see scripts/sample-driver-loads.php):
 *
 *   loadinfo[0]  load_type           tinyint (0/1/4)
 *   loadinfo[1]  empty_miles         int
 *   loadinfo[2]  pickup_city         varchar  ("Panama City, FL")
 *   loadinfo[3]  delivery_city       varchar
 *   loadinfo[4]  is_split            tinyint (0/1)
 *   loadinfo[5]  is_weekend          tinyint (0/1)
 *   loadinfo[6]  legacy constant "2" — preserved in loadinfo, not extracted
 *   loadinfo[7]  begin_empty_miles   int
 *   loadinfo[8]  used_google_maps    tinyint (0/1)
 *   loadinfo[9]  extra_pay           decimal
 *   loadinfo[10] dem_minutes         int
 *   loadinfo[11] break_minutes       int
 *   loadinfo[12] out_of_route_ind    tinyint
 *   loadinfo[13] out_of_route_miles  int
 *
 * Edge cases:
 *   - 6 rows in the live data have only 2 fields in loadinfo (legacy
 *     corruption). These rows are skipped — their typed columns stay NULL.
 *     The deploy log records the count.
 *   - terminal_pcola is derived from the legacy `account.pcola` column when
 *     present. If the column doesn't exist on the legacy account table the
 *     migration logs a notice and leaves the flag NULL.
 *
 * Idempotency: the UPDATE filters with `WHERE load_type IS NULL`, so a
 * re-run only touches rows that haven't been parsed yet. Safe to run
 * multiple times.
 *
 * What this migration does NOT do
 *   - It does not modify variables / loadinfo / paid. Those stay verbatim.
 *   - It does not retroactively re-parse rows that were already populated
 *     (deliberate — a manual fix in the future shouldn't be overwritten).
 */

return static function (PDO $pdo): void {
    // Detect whether the legacy account table carries a `pcola` flag. The
    // column is present on the live database (legacy schema) but absent
    // from the modernized one — gracefully handle both.
    $hasPcolaCol = false;
    $stmt = $pdo->query("SHOW COLUMNS FROM `account` LIKE 'pcola'");
    if ($stmt !== false && $stmt->fetch() !== false) {
        $hasPcolaCol = true;
    }

    /** @var array<int,int> $pcolaByDriver driver_id => 0|1 */
    $pcolaByDriver = [];
    if ($hasPcolaCol) {
        $rows = $pdo->query('SELECT id, pcola FROM `account`')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $pcolaByDriver[(int) $row['id']] = (int) $row['pcola'];
        }
    } else {
        echo "  notice: account.pcola column not present; terminal_pcola will be NULL\n";
    }

    // Process unparsed rows in driver_id batches so the transaction stays
    // bounded. We don't pull all 3.3K rows into memory at once.
    $unparsedDrivers = $pdo->query(
        'SELECT DISTINCT driver_id FROM `driver_loads` WHERE load_type IS NULL ORDER BY driver_id'
    )->fetchAll(PDO::FETCH_COLUMN);

    if (count($unparsedDrivers) === 0) {
        echo "  no rows need parsing — all driver_loads already have typed columns populated\n";
        return;
    }

    $update = $pdo->prepare(
        'UPDATE `driver_loads`
            SET load_type          = :load_type,
                empty_miles        = :empty_miles,
                pickup_city        = :pickup_city,
                delivery_city      = :delivery_city,
                is_split           = :is_split,
                is_weekend         = :is_weekend,
                begin_empty_miles  = :begin_empty_miles,
                used_google_maps   = :used_google_maps,
                extra_pay          = :extra_pay,
                dem_minutes        = :dem_minutes,
                break_minutes      = :break_minutes,
                out_of_route_ind   = :out_of_route_ind,
                out_of_route_miles = :out_of_route_miles,
                terminal_pcola     = :terminal_pcola
          WHERE driver_id = :driver_id AND frtl = :frtl'
    );

    $totalParsed     = 0;
    $totalSkipped    = 0;
    $totalNumeric    = 0;

    /**
     * Safe-cast a blob field to int. Empty string and non-numeric become
     * NULL so we never coerce garbage to 0 (which would lie).
     */
    $toInt = static function (string $v): ?int {
        $v = trim($v);
        return $v !== '' && is_numeric($v) ? (int) $v : null;
    };
    $toDec = static function (string $v) use (&$totalNumeric): ?string {
        $v = trim($v);
        if ($v === '' || ! is_numeric($v)) {
            return null;
        }
        $totalNumeric++;
        return number_format((float) $v, 2, '.', '');
    };

    foreach ($unparsedDrivers as $driverId) {
        $driverId = (int) $driverId;
        $rows = $pdo->prepare(
            'SELECT frtl, loadinfo FROM `driver_loads`
              WHERE driver_id = ? AND load_type IS NULL'
        );
        $rows->execute([$driverId]);
        $batch = $rows->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();
        try {
            foreach ($batch as $row) {
                $frtl = (int) $row['frtl'];
                $parts = explode('-', (string) $row['loadinfo']);
                if (count($parts) < 14) {
                    // Legacy-corrupted row — skip, leave typed columns NULL.
                    $totalSkipped++;
                    continue;
                }

                $update->execute([
                    ':load_type'          => $toInt($parts[0]),
                    ':empty_miles'        => $toInt($parts[1]),
                    ':pickup_city'        => $parts[2] !== '' ? $parts[2] : null,
                    ':delivery_city'      => $parts[3] !== '' ? $parts[3] : null,
                    ':is_split'           => $toInt($parts[4]),
                    ':is_weekend'         => $toInt($parts[5]),
                    ':begin_empty_miles'  => $toInt($parts[7]),
                    ':used_google_maps'   => $toInt($parts[8]),
                    ':extra_pay'          => $toDec($parts[9]),
                    ':dem_minutes'        => $toInt($parts[10]),
                    ':break_minutes'      => $toInt($parts[11]),
                    ':out_of_route_ind'   => $toInt($parts[12]),
                    ':out_of_route_miles' => $toInt($parts[13]),
                    ':terminal_pcola'     => $pcolaByDriver[$driverId] ?? null,
                    ':driver_id'          => $driverId,
                    ':frtl'               => $frtl,
                ]);
                $totalParsed++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    echo "  parsed   {$totalParsed} rows\n";
    echo "  skipped  {$totalSkipped} legacy-corrupted rows (loadinfo has <14 fields)\n";
};
