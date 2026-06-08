<?php

declare(strict_types=1);

namespace PayTracker\Models;

use PayTracker\Database\Model;
use PDO;
use Throwable;

/**
 * `audit_logs` — append-only event ledger.
 *
 * Public API is intentionally minimal:
 *   - record(...)       — insert a single event. Wrapped in a
 *                          try/catch so a logging failure NEVER
 *                          bubbles up and disrupts the action
 *                          being audited. (Auth must not refuse
 *                          a valid login because the audit DB
 *                          is full.)
 *   - recent(...)       — paginated list for the admin viewer,
 *                          with optional filters on user / action
 *                          / IP / date range.
 *   - countByAction(..) — group-by counters for the diagnostics
 *                          dashboard.
 *
 * Notably absent: update / delete. WORM is enforced at the API
 * surface — there is no method here that mutates an existing row.
 */
final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    // ---- Action constants. Kept here so callers don't drift into
    // freeform strings; phpstan catches a typo at the call site.

    public const ACTION_USER_LOGIN          = 'USER_LOGIN';
    public const ACTION_USER_LOGIN_FAILED   = 'USER_LOGIN_FAILED';
    public const ACTION_USER_LOGOUT         = 'USER_LOGOUT';
    public const ACTION_USER_BANNED         = 'USER_BANNED';
    public const ACTION_USER_UNBANNED       = 'USER_UNBANNED';
    public const ACTION_USER_DELETED        = 'USER_DELETED';
    public const ACTION_PASSWORD_RESET_SENT = 'PASSWORD_RESET_SENT';
    public const ACTION_PASSWORD_RESET_USED = 'PASSWORD_RESET_USED';
    /**
     * Auto-minted reset sent during /register because the typed email
     * already exists on a legacy account that has never signed in to
     * PayTracker 2.0. Distinct from PASSWORD_RESET_SENT so reporting
     * can tell admin-initiated resets apart from self-service ones.
     */
    public const ACTION_LEGACY_RESET_SENT   = 'LEGACY_RESET_SENT';
    public const ACTION_USER_ROLE_CHANGED   = 'USER_ROLE_CHANGED';
    public const ACTION_USER_EDITED         = 'USER_EDITED';
    public const ACTION_ANNOUNCEMENT_CREATED    = 'ANNOUNCEMENT_CREATED';
    public const ACTION_ANNOUNCEMENT_UPDATED    = 'ANNOUNCEMENT_UPDATED';
    public const ACTION_ANNOUNCEMENT_ACTIVATED  = 'ANNOUNCEMENT_ACTIVATED';
    public const ACTION_ANNOUNCEMENT_DEACTIVATED= 'ANNOUNCEMENT_DEACTIVATED';
    public const ACTION_ANNOUNCEMENT_DELETED    = 'ANNOUNCEMENT_DELETED';
    public const ACTION_ANNOUNCEMENT_DISMISSED  = 'ANNOUNCEMENT_DISMISSED';
    public const ACTION_INVITE_CREATED          = 'INVITE_CREATED';
    public const ACTION_INVITE_UPDATED          = 'INVITE_UPDATED';
    public const ACTION_INVITE_EMAILED          = 'INVITE_EMAILED';
    public const ACTION_INVITE_REVOKED          = 'INVITE_REVOKED';
    public const ACTION_INVITE_USED             = 'INVITE_USED';
    public const ACTION_USER_REGISTERED         = 'USER_REGISTERED';

    // ---- Failed-login reason constants. Mirrors the discriminators
    // in AuthService::attempt so a downstream "why is this account
    // failing to log in" query can group by reason.

    public const REASON_BAD_PASSWORD   = 'bad_password';
    public const REASON_NO_SUCH_USER   = 'no_such_user';
    public const REASON_BANNED         = 'banned';
    public const REASON_LOCKED         = 'locked';

    /**
     * Insert a single audit event. SWALLOWS exceptions: a failing
     * audit write must NEVER block the action being audited. The
     * caller therefore doesn't need to wrap their call in try/catch
     * for log-write errors.
     *
     * Why swallow rather than propagate: if the audit table is full
     * / locked / dropped, the right answer is "let the user keep
     * using the app while we figure it out" — not "lock everyone
     * out because the audit log broke". Failures land in the PHP
     * error log via the standard error handler.
     *
     * @param array<string,mixed>|null $metadata Optional structured
     *        context. Encoded to JSON; non-encodable values are
     *        silently dropped.
     */
    public function record(
        string $action,
        ?int $userId = null,
        ?string $reason = null,
        ?string $ipAddress = null,
        ?array $metadata = null,
    ): void {
        try {
            $metaJson = null;
            if (is_array($metadata) && $metadata !== []) {
                $metaJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
                if (! is_string($metaJson)) {
                    $metaJson = null;
                }
            }
            $sql = 'INSERT INTO `audit_logs`
                        (user_id, action, reason, ip_address, metadata, timestamp)
                    VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())';
            $this->prepared($sql, [$userId, $action, $reason, $ipAddress, $metaJson]);
        } catch (Throwable $e) {
            // Last-resort error log; do NOT rethrow.
            error_log('AuditLog::record failed: ' . $e->getMessage());
        }
    }

    /**
     * Most recent N events, optionally scoped to a user / action /
     * IP. Returns rows in newest-first order.
     *
     * @return list<array{
     *   id:int, user_id:?int, action:string, reason:?string,
     *   ip_address:?string, metadata:?string, timestamp:string,
     *   user:?string
     * }>
     */
    public function recent(
        int $limit = 100,
        ?int $userFilter = null,
        ?string $actionFilter = null,
        ?string $ipFilter = null,
    ): array {
        $limit  = max(1, min(500, $limit));
        $where  = [];
        $params = [];
        if ($userFilter !== null && $userFilter > 0) {
            $where[]  = 'l.user_id = ?';
            $params[] = $userFilter;
        }
        if (is_string($actionFilter) && $actionFilter !== '') {
            $where[]  = 'l.action = ?';
            $params[] = $actionFilter;
        }
        if (is_string($ipFilter) && $ipFilter !== '') {
            $where[]  = 'l.ip_address = ?';
            $params[] = $ipFilter;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        // LEFT JOIN account so deleted users still show up by id
        // with a null user handle (rather than disappearing).
        $sql = 'SELECT
                    l.id, l.user_id, l.action, l.reason,
                    l.ip_address, l.metadata, l.timestamp,
                    a.user AS user
                FROM `audit_logs` l
                LEFT JOIN `account` a ON a.id = l.user_id'
            . $whereSql
            . ' ORDER BY l.id DESC LIMIT ' . $limit;

        $rows = $this->prepared($sql, $params)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Distinct action strings present in the table. Powers the
     * action-filter dropdown in the viewer.
     *
     * @return list<string>
     */
    public function distinctActions(): array
    {
        $rows = $this->prepared(
            'SELECT DISTINCT action FROM `audit_logs` ORDER BY action ASC'
        )->fetchAll();
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['action'] ?? null)) {
                $out[] = (string) $row['action'];
            }
        }
        return $out;
    }

    /**
     * Grouped counters for the diagnostics dashboard: how many
     * USER_LOGIN_FAILED in the last hour, etc.
     *
     * @return array<string,int>
     */
    public function countByActionSince(string $sinceUtc): array
    {
        $sql = 'SELECT action, COUNT(*) AS n
                  FROM `audit_logs`
                 WHERE timestamp >= ?
                 GROUP BY action';
        $rows = $this->prepared($sql, [$sinceUtc])->fetchAll();
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['action'] ?? null)) {
                $out[(string) $row['action']] = (int) ($row['n'] ?? 0);
            }
        }
        return $out;
    }
}
