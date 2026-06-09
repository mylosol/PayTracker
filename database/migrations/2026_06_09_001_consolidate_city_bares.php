<?php

declare(strict_types=1);

/*
 * 2026_06_09_001_consolidate_city_bares.php
 *
 * Cleanup of legacy "bare-name" city rows. Background:
 *
 *   - PayTracker 1.x stored cities as bare strings ("Bristol",
 *     "Andalusia", ...).
 *   - Migration 2026_05_23_003 added state-suffixed twins
 *     ("Bristol, FL", "Andalusia, AL", ...) but DELIBERATELY did
 *     not delete the bare rows, fearing unknown references.
 *
 * Audit (scripts/audit-city-bares.php run on 2026-06-09) showed:
 *
 *   - 45 bares have state-suffixed twins → consolidate.
 *   -  6 bares have no twin (Carrabelle, Cottonwood, Ft. Walton,
 *      Parker, Port St Joe, Wewa) → rename in place to add ", FL".
 *   -  1 malformed twin ("Cottondale, Fl, FL") → rename to
 *      "Cottondale, FL" before consolidating its bare.
 *
 * References to bare rows only ever appear in
 * driver_loads.{pickup_city, delivery_city, end_empty_city} (by
 * NAME). No terminals.* or city_distances rows touch a bare's id.
 *
 * Strategy:
 *
 *   1. Rename the malformed Cottondale twin first.
 *   2. For each (bare, twin) pair: rewrite any driver_loads name
 *      refs from bare → twin, then DELETE the bare row.
 *   3. For each orphan bare: rename in place to "<name>, FL". For
 *      Ft. Walton (the only orphan with driver_loads refs), also
 *      rewrite those refs.
 *
 * Defensive: every step checks the row still exists with the
 * expected shape before acting. If preview and production drift
 * (shared DB, but state can differ between migration runs), a
 * missing row is silently skipped rather than failing the
 * migration.
 *
 * Idempotency: re-running is a no-op once the bare rows are gone
 * and the orphans have been renamed.
 *
 * NOTE: This migration runs against the shared preview/production
 * database. Whichever workflow applies it first wins; the
 * migrator's tracking table prevents a second application.
 */

return static function (PDO $pdo): void {
    // Pairs of (bare_name, twin_name). Bare gets DELETED after its
    // refs are rewritten to point at the twin. Compiled from the
    // 2026-06-09 audit output; the migration verifies each pair
    // exists before acting.
    /** @var list<array{bare:string, twin:string}> $pairs */
    $pairs = [
        ['bare' => 'Andalusia',        'twin' => 'Andalusia, AL'],
        ['bare' => 'Apalachicola',     'twin' => 'Apalachicola, FL'],
        ['bare' => 'Bluewater',        'twin' => 'Bluewater, FL'],
        ['bare' => 'Bonifay',          'twin' => 'Bonifay, FL'],
        ['bare' => 'Bristol',          'twin' => 'Bristol, FL'],
        ['bare' => 'Callaway',         'twin' => 'Callaway, FL'],
        ['bare' => 'Chipley',          'twin' => 'Chipley, FL'],
        // Cottondale's twin is malformed ("Cottondale, Fl, FL");
        // we rename it to "Cottondale, FL" first in a separate
        // step below, then this pair consolidates as normal.
        ['bare' => 'Cottondale',       'twin' => 'Cottondale, FL'],
        ['bare' => 'Crawfordville',    'twin' => 'Crawfordville, FL'],
        ['bare' => 'Crestview',        'twin' => 'Crestview, FL'],
        ['bare' => 'Crystal Lake',     'twin' => 'Crystal Lake, FL'],
        ['bare' => 'DeFuniak Springs', 'twin' => 'DeFuniak Springs, FL'],
        ['bare' => 'Destin',           'twin' => 'Destin, FL'],
        ['bare' => 'Dothan',           'twin' => 'Dothan, AL'],
        ['bare' => 'Eastpoint',        'twin' => 'Eastpoint, FL'],
        ['bare' => 'Ebro',             'twin' => 'Ebro, FL'],
        ['bare' => 'Enterprise',       'twin' => 'Enterprise, AL'],
        ['bare' => 'Esto',             'twin' => 'Esto, FL'],
        ['bare' => 'Freeport',         'twin' => 'Freeport, FL'],
        ['bare' => 'Graceville',       'twin' => 'Graceville, FL'],
        ['bare' => 'Greensboro',       'twin' => 'Greensboro, FL'],
        ['bare' => 'Gulf Breeze',      'twin' => 'Gulf Breeze, FL'],
        ['bare' => 'Inlet Beach',      'twin' => 'Inlet Beach, FL'],
        ['bare' => 'Laurel Hill',      'twin' => 'Laurel Hill, FL'],
        ['bare' => 'Lynn Haven',       'twin' => 'Lynn Haven, FL'],
        ['bare' => 'Marianna',         'twin' => 'Marianna, FL'],
        ['bare' => 'Mary Esther',      'twin' => 'Mary Esther, FL'],
        ['bare' => 'Mexico Beach',     'twin' => 'Mexico Beach, FL'],
        ['bare' => 'Navarre',          'twin' => 'Navarre, FL'],
        ['bare' => 'New Hope',         'twin' => 'New Hope, FL'],
        ['bare' => 'Niceville',        'twin' => 'Niceville, FL'],
        ['bare' => 'Panama City',      'twin' => 'Panama City, FL'],
        ['bare' => 'Panama City Beach','twin' => 'Panama City Beach, FL'],
        ['bare' => 'Paxton',           'twin' => 'Paxton, FL'],
        ['bare' => 'Pensacola',        'twin' => 'Pensacola, FL'],
        ['bare' => 'Ponce De Leon',    'twin' => 'Ponce De Leon, FL'],
        ['bare' => 'San Destin',       'twin' => 'San Destin, FL'],
        ['bare' => 'Santa Rosa Beach', 'twin' => 'Santa Rosa Beach, FL'],
        ['bare' => 'Shallamar',        'twin' => 'Shallamar, FL'],
        ['bare' => 'Southport',        'twin' => 'Southport, FL'],
        ['bare' => 'Tallahassee',      'twin' => 'Tallahassee, FL'],
        ['bare' => 'Valparaiso',       'twin' => 'Valparaiso, FL'],
        ['bare' => 'Wausau',           'twin' => 'Wausau, FL'],
        ['bare' => 'Westbay',          'twin' => 'Westbay, FL'],
        ['bare' => 'Youngstown',       'twin' => 'Youngstown, FL'],
    ];

    // Orphan bares: no twin exists. Renamed in place to add the
    // state suffix. Ft. Walton is the only one with driver_loads
    // refs; the rename below rewrites those refs too.
    /** @var list<array{old:string, new:string}> $orphans */
    $orphans = [
        ['old' => 'Carrabelle',  'new' => 'Carrabelle, FL'],
        ['old' => 'Cottonwood',  'new' => 'Cottonwood, FL'],
        ['old' => 'Ft. Walton',  'new' => 'Ft. Walton, FL'],
        ['old' => 'Parker',      'new' => 'Parker, FL'],
        ['old' => 'Port St Joe', 'new' => 'Port St Joe, FL'],
        ['old' => 'Wewa',        'new' => 'Wewa, FL'],
    ];

    $cityIdByName = static function (PDO $pdo, string $name): ?int {
        $stmt = $pdo->prepare('SELECT id FROM `city` WHERE city = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? (int) $row['id'] : null;
    };

    $renameLoadsCity = static function (PDO $pdo, string $column, string $from, string $to): int {
        $sql  = "UPDATE `driver_loads` SET `{$column}` = ? WHERE `{$column}` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$to, $from]);
        return $stmt->rowCount();
    };

    $pdo->beginTransaction();
    try {
        // --- 0. Rename the malformed Cottondale twin -----------------
        // "Cottondale, Fl, FL" → "Cottondale, FL". Guarded by an
        // existence check so a re-run after a manual cleanup is a
        // no-op.
        $malformedId = $cityIdByName($pdo, 'Cottondale, Fl, FL');
        if ($malformedId !== null && $cityIdByName($pdo, 'Cottondale, FL') === null) {
            $upd = $pdo->prepare('UPDATE `city` SET city = ? WHERE id = ?');
            $upd->execute(['Cottondale, FL', $malformedId]);
        }

        // --- 1. Bare → twin consolidations ---------------------------
        foreach ($pairs as $p) {
            $bareId = $cityIdByName($pdo, $p['bare']);
            $twinId = $cityIdByName($pdo, $p['twin']);
            if ($bareId === null || $twinId === null) {
                // Either already consolidated, or the expected twin
                // is missing. Skip silently — defensive against
                // preview/production drift.
                continue;
            }
            // Rewrite name refs on driver_loads. Each column gets
            // its own UPDATE so rowCount() can report cleanly.
            $renameLoadsCity($pdo, 'pickup_city',    $p['bare'], $p['twin']);
            $renameLoadsCity($pdo, 'delivery_city',  $p['bare'], $p['twin']);
            $renameLoadsCity($pdo, 'end_empty_city', $p['bare'], $p['twin']);

            // city_distances FK refs — audit confirmed there are
            // none touching a bare id, but the safety net is cheap:
            // shift any straggler rows over to the twin's id. The
            // ON DUPLICATE KEY UPDATE pattern handles the rare case
            // where the twin already had the same (from, to,
            // source) tuple.
            $pdo->prepare(
                'UPDATE IGNORE `city_distances`
                    SET from_city_id = ?
                  WHERE from_city_id = ?'
            )->execute([$twinId, $bareId]);
            $pdo->prepare(
                'UPDATE IGNORE `city_distances`
                    SET to_city_id = ?
                  WHERE to_city_id = ?'
            )->execute([$twinId, $bareId]);
            // Any rows the UPDATE IGNORE refused (because they'd
            // collide with an existing row) are orphans from the
            // bare side; we delete them so the bare can be
            // removed cleanly.
            $pdo->prepare('DELETE FROM `city_distances` WHERE from_city_id = ? OR to_city_id = ?')
                ->execute([$bareId, $bareId]);

            // terminals.city_id FK — audit confirmed none, but
            // again, cheap safety net.
            $pdo->prepare('UPDATE `terminals` SET city_id = ? WHERE city_id = ?')
                ->execute([$twinId, $bareId]);

            // Finally, delete the bare.
            $pdo->prepare('DELETE FROM `city` WHERE id = ?')->execute([$bareId]);
        }

        // --- 2. Orphan bare renames ----------------------------------
        foreach ($orphans as $o) {
            $oldId = $cityIdByName($pdo, $o['old']);
            if ($oldId === null) {
                continue; // already renamed
            }
            // Guard against a collision with an existing row that
            // happens to share the new name. Safer to skip than
            // accidentally clobber.
            if ($cityIdByName($pdo, $o['new']) !== null) {
                continue;
            }
            // Rename the city row itself.
            $pdo->prepare('UPDATE `city` SET city = ? WHERE id = ?')
                ->execute([$o['new'], $oldId]);
            // Rewrite driver_loads name refs to match.
            $renameLoadsCity($pdo, 'pickup_city',    $o['old'], $o['new']);
            $renameLoadsCity($pdo, 'delivery_city',  $o['old'], $o['new']);
            $renameLoadsCity($pdo, 'end_empty_city', $o['old'], $o['new']);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
};
