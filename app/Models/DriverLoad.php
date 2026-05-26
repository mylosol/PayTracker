<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;
use PDO;

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
}
