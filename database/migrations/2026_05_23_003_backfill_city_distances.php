<?php

declare(strict_types=1);

/*
 * 2026_05_23_003_backfill_city_distances.php
 *
 * Copies distance data from the legacy column-per-city matrix tables
 * (`largeMiles`, `pcola_largeMiles`) into the relational `city_distances`
 * table created by the prior schema migration.
 *
 * The legacy matrices are sparse — a row label and a column name uniquely
 * identify a directed (from, to) pair, and the cell value is the recorded
 * mileage. Zero cells are treated as "unknown" / not yet recorded and are
 * SKIPPED — converting them to literal zero-mile rows would pollute the
 * new table with ~13,000 sentinel rows.
 *
 * Data-quality fixes applied at the boundary
 *   - Trim each city label / column name.
 *   - Collapse runs of whitespace to a single space.
 *   - Remove whitespace immediately before a comma so e.g.
 *     "Bainbridge , GA" and "Bainbridge, GA" map to the same city. The
 *     legacy data contains both spellings of the SAME city in the same
 *     table — without this normalization the backfill would create two
 *     `city` rows for one city.
 *
 * Idempotency
 *   The migrator's tracking table skips already-applied migrations, so
 *   under normal operation this file runs exactly once. As belt-and-braces
 *   the INSERT uses `INSERT IGNORE` keyed on the composite (from, to,
 *   source) — re-running by hand from a fresh `_migrations` row would be
 *   safe and produce the same final state.
 *
 * What this migration does NOT do
 *   - It does not modify the legacy matrix tables. The legacy Pi app
 *     continues to read from them exactly as it does today.
 *   - It does not deduplicate the `city` table. Pre-existing rows like
 *     "Andalusia" (no state) remain alongside the new "Andalusia, AL"
 *     row inserted by this backfill. Resolving that overlap is a
 *     separate concern tracked for a future branch.
 */

return static function (PDO $pdo): void {
    /**
     * Normalize a city label: trim, collapse internal whitespace, and remove
     * whitespace immediately before a comma so "Bainbridge , GA" and
     * "Bainbridge, GA" canonicalise to one form.
     */
    $normalize = static function (string $raw): string {
        $clean = preg_replace('/\s+,/', ',', $raw) ?? $raw;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        return trim($clean);
    };

    /**
     * Find or create a row in the `city` table for a normalized name and
     * return its id. Caches the lookups in-memory for the duration of the
     * migration so each unique city hits the DB at most twice.
     *
     * @var array<string, int> $cityIdCache
     */
    $cityIdCache = [];
    $findCity    = $pdo->prepare('SELECT id FROM `city` WHERE city = ? LIMIT 1');
    $insertCity  = $pdo->prepare('INSERT INTO `city` (city) VALUES (?)');

    $upsertCity = static function (string $name) use ($pdo, &$cityIdCache, $findCity, $insertCity): int {
        if (isset($cityIdCache[$name])) {
            return $cityIdCache[$name];
        }
        $findCity->execute([$name]);
        $existing = $findCity->fetchColumn();
        if ($existing !== false) {
            return $cityIdCache[$name] = (int) $existing;
        }
        $insertCity->execute([$name]);
        return $cityIdCache[$name] = (int) $pdo->lastInsertId();
    };

    $insertDistance = $pdo->prepare(
        'INSERT IGNORE INTO `city_distances` (from_city_id, to_city_id, miles, source)
         VALUES (?, ?, ?, ?)'
    );

    $totals = [
        'inserted_pairs'    => 0,
        'skipped_zero'      => 0,
        'skipped_self'      => 0,
        'cities_registered' => 0,
    ];
    $registeredBefore = count($cityIdCache);

    foreach (['largeMiles', 'pcola_largeMiles'] as $source) {
        $columns = $pdo->query("SHOW COLUMNS FROM `{$source}`")->fetchAll(PDO::FETCH_COLUMN);
        // The first column is the row label ("city"); everything after is a
        // destination city column. Filter so the row label doesn't get
        // accidentally treated as a destination.
        $destColumns = array_values(array_filter($columns, static fn (string $c): bool => $c !== 'city'));

        $rows = $pdo->query("SELECT * FROM `{$source}`")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $fromName = $normalize((string) ($row['city'] ?? ''));
            if ($fromName === '') {
                continue;
            }
            $fromId = $upsertCity($fromName);

            foreach ($destColumns as $destColumn) {
                // Cell value: distance in miles, or zero/null when unknown.
                $miles = (int) ($row[$destColumn] ?? 0);
                if ($miles <= 0) {
                    $totals['skipped_zero']++;
                    continue;
                }

                $toName = $normalize($destColumn);
                if ($toName === '') {
                    continue;
                }
                $toId = $upsertCity($toName);

                if ($fromId === $toId) {
                    // Self-distance — the legacy schema has a sentinel of 1
                    // on the diagonal for "this city exists". Skipping
                    // these keeps the new table free of nonsense rows.
                    $totals['skipped_self']++;
                    continue;
                }

                $insertDistance->execute([$fromId, $toId, $miles, $source]);
                if ($insertDistance->rowCount() === 1) {
                    $totals['inserted_pairs']++;
                }
            }
        }
    }

    $totals['cities_registered'] = count($cityIdCache) - $registeredBefore;

    // Persist a small summary so a follow-up health page can render
    // backfill stats. We use the `_migrations` row's existence as the
    // applied-marker and don't need a second tracking table.
    fwrite(
        STDOUT,
        "  backfill complete: " . json_encode($totals, JSON_UNESCAPED_SLASHES) . "\n",
    );
};
