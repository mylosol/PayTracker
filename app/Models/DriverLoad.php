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
     * Loads for a specific driver, restricted to a date range. The window
     * is inclusive on the start (>= since) and exclusive on the end
     * (< until) so callers can use "midnight to midnight" without
     * worrying about second-boundary off-by-one.
     *
     * Typed columns only (no blob strings) — the dashboard renders these
     * via the typed surface; the /loads page is where the blobs live.
     *
     * @return list<array{
     *   frtl:int, date:string,
     *   load_type:?int, pickup_city:?string, delivery_city:?string,
     *   empty_miles:?int, is_split:?int, is_weekend:?int,
     *   extra_pay:?string, dem_minutes:?int, break_minutes:?int,
     *   out_of_route_miles:?int, np:string, op:string,
     * }>
     */
    public function forDriverInWindow(int $driverId, string $since, string $until): array
    {
        $sql = '
            SELECT
                frtl, date,
                load_type, pickup_city, delivery_city,
                empty_miles, is_split, is_weekend,
                extra_pay, dem_minutes, break_minutes,
                out_of_route_miles,
                np, op, pay_breakdown
            FROM ' . self::ident(self::$table) . '
            WHERE driver_id = ?
              AND date >= ?
              AND date <  ?
            ORDER BY date DESC, frtl DESC';
        $rows = $this->prepared($sql, [$driverId, $since, $until])->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Aggregate counters for a driver across a date window. Same window
     * semantics as forDriverInWindow().
     *
     * "miles" is the sum of (pickup → delivery distance + return empty +
     * any deadhead) — a single number representing total driving on the
     * load. Pulled from the typed columns; rows where load_type IS NULL
     * (legacy backfill couldn't parse the blob) contribute zero.
     *
     * @return array{count:int, np_total:string, op_total:string, miles_total:int}
     */
    public function totalsForDriverInWindow(int $driverId, string $since, string $until): array
    {
        $sql = '
            SELECT
                COUNT(*)                                                AS row_count,
                COALESCE(SUM(np), 0)                                    AS np_total,
                COALESCE(SUM(op), 0)                                    AS op_total,
                COALESCE(SUM(
                    COALESCE(empty_miles, 0)
                  + COALESCE(begin_empty_miles, 0)
                  + COALESCE(out_of_route_miles, 0)
                ), 0)                                                   AS miles_total
            FROM ' . self::ident(self::$table) . '
            WHERE driver_id = ?
              AND date >= ?
              AND date <  ?';
        $row = $this->prepared($sql, [$driverId, $since, $until])->fetch();
        if (! is_array($row)) {
            return ['count' => 0, 'np_total' => '0.00', 'op_total' => '0.00', 'miles_total' => 0];
        }
        return [
            'count'       => (int) $row['row_count'],
            'np_total'    => (string) $row['np_total'],
            'op_total'    => (string) $row['op_total'],
            'miles_total' => (int) $row['miles_total'],
        ];
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
     *   notes?:string|null,
     * } $data
     *
     * @return int the frtl assigned to the inserted row
     *
     * @throws InvalidArgumentException if driver_id does not exist in account
     */
    /**
     * Walk driver_loads in batches, calling $compute(row) → {np, op} on
     * each typed row, and UPDATE-ing the np/op columns when the new values
     * differ from the stored ones.
     *
     * Designed for the /pay-admin "Recompute np/op" action. The optional
     * filters scope the recompute to a single driver and/or a date range
     * so admins can re-run pay for one week without touching the whole
     * history.
     *
     * The compute callable now returns the full PayCalculator breakdown
     * (keyed by np, op, base_pay, shift_pay, etc.). We only diff on
     * np/op to decide "changed", but we ALWAYS rewrite pay_breakdown
     * when there's an np/op change, so the dashboard breakdown card
     * stays in sync.
     *
     * @param callable(array<string,mixed>): array<string,mixed> $compute
     * @return array{considered:int, updated:int, unchanged:int, skipped:int}
     */
    public function recomputePay(
        callable $compute,
        ?int $driverFilter = null,
        ?string $sinceDate = null,
    ): array {
        $where  = ['load_type IS NOT NULL'];
        $params = [];
        if ($driverFilter !== null && $driverFilter > 0) {
            $where[]  = 'driver_id = ?';
            $params[] = $driverFilter;
        }
        if ($sinceDate !== null && $sinceDate !== '') {
            $where[]  = 'date >= ?';
            $params[] = $sinceDate;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = 'SELECT
                    driver_id, frtl,
                    load_type, empty_miles, pickup_city, delivery_city,
                    is_split, is_weekend, begin_empty_miles,
                    extra_pay, dem_minutes, break_minutes,
                    out_of_route_ind, out_of_route_miles, terminal_pcola,
                    variables, np, op, pay_breakdown
                FROM `driver_loads` ' . $whereSql . '
                ORDER BY driver_id ASC, frtl ASC';
        $rows = $this->prepared($sql, $params)->fetchAll();
        if (! is_array($rows)) {
            return ['considered' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
        }

        // The variables column is rewritten alongside np/op when the
        // closure surfaces an `_variables` blob — covers the driver-
        // refresh path where the snapshot was wrong at insert time
        // and needs to be corrected from the current profile. Admin
        // recompute leaves the blob untouched, in which case the new
        // value equals the old value and the column write is a no-op.
        $update = $this->connection->pdo()->prepare(
            'UPDATE `driver_loads`
             SET np = ?, op = ?, pay_breakdown = ?, variables = ?
             WHERE driver_id = ? AND frtl = ?'
        );

        $stats = ['considered' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $stats['considered']++;
            try {
                $result = $compute($row);
            } catch (Throwable) {
                $stats['skipped']++;
                continue;
            }
            $newNp        = (float) $result['np'];
            $newOp        = (float) $result['op'];
            $newVariables = isset($result['_variables']) && is_string($result['_variables'])
                ? $result['_variables']
                : (string) ($row['variables'] ?? '');
            $oldNp        = (float) $row['np'];
            $oldOp        = (float) $row['op'];
            $oldVariables = (string) ($row['variables'] ?? '');
            // Unchanged short-circuit: same totals AND same variables blob
            // AND we already have a breakdown stored. Any mismatch (even
            // just the blob) means we re-write so the stored snapshot
            // stays consistent with what the calculator was fed.
            $sameTotals      = abs($newNp - $oldNp) < 0.005 && abs($newOp - $oldOp) < 0.005;
            $sameVariables   = $newVariables === $oldVariables;
            $haveBreakdown   = isset($row['pay_breakdown']) && $row['pay_breakdown'] !== null && $row['pay_breakdown'] !== '';
            if ($sameTotals && $sameVariables && $haveBreakdown) {
                $stats['unchanged']++;
                continue;
            }
            // Strip the recompute-only sentinel before encoding so the
            // stored JSON matches the calculator's published shape.
            unset($result['_variables']);
            $breakdownJson = json_encode($result, JSON_THROW_ON_ERROR);
            $update->execute([
                number_format($newNp, 2, '.', ''),
                number_format($newOp, 2, '.', ''),
                $breakdownJson,
                $newVariables,
                (int) $row['driver_id'],
                (int) $row['frtl'],
            ]);
            $stats['updated']++;
        }
        return $stats;
    }

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
        // `variables` is the per-load tenure/shift snapshot. The caller
        // builds it from the driver's profile (hire_date → tenure band,
        // shift → night flag) via VariableBlobBuilder; if not supplied
        // we fall back to the JUNIOR band ('6') so a caller that skips
        // the builder under-pays rather than over-pays. Matches the
        // builder's own fallback for the same reason.
        $variables = isset($data['variables']) && is_string($data['variables']) && $data['variables'] !== ''
            ? $data['variables']
            : '6-day--0';
        // `paid` is an 8-field pay-state vector. Fresh inserts start in the
        // "submitted, unpaid" state — first slot 1, rest 0.
        $paid = '1-0-0-0-0-0-0-0';

        // FRTL is normally a USER-PROVIDED dispatch identifier, but the
        // form makes it optional — drivers who don't have the paperwork
        // handy can submit and we synthesise the next-available number
        // per driver. When the caller passes a positive int, we use it
        // verbatim and let the composite PK reject duplicates (the
        // controller pre-flights via frtlExists() for a friendly error).
        // When the caller passes 0/missing, we compute MAX(frtl)+1 with
        // a small retry loop in case two submissions race for the same
        // slot.
        $frtl = (int) ($data['frtl'] ?? 0);
        $autoAssign = $frtl <= 0;

        // np / op default to 0.00 when the caller doesn't pass them. The
        // load-entry controller computes them via PayCalculator before
        // calling here so the dashboard's totals reflect reality on the
        // very first render. The admin-driven /pay-admin/recompute path
        // exists for bulk historical recomputes after a rate change.
        $np = number_format((float) ($data['np'] ?? 0), 2, '.', '');
        $op = number_format((float) ($data['op'] ?? 0), 2, '.', '');

        // pay_breakdown: the structured PayCalculator result, JSON-encoded.
        // Optional — older callers that haven't been updated still work
        // and the dashboard renders a "no breakdown" placeholder for
        // rows where it's NULL.
        $payBreakdown = null;
        if (isset($data['pay_breakdown']) && is_array($data['pay_breakdown'])) {
            $payBreakdown = json_encode($data['pay_breakdown'], JSON_THROW_ON_ERROR);
        }

        // terminal_pcola is a legacy column with no remaining read-side —
        // the legacy formula had a branch that referenced it, but both
        // sides of the branch were identical (it was dead). The column
        // stays on driver_loads (additive-only migration policy) and is
        // written as a literal 0 here so we don't need to thread it
        // through the calling code.
        $sql = 'INSERT INTO `driver_loads` (
                    driver_id, frtl, date,
                    variables, loadinfo, paid, notPaid, notes, np, op, pay_breakdown,
                    load_type, empty_miles, pickup_city, delivery_city,
                    is_split, is_weekend, begin_empty_miles, used_google_maps,
                    extra_pay, dem_minutes, break_minutes,
                    out_of_route_ind, out_of_route_miles, terminal_pcola
                ) VALUES (
                    ?, ?, NOW(),
                    ?, ?, ?, 0, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, 0
                )';

        // When auto-assigning, retry on PK collision so two concurrent
        // submissions for the same driver don't both claim MAX+1. Bounded
        // to a small number of attempts — a sustained collision rate would
        // indicate a runaway client and is better surfaced as an error
        // than silently absorbed.
        $maxAttempts = $autoAssign ? 5 : 1;
        $lastError   = null;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($autoAssign) {
                $frtl = $this->nextFrtlFor((int) $data['driver_id']);
            }
            try {
                $this->prepared($sql, [
                    $data['driver_id'], $frtl,
                    $variables, $loadinfo, $paid, $data['notes'] ?? null,
                    $np, $op, $payBreakdown,
                    $data['load_type'], $data['empty_miles'], $data['pickup_city'], $data['delivery_city'],
                    $data['is_split'], $data['is_weekend'], $data['begin_empty_miles'], $data['used_google_maps'],
                    number_format($data['extra_pay'], 2, '.', ''),
                    $data['dem_minutes'], $data['break_minutes'],
                    $data['out_of_route_ind'], $data['out_of_route_miles'],
                ]);
                return $frtl;
            } catch (\PDOException $e) {
                // 23000 / 1062 = duplicate PK. On auto-assign we loop and
                // try the next number; on user-supplied frtl we re-throw
                // (controller pre-flights but a race could still land here).
                $isDupe = $e->getCode() === '23000'
                    || (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062);
                if (! $isDupe || ! $autoAssign) {
                    throw $e;
                }
                $lastError = $e;
            }
        }
        throw new \RuntimeException(
            'Could not assign a free FRTL after ' . $maxAttempts . ' attempts',
            0,
            $lastError
        );
    }

    /**
     * Compute the next-available FRTL for a driver: MAX(frtl)+1, or 1 if
     * the driver has no rows yet. Not collision-safe on its own; callers
     * that race must wrap with a retry loop on PK violations.
     */
    private function nextFrtlFor(int $driverId): int
    {
        $sql = 'SELECT COALESCE(MAX(frtl), 0) + 1 FROM `driver_loads` WHERE driver_id = ?';
        $next = $this->prepared($sql, [$driverId])->fetchColumn();
        return (int) $next > 0 ? (int) $next : 1;
    }

    /**
     * True if (driver_id, frtl) is already taken. Used by the controller
     * to pre-flight check and produce a friendlier error than letting
     * the PK collision bubble up as a PDOException.
     */
    public function frtlExists(int $driverId, int $frtl): bool
    {
        $sql = 'SELECT 1 FROM `driver_loads` WHERE driver_id = ? AND frtl = ? LIMIT 1';
        return $this->prepared($sql, [$driverId, $frtl])->fetchColumn() !== false;
    }
}
