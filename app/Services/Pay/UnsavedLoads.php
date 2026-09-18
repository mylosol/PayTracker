<?php

declare(strict_types=1);

namespace PayTracker\Services\Pay;

/**
 * UnsavedLoads — the untrusted-input boundary for the browser scratchpad.
 *
 * A driver who doesn't have the dispatch FRTL yet can enter a load with
 * "Store Load Info" OFF. The server still computes it (POST /loads/preview
 * runs the same validation + mile lookup + PayCalculator pipeline), the
 * browser keeps the result under the `paytracker.unsavedLoads`
 * localStorage key, and the dashboard hydrates those rows client-side.
 * Nothing is written to the database.
 *
 * The pay-admin draft preview has to include those rows: they are part of
 * "the loads on my dashboard", so a preview that reads only `driver_loads`
 * silently under-reports against the dashboard's own totals ($0.00 vs the
 * $434.05 the driver sees). The preview page therefore posts the
 * browser's entries back to the server to be repriced — which makes this
 * the untrusted-input boundary:
 *
 *   - every field is validated and clamped; a malformed entry is dropped
 *     rather than fatal, and the count is capped
 *   - the money the browser holds (np/op, and the whole precomputed
 *     `pay_breakdown`) is NEVER read. Pay is recomputed server-side with
 *     the same PayCalculator the saved path uses, so a tampered payload
 *     can only mislead the person who tampered with it
 *   - entry count and field sizes are capped so an oversized payload can't
 *     turn a page render into a memory/CPU sink
 *
 * Deliberately free of side effects and dependencies: parsing is pure, so
 * it can be unit-tested without a database.
 */
final class UnsavedLoads
{
    /** Hard cap on entries accepted from one payload. */
    public const MAX_ENTRIES = 100;

    /** Raw payload ceiling, checked before json_decode() sees it. */
    public const MAX_PAYLOAD_BYTES = 512_000;

    public const MAX_MILES        = 100_000;
    public const MAX_MINUTES      = 10_000;
    public const MAX_MONEY        = 100_000.0;
    public const MAX_ID_LENGTH    = 64;
    public const MAX_CITY_LENGTH  = 80;
    public const MAX_NOTES_LENGTH = 1000;

    /**
     * Load types the calculator can price: 0 = one-way, 1 = round-trip,
     * 4 = trainer. Anything else is dropped (the form can't produce it,
     * so its presence means a hand-built payload).
     */
    public const LOAD_TYPES = [0, 1, 4];

    /**
     * Parse the browser's scratchpad array.
     *
     * The expected shape is exactly what the dashboard reads from
     * localStorage — a list of
     *   { local_id: string, created_at: int, computed: {...} }
     * where only `computed` matters here (`created_at` is the browser's
     * 24-hour expiry bookkeeping, re-derived client-side before submit).
     *
     * @return array{
     *   entries: list<array<string,mixed>>,
     *   dropped: int,
     *   truncated: bool,
     *   error: ?string
     * } `entries` is normalized and safe to feed to toLoadInputs().
     */
    public function parse(string $json): array
    {
        $empty = ['entries' => [], 'dropped' => 0, 'truncated' => false, 'error' => null];

        $json = trim($json);
        if ($json === '') {
            // No scratchpad on this browser — the normal case, not an error.
            return $empty;
        }
        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            return [
                'entries'   => [],
                'dropped'   => 0,
                'truncated' => false,
                'error'     => 'That unconfirmed-load payload is too large to preview. Discard stale entries on the dashboard and try again.',
            ];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [
                'entries'   => [],
                'dropped'   => 0,
                'truncated' => false,
                'error'     => 'Those unconfirmed loads could not be read, so they were left out of the preview.',
            ];
        }

        $truncated = false;
        if (count($decoded) > self::MAX_ENTRIES) {
            // Keep the newest — the array is append-ordered, and a driver
            // with 100+ live scratchpad entries has a different problem.
            $decoded   = array_slice($decoded, -self::MAX_ENTRIES);
            $truncated = true;
        }

        $entries = [];
        $dropped = 0;
        foreach ($decoded as $raw) {
            $entry = $this->normalize($raw);
            if ($entry === null) {
                $dropped++;
                continue;
            }
            $entries[] = $entry;
        }

        return ['entries' => $entries, 'dropped' => $dropped, 'truncated' => $truncated, 'error' => null];
    }

    /**
     * Validate + clamp one scratchpad entry. Returns null when the entry
     * is unusable (no `computed`, bad date, unknown load_type) — the
     * caller counts those and carries on so one bad row can't blank the
     * page.
     *
     * Note the absent keys: `np`, `op` and `pay_breakdown` are read
     * nowhere in this class, on purpose. Whatever the browser claims the
     * load pays is ignored; the preview recomputes it.
     *
     * @return array<string,mixed>|null
     */
    public function normalize(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $c = $raw['computed'] ?? null;
        if (! is_array($c)) {
            return null;
        }

        $date = is_string($c['date'] ?? null) ? substr(trim($c['date']), 0, 10) : '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $loadType = is_numeric($c['load_type'] ?? null) ? (int) $c['load_type'] : -1;
        if (! in_array($loadType, self::LOAD_TYPES, true)) {
            return null;
        }

        return [
            'local_id'           => $this->identifier($raw['local_id'] ?? ''),
            'date'               => $date,
            'load_type'          => $loadType,
            'pickup_city'        => $this->text($c['pickup_city'] ?? '', self::MAX_CITY_LENGTH),
            'delivery_city'      => $this->text($c['delivery_city'] ?? '', self::MAX_CITY_LENGTH),
            'end_empty_city'     => $this->text($c['end_empty_city'] ?? '', self::MAX_CITY_LENGTH),
            'notes'              => $this->text($c['notes'] ?? '', self::MAX_NOTES_LENGTH),
            'empty_miles'        => $this->count($c['empty_miles'] ?? 0, self::MAX_MILES),
            'begin_empty_miles'  => $this->count($c['begin_empty_miles'] ?? 0, self::MAX_MILES),
            'end_empty_miles'    => $this->count($c['end_empty_miles'] ?? 0, self::MAX_MILES),
            'out_of_route_miles' => $this->count($c['out_of_route_miles'] ?? 0, self::MAX_MILES),
            'out_of_route_ind'   => $this->flag($c['out_of_route_ind'] ?? 0),
            'dem_minutes'        => $this->count($c['dem_minutes'] ?? 0, self::MAX_MINUTES),
            'break_minutes'      => $this->count($c['break_minutes'] ?? 0, self::MAX_MINUTES),
            'is_split'           => $this->flag($c['is_split'] ?? 0),
            'is_weekend'         => $this->flag($c['is_weekend'] ?? 0),
            'is_backhaul'        => $this->flag($c['is_backhaul'] ?? 0),
            'extra_pay'          => $this->money($c['extra_pay'] ?? 0),
        ];
    }

    /**
     * Map a normalized entry onto the calculator's input DTO.
     *
     * `$variablesBlob` comes from the viewer's CURRENT profile
     * (VariableBlobBuilder), matching what /loads would rebuild on a
     * convert-to-saved submit — the browser never stores the blob because
     * it is server-only.
     *
     * @param array<string,mixed> $entry Normalized entry from parse().
     */
    public function toLoadInputs(array $entry, string $variablesBlob): LoadInputs
    {
        return new LoadInputs(
            load_type: (int) $entry['load_type'],
            // Legacy misnomer carried through the schema: the
            // `empty_miles` column holds the LOADED leg's miles.
            load_miles:         (int) $entry['empty_miles'],
            empty_miles:        (int) $entry['end_empty_miles'],
            begin_empty_miles:  (int) $entry['begin_empty_miles'],
            is_split:           (int) $entry['is_split'],
            is_weekend:         (int) $entry['is_weekend'],
            is_backhaul:        (int) $entry['is_backhaul'],
            extra_pay:          (float) $entry['extra_pay'],
            dem_minutes:        (int) $entry['dem_minutes'],
            break_minutes:      (int) $entry['break_minutes'],
            variables_blob:     $variablesBlob,
            out_of_route_ind:   (int) $entry['out_of_route_ind'],
            out_of_route_miles: (int) $entry['out_of_route_miles'],
            load_date:          (string) $entry['date'],
        );
    }

    /** Trim + hard cap a free-text field. */
    private function text(mixed $value, int $maxLength): string
    {
        if (! is_string($value)) {
            return '';
        }
        return mb_substr(trim($value), 0, $maxLength);
    }

    /** Clamp a mile/minute count into [0, $max]. */
    private function count(mixed $value, int $max): int
    {
        if (! is_numeric($value)) {
            return 0;
        }
        return max(0, min($max, (int) $value));
    }

    /** Booleans arrive as 0/1 (JSON numbers) with the odd `true`. */
    private function flag(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return is_numeric($value) && (int) $value > 0 ? 1 : 0;
    }

    /** Clamp + round a dollar amount into [0, MAX_MONEY]. */
    private function money(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }
        return round(max(0.0, min(self::MAX_MONEY, (float) $value)), 2);
    }

    /** Keep localId characters that are safe in a URL query value. */
    private function identifier(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '', $value) ?? '';
        return substr($clean, 0, self::MAX_ID_LENGTH);
    }
}
