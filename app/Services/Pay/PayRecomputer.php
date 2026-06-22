<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

use PayTracker\Models\CityDistance;
use PayTracker\Models\DriverLoad;
use PayTracker\Services\PayCalculator;

/**
 * PayRecomputer — pure-logic core of the np/op recompute flow.
 *
 * Two intents share this code path:
 *
 *   Admin recompute (PayAdminController):
 *     Replays np/op against current rates, preserving each load's
 *     historical tenure/shift snapshot. Used after a rate-table
 *     change so every driver's old loads get refreshed without
 *     time-travelling their tenure.
 *
 *   Driver self-serve refresh (DashboardController):
 *     Same as above, PLUS overrides each load's variables blob with
 *     one freshly derived from the driver's current account profile.
 *     This is the "I just set my hire date, fix my pay" case: the
 *     blob was wrong at insert time and the driver wants it corrected.
 *
 * The override is opt-in via the $applyProfile parameter. When omitted,
 * the historical snapshot wins (admin behaviour). When supplied, every
 * row's blob is regenerated and the variables column is rewritten
 * alongside np/op/pay_breakdown.
 */
final class PayRecomputer
{
    public function __construct(
        private readonly PayCalculator $calculator,
        private readonly DriverLoad $loads,
        private readonly CityDistance $distances,
        private readonly VariableBlobBuilder $blobBuilder,
    ) {
    }

    /**
     * Walk driver_loads in the given window, compute np/op via
     * PayCalculator, and UPDATE rows whose values differ.
     *
     * @param ?int                $driverFilter  Restrict to a single driver. Null = all.
     * @param ?string             $since         SQL datetime lower bound. Null/empty
     *                                           defaults to "30 days ago" to bound the
     *                                           workload.
     * @param ?array<string,mixed> $applyProfile Driver account row. When non-null, each
     *                                           row's variables blob is rebuilt from
     *                                           this profile (tenure + shift) instead
     *                                           of using the row's stored snapshot.
     *
     * @return array{considered:int, updated:int, unchanged:int, skipped:int}
     */
    public function run(
        ?int $driverFilter = null,
        ?string $since = null,
        ?array $applyProfile = null,
    ): array {
        // Resolve a sensible "since" — accepts either a date-only
        // (YYYY-MM-DD) or full datetime; falls back to 30 days ago.
        if ($since === null || $since === '') {
            $since = date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $since) === 1) {
            $since .= ' 00:00:00';
        }

        // Build the profile-override blob once if applicable — same blob
        // for every row, since the driver's current profile is stable
        // across the recompute window.
        $profileBlob = $applyProfile !== null
            ? $this->blobBuilder->build($applyProfile)
            : null;

        // Mile lookups are by (pickup, delivery) pair. Cache them across
        // rows so a busy driver doesn't hit city_distances 50 times for
        // the same pair.
        $milesCache = [];
        $resolve = function (string $from, string $to) use (&$milesCache): int {
            $key = "{$from}|{$to}";
            if (! array_key_exists($key, $milesCache)) {
                $rows = $this->distances->between($from, $to);
                $milesCache[$key] = $rows !== [] ? (int) $rows[0]['miles'] : 0;
            }
            return $milesCache[$key];
        };

        $compute = function (array $row) use ($resolve, $profileBlob): array {
            $miles    = $resolve((string) $row['pickup_city'], (string) $row['delivery_city']);
            $useBlob  = $profileBlob ?? (string) ($row['variables'] ?? '6-day--0');
            // The legacy `empty_miles` column actually stores the LOADED
            // leg distance; the real empty leg (delivery → end_empty)
            // is in `end_empty_miles`. We feed the calculator the
            // correctly-named values.
            $load     = new LoadInputs(
                load_type:          (int) $row['load_type'],
                load_miles:         $miles,
                empty_miles:        (int) ($row['end_empty_miles'] ?? 0),
                begin_empty_miles:  (int) $row['begin_empty_miles'],
                is_split:           (int) $row['is_split'],
                is_weekend:         (int) $row['is_weekend'],
                extra_pay:          (float) $row['extra_pay'],
                dem_minutes:        (int) $row['dem_minutes'],
                break_minutes:      (int) $row['break_minutes'],
                variables_blob:     $useBlob,
                out_of_route_ind:   (int) ($row['out_of_route_ind']   ?? 0),
                out_of_route_miles: (int) ($row['out_of_route_miles'] ?? 0),
                // Critical for rate-version resolution. Without this,
                // a recompute would walk every load through the
                // CURRENTLY-active version regardless of when the
                // load happened — exactly the retroactive-raise bug
                // we just refactored around.
                load_date:          isset($row['date']) ? substr((string) $row['date'], 0, 10) : '',
            );
            $result = $this->calculator->computeFor($load);
            // Attach the blob that was actually used so recomputePay can
            // persist it (only matters when the profile-override path
            // changes the blob; admin path passes through the stored
            // value so the UPDATE is a no-op write).
            $result['_variables'] = $useBlob;
            return $result;
        };

        return $this->loads->recomputePay($compute, $driverFilter, $since);
    }
}
