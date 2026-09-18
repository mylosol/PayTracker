<?php

declare(strict_types=1);

namespace PayTracker\Tests\Unit\Models;

use PayTracker\Models\PayRate;
use PHPUnit\Framework\TestCase;

/**
 * PayRate::flagRungs — the "this row looks wrong" audit the pay-admin
 * surfaces render. The rules exist because the live ladder contains real
 * cases: the round-trip 44-mile rung still carries its 2019 value ($47.3874)
 * while every neighbour was raised, so it pays $7.49 less than the 42-mile
 * rung, and the legacy ladder carried a 999.9999 placeholder at 122 miles.
 */
final class PayRateFlagRungsTest extends TestCase
{
    public function testWellFormedLadderIsSilent(): void
    {
        $flags = PayRate::flagRungs([
            10 => 38.2767, 15 => 40.5556, 20 => 42.1367, 25 => 44.2451,
        ]);

        $this->assertSame([], $flags);
    }

    public function testFlagsTheRungThatPaysLessThanTheOneBelowIt(): void
    {
        // Live values around the fault, 2026-06-02 export.
        $flags = PayRate::flagRungs([
            40 => 53.1045, 42 => 54.8727, 44 => 47.3874, 46 => 57.9336,
        ]);

        $this->assertSame([44], array_keys($flags));
        $this->assertStringContainsString('$7.49 less than the 42 mi row', $flags[44]);
    }

    public function testFlagsAPlaceholderOutlier(): void
    {
        // The legacy sentinel, in the shape it actually shipped in.
        $flags = PayRate::flagRungs([
            118 => 94.4479, 120 => 95.8695, 122 => 999.9999, 124 => 97.3763,
        ]);

        $this->assertSame([122], array_keys($flags));
        $this->assertStringContainsString('placeholder value?', $flags[122]);
        // Named as a placeholder, not as a ladder break against 120.
        $this->assertStringNotContainsString('less than', $flags[122]);
    }

    public function testIgnoresLargeButMonotonicJumps(): void
    {
        // Long-haul 204 -> 206 is +$50.66 on the live ladder; it is a step,
        // not a fault, and flagging it would devalue the flag.
        $flags = PayRate::flagRungs([
            202 => 99.7958, 204 => 100.5285, 206 => 151.1903, 208 => 152.9891,
        ]);

        $this->assertSame([], $flags);
    }

    public function testEmptyLadderIsSilent(): void
    {
        $this->assertSame([], PayRate::flagRungs([]));
    }

    public function testUnsortedInputIsFlaggedInMileageOrder(): void
    {
        $flags = PayRate::flagRungs([
            46 => 57.9336, 44 => 47.3874, 42 => 54.8727,
        ]);

        $this->assertSame([44], array_keys($flags));
    }
}
