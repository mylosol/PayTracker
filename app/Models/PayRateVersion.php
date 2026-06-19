<?php

declare(strict_types=1);

namespace PayTracker\Models;

use InvalidArgumentException;
use PayTracker\Database\Connection;
use PayTracker\Database\Model;

/**
 * `pay_rate_versions` — immutable history of pay-rate tier sets.
 *
 * Companion to `pay_rates` (the editor surface). Every time an admin
 * promotes a draft, the active versions for that trip_type get their
 * `effective_until` stamped with the admin-chosen effective date, and
 * new active versions get inserted with `effective_from` = same date,
 * `effective_until` = NULL.
 *
 * Schema (migration 2026_06_19_002):
 *   id              INT AUTO_INCREMENT
 *   trip_type       'round_trip' | 'long_haul'
 *   miles           SMALLINT — tier ceiling
 *   rate            DECIMAL(10,4)
 *   effective_from  DATE — inclusive lower bound
 *   effective_until DATE — exclusive upper bound, NULL while active
 *   created_at      TIMESTAMP
 *
 * Lookup semantics:
 *   "Lowest tier whose miles ≥ load_miles, among versions whose
 *    effective_from ≤ load_date AND (effective_until IS NULL OR
 *    load_date < effective_until)."
 *
 * The "tier ≥ load_miles" rule matches the pre-versioning RateLookup
 * (legacy SELECT ... WHERE miles >= $loadMiles LIMIT 1). The date
 * filter is the new piece — it's what makes a Recompute after a
 * raise stop revaluing pre-raise loads.
 */
class PayRateVersion extends Model
{
    protected static string $table = 'pay_rate_versions';

    /** @var list<string> mirror of PayRate::TRIP_TYPES so callers don't reach across models */
    public const TRIP_TYPES = ['round_trip', 'long_haul'];

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

    /**
     * Resolve the rate that applied on `$loadDate` for `$tripType` at
     * `$loadMiles`. Returns null when no version covers that date or
     * no tier reaches that mileage — the calculator treats null the
     * same way the pre-versioning lookup did (zero base pay).
     *
     * @param string $loadDate YYYY-MM-DD
     */
    public function lookup(string $tripType, int $loadMiles, string $loadDate): ?float
    {
        $this->assertTripType($tripType);

        $sql = 'SELECT rate
                FROM ' . self::ident(self::$table) . '
                WHERE trip_type = ?
                  AND miles >= ?
                  AND effective_from <= ?
                  AND (effective_until IS NULL OR effective_until > ?)
                ORDER BY miles ASC
                LIMIT 1';
        $stmt = $this->prepared($sql, [$tripType, $loadMiles, $loadDate, $loadDate]);
        $row  = $stmt->fetch();
        if (! is_array($row) || ! isset($row['rate'])) {
            return null;
        }
        return (float) $row['rate'];
    }

    /**
     * Promote-time hook: close out the currently-active versions for
     * a trip_type (UPDATE … SET effective_until = $date WHERE
     * effective_until IS NULL) and insert the supplied tier rows as
     * the new active versions. Runs inside a single transaction so a
     * partial failure can't leave a half-promoted history.
     *
     * @param string $tripType
     * @param string $effectiveDate YYYY-MM-DD — also used as the new
     *                              versions' effective_from and the
     *                              old versions' effective_until.
     * @param list<array{miles:int, rate:string}> $tiers New tier rows
     *                              (copied from the draft at the
     *                              promote callsite).
     */
    public function createVersionFromTiers(
        string $tripType,
        string $effectiveDate,
        array $tiers,
    ): void {
        $this->assertTripType($tripType);
        if ($tiers === []) {
            throw new InvalidArgumentException(
                "No tiers supplied for {$tripType} version — nothing to promote.",
            );
        }

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $close = $pdo->prepare(
                'UPDATE ' . self::ident(self::$table) . '
                 SET effective_until = ?
                 WHERE trip_type = ? AND effective_until IS NULL'
            );
            $close->execute([$effectiveDate, $tripType]);

            $ins = $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . '
                    (trip_type, miles, rate, effective_from, effective_until)
                 VALUES (?, ?, ?, ?, NULL)'
            );
            foreach ($tiers as $tier) {
                $ins->execute([
                    $tripType,
                    (int) $tier['miles'],
                    (string) $tier['rate'],
                    $effectiveDate,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Bulk fetch the active tier set on `$loadDate` for `$tripType` —
     * cheaper than walking lookup() once per tier when the calculator
     * prewarms its cache.
     *
     * @return list<array{miles:int, rate:string}>
     */
    public function activeTiersOn(string $tripType, string $loadDate): array
    {
        $this->assertTripType($tripType);

        $sql = 'SELECT miles, rate
                FROM ' . self::ident(self::$table) . '
                WHERE trip_type = ?
                  AND effective_from <= ?
                  AND (effective_until IS NULL OR effective_until > ?)
                ORDER BY miles ASC';
        $stmt = $this->prepared($sql, [$tripType, $loadDate, $loadDate]);
        $out  = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'miles' => (int)    $row['miles'],
                'rate'  => (string) $row['rate'],
            ];
        }
        return $out;
    }

    /**
     * Read the full version history for one trip_type, newest first.
     * Used by the admin pay-rate history view.
     *
     * @return list<array{
     *     id:int, miles:int, rate:string,
     *     effective_from:string, effective_until:?string
     * }>
     */
    public function historyFor(string $tripType): array
    {
        $this->assertTripType($tripType);

        $sql = 'SELECT id, miles, rate, effective_from, effective_until
                FROM ' . self::ident(self::$table) . '
                WHERE trip_type = ?
                ORDER BY effective_from DESC, miles ASC';
        $stmt = $this->prepared($sql, [$tripType]);
        $out  = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'id'              => (int)    $row['id'],
                'miles'           => (int)    $row['miles'],
                'rate'            => (string) $row['rate'],
                'effective_from'  => (string) $row['effective_from'],
                'effective_until' => isset($row['effective_until']) && $row['effective_until'] !== null
                    ? (string) $row['effective_until']
                    : null,
            ];
        }
        return $out;
    }
}
