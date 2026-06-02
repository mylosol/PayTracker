<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

/**
 * VariableBlobBuilder — turns a driver account row into the
 * "tenure-shift-slip-?" hyphen-string the PayCalculator reads as
 * LoadInputs::$variables_blob.
 *
 * The output format is fixed by the legacy schema: four hyphen-
 * separated fields, slip currently always empty, the trailing flag
 * currently always "0". The interesting fields are:
 *
 *   tenure: the driver's pay band, derived from MONTHS-since-hire
 *           and snapped to one of the legacy bands (6, 12, 24, 60,
 *           108, 168). The bands are MONTHS, NOT weeks — matching
 *           the legacy `169+ M` pill on the production load card.
 *           Drivers past 168 months stay in the 168 band; we don't
 *           auto-promote to 'max' because legacy treats 'max' as a
 *           manual override, not a tenure ceiling.
 *           When hire_date is missing/invalid we fall back to the
 *           JUNIOR band ('6'), not the senior ('168'). Under-paying
 *           a senior whose profile is unset is recoverable: they set
 *           the date, admin re-runs /pay-admin/recompute, and the
 *           historical np is brought up to scale. Over-paying a
 *           junior by defaulting to senior is much harder to claw
 *           back, so the safer default is the floor.
 *
 *   shift:  the driver's default day/night, copied straight from
 *           account.shift.
 *
 * Why snapshot at write time, not compute at read time?
 *   Tenure changes with the calendar. If we recomputed at /dashboard
 *   render time, a driver could cross a band boundary mid-month and
 *   have last week's loads silently revalue. Snapshotting into the
 *   load's variables column on insert preserves "the tenure that
 *   was in effect when this load happened" as the historical truth.
 *   The recompute path (PayRecomputer) reads back that snapshot, so
 *   re-running pay against current rates uses the *original* tenure
 *   for each row, not the driver's current tenure.
 */
final class VariableBlobBuilder
{
    /** Tenure bands in MONTHS (matches PayCalculator). >168 stays at 168. */
    private const BANDS = [6, 12, 24, 60, 108, 168];

    /**
     * Build a variables blob for the given account row.
     *
     * Accepts a partial array because the auth layer returns a mixed-
     * key array. Missing/invalid fields fall back to the legacy
     * defaults so accounts created before the profile migration keep
     * working: '168' tenure, 'day' shift.
     *
     * @param array<string, mixed> $account
     */
    public function build(array $account, ?\DateTimeInterface $asOf = null): string
    {
        $asOf ??= new \DateTimeImmutable('now');

        $shiftRaw = $account['shift'] ?? 'day';
        $shift    = is_string($shiftRaw) && in_array($shiftRaw, ['day', 'night'], true) ? $shiftRaw : 'day';

        $tenure = $this->resolveTenure($account['hire_date'] ?? null, $asOf);

        // The third and fourth fields are slip-seat and a legacy flag;
        // both have been constants in 100% of observed rows.
        return $tenure . '-' . $shift . '--0';
    }

    /**
     * Months since hire_date, snapped down to the matching band.
     *
     * Months are calendar months, not 30-day buckets — a driver hired
     * 2026-01-15 hits "1 month" on 2026-02-15, not 30 days later.
     * \DateTimeImmutable::diff handles the calendar arithmetic.
     *
     * Returns '6' (junior floor) when hire_date is missing or
     * unparseable — the safer-by-default choice (see class docblock).
     * Drivers who set their profile after submitting loads get a
     * /pay-admin/recompute to lift np to the correct band.
     */
    private function resolveTenure(mixed $hireDate, \DateTimeInterface $asOf): string
    {
        if (! is_string($hireDate) || $hireDate === '') {
            return (string) self::BANDS[0];
        }
        $hire = \DateTimeImmutable::createFromFormat('Y-m-d', $hireDate);
        if ($hire === false) {
            return (string) self::BANDS[0];
        }

        if ($hire >= $asOf) {
            // Hire date today or in the future → brand-new driver.
            return (string) self::BANDS[0];
        }

        // Calendar-month diff: years*12 + months. We deliberately do NOT
        // round up on partial months — a driver hired 5 months and 28
        // days ago is still at 5 months, which lands in band 6 (since
        // 5 <= 6). Crossing the month boundary moves them up.
        $diff   = $hire->diff($asOf);
        $months = ($diff->y * 12) + $diff->m;

        foreach (self::BANDS as $band) {
            if ($months <= $band) {
                return (string) $band;
            }
        }
        // Past the top band — stay there. 'max' is reserved for the
        // manual senior-override path which we don't model yet.
        return (string) self::BANDS[count(self::BANDS) - 1];
    }
}
