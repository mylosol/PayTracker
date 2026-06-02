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
 *   out_of_route_miles:?int, np:string, op:string, pay_breakdown:?string,
 * }> $rows
 * @var array{count:int, np_total:string, op_total:string, miles_total:int} $totals
 * @var string $csrfToken
 * @var string|null $flash
 * @var array{band:string, shift:string} $effective Current tenure/shift readout.
 */
layout('layouts/app');

$npTotalF    = number_format((float) $totals['np_total'], 2);
$milesTotalF = number_format((int)   $totals['miles_total']);

$loadTypeLabel = static function (?int $t): string {
    if ($t === null) return '?';
    if ($t === 0)    return 'One-way';
    if ($t === 1)    return 'Round-trip';
    if ($t === 4)    return 'Trainer';
    return (string) $t;
};

/**
 * Decode a row's pay_breakdown JSON. Returns null when the breakdown
 * is missing (legacy rows backfilled before the column existed) or
 * unparseable.
 *
 * @return array<string,mixed>|null
 */
$decodeBreakdown = static function (?string $json): ?array {
    if ($json === null || $json === '') return null;
    try {
        $arr = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    } catch (\Throwable) {
        return null;
    }
    return is_array($arr) ? $arr : null;
};

$money = static fn (float $v): string => '$' . number_format($v, 2);
$pct   = static fn (float $v): string => number_format($v * 100, 2) . '%';
?>
<?php if ($flash !== null): ?>
    <div class="card" style="background:#dcfce7;color:#166534;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h1>My pay &mdash; <?= e($date) ?><?= $isToday ? ' <span class="pill ok">today</span>' : '' ?></h1>
    <p class="muted">
        Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong>
        (driver id <?= (int) ($driver['id'] ?? 0) ?>).
        Pay totals reflect the most recent
        <a href="<?= e($base) ?>/pay-admin">pay-admin recompute</a>.
    </p>
    <p style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <span class="pill"
              style="background:#0f172a;color:#e2e8f0;padding:.35rem .8rem;font-size:12px;letter-spacing:.04em;text-transform:uppercase;border-radius:6px;">
            <?= e($effective['band']) ?>&nbsp;M &nbsp;|&nbsp; <?= e(ucfirst($effective['shift'])) ?> Shift
        </span>
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
        <a href="<?= e($base) ?>/profile"
           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem .8rem;border-radius:6px;text-decoration:none;">
            Profile
        </a>
    </p>
</div>

<div class="card">
    <h2>Totals</h2>
    <table style="border-collapse:collapse;font-size:14px;">
        <tbody>
            <tr><td style="padding:.3rem .8rem;"><strong>Loads</strong></td><td style="padding:.3rem .8rem;"><code><?= (int) $totals['count'] ?></code></td></tr>
            <tr><td style="padding:.3rem .8rem;"><strong>Net pay</strong></td><td style="padding:.3rem .8rem;"><code>$<?= e($npTotalF) ?></code></td></tr>
            <tr><td style="padding:.3rem .8rem;"><strong>Miles</strong></td><td style="padding:.3rem .8rem;"><code><?= e($milesTotalF) ?></code></td></tr>
        </tbody>
    </table>

    <?php if ($totals['count'] > 0 && (float) $totals['np_total'] === 0.0): ?>
        <p class="muted" style="background:#fef3c7;color:#92400e;border-radius:6px;padding:.5rem .8rem;margin-top:.8rem;">
            <strong>Heads up:</strong> there are <?= (int) $totals['count'] ?> load(s) on
            this date but the net pay total is $0.00 &mdash; the stored pay
            may not have been computed yet. Click <strong>Refresh my pay</strong>
            below to recompute.
        </p>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/dashboard/recompute" style="margin-top:1rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="date"  value="<?= e($date) ?>">
        <button type="submit"
                style="background:#16a34a;color:#fff;border:0;padding:.4rem 1rem;border-radius:6px;font:inherit;cursor:pointer;">
            Refresh my pay
        </button>
        <small class="muted">
            Recomputes pay for your loads since <?= e(date('Y-m-d', strtotime($date . ' -30 days'))) ?>.
            Safe to click repeatedly &mdash; the math is deterministic.
        </small>
    </form>
</div>

<div class="card">
    <h2>Loads</h2>
    <?php if ($rows === []): ?>
        <p class="muted">No loads on <?= e($date) ?>.</p>
    <?php else: ?>
        <table style="border-collapse:collapse;font-size:13px;width:100%;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                    <th style="padding:.3rem .5rem;width:1.5rem;"></th>
                    <th style="padding:.3rem .5rem;">FRTL</th>
                    <th style="padding:.3rem .5rem;">Time</th>
                    <th style="padding:.3rem .5rem;">Type</th>
                    <th style="padding:.3rem .5rem;">Pickup &rarr; Delivery</th>
                    <th style="padding:.3rem .5rem;text-align:right;">Load Pay</th>
                    <th style="padding:.3rem .5rem;text-align:right;">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $bd = $decodeBreakdown($row['pay_breakdown'] ?? null);
                ?>
                    <tr style="border-bottom:1px solid #f0f2f6;vertical-align:top;">
                        <td style="padding:.25rem .5rem;text-align:center;">
                            <?php if ($bd !== null): ?>
                                <details><summary
                                    style="list-style:none;cursor:pointer;color:var(--accent);font-weight:600;display:inline-block;"
                                    title="Show pay breakdown">&#x25B8;</summary></details>
                            <?php endif; ?>
                        </td>
                        <td style="padding:.25rem .5rem;"><code><?= (int) $row['frtl'] ?></code></td>
                        <td style="padding:.25rem .5rem;"><code><?= e(substr((string) $row['date'], 11, 5)) ?></code></td>
                        <td style="padding:.25rem .5rem;"><?= e($loadTypeLabel($row['load_type'])) ?></td>
                        <td style="padding:.25rem .5rem;">
                            <?= e((string) ($row['pickup_city'] ?? '?')) ?>
                            &nbsp;&rarr;&nbsp;
                            <?= e((string) ($row['delivery_city'] ?? '?')) ?>
                        </td>
                        <td style="padding:.25rem .5rem;text-align:right;"><code>$<?= number_format((float) $row['np'], 2) ?></code></td>
                        <td style="padding:.25rem .5rem;text-align:right;white-space:nowrap;">
                            <a href="<?= e($base) ?>/loads/<?= (int) $row['frtl'] ?>/edit"
                               style="color:var(--accent);text-decoration:none;font-size:12px;margin-right:.3rem;"
                               title="Edit this load">Edit</a>
                            <form method="post" action="<?= e($base) ?>/loads/<?= (int) $row['frtl'] ?>/delete"
                                  style="display:inline;margin:0;"
                                  onsubmit="return confirm('Delete load #<?= (int) $row['frtl'] ?>? This cannot be undone.');">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit"
                                        style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:12px;padding:0;font:inherit;text-decoration:underline;"
                                        title="Delete this load">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php if ($bd !== null): ?>
                        <tr style="background:#f8fafc;border-bottom:1px solid #f0f2f6;">
                            <td colspan="7" style="padding:.6rem 1.2rem;">
                                <details>
                                    <summary style="cursor:pointer;color:var(--accent);font-weight:600;">
                                        Pay breakdown &mdash; <?= e((string) ($bd['trip_label'] ?? '?')) ?>
                                        (<?= e((string) ($bd['tenure_band'] ?? '?')) ?>&nbsp;M&nbsp;|&nbsp;<?= e(ucfirst((string) ($bd['shift'] ?? '?'))) ?>)
                                    </summary>
                                    <table style="border-collapse:collapse;font-size:13px;margin-top:.4rem;">
                                        <tbody>
                                            <?php if ((float) ($bd['base_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">
                                                        <?= (int) ($bd['base_miles'] ?? 0) ?> Miles Base
                                                    </td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['base_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ((float) ($bd['empty_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">
                                                        Empty Pay:
                                                        <?php if ((int) ($bd['empty_miles'] ?? 0) > 0): ?>
                                                            <?= (int) $bd['empty_miles'] ?> Miles
                                                            <span class="muted">@ $<?= number_format((float) ($bd['empty_rate'] ?? 0), 4) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['empty_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ((float) ($bd['shift_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">
                                                        Shift Pay <span style="color:#ec4899;">(<?= e($pct((float) $bd['shift_pct'])) ?>)</span>
                                                    </td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['shift_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ((float) ($bd['seniority_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">
                                                        Seniority Pay <span style="color:#a855f7;">(<?= e($pct((float) $bd['seniority_pct'])) ?>)</span>
                                                    </td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['seniority_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ((float) ($bd['weekend_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">
                                                        Weekend <span style="color:#f59e0b;">(<?= e($pct((float) $bd['weekend_pct'])) ?>)</span>
                                                    </td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['weekend_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ((float) ($bd['split_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">Split Pay</td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['split_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ((float) ($bd['dem_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">Demurrage</td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['dem_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ((float) ($bd['break_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">Breakdown</td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['break_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if ((float) ($bd['extra_pay'] ?? 0) !== 0.0): ?>
                                                <tr>
                                                    <td style="padding:.2rem .8rem;color:#475569;">Extra Pay</td>
                                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                                        <?= e($money((float) $bd['extra_pay'])) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr style="border-top:1px solid #cbd5e1;">
                                                <td style="padding:.3rem .8rem;font-weight:700;">Total Load Pay</td>
                                                <td style="padding:.3rem .8rem;text-align:right;font-weight:700;color:#f59e0b;">
                                                    <?= e($money((float) ($bd['np'] ?? 0))) ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </details>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="muted" style="margin-top:1rem;font-size:12px;">
        Showing typed-column rows from <code>driver_loads</code>. For the
        backfill diagnostic surface (legacy blob strings, all drivers),
        see <a href="<?= e($base) ?>/loads">/loads</a>. Rows without a
        breakdown were created before pay_breakdown was tracked &mdash;
        click <strong>Refresh my pay</strong> to backfill them.
    </p>
</div>
