<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Connection;
use PayTracker\Database\Model;
use PayTracker\Logging\Logger;
use PayTracker\Services\GoogleMapsService;
use RuntimeException;

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
     * Source string used by admin-set overrides. See SOURCE_PRIORITY_SQL
     * for the full priority order; admin sits at the top so an admin
     * row wins over every other source for the same pair.
     */
    public const SOURCE_ADMIN = 'admin';

    /**
     * Source-priority SQL fragment. Used as the ORDER BY in
     * lookupOrFetch + between() so the "winning" source is
     * deterministic regardless of insertion order.
     *
     * Priority is INTENTIONAL, not alphabetical:
     *
     *   1. admin            -- explicit override set by an Admin+.
     *                          Always wins; deleting the row reverts
     *                          to the next-highest source.
     *   2. largeMiles       -- legacy non-Pcola distance matrix.
     *   2. pcola_largeMiles -- legacy Pcola distance matrix. Tied
     *                          with largeMiles -- the rare pair
     *                          where both exist tiebreaks
     *                          alphabetically, but both are
     *                          considered "authoritative legacy".
     *   3. google_maps      -- LIVE fallback when no row exists in
     *                          the cache. Once written, stays at
     *                          the bottom: a stale Google answer
     *                          should never beat a legacy matrix
     *                          entry that turns up later (e.g. after
     *                          a 2026_05_23 backfill rerun).
     *   4. anything else    -- never expected. ELSE 99 keeps the
     *                          query well-defined.
     *
     * Encoded as a CASE in raw SQL so the order can't drift from
     * the docblock above.
     */
    private const SOURCE_PRIORITY_SQL =
        "CASE d.source
            WHEN 'admin'            THEN 1
            WHEN 'largeMiles'       THEN 2
            WHEN 'pcola_largeMiles' THEN 2
            WHEN 'google_maps'      THEN 3
            ELSE 99
         END";

    public function __construct(
        Connection $connection,
        private readonly City $cities,
        private readonly GoogleMapsService $maps,
        private readonly Logger $logger,
    ) {
        parent::__construct($connection);
    }

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
     * values, or when an admin override exists alongside a legacy / Google
     * row.
     *
     * Rows are sorted by SOURCE_PRIORITY (admin first, then legacy, then
     * google_maps) so callers that just take the first element get the
     * "winning" value.
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
            ORDER BY ' . self::SOURCE_PRIORITY_SQL . ' ASC, d.source ASC';
        $rows = $this->prepared($sql, [$fromName, $toName])->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Look up the recorded mileage for a pair, falling back to the Google
     * Maps Distance Matrix API on a cache miss and writing the API result
     * back into city_distances so the next lookup hits the local matrix.
     *
     * Returns null if BOTH the local cache misses AND the API can't connect
     * the cities (or the API key isn't configured). The controller maps
     * null to a user-facing "we couldn't resolve a mileage" validation
     * error rather than failing the whole submission.
     *
     * Behaviour:
     *   1. If between() finds any recorded distance, the FIRST one wins.
     *      between() sorts by SOURCE_PRIORITY_SQL (admin → legacy →
     *      google_maps), so an admin override beats every other source,
     *      a legacy matrix entry beats a cached Google answer, and
     *      Google only ever wins when nothing else exists for the pair.
     *   2. On cache miss, call GoogleMapsService::distanceMiles().
     *   3. On a real Google answer, insert/find both endpoint cities,
     *      then INSERT IGNORE the new (from, to, miles, source='google_maps')
     *      row. IGNORE handles the race where two concurrent requests
     *      resolve the same pair. The new row sits at the bottom of
     *      the priority order, so a later admin override or legacy
     *      backfill will immediately shadow it.
     *
     * Caches are written ONE-WAY (from → to) because the legacy data was
     * directional too. The opposite direction will be resolved + cached
     * the next time it's needed. Cheap, predictable.
     */
    public function lookupOrFetch(string $fromName, string $toName): ?int
    {
        $rows = $this->between($fromName, $toName);
        if (count($rows) > 0) {
            return (int) $rows[0]['miles'];
        }
        if (! $this->maps->isConfigured()) {
            return null;
        }
        // GoogleMapsService throws RuntimeException on transport, HTTP,
        // JSON, and non-OK top-status responses (e.g. REQUEST_DENIED
        // when the API key is missing/wrong/restricted, OVER_QUERY_LIMIT
        // when billing is suspended). Those are admin-side configuration
        // problems, not driver-facing errors — log them with enough
        // context for triage and fall through to the controller's
        // friendly "could not find a mileage" message.
        try {
            $miles = $this->maps->distanceMiles($fromName, $toName);
        } catch (RuntimeException $e) {
            $this->logger->info('CityDistance: Google Maps lookup failed', [
                'from'  => $fromName,
                'to'    => $toName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
        if ($miles === null) {
            return null;
        }

        $fromId = $this->cities->findOrCreate($fromName);
        $toId   = $this->cities->findOrCreate($toName);
        $sql    = 'INSERT IGNORE INTO ' . self::ident(self::$table)
                . ' (from_city_id, to_city_id, miles, source) VALUES (?, ?, ?, ?)';
        $this->prepared($sql, [$fromId, $toId, $miles, 'google_maps']);
        return $miles;
    }

    // ====================================================================
    // Admin CRUD
    // ====================================================================

    /**
     * Insert or update an admin-set override for a (from, to) pair.
     * Doesn't touch any existing non-admin row -- the override
     * shadows them via the source-sort priority in lookupOrFetch.
     *
     * Returns true when the row was newly inserted, false when it
     * was updated in place. Callers use this to distinguish "added"
     * from "changed" in the audit metadata.
     */
    public function upsertOverride(int $fromCityId, int $toCityId, int $miles): bool
    {
        // INSERT ... ON DUPLICATE KEY UPDATE means the (from, to,
        // 'admin') row is created on first call and overwritten on
        // every subsequent call. rowCount() reports 1 for an insert
        // and 2 for an update (the MySQL convention for ON DUP UPD).
        $sql = 'INSERT INTO ' . self::ident(self::$table)
             . ' (from_city_id, to_city_id, miles, source)
                VALUES (?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE miles = VALUES(miles)';
        $stmt = $this->prepared($sql, [$fromCityId, $toCityId, $miles, self::SOURCE_ADMIN]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Delete a single (from_city_id, to_city_id, source) row.
     * Returns true when a row was removed, false when no such row
     * existed (e.g. concurrent click, stale UI).
     */
    public function deleteRow(int $fromCityId, int $toCityId, string $source): bool
    {
        $sql  = 'DELETE FROM ' . self::ident(self::$table)
              . ' WHERE from_city_id = ? AND to_city_id = ? AND source = ?';
        $stmt = $this->prepared($sql, [$fromCityId, $toCityId, $source]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Paginated, searchable list for the admin index. Each row
     * carries the joined city names PLUS an override_active flag
     * so the view can decorate the row when an admin source row
     * exists for the same (from, to) pair regardless of which
     * source's row is being rendered.
     *
     * Search matches the from-city OR to-city name (case-insensitive
     * via MySQL's default collation).
     *
     * @return array{rows:list<array{from_id:int,to_id:int,from:string,to:string,miles:int,source:string,override_active:bool}>, total:int}
     */
    public function listForAdmin(string $search, int $page, int $perPage): array
    {
        $perPage = max(1, min(100, $perPage));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $where  = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE cf.city LIKE ? OR ct.city LIKE ?';
            $like  = '%' . $search . '%';
            $params = [$like, $like];
        }

        $countSql = '
            SELECT COUNT(*) FROM ' . self::ident(self::$table) . ' d
            JOIN ' . self::ident('city') . ' cf ON cf.id = d.from_city_id
            JOIN ' . self::ident('city') . ' ct ON ct.id = d.to_city_id'
            . $where;
        $total = (int) $this->prepared($countSql, $params)->fetchColumn();

        // Correlated subquery flags every row whose (from, to) pair
        // has an `admin` row anywhere in the table -- not just the
        // row being rendered. The view uses that to badge the
        // legacy row "shadowed by admin override" so the admin can
        // tell at a glance which displayed numbers are actually
        // load-bearing.
        $rowsSql = '
            SELECT
                d.from_city_id AS from_id,
                d.to_city_id   AS to_id,
                cf.city        AS `from`,
                ct.city        AS `to`,
                d.miles,
                d.source,
                EXISTS (
                    SELECT 1 FROM ' . self::ident(self::$table) . ' d2
                     WHERE d2.from_city_id = d.from_city_id
                       AND d2.to_city_id   = d.to_city_id
                       AND d2.source       = ?
                ) AS override_active
            FROM ' . self::ident(self::$table) . ' d
            JOIN ' . self::ident('city') . ' cf ON cf.id = d.from_city_id
            JOIN ' . self::ident('city') . ' ct ON ct.id = d.to_city_id'
            . $where . '
            ORDER BY cf.city ASC, ct.city ASC, ' . self::SOURCE_PRIORITY_SQL . ' ASC, d.source ASC
            LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $rowParams = array_merge([self::SOURCE_ADMIN], $params);
        $rows = $this->prepared($rowsSql, $rowParams)->fetchAll();
        if (! is_array($rows)) {
            $rows = [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'from_id'         => (int) $r['from_id'],
                'to_id'           => (int) $r['to_id'],
                'from'            => (string) $r['from'],
                'to'              => (string) $r['to'],
                'miles'           => (int) $r['miles'],
                'source'          => (string) $r['source'],
                'override_active' => (int) $r['override_active'] === 1,
            ];
        }
        return ['rows' => $out, 'total' => $total];
    }

    /**
     * Look up a single row by composite PK. Used by the admin
     * surface to fetch the row that's currently being edited so
     * the form can prefill miles.
     *
     * @return array{from_id:int,to_id:int,from:string,to:string,miles:int,source:string}|null
     */
    public function findExact(int $fromCityId, int $toCityId, string $source): ?array
    {
        $sql = '
            SELECT
                d.from_city_id AS from_id,
                d.to_city_id   AS to_id,
                cf.city        AS `from`,
                ct.city        AS `to`,
                d.miles,
                d.source
            FROM ' . self::ident(self::$table) . ' d
            JOIN ' . self::ident('city') . ' cf ON cf.id = d.from_city_id
            JOIN ' . self::ident('city') . ' ct ON ct.id = d.to_city_id
            WHERE d.from_city_id = ? AND d.to_city_id = ? AND d.source = ?
            LIMIT 1';
        $row = $this->prepared($sql, [$fromCityId, $toCityId, $source])->fetch();
        if (! is_array($row)) {
            return null;
        }
        return [
            'from_id' => (int) $row['from_id'],
            'to_id'   => (int) $row['to_id'],
            'from'    => (string) $row['from'],
            'to'      => (string) $row['to'],
            'miles'   => (int) $row['miles'],
            'source'  => (string) $row['source'],
        ];
    }
}
