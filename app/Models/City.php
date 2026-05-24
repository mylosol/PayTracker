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
}
