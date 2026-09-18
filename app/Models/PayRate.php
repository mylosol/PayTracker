<?php

declare(strict_types=1);

namespace PayTracker\Models;

use InvalidArgumentException;
use PDO;
use PayTracker\Database\Connection;
use PayTracker\Database\Model;

/**
 * `pay_rates` — relational replacement for the legacy per-table pay rates.
 *
 * Schema (migration 2026_05_28_001):
 *   terminal   VARCHAR(16)        — vestigial dimension, see below
 *   trip_type  VARCHAR(16)        — 'round_trip' | 'long_haul'
 *   stage      VARCHAR(8)         — 'default' | 'current' | 'draft'
 *   miles      SMALLINT UNSIGNED
 *   rate       DECIMAL(10,4)
 *   PRIMARY KEY (terminal, trip_type, stage, miles)
 *
 * Vestigial terminal dimension
 *   The original design assumed Pensacola and Panama City had distinct
 *   pay rates, so both terminals' rate tables were duplicated under
 *   stage = 'default' | 'current' | 'draft'. That assumption turned
 *   out to be wrong — the rates are identical. From this model's
 *   perspective there is a single canonical rate set, and we hard-code
 *   the terminal to 'pensacola' on every read and write. The 'panama'
 *   rows stay in the table (additive-only migration policy) but are
 *   unread by the app. A future destructive migration can drop the
 *   column and its rows when the dust has settled.
 *
 * Editing model
 *   - 'default' is read-mostly. The only mutator is resetCurrentToDefault().
 *   - 'current' is the live pay-rate table read by PayCalculator.
 *     Admins do not edit it directly; they edit drafts and promote.
 *   - 'draft' is the admin's working copy. Add/edit/delete tiers freely,
 *     then promoteDraftToCurrent() to publish. startOrResetDraft()
 *     discards the draft and starts again from current.
 *
 * Concurrency: promote and reset operations run inside a single transaction
 * so a partial failure can't leave the live table half-replaced.
 */
class PayRate extends Model
{
    protected static string $table = 'pay_rates';

    /**
     * The single canonical terminal we read/write under. See class
     * docblock for why this is a hard-coded constant rather than a
     * caller-supplied value.
     */
    private const TERMINAL = 'pensacola';

    /** @var list<string> */
    public const TRIP_TYPES = ['round_trip', 'long_haul'];

    /** @var list<string> */
    public const STAGES = ['default', 'current', 'draft'];

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
    }

    private function assertTripType(string $tripType): void
    {
        if (! in_array($tripType, self::TRIP_TYPES, true)) {
            throw new InvalidArgumentException("Unknown trip_type: {$tripType}");
        }
    }

    private function assertStage(string $stage): void
    {
        if (! in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException("Unknown stage: {$stage}");
        }
    }

    /**
     * Aggregate counters for the /pay-admin dashboard.
     *
     * @return array{
     *     total_rows:int,
     *     by_stage: array<string,int>,
     *     by_trip: list<array{trip_type:string, default_n:int, current_n:int, draft_n:int}>
     * }
     */
    public function summary(): array
    {
        $pdo = $this->connection->pdo();

        $total = (int) $pdo->query(
            'SELECT COUNT(*) FROM ' . self::ident(self::$table) .
            ' WHERE terminal = ' . $pdo->quote(self::TERMINAL)
        )->fetchColumn();

        $byStage = [];
        $stmt = $pdo->prepare(
            'SELECT stage, COUNT(*) AS n FROM ' . self::ident(self::$table) .
            ' WHERE terminal = ? GROUP BY stage'
        );
        $stmt->execute([self::TERMINAL]);
        foreach ($stmt as $row) {
            $byStage[(string) $row['stage']] = (int) $row['n'];
        }

        // Per-trip_type breakdown — drives the editor cards.
        $stmt = $pdo->prepare(
            'SELECT trip_type, stage, COUNT(*) AS n
             FROM ' . self::ident(self::$table) . '
             WHERE terminal = ?
             GROUP BY trip_type, stage'
        );
        $stmt->execute([self::TERMINAL]);
        $bucket = [];
        foreach ($stmt as $row) {
            $key = (string) $row['trip_type'];
            $bucket[$key]['trip_type']  = (string) $row['trip_type'];
            $bucket[$key]['default_n']  = $bucket[$key]['default_n']  ?? 0;
            $bucket[$key]['current_n']  = $bucket[$key]['current_n']  ?? 0;
            $bucket[$key]['draft_n']    = $bucket[$key]['draft_n']    ?? 0;
            $bucket[$key][$row['stage'] . '_n'] = (int) $row['n'];
        }
        ksort($bucket);
        $breakdown = [];
        foreach ($bucket as $b) {
            $breakdown[] = [
                'trip_type' => $b['trip_type'],
                'default_n' => $b['default_n'],
                'current_n' => $b['current_n'],
                'draft_n'   => $b['draft_n'],
            ];
        }

        return [
            'total_rows' => $total,
            'by_stage'   => $byStage,
            'by_trip'    => $breakdown,
        ];
    }

    /**
     * Rungs that look WRONG rather than merely different. Feed it a ladder
     * as [miles => pay] and it returns [miles => reason] for the rows worth
     * a look before promoting.
     *
     * Two rules, both drawn from the live ladder:
     *
     *   1. Ladder break — a rung that pays LESS than the rung below it, so a
     *      load of 43–44 miles would pay less than one of 41–42. That is
     *      what a row missed by a raise pass looks like: the Pensacola
     *      round-trip 44-mile rung still carries its 2019 value while all
     *      111 of its neighbours were raised.
     *   2. Placeholder outlier — a rung paying more than five times the
     *      median. The legacy ladder carried 999.9999 at 122 miles, a
     *      tripwire value that was never a pay rate; that shape should be
     *      glaring on screen instead of quietly paying ~$1,000.
     *
     * Deliberately silent about big-but-monotonic jumps (the long-haul
     * 204 → 206 mile step is +$50 and presumably intentional). A rule that
     * flagged those too would train everyone to ignore the flag.
     *
     * @param array<int, float> $rungs miles => pay, any order
     * @return array<int, string> miles => human-readable reason
     */
    public static function flagRungs(array $rungs): array
    {
        ksort($rungs);

        $flags = [];
        if ($rungs === []) {
            return $flags;
        }

        $values = array_values($rungs);
        sort($values);
        $median = (float) $values[intdiv(count($values), 2)];

        // Rule 2 first so a placeholder value is named as such rather than
        // as a ladder break against whatever row precedes it.
        foreach ($rungs as $miles => $pay) {
            if ($median > 0 && $pay > 5 * $median) {
                $flags[$miles] = sprintf(
                    'far above the rest of this ladder (%s against a %s median) — placeholder value?',
                    '$' . number_format($pay, 2),
                    '$' . number_format($median, 2),
                );
            }
        }

        $prevMiles = null;
        foreach ($rungs as $miles => $pay) {
            if (isset($flags[$miles])) {
                // A placeholder value isn't a rung in the ladder's shape:
                // skip it so it can't manufacture a break on its neighbour.
                continue;
            }
            if ($prevMiles !== null) {
                $prevPay = (float) $rungs[$prevMiles];
                if ($pay < $prevPay - 0.005) {
                    $flags[$miles] = sprintf(
                        'pays %s less than the %d mi row, so a load here earns less than a shorter one',
                        '$' . number_format($prevPay - $pay, 2),
                        $prevMiles,
                    );
                }
            }
            $prevMiles = $miles;
        }

        return $flags;
    }

    /**
     * All (miles, rate) tiers for a given (trip_type, stage) sorted
     * ascending by miles — the natural order for the editor UI.
     *
     * @return list<array{miles:int, rate:string}>
     */
    public function tiers(string $tripType, string $stage): array
    {
        $this->assertTripType($tripType);
        $this->assertStage($stage);

        $sql = 'SELECT miles, rate FROM ' . self::ident(self::$table) . '
                WHERE terminal = ? AND trip_type = ? AND stage = ?
                ORDER BY miles ASC';
        $rows = $this->prepared($sql, [self::TERMINAL, $tripType, $stage])->fetchAll();
        if (! is_array($rows)) {
            return [];
        }
        return array_map(
            static fn (array $r): array => [
                'miles' => (int) $r['miles'],
                'rate'  => (string) $r['rate'],
            ],
            $rows,
        );
    }

    /**
     * True if a draft exists for the given trip_type.
     */
    public function hasDraft(string $tripType): bool
    {
        $this->assertTripType($tripType);

        $sql = 'SELECT 1 FROM ' . self::ident(self::$table) . '
                WHERE terminal = ? AND trip_type = ? AND stage = ?
                LIMIT 1';
        $stmt = $this->prepared($sql, [self::TERMINAL, $tripType, 'draft']);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Copy current → draft. Replaces any existing draft for that
     * trip_type ("Reset Test" semantics from the legacy UI). Runs in
     * a transaction so a partial state can't persist on failure.
     */
    public function startOrResetDraft(string $tripType): void
    {
        $this->assertTripType($tripType);

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare(
                'DELETE FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $del->execute([self::TERMINAL, $tripType, 'draft']);

            $ins = $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (terminal, trip_type, stage, miles, rate)
                 SELECT terminal, trip_type, ?, miles, rate
                 FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $ins->execute(['draft', self::TERMINAL, $tripType, 'current']);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Multiply every draft tier's rate by (1 + percent/100), rounded
     * to 2 decimals (dollars.cents — matches how rates are displayed
     * and hand-edited in the UI). Auto-starts a draft from current
     * if none exists so a percentage bump from a clean state is one
     * click, not two.
     *
     * Runs the update inside a single UPDATE so a partial application
     * can't leak — either every tier gets the multiplier or none do.
     * Returns the count of tiers touched so the controller can flash
     * an honest "bumped N tiers" number.
     */
    public function bumpDraftByPercent(string $tripType, float $percent): int
    {
        $this->assertTripType($tripType);

        // Reasonable guard rails: a 500% raise or a -99% cut is almost
        // certainly a typo. Refuse rather than silently apply.
        if ($percent < -99.0 || $percent > 500.0) {
            throw new InvalidArgumentException(
                sprintf('percent out of range: %.4f (allowed −99 to 500)', $percent)
            );
        }

        if (! $this->hasDraft($tripType)) {
            $this->startOrResetDraft($tripType);
        }

        $multiplier = 1.0 + ($percent / 100.0);
        // ROUND(x, 2) at the SQL layer keeps the write atomic and
        // avoids pulling every row into PHP just to multiply and
        // send it back. The schema stores DECIMAL(x, 4) so 2-place
        // rounding leaves headroom if a future tweak wants finer
        // precision.
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                   SET rate = ROUND(rate * ?, 2)
                 WHERE terminal = ? AND trip_type = ? AND stage = ?';
        $stmt = $this->prepared($sql, [$multiplier, self::TERMINAL, $tripType, 'draft']);
        return $stmt->rowCount();
    }

    /**
     * Promote draft → current. Atomically replaces all current rows
     * with draft rows, then clears the draft so the editor UI shows
     * a clean "no pending changes" state.
     */
    public function promoteDraftToCurrent(string $tripType): void
    {
        $this->assertTripType($tripType);

        if (! $this->hasDraft($tripType)) {
            throw new InvalidArgumentException(
                "No draft exists for {$tripType} — nothing to promote.",
            );
        }

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare(
                'DELETE FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $del->execute([self::TERMINAL, $tripType, 'current']);

            $ins = $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (terminal, trip_type, stage, miles, rate)
                 SELECT terminal, trip_type, ?, miles, rate
                 FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $ins->execute(['current', self::TERMINAL, $tripType, 'draft']);

            // Clear the draft after a successful promote — matches the
            // legacy "draft auto-clears after going live" intent.
            $del->execute([self::TERMINAL, $tripType, 'draft']);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reset current → default. Also clears any in-flight draft so the
     * UI doesn't show pending edits against now-stale current rates.
     */
    public function resetCurrentToDefault(string $tripType): void
    {
        $this->assertTripType($tripType);

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare(
                'DELETE FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $del->execute([self::TERMINAL, $tripType, 'current']);
            $del->execute([self::TERMINAL, $tripType, 'draft']);

            $ins = $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (terminal, trip_type, stage, miles, rate)
                 SELECT terminal, trip_type, ?, miles, rate
                 FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $ins->execute(['current', self::TERMINAL, $tripType, 'default']);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Insert OR update a draft tier (miles, rate). Auto-starts a draft
     * from current if none exists yet.
     */
    public function upsertDraftTier(string $tripType, int $miles, string $rate): void
    {
        $this->assertTripType($tripType);

        if ($miles <= 0 || $miles > 65535) {
            throw new InvalidArgumentException("miles out of range: {$miles}");
        }
        if (! preg_match('/^\d+(\.\d{1,4})?$/', $rate)) {
            throw new InvalidArgumentException("rate not in dollars(.cents) form: {$rate}");
        }

        if (! $this->hasDraft($tripType)) {
            $this->startOrResetDraft($tripType);
        }

        $sql = 'INSERT INTO ' . self::ident(self::$table) . ' (terminal, trip_type, stage, miles, rate)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rate = VALUES(rate)';
        $this->prepared($sql, [self::TERMINAL, $tripType, 'draft', $miles, $rate]);
    }

    /**
     * Delete a draft tier (miles). Auto-starts a draft from current if
     * none exists yet.
     */
    public function deleteDraftTier(string $tripType, int $miles): void
    {
        $this->assertTripType($tripType);

        if (! $this->hasDraft($tripType)) {
            $this->startOrResetDraft($tripType);
        }

        $sql = 'DELETE FROM ' . self::ident(self::$table) . '
                WHERE terminal = ? AND trip_type = ? AND stage = ? AND miles = ?';
        $this->prepared($sql, [self::TERMINAL, $tripType, 'draft', $miles]);
    }
}
