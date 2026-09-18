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
 * @var float       $currentRaise    live `raise` variable, e.g. 0.08675
 * @var float|null  $draftRaise      draft `raise` value, or null if no draft
 */
layout('layouts/app');

/** Render a hidden trip_type + CSRF pair into a form. */
$bucketInputs = static function (string $tripType, string $csrf): string {
    return '<input type="hidden" name="_csrf"     value="' . e($csrf)     . '">'
         . '<input type="hidden" name="trip_type" value="' . e($tripType) . '">';
};

/** Format a rate as its effective loaded pay (rate × (1 + raise)). */
$effectivePay = static function (?string $rate, float $raise): ?string {
    if ($rate === null || $rate === '') {
        return null;
    }
    return '$' . number_format(((float) $rate) * (1.0 + $raise), 2);
};

$raiseIsZero      = abs($currentRaise) < 1e-9;
$draftRaiseActive = $draftRaise !== null && abs($draftRaise - $currentRaise) > 1e-9;
?>
<div class="card">
    <h1 class="m-0">Pay-rate admin</h1>
    <p class="text-brand-muted mt-2">
        Pay rows per trip type. Each row is a mileage bracket: it pays every load whose
        mileage is at most that row's <strong>Miles</strong> value and above the previous
        row's, and its value is the flat pay for the whole bracket (not a per-mile rate).
        Edit a draft, promote it to current to publish.
    </p>
    <?php if (! $raiseIsZero || $draftRaiseActive): ?>
        <div class="mt-3 px-3 py-2 bg-amber-50 dark:bg-amber-950/40 border-l-4 border-amber-400 text-slate-700 dark:text-slate-200 text-[13px]">
            <strong class="text-amber-900 dark:text-amber-300">Heads up — hidden multiplier:</strong>
            every rate below is multiplied by
            <code>(1 + raise) = <?= number_format(1.0 + $currentRaise, 5) ?></code>
            before drivers see it (current <code>raise = <?= number_format($currentRaise, 5) ?></code>).
            The "→ pays" column is what actually lands on the load card.
            <?php if ($draftRaiseActive): ?>
                A variables draft is queued (<code>raise = <?= number_format((float) $draftRaise, 5) ?></code>) —
                that value doesn't take effect until you promote it.
            <?php endif; ?>
            <a href="<?= e($base) ?>/pay-admin/variables" class="underline">Edit raise →</a>
        </div>
    <?php endif; ?>
    <p class="text-sm mt-3 flex flex-wrap gap-3 items-center">
        <a href="<?= e($base) ?>/pay-admin/preview" class="btn-secondary btn-sm">
            Preview impact — all trip types →
        </a>
        <a href="<?= e($base) ?>/pay-admin/variables" class="btn-secondary btn-sm">
            Edit pay variables →
        </a>
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

    <div class="flex flex-col gap-4 mt-4 mb-5">
        <?php if (! $bucket['has_draft']): ?>
            <div class="flex flex-wrap gap-2 items-center">
                <form method="post" action="<?= e($base) ?>/pay-admin/draft/start" class="m-0">
                    <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                    <button type="submit" class="btn-primary btn-sm">
                        Start draft from current
                    </button>
                </form>
                <span class="text-xs text-brand-muted">
                    Start a draft to edit rates, bump every tier by %, or preview impact.
                </span>
            </div>
        <?php else: ?>
            <?php /* Row 1 — publish: date + promote + preview, primary flow. */ ?>
            <div class="flex flex-wrap gap-2 items-end">
                <form method="post" action="<?= e($base) ?>/pay-admin/draft/promote"
                      class="m-0 flex flex-wrap items-end gap-2"
                      onsubmit="return confirm('Promote draft to current and snapshot a new rate-version for <?= e($bucket['label']) ?>? Loads on or after the effective date will use the new rates; earlier loads keep the previous rate.');">
                    <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                    <div>
                        <label for="eff-<?= e($bucket['trip_type']) ?>" class="field-label text-xs">Effective from</label>
                        <input id="eff-<?= e($bucket['trip_type']) ?>" type="date" name="effective_date"
                               value="<?= e(date('Y-m-d')) ?>"
                               class="field text-sm h-9 py-1 px-2 min-h-0 w-auto">
                    </div>
                    <button type="submit" class="btn-primary btn-sm">
                        Promote draft → current
                    </button>
                </form>
                <a href="<?= e($base) ?>/pay-admin/preview?trip_type=<?= e($bucket['trip_type']) ?>"
                   class="btn-secondary btn-sm">
                    Preview impact →
                </a>
            </div>

            <?php /* Row 2 — draft edits: bump + reset draft, with the hint. */ ?>
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap gap-2 items-end">
                    <form method="post" action="<?= e($base) ?>/pay-admin/draft/bump"
                          class="m-0 flex flex-wrap items-end gap-2"
                          data-bump-form data-bump-label="<?= e($bucket['label']) ?>"
                          onsubmit="return confirm('Bump ' + (this.dataset.bumpLabel || 'draft') + ' by ' + (this.querySelector('input[name=&quot;percent&quot;]').value || '?') + '%? Draft rates will be multiplied and rounded to 2 decimals — you can hand-tweak individual tiers before Promote.');">
                        <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                        <div>
                            <label for="bump-<?= e($bucket['trip_type']) ?>" class="field-label text-xs">Bump draft by %</label>
                            <input id="bump-<?= e($bucket['trip_type']) ?>" type="text" name="percent"
                                   inputmode="decimal"
                                   placeholder="e.g. 7 or -2.5"
                                   class="field text-sm h-9 py-1 px-2 min-h-0 w-24"
                                   aria-describedby="bump-hint-<?= e($bucket['trip_type']) ?>">
                        </div>
                        <button type="submit" class="btn-secondary btn-sm">
                            Apply %
                        </button>
                    </form>
                    <form method="post" action="<?= e($base) ?>/pay-admin/draft/start" class="m-0"
                          onsubmit="return confirm('Discard the current draft and start fresh from current?');">
                        <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                        <button type="submit" class="btn-secondary btn-sm">
                            Reset draft to current
                        </button>
                    </form>
                </div>
                <span id="bump-hint-<?= e($bucket['trip_type']) ?>" class="text-xs text-brand-muted">
                    Multiplies every draft tier and rounds to 2 decimals. Rates are stored as absolute numbers; the % is a calculator, not a policy.
                </span>
            </div>
        <?php endif; ?>

        <?php /* Row 3 — danger: reset current to shipped defaults. Always visible.
                 Type-to-confirm: a plain confirm() dialog on this one is too easy
                 to muscle-memory click through, and it's the one action here that
                 silently wipes hand-tuned rates AND any draft with them. Requires
                 the admin to type the trip-type label (case-insensitive) before
                 the button becomes a real submit. */ ?>
        <div class="flex flex-col gap-1 pt-2 border-t border-slate-200 dark:border-slate-700">
            <div class="flex flex-wrap gap-2 items-center">
                <form method="post" action="<?= e($base) ?>/pay-admin/reset"
                      class="m-0 flex flex-wrap items-center gap-2"
                      data-reset-form data-reset-label="<?= e($bucket['label']) ?>"
                      onsubmit="return (function (f) {
                          const typed = (f.querySelector('input[name=&quot;confirm_label&quot;]').value || '').trim().toLowerCase();
                          const need  = (f.dataset.resetLabel || '').trim().toLowerCase();
                          if (typed !== need) {
                              alert('Type ' + (f.dataset.resetLabel || '') + ' (case-insensitive) into the confirm box to reset.');
                              return false;
                          }
                          return true;
                      })(this);">
                    <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
                    <button type="button" class="btn-danger btn-sm"
                            data-reveal-reset
                            onclick="const g = this.closest('form').querySelector('[data-reset-confirm]'); g.hidden = false; this.hidden = true; g.querySelector('input').focus();">
                        Reset current ← default…
                    </button>
                    <span data-reset-confirm hidden class="flex flex-wrap items-center gap-2">
                        <label class="text-xs text-brand-muted">
                            Type
                            <code><?= e($bucket['label']) ?></code>
                            to confirm:
                        </label>
                        <input type="text" name="confirm_label" required
                               class="field text-sm h-9 py-1 px-2 min-h-0 w-40"
                               autocomplete="off" spellcheck="false"
                               aria-label="Type <?= e($bucket['label']) ?> to confirm reset">
                        <button type="submit" class="btn-danger btn-sm">Confirm reset</button>
                        <button type="button" class="btn-secondary btn-sm"
                                onclick="const f = this.closest('form'); const g = f.querySelector('[data-reset-confirm]'); g.hidden = true; g.querySelector('input').value = ''; f.querySelector('[data-reveal-reset]').hidden = false;">
                            Cancel
                        </button>
                    </span>
                </form>
            </div>
            <span class="text-xs text-brand-muted">
                Rolls the live rates back to what shipped with the app AND clears any
                draft. Irreversible from the UI — <code><?= e($bucket['label']) ?></code>
                type-confirmation guards against a muscle-memory click.
            </span>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table stack-on-mobile text-[14px]">
            <thead>
                <tr>
                    <th class="text-right">Miles</th>
                    <th class="text-right">Covers</th>
                    <th class="text-right">Current row pay</th>
                    <th class="text-right">→ pays driver</th>
                    <th class="text-right">Draft row pay</th>
                    <th class="text-right">→ pays driver</th>
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

                // Mileage band each row pays. The calculator takes the
                // LOWEST row whose ceiling is >= the load's miles, so a row
                // pays from the previous row's ceiling + 1 up to its own —
                // "Covers" makes visible which loads a row can move.
                $bands     = [];
                $prevMiles = 0;
                foreach (array_keys($combined) as $m) {
                    $m = (int) $m;
                    $bands[$m] = $prevMiles === 0
                        ? sprintf('≤ %d mi', $m)
                        : sprintf('%d–%d mi', $prevMiles + 1, $m);
                    $prevMiles = $m;
                }

                // Rows that look WRONG rather than merely different: a rung
                // paying less than a shorter one, or a placeholder value
                // many times the ladder's median. The reasons are spelled
                // out under the table, not hidden behind an icon.
                $ladderVal = [];
                foreach ($combined as $m => $vals) {
                    $val = $vals['draft'] ?? $vals['current'];
                    if ($val !== null) {
                        $ladderVal[(int) $m] = (float) $val;
                    }
                }
                $flags = \PayTracker\Models\PayRate::flagRungs($ladderVal);

                foreach ($combined as $miles => $vals):
                    $milesInt = (int) $miles;
                ?>
                    <?php
                    $curEff = $effectivePay($vals['current'], $currentRaise);
                    // Effective loaded pay uses the *current* raise even
                    // for the draft rate — that's what promoting the rate
                    // draft NOW (without touching variables) would land at.
                    // A separate variables draft is a separate promote,
                    // called out in the header banner.
                    $draftEff = $effectivePay($vals['draft'], $currentRaise);
                    ?>
                    <tr>
                        <td data-label="Miles" class="md:text-right"><code><?= $milesInt ?></code><?php if (isset($flags[$milesInt])): ?> <span class="text-amber-600 dark:text-amber-400" title="<?= e($flags[$milesInt]) ?>" aria-label="flagged: <?= e($flags[$milesInt]) ?>">&#9888;</span><?php endif; ?></td>
                        <td data-label="Covers" class="md:text-right text-brand-muted whitespace-nowrap"><?= e($bands[$milesInt] ?? '—') ?></td>
                        <td data-label="Current row pay" class="md:text-right">
                            <?php if ($vals['current'] === null): ?>
                                <em class="text-brand-muted">(dropped)</em>
                            <?php else: ?>
                                <code><?= e((string) $vals['current']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td data-label="→ Current pays driver" class="md:text-right text-brand-muted whitespace-nowrap">
                            <?php if ($curEff === null): ?>
                                <span class="text-brand-muted">—</span>
                            <?php else: ?>
                                <?= e($curEff) ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Draft row pay" class="md:text-right">
                            <?php if ($vals['draft'] === null): ?>
                                <span class="text-brand-muted">—</span>
                            <?php else: ?>
                                <code><?= e((string) $vals['draft']) ?></code>
                            <?php endif; ?>
                        </td>
                        <td data-label="→ Draft pays driver" class="md:text-right text-brand-muted whitespace-nowrap">
                            <?php if ($draftEff === null): ?>
                                <span class="text-brand-muted">—</span>
                            <?php else: ?>
                                <?= e($draftEff) ?>
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

    <?php if ($flags !== []): ?>
        <div class="mt-3 px-3 py-2 bg-amber-50 dark:bg-amber-950/40 border-l-4 border-amber-400 text-slate-700 dark:text-slate-200 text-[13px]">
            <strong class="text-amber-900 dark:text-amber-300">Worth checking before you promote:</strong>
            <ul class="mb-0 mt-1 pl-4">
                <?php foreach ($flags as $flaggedMiles => $reason): ?>
                    <li><code><?= (int) $flaggedMiles ?></code> mi — <?= e($reason) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h3 class="mt-6 mb-3 text-base font-semibold">Add tier</h3>
    <form method="post" action="<?= e($base) ?>/pay-admin/draft/upsert"
          class="flex gap-3 flex-wrap items-end">
        <?= $bucketInputs($bucket['trip_type'], $csrfToken) ?>
        <div>
            <label class="field-label">Miles</label>
            <input type="number" name="miles" min="1" max="65535" step="1" required class="field w-24">
        </div>
        <div>
            <label class="field-label">Pay for this bracket ($)</label>
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
