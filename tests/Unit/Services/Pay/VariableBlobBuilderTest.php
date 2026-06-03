<?php

declare(strict_types=1);

namespace PayTracker\Tests\Unit\Services\Pay;

use PayTracker\Services\Pay\VariableBlobBuilder;
use PHPUnit\Framework\TestCase;

final class VariableBlobBuilderTest extends TestCase
{
    private VariableBlobBuilder $builder;
    private \DateTimeImmutable $asOf;

    protected function setUp(): void
    {
        $this->builder = new VariableBlobBuilder();
        $this->asOf    = new \DateTimeImmutable('2026-06-01');
    }

    public function testNullHireDateFallsBackToJuniorBandAndDayShift(): void
    {
        // Junior-band default is the financially safer side: under-pay
        // is recoverable via /pay-admin/recompute once the profile is
        // set; over-pay is not.
        $blob = $this->builder->build(['hire_date' => null], $this->asOf);
        $this->assertSame('6-day--0', $blob);
    }

    public function testInvalidHireDateFallsBackToJuniorBand(): void
    {
        $blob = $this->builder->build(['hire_date' => 'garbage', 'shift' => 'night'], $this->asOf);
        $this->assertSame('6-night--0', $blob);
    }

    public function testHiredThreeMonthsAgoMapsToBand6(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P3M'));
        $blob = $this->builder->build([
            'hire_date' => $hire->format('Y-m-d'),
            'shift'     => 'day',
        ], $this->asOf);
        $this->assertSame('6-day--0', $blob);
    }

    public function testHiredTenMonthsAgoMapsToBand12(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P10M'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('12-day--0', $blob);
    }

    public function testHiredTwentyMonthsAgoMapsToBand24(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P20M'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('24-day--0', $blob);
    }

    public function testHiredFortyMonthsAgoMapsToBand60(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P40M'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('60-day--0', $blob);
    }

    public function testHiredEightyMonthsAgoMapsToBand108(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P80M'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('108-day--0', $blob);
    }

    public function testHiredOneHundredFiftyMonthsAgoMapsToBand168(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P150M'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('168-day--0', $blob);
    }

    public function testPastTopBandPromotesToMax(): void
    {
        // 20 years = 240 months, past the 168 cap → senior 'max' tier.
        $hire = $this->asOf->sub(new \DateInterval('P20Y'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('max-day--0', $blob);
    }

    public function testJustOverTopBandPromotesToMax(): void
    {
        // 169 months — one past the 168 boundary.
        $hire = $this->asOf->sub(new \DateInterval('P169M'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('max-day--0', $blob);
    }

    public function testNightShiftIsPreservedInBlob(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P3M'));
        $blob = $this->builder->build([
            'hire_date' => $hire->format('Y-m-d'),
            'shift'     => 'night',
        ], $this->asOf);
        $this->assertSame('6-night--0', $blob);
    }

    public function testPartialMonthStaysInLowerBand(): void
    {
        // Hired 5 months and 28 days before asOf — still 5 months for
        // band purposes, lands in band 6 (since 5 <= 6).
        $hire = $this->asOf->sub(new \DateInterval('P5M28D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('6-day--0', $blob);
    }

    public function testSixMonthBoundaryStaysInBand6(): void
    {
        // Exactly 6 months → band 6 (the comparison is <=, not <).
        $hire = $this->asOf->sub(new \DateInterval('P6M'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('6-day--0', $blob);
    }

    public function testSevenMonthsCrossesIntoBand12(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P7M'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('12-day--0', $blob);
    }

    public function testUnknownShiftFallsBackToDay(): void
    {
        // No hire_date supplied → junior tenure fallback; bad shift → day.
        $blob = $this->builder->build(['shift' => 'evening'], $this->asOf);
        $this->assertSame('6-day--0', $blob);
    }

    public function testFutureHireDateTreatedAsBrandNew(): void
    {
        $hire = $this->asOf->add(new \DateInterval('P30D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('6-day--0', $blob);
    }
}
