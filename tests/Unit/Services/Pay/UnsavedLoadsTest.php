<?php

declare(strict_types=1);

namespace PayTracker\Tests\Unit\Services\Pay;

use PayTracker\Services\Pay\UnsavedLoads;
use PHPUnit\Framework\TestCase;

/**
 * UnsavedLoads — the untrusted boundary for the browser scratchpad.
 *
 * Everything here is about the same question: can a hand-crafted payload
 * make the pay-admin preview lie, crash, or work too hard? The answer has
 * to be no on all three counts, so the cases below cover shape rejection,
 * clamping, caps and — most importantly — that the browser's own money
 * figures are never used.
 */
final class UnsavedLoadsTest extends TestCase
{
    private UnsavedLoads $unsaved;

    protected function setUp(): void
    {
        $this->unsaved = new UnsavedLoads();
    }

    /** A realistic scratchpad entry, as POST /loads/preview would have produced it. */
    private function entry(array $computedOverride = [], array $entryOverride = []): array
    {
        $computed = array_merge([
            'date'               => '2026-09-18',
            'load_type'          => 1,
            'pickup_city'        => 'Pensacola, FL',
            'delivery_city'      => 'Mobile, AL',
            'end_empty_city'     => '',
            'end_empty_miles'    => 0,
            'empty_miles'        => 320,
            'begin_empty_miles'  => 30,
            'is_split'           => 0,
            'is_weekend'         => 1,
            'is_backhaul'        => 0,
            'extra_pay'          => '25.00',
            'dem_minutes'        => 30,
            'break_minutes'      => 0,
            'out_of_route_ind'   => 0,
            'out_of_route_miles' => 0,
            'notes'              => 'scratchpad test',
            'np'                 => 421.13,
            'op'                 => 400.00,
            'pay_breakdown'      => ['np' => 421.13, 'base_pay' => 300.0],
        ], $computedOverride);

        return array_merge([
            'local_id'   => 'u_abc123_xyz',
            'created_at' => 1_760_000_000_000,
            'computed'   => $computed,
        ], $entryOverride);
    }

    public function testEmptyPayloadIsNotAnError(): void
    {
        // No scratchpad on this browser is the common case, not a failure.
        $parsed = $this->unsaved->parse('');

        $this->assertSame([], $parsed['entries']);
        $this->assertNull($parsed['error']);
    }

    public function testMalformedJsonIsReportedNotThrown(): void
    {
        $parsed = $this->unsaved->parse('{not json');

        $this->assertSame([], $parsed['entries']);
        $this->assertNotNull($parsed['error']);
    }

    public function testRoundTripsARealisticEntry(): void
    {
        $parsed = $this->unsaved->parse(json_encode([$this->entry()], JSON_THROW_ON_ERROR));

        $this->assertNull($parsed['error']);
        $this->assertCount(1, $parsed['entries']);

        $entry = $parsed['entries'][0];
        $this->assertSame('u_abc123_xyz', $entry['local_id']);
        $this->assertSame('2026-09-18', $entry['date']);
        $this->assertSame(1, $entry['load_type']);
        $this->assertSame(320, $entry['empty_miles']);
        $this->assertSame(30, $entry['begin_empty_miles']);
        $this->assertSame(1, $entry['is_weekend']);
        $this->assertSame(25.0, $entry['extra_pay']);
        $this->assertSame('scratchpad test', $entry['notes']);
    }

    public function testBrowserMoneyFiguresAreNeverRead(): void
    {
        // The payload claims an absurd np. The normalized entry must not
        // carry it — the preview recomputes, so a tampered payload can
        // only mislead whoever tampered with it.
        $parsed = $this->unsaved->parse(json_encode([
            $this->entry(['np' => 999999.99, 'op' => 999999.99, 'pay_breakdown' => ['np' => 999999.99]]),
        ], JSON_THROW_ON_ERROR));

        $entry = $parsed['entries'][0];
        $this->assertArrayNotHasKey('np', $entry);
        $this->assertArrayNotHasKey('op', $entry);
        $this->assertArrayNotHasKey('pay_breakdown', $entry);
    }

    public function testEntriesWithoutComputedAreDropped(): void
    {
        $parsed = $this->unsaved->parse(json_encode([
            $this->entry(),
            ['local_id' => 'u_no_computed'],
            ['computed' => 'not-an-object'],
            'garbage',
        ], JSON_THROW_ON_ERROR));

        $this->assertCount(1, $parsed['entries']);
        $this->assertSame(3, $parsed['dropped']);
    }

    public function testBadDateOrUnknownLoadTypeIsDropped(): void
    {
        $parsed = $this->unsaved->parse(json_encode([
            $this->entry(['date' => '18/09/2026']),
            $this->entry(['load_type' => 9]),
            $this->entry(['load_type' => 'trainer']),
            $this->entry(['load_type' => 4]), // trainer IS valid
        ], JSON_THROW_ON_ERROR));

        $this->assertCount(1, $parsed['entries']);
        $this->assertSame(4, $parsed['entries'][0]['load_type']);
    }

    public function testNumbersAreClampedAndNegativesZeroed(): void
    {
        $parsed = $this->unsaved->parse(json_encode([
            $this->entry([
                'empty_miles'       => -500,
                'begin_empty_miles' => 9_999_999,
                'dem_minutes'       => 'abc',
                'extra_pay'         => -100,
                'is_split'          => 7,
                'is_backhaul'       => true,
            ]),
        ], JSON_THROW_ON_ERROR));

        $entry = $parsed['entries'][0];
        $this->assertSame(0, $entry['empty_miles']);
        $this->assertSame(UnsavedLoads::MAX_MILES, $entry['begin_empty_miles']);
        $this->assertSame(0, $entry['dem_minutes']);
        $this->assertSame(0.0, $entry['extra_pay']);
        $this->assertSame(1, $entry['is_split']);
        $this->assertSame(1, $entry['is_backhaul']);
    }

    public function testTextFieldsAreTrimmedAndCapped(): void
    {
        $parsed = $this->unsaved->parse(json_encode([
            $this->entry(['pickup_city' => '  ' . str_repeat('x', 500) . '  ']),
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(
            UnsavedLoads::MAX_CITY_LENGTH,
            mb_strlen($parsed['entries'][0]['pickup_city']),
        );

        $parsed = $this->unsaved->parse(json_encode([
            $this->entry(['notes' => str_repeat('n', 5000)]),
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(
            UnsavedLoads::MAX_NOTES_LENGTH,
            mb_strlen($parsed['entries'][0]['notes']),
        );
    }

    public function testLocalIdIsSanitisedForUrlUse(): void
    {
        $parsed = $this->unsaved->parse(json_encode([
            $this->entry([], ['local_id' => 'u_ok<script>"\'' . str_repeat('z', 200)]),
        ], JSON_THROW_ON_ERROR));

        $localId = $parsed['entries'][0]['local_id'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]+$/', $localId);
        $this->assertLessThanOrEqual(UnsavedLoads::MAX_ID_LENGTH, strlen($localId));
    }

    public function testEntryListIsCappedKeepingTheNewest(): void
    {
        $many = [];
        for ($i = 0; $i < UnsavedLoads::MAX_ENTRIES + 25; $i++) {
            $many[] = $this->entry([], ['local_id' => 'u_' . $i]);
        }

        $parsed = $this->unsaved->parse(json_encode($many, JSON_THROW_ON_ERROR));

        $this->assertCount(UnsavedLoads::MAX_ENTRIES, $parsed['entries']);
        $this->assertTrue($parsed['truncated']);
        // Newest kept: the tail of the array survives.
        $this->assertSame('u_' . (UnsavedLoads::MAX_ENTRIES + 24), $parsed['entries'][UnsavedLoads::MAX_ENTRIES - 1]['local_id']);
    }

    public function testOversizedPayloadIsRejectedBeforeDecoding(): void
    {
        $huge = str_repeat('a', UnsavedLoads::MAX_PAYLOAD_BYTES + 10);

        $parsed = $this->unsaved->parse($huge);

        $this->assertSame([], $parsed['entries']);
        $this->assertNotNull($parsed['error']);
    }

    public function testMapsOntoCalculatorInputs(): void
    {
        $entry = $this->unsaved->parse(json_encode([
            $this->entry(['empty_miles' => 320, 'end_empty_miles' => 180, 'is_backhaul' => 1]),
        ], JSON_THROW_ON_ERROR))['entries'][0];

        $inputs = $this->unsaved->toLoadInputs($entry, '168-night--0');

        // The `empty_miles` scratchpad field is the legacy misnomer for
        // LOADED miles — it must land on load_miles, while the end-empty
        // leg lands on empty_miles.
        $this->assertSame(320, $inputs->load_miles);
        $this->assertSame(180, $inputs->empty_miles);
        $this->assertSame(30, $inputs->begin_empty_miles);
        $this->assertSame(1, $inputs->is_weekend);
        $this->assertSame(1, $inputs->is_backhaul);
        $this->assertSame('168-night--0', $inputs->variables_blob);
        $this->assertSame('2026-09-18', $inputs->load_date);
    }
}
