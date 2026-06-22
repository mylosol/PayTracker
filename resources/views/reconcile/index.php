<?php
/**
 * @var string                                                       $base
 * @var string                                                       $csrfToken
 * @var array<string,mixed>                                          $driver
 * @var string|null                                                  $flash
 * @var list<array{label:string, start:string, end:string, start_at:string, end_at:string, rows:list<array<string,mixed>>}> $weeks
 * @var array<int, array<string,mixed>>                              $reconByFrtl
 * @var list<array<string,mixed>>                                    $pendingBatch
 * @var int                                                          $pendingCount
 * @var string|null                                                  $payrollEmail
 * @var bool                                                         $mailConfigured
 */
layout('layouts/app');

$money = static fn ($v): string => '$' . number_format((float) $v, 2);
$pct   = static fn ($v): string => number_format(((float) $v) * 100, 2) . '%';

$loadTypeLabel = static function ($t): string {
    $t = $t === null ? null : (int) $t;
    if ($t === null) return '?';
    if ($t === 0)    return 'One-way';
    if ($t === 1)    return 'Round-trip';
    if ($t === 4)    return 'Trainer';
    return (string) $t;
};

$statePill = static function (?string $state): string {
    return match ($state) {
        'paid'     => '<span class="pill ok">paid</span>',
        'short'    => '<span class="pill warn">short</span>',
        'disputed' => '<span class="pill err">disputed</span>',
        default    => '<span class="pill">pending</span>',
    };
};

/** Decode pay_breakdown JSON column → array | null. */
$decodeBreakdown = static function (?string $json): ?array {
    if ($json === null || $json === '') return null;
    try {
        $arr = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    } catch (\Throwable) {
        return null;
    }
    return is_array($arr) ? $arr : null;
};

/**
 * Map of pay_breakdown keys → human labels. Rendered both in the
 * per-load breakdown sub-row AND as the checkbox list in the dispute
 * form (only the components the load actually has pay for are shown).
 */
$componentLabels = [
    'base_pay'      => 'Base pay',
    'empty_pay'     => 'Empty pay',
    'shift_pay'     => 'Shift pay',
    'seniority_pay' => 'Seniority pay',
    'weekend_pay'   => 'Weekend pay',
    'split_pay'     => 'Split pay',
    'dem_pay'       => 'Demurrage',
    'break_pay'     => 'Breakdown',
    'extra_pay'     => 'Extra pay',
];
?>
<?php if ($flash !== null): ?>
    <div class="flash-ok" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <h1 class="m-0">Reconcile your pay</h1>
    <p class="text-brand-muted mt-2">
        Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong>
        (driver id <?= (int) ($driver['id'] ?? 0) ?>).
        Walk through each load as your paystubs arrive and mark whether you got the
        expected amount. Click <strong>Paid</strong> for fully paid loads, or
        <strong>Dispute</strong> if there's a problem worth flagging &mdash;
        being short on any line item counts as a dispute.
    </p>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-5">
        <a href="<?= e($base) ?>/dashboard" class="admin-tile">
            <span>← Dashboard</span><span aria-hidden="true" class="admin-tile-arrow">→</span>
        </a>
        <a href="<?= e($base) ?>/loads/new" class="admin-tile">
            <span>Add load</span><span aria-hidden="true" class="admin-tile-arrow">→</span>
        </a>
        <a href="<?= e($base) ?>/profile" class="admin-tile">
            <span>Profile</span><span aria-hidden="true" class="admin-tile-arrow">→</span>
        </a>
    </div>
</div>

<div class="card bg-amber-50 border border-amber-200">
    <h2 class="m-0">Pending payroll batch
        <span class="pill <?= $pendingCount > 0 ? 'warn' : '' ?> align-middle ml-1 text-xs"><?= (int) $pendingCount ?></span>
    </h2>
    <?php if ($pendingCount === 0): ?>
        <p class="text-brand-muted mt-2 mb-0">
            No disputes are flagged for batch notification.
            Tick the <em>Include in next payroll batch</em> box when you dispute a
            load and it'll queue up here.
        </p>
    <?php else: ?>
        <p class="text-brand-muted mt-2 mb-3">
            <strong><?= (int) $pendingCount ?></strong> disputed load(s) are waiting to be sent in a
            single email
            <?php if ($payrollEmail !== null): ?>
                to <code><?= e($payrollEmail) ?></code>.
            <?php else: ?>
                — but no payroll contact email is set yet.
            <?php endif; ?>
        </p>
        <?php if ($payrollEmail === null): ?>
            <a href="<?= e($base) ?>/profile" class="btn-primary inline-block">
                Set a payroll contact email →
            </a>
        <?php else: ?>
            <form id="send-batch-form" method="post" action="<?= e($base) ?>/reconcile/send-batch" class="space-y-3">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="cc_self" id="send-batch-cc-self" value="0">
                <label class="inline-flex items-center gap-2 text-sm min-h-[44px]">
                    <input type="checkbox" id="cc-self-batch" data-cc-self-toggle class="field-checkbox">
                    Send me a copy of this batch
                </label>
                <?php if (! $mailConfigured): ?>
                    <div class="flash-info text-sm">
                        <strong>Heads up:</strong> email delivery is not configured on the
                        server yet (waiting on Resend / DNS verification). The batch is
                        queued and will go out on your next click once delivery is live.
                    </div>
                    <button type="submit" disabled class="btn-secondary opacity-60 cursor-not-allowed">
                        Send batch (delivery pending)
                    </button>
                <?php else: ?>
                    <button type="submit"
                            onclick="return confirm('Send <?= (int) $pendingCount ?> disputed load(s) to <?= e($payrollEmail) ?>?');"
                            class="btn-primary">
                        Send batch to <?= e($payrollEmail) ?>
                    </button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$renderRow = static function (array $row, ?array $reconRow) use (
    $base, $csrfToken, $money, $pct, $loadTypeLabel, $statePill,
    $decodeBreakdown, $componentLabels
) {
    $frtl = (int) ($row['frtl'] ?? 0);
    $np   = (float) ($row['np']  ?? 0);
    $state = $reconRow !== null ? (string) $reconRow['state'] : null;
    $actual    = $reconRow !== null && $reconRow['actual_np']  !== null ? (float) $reconRow['actual_np']  : null;
    $shortfall = $reconRow !== null && $reconRow['shortfall']  !== null ? (float) $reconRow['shortfall']  : null;
    $note      = $reconRow !== null ? (string) ($reconRow['note'] ?? '') : '';
    $notify    = $reconRow !== null && (int) ($reconRow['notify_email'] ?? 0) === 1;
    $emailedAt = $reconRow !== null && is_string($reconRow['emailed_at'] ?? null) ? (string) $reconRow['emailed_at'] : '';
    $disputedComponentsJson = $reconRow !== null && is_string($reconRow['disputed_components'] ?? null) ? (string) $reconRow['disputed_components'] : '';
    $disputedOther = $reconRow !== null && $reconRow['disputed_other_amount'] !== null ? (float) $reconRow['disputed_other_amount'] : null;
    $disputedItems = [];
    if ($disputedComponentsJson !== '') {
        $decoded = json_decode($disputedComponentsJson, true);
        if (is_array($decoded)) {
            foreach ($decoded as $k) {
                if (is_string($k) && isset($componentLabels[$k])) {
                    $disputedItems[] = $componentLabels[$k];
                }
            }
        }
    }
    $rowClass = match ($state) {
        'paid'     => 'bg-emerald-50/60',
        'short'    => 'bg-amber-50/60',
        'disputed' => 'bg-rose-50/60',
        default    => '',
    };
    $bd = $decodeBreakdown(isset($row['pay_breakdown']) ? (string) $row['pay_breakdown'] : null);
    ?>
    <tr class="align-top <?= $rowClass ?>">
        <td class="text-center w-6 hidden md:table-cell">
            <?php if ($bd !== null): ?>
                <span class="text-brand-primary font-semibold" title="See pay breakdown below">▸</span>
            <?php endif; ?>
        </td>
        <td data-label="FRTL"><code><?= $frtl ?></code></td>
        <td data-label="Date"><?= e(substr((string) ($row['date'] ?? ''), 0, 10)) ?></td>
        <td data-label="Type"><?= e($loadTypeLabel($row['load_type'] ?? null)) ?></td>
        <td data-label="Pickup → Delivery">
            <?= e((string) ($row['pickup_city']   ?? '?')) ?>
            &nbsp;→&nbsp;
            <?= e((string) ($row['delivery_city'] ?? '?')) ?>
        </td>
        <td data-label="Expected" class="text-right md:text-right"><code><?= e($money($np)) ?></code></td>
        <?php
        // "Was this a resolved dispute?" — paid state + any dispute
        // artefact (note, items, or other amount) → row was originally
        // disputed and has since been closed out. Surface a sub-label
        // so it reads differently from a row paid in a single click.
        $resolvedFromDispute = ($state === 'paid')
            && ($note !== '' || $disputedItems !== [] || $disputedOther !== null);
        ?>
        <td data-label="State">
            <?= $statePill($state) ?>
            <?php if ($resolvedFromDispute): ?>
                <br><small class="text-brand-muted">resolved dispute</small>
            <?php elseif ($state === 'short' && $shortfall !== null): ?>
                <br><small class="text-brand-muted">−<?= e($money($shortfall)) ?></small>
            <?php elseif ($state === 'disputed' && $shortfall !== null): ?>
                <br><small class="text-brand-muted">gap <?= e($money($shortfall)) ?></small>
            <?php endif; ?>
            <?php if ($state === 'disputed' && $notify): ?>
                <br><small class="text-brand-muted">
                    <?php if ($emailedAt !== ''): ?>
                        <span class="pill ok text-[10px]">batched</span>
                    <?php else: ?>
                        <span class="pill warn text-[10px]">in next batch</span>
                    <?php endif; ?>
                </small>
            <?php endif; ?>
        </td>
        <td data-label="Actions" class="text-right md:whitespace-nowrap">
            <?php if ($state === null): ?>
                <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/paid" class="inline-block m-0">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn-primary btn-sm">Paid</button>
                </form>
                <details class="inline-block relative">
                    <summary class="dispute-summary btn-danger btn-sm inline-block cursor-pointer list-none">Dispute…</summary>
                    <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/dispute" data-dispute-form
                          class="absolute z-10 bg-white border border-brand-line rounded-lg p-3 mt-1 shadow-lg min-w-[22rem] max-w-md text-left right-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <p class="m-0 mb-2 text-xs text-brand-muted">
                            Expected pay: <strong><?= e($money($np)) ?></strong>
                        </p>
                        <label class="block text-xs mb-2">
                            <strong>Actual paid ($)</strong> — required<br>
                            <input type="number" name="actual_np" step="0.01" min="0" required class="field w-32">
                        </label>
                        <?php if ($bd !== null): ?>
                            <fieldset class="border border-brand-line rounded-md p-2 mb-2">
                                <legend class="text-xs text-brand-muted px-1">Which items are wrong?</legend>
                                <?php
                                $anyComp = false;
                                foreach ($componentLabels as $key => $label):
                                    $val = (float) ($bd[$key] ?? 0);
                                    if ($val === 0.0) continue;
                                    $anyComp = true;
                                    ?>
                                    <label class="block text-xs my-0.5">
                                        <input type="checkbox" name="disputed_components[]" value="<?= e($key) ?>" class="field-checkbox">
                                        <?= e($label) ?>
                                        <span class="text-brand-muted">(<?= e($money($val)) ?> expected)</span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if (! $anyComp): ?>
                                    <p class="text-brand-muted text-[11px] m-0">
                                        No itemised components for this load.
                                    </p>
                                <?php endif; ?>
                            </fieldset>
                        <?php endif; ?>
                        <label class="block text-xs mb-2">
                            Other shortfall ($) <span class="text-brand-muted">(optional)</span><br>
                            <input type="number" name="disputed_other_amount" step="0.01" min="0" placeholder="0.00" class="field w-32">
                        </label>
                        <label class="block text-xs mb-2">
                            <strong>Note</strong> — required<br>
                            <textarea name="note" rows="3" maxlength="4000" required class="field w-full"
                                      placeholder="What should payroll know?"></textarea>
                        </label>
                        <label class="block text-xs my-2">
                            <input type="checkbox" name="notify_email" value="1" data-notify-toggle class="field-checkbox">
                            Include in next payroll batch email
                        </label>
                        <label class="block text-xs my-2">
                            <input type="checkbox" data-cc-self-toggle class="field-checkbox">
                            Send me a copy when this batch goes out
                        </label>
                        <button type="submit" class="btn-danger btn-sm">Flag dispute</button>
                    </form>
                </details>
            <?php else: ?>
                <?php if ($state === 'disputed'): ?>
                    <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/resolve" class="inline-block m-0">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit"
                                onclick="return confirm('Mark load <?= $frtl ?> as resolved? This closes out the dispute and flips it to paid (the note + items stay as history).');"
                                title="Payroll paid the gap — close out this dispute"
                                class="btn-primary btn-sm">
                            Resolved
                        </button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/undo" class="inline-block m-0">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"
                            onclick="return confirm('Reset load <?= $frtl ?> back to pending? This deletes the current reconcile record.');"
                            class="btn-secondary btn-sm">
                        Undo
                    </button>
                </form>
            <?php endif; ?>
        </td>
    </tr>

    <?php
    // Per-load breakdown sub-row (always rendered if a breakdown exists,
    // collapsed inside a <details> — driver clicks the chevron in the
    // main row's first cell, or directly the "Pay breakdown" summary).
    if ($bd !== null):
        ?>
        <tr class="bg-slate-50">
            <td colspan="8" class="px-5 py-2">
                <details>
                    <summary class="cursor-pointer text-brand-primary font-semibold text-[13px]">
                        Pay breakdown — <?= e((string) ($bd['trip_label']  ?? '?')) ?>
                        (<?= e((string) ($bd['tenure_band'] ?? '?')) ?>&nbsp;M&nbsp;|&nbsp;<?= e(ucfirst((string) ($bd['shift'] ?? '?'))) ?>)
                    </summary>
                    <?php if (! empty($row['notes'])): ?>
                        <div class="mt-2 p-2 bg-amber-50 border-l-4 border-amber-500 text-brand-muted text-[13px] whitespace-pre-wrap break-words">
                            <strong class="text-amber-900">Notes:</strong>
                            <?= e((string) $row['notes']) ?>
                        </div>
                    <?php endif; ?>
                    <table class="text-[13px] mt-2 border-collapse">
                        <tbody>
                            <?php
                            // For one-way / round-trip loads we always render
                            // the Loaded Pay row, even at $0 — a same-city
                            // load with 0 loaded miles is real, and hiding
                            // the row reads as "loaded miles missing."
                            $tripLabel = (string) ($bd['trip_label'] ?? '');
                            $forceShowLoaded = $tripLabel === 'One-way' || $tripLabel === 'Round-trip';
                            ?>
                            <?php foreach ($componentLabels as $key => $label):
                                $val = (float) ($bd[$key] ?? 0);
                                if ($val === 0.0 && ! ($key === 'base_pay' && $forceShowLoaded)) continue;
                                $rate = null;
                                $countLabel = null;
                                if ($key === 'base_pay' && isset($bd['base_miles'])) {
                                    $bm = (int) $bd['base_miles'];
                                    $countLabel = 'Loaded Pay: ' . $bm . ' Miles';
                                    if ($bm > 0 && isset($bd['base_rate'])) {
                                        $rate = (float) $bd['base_rate'];
                                    }
                                } elseif ($key === 'empty_pay' && isset($bd['empty_miles']) && (int) $bd['empty_miles'] > 0) {
                                    $countLabel = sprintf('Empty Pay: %d Miles', (int) $bd['empty_miles']);
                                    $rate = isset($bd['empty_rate']) ? (float) $bd['empty_rate'] : null;
                                } elseif ($key === 'shift_pay'     && isset($bd['shift_pct'])) {
                                    $countLabel = 'Shift Pay <span class="text-pink-600">(' . $pct($bd['shift_pct']) . ')</span>';
                                } elseif ($key === 'seniority_pay' && isset($bd['seniority_pct'])) {
                                    $countLabel = 'Seniority Pay <span class="text-purple-600">(' . $pct($bd['seniority_pct']) . ')</span>';
                                } elseif ($key === 'weekend_pay'   && isset($bd['weekend_pct'])) {
                                    $countLabel = 'Weekend <span class="text-amber-600">(' . $pct($bd['weekend_pct']) . ')</span>';
                                }
                                if ($countLabel === null) $countLabel = $label;
                                ?>
                                <tr>
                                    <td class="px-3 py-0.5 text-brand-muted">
                                        <?= $countLabel ?>
                                        <?php if ($rate !== null): ?>
                                            <span class="text-brand-muted">@ $<?= number_format($rate, 4) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-0.5 text-right text-emerald-700 font-semibold">
                                        <?= e($money($val)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="border-t border-slate-300">
                                <td class="px-3 py-1 font-bold">Total Load Pay</td>
                                <td class="px-3 py-1 text-right font-bold text-brand-primary">
                                    <?= e($money((float) ($bd['np'] ?? 0))) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </details>
            </td>
        </tr>
    <?php endif; ?>

    <?php
    // Acted-row detail strip: actual / shortfall / disputed items / note.
    // Shown whenever any historical artefact is present — disputed
    // rows AND paid rows that were originally disputed (resolved).
    $showStrip = $reconRow !== null
        && ($note !== '' || $disputedItems !== [] || $disputedOther !== null);
    if ($showStrip):
        ?>
        <tr class="<?= $rowClass ?>">
            <td colspan="8" class="px-5 py-2 text-[13px] text-brand-muted">
                <?php if ($resolvedFromDispute): ?>
                    <em class="text-brand-muted">Originally disputed, now resolved.</em><br>
                <?php endif; ?>
                <?php if ($actual !== null): ?>
                    <strong>Actual paid:</strong> <?= e($money($actual)) ?>
                <?php endif; ?>
                <?php if ($disputedItems !== []): ?>
                    · <strong>Items:</strong> <?= e(implode(', ', $disputedItems)) ?>
                <?php endif; ?>
                <?php if ($disputedOther !== null && $disputedOther > 0): ?>
                    · <strong>Other:</strong> <?= e($money($disputedOther)) ?>
                <?php endif; ?>
                <?php if ($note !== ''): ?>
                    <br><strong>Note:</strong> <?= e($note) ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    endif;
};
?>

<?php foreach ($weeks as $i => $week):
    $rows = $week['rows'];
    $headerText = $i === 0 ? 'This week' : 'Week of ' . $week['label'];
    $countLabel = count($rows) === 1 ? '1 load' : count($rows) . ' loads';
?>
<div class="card">
<?php if ($i === 0): ?>
    <h2 class="m-0">
        <?= e($headerText) ?>
        <span class="text-brand-muted font-normal text-sm">(<?= e($week['label']) ?> · <?= e($countLabel) ?>)</span>
    </h2>
<?php else: ?>
    <details<?= count($rows) > 0 ? '' : ' open' ?>>
        <summary class="cursor-pointer font-semibold text-lg">
            <?= e($headerText) ?>
            <span class="text-brand-muted font-normal text-sm">(<?= e($countLabel) ?>)</span>
        </summary>
<?php endif; ?>
        <?php if ($rows === []): ?>
            <p class="text-brand-muted mt-3 mb-0">No loads for this week.</p>
        <?php else: ?>
            <div class="table-wrap mt-3">
                <table class="data-table stack-on-mobile text-[14px]">
                    <thead>
                        <tr>
                            <th class="w-6"></th>
                            <th>FRTL</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Pickup → Delivery</th>
                            <th class="text-right">Expected</th>
                            <th>State</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $frtl     = (int) ($row['frtl'] ?? 0);
                            $reconRow = $reconByFrtl[$frtl] ?? null;
                            $renderRow($row, $reconRow);
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
<?php if ($i !== 0): ?>
    </details>
<?php endif; ?>
</div>
<?php endforeach; ?>

<script>
    // -------------------------------------------------------------------
    // Persistent toggle preferences.
    //
    // Two driver preferences are remembered across sessions via
    // localStorage and mirrored across every matching checkbox on
    // the page (so toggling on one form updates them all):
    //
    //   [data-cc-self-toggle]  → paytracker.disputeCcSelf
    //     "Send me a copy when the batch goes out". Appears on every
    //     dispute form AND on the Send Batch card. Also drives the
    //     hidden #send-batch-cc-self field the batch form POSTs.
    //
    //   [data-notify-toggle]   → paytracker.disputeNotifyEmail
    //     "Include in next payroll batch email". Appears on every
    //     dispute form. Persisted so a driver who batches every
    //     dispute doesn't have to re-tick the box for each load.
    // -------------------------------------------------------------------
    (function () {
        const bindPersistentToggles = (selector, storageKey, onChange) => {
            const read = () => {
                try { return localStorage.getItem(storageKey) === '1'; }
                catch (e) { return false; }
            };
            const write = (on) => {
                try { localStorage.setItem(storageKey, on ? '1' : '0'); }
                catch (e) {}
            };
            const initial = read();
            const toggles = document.querySelectorAll(selector);
            toggles.forEach(box => {
                box.checked = initial;
                box.addEventListener('change', () => {
                    write(box.checked);
                    toggles.forEach(b => { if (b !== box) b.checked = box.checked; });
                    if (onChange) onChange(box.checked);
                });
            });
            if (onChange) onChange(initial);
        };

        bindPersistentToggles(
            '[data-cc-self-toggle]',
            'paytracker.disputeCcSelf',
            (on) => {
                const hidden = document.getElementById('send-batch-cc-self');
                if (hidden) hidden.value = on ? '1' : '0';
            }
        );
        bindPersistentToggles(
            '[data-notify-toggle]',
            'paytracker.disputeNotifyEmail',
            null
        );
    })();
</script>
