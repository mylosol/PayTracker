<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;
use PDO;

/**
 * `announcements` — Super-Admin-authored site-wide messages
 * shown to users as a login modal.
 *
 * Lifecycle:
 *   * is_active = 1 AND is_template = 0 AND not expired → shown.
 *   * is_template = 1 → never shown; reusable form for a future
 *     "Use template" copy.
 *   * Expired (expires_at in the past) → not shown even when
 *     still flagged active; the admin should deactivate or
 *     delete to clean up.
 *
 * Only ONE announcement is shown at a time. activate() runs an
 * UPDATE that clears every other row's is_active flag first, in
 * the same transaction.
 *
 * Replaces the unused legacy stub that read the dead `announce`
 * table. The legacy table is left in place; this model addresses
 * a different schema (plural `announcements`).
 */
final class Announcement extends Model
{
    protected static string $table = 'announcements';

    /** Soft caps surfaced to the form + enforced server-side. */
    public const MAX_SUBJECT_LEN = 255;
    public const MAX_BODY_LEN    = 8000;

    /**
     * Currently displayable announcement, if any. Returns the row
     * where is_active = 1 AND is_template = 0 AND (no expiry OR
     * expiry in the future), or null when there's nothing to show.
     *
     * @return array<string,mixed>|null
     */
    public function currentActive(): ?array
    {
        $sql = 'SELECT id, subject, body, is_active, is_template,
                       expires_at, created_by, created_at, updated_at
                  FROM ' . self::ident(self::$table) . '
                 WHERE is_active   = 1
                   AND is_template = 0
                   AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                 ORDER BY updated_at DESC
                 LIMIT 1';
        $row = $this->prepared($sql)->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Page through every announcement for the admin index.
     *
     * @return list<array<string,mixed>>
     */
    public function allForAdmin(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT id, subject, body, is_active, is_template,
                       expires_at, created_by, created_at, updated_at
                  FROM ' . self::ident(self::$table) . '
                 ORDER BY is_template ASC, is_active DESC, updated_at DESC
                 LIMIT ' . $limit;
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Fetch a single announcement by id, joining the creator's user
     * handle when available so the admin view can render attribution.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT a.id, a.subject, a.body, a.is_active, a.is_template,
                       a.expires_at, a.created_by, a.created_at, a.updated_at,
                       c.user AS created_by_user
                  FROM ' . self::ident(self::$table) . ' a
                  LEFT JOIN `account` c ON c.id = a.created_by
                 WHERE a.id = ?
                 LIMIT 1';
        $row = $this->prepared($sql, [$id])->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Create a new announcement row. Always non-active by default
     * (callers must call activate() separately to broadcast). The
     * caller chooses is_template at creation time.
     *
     * @return int the newly inserted id
     */
    public function create(
        string $subject,
        string $body,
        ?string $expiresAt,
        ?int $createdBy,
        bool $isTemplate,
    ): int {
        $sql = 'INSERT INTO ' . self::ident(self::$table) . '
                    (subject, body, is_active, is_template, expires_at, created_by)
                VALUES (?, ?, 0, ?, ?, ?)';
        $this->prepared($sql, [
            $subject,
            $body,
            $isTemplate ? 1 : 0,
            $expiresAt,
            $createdBy,
        ]);
        return (int) $this->connection->pdo()->lastInsertId();
    }

    /**
     * Update an existing row's subject / body / expires_at /
     * is_template. The is_active flag is intentionally NOT
     * touched here; toggling activation is a separate
     * surface so the audit log can record it explicitly.
     */
    public function updateBasics(
        int $id,
        string $subject,
        string $body,
        ?string $expiresAt,
        bool $isTemplate,
    ): void {
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                   SET subject = ?, body = ?, expires_at = ?, is_template = ?
                 WHERE id = ?';
        $this->prepared($sql, [
            $subject,
            $body,
            $expiresAt,
            $isTemplate ? 1 : 0,
            $id,
        ]);
    }

    /**
     * Make this announcement the (single) active one. Deactivates
     * every other row in the same transaction so we never end up
     * with two active rows even if two admins race to activate.
     *
     * Refuses to activate a template — that would be inconsistent
     * with the "templates are never shown" invariant. The caller
     * should clear is_template first (via updateBasics) if they
     * want to broadcast a template's content.
     */
    public function activate(int $id): void
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare('SELECT is_template FROM `announcements` WHERE id = ? LIMIT 1');
            $check->execute([$id]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                throw new \RuntimeException(sprintf('Announcement %d not found.', $id));
            }
            if ((int) ($row['is_template'] ?? 0) === 1) {
                throw new \RuntimeException('Cannot activate a template — clear the template flag first.');
            }
            $pdo->exec('UPDATE `announcements` SET is_active = 0 WHERE is_active = 1');
            $set = $pdo->prepare('UPDATE `announcements` SET is_active = 1 WHERE id = ?');
            $set->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deactivate(int $id): void
    {
        $sql = 'UPDATE ' . self::ident(self::$table) . ' SET is_active = 0 WHERE id = ?';
        $this->prepared($sql, [$id]);
    }

    public function delete(int $id): void
    {
        $sql = 'DELETE FROM ' . self::ident(self::$table) . ' WHERE id = ?';
        $this->prepared($sql, [$id]);
    }
}
