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
 */
final class RateLookup
{
    /** @var array<string, list<array{miles:int, rate:float}>> cache keyed by "terminal|trip_type" */
    private array $tierCache = [];

    public function __construct(private readonly PayRate $rates)
    {
    }

    public function lookup(string $terminal, string $tripType, int $loadMiles): ?float
    {
        $key = "{$terminal}|{$tripType}";
        if (! isset($this->tierCache[$key])) {
            $tiers = $this->rates->tiers($terminal, $tripType, 'current');
            $this->tierCache[$key] = array_map(
                static fn (array $t): array => ['miles' => $t['miles'], 'rate' => (float) $t['rate']],
                $tiers,
            );
        }
        foreach ($this->tierCache[$key] as $tier) {
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
    public function setTiersForTest(string $terminal, string $tripType, array $tiers): void
    {
        $this->tierCache["{$terminal}|{$tripType}"] = $tiers;
    }
}
