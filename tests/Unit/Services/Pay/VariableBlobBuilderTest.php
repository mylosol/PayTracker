<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pay;

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

    public function testNullHireDateFallsBackToSeniorBandAndDayShift(): void
    {
        $blob = $this->builder->build(['hire_date' => null], $this->asOf);
        $this->assertSame('168-day--0', $blob);
    }

    public function testInvalidHireDateFallsBackToSeniorBand(): void
    {
        $blob = $this->builder->build(['hire_date' => 'garbage', 'shift' => 'night'], $this->asOf);
        $this->assertSame('168-night--0', $blob);
    }

    public function testHiredFourWeeksAgoMapsToBand6(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P28D'));
        $blob = $this->builder->build([
            'hire_date' => $hire->format('Y-m-d'),
            'shift'     => 'day',
        ], $this->asOf);
        $this->assertSame('6-day--0', $blob);
    }

    public function testHiredTenWeeksAgoMapsToBand12(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P70D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('12-day--0', $blob);
    }

    public function testHiredTwentyWeeksAgoMapsToBand24(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P140D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('24-day--0', $blob);
    }

    public function testHiredFiftyWeeksAgoMapsToBand60(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P350D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('60-day--0', $blob);
    }

    public function testHiredOneHundredWeeksAgoMapsToBand108(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P700D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('108-day--0', $blob);
    }

    public function testHiredOneHundredFiftyWeeksAgoMapsToBand168(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P1050D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('168-day--0', $blob);
    }

    public function testPastTopBandStaysAt168(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P3000D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('168-day--0', $blob);
    }

    public function testNightShiftIsPreservedInBlob(): void
    {
        $hire = $this->asOf->sub(new \DateInterval('P28D'));
        $blob = $this->builder->build([
            'hire_date' => $hire->format('Y-m-d'),
            'shift'     => 'night',
        ], $this->asOf);
        $this->assertSame('6-night--0', $blob);
    }

    public function testUnknownShiftFallsBackToDay(): void
    {
        $blob = $this->builder->build(['shift' => 'evening'], $this->asOf);
        $this->assertSame('168-day--0', $blob);
    }

    public function testFutureHireDateTreatedAsBrandNew(): void
    {
        $hire = $this->asOf->add(new \DateInterval('P30D'));
        $blob = $this->builder->build(['hire_date' => $hire->format('Y-m-d')], $this->asOf);
        $this->assertSame('6-day--0', $blob);
    }
}
