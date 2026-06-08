<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;

/**
 * `terminals` — the consolidated list of pickup / Begin Empty terminals
 * that drivers can choose from. Replaces the legacy split between
 * `terminal` (non-Pcola drivers) and `pcola_terminal` (Pcola drivers)
 * which existed because the legacy stack was two apps and a cookie chose
 * which table to read. That distinction is no longer meaningful.
 *
 * Schema (see 2026_06_08_001_create_terminals.sql):
 *
 *   id          INT PK
 *   name        VARCHAR(120) UNIQUE  — display string, e.g. "Panama City, FL"
 *   city_id     INT NULL              — FK into city.id for distance lookups
 *   active      TINYINT(1) DEFAULT 1  — admin soft-delete flag
 *   created_at  DATETIME
 *   updated_at  DATETIME
 *
 * The two legacy tables stay populated and untouched on shared hosting
 * for now; a follow-up cleanup branch will drop them once the modern
 * flow has soaked in production.
 */
final class Terminal extends Model
{
    protected static string $table = 'terminals';

    /**
     * Maximum length of a terminal name. Matches the schema's
     * VARCHAR(120). Public so the admin controller can use it for
     * input validation without re-deriving the cap.
     */
    public const NAME_MAX_LEN = 120;

    // ====================================================================
    // Read paths used by the driver-facing load form
    // ====================================================================

    /**
     * Active terminal display names, alphabetised. This is the picker
     * source for the loads/new + loads/edit forms. Inactive (soft-
     * deleted) rows are excluded.
     *
     * Signature preserved from the pre-consolidation Terminal model so
     * existing callers (LoadEntryController::create / edit) keep working
     * with no change.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $sql  = 'SELECT name
                   FROM ' . self::ident(self::$table) . '
                  WHERE active = 1
                  ORDER BY name ASC';
        $rows = $this->prepared($sql)->fetchAll();
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }

    /**
     * O(1) membership test for the validation path. Inactive rows are
     * NOT known — refusing them at submit time means a deactivated
     * terminal can't be slipped back in via a stale form open.
     */
    public function isKnown(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        return in_array($name, $this->all(), true);
    }

    // ====================================================================
    // Read paths used by the admin CRUD + Begin Empty picker
    // ====================================================================

    /**
     * Every active row including city_id, used by the Begin Empty
     * picker so the controller can resolve distance lookups against
     * the correct city.id.
     *
     * @return list<array{id:int,name:string,city_id:?int}>
     */
    public function listActive(): array
    {
        $sql  = 'SELECT id, name, city_id
                   FROM ' . self::ident(self::$table) . '
                  WHERE active = 1
                  ORDER BY name ASC';
        $rows = $this->prepared($sql)->fetchAll();
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'id'      => (int) $row['id'],
                'name'    => $name,
                'city_id' => isset($row['city_id']) && is_numeric($row['city_id'])
                                 ? (int) $row['city_id']
                                 : null,
            ];
        }
        return $out;
    }

    /**
     * Full row listing for the admin CRUD index, INCLUDING inactive
     * rows so admins can see + reactivate previously deactivated
     * terminals. Sorted active-first, then by name.
     *
     * @return list<array{id:int,name:string,city_id:?int,active:int,
     *                    created_at:?string,updated_at:?string}>
     */
    public function listForAdmin(): array
    {
        $sql  = 'SELECT id, name, city_id, active, created_at, updated_at
                   FROM ' . self::ident(self::$table) . '
                  ORDER BY active DESC, name ASC';
        $rows = $this->prepared($sql)->fetchAll();
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'id'         => (int) $row['id'],
                'name'       => $name,
                'city_id'    => isset($row['city_id']) && is_numeric($row['city_id'])
                                    ? (int) $row['city_id']
                                    : null,
                'active'     => (int) ($row['active'] ?? 0),
                'created_at' => is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : null,
                'updated_at' => is_string($row['updated_at'] ?? null) ? (string) $row['updated_at'] : null,
            ];
        }
        return $out;
    }

    /**
     * Single-row lookup by primary key. Returns null on miss.
     *
     * @return array{id:int,name:string,city_id:?int,active:int}|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $sql = 'SELECT id, name, city_id, active
                  FROM ' . self::ident(self::$table) . '
                 WHERE id = ?
                 LIMIT 1';
        $row = $this->prepared($sql, [$id])->fetch();
        if (! is_array($row)) {
            return null;
        }
        return [
            'id'      => (int) $row['id'],
            'name'    => trim((string) ($row['name'] ?? '')),
            'city_id' => isset($row['city_id']) && is_numeric($row['city_id'])
                            ? (int) $row['city_id']
                            : null,
            'active'  => (int) ($row['active'] ?? 0),
        ];
    }

    // ====================================================================
    // Write paths used by the admin CRUD
    // ====================================================================

    /**
     * Insert a new terminal. Returns the new id, or null if the name
     * was already present (the unique constraint refused the insert).
     * Validation of `name` length / charset is the caller's job;
     * this method only enforces the trim + non-empty rule.
     */
    public function create(string $name, ?int $cityId): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        try {
            $sql = 'INSERT INTO ' . self::ident(self::$table) . ' (name, city_id, active)
                    VALUES (?, ?, 1)';
            $this->prepared($sql, [$name, $cityId]);
            return (int) $this->connection->pdo()->lastInsertId();
        } catch (\PDOException $e) {
            // SQLSTATE 23000 = integrity constraint violation
            // (unique key on `name`). Refuse cleanly.
            if ($e->getCode() === '23000') {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Update name + city_id on an existing terminal. Returns true on
     * success, false if the row vanished between read and write or
     * the new name collided with another row's unique key.
     */
    public function update(int $id, string $name, ?int $cityId): bool
    {
        $name = trim($name);
        if ($id <= 0 || $name === '') {
            return false;
        }
        try {
            $sql = 'UPDATE ' . self::ident(self::$table) . '
                       SET name = ?, city_id = ?, updated_at = NOW()
                     WHERE id = ?';
            $stmt = $this->prepared($sql, [$name, $cityId, $id]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Flip `active` to 0. Soft-delete semantics: the row stays for
     * historical / audit context but stops showing in the driver
     * picker.
     */
    public function deactivate(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                   SET active = 0, updated_at = NOW()
                 WHERE id = ? AND active = 1';
        return $this->prepared($sql, [$id])->rowCount() > 0;
    }

    /**
     * Inverse of deactivate(). Useful when an admin removed a
     * terminal by mistake.
     */
    public function reactivate(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                   SET active = 1, updated_at = NOW()
                 WHERE id = ? AND active = 0';
        return $this->prepared($sql, [$id])->rowCount() > 0;
    }
}
