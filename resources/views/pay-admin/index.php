<?php
/**
 * @var string $base
 * @var string $csrfToken
 * @var array{
 *   total_rows:int,
 *   by_stage: array<string,int>,
 *   by_trip: list<array{trip_type:string, default_n:int, current_n:int, draft_n:int}>
 * } $summary
 * @var list<array{
 *   trip_type:string, label:string, has_draft:bool,
 *   current: list<array{miles:int, rate:string}>,
 *   draft:   list<array{miles:int, rate:string}>,
 * }> $buckets
 * @var string|null $flash
 */
layout('layouts/app');

/** Render a hidden trip_type + CSRF pair into a form. */
$bucketInputs = static function (string $tripType, string $csrf): string {
    return '<input type="hidden" name="_csrf"     value="' . e($csrf)     . '">'
         . '<input type="hidden" name="trip_type" value="' . e($tripType) . '">';
};
?>
<div class="card">
    <h1 class="m-0">Pay-rate admin</h1>
    <p class="text-brand-muted mt-2">
        Per-mile pay tiers for each trip type. Edit a draft, promote
        it to current to publish.
    </p>
    <p class="text-sm mt-3">
        <a href="<?= e($base) ?>/">← Back</a>
    </p>

    <?php if ($flash !== null): ?>
        <div class="flash-ok mt-4" role="status"><?= e($flash) ?></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="m-0">Jump to</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5">
        <a href="#bucket-round_trip" class="admin-tile">
            <span>Round-trip rates</span><span aria-hidden="true" class="admin-tile-arrow">↓</span>
        </a>
        <a href="#bucket-long_haul" class="admin-tile">
            <span>Long-haul rates</span><span aria-hidden="true" class="admin-tile-arrow">↓</span>
        </a>
        <a href="#recompute-pay" class="admin-tile">
            <span>Recompute pay</span><span aria-hidden="true" class="admin-tile-arrow">↓</span>
        </a>
    </div>
    <?php if ($summary['total_rows'] === 0): ?>
        <div class="flash-err mt-4" role="alert">
            No pay-rate rows. The backfill migration may not have run yet —
            check <code>_migrations</code> on the server.
        </div>
    <?php endif; ?>
</div>

<?php foreach ($buckets as $bucket): ?>
<div class="card scroll-mt-4" id="bucket-<?= e((string) $bucket['trip_type']) ?>">
    <h2 class="m-0"><?= e($bucket['label']) ?></h2>

    <p class="text-brand-muted mt-2">
        Current tiers: <code><?= count($bucket['current']) ?></code>
        ·
        Draft: <code><?= $bucket['has_draft'] ? count($bucket['draft']) . ' rows' : 'none' ?></code>
    </p>

    <div class="flex gap-2 flex-wrap mt-4 mb-5">
        <?php if (! $bucket['has_draft']): ?>
            <form method="post" action="<?= e($base) ?>/pay-admin/draft/start" class="m-0">
                <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                <button type="submit" class="btn-primary btn-sm">
                    Start draft from current
                </button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e($base) ?>/pay-admin/draft/promote" class="m-0"
                  onsubmit="return confirm('Promote draft to current? This replaces the live rates for <?= e($bucket['label']) ?>.');">
                <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                <button type="submit" class="btn-primary btn-sm">
                    Promote draft → current
                </button>
            </form>
            <form method="post" action="<?= e($base) ?>/pay-admin/draft/start" class="m-0"
                  onsubmit="return confirm('Discard the current draft and start fresh from current?');">
                <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                <button type="submit" class="btn-secondary btn-sm">
                    Reset draft to current
                </button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e($base) ?>/pay-admin/reset" class="m-0"
              onsubmit="return confirm('Reset current rates to factory defaults? This is irreversible from the UI.');">
            <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
            <button type="submit" class="btn-danger btn-sm">
                Reset current ← default
            </button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table stack-on-mobile text-[14px]">
            <thead>
                <tr>
                    <th class="text-right">Miles</th>
                    <th class="text-right">Current rate</th>
                    <th class="text-right">Draft rate</th>
                    <th>Save / delete draft tier</th>
                </tr>
            </thead>
            <?php /* stack-on-mobile + data-label collapses each row to a card below md;
                     desktop layout is unchanged. */ ?>
            <tbody>
                <?php
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
                    <tr>
                        <td data-label="Miles" class="md:text-right"><code><?= $milesInt ?></code></td>
                        <td data-label="Current rate" class="md:text-right">
                            <?php if ($vals['current'] === null): ?>
                                <em class="text-brand-muted">(dropped)</em>
                            <?php else: ?>
                                <code><?= e((string) $vals['current']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td data-label="Draft rate" class="md:text-right">
                            <?php if ($vals['draft'] === null): ?>
                                <span class="text-brand-muted">—</span>
                            <?php else: ?>
                                <code><?= e((string) $vals['draft']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td data-label="Save / delete">
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="post" action="<?= e($base) ?>/pay-admin/draft/upsert"
                                      class="flex flex-wrap items-center gap-2 m-0">
                                    <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                                    <input type="hidden" name="miles" value="<?= $milesInt ?>">
                                    <input type="text" name="rate" inputmode="decimal" required
                                           value="<?= e((string) ($vals['draft'] ?? $vals['current'] ?? '')) ?>"
                                           class="field w-24">
                                    <button type="submit" class="btn-primary btn-sm">Save</button>
                                </form>
                                <form method="post" action="<?= e($base) ?>/pay-admin/draft/delete"
                                      class="m-0"
                                      onsubmit="return confirm('Delete tier <?= $milesInt ?> from the <?= e($bucket['label']) ?> draft?');">
                                    <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                                    <input type="hidden" name="miles" value="<?= $milesInt ?>">
                                    <button type="submit" class="btn-secondary btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 class="mt-6 mb-3 text-base font-semibold">Add tier</h3>
    <form method="post" action="<?= e($base) ?>/pay-admin/draft/upsert"
          class="flex gap-3 flex-wrap items-end">
        <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
        <div>
            <label class="field-label">Miles</label>
            <input type="number" name="miles" min="1" max="65535" step="1" required class="field w-24">
        </div>
        <div>
            <label class="field-label">Rate ($)</label>
            <input type="text" name="rate" inputmode="decimal" required placeholder="e.g. 85.1492" class="field w-32">
        </div>
        <button type="submit" class="btn-primary">Add to draft</button>
    </form>
</div>
<?php endforeach; ?>

<div class="card scroll-mt-4" id="recompute-pay">
    <h2 class="m-0">Recompute pay</h2>
    <p class="text-brand-muted mt-2 mb-5">
        Walks <code>driver_loads</code> with PayCalculator using the current
        pay rates + variables, refilling the stored net pay + extras on
        rows whose computed values differ. Scope defaults to the last
        30 days; widen with the date filter or restrict to a single driver
        when needed.
    </p>
    <form method="post" action="<?= e($base) ?>/pay-admin/recompute"
          class="flex gap-3 items-end flex-wrap"
          onsubmit="return confirm('Run PayCalculator over the selected driver_loads rows? This will UPDATE the stored pay values.');">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div>
            <label class="field-label">Since (YYYY-MM-DD)</label>
            <input type="date" name="since" class="field">
        </div>
        <div>
            <label class="field-label">Driver id (optional)</label>
            <input type="number" name="driver_id" min="1" step="1" class="field w-32">
        </div>
        <button type="submit" class="btn-primary">Recompute pay</button>
    </form>
</div>
