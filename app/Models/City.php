<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;

/**
 * `city` — the canonical list of city names used as load origins/destinations.
 *
 * Legacy schema (preserved):
 *   id   int PRIMARY KEY AUTO_INCREMENT
 *   city varchar(50) NOT NULL
 *
 * Note: the legacy system separately maintains city-to-city distance data
 * in `largeMiles` / `pcola_largeMiles`, which use cities as COLUMN names
 * (anti-relational, ALTER-TABLE-on-insert design). This model intentionally
 * only manages the simple `city` list; the matrix tables are slated for a
 * normalization migration in a future branch.
 */
final class City extends Model
{
    protected static string $table = 'city';

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $sql  = 'SELECT id, city FROM ' . self::ident(self::$table) . ' ORDER BY city ASC';
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * City list scoped to form-picker UIs. Same shape as all() but with
     * the legacy "bare name" duplicates hidden when a state-suffixed
     * variant exists.
     *
     * The `city` table carries two generations of rows:
     *   - Legacy bare names: "Andalusia", "Apalachicola", "Chipley".
     *   - Backfill-normalized names: "Andalusia, AL", "Apalachicola, FL",
     *     "Chipley, FL" — inserted by migration 2026_05_23_003 from the
     *     legacy largeMiles / pcola_largeMiles matrices.
     *
     * The backfill intentionally did NOT delete the bare rows (citing
     * unknown legacy references to them). On the picker UI that means
     * the user sees both forms back-to-back in the alphabetised list.
     * This method filters the bare form out when a "Name, ST" twin
     * exists for any ST, while preserving:
     *   - Bare names with no state-suffixed twin (kept as-is).
     *   - All state-suffixed names (always kept).
     *
     * The full all() list remains available for code paths (e.g. the
     * existing /loads QA surface) that need every row.
     *
     * @return list<array<string, mixed>>
     */
    public function allForPicker(): array
    {
        $rows = $this->all();
        if ($rows === []) {
            return [];
        }

        // First pass: collect the "Name" prefix for every state-suffixed
        // row. We treat any trailing ", XX" (two uppercase letters) as a
        // state code; that matches every legacy normalization the
        // backfill produced.
        $hasSuffixed = [];
        foreach ($rows as $row) {
            $name = (string) $row['city'];
            if (preg_match('/^(.+), [A-Z]{2}$/', $name, $m) === 1) {
                $hasSuffixed[$m[1]] = true;
            }
        }

        // Second pass: keep all state-suffixed rows; keep bare rows only
        // when no state-suffixed twin exists.
        $out = [];
        foreach ($rows as $row) {
            $name = (string) $row['city'];
            $isSuffixed = preg_match('/, [A-Z]{2}$/', $name) === 1;
            if ($isSuffixed) {
                $out[] = $row;
                continue;
            }
            if (! isset($hasSuffixed[$name])) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * Look up a city by its display name. Case-insensitive on the comparison
     * side (utf8mb3_general_ci is case-insensitive by default in MySQL) so a
     * user who types "panama city, fl" doesn't successfully sneak in a
     * duplicate of "Panama City, FL".
     */
    public function findByName(string $name): ?array
    {
        $sql  = 'SELECT id, city FROM ' . self::ident(self::$table) . ' WHERE city = ? LIMIT 1';
        $row = $this->prepared($sql, [$name])->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Insert a new city row. Returns the new id.
     *
     * The caller MUST have validated `$name` against the allowed charset
     * before calling — this method does no sanitisation, only parameter
     * binding. Validation lives in the controller so error messages can be
     * surfaced to the user.
     */
    public function insert(string $name): int
    {
        $sql = 'INSERT INTO ' . self::ident(self::$table) . ' (city) VALUES (?)';
        $this->prepared($sql, [$name]);
        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * Resolve a city name to its id, inserting a row if one does not exist.
     * Used by the city_distances cache-fill path: when Google Maps returns
     * mileage for a pair we don't have yet, we need both endpoint city.id
     * values to write the row.
     *
     * No validation — callers should only pass names that survived a form-
     * level allow-list check (letters, spaces, commas, periods, hyphens,
     * apostrophes). The cache-fill caller in CityDistance gates this.
     */
    public function findOrCreate(string $name): int
    {
        $existing = $this->findByName($name);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return $this->insert($name);
    }
}
