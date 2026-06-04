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
     *   1. If between() finds any recorded distance, the FIRST one wins
     *      (sources sort alphabetically — google_maps < largeMiles <
     *      pcola_largeMiles — so a legacy matrix entry beats a Google entry
     *      when both exist).
     *   2. On cache miss, call GoogleMapsService::distanceMiles().
     *   3. On a real Google answer, insert/find both endpoint cities,
     *      then INSERT IGNORE the new (from, to, miles, source='google_maps')
     *      row. IGNORE handles the race where two concurrent requests
     *      resolve the same pair.
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
}
