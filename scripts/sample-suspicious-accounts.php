<?php

declare(strict_types=1);

/*
 * scripts/sample-suspicious-accounts.php — read-only audit of the
 * `account` table to identify suspicious rows.
 *
 * Why this exists: the admin panel surfaces every account_row directly,
 * which on the legacy DB includes years of bot-registration garbage --
 * SQL-injection probes used as usernames, spam-bot URLs, XSS payloads,
 * etc. Before we decide whether to filter the admin UI vs. bulk-delete
 * the rows, we want to SEE what's in there and how many there are.
 *
 * Usage (preview or production):
 *   ssh ...
 *   cd /home/robshe48/paytracker[/preview]
 *   php scripts/sample-suspicious-accounts.php
 *
 * Strictly read-only. Does NOT write or delete anything. Print to stdout
 * only.
 */

use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/sample-suspicious-accounts.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

try {
    /** @var Connection $connection */
    $connection = $app->make(Connection::class);
    $pdo        = $connection->pdo();

    // The patterns we'll classify as "suspicious". These are the exact
    // signatures visible in the QA screenshot:
    //   - SQL injection: SELECT, WAITFOR, SLEEP, UNION, OR 1=1
    //   - URLs as usernames: http(s)://
    //   - XSS payloads: <script, <img, <svg
    //   - Random whitespace / control characters
    //   - Specific spam patterns: example.com
    //
    // We rely on MySQL's REGEXP / LIKE — no JSON or fancy stuff.
    // Per-label clauses. Same set as Account::SPAM_PREDICATE and
    // cleanup-spam-accounts.php; broken out per-label here so the
    // survey can tell the operator which patterns are driving the
    // total. Calibrated against a real production account.json dump.
    $patterns = [
        'sql_injection'       => "user REGEXP '(SELECT|WAITFOR|SLEEP|UNION|RAID|pg_sleep|EXTRACTVALUE|BENCHMARK)'",
        'sql_or_payload'      => "user REGEXP '\\\\bOR\\\\b.*=' OR user REGEXP '\\\\bAND\\\\b.*='",
        'url_in_handle'       => "user LIKE 'http://%' OR user LIKE 'https://%'",
        'example_com'         => "user LIKE '%example.com%'",
        'angle_bracket_xss'   => "user LIKE '%<%>%'",
        'has_whitespace'      => "user LIKE '% %'",
        'has_parentheses'     => "user LIKE '%(%' OR user LIKE '%)%'",
        'has_quotes'          => "user LIKE '%''%' OR user LIKE '%\"%'",
        'empty_or_null'       => "user IS NULL OR user = ''",
        'has_null_byte'       => "LOCATE(CHAR(0), user) > 0",
        'has_cr_or_lf'        => "LOCATE(CHAR(10), user) > 0 OR LOCATE(CHAR(13), user) > 0",
        'has_tab'             => "LOCATE(CHAR(9), user) > 0",
        'path_traversal'      => "user LIKE '%../%' OR user LIKE '%..\\\\%'",
        'url_encoded_blob'    => "user REGEXP '(%[0-9a-fA-F]{2}){3,}'",
        'file_extension'      => "user REGEXP '\\\\.(php|cgi|asp|jsp|aspx|html?|xml|sh|bak|inc)$'",
    ];

    echo "=== `account` summary ===\n";
    $total = (int) $pdo->query('SELECT COUNT(*) FROM `account`')->fetchColumn();
    echo sprintf("Total rows: %d\n\n", $total);

    echo "=== Suspicious-pattern counts ===\n";
    foreach ($patterns as $label => $where) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM `account` WHERE {$where}")->fetchColumn();
        echo sprintf("  %-22s %6d\n", $label, $n);
    }
    echo "\n";

    // Sample 20 rows that look obviously bogus (any of the above).
    $unionWhere = '(' . implode(') OR (', array_values($patterns)) . ')';
    echo "=== 20 sample suspicious rows ===\n";
    $sql = "SELECT id, user, email, role,
                   CASE WHEN password_hash IS NOT NULL AND password_hash != '' THEN 'yes' ELSE 'no' END AS has_pw,
                   joinDate, last_login_at
              FROM `account`
             WHERE {$unionWhere}
             ORDER BY id ASC
             LIMIT 20";
    $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo sprintf(
            "  id=%-6d role=%-12s has_pw=%-3s join=%s  user=%s\n",
            (int) $row['id'],
            (string) ($row['role'] ?? '—'),
            (string) ($row['has_pw'] ?? '—'),
            (string) ($row['joinDate'] ?? '—'),
            // Truncate so the line stays readable.
            substr((string) ($row['user'] ?? ''), 0, 80)
        );
    }
    echo "\n";

    // And 5 rows that look LEGITIMATE so we can verify the heuristic
    // isn't going to suppress real users.
    echo "=== 5 sample legitimate-looking rows (handle is simple OR has a real email) ===\n";
    $sql = "SELECT id, user, email, role, last_login_at
              FROM `account`
             WHERE NOT ({$unionWhere})
               AND (email IS NOT NULL OR user REGEXP '^[A-Za-z0-9._+-]+@?[A-Za-z0-9.-]*$')
             ORDER BY (last_login_at IS NULL) ASC, last_login_at DESC, id DESC
             LIMIT 5";
    $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo sprintf(
            "  id=%-6d role=%-12s last_login=%s  user=%-40s email=%s\n",
            (int) $row['id'],
            (string) ($row['role'] ?? '—'),
            (string) ($row['last_login_at'] ?? 'never'),
            substr((string) ($row['user'] ?? ''), 0, 40),
            (string) ($row['email'] ?? '—')
        );
    }
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf("Failed: %s\n%s\n", $e->getMessage(), $e->getTraceAsString()));
    exit(1);
}
