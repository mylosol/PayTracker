<?php

declare(strict_types=1);

namespace PayTracker\Tests\Unit\Services\Pay;

use PayTracker\Models\Account;
use PayTracker\Services\Pay\PayWeek;
use PHPUnit\Framework\TestCase;

/**
 * PayWeek — boundaries of the driver-configurable pay week.
 *
 * These are the boundaries that decide which loads the dashboard totals
 * and (as of the draft-preview fix) the pay-admin "Preview impact" page
 * read. An off-by-one here silently moves a load between weeks on two
 * different pages, so every case below is an exact-date assertion rather
 * than a "roughly a week" check.
 *
 * Reference dates: 2026-09-13 is a Sunday, so the week Sun→Sat is
 * 2026-09-13 .. 2026-09-19.
 */
final class PayWeekTest extends TestCase
{
    public function testDefaultsToSundayWhenPreferenceIsMissing(): void
    {
        $week = PayWeek::containing([], '2026-09-16'); // Wednesday

        $this->assertSame('sun', $week['start_day']);
        $this->assertSame('2026-09-13', $week['start']);
        $this->assertSame('2026-09-19', $week['end']);
    }

    public function testNullPreferenceFallsBackToSunday(): void
    {
        $week = PayWeek::containing(['pay_week_start_day' => null], '2026-09-16');

        $this->assertSame('2026-09-13', $week['start']);
    }

    public function testGarbagePreferenceFallsBackToSunday(): void
    {
        // A hand-edited account row must not be able to produce a
        // non-existent weekday and shift every window by an arbitrary
        // number of days.
        $week = PayWeek::containing(['pay_week_start_day' => 'caturday'], '2026-09-16');

        $this->assertSame('sun', $week['start_day']);
        $this->assertSame('2026-09-13', $week['start']);
    }

    public function testMondayStartContainsFriday(): void
    {
        $week = PayWeek::containing(['pay_week_start_day' => 'mon'], '2026-09-18');

        $this->assertSame('2026-09-14', $week['start']); // Monday
        $this->assertSame('2026-09-20', $week['end']);   // Sunday
    }

    public function testSaturdayStartContainsFriday(): void
    {
        // Friday 2026-09-18 sits at the END of a Saturday-start week.
        $week = PayWeek::containing(['pay_week_start_day' => 'sat'], '2026-09-18');

        $this->assertSame('2026-09-12', $week['start']); // Saturday
        $this->assertSame('2026-09-18', $week['end']);   // Friday
    }

    public function testAnchorOnStartDayReturnsThatSameDay(): void
    {
        $week = PayWeek::containing(['pay_week_start_day' => 'sun'], '2026-09-13');

        $this->assertSame('2026-09-13', $week['start']);
        $this->assertSame('2026-09-19', $week['end']);
    }

    public function testAnchorOnLastDayReturnsThatWeeksStart(): void
    {
        $week = PayWeek::containing(['pay_week_start_day' => 'sun'], '2026-09-19'); // Saturday

        $this->assertSame('2026-09-13', $week['start']);
    }

    public function testMalformedDateFallsBackToTodaysWeek(): void
    {
        // HTTP surfaces pass this straight through from a query param;
        // a typo must not throw or produce a 1970 week.
        $fallback = PayWeek::containing(['pay_week_start_day' => 'sun'], '');
        $today    = PayWeek::containing(['pay_week_start_day' => 'sun'], date('Y-m-d'));

        $this->assertSame($today, $fallback);
    }

    public function testWindowBoundsAreMidnightToMidnightExclusive(): void
    {
        $week = PayWeek::containing(['pay_week_start_day' => 'sun'], '2026-09-16');

        $this->assertSame('2026-09-13 00:00:00', $week['since']);
        $this->assertSame('2026-09-20 00:00:00', $week['until']);

        // since inclusive, until exclusive => 7 days of coverage, which
        // is what forDriverInWindow()/forPreviewForDriver() assume.
        $this->assertSame(
            7 * 86400,
            strtotime($week['until']) - strtotime($week['since']),
        );
    }

    public function testEveryStartDayProducesASevenDayWeekAnchoredOnThatWeekday(): void
    {
        $anchor = '2026-09-16'; // Wednesday

        foreach (Account::PAY_WEEK_DAYS as $index => $key) {
            $week = PayWeek::containing(['pay_week_start_day' => $key], $anchor);

            $this->assertSame($key, $week['start_day']);
            $this->assertSame(
                $index,
                (int) date('w', strtotime($week['start'])),
                sprintf('week starting on %s should begin on weekday %d', $key, $index),
            );
            $this->assertSame(
                6 * 86400,
                strtotime($week['end']) - strtotime($week['start']),
                sprintf('week starting on %s must span 7 days (end inclusive)', $key),
            );

            // The anchor day itself must fall inside the window.
            $this->assertGreaterThanOrEqual(strtotime($week['since']), strtotime($anchor . ' 12:00:00'));
            $this->assertLessThan(strtotime($week['until']), strtotime($anchor . ' 12:00:00'));
        }
    }
}
