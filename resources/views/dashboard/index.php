<?php
/**
 * @var string $base
 * @var array<string,mixed> $driver
 * @var string $date
 * @var bool   $isToday
 * @var string $today
 * @var string $prevDate
 * @var string $nextDate
 * @var list<array{
 *   frtl:int, date:string,
 *   load_type:?int, pickup_city:?string, delivery_city:?string,
 *   empty_miles:?int, is_split:?int, is_weekend:?int,
 *   extra_pay:?string, dem_minutes:?int, break_minutes:?int,
 *   out_of_route_miles:?int, np:string, op:string,
 * }> $rows
 * @var array{count:int, np_total:string, op_total:string, miles_total:int} $totals
 */
layout('layouts/app');

$npTotalF    = number_format((float) $totals['np_total'], 2);
$opTotalF    = number_format((float) $totals['op_total'], 2);
$milesTotalF = number_format((int)   $totals['miles_total']);

$loadTypeLabel = static function (?int $t): string {
    if ($t === null) return '?';
    if ($t === 0)    return 'One-way';
    if ($t === 1)    return 'Round-trip';
    if ($t === 4)    return 'Trainer';
    return (string) $t;
};
?>
<div class="card">
    <h1>My pay &mdash; <?= e($date) ?><?= $isToday ? ' <span class="pill ok">today</span>' : '' ?></h1>
    <p class="muted">
        Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong>
        (driver id <?= (int) ($driver['id'] ?? 0) ?>).
        Pay totals reflect the most recent
        <a href="<?= e($base) ?>/pay-admin">pay-admin recompute</a>.
    </p>
    <p style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <a href="<?= e($base) ?>/dashboard?date=<?= e($prevDate) ?>"
           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem .8rem;border-radius:6px;text-decoration:none;">
            &larr; <?= e($prevDate) ?>
        </a>
        <?php if (! $isToday): ?>
            <a href="<?= e($base) ?>/dashboard"
               style="display:inline-block;background:var(--accent);color:#fff;padding:.4rem .8rem;border-radius:6px;text-decoration:none;">
                Today
            </a>
        <?php endif; ?>
        <a href="<?= e($base) ?>/dashboard?date=<?= e($nextDate) ?>"
           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem .8rem;border-radius:6px;text-decoration:none;">
            <?= e($nextDate) ?> &rarr;
        </a>
        &nbsp;
        <a href="<?= e($base) ?>/loads/new"
           style="display:inline-block;background:var(--accent);color:#fff;padding:.4rem .8rem;border-radius:6px;text-decoration:none;">
            + Add load
        </a>
    </p>
</div>

<div class="card">
    <h2>Totals</h2>
    <table style="border-collapse:collapse;font-size:14px;">
        <tbody>
            <tr><td style="padding:.3rem .8rem;"><strong>Loads</strong></td><td style="padding:.3rem .8rem;"><code><?= (int) $totals['count'] ?></code></td></tr>
            <tr><td style="padding:.3rem .8rem;"><strong>Net pay (np)</strong></td><td style="padding:.3rem .8rem;"><code>$<?= e($npTotalF) ?></code></td></tr>
            <tr><td style="padding:.3rem .8rem;"><strong>Old pay (op)</strong></td><td style="padding:.3rem .8rem;"><code>$<?= e($opTotalF) ?></code></td></tr>
            <tr><td style="padding:.3rem .8rem;"><strong>Miles</strong></td><td style="padding:.3rem .8rem;"><code><?= e($milesTotalF) ?></code></td></tr>
        </tbody>
    </table>

    <?php if ($totals['count'] > 0 && (float) $totals['np_total'] === 0.0): ?>
        <p class="muted" style="background:#fef3c7;color:#92400e;border-radius:6px;padding:.5rem .8rem;margin-top:.8rem;">
            <strong>Heads up:</strong> there are <?= (int) $totals['count'] ?> load(s) on
            this date but np total is $0.00 &mdash; the stored pay columns may not
            have been computed yet. Ask the admin to run
            <a href="<?= e($base) ?>/pay-admin">/pay-admin &rarr; Recompute np/op</a>
            scoped to this date.
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Loads</h2>
    <?php if ($rows === []): ?>
        <p class="muted">No loads on <?= e($date) ?>.</p>
    <?php else: ?>
        <table style="border-collapse:collapse;font-size:13px;width:100%;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                    <th style="padding:.3rem .5rem;">FRTL</th>
                    <th style="padding:.3rem .5rem;">Time</th>
                    <th style="padding:.3rem .5rem;">Type</th>
                    <th style="padding:.3rem .5rem;">Pickup &rarr; Delivery</th>
                    <th style="padding:.3rem .5rem;text-align:right;">Empty mi</th>
                    <th style="padding:.3rem .5rem;text-align:center;">Split</th>
                    <th style="padding:.3rem .5rem;text-align:center;">Weekend</th>
                    <th style="padding:.3rem .5rem;text-align:right;">Extras</th>
                    <th style="padding:.3rem .5rem;text-align:right;">NP</th>
                    <th style="padding:.3rem .5rem;text-align:right;">OP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    // Sum the easily-visible "extras" amount surfaces — extras
                    // already roll into np/op via PayCalculator, so this is a
                    // sanity readout, not a separate column.
                    $extras = (float) ($row['extra_pay'] ?? 0)
                            + ((int) ($row['dem_minutes']   ?? 0)) * 0.391667
                            + ((int) ($row['break_minutes'] ?? 0)) * 0.391667;
                ?>
                    <tr style="border-bottom:1px solid #f0f2f6;">
                        <td style="padding:.25rem .5rem;"><code><?= (int) $row['frtl'] ?></code></td>
                        <td style="padding:.25rem .5rem;"><code><?= e(substr((string) $row['date'], 11, 5)) ?></code></td>
                        <td style="padding:.25rem .5rem;"><?= e($loadTypeLabel($row['load_type'])) ?></td>
                        <td style="padding:.25rem .5rem;">
                            <?= e((string) ($row['pickup_city'] ?? '?')) ?>
                            &nbsp;&rarr;&nbsp;
                            <?= e((string) ($row['delivery_city'] ?? '?')) ?>
                        </td>
                        <td style="padding:.25rem .5rem;text-align:right;"><code><?= (int) ($row['empty_miles'] ?? 0) ?></code></td>
                        <td style="padding:.25rem .5rem;text-align:center;"><?= ((int) ($row['is_split']   ?? 0)) === 1 ? '✓' : '' ?></td>
                        <td style="padding:.25rem .5rem;text-align:center;"><?= ((int) ($row['is_weekend'] ?? 0)) === 1 ? '✓' : '' ?></td>
                        <td style="padding:.25rem .5rem;text-align:right;"><code>$<?= number_format($extras, 2) ?></code></td>
                        <td style="padding:.25rem .5rem;text-align:right;"><code>$<?= number_format((float) $row['np'], 2) ?></code></td>
                        <td style="padding:.25rem .5rem;text-align:right;"><code>$<?= number_format((float) $row['op'], 2) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="muted" style="margin-top:1rem;font-size:12px;">
        Showing typed-column rows from <code>driver_loads</code>. For the
        backfill diagnostic surface (legacy blob strings, all drivers),
        see <a href="<?= e($base) ?>/loads">/loads</a>.
    </p>
</div>
