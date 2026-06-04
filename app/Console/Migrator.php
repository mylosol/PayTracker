<?php

declare(strict_types=1);

namespace PayTracker\Console;

use PayTracker\Database\Connection;
use PDO;
use RuntimeException;

/**
 * Minimal forward-only database migrator.
 *
 * Migrations live as files under `database/migrations/`. The lexicographic
 * filename ordering IS the apply order — name them with a date or zero-padded
 * sequence prefix (e.g. `2026_05_23_001_extend_account.sql`).
 *
 * Two file formats are supported:
 *   - `.sql` — one or more semicolon-terminated statements, executed in
 *     order against the live connection.
 *   - `.php` — must `return` a callable that receives a PDO instance:
 *         `<?php return static function (PDO $pdo): void { ... };`
 *     Use this only when SQL alone is not enough (e.g. backfilling data
 *     with code).
 *
 * Tracking: a `_migrations` table records `name` + `applied_at` for every
 * migration that ran successfully. Migrations are forward-only — there is no
 * `down()` and no automatic rollback. The legacy schema mixes MyISAM (no
 * transactions) with InnoDB (transactional), so a generic "wrap each
 * migration in BEGIN/COMMIT" would silently no-op on the MyISAM tables. To
 * keep behaviour predictable we instead enforce two rules at the source:
 *     1. Every migration MUST be additive only. No DROP / RENAME without an
 *        accompanying data-preservation plan documented in the commit body.
 *     2. Every migration MUST be idempotent at the statement level when
 *        practical (e.g. `ADD COLUMN IF NOT EXISTS`). Partial application is
 *        then recoverable by re-running.
 */
final class Migrator
{
    private const TRACKING_TABLE = '_migrations';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationsDir,
    ) {
    }

    /**
     * Apply every pending migration in lexicographic order and return the
     * list of names that were applied (empty list = already up to date).
     *
     * @return list<string>
     */
    public function migrate(bool $dryRun = false): array
    {
        $this->ensureTrackingTable();

        $applied = $this->appliedNames();
        $pending = array_values(array_filter(
            $this->discoverMigrations(),
            static fn (string $name): bool => ! in_array($name, $applied, true),
        ));

        $ran = [];
        foreach ($pending as $name) {
            if ($dryRun) {
                $ran[] = $name . ' (dry-run)';
                continue;
            }
            $this->runOne($name);
            $ran[] = $name;
        }
        return $ran;
    }

    /** @return list<string> */
    private function discoverMigrations(): array
    {
        if (! is_dir($this->migrationsDir)) {
            return [];
        }
        $files = glob($this->migrationsDir . DIRECTORY_SEPARATOR . '*.{sql,php}', GLOB_BRACE) ?: [];
        $names = array_map('basename', $files);
        sort($names, SORT_STRING);
        return $names;
    }

    /** @return list<string> */
    private function appliedNames(): array
    {
        $stmt = $this->connection->pdo()->query('SELECT name FROM ' . self::TRACKING_TABLE . ' ORDER BY name ASC');
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return is_array($rows) ? array_values(array_map('strval', $rows)) : [];
    }

    private function ensureTrackingTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::TRACKING_TABLE . ' (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `applied_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_migrations_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->connection->pdo()->exec($sql);
    }

    private function runOne(string $name): void
    {
        $path = $this->migrationsDir . DIRECTORY_SEPARATOR . $name;
        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Migration file not found: %s', $path));
        }

        $pdo = $this->connection->pdo();
        if (str_ends_with($name, '.sql')) {
            $sql = (string) file_get_contents($path);
            // PDO::exec runs multiple statements when emulation is off only if
            // the driver supports it (mysqlnd does). Split on semicolons that
            // end a line so each statement is sent individually — more
            // portable and gives clearer error messages on failure.
            foreach (self::splitStatements($sql) as $statement) {
                $pdo->exec($statement);
            }
        } elseif (str_ends_with($name, '.php')) {
            /** @psalm-suppress UnresolvableInclude */
            $callable = require $path;
            if (! is_callable($callable)) {
                throw new RuntimeException(sprintf('PHP migration %s must return a callable.', $name));
            }
            $callable($pdo);
        } else {
            throw new RuntimeException(sprintf('Unsupported migration extension: %s', $name));
        }

        $insert = $pdo->prepare('INSERT INTO ' . self::TRACKING_TABLE . ' (`name`, `applied_at`) VALUES (:name, :applied_at)');
        $insert->execute([
            'name'       => $name,
            'applied_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Split a SQL blob into individual statements. Naive splitter — strips
     * line comments and breaks on `;` followed by end-of-line. Sufficient for
     * the migration style this project uses (no stored procedures, no DELIMITER
     * tricks). Anything fancier should be a `.php` migration that issues its
     * own `$pdo->exec()` calls.
     *
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        // Strip `-- ...` line comments.
        $clean = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*[\r\n]+/', $clean) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
    }
}
