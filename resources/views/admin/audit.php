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
    <h1 class="m-0">Audit log
        <span class="pill ok align-middle ml-1 text-xs"><?= e((string) $actor['role']) ?></span>
    </h1>
    <p class="text-brand-muted mt-2">
        Append-only event ledger. Filter by user id, action, or IP.
        Showing the most recent <?= (int) $filters['limit'] ?> rows
        (max 500).
    </p>
    <p class="text-sm mt-4 flex flex-wrap items-center gap-x-2 gap-y-1">
        <a href="<?= e($base) ?>/admin">← Admin Panel</a>
        <span class="text-brand-muted">·</span>
        <a href="<?= e($base) ?>/admin/diagnostics">System diagnostics →</a>
    </p>
</div>

<div class="card">
    <form method="get" action="<?= e($base) ?>/admin/audit"
          class="grid grid-cols-2 sm:grid-cols-4 lg:flex lg:flex-wrap items-end gap-3">
        <div>
            <label for="audit-user" class="field-label">User id</label>
            <input id="audit-user" type="number" name="user" min="1" value="<?= e($filters['user']) ?>"
                   class="field w-32">
        </div>
        <div>
            <label for="audit-action" class="field-label">Action</label>
            <select id="audit-action" name="action" class="field-select w-56">
                <option value="">Any</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>>
                        <?= e($a) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="audit-ip" class="field-label">IP address</label>
            <input id="audit-ip" type="text" name="ip" value="<?= e($filters['ip']) ?>" maxlength="45"
                   class="field w-48">
        </div>
        <div>
            <label for="audit-limit" class="field-label">Limit</label>
            <input id="audit-limit" type="number" name="limit" min="1" max="500" value="<?= (int) $filters['limit'] ?>"
                   class="field w-24">
        </div>
        <div class="flex items-center gap-2 col-span-2 sm:col-auto">
            <button type="submit" class="btn-primary btn-sm">Apply</button>
            <?php if ($filters['user'] !== '' || $filters['action'] !== '' || $filters['ip'] !== ''): ?>
                <a href="<?= e($base) ?>/admin/audit" class="text-sm">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table text-[13px]">
            <thead>
                <tr>
                    <th>Time (local)</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Reason</th>
                    <th>IP</th>
                    <th>Metadata</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) === 0): ?>
                    <tr><td colspan="6" class="text-brand-muted">No events match the current filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $userId   = isset($r['user_id']) ? (int) $r['user_id'] : null;
                    $userName = is_string($r['user'] ?? null) ? (string) $r['user'] : null;
                    $action   = (string) ($r['action'] ?? '');
                    $isFail   = str_contains($action, 'FAILED');
                    $isBan    = str_contains($action, 'BANNED') || str_contains($action, 'DELETED');
                    $actionBg = $isFail ? 'bg-rose-100' : ($isBan ? 'bg-amber-100' : 'bg-slate-100');
                    ?>
                    <tr class="<?= $isFail ? 'bg-rose-50/60' : '' ?>">
                        <?php
                            // Emit as ISO-8601 UTC so the JS convertor
                            // can parse cleanly. DB column is a plain
                            // MySQL DATETIME stored in UTC (see
                            // AuditLog::record → UTC_TIMESTAMP()), so
                            // we swap the space for a "T" and append "Z".
                            $raw = (string) ($r['timestamp'] ?? '');
                            $iso = $raw !== '' ? str_replace(' ', 'T', $raw) . 'Z' : '';
                        ?>
                        <td class="whitespace-nowrap">
                            <time class="js-local-time"
                                  datetime="<?= e($iso) ?>"
                                  title="<?= e($raw) ?> UTC"><?= e($raw) ?></time>
                        </td>
                        <td>
                            <?php if ($userId !== null): ?>
                                <code><?= $userId ?></code>
                                <?php if ($userName !== null): ?>
                                    <span class="text-brand-muted"><?= e($userName) ?></span>
                                <?php else: ?>
                                    <span class="text-brand-muted">(deleted)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-brand-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code class="<?= $actionBg ?>"><?= e($action) ?></code>
                        </td>
                        <td><code><?= e((string) ($r['reason'] ?? '—')) ?></code></td>
                        <td><code><?= e((string) ($r['ip_address'] ?? '—')) ?></code></td>
                        <td class="max-w-md break-words">
                            <?php if (is_string($r['metadata'] ?? null) && $r['metadata'] !== ''): ?>
                                <code class="text-[11px]"><?= e((string) $r['metadata']) ?></code>
                            <?php else: ?>
                                <span class="text-brand-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Convert every <time class="js-local-time" datetime="..."> element
    // to the viewer's local timezone. Server stores UTC (fine for
    // multi-driver correlation), viewer wants local (real humans
    // don't reason in Zulu). If JS is off, the UTC value stays
    // visible and the title attribute always shows UTC as ground truth.
    (function () {
        var els = document.querySelectorAll('time.js-local-time');
        if (!els.length) return;
        var fmt;
        try {
            fmt = new Intl.DateTimeFormat(undefined, {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit',  minute: '2-digit', second: '2-digit',
                hour12: false,
            });
        } catch (e) { return; }
        els.forEach(function (el) {
            var iso = el.getAttribute('datetime');
            if (!iso) return;
            var d = new Date(iso);
            if (isNaN(d.getTime())) return;
            el.textContent = fmt.format(d);
        });
    })();
</script>
