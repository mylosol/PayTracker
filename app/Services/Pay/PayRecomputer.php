<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

use PayTracker\Models\CityDistance;
use PayTracker\Models\DriverLoad;
use PayTracker\Services\PayCalculator;

/**
 * PayRecomputer — pure-logic core of the np/op recompute flow.
 *
 * Extracted from PayAdminController so the dashboard's per-driver
 * "Refresh my pay" button can share the same code path without
 * duplicating the closure-building and mile-cache glue.
 *
 * Both the admin (all-drivers) and self-serve (single-driver)
 * surfaces call run() with the appropriate driverFilter scope.
 */
final class PayRecomputer
{
    public function __construct(
        private readonly PayCalculator $calculator,
        private readonly DriverLoad $loads,
        private readonly CityDistance $distances,
    ) {
    }

    /**
     * Walk driver_loads in the given window, compute np/op via
     * PayCalculator using current rates+variables, and UPDATE-rows
     * whose values differ.
     *
     * @param ?int    $driverFilter Restrict to a single driver. Null = all.
     * @param ?string $since        SQL datetime lower bound. Null/empty
     *                              defaults to "30 days ago" to bound the
     *                              workload.
     *
     * @return array{considered:int, updated:int, unchanged:int, skipped:int}
     */
    public function run(?int $driverFilter = null, ?string $since = null): array
    {
        // Resolve a sensible "since" — accepts either a date-only
        // (YYYY-MM-DD) or full datetime; falls back to 30 days ago.
        if ($since === null || $since === '') {
            $since = date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $since) === 1) {
            $since .= ' 00:00:00';
        }

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

        $compute = function (array $row) use ($resolve): array {
            $miles = $resolve((string) $row['pickup_city'], (string) $row['delivery_city']);
            $load  = new LoadInputs(
                load_type:          (int) $row['load_type'],
                load_miles:         $miles,
                empty_miles:        (int) $row['empty_miles'],
                begin_empty_miles:  (int) $row['begin_empty_miles'],
                is_split:           (int) $row['is_split'],
                is_weekend:         (int) $row['is_weekend'],
                extra_pay:          (float) $row['extra_pay'],
                dem_minutes:        (int) $row['dem_minutes'],
                break_minutes:      (int) $row['break_minutes'],
                variables_blob:     (string) ($row['variables'] ?? '168-night--0'),
                out_of_route_ind:   (int) ($row['out_of_route_ind']   ?? 0),
                out_of_route_miles: (int) ($row['out_of_route_miles'] ?? 0),
                terminal_pcola:     (int) ($row['terminal_pcola']     ?? 0),
            );
            return $this->calculator->computeFor($load);
        };

        return $this->loads->recomputePay($compute, $driverFilter, $since);
    }
}
