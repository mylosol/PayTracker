<?php

declare(strict_types=1);

/*
 * scripts/qa-cleanup.php — undo test-data residue from a QA walkthrough.
 *
 * Two things accumulate during a normal QA pass:
 *
 *   1. "Qa Test ###, ST" rows in the `city` table from Section 6 of the
 *      test plan. The naming convention (documented in test_plan.md) makes
 *      these unambiguous, so we delete them by a strict LIKE pattern that
 *      cannot match legitimate city names.
 *
 *   2. Lockout counters on accounts that tested Section 5d — the legitimate
 *      tester is now staring at a 15-minute lockout. We can reset
 *      failed_login_count and locked_until safely because lockouts are
 *      ephemeral: a tester clearing their own counter is the documented
 *      recovery path.
 *
 * Default mode is DRY-RUN: it prints what would be deleted / reset but
 * changes nothing. Pass --apply to actually perform the writes. Production
 * is double-guarded: APP_ENV=production refuses unless --confirm-production
 * is also passed, matching the safety pattern already in set-password.php.
 *
 * Usage:
 *   php scripts/qa-cleanup.php                          # dry-run report
 *   php scripts/qa-cleanup.php --apply                  # apply on preview
 *   php scripts/qa-cleanup.php --apply --cities         # only QA cities
 *   php scripts/qa-cleanup.php --apply --unlock         # only lockouts
 *   php scripts/qa-cleanup.php --apply --confirm-production
 */

use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/qa-cleanup.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$flags             = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => str_starts_with($a, '--')));
$apply             = in_array('--apply', $flags, true);
$onlyCities        = in_array('--cities', $flags, true);
$onlyUnlock        = in_array('--unlock', $flags, true);
$confirmProduction = in_array('--confirm-production', $flags, true);

// If neither --cities nor --unlock is passed, do both. Mirrors the common
// "I just finished a QA pass, please tidy up" intent.
$doCities = $onlyCities || ! $onlyUnlock;
$doUnlock = $onlyUnlock || ! $onlyCities;

if (config('app.env') === 'production' && $apply && ! $confirmProduction) {
    fwrite(STDERR, "Refusing to run against APP_ENV=production without --confirm-production.\n");
    exit(2);
}

$mode = $apply ? 'APPLY' : 'DRY-RUN';
echo "qa-cleanup [{$mode}] env=" . (string) config('app.env') . "\n";

try {
    /** @var Connection $connection */
    $connection = $app->make(Connection::class);
    $pdo        = $connection->pdo();

    // --- 1. QA-test cities ----------------------------------------------
    if ($doCities) {
        // Strict pattern: starts with literal "Qa Test " followed by one or
        // more chars. Cannot match a real city.
        $find = $pdo->prepare("SELECT id, city FROM `city` WHERE city LIKE 'Qa Test %'");
        $find->execute();
        $rows = $find->fetchAll();

        if ($rows === []) {
            echo "  cities: no QA test rows found.\n";
        } else {
            echo "  cities: " . count($rows) . " QA test row(s) to remove:\n";
            foreach ($rows as $r) {
                printf("    - id=%d  city=%s\n", (int) $r['id'], (string) $r['city']);
            }
            if ($apply) {
                $del = $pdo->prepare("DELETE FROM `city` WHERE city LIKE 'Qa Test %'");
                $del->execute();
                echo "    → deleted.\n";
            }
        }
    }

    // --- 2. Account lockouts -------------------------------------------
    if ($doUnlock) {
        $find = $pdo->prepare(
            'SELECT id, user, failed_login_count, locked_until
             FROM `account`
             WHERE failed_login_count > 0 OR locked_until IS NOT NULL'
        );
        $find->execute();
        $rows = $find->fetchAll();

        if ($rows === []) {
            echo "  lockouts: no accounts have failed counters or locks set.\n";
        } else {
            echo "  lockouts: " . count($rows) . " account(s) to reset:\n";
            foreach ($rows as $r) {
                printf(
                    "    - id=%d  user=%s  failed=%d  locked_until=%s\n",
                    (int) $r['id'],
                    (string) $r['user'],
                    (int) $r['failed_login_count'],
                    (string) ($r['locked_until'] ?? 'NULL'),
                );
            }
            if ($apply) {
                $upd = $pdo->prepare(
                    'UPDATE `account`
                     SET failed_login_count = 0, locked_until = NULL
                     WHERE failed_login_count > 0 OR locked_until IS NOT NULL'
                );
                $upd->execute();
                echo "    → reset.\n";
            }
        }
    }

    if (! $apply) {
        echo "\n(dry-run — re-run with --apply to perform the changes above)\n";
    }
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf("Failed: %s\n", $e->getMessage()));
    exit(1);
}
