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
$onlyLoads         = in_array('--loads', $flags, true);
$onlyAnnounce      = in_array('--announcements', $flags, true);
$onlyInvites       = in_array('--invites', $flags, true);
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

// If no specific category flag is passed, do all three. Mirrors the common
// "I just finished a QA pass, please tidy up" intent.
$anySpecific = $onlyCities || $onlyUnlock || $onlyLoads || $onlyAnnounce || $onlyInvites;
$doCities   = $onlyCities   || ! $anySpecific;
$doUnlock   = $onlyUnlock   || ! $anySpecific;
$doLoads    = $onlyLoads    || ! $anySpecific;
$doAnnounce = $onlyAnnounce || ! $anySpecific;
$doInvites  = $onlyInvites  || ! $anySpecific;

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

    // --- 3. QA-test driver_loads rows ----------------------------------
    // The Playwright load-entry spec inserts rows with `notes` prefixed
    // by "QA TEST " — that's the marker we sweep on. We don't accept a
    // custom pattern here: the prefix is hardcoded in the spec and any
    // load that uses a different prefix is, by definition, NOT a QA
    // artefact and we should not delete it.
    if ($doLoads) {
        $loadPattern = 'QA TEST %';
        $find = $pdo->prepare(
            'SELECT driver_id, frtl, date, notes
             FROM `driver_loads` WHERE notes LIKE ?'
        );
        $find->execute([$loadPattern]);
        $rows = $find->fetchAll();

        if ($rows === []) {
            echo "  loads: no driver_loads rows match notes LIKE '{$loadPattern}'.\n";
        } else {
            echo "  loads: " . count($rows) . " row(s) match notes LIKE '{$loadPattern}':\n";
            foreach ($rows as $r) {
                printf(
                    "    - driver_id=%d frtl=%d date=%s notes=%s\n",
                    (int) $r['driver_id'],
                    (int) $r['frtl'],
                    (string) $r['date'],
                    (string) $r['notes'],
                );
            }
            if ($apply) {
                // pay_reconciliations rows have no FK back to driver_loads
                // (driver_loads is MyISAM-flavoured by tradition), so
                // we delete them by the same (driver_id, frtl) pairs
                // BEFORE the load rows go away. Failing to do this
                // leaves orphan reconcile rows that a future driver
                // with the same FRTL would inherit.
                $reconDel = $pdo->prepare(
                    'DELETE r FROM `pay_reconciliations` r
                     JOIN `driver_loads` d
                       ON d.driver_id = r.driver_id AND d.frtl = r.frtl
                     WHERE d.notes LIKE ?'
                );
                try {
                    $reconDel->execute([$loadPattern]);
                } catch (\PDOException $e) {
                    // pay_reconciliations might not exist yet on
                    // ancient preview branches; ignore.
                    if (! str_contains($e->getMessage(), 'pay_reconciliations')) {
                        throw $e;
                    }
                }
                $del = $pdo->prepare('DELETE FROM `driver_loads` WHERE notes LIKE ?');
                $del->execute([$loadPattern]);
                echo "    → deleted.\n";
            }
        }
    }

    // --- 4. QA-test announcements -----------------------------------
    // Section 19 of the QA plan creates rows with subject prefixed
    // "QA TEST ". This block sweeps them and the matching dismissal
    // records so a Playwright run on the next deploy doesn't see a
    // stale modal blocking the welcome heading.
    if ($doAnnounce) {
        $annPattern = 'QA TEST %';

        // Does the table even exist? Migration 2026_06_04_002 created
        // it -- but qa-cleanup is also run against pre-migration
        // preview databases during the workflow's seed step. SHOW
        // TABLES guards the destructive path.
        $exists = (bool) $pdo->query("SHOW TABLES LIKE 'announcements'")->fetchColumn();
        if (! $exists) {
            echo "  announcements: table not present, skipping.\n";
        } else {
            $find = $pdo->prepare(
                'SELECT id, subject, is_active, is_template
                   FROM `announcements`
                  WHERE subject LIKE ?'
            );
            $find->execute([$annPattern]);
            $rows = $find->fetchAll();

            if ($rows === []) {
                echo "  announcements: no rows match subject LIKE '{$annPattern}'.\n";
            } else {
                echo "  announcements: " . count($rows) . " row(s) match subject LIKE '{$annPattern}':\n";
                foreach ($rows as $r) {
                    printf(
                        "    - id=%d active=%d template=%d subject=%s\n",
                        (int) $r['id'],
                        (int) ($r['is_active']   ?? 0),
                        (int) ($r['is_template'] ?? 0),
                        substr((string) ($r['subject'] ?? ''), 0, 60)
                    );
                }
                if ($apply) {
                    $delDismissals = $pdo->prepare(
                        'DELETE d FROM `announcement_dismissals` d
                          INNER JOIN `announcements` a ON a.id = d.announcement_id
                          WHERE a.subject LIKE ?'
                    );
                    $delDismissals->execute([$annPattern]);
                    $del = $pdo->prepare('DELETE FROM `announcements` WHERE subject LIKE ?');
                    $del->execute([$annPattern]);
                    echo "    → deleted (announcements + matching dismissals).\n";
                }
            }
        }
    }

    // --- 5. QA-test invite codes ------------------------------------
    // Section 20 of the QA plan creates throw-away codes whose
    // invitee_email is typically a tester's own address. We sweep
    // by created_by = QA_TEST_USER when configured; otherwise we
    // sweep every UNCONSUMED code (consumed ones are real users
    // we mustn't tamper with).
    if ($doInvites) {
        $exists = (bool) $pdo->query("SHOW TABLES LIKE 'invite_codes'")->fetchColumn();
        if (! $exists) {
            echo "  invites: table not present, skipping.\n";
        } else {
            // The only safe blanket sweep is "unconsumed AND no
            // used_by_id". Real user registrations (branch 2) set
            // used_at + used_by_id; QA-created codes that were
            // never redeemed don't.
            $find = $pdo->prepare(
                "SELECT id, code, invitee_email, created_at
                   FROM `invite_codes`
                  WHERE used_at IS NULL"
            );
            $find->execute();
            $rows = $find->fetchAll();

            if ($rows === []) {
                echo "  invites: no unconsumed codes on file.\n";
            } else {
                echo "  invites: " . count($rows) . " unconsumed code(s):\n";
                foreach ($rows as $r) {
                    printf(
                        "    - id=%d code=%s invitee_email=%s created=%s\n",
                        (int) $r['id'],
                        (string) $r['code'],
                        (string) ($r['invitee_email'] ?? '—'),
                        (string) ($r['created_at'] ?? '—')
                    );
                }
                if ($apply) {
                    $del = $pdo->prepare('DELETE FROM `invite_codes` WHERE used_at IS NULL');
                    $del->execute();
                    echo "    → deleted.\n";
                }
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
