<?php

declare(strict_types=1);

namespace PayTracker\Models;

use InvalidArgumentException;
use PDO;
use PayTracker\Database\Connection;
use PayTracker\Database\Model;

/**
 * `pay_rates` — relational replacement for the six legacy pay-rate tables
 * (PensacolaPayDefault/Current, LHPensacolaPayDefault/Current, PanamaPay).
 *
 * Schema (migration 2026_05_28_001):
 *   terminal   VARCHAR(16)
 *   trip_type  VARCHAR(16)
 *   stage      VARCHAR(8)         — 'default' | 'current' | 'draft'
 *   miles      SMALLINT UNSIGNED
 *   rate       DECIMAL(10,4)
 *   PRIMARY KEY (terminal, trip_type, stage, miles)
 *
 * Editing model
 *   - 'default' is read-mostly. The only mutator is resetCurrentToDefault().
 *   - 'current' is the live pay-rate table read by future PayCalculator.
 *     Admins do not edit it directly; they edit drafts and promote.
 *   - 'draft' is admin's per-terminal working copy. Add/edit/delete tiers
 *     freely, then promoteDraftToCurrent() to publish. resetDraftToCurrent()
 *     discards the draft and starts again from live.
 *
 * Concurrency: promote and reset operations run inside a single transaction
 * so a partial failure can't leave the live table half-replaced.
 */
final class PayRate extends Model
{
    protected static string $table = 'pay_rates';

    /** @var list<string> */
    public const TERMINALS = ['pensacola', 'panama'];

    /** @var list<string> */
    public const TRIP_TYPES = ['round_trip', 'long_haul'];

    /** @var list<string> */
    public const STAGES = ['default', 'current', 'draft'];

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
    }

    private function assertTerminal(string $terminal): void
    {
        if (! in_array($terminal, self::TERMINALS, true)) {
            throw new InvalidArgumentException("Unknown terminal: {$terminal}");
        }
    }

    private function assertTripType(string $tripType): void
    {
        if (! in_array($tripType, self::TRIP_TYPES, true)) {
            throw new InvalidArgumentException("Unknown trip_type: {$tripType}");
        }
    }

    private function assertStage(string $stage): void
    {
        if (! in_array($stage, self::STAGES, true)) {
            throw new InvalidArgumentException("Unknown stage: {$stage}");
        }
    }

    /**
     * Aggregate counters for the /pay-admin dashboard. Always returns the
     * same shape (zeroes when the backfill hasn't run) so the view doesn't
     * have to defend against missing keys.
     *
     * @return array{
     *     total_rows:int,
     *     by_stage: array<string,int>,
     *     by_terminal_trip: list<array{terminal:string, trip_type:string, default_n:int, current_n:int, draft_n:int}>
     * }
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

        // Per (terminal, trip_type) breakdown — drives the dashboard tabs.
        $breakdown = [];
        $stmt = $pdo->query(
            'SELECT terminal, trip_type, stage, COUNT(*) AS n
             FROM ' . self::ident(self::$table) . '
             GROUP BY terminal, trip_type, stage'
        );
        $bucket = [];
        foreach ($stmt as $row) {
            $key = (string) $row['terminal'] . '|' . (string) $row['trip_type'];
            $bucket[$key]['terminal']   = (string) $row['terminal'];
            $bucket[$key]['trip_type']  = (string) $row['trip_type'];
            $bucket[$key]['default_n']  = $bucket[$key]['default_n']  ?? 0;
            $bucket[$key]['current_n']  = $bucket[$key]['current_n']  ?? 0;
            $bucket[$key]['draft_n']    = $bucket[$key]['draft_n']    ?? 0;
            $bucket[$key][$row['stage'] . '_n'] = (int) $row['n'];
        }
        ksort($bucket);
        foreach ($bucket as $b) {
            $breakdown[] = [
                'terminal'  => $b['terminal'],
                'trip_type' => $b['trip_type'],
                'default_n' => $b['default_n'],
                'current_n' => $b['current_n'],
                'draft_n'   => $b['draft_n'],
            ];
        }

        return [
            'total_rows'       => $total,
            'by_stage'         => $byStage,
            'by_terminal_trip' => $breakdown,
        ];
    }

    /**
     * All (miles, rate) tiers for a given (terminal, trip_type, stage)
     * sorted ascending by miles — the natural order for the editor UI.
     *
     * @return list<array{miles:int, rate:string}>
     */
    public function tiers(string $terminal, string $tripType, string $stage): array
    {
        $this->assertTerminal($terminal);
        $this->assertTripType($tripType);
        $this->assertStage($stage);

        $sql = 'SELECT miles, rate FROM ' . self::ident(self::$table) . '
                WHERE terminal = ? AND trip_type = ? AND stage = ?
                ORDER BY miles ASC';
        $rows = $this->prepared($sql, [$terminal, $tripType, $stage])->fetchAll();
        if (! is_array($rows)) {
            return [];
        }
        return array_map(
            static fn (array $r): array => [
                'miles' => (int) $r['miles'],
                'rate'  => (string) $r['rate'],
            ],
            $rows,
        );
    }

    /**
     * True if a draft exists for (terminal, trip_type). Used by the UI to
     * decide whether to show "edit draft" vs "start draft from current".
     */
    public function hasDraft(string $terminal, string $tripType): bool
    {
        $this->assertTerminal($terminal);
        $this->assertTripType($tripType);

        $sql = 'SELECT 1 FROM ' . self::ident(self::$table) . '
                WHERE terminal = ? AND trip_type = ? AND stage = ?
                LIMIT 1';
        $stmt = $this->prepared($sql, [$terminal, $tripType, 'draft']);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Copy current → draft for (terminal, trip_type). Replaces any existing
     * draft for that bucket (the "start fresh" semantics of the legacy
     * "Reset Test" button). Runs in a transaction so a partial state can't
     * persist on failure.
     */
    public function startOrResetDraft(string $terminal, string $tripType): void
    {
        $this->assertTerminal($terminal);
        $this->assertTripType($tripType);

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare(
                'DELETE FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $del->execute([$terminal, $tripType, 'draft']);

            $ins = $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (terminal, trip_type, stage, miles, rate)
                 SELECT terminal, trip_type, ?, miles, rate
                 FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $ins->execute(['draft', $terminal, $tripType, 'current']);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Promote draft → current for (terminal, trip_type). Atomically replaces
     * all current rows with draft rows, then clears the draft so the editor
     * UI shows a clean "no pending changes" state.
     *
     * Throws if there's no draft to promote — the caller is expected to
     * gate on hasDraft() first.
     */
    public function promoteDraftToCurrent(string $terminal, string $tripType): void
    {
        $this->assertTerminal($terminal);
        $this->assertTripType($tripType);

        if (! $this->hasDraft($terminal, $tripType)) {
            throw new InvalidArgumentException(
                "No draft exists for {$terminal}/{$tripType} — nothing to promote.",
            );
        }

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare(
                'DELETE FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $del->execute([$terminal, $tripType, 'current']);

            $ins = $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (terminal, trip_type, stage, miles, rate)
                 SELECT terminal, trip_type, ?, miles, rate
                 FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $ins->execute(['current', $terminal, $tripType, 'draft']);

            // Clear the draft after a successful promote — matches the
            // legacy "draft auto-clears after going live" intent.
            $del->execute([$terminal, $tripType, 'draft']);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reset current → default for (terminal, trip_type). The legacy
     * "Reset to defaults" workflow. Also clears any in-flight draft so
     * the UI doesn't show pending edits against now-stale current rates.
     */
    public function resetCurrentToDefault(string $terminal, string $tripType): void
    {
        $this->assertTerminal($terminal);
        $this->assertTripType($tripType);

        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare(
                'DELETE FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $del->execute([$terminal, $tripType, 'current']);
            $del->execute([$terminal, $tripType, 'draft']);

            $ins = $pdo->prepare(
                'INSERT INTO ' . self::ident(self::$table) . ' (terminal, trip_type, stage, miles, rate)
                 SELECT terminal, trip_type, ?, miles, rate
                 FROM ' . self::ident(self::$table) . '
                 WHERE terminal = ? AND trip_type = ? AND stage = ?'
            );
            $ins->execute(['current', $terminal, $tripType, 'default']);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Insert OR update a draft tier (miles, rate). Used by the editor's
     * inline-save and add-tier forms. Auto-starts a draft from current if
     * none exists yet — matches the natural "I want to change this tier"
     * intent without forcing a separate "start draft" click.
     */
    public function upsertDraftTier(string $terminal, string $tripType, int $miles, string $rate): void
    {
        $this->assertTerminal($terminal);
        $this->assertTripType($tripType);

        if ($miles <= 0 || $miles > 65535) {
            throw new InvalidArgumentException("miles out of range: {$miles}");
        }
        if (! preg_match('/^\d+(\.\d{1,4})?$/', $rate)) {
            throw new InvalidArgumentException("rate not in dollars(.cents) form: {$rate}");
        }

        if (! $this->hasDraft($terminal, $tripType)) {
            $this->startOrResetDraft($terminal, $tripType);
        }

        $sql = 'INSERT INTO ' . self::ident(self::$table) . ' (terminal, trip_type, stage, miles, rate)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rate = VALUES(rate)';
        $this->prepared($sql, [$terminal, $tripType, 'draft', $miles, $rate]);
    }

    /**
     * Delete a draft tier (miles). Auto-starts a draft from current if none
     * exists — same DX consideration as upsertDraftTier.
     */
    public function deleteDraftTier(string $terminal, string $tripType, int $miles): void
    {
        $this->assertTerminal($terminal);
        $this->assertTripType($tripType);

        if (! $this->hasDraft($terminal, $tripType)) {
            $this->startOrResetDraft($terminal, $tripType);
        }

        $sql = 'DELETE FROM ' . self::ident(self::$table) . '
                WHERE terminal = ? AND trip_type = ? AND stage = ? AND miles = ?';
        $this->prepared($sql, [$terminal, $tripType, 'draft', $miles]);
    }
}
