<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;

/**
 * `announcement_dismissals` — per-user acknowledgement record.
 *
 * One row per (announcement_id, account_id) thanks to the UNIQUE
 * index. INSERT ... ON DUPLICATE KEY UPDATE keeps the recordSeen
 * call race-free.
 *
 * Two questions this table answers:
 *   1. "Has this user permanently dismissed this announcement?"
 *      → SELECT WHERE suppressed = 1.
 *   2. "Who has seen this announcement?" (admin report)
 *      → SELECT all rows for the announcement.
 */
final class AnnouncementDismissal extends Model
{
    protected static string $table = 'announcement_dismissals';

    /**
     * True when the account has permanently dismissed the
     * announcement (clicked "Okay" with "don't show again"
     * ticked).
     *
     * A non-suppressed row means the user has seen + clicked
     * Okay at least once but wants to keep seeing it on each
     * login. That row doesn't block the modal.
     */
    public function isSuppressed(int $announcementId, int $accountId): bool
    {
        $sql = 'SELECT 1 FROM ' . self::ident(self::$table) . '
                 WHERE announcement_id = ?
                   AND account_id      = ?
                   AND suppressed      = 1
                 LIMIT 1';
        return (bool) $this->prepared($sql, [$announcementId, $accountId])->fetchColumn();
    }

    /**
     * Record an acknowledgement. Inserts a new row OR upgrades an
     * existing one's suppressed flag, whichever applies.
     *
     * Idempotent: clicking Okay twice in a row produces the same
     * end state.
     */
    public function recordAck(int $announcementId, int $accountId, bool $suppressed): void
    {
        $suppressedInt = $suppressed ? 1 : 0;
        $sql = 'INSERT INTO ' . self::ident(self::$table) . '
                    (announcement_id, account_id, suppressed)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    suppressed   = GREATEST(suppressed, VALUES(suppressed)),
                    dismissed_at = CURRENT_TIMESTAMP';
        $this->prepared($sql, [$announcementId, $accountId, $suppressedInt]);
    }

    /**
     * Who has seen this announcement? Returns the per-user
     * dismissal rows joined with the account handle so the admin
     * "seen by" report can render names instead of bare ids.
     *
     * @return list<array{
     *   account_id:int, user:?string, email:?string,
     *   dismissed_at:string, suppressed:int
     * }>
     */
    public function viewersOf(int $announcementId, int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        $sql = 'SELECT d.account_id, a.user, a.email,
                       d.dismissed_at, d.suppressed
                  FROM ' . self::ident(self::$table) . ' d
                  LEFT JOIN `account` a ON a.id = d.account_id
                 WHERE d.announcement_id = ?
                 ORDER BY d.dismissed_at DESC
                 LIMIT ' . $limit;
        $rows = $this->prepared($sql, [$announcementId])->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Count of distinct viewers for the admin index summary.
     */
    public function viewerCount(int $announcementId): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::ident(self::$table) . '
                 WHERE announcement_id = ?';
        return (int) $this->prepared($sql, [$announcementId])->fetchColumn();
    }
}
