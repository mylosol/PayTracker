<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

use PayTracker\Models\Account;

/**
 * PayWeek — the single source of truth for "which pay week is this?".
 *
 * The pay week is driver-configurable: `account.pay_week_start_day` is
 * one of sun..sat, defaulting to sun. Three surfaces depend on those
 * boundaries — the driver dashboard (its "this week" totals), the
 * reconcile page (the current week plus the previous four), and the
 * pay-admin draft preview (which reprices the loads the viewer can
 * actually see on their own dashboard). Each of them used to recompute
 * the boundaries inline, which is exactly the kind of duplicated
 * arithmetic that drifts: two pages then disagree about which loads
 * fall inside "this week". This class exists so they can't.
 *
 * Pure by design — no DB, no session, no clock beyond "today" as the
 * fallback for an unparseable date. That keeps it unit-testable without
 * a database harness.
 */
final class PayWeek
{
    /** Weekday key applied when the account has no valid preference. */
    public const DEFAULT_START_DAY = 'sun';

    /**
     * The pay week containing `$date` for the given account.
     *
     * @param array<string,mixed> $account Account row; reads pay_week_start_day.
     * @param string $date YYYY-MM-DD. Anything else (empty, malformed)
     *                     falls back to today rather than throwing — the
     *                     callers are HTTP surfaces where a bad query
     *                     param shouldn't 500.
     *
     * @return array{
     *   start_day:string, start:string, end:string, since:string, until:string
     * }
     *   start/end are YYYY-MM-DD and inclusive (end = start + 6 days);
     *   since/until are the SQL window bounds for the same week —
     *   since inclusive at 00:00:00, until exclusive (start + 7 days at
     *   00:00:00), matching Model::forDriverInWindow() semantics.
     */
    public static function containing(array $account, string $date): array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = date('Y-m-d');
        }

        $startDay = $account['pay_week_start_day'] ?? null;
        if (! is_string($startDay) || ! in_array($startDay, Account::PAY_WEEK_DAYS, true)) {
            $startDay = self::DEFAULT_START_DAY;
        }

        // date('w') is 0=Sun..6=Sat and PAY_WEEK_DAYS is in the same
        // calendar order, so the index IS the weekday number. Subtracting
        // the difference and taking mod 7 lands on the most recent
        // week-start at or before $date.
        $startIndex = (int) array_search($startDay, Account::PAY_WEEK_DAYS, true);
        $dow        = (int) date('w', strtotime($date));
        $offsetDays = ($dow - $startIndex + 7) % 7;

        $start = date('Y-m-d', strtotime($date . ' -' . $offsetDays . ' days'));
        $end   = date('Y-m-d', strtotime($start . ' +6 days'));

        return [
            'start_day' => $startDay,
            'start'     => $start,
            'end'       => $end,
            'since'     => $start . ' 00:00:00',
            'until'     => date('Y-m-d 00:00:00', strtotime($start . ' +7 days')),
        ];
    }
}
