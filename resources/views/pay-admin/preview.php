<?php
/**
 * @var string                                 $base
 * @var array<string,mixed>                    $actor
 * @var string                                 $trip_type
 * @var string                                 $trip_label
 * @var int                                    $days
 * @var string                                 $since
 * @var int                                    $row_count
 * @var float                                  $total_old
 * @var float                                  $total_new
 * @var float                                  $delta_total
 * @var float                                  $delta_pct
 * @var list<array{
 *   driver_user:string, driver_id:int, frtl:int, date:string,
 *   pickup:string, delivery:string,
 *   old_np:float, new_np:float, delta:float
 * }>                                          $comparisons
 * @var list<array{miles:int, rate:string}>    $draft_tiers
 * @var list<array{miles:int, rate:string}>    $current_tiers
 */
layout('layouts/app');

$money = static fn (float $v): string => '$' . number_format($v, 2);
$signedMoney = static function (float $v): string {
    $sign = $v > 0 ? '+' : ($v < 0 ? '-' : '');
    return $sign . '$' . number_format(abs($v), 2);
};
$deltaClass = static fn (float $v): string => $v > 0
    ? 'text-emerald-700 dark:text-emerald-300'
    : ($v < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-brand-muted');
?>
<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="m-0">Preview draft — <?= e($trip_label) ?></h1>
            <p class="text-brand-muted mt-1 mb-0 text-sm">
                Nothing has been saved. This is a dry-run of the current
                <code><?= e($trip_type) ?></code> draft against every load of that type
                since <code><?= e($since) ?></code> (<?= (int) $days ?> days).
                Adjust the draft, hand-tweak tiers, or start over — no drivers
                see any change until you Promote.
            </p>
        </div>
        <a href="<?= e($base) ?>/pay-admin" class="btn-secondary btn-sm">← Back to pay-admin</a>
    </div>
</div>

<div class="card">
    <h2 class="m-0">Aggregate impact</h2>
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-4">
        <div>
            <div class="text-xs text-brand-muted uppercase tracking-wide">Loads considered</div>
            <div class="text-2xl font-bold mt-1"><?= (int) $row_count ?></div>
        </div>
        <div>
            <div class="text-xs text-brand-muted uppercase tracking-wide">Current total</div>
            <div class="text-2xl font-bold mt-1"><?= e($money($total_old)) ?></div>
        </div>
        <div>
            <div class="text-xs text-brand-muted uppercase tracking-wide">Projected total</div>
            <div class="text-2xl font-bold mt-1"><?= e($money($total_new)) ?></div>
        </div>
        <div>
            <div class="text-xs text-brand-muted uppercase tracking-wide">Delta</div>
            <div class="text-2xl font-bold mt-1 <?= $deltaClass($delta_total) ?>">
                <?= e($signedMoney($delta_total)) ?>
                <span class="text-sm font-normal">
                    (<?= ($delta_pct >= 0 ? '+' : '') . number_format($delta_pct, 2) ?>%)
                </span>
            </div>
        </div>
    </div>
    <?php if ($row_count === 0): ?>
        <p class="text-brand-muted mt-4 mb-0 text-sm">
            No <?= e($trip_type) ?> loads in the last <?= (int) $days ?> days —
            nothing to compare. Widen the window with
            <code>?days=90</code> or enter a test load and try again.
        </p>
    <?php endif; ?>
</div>

<?php if ($row_count > 0): ?>
    <div class="card">
        <h2 class="m-0">Per-load diff</h2>
        <p class="text-brand-muted mt-1 text-sm">
            Sorted newest-first. Deltas ≥ $0.01 are highlighted; loads that
            price identically are shown in muted rows so you can scan for
            the ones the raise actually moves.
        </p>
        <div class="table-wrap mt-3">
            <table class="data-table text-[13px] w-full">
                <thead>
                    <tr>
                        <th class="text-left">Date</th>
                        <th class="text-left">Driver</th>
                        <th class="text-left">FRTL</th>
                        <th class="text-left">Pickup → Delivery</th>
                        <th class="text-right">Current</th>
                        <th class="text-right">Projected</th>
                        <th class="text-right">Δ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparisons as $c): ?>
                        <?php $changed = abs($c['delta']) >= 0.005; ?>
                        <tr class="<?= $changed ? '' : 'opacity-50' ?>">
                            <td class="whitespace-nowrap"><?= e($c['date']) ?></td>
                            <td class="whitespace-nowrap">
                                <?= e($c['driver_user']) ?>
                                <span class="text-brand-muted text-xs">#<?= (int) $c['driver_id'] ?></span>
                            </td>
                            <td><code><?= (int) $c['frtl'] ?></code></td>
                            <td class="break-words max-w-md">
                                <?= e($c['pickup']) ?>
                                <span class="text-brand-muted">→</span>
                                <?= e($c['delivery']) ?>
                            </td>
                            <td class="text-right whitespace-nowrap"><?= e($money($c['old_np'])) ?></td>
                            <td class="text-right whitespace-nowrap font-semibold"><?= e($money($c['new_np'])) ?></td>
                            <td class="text-right whitespace-nowrap <?= $deltaClass($c['delta']) ?>">
                                <?= $changed ? e($signedMoney($c['delta'])) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <h2 class="m-0">Draft vs current tiers</h2>
    <div class="table-wrap mt-3">
        <table class="data-table text-[13px]">
            <thead>
                <tr>
                    <th class="text-left">Miles ≤</th>
                    <th class="text-right">Current rate</th>
                    <th class="text-right">Draft rate</th>
                    <th class="text-right">Δ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Merge on miles so a table row exists for any tier
                // that's in either side. Handles rare cases where a
                // draft adds or removes a tier vs current.
                $byMiles = [];
                foreach ($current_tiers as $t) {
                    $byMiles[(int) $t['miles']]['current'] = (float) $t['rate'];
                }
                foreach ($draft_tiers as $t) {
                    $byMiles[(int) $t['miles']]['draft'] = (float) $t['rate'];
                }
                ksort($byMiles);
                foreach ($byMiles as $miles => $pair):
                    $cur   = $pair['current'] ?? null;
                    $drf   = $pair['draft']   ?? null;
                    $delta = ($cur !== null && $drf !== null) ? $drf - $cur : null;
                    ?>
                    <tr>
                        <td class="whitespace-nowrap"><?= (int) $miles ?></td>
                        <td class="text-right whitespace-nowrap"><?= $cur !== null ? '$' . number_format($cur, 4) : '<em class="text-brand-muted">removed</em>' ?></td>
                        <td class="text-right whitespace-nowrap font-semibold"><?= $drf !== null ? '$' . number_format($drf, 4) : '<em class="text-brand-muted">added</em>' ?></td>
                        <td class="text-right whitespace-nowrap <?= $delta !== null ? $deltaClass($delta) : '' ?>">
                            <?php if ($delta !== null && abs($delta) >= 0.00005): ?>
                                <?= ($delta >= 0 ? '+' : '-') . '$' . number_format(abs($delta), 4) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="m-0 text-sm">
        <a href="<?= e($base) ?>/pay-admin">← Back to pay-admin</a>
        &middot; From here you can hand-tweak individual draft tiers, click
        <strong>Bump draft by %</strong> again to try a different number,
        <strong>Reset draft to current</strong> to throw the draft away,
        or <strong>Promote</strong> when you're ready to make it live.
    </p>
</div>
