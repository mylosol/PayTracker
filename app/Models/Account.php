<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;

/**
 * `account` — authoritative user record.
 *
 * Legacy schema (preserved 1:1 for the migration; modern fields will be added
 * in a follow-up migration that introduces `password_hash`, `last_login_at`,
 * and `role`):
 *
 *   id           int PRIMARY KEY AUTO_INCREMENT
 *   user         varchar(60)       — display name / login handle
 *   joinDate     date              — registration day
 *   paidDate     date              — subscription paid-through date
 *   emailValid   date              — email-verified-on date
 *   nonce        int               — single-use email verification nonce
 *   paynonce     int               — single-use payment nonce
 *   accountValid int  default 0    — flag: account fully activated
 *   announce     int  default 0    — flag: opt-in to broadcast announcements
 *   agree        int  default 1    — TOS acceptance flag
 *   ulk          int  default 0    — "user locked" flag
 *
 * The legacy app authenticated users with a single shared password ("monkey")
 * stored in a client cookie. The rebuild MUST move authentication into this
 * table with per-user password hashes — that work is tracked in the project
 * spec under "Rebuilt Application Logic / secure session management".
 */
final class Account extends Model
{
    protected static string $table = 'account';

    /**
     * Find an account by its login handle. Returns null if no row matches —
     * the calling controller is responsible for converting that into a
     * deliberate user-facing error (never a 500).
     *
     * @return array<string, mixed>|null
     */
    public function findByUser(string $username): ?array
    {
        $sql  = 'SELECT id, user, joinDate, paidDate, emailValid, accountValid, announce, agree, ulk
                 FROM ' . self::ident(self::$table) . '
                 WHERE user = :user
                 LIMIT 1';
        $row = $this->prepared($sql, ['user' => $username])->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Count active accounts. Used by the health/admin dashboards. We keep it
     * here rather than inlining in a controller so a future schema change
     * (e.g. soft-delete column) only requires updating one place.
     */
    public function countActive(): int
    {
        $sql = 'SELECT COUNT(*) AS n FROM ' . self::ident(self::$table) . ' WHERE accountValid = 1 AND ulk = 0';
        $row = $this->prepared($sql)->fetch();
        return is_array($row) ? (int) $row['n'] : 0;
    }

    /** Three-letter weekday keys for pay_week_start_day, in calendar order. */
    public const PAY_WEEK_DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /**
     * RBAC roles, in ascending privilege order. The position in this
     * array IS the privilege level — `hasRole($acct, 'admin')` returns
     * true for both 'admin' AND 'super_admin' because super_admin is
     * at a higher index.
     *
     * Why a code-side whitelist rather than a DB enum / CHECK constraint:
     *   Adding a new role (say 'auditor') would otherwise require a
     *   migration + a deploy in lockstep. Keeping the whitelist in
     *   PHP means we can add a role with a single code change and
     *   the existing migration's UPDATE-stragglers-to-'user' clause
     *   still keeps the DB safe against any rogue value.
     */
    public const ROLES = ['user', 'admin', 'super_admin'];

    public const ROLE_USER        = 'user';
    public const ROLE_ADMIN       = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    /**
     * True if the account's role is at least `$minimumRole` in the
     * privilege hierarchy. Inclusive: 'admin' satisfies 'admin', and
     * 'super_admin' satisfies both 'admin' and 'super_admin'.
     *
     * Anonymous accounts (null) and accounts with a missing/unknown
     * role always return false — fail closed. Callers pre-validate
     * that `$minimumRole` is a known role; we throw on unknowns
     * rather than silently allowing anything.
     *
     * @param array<string,mixed>|null $account
     */
    public static function hasRole(?array $account, string $minimumRole): bool
    {
        if (! in_array($minimumRole, self::ROLES, true)) {
            throw new \InvalidArgumentException(
                'Unknown role: ' . $minimumRole . ' (must be one of: ' . implode(', ', self::ROLES) . ')'
            );
        }
        if ($account === null) {
            return false;
        }
        $actorRole = is_string($account['role'] ?? null) ? (string) $account['role'] : '';
        $actorIdx  = array_search($actorRole, self::ROLES, true);
        $minIdx    = array_search($minimumRole, self::ROLES, true);
        if ($actorIdx === false) {
            return false;
        }
        return $actorIdx >= $minIdx;
    }

    /**
     * Update the driver-profile columns for an account.
     *
     * Fields:
     *   hire_date         — drives tenure band at load-write time.
     *   shift             — 'day' or 'night'; snapshotted per load.
     *   payWeekStartDay   — three-letter weekday key. The dashboard's
     *                       This Week card uses this to compute the
     *                       pay-period window.
     *
     * @param ?string $hireDate        ISO date (YYYY-MM-DD) or null to clear.
     * @param string  $shift           'day' or 'night'.
     * @param string  $payWeekStartDay one of PAY_WEEK_DAYS.
     */
    public function updateProfile(
        int $accountId,
        ?string $hireDate,
        string $shift,
        string $payWeekStartDay,
    ): void {
        if (! in_array($shift, ['day', 'night'], true)) {
            throw new \InvalidArgumentException('shift must be "day" or "night"');
        }
        if (! in_array($payWeekStartDay, self::PAY_WEEK_DAYS, true)) {
            throw new \InvalidArgumentException('pay_week_start_day must be one of: ' . implode(', ', self::PAY_WEEK_DAYS));
        }
        if ($hireDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate) !== 1) {
            throw new \InvalidArgumentException('hire_date must be YYYY-MM-DD or null');
        }
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                SET hire_date = ?, shift = ?, pay_week_start_day = ?
                WHERE id = ?';
        $this->prepared($sql, [$hireDate, $shift, $payWeekStartDay, $accountId]);
    }

    // ====================================================================
    // Admin-panel surface
    // ====================================================================

    /**
     * Page through the account list for the admin user-management
     * surface. Returns columns the admin view actually displays —
     * not a SELECT *, so adding sensitive columns later doesn't
     * leak them into the admin page by accident.
     *
     * Ordering: most-recently-active first. NULL `last_login_at`
     * (never-logged-in accounts) sort to the bottom. Falls back
     * to id DESC so the order is deterministic when last_login
     * ties.
     *
     * @return list<array{
     *   id:int, user:string, email:?string, role:string,
     *   last_login_at:?string, banned_at:?string, locked_until:?string
     * }>
     */
    public function allForAdmin(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $sql = 'SELECT id, user, email, role,
                       last_login_at, banned_at, locked_until
                  FROM ' . self::ident(self::$table) . '
                 ORDER BY (last_login_at IS NULL) ASC,
                          last_login_at DESC,
                          id DESC
                 LIMIT ' . $limit;
        $rows = $this->prepared($sql)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Fetch a single account row for the admin edit / action surface.
     * Returns the full set of admin-visible columns including
     * profile fields so a future edit form can pre-populate them.
     *
     * @return array<string,mixed>|null
     */
    public function findById(int $accountId): ?array
    {
        $sql = 'SELECT id, user, email, role,
                       last_login_at, banned_at, locked_until,
                       hire_date, shift, pay_week_start_day
                  FROM ' . self::ident(self::$table) . '
                 WHERE id = ?
                 LIMIT 1';
        $row = $this->prepared($sql, [$accountId])->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Suspend an account. Sets banned_at = NOW() in UTC if not
     * already set. Idempotent — re-banning an already-banned
     * account is a no-op rather than refreshing the timestamp,
     * so the audit log (branch 3) can reason about "first banned
     * at" timestamps cleanly.
     */
    public function ban(int $accountId): void
    {
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                   SET banned_at = COALESCE(banned_at, UTC_TIMESTAMP())
                 WHERE id = ?';
        $this->prepared($sql, [$accountId]);
    }

    /**
     * Lift a suspension. Clears banned_at AND the lockout counters
     * — if an admin is explicitly unbanning someone, the user has
     * earned a clean slate, not a hidden 5-strikes-lockout left
     * over from before the ban.
     */
    public function unban(int $accountId): void
    {
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                   SET banned_at          = NULL,
                       failed_login_count = 0,
                       locked_until       = NULL
                 WHERE id = ?';
        $this->prepared($sql, [$accountId]);
    }

    /**
     * Hard-delete an account. The legacy `account` table has no
     * soft-delete column and the application enforces foreign-key-
     * style constraints in code (e.g., DriverLoad inserts check
     * the driver_id exists). We adopt hard delete here to match
     * the existing convention. The audit log (branch 3) preserves
     * the actor + timestamp trail so "what happened to account N"
     * remains answerable even after the row is gone.
     *
     * The caller is responsible for refusing to delete the actor's
     * OWN account (that would orphan their session and is almost
     * never what an admin actually wants).
     */
    public function deleteAccount(int $accountId): void
    {
        $sql = 'DELETE FROM ' . self::ident(self::$table) . ' WHERE id = ?';
        $this->prepared($sql, [$accountId]);
    }
}
