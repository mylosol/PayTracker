<?php

declare(strict_types=1);

namespace PayTracker\Models;

use InvalidArgumentException;
use PayTracker\Database\Model;

/**
 * `pay_reconciliations` — per-load paid / short / disputed state.
 *
 * One row per (driver_id, frtl) once the driver has acted on a load.
 * No row → the load is still pending reconcile. The UNIQUE key on
 * (driver_id, frtl) guarantees a single open claim per load; the
 * driver can flip state by re-acting, but the row count stays at 1.
 *
 * `expected_np` is snapshotted at reconcile time so historical claims
 * survive later rate edits or PayCalculator changes. Re-marking the
 * same load updates `expected_np` to whatever's current; the dispute
 * note + state captures the driver's intent at the time they acted.
 *
 * Email batch design:
 *   - notify_email = 1 marks a row for inclusion in the NEXT batch
 *     the driver triggers; setting it does not send anything.
 *   - emailed_at IS NULL means the row has never been included in a
 *     batch. A successful batch send stamps emailed_at on every row
 *     it covered, taking them out of the pending pool.
 *   - (notify_email = 1 AND emailed_at IS NULL) is the
 *     pending-batch predicate the UI counts on.
 *
 * The actual mail-send is in the controller; this model exposes the
 * data layer only.
 */
final class PayReconciliation extends Model
{
    protected static string $table = 'pay_reconciliations';

    public const STATE_PAID     = 'paid';
    public const STATE_SHORT    = 'short';
    public const STATE_DISPUTED = 'disputed';

    /** @var list<string> */
    public const STATES = [self::STATE_PAID, self::STATE_SHORT, self::STATE_DISPUTED];

    /**
     * Look up the reconcile row for a load. Returns null when the
     * driver hasn't acted on it yet (= pending).
     *
     * @return array<string,mixed>|null
     */
    public function findForLoad(int $driverId, int $frtl): ?array
    {
        $sql = 'SELECT id, driver_id, frtl, state, expected_np, actual_np, shortfall,
                       note, notify_email, emailed_at, reconciled_at, updated_at
                FROM ' . self::ident(self::$table) . '
                WHERE driver_id = ? AND frtl = ? LIMIT 1';
        $row = $this->prepared($sql, [$driverId, $frtl])->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Upsert a reconcile row to the given state. Re-marking the same
     * load updates state + expected_np + actual_np + note in place;
     * notify_email + emailed_at are preserved across re-marks
     * (re-marking a previously batched disputed row does NOT re-queue
     * it for another batch).
     *
     * @throws InvalidArgumentException on unknown state.
     */
    public function upsert(
        int $driverId,
        int $frtl,
        string $state,
        float $expectedNp,
        ?float $actualNp,
        ?float $shortfall,
        ?string $note,
        bool $notifyEmail,
    ): void {
        if (! in_array($state, self::STATES, true)) {
            throw new InvalidArgumentException("Unknown reconcile state: {$state}");
        }
        // Sanitise the note: trim, collapse to NULL if empty, cap at
        // 4k so a runaway paste can't bloat the row.
        if ($note !== null) {
            $note = trim($note);
            if ($note === '') {
                $note = null;
            } elseif (mb_strlen($note) > 4000) {
                $note = mb_substr($note, 0, 4000);
            }
        }

        // INSERT ... ON DUPLICATE KEY UPDATE keeps notify_email + emailed_at
        // when the row already exists; the driver flips state on those
        // columns separately via setNotifyFlag() so a state edit doesn't
        // silently re-queue a previously-sent dispute.
        $sql = 'INSERT INTO ' . self::ident(self::$table) . '
                    (driver_id, frtl, state, expected_np, actual_np, shortfall, note, notify_email, reconciled_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    state         = VALUES(state),
                    expected_np   = VALUES(expected_np),
                    actual_np     = VALUES(actual_np),
                    shortfall     = VALUES(shortfall),
                    note          = VALUES(note),
                    updated_at    = NOW()';
        $this->prepared($sql, [
            $driverId,
            $frtl,
            $state,
            number_format($expectedNp, 2, '.', ''),
            $actualNp  !== null ? number_format($actualNp,  2, '.', '') : null,
            $shortfall !== null ? number_format($shortfall, 2, '.', '') : null,
            $note,
            $notifyEmail ? 1 : 0,
        ]);
    }

    /**
     * Flip the "include in next batch" flag without re-stamping state.
     * Used by the dedicated checkbox + the dispute form. Setting to
     * true on a row whose emailed_at is non-null resets emailed_at to
     * NULL — the driver explicitly asked for another notification, so
     * include it in the next batch.
     */
    public function setNotifyFlag(int $driverId, int $frtl, bool $notify): void
    {
        if ($notify) {
            $sql = 'UPDATE ' . self::ident(self::$table) . '
                    SET notify_email = 1,
                        emailed_at   = NULL,
                        updated_at   = NOW()
                    WHERE driver_id = ? AND frtl = ?';
        } else {
            $sql = 'UPDATE ' . self::ident(self::$table) . '
                    SET notify_email = 0,
                        updated_at   = NOW()
                    WHERE driver_id = ? AND frtl = ?';
        }
        $this->prepared($sql, [$driverId, $frtl]);
    }

    /**
     * Drop the reconcile row entirely. The load returns to "pending"
     * — there is no audit trail in this table for the prior state.
     * The audit_logs entry written by the controller carries the
     * before/after for compliance review.
     */
    public function undo(int $driverId, int $frtl): bool
    {
        $sql = 'DELETE FROM ' . self::ident(self::$table) . '
                WHERE driver_id = ? AND frtl = ?';
        $stmt = $this->prepared($sql, [$driverId, $frtl]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Pull every reconcile row a driver has touched, keyed by frtl,
     * for the reconcile view's per-load state lookup.
     *
     * @return array<int, array<string,mixed>>
     */
    public function byFrtlForDriver(int $driverId, array $frtls): array
    {
        $frtls = array_values(array_unique(array_map('intval', $frtls)));
        if ($frtls === []) return [];
        $placeholders = implode(',', array_fill(0, count($frtls), '?'));
        $sql = 'SELECT id, frtl, state, expected_np, actual_np, shortfall,
                       note, notify_email, emailed_at, reconciled_at, updated_at
                FROM ' . self::ident(self::$table) . '
                WHERE driver_id = ?
                  AND frtl IN (' . $placeholders . ')';
        $rows = $this->prepared($sql, array_merge([$driverId], $frtls))->fetchAll();
        if (! is_array($rows)) return [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['frtl']] = $r;
        }
        return $out;
    }

    /**
     * Rows opted into the next batch but not yet sent.
     *
     * @return list<array<string,mixed>>
     */
    public function pendingBatchForDriver(int $driverId): array
    {
        $sql = 'SELECT id, frtl, state, expected_np, actual_np, shortfall,
                       note, reconciled_at
                FROM ' . self::ident(self::$table) . '
                WHERE driver_id = ?
                  AND notify_email = 1
                  AND emailed_at IS NULL
                ORDER BY reconciled_at DESC';
        $rows = $this->prepared($sql, [$driverId])->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * Stamp emailed_at on a set of rows after a successful batch send.
     * Scoped to the driver so a malicious POST with someone else's
     * ids can't reach across accounts.
     */
    public function markBatchSent(int $driverId, array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'UPDATE ' . self::ident(self::$table) . '
                SET emailed_at = NOW(), updated_at = NOW()
                WHERE driver_id = ?
                  AND id IN (' . $placeholders . ')
                  AND notify_email = 1
                  AND emailed_at IS NULL';
        $stmt = $this->prepared($sql, array_merge([$driverId], $ids));
        return $stmt->rowCount();
    }

    /**
     * Super-Admin queue: every open dispute across every driver, with
     * the driver's display name + login email for context. Excludes
     * paid + short states — only the formally-disputed rows matter to
     * the operator.
     *
     * @return list<array<string,mixed>>
     */
    public function openDisputesForAdmin(int $limit = 200): array
    {
        $sql = 'SELECT r.id, r.driver_id, r.frtl, r.expected_np, r.actual_np, r.shortfall,
                       r.note, r.notify_email, r.emailed_at, r.reconciled_at, r.updated_at,
                       a.user           AS driver_user,
                       a.email          AS driver_email,
                       a.payroll_email  AS payroll_email
                FROM ' . self::ident(self::$table) . ' AS r
                JOIN `account` AS a ON a.id = r.driver_id
                WHERE r.state = ?
                ORDER BY r.reconciled_at DESC
                LIMIT ' . (int) $limit;
        $rows = $this->prepared($sql, [self::STATE_DISPUTED])->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
