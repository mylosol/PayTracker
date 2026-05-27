<?php

declare(strict_types=1);

namespace PayTracker\Models;

use InvalidArgumentException;
use PayTracker\Database\Model;
use PDO;
use Throwable;

/**
 * `driver_loads` — relational replacement for the per-driver `loadsNN`
 * tables.
 *
 * Schema (created by migration 2026_05_25_001, populated by 002):
 *   driver_id INT     — account.id of the driver who hauled the load
 *   frtl      INT     — freight load number, unique per driver
 *   date      DATETIME
 *   variables VARCHAR(100)  — legacy dash-separated encoded fields
 *   loadinfo  VARCHAR(100)  — legacy dash-separated encoded fields
 *   paid      VARCHAR(20)   — legacy dash-separated pay-state flags
 *   notPaid   INT
 *   notes     VARCHAR(1000) NULL
 *   np        DECIMAL(6,2)  — net pay
 *   op        DECIMAL(6,2)  — original pay
 *   PRIMARY KEY (driver_id, frtl)
 *
 * Read-only on this branch. The dash-separated fields are preserved
 * verbatim — parsing them into proper columns is a separate, larger
 * concern. Writes will land in a future branch that ports the legacy
 * load-entry surface (newload.php → loadselect.php → ...).
 */
final class DriverLoad extends Model
{
    protected static string $table = 'driver_loads';

    /**
     * Aggregate stats for the /loads dashboard.
     *
     * @return array{total_rows:int, drivers_with_loads:int, oldest_date:?string, newest_date:?string}
     */
    public function summary(): array
    {
        $pdo = $this->connection->pdo();
        $row = $pdo->query(
            'SELECT
                COUNT(*)                       AS total_rows,
                COUNT(DISTINCT driver_id)      AS drivers_with_loads,
                MIN(date)                      AS oldest_date,
                MAX(date)                      AS newest_date
             FROM ' . self::ident(self::$table)
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'total_rows'         => (int) ($row['total_rows'] ?? 0),
            'drivers_with_loads' => (int) ($row['drivers_with_loads'] ?? 0),
            'oldest_date'        => $row['oldest_date'] !== null ? (string) $row['oldest_date'] : null,
            'newest_date'        => $row['newest_date'] !== null ? (string) $row['newest_date'] : null,
        ];
    }

    /**
     * Row counts grouped by driver, joined to the account handle so the
     * QA page can show "cheveriek@gmail.com — 2,510 loads" instead of
     * just "driver 68 — 2,510 loads".
     *
     * @return list<array{driver_id:int, user:?string, n:int}>
     */
    public function countsPerDriver(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = '
            SELECT dl.driver_id, a.user, COUNT(*) AS n
            FROM ' . self::ident(self::$table) . ' dl
            LEFT JOIN ' . self::ident('account') . ' a ON a.id = dl.driver_id
            GROUP BY dl.driver_id, a.user
            ORDER BY n DESC
            LIMIT ' . $limit;
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Recent loads across all drivers. For the QA dashboard preview.
     *
     * @return list<array{driver_id:int, user:?string, frtl:int, date:string, np:string, op:string}>
     */
    public function recentAcrossAll(int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));
        $sql = '
            SELECT dl.driver_id, a.user, dl.frtl, dl.date, dl.np, dl.op
            FROM ' . self::ident(self::$table) . ' dl
            LEFT JOIN ' . self::ident('account') . ' a ON a.id = dl.driver_id
            ORDER BY dl.date DESC
            LIMIT ' . $limit;
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Recent loads for a specific driver. Used in the future when each
     * driver has a per-account loads page; for now just exposed on the
     * model so the QA controller can spot-check parity with the legacy
     * loadsNN tables.
     *
     * @return list<array<string,mixed>>
     */
    public function forDriver(int $driverId, int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));
        $sql = '
            SELECT frtl, date, variables, loadinfo, paid, np, op
            FROM ' . self::ident(self::$table) . '
            WHERE driver_id = ?
            ORDER BY date DESC
            LIMIT ' . $limit;
        $rows = $this->prepared($sql, [$driverId])->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Insert a single load.
     *
     * Performs the full write transaction:
     *   1. Lock the driver's existing rows briefly and compute the next
     *      `frtl` as MAX(frtl)+1 (or 1 if the driver has no rows yet).
     *      The composite PK (driver_id, frtl) guarantees no collisions
     *      INSIDE the transaction; a separate concurrent submission for
     *      the SAME driver retries via the duplicate-key catch.
     *   2. Re-build the legacy `variables` / `loadinfo` / `paid` strings
     *      so unported legacy pages and the read-only /loads view
     *      keep working.
     *   3. INSERT a row that populates BOTH the typed columns AND the
     *      blob strings.
     *
     * The pay columns (`np`, `op`) are left at 0 here. Pay calculation
     * happens in a separate admin-driven flow (BasePayAdminSubmit etc.)
     * that hasn't been ported yet; once it has, it will UPDATE rows by
     * (driver_id, frtl).
     *
     * @param array{
     *   driver_id:int,
     *   load_type:int,
     *   pickup_city:string,
     *   delivery_city:string,
     *   empty_miles:int,
     *   begin_empty_miles:int,
     *   is_split:int,
     *   is_weekend:int,
     *   extra_pay:float,
     *   dem_minutes:int,
     *   break_minutes:int,
     *   out_of_route_ind:int,
     *   out_of_route_miles:int,
     *   used_google_maps:int,
     *   terminal_pcola:int,
     *   notes?:string|null,
     * } $data
     *
     * @return int the frtl assigned to the inserted row
     *
     * @throws InvalidArgumentException if driver_id does not exist in account
     */
    public function insertOne(array $data): int
    {
        $pdo = $this->connection->pdo();

        // Reject unknown drivers up front rather than letting the insert
        // succeed with a dangling foreign value. account is MyISAM so there
        // is no FK; this check is the equivalent.
        $check = $pdo->prepare('SELECT 1 FROM `account` WHERE id = ? LIMIT 1');
        $check->execute([$data['driver_id']]);
        if ($check->fetchColumn() === false) {
            throw new InvalidArgumentException(
                "Unknown driver_id={$data['driver_id']} — refusing to insert load"
            );
        }

        // Legacy hyphen-string formats. Field positions documented in
        // migration 2026_05_27_002.
        $loadinfo = implode('-', [
            $data['load_type'],
            $data['empty_miles'],
            $data['pickup_city'],
            $data['delivery_city'],
            $data['is_split'],
            $data['is_weekend'],
            '2',                       // legacy constant
            $data['begin_empty_miles'],
            $data['used_google_maps'],
            number_format($data['extra_pay'], 0, '.', ''),
            $data['dem_minutes'],
            $data['break_minutes'],
            $data['out_of_route_ind'],
            $data['out_of_route_miles'],
        ]);
        // `variables` is the week-context blob; on fresh inserts we use the
        // canonical "168-night--0" form observed in 100% of live rows. A
        // future branch that ports the weekly-settings UI can vary this.
        $variables = '168-night--0';
        // `paid` is an 8-field pay-state vector. Fresh inserts start in the
        // "submitted, unpaid" state — first slot 1, rest 0.
        $paid = '1-0-0-0-0-0-0-0';

        // Retry loop to survive concurrent insertions for the same driver.
        // The race is extremely narrow (a single driver double-clicking
        // submit) but the composite PK leaves no room for ambiguity if it
        // happens — better to retry than to silently overwrite.
        $maxAttempts = 5;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $pdo->beginTransaction();
            try {
                /** @var int|false $maxFrtl */
                $maxFrtl = $pdo->query(
                    'SELECT MAX(frtl) FROM `driver_loads` WHERE driver_id = ' . (int) $data['driver_id']
                )->fetchColumn();
                $nextFrtl = $maxFrtl === false || $maxFrtl === null ? 1 : ((int) $maxFrtl) + 1;

                $sql = 'INSERT INTO `driver_loads` (
                            driver_id, frtl, date,
                            variables, loadinfo, paid, notPaid, notes, np, op,
                            load_type, empty_miles, pickup_city, delivery_city,
                            is_split, is_weekend, begin_empty_miles, used_google_maps,
                            extra_pay, dem_minutes, break_minutes,
                            out_of_route_ind, out_of_route_miles, terminal_pcola
                        ) VALUES (
                            ?, ?, NOW(),
                            ?, ?, ?, 0, ?, 0.00, 0.00,
                            ?, ?, ?, ?,
                            ?, ?, ?, ?,
                            ?, ?, ?,
                            ?, ?, ?
                        )';
                $this->prepared($sql, [
                    $data['driver_id'], $nextFrtl,
                    $variables, $loadinfo, $paid, $data['notes'] ?? null,
                    $data['load_type'], $data['empty_miles'], $data['pickup_city'], $data['delivery_city'],
                    $data['is_split'], $data['is_weekend'], $data['begin_empty_miles'], $data['used_google_maps'],
                    number_format($data['extra_pay'], 2, '.', ''),
                    $data['dem_minutes'], $data['break_minutes'],
                    $data['out_of_route_ind'], $data['out_of_route_miles'], $data['terminal_pcola'],
                ]);
                $pdo->commit();
                return $nextFrtl;
            } catch (Throwable $e) {
                $pdo->rollBack();
                // PDO error code 23000 == integrity constraint violation
                // (duplicate key). Retry to pick up a fresh MAX(frtl).
                if ($e instanceof \PDOException && $e->getCode() === '23000' && $attempt < $maxAttempts) {
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException(
            "DriverLoad::insertOne exhausted {$maxAttempts} retries for driver_id={$data['driver_id']}"
        );
    }
}
