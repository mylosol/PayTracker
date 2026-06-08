<?php

declare(strict_types=1);

/*
 * 2026_06_08_002_backfill_terminals.php
 *
 * Backfill the new `terminals` table from the legacy union of `terminal`
 * + `pcola_terminal`. Both legacy tables share the schema
 *   (id INT, terminal VARCHAR)
 * and the two-app split is no longer meaningful (see 2026_06_08_001).
 *
 * Strategy:
 *   - SELECT DISTINCT terminal FROM each legacy table (skipping the
 *     table entirely if it doesn't exist on this host — clean dev DBs
 *     may not have either).
 *   - Resolve city_id with a name match against `city.city` — same case-
 *     insensitive collation as the rest of the app so "Panama City, FL"
 *     and "panama city, fl" land on the same row.
 *   - INSERT ... ON DUPLICATE KEY UPDATE keeps the migration idempotent:
 *     re-running re-resolves city_id (so adding the city later picks up
 *     the link) without clobbering admin-edited names.
 *
 * What this migration does NOT do:
 *   - It does not modify or drop the legacy `terminal` / `pcola_terminal`
 *     tables. They stay live as a fallback during the soak period;
 *     a follow-up migration will drop them once the modern surface is
 *     proven in production.
 *   - It does not flip the `active` flag for any existing terminals row.
 *     A re-run after an admin deactivated a row keeps that decision.
 */

return static function (PDO $pdo): void {
    // ---- Gather distinct names from both legacy tables. ----
    $names = [];
    foreach (['terminal', 'pcola_terminal'] as $table) {
        $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        if ($exists === false || $exists->fetchColumn() === false) {
            continue;
        }

        $rows = $pdo->query(
            'SELECT DISTINCT terminal
               FROM `' . $table . '`
              WHERE terminal IS NOT NULL AND terminal <> ""'
        );
        if ($rows === false) {
            continue;
        }
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim((string) ($row['terminal'] ?? ''));
            if ($name !== '') {
                $names[$name] = true; // dedupe via array key
            }
        }
    }

    if ($names === []) {
        return; // Nothing to backfill (probably a clean dev DB).
    }

    // ---- Pre-build a name → city_id map (case-insensitive on MySQL's
    // default utf8mb3_general_ci collation, so "panama city, fl" and
    // "Panama City, FL" both hit the same row). ----
    $cityIdByLower = [];
    $cityRows = $pdo->query('SELECT id, city FROM `city`');
    if ($cityRows !== false) {
        foreach ($cityRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cityName = trim((string) ($row['city'] ?? ''));
            if ($cityName !== '') {
                // Lower-case key prevents two cities differing only in
                // case from colliding; that's a legacy data-quality
                // problem we don't try to solve here.
                $cityIdByLower[strtolower($cityName)] = (int) $row['id'];
            }
        }
    }

    // ---- Upsert each distinct name into `terminals`. ----
    //
    // ON DUPLICATE KEY UPDATE intentionally:
    //   - Re-resolves city_id (so adding a missing city to `city` later
    //     and re-running this migration backfills the FK).
    //   - Does NOT touch `active` (admin deactivations are sticky).
    //   - Does NOT touch `name` (preserves an admin rename).
    $upsert = $pdo->prepare(
        'INSERT INTO `terminals` (name, city_id, active)
              VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE
              city_id = COALESCE(VALUES(city_id), city_id),
              updated_at = NOW()'
    );

    foreach (array_keys($names) as $name) {
        $cityId = $cityIdByLower[strtolower($name)] ?? null;
        $upsert->execute([$name, $cityId]);
    }
};
