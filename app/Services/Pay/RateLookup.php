<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

use PayTracker\Models\PayRate;

/**
 * Rate-table tier lookup. Wraps PayRate so the calculator can be unit-
 * tested with a stub that doesn't need a live DB.
 *
 * Resolution: "first tier whose miles ≥ load_miles". Matches the legacy
 * "SELECT rate FROM ... WHERE miles >= $loadMiles LIMIT 1" semantics.
 *
 * The terminal dimension is gone — see PayRate's docblock. We cache
 * per trip_type only.
 */
final class RateLookup
{
    /** @var array<string, list<array{miles:int, rate:float}>> cache keyed by trip_type */
    private array $tierCache = [];

    public function __construct(private readonly PayRate $rates)
    {
    }

    public function lookup(string $tripType, int $loadMiles): ?float
    {
        if (! isset($this->tierCache[$tripType])) {
            $tiers = $this->rates->tiers($tripType, 'current');
            $this->tierCache[$tripType] = array_map(
                static fn (array $t): array => ['miles' => $t['miles'], 'rate' => (float) $t['rate']],
                $tiers,
            );
        }
        foreach ($this->tierCache[$tripType] as $tier) {
            if ($tier['miles'] >= $loadMiles) {
                return $tier['rate'];
            }
        }
        return null;
    }

    /**
     * Inject a hand-built tier set for unit tests. Bypasses the PayRate
     * model so tests don't need a live DB.
     *
     * @param list<array{miles:int, rate:float}> $tiers
     */
    public function setTiersForTest(string $tripType, array $tiers): void
    {
        $this->tierCache[$tripType] = $tiers;
    }
}
