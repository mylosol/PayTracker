<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;

/**
 * `terminal` and `pcola_terminal` — the small set of dispatch hubs that a
 * driver is allowed to PICK UP from. These are a separate legacy concept
 * from the general `city` list: terminals are where the dispatcher fuels
 * the truck, cities are where loads get delivered.
 *
 * Legacy schema (both tables identical):
 *   id        INT
 *   terminal  VARCHAR    — the city name (e.g. "Panama City, FL")
 *
 * The two tables historically scoped "which terminals does THIS driver
 * see": Pensacola drivers got `pcola_terminal`, non-Pcola drivers got
 * `terminal`. Pre-modern, that scoping was cookie-driven.
 *
 * For now the modern picker unions both tables and shows every terminal
 * to every driver — the modern preview has no per-driver pcola flag and
 * the QA account is admin. When per-driver scoping lands later,
 * `allForDriver(int $driverId)` is the obvious extension point.
 */
final class Terminal extends Model
{
    protected static string $table = 'terminal';

    /**
     * Deduplicated, alphabetised list of terminal city names. Unions
     * `terminal` and `pcola_terminal`. Tables that don't exist on this
     * host (clean dev DBs, future schema cleanups) are skipped without
     * error.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $names = [];
        foreach (['terminal', 'pcola_terminal'] as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }
            $sql  = 'SELECT terminal FROM ' . self::ident($table)
                  . ' WHERE terminal IS NOT NULL AND terminal <> ""';
            $rows = $this->prepared($sql)->fetchAll();
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $name = trim((string) $row['terminal']);
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }
        $list = array_keys($names);
        sort($list, SORT_STRING);
        return $list;
    }

    /**
     * O(1) membership test for the validation path. Builds the
     * lookup once per request using all().
     */
    public function isKnown(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        return in_array($name, $this->all(), true);
    }

    private function tableExists(string $name): bool
    {
        $pdo  = $this->connection->pdo();
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($name));
        return $stmt !== false && $stmt->fetchColumn() !== false;
    }
}
