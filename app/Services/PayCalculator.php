<?php

declare(strict_types=1);

namespace PayTracker\Services;

use PayTracker\Models\PayRate;
use PayTracker\Models\PayVariable;
use PayTracker\Services\Pay\LoadInputs;
use PayTracker\Services\Pay\RateLookup;
use PayTracker\Services\Pay\VariableBag;

/**
 * PayCalculator — computes net pay (np) and op for one load.
 *
 * Inputs come through LoadInputs (a DTO built from the form or from
 * a stored driver_loads row); rates and per-band variables come from
 * the pay_rates / pay_variables tables. Result is a structured array
 * with the np/op totals plus every component the dashboard breakdown
 * card renders (base mi/$, shift %/$, seniority %/$, weekend, split,
 * dem, break, extra).
 *
 * Formula:
 *
 *   round-trip (load_type=1):
 *     base      = lookupTier(round_trip, load_miles) × (1 + raise)
 *     base_pay  = round(base, 2)
 *     seniority = round(base × newBump, 2)
 *     shift     = round(base × night, 2)  // night-shift only
 *     weekend   = round(base × wk, 2)     // when is_weekend
 *     np        = base_pay + seniority + shift + weekend + extras
 *
 *   one-way (load_type=0):
 *     oneWay   = lookupTier(long_haul, load_miles) × (1 + raise)
 *     empty    = (empty_miles + begin_empty_miles) × mt
 *     base_pay = round(oneWay, 2)
 *     empty_pay = round(empty, 2)
 *     combined = oneWay + empty
 *     seniority = round(combined × newBump, 2)
 *     shift     = round(combined × night, 2)
 *     weekend   = round(combined × wk, 2)
 *     if oneWay == 0 && empty > 0: np = empty_pay + extras (no overlay)
 *     else: np = base_pay + empty_pay + seniority + shift + weekend + extras
 *
 *   trainer (load_type=4):
 *     np = op = round(trainer_pay + extras, 2)
 *
 * Extras (the op total, also added to np):
 *   split (flat $15 if is_split) + extra_pay + dem×CPM + break×CPM
 *
 * Rounding doctrine: every component is rounded to 2 decimals, and
 * the totals are the sums of those rounded components. The breakdown
 * shown to the driver sums exactly to the np they see — no off-by-
 * a-cent drift between the row-by-row card and the total.
 *
 * Out-of-route rewrite: when out_of_route_ind > 0 and out_of_route_miles
 * exceeds load_miles + 3, the rate-table lookup uses the longer mileage
 * (a Panama→Panama 0-mile loop with a 10-mile detour bills at the
 * 10-mile tier).
 */
final class PayCalculator
{
    private RateLookup $rates;
    private VariableBag $vars;

    public function __construct(
        PayRate $rateModel,
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
     * Compute np/op + a full breakdown for one load. The returned array
     * is intentionally rich: alongside the legacy `np`/`op` totals, we
     * expose every intermediate the legacy load card surfaces (base mi,
     * base $, seniority pct + $, shift pct + $, weekend, split, dem,
     * break, extra), so the dashboard can render the per-row breakdown
     * without re-deriving anything.
     *
     * Existing callers reading $result['np'] / $result['op'] keep
     * working — the extra fields are additive.
     *
     * @return array{
     *   np: float, op: float,
     *   trip_label: string,
     *   tenure_band: string, shift: string,
     *   base_miles: int, base_rate: float,
     *   base_pay: float, empty_pay: float,
     *   seniority_pct: float, seniority_pay: float,
     *   shift_pct: float,    shift_pay: float,
     *   weekend_pct: float,  weekend_pay: float,
     *   split_pay: float, extra_pay: float, dem_pay: float, break_pay: float,
     * }
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

        // Single view of extras: every component rounded to 2 decimals,
        // then summed. The breakdown shown to the driver sums exactly
        // to op (and to the extras portion of np) — no off-by-a-cent
        // drift between the row-by-row card and the totals.
        $extrasBreakdown = $this->extrasBreakdown($load, $demRate, $brkRate);
        $extras          = $extrasBreakdown['split_pay']
                         + $extrasBreakdown['extra_pay']
                         + $extrasBreakdown['dem_pay']
                         + $extrasBreakdown['break_pay'];

        // Trainer path: np = op = trainer_pay + extras. No base/seniority/
        // shift/weekend overlay applies. We zero those fields so the
        // breakdown view renders only the trainer base + any extras.
        if ($load->load_type === 4) {
            $trainer = (float) $this->vars->get('trainer_pay', '0');
            $total   = round($trainer + $extras, 2);
            return $this->buildResult(
                np: $total, op: $total,
                tripLabel: 'Trainer',
                tenureBand: $tenure, shift: $shift,
                baseMiles: 0, baseRate: 0.0,
                basePay: round($trainer, 2), emptyPay: 0.0,
                seniorityPct: 0.0, seniorityPay: 0.0,
                shiftPct: 0.0, shiftPay: 0.0,
                weekendPct: 0.0, weekendPay: 0.0,
                extras: $extrasBreakdown,
            );
        }

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

        $basePay     = 0.0;
        $emptyPay    = 0.0;
        $seniority   = 0.0;
        $shiftPay    = 0.0;
        $weekendPay  = 0.0;
        $baseRate    = 0.0;

        if ($load->load_type === 1) {
            // Round-trip: base = rate-table lookup × (1 + raise). All
            // overlays scale off base alone.
            $base = $this->rates->lookup('pensacola', 'round_trip', $effectiveMiles);
            if ($base !== null) {
                $base       = $base * (1 + $raise);
                $basePay    = round($base, 2);
                $seniority  = round($base * $newBump, 2);
                $shiftPay   = round($base * $nightOn, 2);
                $weekendPay = round($base * $weekendOn, 2);
                $baseRate   = $effectiveMiles > 0 ? $base / $effectiveMiles : 0.0;
            }
        } elseif ($load->load_type === 0) {
            // One-way: split into a loaded leg (rate-table) and an empty
            // leg (mt × miles). Overlays scale off the COMBINED total so
            // a long deadhead lifts seniority/shift/weekend pay too.
            $oneWay = 0.0;
            if ($effectiveMiles > 0) {
                $base = $this->rates->lookup('pensacola', 'long_haul', $effectiveMiles);
                if ($base !== null) {
                    $oneWay   = $base * (1 + $raise);
                    // $effectiveMiles is already > 0 from the outer guard;
                    // the per-mile rate is safe to compute unconditionally.
                    $baseRate = $oneWay / $effectiveMiles;
                }
            }
            $emptyMilesTotal = max(0, $load->empty_miles + $load->begin_empty_miles);
            $empty           = $emptyMilesTotal * $mt;

            if ($oneWay === 0.0 && $empty > 0.0) {
                // Empty-only: no overlay applies in the legacy formula.
                $emptyPay = round($empty, 2);
            } elseif ($oneWay > 0.0) {
                $combined   = $oneWay + $empty;
                $basePay    = round($oneWay, 2);
                $emptyPay   = round($empty, 2);
                $seniority  = round($combined * $newBump, 2);
                $shiftPay   = round($combined * $nightOn, 2);
                $weekendPay = round($combined * $weekendOn, 2);
            }
        }

        $np = $basePay + $emptyPay + $seniority + $shiftPay + $weekendPay + $extras;

        return $this->buildResult(
            np: round($np, 2),
            op: round($extras, 2),
            tripLabel: $load->load_type === 1 ? 'Round-trip' : 'One-way',
            tenureBand: $tenure, shift: $shift,
            baseMiles: $effectiveMiles, baseRate: round($baseRate, 4),
            basePay: $basePay, emptyPay: $emptyPay,
            seniorityPct: $newBump, seniorityPay: $seniority,
            shiftPct: $nightOn, shiftPay: $shiftPay,
            weekendPct: $weekendOn, weekendPay: $weekendPay,
            extras: $extrasBreakdown,
        );
    }

    /**
     * Assemble the public result array. Centralised so every code path
     * returns the same shape — the dashboard view trusts every key to
     * exist.
     *
     * @param array{split_pay:float, extra_pay:float, dem_pay:float, break_pay:float} $extras
     * @return array{
     *   np: float, op: float, trip_label: string,
     *   tenure_band: string, shift: string,
     *   base_miles: int, base_rate: float,
     *   base_pay: float, empty_pay: float,
     *   seniority_pct: float, seniority_pay: float,
     *   shift_pct: float, shift_pay: float,
     *   weekend_pct: float, weekend_pay: float,
     *   split_pay: float, extra_pay: float, dem_pay: float, break_pay: float,
     * }
     */
    private function buildResult(
        float $np, float $op, string $tripLabel,
        string $tenureBand, string $shift,
        int $baseMiles, float $baseRate,
        float $basePay, float $emptyPay,
        float $seniorityPct, float $seniorityPay,
        float $shiftPct, float $shiftPay,
        float $weekendPct, float $weekendPay,
        array $extras,
    ): array {
        return [
            'np'            => $np,
            'op'            => $op,
            'trip_label'    => $tripLabel,
            'tenure_band'   => $tenureBand,
            'shift'         => $shift,
            'base_miles'    => $baseMiles,
            'base_rate'     => $baseRate,
            'base_pay'      => $basePay,
            'empty_pay'     => $emptyPay,
            'seniority_pct' => $seniorityPct,
            'seniority_pay' => $seniorityPay,
            'shift_pct'     => $shiftPct,
            'shift_pay'     => $shiftPay,
            'weekend_pct'   => $weekendPct,
            'weekend_pay'   => $weekendPay,
            'split_pay'     => $extras['split_pay'],
            'extra_pay'     => $extras['extra_pay'],
            'dem_pay'       => $extras['dem_pay'],
            'break_pay'     => $extras['break_pay'],
        ];
    }

    /**
     * @return array{split_pay:float, extra_pay:float, dem_pay:float, break_pay:float}
     */
    private function extrasBreakdown(LoadInputs $load, float $demRate, float $brkRate): array
    {
        return [
            'split_pay' => $load->is_split > 0 ? 15.0 : 0.0,
            'extra_pay' => round($load->extra_pay, 2),
            'dem_pay'   => round($load->dem_minutes   * $demRate, 2),
            'break_pay' => round($load->break_minutes * $brkRate, 2),
        ];
    }


    /**
     * Map driver tenure (months) onto a tenure band and return the
     * per-band multipliers the formula uses.
     *
     * Bands are months-since-hire:
     *   tenure <=  6   → '6'
     *   tenure <= 12   → '12'
     *   tenure <= 24   → '24'
     *   tenure <= 60   → '60'
     *   tenure <= 108  → '108'
     *   tenure <= 168  → '168'
     *   tenure  > 168  → '168' (caps at the top band; 'max' would be a
     *                            manual senior-override path we don't
     *                            currently model)
     *
     * Anything unparseable falls back to '168' — the senior band.
     *
     * The legacy pay_variables table also defines a `{band}_tb` value
     * per band, but it isn't read by any formula path; we don't surface
     * it here.
     *
     * @return array{mt:string, wk:string, newBump:string, night:string}
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
            default                                                             => '168',
        };
        return [
            'mt'      => $this->vars->get("{$band}_mt",      '0'),
            'wk'      => $this->vars->get("{$band}_wk",      '0'),
            'newBump' => $this->vars->get("{$band}_newBump", '0'),
            'night'   => $this->vars->get("{$band}_night",   '0'),
        ];
    }
}
