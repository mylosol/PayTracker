<?php
/**
 * @var string $base
 * @var string $csrfToken
 * @var array{
 *   total_rows:int,
 *   by_stage: array<string,int>,
 *   by_terminal_trip: list<array{terminal:string, trip_type:string, default_n:int, current_n:int, draft_n:int}>
 * } $summary
 * @var list<array{
 *   terminal:string, trip_type:string, label:string, has_draft:bool,
 *   current: list<array{miles:int, rate:string}>,
 *   draft:   list<array{miles:int, rate:string}>,
 * }> $buckets
 * @var string|null $flash
 */
layout('layouts/app');

/** Render a hidden (terminal, trip_type) pair into a form. */
$bucketInputs = static function (string $terminal, string $tripType, string $csrf): string {
    return '<input type="hidden" name="_csrf"     value="' . e($csrf)     . '">'
         . '<input type="hidden" name="terminal"  value="' . e($terminal) . '">'
         . '<input type="hidden" name="trip_type" value="' . e($tripType) . '">';
};
?>
<div class="card">
    <h1>Pay-rate admin</h1>
    <p class="muted">
        Modern replacement for the legacy <code>BasePayAdminSubmit.php</code>
        + <code>UpdatePanamaPay.php</code> + <code>UpdatePensacolaPay.php</code>
        + <code>adminPaySelect.php</code> stack. Manages the per-mile pay
        tiers for each terminal &times; trip-type combination.
        <a href="<?= e($base) ?>/">&larr; Back</a>
    </p>

    <?php if ($flash !== null): ?>
        <p style="background:#dcfce7;color:#166534;border-radius:6px;padding:.6rem .8rem;">
            <?= e($flash) ?>
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Summary</h2>
    <ul>
        <li>Total rate rows: <code><?= e((string) $summary['total_rows']) ?></code></li>
        <?php foreach (['default', 'current', 'draft'] as $stage): ?>
            <li>
                stage=<code><?= e($stage) ?></code>:
                <code><?= e((string) ($summary['by_stage'][$stage] ?? 0)) ?></code>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($summary['total_rows'] === 0): ?>
        <p class="muted" style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:.6rem .8rem;">
            No pay-rate rows. The backfill migration may not have run yet &mdash;
            check <code>_migrations</code> on the server.
        </p>
    <?php endif; ?>
</div>

<?php foreach ($buckets as $bucket): ?>
<div class="card">
    <h2><?= e($bucket['label']) ?></h2>

    <p class="muted">
        Current tiers: <code><?= count($bucket['current']) ?></code>
        &middot;
        Draft: <code><?= $bucket['has_draft'] ? count($bucket['draft']) . ' rows' : 'none' ?></code>
    </p>

    <!-- Stage actions row -->
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
        <?php if (! $bucket['has_draft']): ?>
            <form method="post" action="<?= e($base) ?>/pay-admin/draft/start" style="margin:0;">
                <?= $bucketInputs($bucket['terminal'], $bucket['trip_type'], $csrfToken) ?>
                <button type="submit"
                        style="background:var(--accent);color:#fff;border:0;padding:.4rem 1rem;border-radius:6px;font:inherit;cursor:pointer;">
                    Start draft from current
                </button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e($base) ?>/pay-admin/draft/promote" style="margin:0;"
                  onsubmit="return confirm('Promote draft to current? This replaces the live rates for <?= e($bucket['label']) ?>.');">
                <?= $bucketInputs($bucket['terminal'], $bucket['trip_type'], $csrfToken) ?>
                <button type="submit"
                        style="background:#16a34a;color:#fff;border:0;padding:.4rem 1rem;border-radius:6px;font:inherit;cursor:pointer;">
                    Promote draft &rarr; current
                </button>
            </form>
            <form method="post" action="<?= e($base) ?>/pay-admin/draft/start" style="margin:0;"
                  onsubmit="return confirm('Discard the current draft and start fresh from current?');">
                <?= $bucketInputs($bucket['terminal'], $bucket['trip_type'], $csrfToken) ?>
                <button type="submit"
                        style="background:#f59e0b;color:#fff;border:0;padding:.4rem 1rem;border-radius:6px;font:inherit;cursor:pointer;">
                    Reset draft to current
                </button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e($base) ?>/pay-admin/reset" style="margin:0;"
              onsubmit="return confirm('Reset current rates to factory defaults? This is irreversible from the UI.');">
            <?= $bucketInputs($bucket['terminal'], $bucket['trip_type'], $csrfToken) ?>
            <button type="submit"
                    style="background:#dc2626;color:#fff;border:0;padding:.4rem 1rem;border-radius:6px;font:inherit;cursor:pointer;">
                Reset current &larr; default
            </button>
        </form>
    </div>

    <!-- Rates table -->
    <table style="border-collapse:collapse;font-size:14px;width:100%;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                <th style="padding:.3rem .5rem;text-align:right;">Miles</th>
                <th style="padding:.3rem .5rem;text-align:right;">Current rate</th>
                <th style="padding:.3rem .5rem;text-align:right;">Draft rate</th>
                <th style="padding:.3rem .5rem;">Save / delete draft tier</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Build a unified list of (miles → [current, draft]) so a row
            // shows both columns side-by-side regardless of which stage it
            // appears in. New draft-only tiers and dropped tiers both
            // surface this way.
            $combined = [];
            foreach ($bucket['current'] as $r) {
                $combined[$r['miles']] = ['current' => $r['rate'], 'draft' => null];
            }
            foreach ($bucket['draft'] as $r) {
                $combined[$r['miles']]['draft']    = $r['rate'];
                $combined[$r['miles']]['current']  = $combined[$r['miles']]['current']  ?? null;
            }
            ksort($combined);
            foreach ($combined as $miles => $vals):
                $milesInt = (int) $miles;
            ?>
                <tr style="border-bottom:1px solid #f0f2f6;">
                    <td style="padding:.25rem .5rem;text-align:right;"><code><?= $milesInt ?></code></td>
                    <td style="padding:.25rem .5rem;text-align:right;">
                        <?php if ($vals['current'] === null): ?>
                            <em class="muted">(dropped)</em>
                        <?php else: ?>
                            <code><?= e((string) $vals['current']) ?></code>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.25rem .5rem;text-align:right;">
                        <?php if ($vals['draft'] === null): ?>
                            <span class="muted">&mdash;</span>
                        <?php else: ?>
                            <code><?= e((string) $vals['draft']) ?></code>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.25rem .5rem;">
                        <form method="post" action="<?= e($base) ?>/pay-admin/draft/upsert"
                              style="margin:0;display:inline-flex;gap:.3rem;align-items:center;">
                            <?= $bucketInputs($bucket['terminal'], $bucket['trip_type'], $csrfToken) ?>
                            <input type="hidden" name="miles" value="<?= $milesInt ?>">
                            <input type="text" name="rate" inputmode="decimal" required
                                   value="<?= e((string) ($vals['draft'] ?? $vals['current'] ?? '')) ?>"
                                   style="width:6rem;padding:.25rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;">
                            <button type="submit"
                                    style="background:var(--accent);color:#fff;border:0;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;">
                                Save
                            </button>
                        </form>
                        <form method="post" action="<?= e($base) ?>/pay-admin/draft/delete"
                              style="display:inline;margin:0 0 0 .3rem;"
                              onsubmit="return confirm('Delete tier <?= $milesInt ?> from the <?= e($bucket['label']) ?> draft?');">
                            <?= $bucketInputs($bucket['terminal'], $bucket['trip_type'], $csrfToken) ?>
                            <input type="hidden" name="miles" value="<?= $milesInt ?>">
                            <button type="submit"
                                    style="background:#6b7280;color:#fff;border:0;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Add new tier -->
    <h3 style="margin-top:1.2rem;">Add tier</h3>
    <form method="post" action="<?= e($base) ?>/pay-admin/draft/upsert"
          style="display:flex;gap:.5rem;align-items:center;">
        <?= $bucketInputs($bucket['terminal'], $bucket['trip_type'], $csrfToken) ?>
        <label>Miles
            <input type="number" name="miles" min="1" max="65535" step="1" required
                   style="width:6rem;padding:.4rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;">
        </label>
        <label>Rate ($)
            <input type="text" name="rate" inputmode="decimal" required placeholder="e.g. 85.1492"
                   style="width:8rem;padding:.4rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;">
        </label>
        <button type="submit"
                style="background:var(--accent);color:#fff;border:0;padding:.4rem 1rem;border-radius:6px;font:inherit;cursor:pointer;">
            Add to draft
        </button>
    </form>
</div>
<?php endforeach; ?>

<div class="card">
    <h2>Recompute pay (np/op)</h2>
    <p class="muted">
        Walks <code>driver_loads</code> with PayCalculator using the current
        pay rates + variables, refilling <code>np</code> and <code>op</code>
        on rows whose computed values differ. Scope defaults to the last
        30 days; widen with the date filter or restrict to a single driver
        when needed.
    </p>
    <form method="post" action="<?= e($base) ?>/pay-admin/recompute"
          style="display:flex;gap:.8rem;align-items:end;flex-wrap:wrap;"
          onsubmit="return confirm('Run PayCalculator over the selected driver_loads rows? This will UPDATE the np/op columns.');">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>
            Since (YYYY-MM-DD)<br>
            <input type="date" name="since"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;">
        </label>
        <label>
            Driver id (optional)<br>
            <input type="number" name="driver_id" min="1" step="1"
                   style="width:8rem;padding:.4rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;">
        </label>
        <button type="submit"
                style="background:#16a34a;color:#fff;border:0;padding:.5rem 1.2rem;border-radius:6px;font:inherit;cursor:pointer;">
            Recompute np/op
        </button>
    </form>
</div>
