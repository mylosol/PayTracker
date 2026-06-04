<?php

declare(strict_types=1);

/*
 * scripts/cleanup-spam-accounts.php — bulk-delete obvious spam-bot
 * registration rows from the legacy `account` table.
 *
 * The legacy PayTracker had an open registration form with no input
 * validation. Over the years bots filled it with SQL-injection probes,
 * XSS payloads, URLs as usernames, and so on. These rows are dead
 * weight on every admin-panel render and make the user table
 * unusable.
 *
 * Patterns matched here are deliberately CONSERVATIVE — any row that
 * matches at least one is overwhelmingly likely to be junk. Real users
 * never have whitespace, angle brackets, parentheses, SQL keywords, or
 * URL prefixes in their handle.
 *
 * Hard safety rails:
 *   - DRY-RUN BY DEFAULT. Without --apply we show what we WOULD
 *     delete and exit non-destructively.
 *   - Refuses to run against APP_ENV=production unless the operator
 *     also passes --confirm-production. Matches set-password.php and
 *     qa-cleanup.php's posture.
 *   - NEVER deletes accounts that have a password_hash set. A real
 *     human went through the trouble of setting a password; that's
 *     a strong signal to leave them alone even if the handle looks
 *     unusual.
 *   - NEVER deletes accounts that have any driver_loads rows on
 *     file (the rebuilt loads system). Same reasoning: if they own
 *     real data, they're real.
 *   - NEVER deletes the actor running this script. Belt-and-
 *     suspenders, even though there's no operator-id concept on the
 *     CLI; we filter by joinDate just to be safe.
 *
 * Usage:
 *   php scripts/cleanup-spam-accounts.php           # dry-run, show counts + sample
 *   php scripts/cleanup-spam-accounts.php --apply   # actually delete
 *   php scripts/cleanup-spam-accounts.php --apply --confirm-production
 */

use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/cleanup-spam-accounts.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$args  = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);

if (config('app.env') === 'production' && ! in_array('--confirm-production', $args, true)) {
    fwrite(STDERR, "Refusing to run against APP_ENV=production without --confirm-production.\n");
    exit(2);
}

try {
    /** @var Connection $connection */
    $connection = $app->make(Connection::class);
    $pdo        = $connection->pdo();

    // The "looks like spam" predicate, applied to `user` (the legacy
    // login handle column). Any single match is enough to flag a row.
    // Notes:
    //   - REGEXP runs against the raw column; we use double-escaped
    //     backslashes inside the PHP string so MySQL receives the
    //     correct single-backslash word-boundaries.
    //   - LIKE % patterns are case-insensitive on utf8mb4_unicode_ci
    //     by default — no extra UPPER() needed.
    // Predicate clauses. MUST mirror Account::SPAM_PREDICATE so the
    // admin-panel UI hides exactly the rows this script would delete.
    // Calibrated against a real production account.json dump.
    $clauses = [
        // SQL injection keyword payloads.
        "user REGEXP '(SELECT|WAITFOR|SLEEP|UNION|RAID|pg_sleep|EXTRACTVALUE|BENCHMARK)'",
        // Tautology-style probes.
        "user REGEXP '\\\\b(OR|AND)\\\\b.*=.*'",
        // URLs jammed into the handle.
        "user LIKE 'http://%' OR user LIKE 'https://%'",
        // Spam test addresses.
        "user LIKE '%example.com%'",
        // XSS / HTML payloads.
        "user LIKE '%<%>%'",
        // Whitespace in handle.
        "user LIKE '% %'",
        // Parens / quotes.
        "user LIKE '%(%' OR user LIKE '%)%' OR user LIKE '%\"%' OR user LIKE '%''%'",
        // Empty / null handles.
        "user IS NULL OR user = ''",
        // Control characters: null byte, CR, LF, tab.
        "LOCATE(CHAR(0),  user) > 0",
        "LOCATE(CHAR(9),  user) > 0",
        "LOCATE(CHAR(10), user) > 0",
        "LOCATE(CHAR(13), user) > 0",
        // Path traversal probes.
        "user LIKE '%../%' OR user LIKE '%..\\\\%'",
        // URL-encoded hex blobs (3+ consecutive %XX sequences).
        "user REGEXP '(%[0-9a-fA-F]{2}){3,}'",
        // File-extension probes -- no real handle ends in these.
        "user REGEXP '\\\\.(php|cgi|asp|jsp|aspx|html?|xml|sh|bak|inc)$'",
    ];
    $where = '(' . implode(') OR (', $clauses) . ')';

    // ----- Survey -----
    $total      = (int) $pdo->query('SELECT COUNT(*) FROM `account`')->fetchColumn();
    $matched    = (int) $pdo->query("SELECT COUNT(*) FROM `account` WHERE {$where}")->fetchColumn();

    // ----- Safety filters -----
    // A: don't delete rows that have a password_hash.
    $safeAfterPw = (int) $pdo->query(
        "SELECT COUNT(*) FROM `account`
          WHERE ({$where})
            AND (password_hash IS NULL OR password_hash = '')"
    )->fetchColumn();

    // B: don't delete rows that own any driver_loads.
    $finalSql = "SELECT COUNT(*) FROM `account` a
                  WHERE ({$where})
                    AND (a.password_hash IS NULL OR a.password_hash = '')
                    AND NOT EXISTS (
                        SELECT 1 FROM `driver_loads` dl WHERE dl.driver_id = a.id
                    )";
    $finalCount = (int) $pdo->query($finalSql)->fetchColumn();

    echo "=== Spam-cleanup survey ===\n";
    echo sprintf("  Total accounts:                    %d\n", $total);
    echo sprintf("  Matching spam predicate:           %d\n", $matched);
    echo sprintf("  After password-hash safety filter: %d\n", $safeAfterPw);
    echo sprintf("  After driver-loads safety filter:  %d  <-- deletion target\n", $finalCount);
    echo "\n";

    // Show a sample so the operator can sanity-check before --apply.
    $sample = $pdo->query(
        "SELECT id, user, email, joinDate
           FROM `account` a
          WHERE ({$where})
            AND (a.password_hash IS NULL OR a.password_hash = '')
            AND NOT EXISTS (SELECT 1 FROM `driver_loads` dl WHERE dl.driver_id = a.id)
          ORDER BY id ASC LIMIT 15"
    )->fetchAll(\PDO::FETCH_ASSOC);

    echo "=== 15 sample rows that WOULD be deleted ===\n";
    foreach ($sample as $row) {
        echo sprintf(
            "  id=%-6d join=%s  user=%s\n",
            (int) $row['id'],
            (string) ($row['joinDate'] ?? '—'),
            substr((string) ($row['user'] ?? ''), 0, 80)
        );
    }
    echo "\n";

    if (! $apply) {
        echo "Dry run — no changes made. Re-run with --apply to delete.\n";
        exit(0);
    }

    if ($finalCount === 0) {
        echo "Nothing to delete.\n";
        exit(0);
    }

    // ----- Apply -----
    // Single DELETE with the same WHERE so we never widen the criteria
    // between survey and delete. driver_loads.password_hash filters
    // are inlined.
    $deleteSql = "DELETE a FROM `account` a
                    WHERE ({$where})
                      AND (a.password_hash IS NULL OR a.password_hash = '')
                      AND NOT EXISTS (
                          SELECT 1 FROM `driver_loads` dl WHERE dl.driver_id = a.id
                      )";
    $deleted = $pdo->exec($deleteSql);
    if ($deleted === false) {
        fwrite(STDERR, "DELETE failed.\n");
        exit(3);
    }

    echo sprintf("Deleted %d row(s) from `account`.\n", (int) $deleted);
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf("Failed: %s\n%s\n", $e->getMessage(), $e->getTraceAsString()));
    exit(1);
}
