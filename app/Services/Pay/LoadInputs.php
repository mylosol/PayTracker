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
        /**
         * YYYY-MM-DD — drives rate-version resolution. The PayCalculator
         * looks this up against pay_rate_versions so loads pre-dating a
         * rate change still bill at the rate that was active when the
         * work was performed. Default is today, matching the pre-
         * versioning behaviour for live load entry.
         */
        public readonly string $load_date = '',
    ) {
    }
}
