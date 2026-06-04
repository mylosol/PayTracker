<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;
use PDO;

/**
 * `invite_codes` — Admin-issued single-use registration tokens.
 *
 * Code shape: 8 uppercase letters + digits. ~2.8 trillion-key space,
 * easy to read in an email AND large enough that we don't need a
 * per-IP rate limiter for v1 -- an attacker would need to make
 * trillions of attempts to land a hit, and every miss is
 * USER_REGISTER_FAILED in the audit log.
 *
 * Lifecycle:
 *   1. Admin mints a row via /admin/invites/new.
 *   2. Optionally Resend emails the URL to invitee_email.
 *   3. The recipient visits /register?invite=CODE, the form
 *      pre-fills code, they submit username + email + password.
 *   4. The registration controller calls consume() inside a single
 *      transaction so the same code can never be used twice.
 *   5. If auto_delete is 1, consume() also DELETEs the row at the
 *      end. If 0, the row stays with used_at populated so the
 *      admin can see who redeemed it.
 *
 * All methods that touch user-supplied code values normalize first
 * (uppercase + trim) so a copy/paste with a leading space or a
 * lowercase typo still hits the right row.
 */
final class InviteCode extends Model
{
    protected static string $table = 'invite_codes';

    /** 8 characters; uppercase letters + digits. */
    public const CODE_LEN = 8;
    /** Alphabet without 0/O/1/I to dodge transcription errors from email. */
    public const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Generate a fresh unique code. Retries on the (extremely
     * rare) collision with an existing row up to $maxAttempts times
     * before giving up with an exception. Default 5 attempts is
     * more than enough -- the alphabet's birthday-bound starts
     * meaningful only past ~10^14 rows.
     *
     * @throws \RuntimeException after exhausting retries
     */
    public function mintUniqueCode(int $maxAttempts = 5): string
    {
        $alphabet = self::CODE_ALPHABET;
        $alphaLen = strlen($alphabet);
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = '';
            for ($j = 0; $j < self::CODE_LEN; $j++) {
                $candidate .= $alphabet[random_int(0, $alphaLen - 1)];
            }
            $exists = (bool) $this->prepared(
                'SELECT 1 FROM ' . self::ident(self::$table) . ' WHERE code = ? LIMIT 1',
                [$candidate]
            )->fetchColumn();
            if (! $exists) {
                return $candidate;
            }
        }
        throw new \RuntimeException(
            'Could not allocate a unique invite code after ' . $maxAttempts . ' attempts'
        );
    }

    /**
     * Normalize a user-supplied code string. Trims whitespace and
     * uppercases. Returns an empty string when the input was empty
     * after trim so the caller can fail validation cleanly.
     */
    public static function normalize(string $raw): string
    {
        return strtoupper(trim($raw));
    }

    /**
     * Page through the admin invite-list view. Active codes
     * (unconsumed, unexpired) sort first; expired and consumed
     * codes fall to the bottom.
     *
     * @return list<array<string,mixed>>
     */
    public function allForAdmin(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT c.id, c.code, c.created_by, c.invitee_email,
                       c.expires_at, c.used_at, c.used_by_id, c.auto_delete,
                       c.created_at, c.updated_at,
                       a.user  AS created_by_user,
                       b.user  AS used_by_user
                  FROM ' . self::ident(self::$table) . ' c
                  LEFT JOIN `account` a ON a.id = c.created_by
                  LEFT JOIN `account` b ON b.id = c.used_by_id
                 ORDER BY (c.used_at IS NULL) DESC,
                          (c.expires_at IS NULL OR c.expires_at > UTC_TIMESTAMP()) DESC,
                          c.created_at DESC
                 LIMIT ' . $limit;
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Fetch by id for the admin edit / re-send / revoke surfaces.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT id, code, created_by, invitee_email, expires_at,
                       used_at, used_by_id, auto_delete, created_at, updated_at
                  FROM ' . self::ident(self::$table) . '
                 WHERE id = ?
                 LIMIT 1';
        $row = $this->prepared($sql, [$id])->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Fetch the live row for a user-supplied code IF it's currently
     * redeemable: exists, not consumed, not expired. Returns null
     * on any miss without distinguishing the failure reason --
     * surfacing "this code already used" vs. "no such code" would
     * help an enumeration attack with no real benefit to the
     * legitimate user (who just needs to ask for a fresh code).
     *
     * @return array<string,mixed>|null
     */
    public function findLive(string $rawCode): ?array
    {
        $code = self::normalize($rawCode);
        if ($code === '' || strlen($code) !== self::CODE_LEN) {
            return null;
        }
        $sql = 'SELECT id, code, created_by, invitee_email, expires_at,
                       used_at, used_by_id, auto_delete, created_at
                  FROM ' . self::ident(self::$table) . '
                 WHERE code         = ?
                   AND used_at      IS NULL
                   AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
                 LIMIT 1';
        $row = $this->prepared($sql, [$code])->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Create a new invite row. Returns the inserted id. The caller
     * holds the raw code (returned alongside) so it can build the
     * shareable URL.
     *
     * @return array{id:int, code:string}
     */
    public function create(
        ?string $inviteeEmail,
        ?string $expiresAt,
        ?int $createdBy,
        bool $autoDelete,
    ): array {
        $code = $this->mintUniqueCode();
        $sql = 'INSERT INTO ' . self::ident(self::$table) . '
                    (code, created_by, invitee_email, expires_at, auto_delete)
                VALUES (?, ?, ?, ?, ?)';
        $this->prepared($sql, [
            $code,
            $createdBy,
            $inviteeEmail,
            $expiresAt,
            $autoDelete ? 1 : 0,
        ]);
        return [
            'id'   => (int) $this->connection->pdo()->lastInsertId(),
            'code' => $code,
        ];
    }

    /**
     * Update the editable fields on an existing row. Refuses to
     * mutate a row that's already been consumed -- the consume
     * path is the canonical end-of-life event.
     *
     * @throws \RuntimeException when the row is already used
     */
    public function update(
        int $id,
        ?string $inviteeEmail,
        ?string $expiresAt,
        bool $autoDelete,
    ): void {
        $row = $this->findById($id);
        if ($row === null) {
            throw new \RuntimeException(sprintf('Invite code %d not found.', $id));
        }
        if (is_string($row['used_at'] ?? null) && $row['used_at'] !== '') {
            throw new \RuntimeException('Cannot edit an invite that has already been consumed.');
        }
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                   SET invitee_email = ?, expires_at = ?, auto_delete = ?
                 WHERE id = ?';
        $this->prepared($sql, [
            $inviteeEmail,
            $expiresAt,
            $autoDelete ? 1 : 0,
            $id,
        ]);
    }

    /**
     * Hard-delete an invite. Used by the admin "Revoke" action and
     * by the consume path when auto_delete is true.
     */
    public function delete(int $id): void
    {
        $sql = 'DELETE FROM ' . self::ident(self::$table) . ' WHERE id = ?';
        $this->prepared($sql, [$id]);
    }
}
