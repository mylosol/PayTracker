<?php

declare(strict_types=1);

/*
 * scripts/qa-cleanup.php — undo test-data residue from a QA walkthrough.
 *
 * Two things accumulate during a normal QA pass:
 *
 *   1. "Qa Test ###, ST" rows in the `city` table from Section 6 of the
 *      test plan. The naming convention (documented in test_plan.md) makes
 *      these unambiguous, so the default delete pattern cannot match
 *      legitimate city names.
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
 * Custom patterns: a tester who forgot the "Qa Test " convention can pass
 * --pattern='Their Prefix%' (or any SQL LIKE pattern) to clean rows they
 * named differently. The pattern is bound as a parameter (no SQL injection
 * risk) and is rejected if it would match overly-broadly (must contain at
 * least 3 non-wildcard chars). The default pattern is "Qa Test %" — that
 * still applies when --pattern is NOT passed.
 *
 * Usage:
 *   php scripts/qa-cleanup.php                              # dry-run report
 *   php scripts/qa-cleanup.php --apply                      # apply on preview
 *   php scripts/qa-cleanup.php --apply --cities             # only cities
 *   php scripts/qa-cleanup.php --apply --unlock             # only lockouts
 *   php scripts/qa-cleanup.php --apply --pattern='Test City%'  # custom prefix
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

$customPattern = null;
foreach ($flags as $f) {
    if (str_starts_with($f, '--pattern=')) {
        $customPattern = substr($f, strlen('--pattern='));
        break;
    }
}

// Reject patterns that would sweep too broadly. The check is intentionally
// strict: at least 3 literal (non-wildcard) characters must remain after
// stripping `%` and `_`. This makes `%`, `_`, `%a%`, and `%T%` all refused
// while still allowing reasonable prefixes like `Test City%` or `Foo Bar%`.
if ($customPattern !== null) {
    $literal = (string) preg_replace('/[%_]/', '', $customPattern);
    if (strlen($literal) < 3) {
        fwrite(STDERR, "Refusing --pattern='{$customPattern}': must contain at least 3 non-wildcard characters.\n");
        exit(3);
    }
}

$pattern = $customPattern ?? 'Qa Test %';

// If neither --cities nor --unlock is passed, do both. Mirrors the common
// "I just finished a QA pass, please tidy up" intent.
$doCities = $onlyCities || ! $onlyUnlock;
$doUnlock = $onlyUnlock || ! $onlyCities;

if (config('app.env') === 'production' && $apply && ! $confirmProduction) {
    fwrite(STDERR, "Refusing to run against APP_ENV=production without --confirm-production.\n");
    exit(2);
}

$mode = $apply ? 'APPLY' : 'DRY-RUN';
echo "qa-cleanup [{$mode}] env=" . (string) config('app.env') . " pattern='{$pattern}'\n";

try {
    /** @var Connection $connection */
    $connection = $app->make(Connection::class);
    $pdo        = $connection->pdo();

    // --- 1. QA-test cities ----------------------------------------------
    if ($doCities) {
        $find = $pdo->prepare('SELECT id, city FROM `city` WHERE city LIKE ?');
        $find->execute([$pattern]);
        $rows = $find->fetchAll();

        if ($rows === []) {
            echo "  cities: no rows match '{$pattern}'.\n";
        } else {
            echo "  cities: " . count($rows) . " row(s) match '{$pattern}':\n";
            foreach ($rows as $r) {
                printf("    - id=%d  city=%s\n", (int) $r['id'], (string) $r['city']);
            }
            if ($apply) {
                $del = $pdo->prepare('DELETE FROM `city` WHERE city LIKE ?');
                $del->execute([$pattern]);
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
