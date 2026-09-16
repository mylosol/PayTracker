<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

use PayTracker\Models\PayRateVersion;

/**
 * Rate-table tier lookup. Wraps PayRateVersion so the calculator can
 * be unit-tested with a stub that doesn't need a live DB.
 *
 * Resolution: "first tier whose miles ≥ load_miles, among the version
 * active on the load's date". Date-awareness is the whole reason this
 * shim went through a rewrite — see PayRateVersion's docblock for the
 * "Aug 1 raise shouldn't retroactively bump July loads" rationale.
 *
 * Cache key is (trip_type, load_date). Two loads on the same day share
 * a query; a recompute window spanning a rate change pays one extra
 * query at the boundary.
 */
final class RateLookup
{
    /**
     * Cache of tier sets keyed by "<trip_type>|<load_date>". Each
     * value is the tier list active on that date, sorted by miles ASC
     * so the linear scan below finds the right ceiling.
     *
     * @var array<string, list<array{miles:int, rate:float}>>
     */
    private array $tierCache = [];

    /**
     * Unit-test stub. When set, completely bypasses the version model
     * — every lookup returns from this map regardless of load_date.
     * Tests that need date-discriminating behaviour should seed the
     * live versions table instead.
     *
     * @var array<string, list<array{miles:int, rate:float}>>
     */
    private array $testTiers = [];

    public function __construct(private readonly PayRateVersion $versions)
    {
    }

    public function lookup(string $tripType, int $loadMiles, string $loadDate): ?float
    {
        $tiers = $this->testTiers[$tripType] ?? null;
        if ($tiers === null) {
            $cacheKey = $tripType . '|' . $loadDate;
            if (! isset($this->tierCache[$cacheKey])) {
                $rows = $this->versions->activeTiersOn($tripType, $loadDate);
                $this->tierCache[$cacheKey] = array_map(
                    static fn (array $r): array => ['miles' => $r['miles'], 'rate' => (float) $r['rate']],
                    $rows,
                );
            }
            $tiers = $this->tierCache[$cacheKey];
        }
        foreach ($tiers as $tier) {
            if ($tier['miles'] >= $loadMiles) {
                return $tier['rate'];
            }
        }
        return null;
    }

    /**
     * @param list<array{miles:int, rate:float}> $tiers
     */
    public function setTiersForTest(string $tripType, array $tiers): void
    {
        $this->testTiers[$tripType] = $tiers;
    }
}
