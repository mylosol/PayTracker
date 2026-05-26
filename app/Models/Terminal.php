<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;

/**
 * `terminal` and `pcola_terminal` — the list of fuel terminals a driver may
 * load at. The Pensacola variant is a per-region overlay carried forward
 * from the legacy app and kept here verbatim for parity. Future work will
 * collapse the two tables into a single `terminals` table with a `region`
 * column once the migration plan is approved.
 */
final class Terminal extends Model
{
    protected static string $table = 'terminal';

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $sql  = 'SELECT id, terminal FROM ' . self::ident(self::$table) . ' ORDER BY terminal ASC';
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
