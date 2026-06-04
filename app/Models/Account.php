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
    /**
     * Privilege-chain mutation guard.
     *
     * Returns true iff the actor's role is STRICTLY higher than the
     * target's in the ROLES hierarchy. Used by AdminUsersController to
     * gate every destructive action against another account: ban,
     * unban, delete, reset-password, role-assignment, basics-edit.
     *
     * Implications worth knowing about:
     *   - A Super Admin cannot modify another Super Admin via the UI.
     *     That makes Super Admin effectively a one-way promotion --
     *     a peer Super Admin can't demote them. Adjusting that
     *     requires direct DB access. The trade-off is intentional:
     *     no power struggle within the highest tier.
     *   - The self-target guard in AdminUsersController::mutate is
     *     a complementary check, not a substitute. canMutate() does
     *     NOT short-circuit on identity -- a Super Admin targeting
     *     themselves still returns false here because
     *     actor_idx > target_idx is required.
     *   - Anonymous or unknown-role values fail closed: an actor
     *     row that lacks a known role can't mutate anything.
     *
     * @param array<string,mixed>|null $actor
     * @param array<string,mixed>|null $target
     */
    public static function canMutate(?array $actor, ?array $target): bool
    {
        if ($actor === null || $target === null) {
            return false;
        }
        $actorRole  = is_string($actor['role']  ?? null) ? (string) $actor['role']  : '';
        $targetRole = is_string($target['role'] ?? null) ? (string) $target['role'] : '';
        $actorIdx   = array_search($actorRole,  self::ROLES, true);
        $targetIdx  = array_search($targetRole, self::ROLES, true);
        if ($actorIdx === false || $targetIdx === false) {
            return false;
        }
        return $targetIdx < $actorIdx;
    }

    /**
     * Variant of canMutate that ALSO allows a Super Admin to act on a
     * peer Super Admin. Used by the role-assignment surface (and only
     * by it) so a Super Admin can fix a mistaken promotion -- demote
     * another Super Admin back down to Admin or User.
     *
     * canMutate proper still gates ban / unban / delete / reset-pw /
     * basics-edit at the stricter "strictly lower" rule, so this
     * carve-out is intentionally narrow: peer-on-peer SET ROLE is
     * fine, peer-on-peer BAN is not. A malicious Super Admin trying
     * to weaponise this would need a multi-step demote-then-ban
     * sequence, both of which audit-log the actor.
     *
     * The sole-Super-Admin safeguard in
     * AdminUsersController::setRole still prevents demoting the
     * last super_admin, so this can't strand the system.
     *
     * @param array<string,mixed>|null $actor
     * @param array<string,mixed>|null $target
     */
    public static function canChangeRoleOf(?array $actor, ?array $target): bool
    {
        if (self::canMutate($actor, $target)) {
            return true;
        }
        // Peer Super Admin carve-out.
        return self::hasRole($actor, self::ROLE_SUPER_ADMIN)
            && is_array($target)
            && is_string($target['role'] ?? null)
            && (string) $target['role'] === self::ROLE_SUPER_ADMIN;
    }

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
        return $this->pageForAdmin($limit, 0, '', null, false)['rows'];
    }

    /**
     * Pattern that matches an account `user` handle we treat as legacy
     * spam-bot garbage. The legacy registration form had no input
     * validation; over the years bots filled it with SQL-injection
     * probes, XSS payloads, URLs, header-injection probes, path
     * traversal probes, and so on. This predicate hides them by
     * default from the admin panel — the cleanup CLI
     * (scripts/cleanup-spam-accounts.php) uses the same set of clauses.
     *
     * Patterns calibrated against a real account.json dump from
     * production (see commit history). The handful that weren't
     * caught by the original v1 predicate added new clauses:
     *
     *   id 109  ""                                  -> empty / null
     *   id 112  "\x00nweonk"                        -> null byte
     *   id 119  "\r\nX-foo: bar"                    -> CR/LF
     *   id 121  "../admin/noop.cgi?foo=bar"         -> traversal + .cgi
     *   id 126  "%68%74%74%70..."                   -> URL-encoded
     *   id 131  "http://rfi.nessus.org/rfi.txt"     -> URL (already caught)
     *   id 141  "create.php"                        -> file probe
     *
     * @var list<string>
     */
    private const SPAM_PREDICATE = [
        // SQL injection keyword payloads.
        "user REGEXP '(SELECT|WAITFOR|SLEEP|UNION|RAID|pg_sleep|EXTRACTVALUE|BENCHMARK)'",
        // Tautology-style probes.
        "user REGEXP '\\\\b(OR|AND)\\\\b.*=.*'",
        // URLs jammed into the handle.
        "user LIKE 'http://%' OR user LIKE 'https://%'",
        // Spam test addresses.
        "user LIKE '%example.com%'",
        // XSS / HTML payloads.
        "user LIKE '%<%>%'",
        // Whitespace in handle.
        "user LIKE '% %'",
        // Parens / quotes.
        "user LIKE '%(%' OR user LIKE '%)%' OR user LIKE '%\"%' OR user LIKE '%''%'",
        // Empty / null handles.
        "user IS NULL OR user = ''",
        // Control characters: null byte, CR, LF, tab.
        "LOCATE(CHAR(0),  user) > 0",
        "LOCATE(CHAR(9),  user) > 0",
        "LOCATE(CHAR(10), user) > 0",
        "LOCATE(CHAR(13), user) > 0",
        // Path traversal probes.
        "user LIKE '%../%' OR user LIKE '%..\\\\%'",
        // URL-encoded hex blobs (3+ consecutive %XX sequences).
        "user REGEXP '(%[0-9a-fA-F]{2}){3,}'",
        // File-extension probes -- no real handle ends in these.
        "user REGEXP '\\\\.(php|cgi|asp|jsp|aspx|html?|xml|sh|bak|inc)$'",
    ];

    /**
     * Paginated user list for the admin panel.
     *
     * @param int     $limit     1-200 rows per page.
     * @param int     $offset    Rows to skip.
     * @param string  $search    Optional substring match against `user`
     *                           OR `email`. Empty means no filter.
     * @param ?string $roleFilter Optional exact role filter. null/'' = any.
     * @param bool    $includeSpam When false (default), rows matching the
     *                            SPAM_PREDICATE are hidden -- the legacy
     *                            DB has years of bot-registration junk
     *                            that crowds the table otherwise.
     *
     * @return array{rows:list<array<string,mixed>>, total:int, totalAfterFilters:int}
     *         total              = COUNT(*) on the table (unfiltered).
     *         totalAfterFilters  = matching rows after search/role/spam filters.
     */
    public function pageForAdmin(
        int $limit = 50,
        int $offset = 0,
        string $search = '',
        ?string $roleFilter = null,
        bool $includeSpam = false,
    ): array {
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where  = [];
        $params = [];

        if (! $includeSpam) {
            // Wrap each clause group so we negate the WHOLE set.
            $spam   = '(' . implode(') OR (', self::SPAM_PREDICATE) . ')';
            $where[] = 'NOT (' . $spam . ')';
        }
        if ($search !== '') {
            $where[]  = '(user LIKE ? OR email LIKE ?)';
            $needle   = '%' . $search . '%';
            $params[] = $needle;
            $params[] = $needle;
        }
        if ($roleFilter !== null && $roleFilter !== '' && in_array($roleFilter, self::ROLES, true)) {
            $where[]  = 'role = ?';
            $params[] = $roleFilter;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->prepared(
            'SELECT COUNT(*) FROM ' . self::ident(self::$table)
        )->fetchColumn();

        $totalAfterFilters = (int) $this->prepared(
            'SELECT COUNT(*) FROM ' . self::ident(self::$table) . $whereSql,
            $params
        )->fetchColumn();

        $rowsSql = 'SELECT id, user, email, role,
                           last_login_at, banned_at, locked_until
                      FROM ' . self::ident(self::$table)
                  . $whereSql
                  . ' ORDER BY (last_login_at IS NULL) ASC,
                              last_login_at DESC,
                              id DESC
                     LIMIT ' . $limit . ' OFFSET ' . $offset;
        $rows = $this->prepared($rowsSql, $params)->fetchAll();

        return [
            'rows'              => is_array($rows) ? $rows : [],
            'total'             => $total,
            'totalAfterFilters' => $totalAfterFilters,
        ];
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

    /**
     * Persist a new role on the given account. Rejects unknown roles
     * with InvalidArgumentException so a controller-side typo can't
     * silently corrupt the column (the column is VARCHAR(20); a stray
     * value would just sit there).
     *
     * Does NOT enforce the self-demotion or sole-super-admin rules —
     * those are policy concerns the controller owns. This method is
     * just the underlying mutation.
     */
    /**
     * Charset / shape constraint for a username (the `user` column).
     * Letters, digits, dot, underscore, dash; 3-32 chars. Crucially
     * does NOT allow '@' so a typed-in username can never look like
     * an email -- the two columns stay semantically distinct.
     */
    public const USER_PATTERN = '/^[A-Za-z0-9._-]{3,32}$/';

    /**
     * Update the login handle + email pair for the given account.
     * Both fields are validated server-side: username against
     * USER_PATTERN, email against PHP's FILTER_VALIDATE_EMAIL.
     * Empty / null email is allowed (it just disables outbound
     * password-reset email until the user sets one).
     *
     * Uniqueness is checked against OTHER accounts before the
     * UPDATE. We rely on the database's UNIQUE index on email and
     * an application-level check on user (the legacy `user` column
     * has no unique constraint and adding one would fail if any
     * duplicates remain from spam-bot signups).
     *
     * @throws \InvalidArgumentException on validation failure
     * @throws \RuntimeException on uniqueness collision
     */
    public function updateBasics(int $accountId, string $user, ?string $email): void
    {
        $user  = trim($user);
        $email = $email !== null ? trim($email) : null;
        if ($email === '') {
            $email = null;
        }

        // Grandfather clause: legacy accounts have their email stored
        // in the `user` column (e.g. "mylosol@gmail.com"), which
        // doesn't match USER_PATTERN. Forcing them to pick a new
        // username before saving anything else would lock them out
        // of changing hire_date / shift / etc. on every legacy login.
        // So: only enforce USER_PATTERN when the username is being
        // CHANGED. A no-op save (current value resubmitted) skips
        // the check. Picking a fresh value DOES validate against
        // the modern pattern, so we forward-only migrate the column.
        $current = $this->prepared(
            'SELECT user FROM ' . self::ident(self::$table) . ' WHERE id = ? LIMIT 1',
            [$accountId]
        )->fetch();
        $currentUser = is_array($current) && is_string($current['user'] ?? null) ? (string) $current['user'] : '';

        if ($user !== $currentUser && preg_match(self::USER_PATTERN, $user) !== 1) {
            throw new \InvalidArgumentException(
                'Username must be 3-32 characters, letters/digits/dot/underscore/dash only (no spaces or @).'
            );
        }
        if ($user === '') {
            throw new \InvalidArgumentException('Username is required.');
        }
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Email is not a valid address.');
        }

        // Application-level uniqueness on `user` (no DB constraint —
        // legacy table has dupes from spam-bot signups; we enforce
        // forward-only).
        $check = $this->prepared(
            'SELECT id FROM ' . self::ident(self::$table) . ' WHERE user = ? AND id <> ? LIMIT 1',
            [$user, $accountId]
        )->fetch();
        if (is_array($check) && (int) ($check['id'] ?? 0) > 0) {
            throw new \RuntimeException('That username is already taken.');
        }
        if ($email !== null) {
            $check = $this->prepared(
                'SELECT id FROM ' . self::ident(self::$table) . ' WHERE email = ? AND id <> ? LIMIT 1',
                [$email, $accountId]
            )->fetch();
            if (is_array($check) && (int) ($check['id'] ?? 0) > 0) {
                throw new \RuntimeException('That email is already taken.');
            }
        }

        $sql = 'UPDATE ' . self::ident(self::$table) . ' SET user = ?, email = ? WHERE id = ?';
        $this->prepared($sql, [$user, $email, $accountId]);
    }

    public function setRole(int $accountId, string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException(
                'Unknown role: ' . $role . ' (must be one of: ' . implode(', ', self::ROLES) . ')'
            );
        }
        $sql = 'UPDATE ' . self::ident(self::$table) . ' SET role = ? WHERE id = ?';
        $this->prepared($sql, [$role, $accountId]);
    }

    /**
     * Count accounts at a given role. Powers the sole-super-admin
     * safeguard: a super_admin can only demote themselves while at
     * least one other super_admin exists.
     */
    public function countByRole(string $role): int
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException(
                'Unknown role: ' . $role . ' (must be one of: ' . implode(', ', self::ROLES) . ')'
            );
        }
        $sql = 'SELECT COUNT(*) FROM ' . self::ident(self::$table) . ' WHERE role = ?';
        return (int) $this->prepared($sql, [$role])->fetchColumn();
    }
}
