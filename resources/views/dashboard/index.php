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
    <div class="flash-ok" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="m-0">My pay — <?= e($date) ?>
                <?= $isToday ? '<span class="pill-ok align-middle ml-1 text-xs">today</span>' : '' ?>
            </h1>
            <p class="text-brand-muted mt-2">
                Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong>
                (driver id <?= (int) ($driver['id'] ?? 0) ?>).
                Pay totals reflect the most recent
                <a href="<?= e($base) ?>/pay-admin">pay-admin recompute</a>.
            </p>
        </div>
        <span class="inline-flex items-center gap-2 bg-brand-surface text-white text-xs font-semibold uppercase tracking-wide px-3 py-2 rounded-md">
            <?= e($effective['band']) ?>&nbsp;M&nbsp;|&nbsp;<?= e(ucfirst($effective['shift'])) ?> Shift
        </span>
    </div>

    <div class="flex flex-wrap items-center gap-2 mt-4">
        <a href="<?= e($base) ?>/dashboard?date=<?= e($prevDate) ?>" class="btn-secondary btn-sm">← <?= e($prevDate) ?></a>
        <?php if (! $isToday): ?>
            <a href="<?= e($base) ?>/dashboard" class="btn-primary btn-sm">Today</a>
        <?php endif; ?>
        <a href="<?= e($base) ?>/dashboard?date=<?= e($nextDate) ?>" class="btn-secondary btn-sm"><?= e($nextDate) ?> →</a>
        <span class="hidden sm:inline-block w-px h-6 bg-brand-line mx-1"></span>
        <a href="<?= e($base) ?>/loads/new" class="btn-primary btn-sm">+ Add load</a>
        <a href="<?= e($base) ?>/reconcile" class="btn-secondary btn-sm">Reconcile</a>
        <a href="<?= e($base) ?>/profile" class="btn-secondary btn-sm">Profile</a>
    </div>
</div>

<div class="card bg-gradient-to-b from-emerald-50 to-white border-emerald-200">
    <h2 class="m-0">This Week
        <span class="text-brand-muted text-sm font-normal">(<?= e($weekRangeLabel) ?>)</span>
    </h2>
    <table class="mt-3">
        <tbody>
            <tr>
                <td class="py-1.5 pr-6"><strong>Loads</strong></td>
                <td class="py-1.5"><code><?= (int) $weekTotals['count'] ?></code></td>
            </tr>
            <tr>
                <td class="py-1.5 pr-6"><strong>Net Pay</strong></td>
                <td class="py-1.5"><code class="bg-emerald-100 text-emerald-800 font-semibold">$<?= e($weekNpTotalF) ?></code></td>
            </tr>
            <tr>
                <td class="py-1.5 pr-6"><strong>Miles</strong></td>
                <td class="py-1.5"><code><?= e($weekMilesF) ?></code></td>
            </tr>
        </tbody>
    </table>
    <p id="week-unconfirmed-note" hidden
       class="mt-3 rounded-md bg-amber-50 text-amber-900 border-l-4 border-amber-400 px-3 py-2 text-sm">
        <strong>Heads up:</strong> these weekly totals include
        <span data-unconfirmed-count>0</span> unconfirmed load(s) from
        today's browser scratchpad. They'll settle to the official
        figures once you edit each one and add the FRTL #.
    </p>
    <p class="text-xs text-brand-muted mt-3 mb-0">
        Pay week is configured in your <a href="<?= e($base) ?>/profile">profile</a>.
        The weekly total stays anchored to the week containing the
        viewed date — use the day-jump nav above to walk through it.
    </p>
</div>

<div class="card">
    <h2 class="m-0">Today <span class="text-brand-muted text-sm font-normal">(<?= e($date) ?>)</span></h2>
    <table class="mt-3">
        <tbody>
            <tr>
                <td class="py-1.5 pr-6"><strong>Loads</strong></td>
                <td class="py-1.5"><code><?= (int) $totals['count'] ?></code></td>
            </tr>
            <tr>
                <td class="py-1.5 pr-6"><strong>Net Pay</strong></td>
                <td class="py-1.5"><code>$<?= e($npTotalF) ?></code></td>
            </tr>
            <tr>
                <td class="py-1.5 pr-6"><strong>Miles</strong></td>
                <td class="py-1.5"><code><?= e($milesTotalF) ?></code></td>
            </tr>
        </tbody>
    </table>

    <?php if ($totals['count'] > 0 && (float) $totals['np_total'] === 0.0): ?>
        <div class="mt-4 rounded-md bg-amber-50 text-amber-900 border-l-4 border-amber-400 px-3 py-2">
            <strong>Heads up:</strong> there are <?= (int) $totals['count'] ?> load(s) on
            this date but the net pay total is $0.00 — the stored pay
            may not have been computed yet. Click <strong>Refresh my pay</strong>
            below to recompute.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/dashboard/recompute" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="date"  value="<?= e($date) ?>">
        <button type="submit"
                class="inline-flex items-center justify-center min-h-[44px] px-5 py-2.5 rounded-lg font-semibold text-base bg-emerald-600 text-white hover:bg-emerald-700 transition-colors cursor-pointer">
            Refresh my pay
        </button>
        <span class="text-sm text-brand-muted">
            Recomputes pay for your loads since <?= e(date('Y-m-d', strtotime($date . ' -30 days'))) ?>.
            Safe to click repeatedly — the math is deterministic.
        </span>
    </form>
</div>

<div class="card" data-loads-card data-is-today="<?= $isToday ? '1' : '0' ?>" data-date="<?= e($date) ?>">
    <h2 class="m-0">Loads</h2>
    <p class="text-brand-muted mt-2" id="dashboard-no-loads-msg"<?= $rows !== [] ? ' hidden' : '' ?>>
        No loads on <?= e($date) ?>.
    </p>
    <?php if ($rows !== [] || $isToday): ?>
        <div class="table-wrap mt-4">
            <table id="dashboard-loads-table" class="data-table text-[13px]"<?= $rows === [] ? ' style="display:none;"' : '' ?>>
                <thead>
                    <tr>
                        <th class="w-6"></th>
                        <th>FRTL</th>
                        <th>Type</th>
                        <th>Pickup → Delivery</th>
                        <th class="text-right">Load Pay</th>
                        <th class="text-right">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row):
                        $bd = $decodeBreakdown($row['pay_breakdown'] ?? null);
                    ?>
                        <tr class="align-top">
                            <td class="text-center">
                                <?php if ($bd !== null): ?>
                                    <details><summary
                                        class="list-none cursor-pointer text-brand-primary font-semibold inline-block"
                                        style="list-style:none;"
                                        title="Show pay breakdown">▸</summary></details>
                                <?php endif; ?>
                            </td>
                            <td><code><?= (int) $row['frtl'] ?></code></td>
                            <td><?= e($loadTypeLabel($row['load_type'])) ?></td>
                            <td>
                                <?= e((string) ($row['pickup_city'] ?? '?')) ?>
                                &nbsp;→&nbsp;
                                <?= e((string) ($row['delivery_city'] ?? '?')) ?>
                            </td>
                            <td class="text-right"><code>$<?= number_format((float) $row['np'], 2) ?></code></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="<?= e($base) ?>/loads/<?= (int) $row['frtl'] ?>/edit"
                                   class="text-brand-primary text-xs mr-2 hover:underline"
                                   title="Edit this load">Edit</a>
                                <form method="post" action="<?= e($base) ?>/loads/<?= (int) $row['frtl'] ?>/delete"
                                      class="inline m-0"
                                      onsubmit="return confirm('Delete load #<?= (int) $row['frtl'] ?>? This cannot be undone.');">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button type="submit"
                                            class="bg-transparent border-0 text-rose-600 cursor-pointer text-xs p-0 underline"
                                            title="Delete this load">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php if ($bd !== null): ?>
                            <tr class="bg-slate-50">
                                <td colspan="6" class="px-5 py-3">
                                    <details>
                                        <summary class="cursor-pointer text-brand-primary font-semibold">
                                            Pay breakdown — <?= e((string) ($bd['trip_label'] ?? '?')) ?>
                                            (<?= e((string) ($bd['tenure_band'] ?? '?')) ?>&nbsp;M&nbsp;|&nbsp;<?= e(ucfirst((string) ($bd['shift'] ?? '?'))) ?>)
                                        </summary>
                                        <?php if (!empty($row['notes'])): ?>
                                            <div class="mt-2 px-3 py-2 bg-amber-50 border-l-4 border-amber-400 text-slate-700 text-[13px] whitespace-pre-wrap break-words">
                                                <strong class="text-amber-900">Notes:</strong>
                                                <?= e((string) $row['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <table class="mt-2 text-[13px]">
                                            <tbody>
                                                <?php if ((float) ($bd['base_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">
                                                            <?= (int) ($bd['base_miles'] ?? 0) ?> Miles Base
                                                        </td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['base_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ((float) ($bd['empty_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">
                                                            Empty Pay:
                                                            <?php if ((int) ($bd['empty_miles'] ?? 0) > 0): ?>
                                                                <?= (int) $bd['empty_miles'] ?> Miles
                                                                <span class="text-brand-muted">@ $<?= number_format((float) ($bd['empty_rate'] ?? 0), 4) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['empty_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ((float) ($bd['shift_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">
                                                            Shift Pay <span class="text-pink-500">(<?= e($pct((float) $bd['shift_pct'])) ?>)</span>
                                                        </td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['shift_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ((float) ($bd['seniority_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">
                                                            Seniority Pay <span class="text-purple-500">(<?= e($pct((float) $bd['seniority_pct'])) ?>)</span>
                                                        </td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['seniority_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ((float) ($bd['weekend_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">
                                                            Weekend <span class="text-amber-500">(<?= e($pct((float) $bd['weekend_pct'])) ?>)</span>
                                                        </td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['weekend_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ((float) ($bd['split_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">Split Pay</td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['split_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ((float) ($bd['dem_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">Demurrage</td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['dem_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ((float) ($bd['break_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">Breakdown</td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['break_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if ((float) ($bd['extra_pay'] ?? 0) !== 0.0): ?>
                                                    <tr>
                                                        <td class="py-1 px-3 text-slate-600">Extra Pay</td>
                                                        <td class="py-1 px-3 text-right text-emerald-600 font-semibold">
                                                            <?= e($money((float) $bd['extra_pay'])) ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <tr class="border-t border-slate-300">
                                                    <td class="py-1.5 px-3 font-bold">Total Load Pay</td>
                                                    <td class="py-1.5 px-3 text-right font-bold text-brand-primary">
                                                        <?= e($money((float) ($bd['np'] ?? 0))) ?>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </details>
                                </td>
                            </tr>
                        <?php elseif (!empty($row['notes'])): ?>
                            <tr class="bg-amber-50">
                                <td colspan="6" class="px-5 py-2 border-l-4 border-amber-400 text-slate-700 text-[13px] whitespace-pre-wrap break-words">
                                    <strong class="text-amber-900">Notes:</strong>
                                    <?= e((string) $row['notes']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="text-xs text-brand-muted mt-4 mb-0">
        Showing typed-column rows from <code>driver_loads</code>. For the
        backfill diagnostic surface (legacy blob strings, all drivers),
        see <a href="<?= e($base) ?>/loads">/loads</a>. Rows without a
        breakdown were created before <code>pay_breakdown</code> was tracked —
        click <strong>Refresh my pay</strong> to backfill them.
    </p>
</div>

<?php if ($isToday): ?>
<script>
    // Scratchpad hydration — preserved from previous deploy. Reads
    // localStorage entries (rolling 24h), injects rows into today's
    // Loads table, and bumps the Today + Week totals using ONLY the
    // precomputed np figures from each entry. Past/future dates do not
    // run this script.
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

        try {
            const pendingId = sessionStorage.getItem('paytracker.consumeLocalId');
            if (pendingId) {
                writeEntries(readEntries().filter(e => e.local_id !== pendingId));
                sessionStorage.removeItem('paytracker.consumeLocalId');
            }
        } catch (e) { /* fail silent */ }

        const card = document.querySelector('[data-loads-card]');
        if (!card) return;
        const date = card.dataset.date;
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

        function renderBreakdownRows(bd) {
            const rows = [];
            const row  = (label, val) => `
                <tr>
                    <td class="py-1 px-3 text-slate-600">${label}</td>
                    <td class="py-1 px-3 text-right text-emerald-600 font-semibold">${money(val)}</td>
                </tr>`;
            if (Number(bd.base_pay)      || 0) rows.push(row(`${Number(bd.base_miles) || 0} Miles Base`, bd.base_pay));
            if (Number(bd.empty_pay)     || 0) {
                const em = Number(bd.empty_miles) || 0;
                const rate = Number(bd.empty_rate) || 0;
                const lbl = em > 0
                    ? `Empty Pay: ${em} Miles <span class="text-brand-muted">@ $${rate.toFixed(4)}</span>`
                    : 'Empty Pay:';
                rows.push(row(lbl, bd.empty_pay));
            }
            if (Number(bd.shift_pay)     || 0) rows.push(row(`Shift Pay <span class="text-pink-500">(${pct(bd.shift_pct || 0)})</span>`, bd.shift_pay));
            if (Number(bd.seniority_pay) || 0) rows.push(row(`Seniority Pay <span class="text-purple-500">(${pct(bd.seniority_pct || 0)})</span>`, bd.seniority_pay));
            if (Number(bd.weekend_pay)   || 0) rows.push(row(`Weekend <span class="text-amber-500">(${pct(bd.weekend_pct || 0)})</span>`, bd.weekend_pay));
            if (Number(bd.split_pay)     || 0) rows.push(row('Split Pay',  bd.split_pay));
            if (Number(bd.dem_pay)       || 0) rows.push(row('Demurrage',  bd.dem_pay));
            if (Number(bd.break_pay)     || 0) rows.push(row('Breakdown',  bd.break_pay));
            if (Number(bd.extra_pay)     || 0) rows.push(row('Extra Pay',  bd.extra_pay));
            rows.push(`
                <tr class="border-t border-slate-300">
                    <td class="py-1.5 px-3 font-bold">Total Load Pay</td>
                    <td class="py-1.5 px-3 text-right font-bold text-brand-primary">
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

            const tr = document.createElement('tr');
            tr.className = 'align-top bg-amber-50';
            tr.innerHTML = `
                <td class="text-center" title="Unconfirmed load — lives in this browser only until you add a FRTL #">
                    ${bd ? '<span class="text-brand-primary font-semibold">▸</span>' : ''}
                </td>
                <td class="text-amber-900" title="No FRTL # yet — edit to add one and save"><code>—</code></td>
                <td>${typeLabel(c.load_type)}</td>
                <td>
                    ${escapeHtml(c.pickup_city || '?')} &nbsp;→&nbsp; ${escapeHtml(c.delivery_city || '?')}
                    <br><small class="text-amber-900">unconfirmed — in this browser only</small>
                </td>
                <td class="text-right"><code>${money(c.np)}</code></td>
                <td class="text-right whitespace-nowrap">
                    <a href="${basePath}/loads/new?unsaved=${encodeURIComponent(id)}"
                       class="text-brand-primary text-xs mr-2 hover:underline"
                       title="Edit this unconfirmed load">Edit</a>
                    <button type="button" data-discard-local-id="${id}"
                            class="bg-transparent border-0 text-rose-600 cursor-pointer text-xs p-0 underline"
                            title="Discard this unconfirmed load">Discard</button>
                </td>`;
            tbody.appendChild(tr);

            if (bd) {
                const noteHtml = c.notes
                    ? `<div class="mt-2 px-3 py-2 bg-amber-50 border-l-4 border-amber-400 text-slate-700 text-[13px] whitespace-pre-wrap break-words">
                           <strong class="text-amber-900">Notes:</strong> ${escapeHtml(c.notes)}
                       </div>` : '';
                const tripLabel = (bd.trip_label  != null) ? String(bd.trip_label)  : '?';
                const band      = (bd.tenure_band != null) ? String(bd.tenure_band) : '?';
                const shift     = (bd.shift       != null) ? String(bd.shift)       : '?';
                const shiftCap  = shift.charAt(0).toUpperCase() + shift.slice(1);
                const tr2 = document.createElement('tr');
                tr2.className = 'bg-slate-50';
                tr2.innerHTML = `
                    <td colspan="6" class="px-5 py-3">
                        <details>
                            <summary class="cursor-pointer text-brand-primary font-semibold">
                                Pay breakdown — ${escapeHtml(tripLabel)}
                                (${escapeHtml(band)}&nbsp;M&nbsp;|&nbsp;${escapeHtml(shiftCap)})
                            </summary>
                            ${noteHtml}
                            <table class="mt-2 text-[13px]">
                                <tbody>${renderBreakdownRows(bd)}</tbody>
                            </table>
                        </details>
                    </td>`;
                tbody.appendChild(tr2);
            } else if (c.notes) {
                const tr2 = document.createElement('tr');
                tr2.className = 'bg-amber-50';
                tr2.innerHTML = `
                    <td colspan="6" class="px-5 py-2 border-l-4 border-amber-400 text-slate-700 text-[13px] whitespace-pre-wrap break-words">
                        <strong class="text-amber-900">Notes:</strong> ${escapeHtml(c.notes)}
                    </td>`;
                tbody.appendChild(tr2);
            }
        });
        table.style.display = '';
        if (noMsg) noMsg.hidden = true;

        tbody.querySelectorAll('button[data-discard-local-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-discard-local-id');
                if (!confirm('Discard this unconfirmed load? It cannot be recovered.')) return;
                writeEntries(readEntries().filter(e => e.local_id !== id));
                window.location.reload();
            });
        });

        // Bump the Today + Week totals — walks .card whose h2 starts
        // with "Today" or "This Week" and adds to displayed totals.
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
