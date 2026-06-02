<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;

/**
 * `announce` — operator-broadcast announcements shown on the dashboard.
 *
 * Schema:
 *   id      int PRIMARY KEY
 *   title   varchar(140)
 *   content varchar(1000)
 *
 * Read-mostly table. Writes happen exclusively through the admin UI, so the
 * model exposes a `latest()` accessor and a parameter-bound `create()` rather
 * than a generic CRUD surface — that keeps the attack surface narrow.
 */
final class Announcement extends Model
{
    protected static string $table = 'announce';

    /**
     * Return the N most recent announcements (default 5). The legacy schema
     * has no created_at column, so we order by descending id as a stable
     * proxy for chronological order — newer rows always have higher ids.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 5): array
    {
        // `LIMIT` cannot be a bound parameter on every MySQL driver
        // configuration, so we hard-clamp the integer ourselves rather than
        // interpolating raw user input.
        $limit = max(1, min(50, $limit));
        $sql   = 'SELECT id, title, content
                  FROM ' . self::ident(self::$table) . '
                  ORDER BY id DESC
                  LIMIT ' . $limit;
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
