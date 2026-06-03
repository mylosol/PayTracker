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
}
