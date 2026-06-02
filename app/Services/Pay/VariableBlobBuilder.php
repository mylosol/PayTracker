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
 *   tenure: the driver's pay band, derived from weeks-since-hire and
 *           snapped to one of the legacy bands (6, 12, 24, 60, 108,
 *           168). Drivers past 168 weeks stay in the 168 band; we
 *           don't auto-promote to 'max' because legacy treats 'max'
 *           as a manual override, not a tenure ceiling.
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
 *   render time, a driver could cross a band boundary mid-week and
 *   have last week's loads silently revalue. Snapshotting into the
 *   load's variables column on insert preserves "the tenure that
 *   was in effect when this load happened" as the historical truth.
 *   The recompute path (PayRecomputer) reads back that snapshot, so
 *   re-running pay against current rates uses the *original* tenure
 *   for each row, not the driver's current tenure.
 */
final class VariableBlobBuilder
{
    /** Tenure bands, in ascending order. >168 stays at 168. */
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
     * Weeks since hire_date, snapped down to the matching band.
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

        $seconds = $asOf->getTimestamp() - $hire->getTimestamp();
        if ($seconds <= 0) {
            // Hire date in the future is nonsensical; treat as brand-new.
            return (string) self::BANDS[0];
        }
        $weeks = (int) floor($seconds / 604_800); // 60 * 60 * 24 * 7

        foreach (self::BANDS as $band) {
            if ($weeks <= $band) {
                return (string) $band;
            }
        }
        // Past the top band — stay there. 'max' is reserved for the
        // manual senior-override path which we don't model yet.
        return (string) self::BANDS[count(self::BANDS) - 1];
    }
}
