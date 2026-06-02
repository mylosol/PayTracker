<?php

declare(strict_types=1);

namespace PayTracker\Models;

use InvalidArgumentException;
use PayTracker\Database\Connection;
use PayTracker\Database\Model;

/**
 * `pay_variables` — relational replacement for variablesDefault /
 * variablesCurrent / variablesTest. Holds the global constants the np/op
 * formula reads (raise, demurrage CPM, trainer_pay, per-tenure {mt,
 * newBump, night, wk, tb}).
 *
 * Same default/current/draft staging model as PayRate, but variables
 * don't vary per terminal or trip_type so the PK is just (stage, variable).
 *
 * The model intentionally does NOT enforce a known-variable allowlist:
 * the legacy formula's variable set is large and evolving, and pinning
 * it here would force a code change every time someone added a new
 * variable. The PayCalculator is the single point that knows which
 * variables it consumes — invalid lookups there fall through to sensible
 * defaults (zero), which is the same behaviour the legacy SQL would
 * produce when a SELECT found nothing.
 */
class PayVariable extends Model
{
    protected static string $table = 'pay_variables';

    /** @var list<string> */
    public const STAGES = ['default', 'current', 'draft'];

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
    }

    private function assertStage(string $stage): void
    {
        if (! in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException("Unknown stage: {$stage}");
        }
    }

    /**
     * All variables for a given stage as an associative variable → amount
     * map. The PayCalculator consumes this shape directly.
     *
     * Amounts are returned as STRINGS (the legacy DECIMAL column) so the
     * caller chooses the cast — most callers want float, the admin UI
     * wants the literal string.
     *
     * @return array<string, string>
     */
    public function allByStage(string $stage): array
    {
        $this->assertStage($stage);
        $sql = 'SELECT variable, amount FROM ' . self::ident(self::$table)
             . ' WHERE stage = ? ORDER BY variable ASC';
        $rows = $this->prepared($sql, [$stage])->fetchAll();
        if (! is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['variable']] = (string) $r['amount'];
        }
        return $out;
    }

    /**
     * Single-variable lookup. Returns null if the variable doesn't exist
     * in this stage. The PayCalculator should treat null as zero — that's
     * how the legacy SQL behaved (a missing row in variablesCurrent left
     * the PHP variable uninitialised, which compared as 0 in arithmetic).
     */
    public function get(string $stage, string $variable): ?string
    {
        $this->assertStage($stage);
        $sql = 'SELECT amount FROM ' . self::ident(self::$table)
             . ' WHERE stage = ? AND variable = ? LIMIT 1';
        $stmt = $this->prepared($sql, [$stage, $variable]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (string) $val;
    }

    /**
     * Counters for the /pay-admin dashboard. Same shape as PayRate::summary
     * but stripped of per-(terminal, trip_type) breakdown.
     *
     * @return array{total_rows:int, by_stage: array<string,int>}
     */
    public function summary(): array
    {
        $pdo = $this->connection->pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM ' . self::ident(self::$table))->fetchColumn();

        $byStage = [];
        $stmt = $pdo->query('SELECT stage, COUNT(*) AS n FROM ' . self::ident(self::$table) . ' GROUP BY stage');
        foreach ($stmt as $row) {
            $byStage[(string) $row['stage']] = (int) $row['n'];
        }
        return ['total_rows' => $total, 'by_stage' => $byStage];
    }

    public function hasDraft(): bool
    {
        $sql = 'SELECT 1 FROM ' . self::ident(self::$table) . ' WHERE stage = ? LIMIT 1';
        return $this->prepared($sql, ['draft'])->fetchColumn() !== false;
    }

    /**
     * Copy current → draft. Replaces any existing draft for the same
     * "start fresh" semantics as the legacy "Reset Test" button. Atomic.
     */
    public function startOrResetDraft(): void
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ' . self::ident(self::$table) . ' WHERE stage = ?')
                ->execute(['draft']);
            $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (stage, variable, amount)
                 SELECT ?, variable, amount FROM ' . self::ident(self::$table) . ' WHERE stage = ?'
            )->execute(['draft', 'current']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Promote draft → current. Atomic. Clears the draft afterwards.
     */
    public function promoteDraftToCurrent(): void
    {
        if (! $this->hasDraft()) {
            throw new InvalidArgumentException('No draft exists — nothing to promote.');
        }
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ' . self::ident(self::$table) . ' WHERE stage = ?')
                ->execute(['current']);
            $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (stage, variable, amount)
                 SELECT ?, variable, amount FROM ' . self::ident(self::$table) . ' WHERE stage = ?'
            )->execute(['current', 'draft']);
            $pdo->prepare('DELETE FROM ' . self::ident(self::$table) . ' WHERE stage = ?')
                ->execute(['draft']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reset current ← default. Atomic. Clears any draft.
     */
    public function resetCurrentToDefault(): void
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ' . self::ident(self::$table) . ' WHERE stage = ?')
                ->execute(['current']);
            $pdo->prepare('DELETE FROM ' . self::ident(self::$table) . ' WHERE stage = ?')
                ->execute(['draft']);
            $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (stage, variable, amount)
                 SELECT ?, variable, amount FROM ' . self::ident(self::$table) . ' WHERE stage = ?'
            )->execute(['current', 'default']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Insert OR update a single draft variable. Auto-starts a draft from
     * current if none exists. Same DX pattern as PayRate::upsertDraftTier.
     */
    public function upsertDraft(string $variable, string $amount): void
    {
        if ($variable === '') {
            throw new InvalidArgumentException('variable must not be empty');
        }
        if (! preg_match('/^-?\d+(\.\d{1,6})?$/', $amount)) {
            throw new InvalidArgumentException("amount not numeric: {$amount}");
        }
        if (! $this->hasDraft()) {
            $this->startOrResetDraft();
        }
        $sql = 'INSERT INTO ' . self::ident(self::$table) . ' (stage, variable, amount)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE amount = VALUES(amount)';
        $this->prepared($sql, ['draft', $variable, $amount]);
    }
}
