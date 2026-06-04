<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Database\Connection;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;

/**
 * AdminAuditController — read-only viewer for the audit_logs trail
 * and a system-diagnostics card alongside it.
 *
 * Routes (admin+):
 *   GET /admin/audit         — paginated audit log with filters
 *   GET /admin/diagnostics   — PHP/DB version, migration tail,
 *                              recent audit counters
 *
 * No write surface. The audit log is append-only on purpose —
 * the model itself doesn't expose UPDATE / DELETE, and this
 * controller doesn't even try.
 */
final class AdminAuditController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditLog $audit,
        private readonly Connection $connection,
    ) {
    }

    /**
     * GET /admin/audit — recent events, scoped by optional filters.
     *
     * Query params:
     *   user   — exact account id (int)
     *   action — exact action constant (string)
     *   ip     — exact IP address (string)
     *   limit  — 1..500, default 100
     */
    public function audit(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }

        $userRaw   = (string) $request->input('user', '');
        $actionRaw = (string) $request->input('action', '');
        $ipRaw     = (string) $request->input('ip', '');
        $limitRaw  = (string) $request->input('limit', '100');

        $userFilter   = ctype_digit($userRaw)  && (int) $userRaw  > 0 ? (int) $userRaw  : null;
        $limit        = ctype_digit($limitRaw) && (int) $limitRaw > 0 ? (int) $limitRaw : 100;
        $actionFilter = $actionRaw !== '' ? $actionRaw : null;
        $ipFilter     = $ipRaw     !== '' ? $ipRaw     : null;

        $rows = $this->audit->recent($limit, $userFilter, $actionFilter, $ipFilter);

        return $this->view('admin/audit', [
            'base'    => $request->basePath(),
            'actor'   => $account,
            'rows'    => $rows,
            'actions' => $this->audit->distinctActions(),
            'filters' => [
                'user'   => $userRaw,
                'action' => $actionRaw,
                'ip'     => $ipRaw,
                'limit'  => $limit,
            ],
        ]);
    }

    /**
     * GET /admin/diagnostics — runtime fingerprint + recent
     * audit counters. Surfaces enough to answer "is the host
     * healthy and is anyone failing to log in" at a glance.
     */
    public function diagnostics(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }

        $pdo = $this->connection->pdo();

        // DB-side diagnostics. Each query is wrapped so a failure on
        // any one (permissions, missing table) downgrades to "unknown"
        // rather than 500ing the whole page.
        $dbVersion       = $this->safeScalar($pdo, 'SELECT VERSION()');
        $dbNow           = $this->safeScalar($pdo, 'SELECT UTC_TIMESTAMP()');
        $migrationsTail  = $this->migrationsTail();
        $accountCount    = $this->safeScalar($pdo, 'SELECT COUNT(*) FROM `account`');
        $auditCount      = $this->safeScalar($pdo, 'SELECT COUNT(*) FROM `audit_logs`');

        // Last hour of audit counters: catches a spike of
        // USER_LOGIN_FAILED at a glance.
        $sinceHour       = gmdate('Y-m-d H:i:s', time() - 3600);
        $hourCounters    = $this->audit->countByActionSince($sinceHour);
        ksort($hourCounters);

        return $this->view('admin/diagnostics', [
            'base'         => $request->basePath(),
            'actor'        => $account,
            'php' => [
                'version'     => PHP_VERSION,
                'sapi'        => PHP_SAPI,
                'os'          => PHP_OS_FAMILY,
                'memoryLimit' => (string) ini_get('memory_limit'),
                'timezone'    => (string) date_default_timezone_get(),
            ],
            'db' => [
                'version'      => $dbVersion,
                'now_utc'      => $dbNow,
                'account_rows' => $accountCount,
                'audit_rows'   => $auditCount,
            ],
            'migrationsTail' => $migrationsTail,
            'hourCounters'   => $hourCounters,
            'sinceHour'      => $sinceHour,
        ]);
    }

    /**
     * Return the value of a single-cell scalar query, or 'unknown'
     * if the query fails. Used by diagnostics for soft-fail
     * version / count probes.
     */
    private function safeScalar(\PDO $pdo, string $sql): string
    {
        try {
            $stmt = $pdo->query($sql);
            if ($stmt === false) {
                return 'unknown';
            }
            $val = $stmt->fetchColumn();
            return $val === false || $val === null ? 'unknown' : (string) $val;
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Last 10 migrations from the _migrations tracking table.
     * Returns an empty list if the table doesn't exist, so a
     * fresh-deploy diagnostics page doesn't 500.
     *
     * @return list<array{name:string, applied_at:string}>
     */
    private function migrationsTail(): array
    {
        try {
            $pdo  = $this->connection->pdo();
            $stmt = $pdo->query(
                'SELECT name, applied_at FROM `_migrations`
                 ORDER BY id DESC LIMIT 10'
            );
            if ($stmt === false) {
                return [];
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $out  = [];
            foreach ($rows as $row) {
                if (is_array($row) && is_string($row['name'] ?? null) && is_string($row['applied_at'] ?? null)) {
                    $out[] = ['name' => (string) $row['name'], 'applied_at' => (string) $row['applied_at']];
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
