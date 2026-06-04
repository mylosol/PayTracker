<?php
/**
 * @var string                     $base
 * @var array<string,mixed>        $actor
 * @var list<array<string,mixed>>  $rows
 * @var list<string>               $actions
 * @var array{user:string,action:string,ip:string,limit:int} $filters
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Audit log <span class="pill ok"><?= e((string) $actor['role']) ?></span></h1>
    <p class="muted">
        Append-only event ledger. Filter by user id, action, or IP.
        Showing the most recent <?= (int) $filters['limit'] ?> rows
        (max 500).
    </p>
    <p>
        <a href="<?= e($base) ?>/admin">&larr; Admin Panel</a> &middot;
        <a href="<?= e($base) ?>/admin/diagnostics">System diagnostics &rarr;</a>
    </p>
</div>

<div class="card">
    <form method="get" action="<?= e($base) ?>/admin/audit"
          style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;">
        <label>
            <strong>User id</strong><br>
            <input type="number" name="user" min="1" value="<?= e($filters['user']) ?>"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:4px;width:8rem;">
        </label>
        <label>
            <strong>Action</strong><br>
            <select name="action"
                    style="padding:.4rem;border:1px solid #cbd2da;border-radius:4px;width:14rem;">
                <option value="">Any</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>>
                        <?= e($a) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <strong>IP address</strong><br>
            <input type="text" name="ip" value="<?= e($filters['ip']) ?>" maxlength="45"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:4px;width:12rem;">
        </label>
        <label>
            <strong>Limit</strong><br>
            <input type="number" name="limit" min="1" max="500" value="<?= (int) $filters['limit'] ?>"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:4px;width:6rem;">
        </label>
        <button type="submit"
                style="background:var(--accent);color:#fff;border:0;padding:.5rem 1rem;border-radius:4px;font:inherit;cursor:pointer;">
            Apply
        </button>
        <a href="<?= e($base) ?>/admin/audit"
           style="align-self:center;text-decoration:none;">
            Clear
        </a>
    </form>
</div>

<div class="card">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="text-align:left;border-bottom:2px solid #cbd2da;">
                <th style="padding:.4rem .25rem;">Time (UTC)</th>
                <th style="padding:.4rem .25rem;">User</th>
                <th style="padding:.4rem .25rem;">Action</th>
                <th style="padding:.4rem .25rem;">Reason</th>
                <th style="padding:.4rem .25rem;">IP</th>
                <th style="padding:.4rem .25rem;">Metadata</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($rows) === 0): ?>
                <tr><td colspan="6" style="padding:.6rem;color:#5a6470;">No events match the current filter.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $userId   = isset($r['user_id']) ? (int) $r['user_id'] : null;
                $userName = is_string($r['user'] ?? null) ? (string) $r['user'] : null;
                $action   = (string) ($r['action'] ?? '');
                $isFail   = str_contains($action, 'FAILED');
                $isBan    = str_contains($action, 'BANNED') || str_contains($action, 'DELETED');
                ?>
                <tr style="border-bottom:1px solid #e4e8ee;<?= $isFail ? 'background:#fef2f2;' : '' ?>">
                    <td style="padding:.4rem .25rem;white-space:nowrap;"><?= e((string) ($r['timestamp'] ?? '')) ?></td>
                    <td style="padding:.4rem .25rem;">
                        <?php if ($userId !== null): ?>
                            <code><?= $userId ?></code>
                            <?php if ($userName !== null): ?>
                                <span class="muted"><?= e($userName) ?></span>
                            <?php else: ?>
                                <span class="muted">(deleted)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.4rem .25rem;">
                        <code style="background:<?= $isFail ? '#fee2e2' : ($isBan ? '#fef9c3' : '#eef2f7') ?>;">
                            <?= e($action) ?>
                        </code>
                    </td>
                    <td style="padding:.4rem .25rem;"><code><?= e((string) ($r['reason'] ?? '—')) ?></code></td>
                    <td style="padding:.4rem .25rem;"><code><?= e((string) ($r['ip_address'] ?? '—')) ?></code></td>
                    <td style="padding:.4rem .25rem;max-width:30rem;overflow-wrap:anywhere;">
                        <?php if (is_string($r['metadata'] ?? null) && $r['metadata'] !== ''): ?>
                            <code style="font-size:11px;"><?= e((string) $r['metadata']) ?></code>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
