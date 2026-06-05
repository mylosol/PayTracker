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

/** Decode the pay_breakdown JSON column into an array (or null). */
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
    <div class="card" style="background:#dcfce7;color:#166534;word-break:break-word;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h1>Reconcile your pay</h1>
    <p class="muted">
        Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong>
        (driver id <?= (int) ($driver['id'] ?? 0) ?>).
        Walk through each load as your paystubs arrive and mark whether you got the
        expected amount. Click <strong>Paid</strong> for fully paid loads, or
        <strong>Dispute</strong> if there's a problem worth flagging &mdash;
        being short on any line item counts as a dispute.
    </p>
    <p>
        <a href="<?= e($base) ?>/dashboard">&larr; Dashboard</a>
        &nbsp;<a href="<?= e($base) ?>/profile">Profile</a>
    </p>
</div>

<div class="card" style="background:#fffbeb;border:1px solid #fde68a;">
    <h2 style="margin-top:0;">Pending payroll batch
        <span class="pill <?= $pendingCount > 0 ? 'warn' : '' ?>"><?= (int) $pendingCount ?></span>
    </h2>
    <?php if ($pendingCount === 0): ?>
        <p class="muted" style="margin:0;">
            No disputes are flagged for batch notification.
            Tick the <em>Include in next payroll batch</em> box when you dispute a
            load and it'll queue up here.
        </p>
    <?php else: ?>
        <p class="muted" style="margin:0 0 .8rem 0;">
            <strong><?= (int) $pendingCount ?></strong> disputed load(s) are waiting to be sent in a
            single email
            <?php if ($payrollEmail !== null): ?>
                to <code><?= e($payrollEmail) ?></code>.
            <?php else: ?>
                &mdash; but no payroll contact email is set yet.
            <?php endif; ?>
        </p>
        <?php if ($payrollEmail === null): ?>
            <p>
                <a href="<?= e($base) ?>/profile"
                   style="display:inline-block;background:var(--accent);color:#fff;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
                    Set a payroll contact email &rarr;
                </a>
            </p>
        <?php else: ?>
            <form id="send-batch-form" method="post" action="<?= e($base) ?>/reconcile/send-batch">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="cc_self" id="send-batch-cc-self" value="0">
                <label style="display:block;font-size:13px;margin-bottom:.5rem;">
                    <input type="checkbox" id="cc-self-batch" data-cc-self-toggle>
                    Send me a copy of this batch
                </label>
                <?php if (! $mailConfigured): ?>
                    <p class="muted" style="background:#fef3c7;color:#854d0e;border-radius:6px;padding:.5rem .7rem;font-size:13px;">
                        <strong>Heads up:</strong> email delivery is not configured on the
                        server yet (waiting on Resend / DNS verification). The batch is
                        queued and will go out on your next click once delivery is live.
                    </p>
                    <button type="submit" disabled
                            style="background:#e5e7eb;color:#6b7280;border:0;padding:.5rem 1.2rem;border-radius:6px;font:inherit;cursor:not-allowed;">
                        Send batch (delivery pending)
                    </button>
                <?php else: ?>
                    <button type="submit"
                            onclick="return confirm('Send <?= (int) $pendingCount ?> disputed load(s) to <?= e($payrollEmail) ?>?');"
                            style="background:#16a34a;color:#fff;border:0;padding:.5rem 1.2rem;border-radius:6px;font:inherit;cursor:pointer;">
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
    $rowStyle = match ($state) {
        'paid'     => 'background:#f0fdf4;',
        'short'    => 'background:#fffbeb;',
        'disputed' => 'background:#fef2f2;',
        default    => '',
    };
    $bd = $decodeBreakdown(isset($row['pay_breakdown']) ? (string) $row['pay_breakdown'] : null);
    ?>
    <tr style="border-bottom:1px solid #f0f2f6;vertical-align:top;<?= $rowStyle ?>">
        <td style="padding:.4rem .25rem;text-align:center;width:1.5rem;">
            <?php if ($bd !== null): ?>
                <span style="color:var(--accent);font-weight:600;" title="See pay breakdown below">&#x25B8;</span>
            <?php endif; ?>
        </td>
        <td style="padding:.4rem .5rem;"><code><?= $frtl ?></code></td>
        <td style="padding:.4rem .5rem;"><?= e(substr((string) ($row['date'] ?? ''), 0, 10)) ?></td>
        <td style="padding:.4rem .5rem;"><?= e($loadTypeLabel($row['load_type'] ?? null)) ?></td>
        <td style="padding:.4rem .5rem;">
            <?= e((string) ($row['pickup_city']   ?? '?')) ?>
            &nbsp;&rarr;&nbsp;
            <?= e((string) ($row['delivery_city'] ?? '?')) ?>
        </td>
        <td style="padding:.4rem .5rem;text-align:right;"><code><?= e($money($np)) ?></code></td>
        <?php
        // "Was this a resolved dispute?" — when state=paid AND any
        // dispute artefact is present (note, items, or other amount),
        // the row was originally disputed and has since been closed
        // out. Surface a sub-label so it reads differently from a
        // row paid in a single click.
        $resolvedFromDispute = ($state === 'paid')
            && ($note !== '' || $disputedItems !== [] || $disputedOther !== null);
        ?>
        <td style="padding:.4rem .5rem;">
            <?= $statePill($state) ?>
            <?php if ($resolvedFromDispute): ?>
                <br><small class="muted">resolved dispute</small>
            <?php elseif ($state === 'short' && $shortfall !== null): ?>
                <br><small class="muted">&minus;<?= e($money($shortfall)) ?></small>
            <?php elseif ($state === 'disputed' && $shortfall !== null): ?>
                <br><small class="muted">gap <?= e($money($shortfall)) ?></small>
            <?php endif; ?>
            <?php if ($state === 'disputed' && $notify): ?>
                <br><small class="muted">
                    <?php if ($emailedAt !== ''): ?>
                        <span class="pill ok" style="font-size:10px;">batched</span>
                    <?php else: ?>
                        <span class="pill warn" style="font-size:10px;">in next batch</span>
                    <?php endif; ?>
                </small>
            <?php endif; ?>
        </td>
        <td style="padding:.4rem .5rem;text-align:right;white-space:nowrap;">
            <?php if ($state === null): ?>
                <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/paid" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"
                            style="background:#16a34a;color:#fff;border:0;padding:.25rem .7rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                        Paid
                    </button>
                </form>
                <details style="display:inline-block;">
                    <summary class="dispute-summary"
                             style="display:inline-block;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:.25rem .7rem;border-radius:4px;cursor:pointer;font-size:12px;list-style:none;">Dispute&hellip;</summary>
                    <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/dispute" data-dispute-form
                          style="position:absolute;z-index:10;background:#fff;border:1px solid #cbd2da;border-radius:6px;padding:.7rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:22rem;max-width:26rem;text-align:left;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <p style="margin:0 0 .4rem 0;font-size:12px;color:#475569;">
                            Expected pay: <strong><?= e($money($np)) ?></strong>
                        </p>
                        <label style="display:block;font-size:12px;margin-bottom:.4rem;">
                            <strong>Actual paid ($)</strong> &mdash; required<br>
                            <input type="number" name="actual_np" step="0.01" min="0" required
                                   style="width:8rem;padding:.3rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;">
                        </label>
                        <?php if ($bd !== null): ?>
                            <fieldset style="border:1px solid #e4e8ee;border-radius:6px;padding:.4rem .6rem;margin:0 0 .5rem 0;">
                                <legend style="font-size:12px;color:#475569;padding:0 .3rem;">Which items are wrong?</legend>
                                <?php
                                $anyComp = false;
                                foreach ($componentLabels as $key => $label):
                                    $val = (float) ($bd[$key] ?? 0);
                                    if ($val === 0.0) continue;
                                    $anyComp = true;
                                    ?>
                                    <label style="display:block;font-size:12px;margin:.15rem 0;">
                                        <input type="checkbox" name="disputed_components[]" value="<?= e($key) ?>">
                                        <?= e($label) ?>
                                        <span class="muted">(<?= e($money($val)) ?> expected)</span>
                                    </label>
                                <?php endforeach; ?>
                                <?php if (! $anyComp): ?>
                                    <p class="muted" style="margin:.2rem 0;font-size:11px;">
                                        No itemised components for this load.
                                    </p>
                                <?php endif; ?>
                            </fieldset>
                        <?php endif; ?>
                        <label style="display:block;font-size:12px;margin-bottom:.4rem;">
                            Other shortfall ($) <span class="muted">(optional)</span><br>
                            <input type="number" name="disputed_other_amount" step="0.01" min="0"
                                   style="width:8rem;padding:.3rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;"
                                   placeholder="0.00">
                        </label>
                        <label style="display:block;font-size:12px;margin-bottom:.4rem;">
                            <strong>Note</strong> &mdash; required<br>
                            <textarea name="note" rows="3" maxlength="4000" required
                                      style="width:100%;padding:.3rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;"
                                      placeholder="What should payroll know?"></textarea>
                        </label>
                        <label style="display:block;font-size:12px;margin:.4rem 0;">
                            <input type="checkbox" name="notify_email" value="1" data-notify-toggle>
                            Include in next payroll batch email
                        </label>
                        <label style="display:block;font-size:12px;margin:.4rem 0;">
                            <input type="checkbox" data-cc-self-toggle>
                            Send me a copy when this batch goes out
                        </label>
                        <button type="submit"
                                style="background:#991b1b;color:#fff;border:0;padding:.35rem .9rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                            Flag dispute
                        </button>
                    </form>
                </details>
            <?php else: ?>
                <?php if ($state === 'disputed'): ?>
                    <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/resolve" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit"
                                onclick="return confirm('Mark load <?= $frtl ?> as resolved? This closes out the dispute and flips it to paid (the note + items stay as history).');"
                                title="Payroll paid the gap — close out this dispute"
                                style="background:#16a34a;color:#fff;border:0;padding:.25rem .7rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                            Resolved
                        </button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/undo" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"
                            onclick="return confirm('Reset load <?= $frtl ?> back to pending? This deletes the current reconcile record.');"
                            style="background:#fff;color:#101418;border:1px solid #cbd2da;padding:.25rem .7rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
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
        <tr style="background:#f8fafc;border-bottom:1px solid #f0f2f6;">
            <td colspan="8" style="padding:.6rem 1.4rem;">
                <details>
                    <summary style="cursor:pointer;color:var(--accent);font-weight:600;font-size:13px;">
                        Pay breakdown &mdash; <?= e((string) ($bd['trip_label']  ?? '?')) ?>
                        (<?= e((string) ($bd['tenure_band'] ?? '?')) ?>&nbsp;M&nbsp;|&nbsp;<?= e(ucfirst((string) ($bd['shift'] ?? '?'))) ?>)
                    </summary>
                    <?php if (! empty($row['notes'])): ?>
                        <div style="margin-top:.5rem;padding:.5rem .7rem;background:#fffbeb;border-left:3px solid #f59e0b;color:#475569;font-size:13px;white-space:pre-wrap;word-break:break-word;">
                            <strong style="color:#92400e;">Notes:</strong>
                            <?= e((string) $row['notes']) ?>
                        </div>
                    <?php endif; ?>
                    <table style="border-collapse:collapse;font-size:13px;margin-top:.4rem;">
                        <tbody>
                            <?php foreach ($componentLabels as $key => $label):
                                $val = (float) ($bd[$key] ?? 0);
                                if ($val === 0.0) continue;
                                $rate = null;
                                $countLabel = null;
                                if ($key === 'base_pay' && isset($bd['base_miles'])) {
                                    $countLabel = (int) $bd['base_miles'] . ' Miles Base';
                                } elseif ($key === 'empty_pay' && isset($bd['empty_miles']) && (int) $bd['empty_miles'] > 0) {
                                    $countLabel = sprintf('Empty Pay: %d Miles', (int) $bd['empty_miles']);
                                    $rate = isset($bd['empty_rate']) ? (float) $bd['empty_rate'] : null;
                                } elseif ($key === 'shift_pay'     && isset($bd['shift_pct'])) {
                                    $countLabel = 'Shift Pay <span style="color:#ec4899;">(' . $pct($bd['shift_pct']) . ')</span>';
                                } elseif ($key === 'seniority_pay' && isset($bd['seniority_pct'])) {
                                    $countLabel = 'Seniority Pay <span style="color:#a855f7;">(' . $pct($bd['seniority_pct']) . ')</span>';
                                } elseif ($key === 'weekend_pay'   && isset($bd['weekend_pct'])) {
                                    $countLabel = 'Weekend <span style="color:#f59e0b;">(' . $pct($bd['weekend_pct']) . ')</span>';
                                }
                                if ($countLabel === null) $countLabel = $label;
                                ?>
                                <tr>
                                    <td style="padding:.2rem .8rem;color:#475569;">
                                        <?= $countLabel ?>
                                        <?php if ($rate !== null): ?>
                                            <span class="muted">@ $<?= number_format($rate, 4) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:.2rem .8rem;text-align:right;color:#16a34a;font-weight:600;">
                                        <?= e($money($val)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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

    <?php
    // Acted-row detail strip: actual / shortfall / disputed items / note.
    // Shown whenever any historical artefact is present — disputed
    // rows AND paid rows that were originally disputed (resolved).
    // Direct-paid rows have no artefacts so the strip stays hidden
    // for them (note=null, items=[], other=null on a clean Paid click).
    $showStrip = $reconRow !== null
        && ($note !== '' || $disputedItems !== [] || $disputedOther !== null);
    if ($showStrip):
        ?>
        <tr style="<?= $rowStyle ?>border-bottom:1px solid #e4e8ee;">
            <td colspan="8" style="padding:.5rem 1.4rem;font-size:13px;color:#475569;">
                <?php if ($resolvedFromDispute): ?>
                    <em class="muted">Originally disputed, now resolved.</em><br>
                <?php endif; ?>
                <?php if ($actual !== null): ?>
                    <strong>Actual paid:</strong> <?= e($money($actual)) ?>
                <?php endif; ?>
                <?php if ($disputedItems !== []): ?>
                    &middot; <strong>Items:</strong> <?= e(implode(', ', $disputedItems)) ?>
                <?php endif; ?>
                <?php if ($disputedOther !== null && $disputedOther > 0): ?>
                    &middot; <strong>Other:</strong> <?= e($money($disputedOther)) ?>
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
<?php if ($i === 0): ?>
    <div class="card">
        <h2 style="margin-top:0;">
            <?= e($headerText) ?>
            <span class="muted" style="font-weight:400;font-size:14px;">(<?= e($week['label']) ?> &middot; <?= e($countLabel) ?>)</span>
        </h2>
<?php else: ?>
    <div class="card">
        <details<?= count($rows) > 0 ? '' : ' open' ?>>
            <summary style="cursor:pointer;font-weight:600;font-size:18px;">
                <?= e($headerText) ?>
                <span class="muted" style="font-weight:400;font-size:14px;">(<?= e($countLabel) ?>)</span>
            </summary>
<?php endif; ?>
        <?php if ($rows === []): ?>
            <p class="muted" style="margin:.6rem 0 0 0;">No loads for this week.</p>
        <?php else: ?>
            <table style="border-collapse:collapse;width:100%;font-size:14px;margin-top:.4rem;">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #e4e8ee;background:#f1f5f9;">
                        <th style="padding:.4rem .25rem;width:1.5rem;"></th>
                        <th style="padding:.4rem .5rem;">FRTL</th>
                        <th style="padding:.4rem .5rem;">Date</th>
                        <th style="padding:.4rem .5rem;">Type</th>
                        <th style="padding:.4rem .5rem;">Pickup &rarr; Delivery</th>
                        <th style="padding:.4rem .5rem;text-align:right;">Expected</th>
                        <th style="padding:.4rem .5rem;">State</th>
                        <th style="padding:.4rem .5rem;text-align:right;">Actions</th>
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
    //
    // Both use the same bind helper; mirroring keeps each toggle in
    // sync on the page without a reload.
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
