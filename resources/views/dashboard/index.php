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
 *   out_of_route_miles:?int, notes:?string,
 *   np:string, op:string, pay_breakdown:?string,
 * }> $rows
 * @var array{count:int, np_total:string, op_total:string, miles_total:int} $totals
 * @var array{count:int, np_total:string, op_total:string, miles_total:int} $weekTotals
 * @var string $weekStartDate  Sunday in YYYY-MM-DD format
 * @var string $weekEndDate    Saturday in YYYY-MM-DD format (inclusive end)
 * @var string $csrfToken
 * @var string|null $flash
 * @var array{band:string, shift:string} $effective Current tenure/shift readout.
 */
layout('layouts/app');

$npTotalF       = number_format((float) $totals['np_total'], 2);
$milesTotalF    = number_format((int)   $totals['miles_total']);
$weekNpTotalF   = number_format((float) $weekTotals['np_total'], 2);
$weekMilesF     = number_format((int)   $weekTotals['miles_total']);
$weekRangeLabel = date('M j', strtotime($weekStartDate)) . ' – ' . date('M j', strtotime($weekEndDate));

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
        <a href="<?= e($base) ?>/reconcile"
           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem .8rem;border-radius:6px;text-decoration:none;">
            Reconcile
        </a>
        <a href="<?= e($base) ?>/profile"
           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem .8rem;border-radius:6px;text-decoration:none;">
            Profile
        </a>
    </p>
</div>

<div class="card" style="background:linear-gradient(180deg,#f0fdf4,#fff);">
    <h2 style="margin-bottom:.4rem;">
        This Week
        <span class="muted" style="font-weight:400;font-size:14px;">
            (<?= e($weekRangeLabel) ?>)
        </span>
    </h2>
    <table style="border-collapse:collapse;font-size:14px;">
        <tbody>
            <tr><td style="padding:.3rem .8rem;"><strong>Loads</strong></td><td style="padding:.3rem .8rem;"><code><?= (int) $weekTotals['count'] ?></code></td></tr>
            <tr><td style="padding:.3rem .8rem;"><strong>Net Pay</strong></td><td style="padding:.3rem .8rem;"><code style="background:#dcfce7;color:#166534;font-weight:600;padding:2px 8px;">$<?= e($weekNpTotalF) ?></code></td></tr>
            <tr><td style="padding:.3rem .8rem;"><strong>Miles</strong></td><td style="padding:.3rem .8rem;"><code><?= e($weekMilesF) ?></code></td></tr>
        </tbody>
    </table>
    <p id="week-unconfirmed-note" hidden
       style="margin-top:.8rem;background:#fef3c7;color:#92400e;border-left:3px solid #f59e0b;border-radius:6px;padding:.6rem .8rem;font-size:13px;">
        <strong>Heads up:</strong> these weekly totals include
        <span data-unconfirmed-count>0</span> unconfirmed load(s) from
        today's browser scratchpad. They'll settle to the official
        figures once you edit each one and add the FRTL #.
    </p>
    <p class="muted" style="margin-top:.6rem;font-size:12px;">
        Pay week is configured in your <a href="<?= e($base) ?>/profile">profile</a>.
        The weekly total stays anchored to the week containing the
        viewed date — use the day-jump nav above to walk through it.
    </p>
</div>

<div class="card">
    <h2>Today <span class="muted" style="font-weight:400;font-size:14px;">(<?= e($date) ?>)</span></h2>
    <table style="border-collapse:collapse;font-size:14px;">
        <tbody>
            <tr><td style="padding:.3rem .8rem;"><strong>Loads</strong></td><td style="padding:.3rem .8rem;"><code><?= (int) $totals['count'] ?></code></td></tr>
            <tr><td style="padding:.3rem .8rem;"><strong>Net Pay</strong></td><td style="padding:.3rem .8rem;"><code>$<?= e($npTotalF) ?></code></td></tr>
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

<div class="card" data-loads-card data-is-today="<?= $isToday ? '1' : '0' ?>" data-date="<?= e($date) ?>">
    <h2>Loads</h2>
    <p class="muted" id="dashboard-no-loads-msg"<?= $rows !== [] ? ' hidden' : '' ?>>No loads on <?= e($date) ?>.</p>
    <?php if ($rows !== [] || $isToday): ?>
        <table id="dashboard-loads-table" style="border-collapse:collapse;font-size:13px;width:100%;<?= $rows === [] ? 'display:none;' : '' ?>">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                    <th style="padding:.3rem .5rem;width:1.5rem;"></th>
                    <th style="padding:.3rem .5rem;">FRTL</th>
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
                            <td colspan="6" style="padding:.6rem 1.2rem;">
                                <details>
                                    <summary style="cursor:pointer;color:var(--accent);font-weight:600;">
                                        Pay breakdown &mdash; <?= e((string) ($bd['trip_label'] ?? '?')) ?>
                                        (<?= e((string) ($bd['tenure_band'] ?? '?')) ?>&nbsp;M&nbsp;|&nbsp;<?= e(ucfirst((string) ($bd['shift'] ?? '?'))) ?>)
                                    </summary>
                                    <?php if (!empty($row['notes'])): ?>
                                        <div style="margin-top:.5rem;padding:.5rem .7rem;background:#fffbeb;border-left:3px solid #f59e0b;color:#475569;font-size:13px;white-space:pre-wrap;word-break:break-word;">
                                            <strong style="color:#92400e;">Notes:</strong>
                                            <?= e((string) $row['notes']) ?>
                                        </div>
                                    <?php endif; ?>
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
                    <?php elseif (!empty($row['notes'])): ?>
                        <tr style="background:#f8fafc;border-bottom:1px solid #f0f2f6;">
                            <td colspan="6" style="padding:.5rem 1.2rem;background:#fffbeb;border-left:3px solid #f59e0b;color:#475569;font-size:13px;white-space:pre-wrap;word-break:break-word;">
                                <strong style="color:#92400e;">Notes:</strong>
                                <?= e((string) $row['notes']) ?>
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

<?php if ($isToday): ?>
<script>
    // -------------------------------------------------------------------
    // Scratchpad hydration. Reads localStorage entries (rolling 24h),
    // injects rows into today's Loads table, and bumps the Today + Week
    // totals using ONLY the precomputed np figures from each entry
    // (never re-running the pay formula client-side).
    //
    // Past/future dates do not run this script — past-date views are
    // intentionally history-only per the FRTL-required design.
    // -------------------------------------------------------------------
    (function () {
        const ENTRIES_KEY = 'paytracker.unsavedLoads';
        const TTL_MS      = 24 * 60 * 60 * 1000;
        const basePath    = <?= json_encode($base) ?>;

        function readEntries() {
            let raw;
            try { raw = localStorage.getItem(ENTRIES_KEY); }
            catch (e) { return []; }
            if (!raw) return [];
            let arr;
            try { arr = JSON.parse(raw); }
            catch (e) { return []; }
            if (!Array.isArray(arr)) return [];
            const now = Date.now();
            const live = arr.filter(e => e && typeof e === 'object'
                && typeof e.created_at === 'number'
                && (now - e.created_at) < TTL_MS);
            if (live.length !== arr.length) {
                try { localStorage.setItem(ENTRIES_KEY, JSON.stringify(live)); } catch (e) {}
            }
            return live;
        }
        function writeEntries(arr) {
            try { localStorage.setItem(ENTRIES_KEY, JSON.stringify(arr)); } catch (e) {}
        }

        // Consume the pending-clear handoff from a scratchpad → DB save.
        // If the form on /loads/new POSTed successfully with an unsaved_id,
        // the dashboard drops that entry from localStorage on first paint.
        try {
            const pendingId = sessionStorage.getItem('paytracker.consumeLocalId');
            if (pendingId) {
                writeEntries(readEntries().filter(e => e.local_id !== pendingId));
                sessionStorage.removeItem('paytracker.consumeLocalId');
            }
        } catch (e) { /* fail silent */ }

        const card = document.querySelector('[data-loads-card]');
        if (!card) return;
        const date = card.dataset.date; // YYYY-MM-DD for "today"
        // Only entries matching today's date land here. The form
        // disallows future dates and load_date defaults to today,
        // so this is effectively "all live entries" — but the date
        // filter is the explicit gate the spec asked for.
        const todays = readEntries().filter(e =>
            e && e.computed && typeof e.computed.date === 'string'
            && e.computed.date.slice(0, 10) === date);
        if (todays.length === 0) return;

        const typeLabel = (t) => t === 0 ? 'One-way'
                              : t === 1 ? 'Round-trip'
                              : t === 4 ? 'Trainer'
                              : String(t);
        const money = (v) => '$' + Number(v).toFixed(2);
        const pct   = (v) => (Number(v) * 100).toFixed(2) + '%';

        // Mirror of the PHP breakdown table in dashboard/index.php — same
        // conditional rows, same money/pct formatting. Kept in lockstep
        // with the saved-row markup so the two paths look identical.
        function renderBreakdownRows(bd) {
            const rows = [];
            const row  = (label, val) => `
                <tr>
                    <td style="padding:.2rem .8rem;color:#475569;">${label}</td>
                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">${money(val)}</td>
                </tr>`;
            if (Number(bd.base_pay)      || 0) rows.push(row(`${Number(bd.base_miles) || 0} Miles Base`, bd.base_pay));
            if (Number(bd.empty_pay)     || 0) {
                const em = Number(bd.empty_miles) || 0;
                const rate = Number(bd.empty_rate) || 0;
                const lbl = em > 0
                    ? `Empty Pay: ${em} Miles <span class="muted">@ $${rate.toFixed(4)}</span>`
                    : 'Empty Pay:';
                rows.push(row(lbl, bd.empty_pay));
            }
            if (Number(bd.shift_pay)     || 0) rows.push(row(`Shift Pay <span style="color:#ec4899;">(${pct(bd.shift_pct || 0)})</span>`, bd.shift_pay));
            if (Number(bd.seniority_pay) || 0) rows.push(row(`Seniority Pay <span style="color:#a855f7;">(${pct(bd.seniority_pct || 0)})</span>`, bd.seniority_pay));
            if (Number(bd.weekend_pay)   || 0) rows.push(row(`Weekend <span style="color:#f59e0b;">(${pct(bd.weekend_pct || 0)})</span>`, bd.weekend_pay));
            if (Number(bd.split_pay)     || 0) rows.push(row('Split Pay',  bd.split_pay));
            if (Number(bd.dem_pay)       || 0) rows.push(row('Demurrage',  bd.dem_pay));
            if (Number(bd.break_pay)     || 0) rows.push(row('Breakdown',  bd.break_pay));
            if (Number(bd.extra_pay)     || 0) rows.push(row('Extra Pay',  bd.extra_pay));
            rows.push(`
                <tr style="border-top:1px solid #cbd5e1;">
                    <td style="padding:.3rem .8rem;font-weight:700;">Total Load Pay</td>
                    <td style="padding:.3rem .8rem;text-align:right;font-weight:700;color:#f59e0b;">
                        ${money(bd.np || 0)}
                    </td>
                </tr>`);
            return rows.join('');
        }

        const tbody = document.querySelector('#dashboard-loads-table tbody');
        const table = document.getElementById('dashboard-loads-table');
        const noMsg = document.getElementById('dashboard-no-loads-msg');
        if (!tbody || !table) return;

        let injectedNp    = 0;
        let injectedMiles = 0;
        todays.forEach((entry) => {
            const c  = entry.computed;
            const id = entry.local_id;
            const bd = (c.pay_breakdown && typeof c.pay_breakdown === 'object') ? c.pay_breakdown : null;
            injectedNp    += Number(c.np)          || 0;
            injectedMiles += Number(c.empty_miles) || 0;

            // Main row — mirror of the saved-row structure (incl. the
            // decorative chevron in column 1 when a breakdown exists).
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #f0f2f6';
            tr.style.verticalAlign = 'top';
            tr.style.background = '#fffbeb'; // pale amber: scratchpad row
            tr.innerHTML = `
                <td style="padding:.25rem .5rem;text-align:center;" title="Unconfirmed load — lives in this browser only until you add a FRTL #">
                    ${bd ? '<span style="color:var(--accent);font-weight:600;">&#x25B8;</span>' : ''}
                </td>
                <td style="padding:.25rem .5rem;color:#92400e;" title="No FRTL # yet — edit to add one and save"><code>—</code></td>
                <td style="padding:.25rem .5rem;">${typeLabel(c.load_type)}</td>
                <td style="padding:.25rem .5rem;">
                    ${escapeHtml(c.pickup_city || '?')} &nbsp;&rarr;&nbsp; ${escapeHtml(c.delivery_city || '?')}
                    <br><small class="muted" style="color:#92400e;">unconfirmed &mdash; in this browser only</small>
                </td>
                <td style="padding:.25rem .5rem;text-align:right;"><code>${money(c.np)}</code></td>
                <td style="padding:.25rem .5rem;text-align:right;white-space:nowrap;">
                    <a href="${basePath}/loads/new?unsaved=${encodeURIComponent(id)}"
                       style="color:var(--accent);text-decoration:none;font-size:12px;margin-right:.3rem;"
                       title="Edit this unconfirmed load">Edit</a>
                    <button type="button" data-discard-local-id="${id}"
                            style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:12px;padding:0;font:inherit;text-decoration:underline;"
                            title="Discard this unconfirmed load">Discard</button>
                </td>`;
            tbody.appendChild(tr);

            // Breakdown row — collapsed by default. Includes notes (if
            // any) above the per-component pay table. Same markup
            // shape as the PHP-rendered breakdown for saved loads.
            if (bd) {
                const noteHtml = c.notes
                    ? `<div style="margin-top:.5rem;padding:.5rem .7rem;background:#fffbeb;border-left:3px solid #f59e0b;color:#475569;font-size:13px;white-space:pre-wrap;word-break:break-word;">
                           <strong style="color:#92400e;">Notes:</strong> ${escapeHtml(c.notes)}
                       </div>` : '';
                const tripLabel = (bd.trip_label  != null) ? String(bd.trip_label)  : '?';
                const band      = (bd.tenure_band != null) ? String(bd.tenure_band) : '?';
                const shift     = (bd.shift       != null) ? String(bd.shift)       : '?';
                const shiftCap  = shift.charAt(0).toUpperCase() + shift.slice(1);
                const tr2 = document.createElement('tr');
                tr2.style.background    = '#f8fafc';
                tr2.style.borderBottom  = '1px solid #f0f2f6';
                tr2.innerHTML = `
                    <td colspan="6" style="padding:.6rem 1.2rem;">
                        <details>
                            <summary style="cursor:pointer;color:var(--accent);font-weight:600;">
                                Pay breakdown &mdash; ${escapeHtml(tripLabel)}
                                (${escapeHtml(band)}&nbsp;M&nbsp;|&nbsp;${escapeHtml(shiftCap)})
                            </summary>
                            ${noteHtml}
                            <table style="border-collapse:collapse;font-size:13px;margin-top:.4rem;">
                                <tbody>${renderBreakdownRows(bd)}</tbody>
                            </table>
                        </details>
                    </td>`;
                tbody.appendChild(tr2);
            } else if (c.notes) {
                // No breakdown but still has notes — surface them in a
                // standalone row so they're not buried behind Edit.
                const tr2 = document.createElement('tr');
                tr2.style.background    = '#f8fafc';
                tr2.style.borderBottom  = '1px solid #f0f2f6';
                tr2.innerHTML = `
                    <td colspan="6" style="padding:.5rem 1.2rem;background:#fffbeb;border-left:3px solid #f59e0b;color:#475569;font-size:13px;white-space:pre-wrap;word-break:break-word;">
                        <strong style="color:#92400e;">Notes:</strong> ${escapeHtml(c.notes)}
                    </td>`;
                tbody.appendChild(tr2);
            }
        });
        table.style.display = '';
        if (noMsg) noMsg.hidden = true;

        // Wire Discard buttons.
        tbody.querySelectorAll('button[data-discard-local-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-discard-local-id');
                if (!confirm('Discard this unconfirmed load? It cannot be recovered.')) return;
                writeEntries(readEntries().filter(e => e.local_id !== id));
                window.location.reload();
            });
        });

        // --- Bump the Today + Week totals -----------------------------
        // Walk every .card whose first h2 starts with "Today" or
        // "This Week" and add the precomputed np / miles / count to
        // the displayed totals (which represent authoritative DB
        // figures). We never re-run pay math — only sum.
        document.querySelectorAll('.card').forEach(c => {
            const h2 = c.querySelector('h2');
            if (!h2) return;
            const txt = h2.textContent.trim();
            if (!txt.startsWith('Today') && !txt.startsWith('This Week')) return;
            c.querySelectorAll('tbody tr').forEach(tr => {
                const label = tr.querySelector('td strong');
                const cell  = tr.querySelectorAll('td code')[0];
                if (!label || !cell || cell.dataset.scratchpadBumped === '1') return;
                const t = label.textContent.trim();
                if (t === 'Loads') {
                    cell.textContent = String((parseInt(cell.textContent, 10) || 0) + todays.length);
                } else if (t === 'Net Pay') {
                    const cur = parseFloat(cell.textContent.replace(/[^0-9.\-]/g, '')) || 0;
                    cell.textContent = '$' + (cur + injectedNp).toFixed(2)
                        .replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    cell.style.background = '#fef3c7';
                    cell.style.color      = '#92400e';
                    cell.title            = 'Includes ' + todays.length + ' unconfirmed load(s)';
                } else if (t === 'Miles') {
                    const cur = parseInt(cell.textContent.replace(/[^0-9]/g, ''), 10) || 0;
                    cell.textContent = (cur + injectedMiles).toLocaleString('en-US');
                } else {
                    return;
                }
                cell.dataset.scratchpadBumped = '1';
            });
        });

        // Reveal the This Week disclaimer (in addition to the amber
        // Net Pay cell shading the bumpTotals loop applied).
        const weekNote = document.getElementById('week-unconfirmed-note');
        if (weekNote) {
            const span = weekNote.querySelector('[data-unconfirmed-count]');
            if (span) span.textContent = String(todays.length);
            weekNote.hidden = false;
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g,
                c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
    })();
</script>
<?php endif; ?>
