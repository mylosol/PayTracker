<?php
/**
 * @var string                                 $base
 * @var array<string,mixed>                    $actor
 * @var string                                 $scope          'all' | 'focused'
 * @var string                                 $trip_type      '' when scope=all
 * @var string                                 $trip_label
 * @var string                                 $anchor
 * @var string                                 $today
 * @var string                                 $week_start
 * @var string                                 $week_end
 * @var string                                 $week_start_day
 * @var bool                                   $week_has_today
 * @var array<string,bool>                     $has_draft
 * @var list<array{
 *   key:string, label:string, trip_type:?string, count:int,
 *   saved_count:int, unsaved_count:int, old:float, new:float,
 *   repriced:bool, has_draft:?bool
 * }>                                          $buckets
 * @var int                                    $row_count
 * @var int                                    $saved_count
 * @var int                                    $unsaved_count
 * @var bool                                   $unsaved_requested
 * @var int                                    $unsaved_live
 * @var ?string                                $unsaved_error
 * @var float                                  $total_old
 * @var float                                  $total_new
 * @var float                                  $delta_total
 * @var float                                  $delta_pct
 * @var float                                  $total_old_saved
 * @var float                                  $total_new_saved
 * @var float                                  $total_old_unsaved
 * @var float                                  $total_new_unsaved
 * @var list<array{
 *   source:string, bucket:string, repriced:bool, frtl:?int, local_id:?string,
 *   date:string, pickup:string, delivery:string, notes:string,
 *   old_np:float, new_np:float, delta:float,
 *   new_breakdown:?array<string,mixed>
 * }>                                          $comparisons
 * @var list<int>                              $scope_load_types
 * @var array<string,list<array{miles:int, rate:string}>> $current_tiers
 * @var array<string,list<array{miles:int, rate:string}>> $draft_tiers
 * @var string                                 $csrf_token
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

$focused  = $scope === 'focused';
$otherType = static fn (string $t): string => $t === 'round_trip' ? 'long_haul' : 'round_trip';

/**
 * The mileage band a rate row covers, e.g. "67–68 mi" (or "≤ 10 mi" for
 * the bottom row).
 *
 * A load is paid by the LOWEST row whose ceiling is ≥ its miles — the
 * legacy `WHERE miles >= ? LIMIT 1` rule — so a 67-mile load is paid by
 * the 68 row and NOT by the 66 row. That is the whole reason an edit to
 * the row below a load's mileage can legitimately change nothing about
 * that load, which is why the preview names the paying row.
 *
 * @param list<array{miles:int, rate:string}> $tiers
 */
$bandLabel = static function (array $tiers, int $miles): ?string {
    $prev = 0;
    foreach ($tiers as $t) {
        $m = (int) $t['miles'];
        if ($m === $miles) {
            return $prev === 0 ? sprintf('≤ %d mi', $m) : sprintf('%d–%d mi', $prev + 1, $m);
        }
        $prev = $m;
    }
    return null;
};

/** The value of one row (by ceiling mileage) in a tier list, or null. */
$valueAt = static function (array $tiers, int $miles): ?float {
    foreach ($tiers as $t) {
        if ((int) $t['miles'] === $miles) {
            return (float) $t['rate'];
        }
    }
    return null;
};

/**
 * "Which row paid this load, and did my draft touch it?" — the line that
 * turns a silent no-op edit into an explanation. Rendered under the
 * per-load breakdown.
 */
$bracketNote = static function (?array $bd) use ($bandLabel, $valueAt, $current_tiers, $draft_tiers): string {
    if ($bd === null) {
        return '';
    }
    if (! array_key_exists('base_tier_miles', $bd)) {
        // Breakdown computed before the paying row was recorded (e.g. a
        // stored pay_breakdown blob). Say nothing rather than guess.
        return '';
    }
    $tripLabel = (string) ($bd['trip_label'] ?? '');
    if ($tripLabel !== 'Round-trip' && $tripLabel !== 'One-way') {
        return '';   // trainer: flat pay, no rate rows apply
    }

    $trip   = $tripLabel === 'Round-trip' ? 'round_trip' : 'long_haul';
    $miles  = (int) ($bd['base_miles'] ?? 0);
    $tier   = (int) ($bd['base_tier_miles'] ?? 0);
    $draft  = $draft_tiers[$trip] ?? [];
    $cur    = $current_tiers[$trip] ?? [];
    $ladder = $draft !== [] ? $draft : $cur;

    if ($tier === 0) {
        if ($miles === 0) {
            return '';
        }
        $top = $ladder === [] ? null : (int) $ladder[count($ladder) - 1]['miles'];
        return sprintf(
            '<p class="mt-1 mb-0 text-xs text-amber-600 dark:text-amber-400">'
            . 'No rate row reaches %d mi, so the loaded-leg pay is $0.00 (top row on file: %s). '
            . 'Add a row at %d mi or above to pay this load.</p>',
            $miles,
            $top !== null ? $top . ' mi' : 'none',
            $miles,
        );
    }

    $band = $bandLabel($ladder, $tier);

    // Did the draft change the row that pays this load?
    $curPay   = $valueAt($cur, $tier);
    $draftPay = $valueAt($draft, $tier);
    $tail     = '';
    if ($draft === []) {
        $tail = ' No draft for this trip type, so this is the current row.';
    } elseif ($draftPay !== null && $curPay === null) {
        $tail = sprintf(' Your draft adds this row at $%s.', number_format($draftPay, 4));
    } elseif ($draftPay !== null && $curPay !== null && abs($draftPay - $curPay) < 0.00005) {
        $tail = sprintf(
            ' Your draft leaves this row at $%s, so it does not move this load.',
            number_format($curPay, 4),
        );
    } elseif ($draftPay !== null && $curPay !== null) {
        $tail = sprintf(
            ' Your draft moves this row $%s → $%s.',
            number_format($curPay, 4),
            number_format($draftPay, 4),
        );
    } elseif ($draftPay === null && $curPay !== null) {
        $tail = ' Your draft deletes this row, so the load falls to a higher one.';
    }

    return sprintf(
        '<p class="mt-1 mb-0 text-xs text-brand-muted">Paid from the <strong>%d mi</strong> rate row%s. '
        . 'A row is that bracket\'s flat pay, not a per-mile rate — a load takes the lowest row whose mileage is at least its own.%s</p>',
        $tier,
        $band !== null ? ' (covers ' . e($band) . ')' : '',
        e($tail),
    );
};

/**
 * Component rows for one load's projected pay — the same card the dashboard
 * renders from pay_breakdown, so the two read identically. Rendered
 * server-side (the projection lives in PHP, not the browser).
 */
$breakdownRows = static function (?array $bd) use ($money): string {
    if ($bd === null) {
        return '<tr><td colspan="2" class="py-1 px-3 text-brand-muted">No breakdown available for this row.</td></tr>';
    }

    $pct = static fn (float $v): string => number_format($v * 100, 2) . '%';
    $row = static fn (string $label, float $value): string => sprintf(
        '<tr><td class="py-1 px-3 text-slate-600 dark:text-slate-300">%s</td>'
        . '<td class="py-1 px-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">%s</td></tr>',
        $label,
        e($money($value)),
    );

    $rows = [];

    // Loaded Pay shows for the trip types that have one, even at 0 miles —
    // a same-city load is real, and hiding the row reads as "miles missing".
    $tripLabel  = (string) ($bd['trip_label'] ?? '');
    $baseMiles  = (int) ($bd['base_miles'] ?? 0);
    $baseRate   = (float) ($bd['base_rate'] ?? 0);
    $basePay    = (float) ($bd['base_pay'] ?? 0);
    if ($tripLabel === 'One-way' || $tripLabel === 'Round-trip' || $basePay !== 0.0) {
        $label = $baseMiles > 0
            ? sprintf(
                'Loaded Pay: %d Miles <span class="text-brand-muted">@ $%s</span>',
                $baseMiles,
                number_format($baseRate, 4),
            )
            : sprintf('Loaded Pay: %d Miles', $baseMiles);
        $rows[] = $row($label, $basePay);
    }

    if ((float) ($bd['empty_pay'] ?? 0) !== 0.0) {
        $emptyMiles = (int) ($bd['empty_miles'] ?? 0);
        $emptyRate  = (float) ($bd['empty_rate'] ?? 0);
        $label = $emptyMiles > 0
            ? sprintf(
                'Empty Pay: %d Miles <span class="text-brand-muted">@ $%s</span>',
                $emptyMiles,
                number_format($emptyRate, 4),
            )
            : 'Empty Pay:';
        $rows[] = $row($label, (float) $bd['empty_pay']);
    }

    if ((float) ($bd['shift_pay'] ?? 0) !== 0.0) {
        $rows[] = $row(
            sprintf('Shift Pay <span class="text-pink-500">(%s)</span>', $pct((float) ($bd['shift_pct'] ?? 0))),
            (float) $bd['shift_pay'],
        );
    }
    if ((float) ($bd['seniority_pay'] ?? 0) !== 0.0) {
        $rows[] = $row(
            sprintf('Seniority Pay <span class="text-purple-500">(%s)</span>', $pct((float) ($bd['seniority_pct'] ?? 0))),
            (float) $bd['seniority_pay'],
        );
    }
    if ((float) ($bd['weekend_pay'] ?? 0) !== 0.0) {
        $rows[] = $row(
            sprintf('Weekend <span class="text-amber-500">(%s)</span>', $pct((float) ($bd['weekend_pct'] ?? 0))),
            (float) $bd['weekend_pay'],
        );
    }
    if ((float) ($bd['split_pay'] ?? 0) !== 0.0) {
        $rows[] = $row('Split Pay', (float) $bd['split_pay']);
    }
    if ((float) ($bd['backhaul_pay'] ?? 0) !== 0.0) {
        $rows[] = $row('Backhaul', (float) $bd['backhaul_pay']);
    }
    if ((float) ($bd['dem_pay'] ?? 0) !== 0.0) {
        $rows[] = $row('Demurrage', (float) $bd['dem_pay']);
    }
    if ((float) ($bd['break_pay'] ?? 0) !== 0.0) {
        $rows[] = $row('Breakdown', (float) $bd['break_pay']);
    }
    if ((float) ($bd['extra_pay'] ?? 0) !== 0.0) {
        $rows[] = $row('Extra Pay', (float) $bd['extra_pay']);
    }

    $rows[] = '<tr class="border-t border-slate-300 dark:border-slate-600">'
        . '<td class="py-1.5 px-3 font-bold">Total Load Pay</td>'
        . '<td class="py-1.5 px-3 text-right font-bold text-brand-primary">'
        . e($money((float) ($bd['np'] ?? 0))) . '</td></tr>';

    return implode('', $rows);
};

/** Note shown next to a sub-total row that could not be / was not repriced. */
$bucketNote = static function (array $bucket): ?string {
    if (! $bucket['repriced']) {
        return 'legacy row type — shown at stored pay';
    }
    if ($bucket['has_draft'] === null) {
        return 'flat trainer pay — no tiers apply';
    }
    return $bucket['has_draft'] ? null : 'no draft yet — compared at current rates';
};
?>
<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="m-0">
                Preview draft —
                <?= $focused ? e($trip_label) . ' only' : 'everything on your dashboard' ?>
            </h1>
            <p class="text-brand-muted mt-1 mb-0 text-sm">
                Nothing has been saved. This dry-run reprices
                <?php if ($focused): ?>
                    the <code><?= e($trip_type) ?></code> loads on <strong>your own
                    dashboard</strong>
                <?php else: ?>
                    every load on <strong>your own dashboard</strong> —
                    round-trip, one-way and trainer alike
                <?php endif; ?>
                for the pay week <code><?= e($week_start) ?></code> →
                <code><?= e($week_end) ?></code>, side by side with what each load
                currently shows. That is the same set of loads the dashboard's
                <em>This Week</em> card adds up, so the totals here can be
                checked against it line for line.
                Nobody else's loads are read here — the fleet-wide dump is the
                super-admin <code>/loads</code> page.
            </p>
        </div>
        <a href="<?= e($base) ?>/pay-admin" class="btn-secondary btn-sm">← Back to pay-admin</a>
    </div>

    <p class="text-sm mt-3 mb-0 flex flex-wrap gap-2 items-center">
        <?php if ($focused): ?>
            <a href="<?= e($base) ?>/pay-admin/preview?date=<?= e($anchor) ?>"
               class="btn-secondary btn-sm">Show every trip type</a>
            <a href="<?= e($base) ?>/pay-admin/preview?trip_type=<?= e($otherType($trip_type)) ?>&amp;date=<?= e($anchor) ?>"
               class="btn-secondary btn-sm">Focus <?= e(str_replace('_', '-', $otherType($trip_type))) ?></a>
        <?php else: ?>
            <a href="<?= e($base) ?>/pay-admin/preview?trip_type=round_trip&amp;date=<?= e($anchor) ?>"
               class="btn-secondary btn-sm">Focus round-trip only</a>
            <a href="<?= e($base) ?>/pay-admin/preview?trip_type=long_haul&amp;date=<?= e($anchor) ?>"
               class="btn-secondary btn-sm">Focus one-way only</a>
        <?php endif; ?>
    </p>
</div>

<div class="card">
    <h2 class="m-0">Aggregate impact</h2>
    <p class="text-brand-muted mt-1 mb-0 text-sm">
        <?= $focused ? e($trip_label) . ' loads' : 'All your loads' ?>
        for the week of <code><?= e($week_start) ?></code> →
        <code><?= e($week_end) ?></code>.
    </p>
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

    <?php if ($buckets !== []): ?>
        <div class="table-wrap mt-5">
            <table class="data-table text-[13px] w-full">
                <thead>
                    <tr>
                        <th class="text-left">Trip type</th>
                        <th class="text-right">Loads</th>
                        <th class="text-right">Current</th>
                        <th class="text-right">Projected</th>
                        <th class="text-right">Δ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buckets as $bucket): ?>
                        <?php $note = $bucketNote($bucket); ?>
                        <tr>
                            <td>
                                <?= e($bucket['label']) ?>
                                <?php if ($bucket['unsaved_count'] > 0): ?>
                                    <span class="text-amber-700 dark:text-amber-300 text-xs">
                                        (incl. <?= (int) $bucket['unsaved_count'] ?> unconfirmed)
                                    </span>
                                <?php endif; ?>
                                <?php if ($note !== null): ?>
                                    <br><small class="text-brand-muted"><?= e($note) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= (int) $bucket['count'] ?></td>
                            <td class="text-right whitespace-nowrap"><?= e($money($bucket['old'])) ?></td>
                            <td class="text-right whitespace-nowrap font-semibold"><?= e($money($bucket['new'])) ?></td>
                            <td class="text-right whitespace-nowrap <?= $deltaClass($bucket['new'] - $bucket['old']) ?>">
                                <?= abs($bucket['new'] - $bucket['old']) >= 0.005
                                    ? e($signedMoney($bucket['new'] - $bucket['old']))
                                    : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="border-t-2 border-slate-300 dark:border-slate-600">
                        <td class="font-bold">Total</td>
                        <td class="text-right font-bold"><?= (int) $row_count ?></td>
                        <td class="text-right whitespace-nowrap font-bold"><?= e($money($total_old)) ?></td>
                        <td class="text-right whitespace-nowrap font-bold"><?= e($money($total_new)) ?></td>
                        <td class="text-right whitespace-nowrap font-bold <?= $deltaClass($delta_total) ?>">
                            <?= abs($delta_total) >= 0.005 ? e($signedMoney($delta_total)) : '—' ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($unsaved_count > 0): ?>
        <p class="text-brand-muted mt-4 mb-0 text-sm">
            <?= (int) $saved_count ?> saved load(s)
            <?= e($money($total_old_saved)) ?> →
            <?= e($money($total_new_saved)) ?>,
            plus <strong><?= (int) $unsaved_count ?></strong> unconfirmed load(s)
            kept in this browser
            <?= e($money($total_old_unsaved)) ?> →
            <?= e($money($total_new_unsaved)) ?>.
            <span class="text-amber-700 dark:text-amber-300">
                Unconfirmed loads live only in this browser and are marked
                <code>—</code> in the FRTL column.
            </span>
        </p>
    <?php endif; ?>

    <?php if ($unsaved_error !== null): ?>
        <p class="mt-4 mb-0 text-sm text-amber-800 dark:text-amber-300">
            <strong>Unconfirmed loads:</strong> <?= e($unsaved_error) ?>
        </p>
    <?php endif; ?>

    <?php if ($row_count === 0): ?>
        <p class="text-brand-muted mt-4 mb-0 text-sm">
            No <?= $focused ? e($trip_type) . ' ' : '' ?>loads on your dashboard for
            the week of <code><?= e($week_start) ?></code> →
            <code><?= e($week_end) ?></code> — nothing to compare yet. Enter a load,
            or point the preview at another week with <code>?date=YYYY-MM-DD</code>.
        </p>
    <?php endif; ?>
</div>

<?php // ------------------------------------------------------------------ ?>
<?php // Unconfirmed-loads (browser scratchpad) handoff.                    ?>
<?php //                                                                    ?>
<?php // Entries entered with "Store Load Info" OFF never reach the         ?>
<?php // database — they live in this browser's localStorage and the         ?>
<?php // dashboard hydrates them client-side. The preview is rendered        ?>
<?php // server-side, so the only way to reprice them is to hand them over:  ?>
<?php // this form's JS reads the same key the dashboard reads, filters to   ?>
<?php // the load types in scope + today (exactly the dashboard's hydration  ?>
<?php // rule), and submits once. The server recomputes their pay; nothing   ?>
<?php // about the payload's own money figures is trusted.                   ?>
<?php // ------------------------------------------------------------------ ?>
<div class="card" id="preview-local-card">
    <h2 class="m-0">Unconfirmed loads (in this browser)</h2>
    <p class="text-brand-muted mt-1 mb-0 text-sm">
        Loads entered with <em>Store Load Info</em> off stay in this browser
        until you add a FRTL #. They show on your dashboard, so they belong in
        this projection too.
    </p>

    <p class="mt-3 mb-3 text-sm" id="preview-local-status">Checking this browser…</p>

    <form method="post" action="<?= e($base) ?>/pay-admin/preview"
          id="preview-local-form"
          data-state="<?= $unsaved_requested ? 'included' : 'pending' ?>"
          data-trip-type="<?= e($trip_type !== '' ? $trip_type : 'all') ?>"
          data-load-types="<?= e(implode(',', $scope_load_types)) ?>"
          data-today="<?= e($today) ?>"
          class="m-0 flex flex-wrap items-center gap-2">
        <input type="hidden" name="_csrf" value="<?= e($csrf_token) ?>">
        <input type="hidden" name="trip_type" value="<?= e($trip_type) ?>">
        <input type="hidden" name="date" value="<?= e($anchor) ?>">
        <input type="hidden" name="unsaved_loads" id="preview-unsaved-payload" value="">
        <button type="submit" class="btn-secondary btn-sm" id="preview-local-submit">
            Include unconfirmed loads
        </button>
    </form>

    <noscript>
        <p class="text-brand-muted mt-3 mb-0 text-sm">
            JavaScript is off in this browser, so the unconfirmed loads stored
            here cannot be read. The tables below cover the saved loads only.
        </p>
    </noscript>
</div>

<?php if ($row_count > 0): ?>
    <div class="card">
        <h2 class="m-0">Per-load diff</h2>
        <p class="text-brand-muted mt-1 text-sm">
            Your own loads, newest-first. Deltas ≥ $0.01 are highlighted; loads
            that price identically are shown in muted rows. Expand a row for the
            projected breakdown of that load's pay. The driver column that used
            to be here is gone on purpose — every row is yours.
        </p>
        <div class="table-wrap mt-3">
            <table class="data-table text-[13px] w-full">
                <thead>
                    <tr>
                        <th class="text-left">Date</th>
                        <th class="text-left">Trip type</th>
                        <th class="text-left">FRTL</th>
                        <th class="text-left">Pickup → Delivery</th>
                        <th class="text-right">Current</th>
                        <th class="text-right">Projected</th>
                        <th class="text-right">Δ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparisons as $c): ?>
                        <?php
                        $changed  = abs($c['delta']) >= 0.005;
                        $unsaved  = $c['source'] === 'unsaved';
                        $bd       = is_array($c['new_breakdown'] ?? null) ? $c['new_breakdown'] : null;
                        $tripName = (string) ($bd['trip_label'] ?? ($c['repriced'] ? '' : 'Not repriced'));
                        ?>
                        <tr class="<?= $changed ? '' : 'opacity-50' ?> <?= $unsaved ? 'bg-amber-50 dark:bg-amber-950/30' : '' ?>">
                            <td class="whitespace-nowrap"><?= e($c['date']) ?></td>
                            <td class="whitespace-nowrap"><?= e($tripName !== '' ? $tripName : '—') ?></td>
                            <td class="whitespace-nowrap">
                                <?php if ($unsaved): ?>
                                    <code title="No FRTL # yet — this load lives in the browser">—</code>
                                <?php else: ?>
                                    <code><?= (int) $c['frtl'] ?></code>
                                <?php endif; ?>
                            </td>
                            <td class="break-words max-w-md">
                                <?= e($c['pickup']) ?>
                                <span class="text-brand-muted">→</span>
                                <?= e($c['delivery']) ?>
                                <?php if ($unsaved): ?>
                                    <br><small class="text-amber-900 dark:text-amber-300">
                                        unconfirmed — in this browser only
                                        <?php if (is_string($c['local_id']) && $c['local_id'] !== ''): ?>
                                            &middot; <a class="underline"
                                                href="<?= e($base) ?>/loads/new?unsaved=<?= e(urlencode($c['local_id'])) ?>"
                                                title="Edit this unconfirmed load">Edit</a>
                                        <?php endif; ?>
                                    </small>
                                <?php elseif ($c['notes'] !== ''): ?>
                                    <br><small class="text-brand-muted"><?= e($c['notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right whitespace-nowrap"><?= e($money($c['old_np'])) ?></td>
                            <td class="text-right whitespace-nowrap font-semibold"><?= e($money($c['new_np'])) ?></td>
                            <td class="text-right whitespace-nowrap <?= $deltaClass($c['delta']) ?>">
                                <?= $changed ? e($signedMoney($c['delta'])) : '—' ?>
                            </td>
                        </tr>
                        <tr class="<?= $unsaved ? 'bg-amber-50 dark:bg-amber-950/30' : '' ?>">
                            <td colspan="7" class="px-5 py-3">
                                <details>
                                    <summary class="cursor-pointer text-brand-primary font-semibold">
                                        Projected pay breakdown under the draft
                                        <?php if ($bd !== null): ?>
                                            — <?= e($tripName !== '' ? $tripName : '?') ?>
                                            (<?= e((string) ($bd['tenure_band'] ?? '?')) ?>&nbsp;M&nbsp;|&nbsp;<?= e(ucfirst((string) ($bd['shift'] ?? '?'))) ?>)
                                        <?php endif; ?>
                                    </summary>
                                    <?php if ($c['notes'] !== '' && ! $unsaved): ?>
                                        <div class="mt-2 px-3 py-2 bg-amber-50 dark:bg-amber-950/40 border-l-4 border-amber-400 text-slate-700 dark:text-slate-200 text-[13px] whitespace-pre-wrap break-words">
                                            <strong class="text-amber-900 dark:text-amber-300">Notes:</strong>
                                            <?= e($c['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <table class="mt-2 text-[13px]">
                                        <tbody><?= $breakdownRows($bd) ?></tbody>
                                    </table>
                                    <?= $bracketNote($bd) ?>
                                    <p class="text-brand-muted mt-2 mb-0 text-xs">
                                        Current column shows <?= $unsaved ? 'what this load pays today (recomputed)' : 'the stored pay on the load' ?>:
                                        <?= e($money($c['old_np'])) ?>. Projected is the same load repriced
                                        against the draft tiers. Nothing has been written.
                                        <?php if (! $c['repriced']): ?>
                                            This row's stored load type can't be priced from the tier
                                            tables, so it is shown at its stored pay.
                                        <?php endif; ?>
                                    </p>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
// Tier tables: the focused type only when focused, both types otherwise.
$tierTypes = $focused ? [$trip_type] : ['round_trip', 'long_haul'];
?>
<?php foreach ($tierTypes as $tierType): ?>
    <?php
    $cur = $current_tiers[$tierType] ?? [];
    $drf = $draft_tiers[$tierType] ?? [];

    // Rows that look wrong rather than merely different: the ladder that
    // will pay these loads, checked for a rung below a shorter one and for
    // placeholder-style values. Reasons are spelled out under the table.
    $ladderMap = [];
    foreach ($drf !== [] ? $drf : $cur as $t) {
        $ladderMap[(int) $t['miles']] = (float) $t['rate'];
    }
    $tierFlags = \PayTracker\Models\PayRate::flagRungs($ladderMap);
    ?>
    <div class="card">
        <h2 class="m-0"><?= e($tierType === 'round_trip' ? 'Round-trip' : 'Long-haul') ?> tiers — draft vs current</h2>
        <?php if ($drf === []): ?>
            <p class="text-brand-muted mt-1 mb-0 text-sm">
                No draft for this trip type, so its loads above are compared at
                current rates (<code>Δ $0.00</code>). Start a draft on
                <a href="<?= e($base) ?>/pay-admin#bucket-<?= e($tierType) ?>">/pay-admin</a>
                to include it in the projection.
            </p>
        <?php endif; ?>
        <p class="text-brand-muted mt-2 mb-0 text-xs">
            A load takes the lowest row whose <strong>Miles ≤</strong> value is at least its own
            mileage, and that row's value is the flat pay for the whole bracket — not a
            per-mile rate. <strong>Covers</strong> shows the mileages each row pays, and every
            load's breakdown names the row that paid it, so an edit that moves nothing is
            visible as such instead of looking like a broken preview.
        </p>
        <div class="table-wrap mt-3">
            <table class="data-table text-[13px]">
                <thead>
                    <tr>
                        <th class="text-left">Miles ≤</th>
                        <th class="text-left">Covers</th>
                        <th class="text-right">Current row pay</th>
                        <th class="text-right">Draft row pay</th>
                        <th class="text-right">Δ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Merge on miles so a row exists for any tier in either
                    // side — a draft can add or remove a tier vs current.
                    $byMiles = [];
                    foreach ($cur as $t) {
                        $byMiles[(int) $t['miles']]['current'] = (float) $t['rate'];
                    }
                    foreach ($drf as $t) {
                        $byMiles[(int) $t['miles']]['draft'] = (float) $t['rate'];
                    }
                    ksort($byMiles);
                    foreach ($byMiles as $miles => $pair):
                        $curRate   = $pair['current'] ?? null;
                        $draftRate = $pair['draft']   ?? null;
                        $delta     = ($curRate !== null && $draftRate !== null) ? $draftRate - $curRate : null;
                        ?>
                        <tr>
                            <td class="whitespace-nowrap"><?= (int) $miles ?><?php if (isset($tierFlags[(int) $miles])): ?> <span class="text-amber-600 dark:text-amber-400" title="<?= e($tierFlags[(int) $miles]) ?>" aria-label="flagged: <?= e($tierFlags[(int) $miles]) ?>">&#9888;</span><?php endif; ?></td>
                            <td class="whitespace-nowrap text-brand-muted"><?= e($bandLabel($drf !== [] ? $drf : $cur, (int) $miles) ?? '—') ?></td>
                            <td class="text-right whitespace-nowrap"><?= $curRate !== null ? '$' . number_format($curRate, 4) : '<em class="text-brand-muted">removed</em>' ?></td>
                            <td class="text-right whitespace-nowrap font-semibold"><?= $draftRate !== null ? '$' . number_format($draftRate, 4) : '<em class="text-brand-muted">added</em>' ?></td>
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
        <?php if ($tierFlags !== []): ?>
            <div class="mt-3 px-3 py-2 bg-amber-50 dark:bg-amber-950/40 border-l-4 border-amber-400 text-slate-700 dark:text-slate-200 text-[13px]">
                <strong class="text-amber-900 dark:text-amber-300">Ladder looks off at:</strong>
                <ul class="mb-0 mt-1 pl-4">
                    <?php foreach ($tierFlags as $flaggedMiles => $reason): ?>
                        <li><code><?= (int) $flaggedMiles ?></code> mi — <?= e($reason) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="card">
    <p class="m-0 text-sm">
        <a href="<?= e($base) ?>/pay-admin">← Back to pay-admin</a>
        &middot; From here you can hand-tweak individual draft tiers, click
        <strong>Bump draft by %</strong> again to try a different number,
        <strong>Reset draft to current</strong> to throw the draft away,
        or <strong>Promote</strong> when you're ready to make it live.
    </p>
</div>

<script>
    // -------------------------------------------------------------------
    // Hand the browser's unconfirmed loads to the server so they can be
    // repriced with the draft.
    //
    // This mirrors the dashboard's hydration rule exactly: the same
    // localStorage key, the same 24-hour TTL, the same "today only"
    // restriction (past/future dates on the dashboard are DB-backed by
    // spec), and the same trip types in scope. The server re-validates and
    // recomputes everything — this script only moves the data.
    //
    // Loop safety: the page renders data-state="pending" for the GET render
    // and "included" once the payload has been submitted, so the auto-submit
    // fires at most once per arrival.
    // -------------------------------------------------------------------
    (function () {
        const form    = document.getElementById('preview-local-form');
        const card    = document.getElementById('preview-local-card');
        const status  = document.getElementById('preview-local-status');
        const payload = document.getElementById('preview-unsaved-payload');
        const button  = document.getElementById('preview-local-submit');
        if (!form || !card || !status || !payload || !button) return;

        const ENTRIES_KEY = 'paytracker.unsavedLoads';
        const TTL_MS      = 24 * 60 * 60 * 1000;
        const today       = form.dataset.today || '';
        const state       = form.dataset.state || 'pending';
        const scopeTypes  = (form.dataset.loadTypes || '')
            .split(',')
            .map(v => Number(v.trim()))
            .filter(v => !Number.isNaN(v));
        const scopeLabel  = form.dataset.tripType === 'all' || form.dataset.tripType === ''
            ? 'loads'
            : form.dataset.tripType.replace('_', '-') + ' loads';

        function readEntries() {
            let raw;
            try { raw = localStorage.getItem(ENTRIES_KEY); }
            catch (e) { return { entries: [], blocked: true }; }
            if (!raw) return { entries: [], blocked: false };
            let arr;
            try { arr = JSON.parse(raw); }
            catch (e) { return { entries: [], blocked: false }; }
            if (!Array.isArray(arr)) return { entries: [], blocked: false };
            const now = Date.now();
            return {
                entries: arr.filter(e => e && typeof e === 'object'
                    && e.computed && typeof e.computed === 'object'
                    && typeof e.created_at === 'number'
                    && (now - e.created_at) < TTL_MS),
                blocked: false,
            };
        }

        const { entries, blocked } = readEntries();
        const eligible = entries.filter(e =>
            scopeTypes.includes(Number(e.computed.load_type))
            && String(e.computed.date || '').slice(0, 10) === today);

        if (blocked) {
            status.textContent = 'This browser is blocking local storage, so unconfirmed loads cannot be read.';
            button.disabled = true;
            return;
        }

        if (eligible.length === 0) {
            status.textContent = state === 'included'
                ? 'No unconfirmed ' + scopeLabel + ' in this browser for today — the tables above are complete.'
                : 'No unconfirmed ' + scopeLabel + ' in this browser for today.';
            button.disabled = true;
            button.textContent = 'No unconfirmed loads to include';
            return;
        }

        payload.value = JSON.stringify(eligible);

        if (state === 'included') {
            status.innerHTML = 'Included <strong>' + eligible.length + '</strong> unconfirmed load(s) from this browser in the projection below.';
            button.textContent = 'Re-include ' + eligible.length + ' unconfirmed load(s)';
            return;
        }

        status.innerHTML = 'Found <strong>' + eligible.length + '</strong> unconfirmed load(s) in this browser — adding them to the projection…';
        button.textContent = 'Include ' + eligible.length + ' unconfirmed load(s)';

        // One auto-submit per GET arrival; the POST render carries
        // data-state="included" so this cannot loop. Deferred to the load
        // event so the submit doesn't abort a half-finished page load.
        const submit = () => form.submit();
        if (document.readyState === 'complete') {
            submit();
        } else {
            window.addEventListener('load', submit, { once: true });
        }
    })();
</script>
