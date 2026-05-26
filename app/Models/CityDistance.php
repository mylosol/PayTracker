<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;

/**
 * `city_distances` — relational replacement for the legacy column-per-city
 * `largeMiles` / `pcola_largeMiles` matrices.
 *
 * Schema (created by migration 2026_05_23_002):
 *   from_city_id INT      — references city.id
 *   to_city_id   INT      — references city.id
 *   miles        INT      — recorded road distance
 *   source       VARCHAR  — origin matrix table ('largeMiles' or
 *                            'pcola_largeMiles'). Kept so a future
 *                            "different region, different recorded
 *                            mileage" case can be represented faithfully.
 *   PRIMARY KEY (from_city_id, to_city_id, source)
 *
 * This branch is read-only: writes will land in a future branch that
 * redirects the legacy add-location flow to insert here. Until then the
 * model surface is intentionally small.
 */
final class CityDistance extends Model
{
    protected static string $table = 'city_distances';

    /**
     * Aggregate counters for the /distances dashboard. Always returns the
     * same shape — zeroes when the backfill hasn't run yet — so the view
     * doesn't have to defend against missing keys.
     *
     * @return array{total_rows:int, unique_pairs:int, unique_cities:int, by_source: array<string,int>}
     */
    public function summary(): array
    {
        $pdo = $this->connection->pdo();

        $total = (int) $pdo->query('SELECT COUNT(*) FROM ' . self::ident(self::$table))->fetchColumn();

        $uniquePairs = (int) $pdo->query(
            'SELECT COUNT(*) FROM (SELECT from_city_id, to_city_id FROM ' . self::ident(self::$table)
            . ' GROUP BY from_city_id, to_city_id) p'
        )->fetchColumn();

        // Cities that participate as either endpoint at least once.
        $uniqueCities = (int) $pdo->query(
            'SELECT COUNT(*) FROM (
                SELECT from_city_id AS id FROM ' . self::ident(self::$table) . '
                UNION
                SELECT to_city_id AS id FROM ' . self::ident(self::$table) . '
             ) c'
        )->fetchColumn();

        $bySource = [];
        $stmt = $pdo->query('SELECT source, COUNT(*) AS n FROM ' . self::ident(self::$table) . ' GROUP BY source');
        foreach ($stmt as $row) {
            $bySource[(string) $row['source']] = (int) $row['n'];
        }

        return [
            'total_rows'    => $total,
            'unique_pairs'  => $uniquePairs,
            'unique_cities' => $uniqueCities,
            'by_source'     => $bySource,
        ];
    }

    /**
     * Most recently-loaded distance rows joined with city names — for the
     * dashboard sample table. Sorted by primary-key order which on
     * InnoDB-with-clustering is effectively insertion order for our
     * append-only backfill.
     *
     * @return list<array{from:string, to:string, miles:int, source:string}>
     */
    public function sample(int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));
        $sql = '
            SELECT cf.city AS `from`, ct.city AS `to`, d.miles, d.source
            FROM ' . self::ident(self::$table) . ' d
            JOIN ' . self::ident('city') . ' cf ON cf.id = d.from_city_id
            JOIN ' . self::ident('city') . ' ct ON ct.id = d.to_city_id
            ORDER BY cf.city ASC, ct.city ASC
            LIMIT ' . $limit;
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Look up the recorded mileage(s) between two cities by name. Returns
     * every (source, miles) tuple recorded — usually one, occasionally two
     * when the same pair appears in both legacy matrices with different
     * values. The view is responsible for picking which one to display.
     *
     * @return list<array{miles:int, source:string}>
     */
    public function between(string $fromName, string $toName): array
    {
        $sql = '
            SELECT d.miles, d.source
            FROM ' . self::ident(self::$table) . ' d
            JOIN ' . self::ident('city') . ' cf ON cf.id = d.from_city_id
            JOIN ' . self::ident('city') . ' ct ON ct.id = d.to_city_id
            WHERE cf.city = ? AND ct.city = ?
            ORDER BY d.source ASC';
        $rows = $this->prepared($sql, [$fromName, $toName])->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
