<?php

declare(strict_types=1);

namespace PayTracker\Services;

use PayTracker\Models\PayRate;
use PayTracker\Models\PayVariable;
use PayTracker\Services\Pay\LoadInputs;
use PayTracker\Services\Pay\RateLookup;
use PayTracker\Services\Pay\VariableBag;

/**
 * PayCalculator — modern port of the legacy index.php np/op formula
 * (lines 568–684).
 *
 * Faithful reproduction of the legacy math, including the specific
 * round-each-component-then-sum behaviour that produces values consistent
 * with what's stored in driver_loads.np / .op.
 *
 * INPUTS  (\PayTracker\Services\Pay\LoadInputs DTO)
 *   load_type:          0 = one-way, 1 = round-trip, 4 = trainer
 *   load_miles:         resolved pickup→delivery distance
 *   empty_miles:        return-leg empty miles
 *   begin_empty_miles:  deadhead miles before pickup
 *   is_split:           1 → flat +$15 to both np and op
 *   is_weekend:         1 → enables wk multiplier on np
 *   extra_pay:          flat $ added to both np and op
 *   dem_minutes:        × demurrage per-minute → adds to both np and op
 *   break_minutes:      × breakdown per-minute → adds to both np and op
 *   variables_blob:     "tenure-shift-slip-?" from driver_loads.variables
 *   terminal_pcola:     unused by formula (legacy dead branch), kept
 *                       in the DTO for future use.
 *
 * OUTPUTS
 *   np: float — total pay (base + seniority + shift + weekend + extras)
 *   op: float — side-extras only (split + extra_pay + dem + break)
 *
 * The op formula was reverse-engineered from sampler fixtures:
 *   frtl=9556333  split=1 extra=5 break=90  → op = 15 + 5 + 90×0.391667 ≈ 55.25 ✓
 *   frtl=9550429  split=1 dem=75            → op = 15 + 75×0.391667    ≈ 44.38 ✓
 *
 * The np formula:
 *   round-trip (load_type=1):
 *     base = lookupTier(round_trip, load_miles) × (1 + raise)
 *     np   = round(base,2) + round(base×newBump,2) + round(base×night,2)
 *          + round(base×wk,2 if weekend else 0)
 *          + extras
 *
 *   one-way (load_type=0):
 *     oneWay = lookupTier(long_haul, load_miles) × (1 + raise)
 *     empty  = (empty_miles + begin_empty_miles) × mt
 *     if oneWay > 0:
 *       np = round(oneWay,2) + round(empty,2)
 *          + round((oneWay+empty)×newBump,2)
 *          + round((oneWay+empty)×night,2)
 *          + round((oneWay+empty)×wk,2 if weekend else 0)
 *          + extras
 *     elif empty > 0:
 *       np = round(empty,2) + extras  (no overlay)
 *
 *   trainer (load_type=4):
 *     np = op = round(trainer_pay + extras, 2)
 */
final class PayCalculator
{
    private RateLookup $rates;
    private VariableBag $vars;

    public function __construct(
        private readonly PayRate $rateModel,
        private readonly PayVariable $varModel,
    ) {
        $this->rates = new RateLookup($rateModel);
        $this->vars  = new VariableBag($varModel->allByStage('current'));
    }

    /**
     * Re-load variables from a specific stage. Useful for unit tests
     * that want to evaluate against a fixed snapshot.
     */
    public function useVariablesFrom(string $stage): void
    {
        $this->vars = new VariableBag($this->varModel->allByStage($stage));
    }

    /**
     * Drop-in for unit tests: swap in a hand-built variable map without
     * hitting the DB.
     *
     * @param array<string,string|float|int> $vars
     */
    public function useVariableMap(array $vars): void
    {
        $stringified = [];
        foreach ($vars as $k => $v) {
            $stringified[(string) $k] = (string) $v;
        }
        $this->vars = new VariableBag($stringified);
    }

    /**
     * Inject a hand-built tier set for unit tests.
     *
     * @param list<array{miles:int, rate:float}> $tiers
     */
    public function setRateTiersForTest(string $terminal, string $tripType, array $tiers): void
    {
        $this->rates->setTiersForTest($terminal, $tripType, $tiers);
    }

    /**
     * Compute np/op for one load. Returns floats rounded to 2 decimals.
     *
     * @return array{np: float, op: float}
     */
    public function computeFor(LoadInputs $load): array
    {
        $parts  = explode('-', $load->variables_blob);
        $tenure = (string) ($parts[0] ?? '168');
        $shift  = (string) ($parts[1] ?? 'day');

        $bandVars = $this->bandVariables($tenure);
        $raise    = (float) $this->vars->get('raise', '0');
        $wkMult   = (float) $bandVars['wk'];
        $newBump  = (float) $bandVars['newBump'];
        $nightOn  = $shift === 'night' ? (float) $bandVars['night'] : 0.0;
        $mt       = (float) $bandVars['mt'];
        $weekendOn = $load->is_weekend > 0 ? $wkMult : 0.0;

        $demRate = (float) $this->vars->get('demurrage', '0');
        $brkRate = (float) $this->vars->get('breakdown', '0');

        if ($load->load_type === 4) {
            $trainer = (float) $this->vars->get('trainer_pay', '0');
            $extras  = $this->extras($load, $demRate, $brkRate);
            return [
                'np' => round($trainer + $extras, 2),
                'op' => round($trainer + $extras, 2),
            ];
        }

        $np = 0.0;

        // Out-of-route rewrite (legacy include/outofroute.php):
        //   if out_of_route_ind > 0 AND out_of_route_miles > load_miles + 3:
        //     load_miles := out_of_route_miles
        // Otherwise unchanged. This lets a Panama→Panama "0-mile" loop with
        // a 10-mile detour bill at the 10-mile tier.
        $effectiveMiles = $load->load_miles;
        if ($load->out_of_route_ind > 0
            && $load->out_of_route_miles > ($load->load_miles + 3)
        ) {
            $effectiveMiles = $load->out_of_route_miles;
        }

        if ($load->load_type === 1) {
            $base = $this->rates->lookup('pensacola', 'round_trip', $effectiveMiles);
            if ($base !== null) {
                $base    = $base * (1 + $raise);
                $rtBase  = round($base, 2);
                $rtSen   = round($base * $newBump, 2);
                $rtNight = round($base * $nightOn, 2);
                $rtWk    = round($base * $weekendOn, 2);
                $np      = $rtBase + $rtSen + $rtNight + $rtWk;
            }
        } elseif ($load->load_type === 0) {
            $oneWay = 0.0;
            // Effective miles (out-of-route rewrite) only matters when the
            // loaded leg is > 0; an "empty-only" load skips the rate-table
            // lookup entirely.
            if ($effectiveMiles > 0) {
                $base = $this->rates->lookup('pensacola', 'long_haul', $effectiveMiles);
                if ($base !== null) {
                    $oneWay = $base * (1 + $raise);
                }
            }
            $emptyMilesTotal = max(0, $load->empty_miles + $load->begin_empty_miles);
            $empty = $emptyMilesTotal * $mt;

            if ($oneWay > 0.0 || $empty > 0.0) {
                if ($oneWay === 0.0) {
                    $np = round($empty, 2);
                } else {
                    $combined = $oneWay + $empty;
                    $sen      = round($combined * $newBump, 2);
                    $night    = round($combined * $nightOn, 2);
                    $weekend  = round($combined * $weekendOn, 2);
                    $np       = round($oneWay, 2) + round($empty, 2) + $sen + $night + $weekend;
                }
            }
        }

        $extras = $this->extras($load, $demRate, $brkRate);
        $np    += $extras;

        return [
            'np' => round($np, 2),
            'op' => round($extras, 2),
        ];
    }

    /**
     * Compute the "extras" sum: split flat $15 + extra_pay + dem×CPM
     * + break×CPM. Used by both np and op.
     */
    private function extras(LoadInputs $load, float $demRate, float $brkRate): float
    {
        $split = $load->is_split > 0 ? 15.0 : 0.0;
        $extra = $load->extra_pay;
        $dem   = $load->dem_minutes   * $demRate;
        $brk   = $load->break_minutes * $brkRate;
        return $split + $extra + $dem + $brk;
    }

    /**
     * Map driver tenure (months) onto a tenure band and return the
     * per-band variables.
     *
     * Bands match the legacy variables.php branches exactly:
     *   tenure ==  6           → '6_*'
     *   tenure == 12           → '12_*'
     *   tenure ∈ {13, 24}      → '24_*'
     *   tenure ∈ (24..60]      → '60_*'
     *   tenure ∈ (60..108]     → '108_*'
     *   tenure ∈ (108..168]    → '168_*'
     *   tenure == 'max'        → 'max_*'
     *
     * Anything else falls back to '168_*' — the senior band. We prefer
     * '168' over the legacy "stay at zero" because the sampler showed
     * 1353 rows on '168' and that's the practical default.
     *
     * @return array{mt:string, wk:string, newBump:string, night:string, tb:string}
     */
    private function bandVariables(string $tenure): array
    {
        $band = match (true) {
            $tenure === '6'                                                     => '6',
            $tenure === '12'                                                    => '12',
            $tenure === '13' || $tenure === '24'                                => '24',
            ctype_digit($tenure) && (int) $tenure > 24  && (int) $tenure <= 60  => '60',
            ctype_digit($tenure) && (int) $tenure > 60  && (int) $tenure <= 108 => '108',
            ctype_digit($tenure) && (int) $tenure > 108 && (int) $tenure <= 168 => '168',
            $tenure === 'max'                                                   => 'max',
            default                                                             => '168',
        };
        return [
            'mt'      => $this->vars->get("{$band}_mt",      '0'),
            'wk'      => $this->vars->get("{$band}_wk",      '0'),
            'newBump' => $this->vars->get("{$band}_newBump", '0'),
            'night'   => $this->vars->get("{$band}_night",   '0'),
            'tb'      => $this->vars->get("{$band}_tb",      '0'),
        ];
    }
}
