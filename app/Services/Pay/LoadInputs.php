<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

/**
 * Plain DTO holding everything PayCalculator needs to compute np/op for
 * one load. Constructed from a driver_loads row or a unit-test fixture.
 */
final class LoadInputs
{
    public function __construct(
        public readonly int    $load_type,
        public readonly int    $load_miles,
        public readonly int    $empty_miles,
        public readonly int    $begin_empty_miles,
        public readonly int    $is_split,
        public readonly int    $is_weekend,
        public readonly float  $extra_pay,
        public readonly int    $dem_minutes,
        public readonly int    $break_minutes,
        public readonly string $variables_blob,
        public readonly int    $out_of_route_ind   = 0,
        public readonly int    $out_of_route_miles = 0,
        public readonly int    $terminal_pcola     = 0,
    ) {
    }
}
