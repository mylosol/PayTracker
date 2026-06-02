<?php

declare(strict_types=1);

/*
 * 2026_05_29_002_backfill_pay_variables.php
 *
 * Backfills `pay_variables` from the legacy variablesDefault and
 * variablesCurrent tables.
 *
 * Drift sample from preview (run before this migration shipped):
 *   raise       default=0.15       current=0.08675
 *   6_mt        default=0.3951     current=0.4149
 *   12_mt       default=0.3951     current=0.4149
 *   24_mt       default=0.3951     current=0.4149
 *   60_mt       default=0.4069     current=0.4272
 *   108_mt      default=0.4322     current=0.4538
 *   168_mt      default=0.4351     current=0.4962
 *   max_mt      default=0.4481     current=0.4962
 *   (newBump, night, wk, demurrage, breakdown, trainer_pay all match)
 *
 * Idempotent via INSERT IGNORE on the composite (stage, variable) PK.
 * The 'draft' stage is NOT seeded — drafts are user-created.
 *
 * This migration does not touch the legacy tables. The Pi keeps reading
 * variablesCurrent exactly as it does today.
 */

return static function (PDO $pdo): void {
    $exists = static function (string $table) use ($pdo): bool {
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        return $stmt !== false && $stmt->fetchColumn() !== false;
    };

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO `pay_variables` (stage, variable, amount)
         VALUES (?, ?, ?)'
    );

    $copy = static function (string $legacyTable, string $stage) use ($pdo, $insert, $exists): int {
        if (! $exists($legacyTable)) {
            fwrite(STDOUT, "  skip {$legacyTable} (absent on this host)\n");
            return 0;
        }
        $inserted = 0;
        $rows = $pdo->query("SELECT variable, amount FROM `{$legacyTable}`");
        foreach ($rows as $r) {
            $name   = (string) $r['variable'];
            $amount = (string) $r['amount'];
            if ($name === '') {
                continue;
            }
            $insert->execute([$stage, $name, $amount]);
            if ($insert->rowCount() === 1) {
                $inserted++;
            }
        }
        return $inserted;
    };

    $totals = [
        'default' => $copy('variablesDefault', 'default'),
        'current' => $copy('variablesCurrent', 'current'),
    ];

    fwrite(
        STDOUT,
        "  backfill complete: " . json_encode($totals, JSON_UNESCAPED_SLASHES) . "\n",
    );
};
