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

$loadTypeLabel = static function ($t): string {
    $t = $t === null ? null : (int) $t;
    if ($t === null) return '?';
    if ($t === 0)    return 'One-way';
    if ($t === 1)    return 'Round-trip';
    if ($t === 4)    return 'Trainer';
    return (string) $t;
};

/**
 * Coloured pill for a reconcile state.
 *
 * @param string|null $state  null = pending
 */
$statePill = static function (?string $state): string {
    return match ($state) {
        'paid'     => '<span class="pill ok">paid</span>',
        'short'    => '<span class="pill warn">short</span>',
        'disputed' => '<span class="pill err">disputed</span>',
        default    => '<span class="pill">pending</span>',
    };
};
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
        expected amount. Click <strong>Mark paid</strong> for fully paid loads,
        <strong>Mark short</strong> if you got less than expected, or
        <strong>Dispute</strong> if there's a problem worth flagging.
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
                — but no payroll contact email is set yet.
            <?php endif; ?>
        </p>
        <?php if ($payrollEmail === null): ?>
            <p>
                <a href="<?= e($base) ?>/profile"
                   style="display:inline-block;background:var(--accent);color:#fff;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
                    Set a payroll contact email &rarr;
                </a>
            </p>
        <?php elseif (! $mailConfigured): ?>
            <p class="muted" style="background:#fef3c7;color:#854d0e;border-radius:6px;padding:.5rem .7rem;font-size:13px;">
                <strong>Heads up:</strong> email delivery is not configured on the
                server yet (waiting on Resend / DNS verification). The batch is
                queued and will go out on your next click once delivery is live.
            </p>
            <form method="post" action="<?= e($base) ?>/reconcile/send-batch">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" disabled
                        style="background:#e5e7eb;color:#6b7280;border:0;padding:.5rem 1.2rem;border-radius:6px;font:inherit;cursor:not-allowed;">
                    Send batch (delivery pending)
                </button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e($base) ?>/reconcile/send-batch">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit"
                        onclick="return confirm('Send <?= (int) $pendingCount ?> disputed load(s) to <?= e($payrollEmail) ?>?');"
                        style="background:#16a34a;color:#fff;border:0;padding:.5rem 1.2rem;border-radius:6px;font:inherit;cursor:pointer;">
                    Send batch to <?= e($payrollEmail) ?>
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$renderRow = static function (array $row, ?array $reconRow) use ($base, $csrfToken, $money, $loadTypeLabel, $statePill) {
    $frtl = (int) ($row['frtl'] ?? 0);
    $np   = (float) ($row['np']  ?? 0);
    $state = $reconRow !== null ? (string) $reconRow['state'] : null;
    $actual    = $reconRow !== null && $reconRow['actual_np']  !== null ? (float) $reconRow['actual_np']  : null;
    $shortfall = $reconRow !== null && $reconRow['shortfall']  !== null ? (float) $reconRow['shortfall']  : null;
    $note      = $reconRow !== null ? (string) ($reconRow['note'] ?? '') : '';
    $notify    = $reconRow !== null && (int) ($reconRow['notify_email'] ?? 0) === 1;
    $emailedAt = $reconRow !== null && is_string($reconRow['emailed_at'] ?? null) ? (string) $reconRow['emailed_at'] : '';
    $rowStyle = match ($state) {
        'paid'     => 'background:#f0fdf4;',
        'short'    => 'background:#fffbeb;',
        'disputed' => 'background:#fef2f2;',
        default    => '',
    };
    ?>
    <tr style="border-bottom:1px solid #e4e8ee;vertical-align:top;<?= $rowStyle ?>">
        <td style="padding:.4rem .5rem;"><code><?= $frtl ?></code></td>
        <td style="padding:.4rem .5rem;"><?= e(substr((string) ($row['date'] ?? ''), 0, 10)) ?></td>
        <td style="padding:.4rem .5rem;"><?= e($loadTypeLabel($row['load_type'] ?? null)) ?></td>
        <td style="padding:.4rem .5rem;">
            <?= e((string) ($row['pickup_city']   ?? '?')) ?>
            &nbsp;&rarr;&nbsp;
            <?= e((string) ($row['delivery_city'] ?? '?')) ?>
        </td>
        <td style="padding:.4rem .5rem;text-align:right;"><code><?= e($money($np)) ?></code></td>
        <td style="padding:.4rem .5rem;">
            <?= $statePill($state) ?>
            <?php if ($state === 'short'    && $shortfall !== null): ?>
                <br><small class="muted">−<?= e($money($shortfall)) ?></small>
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
                <!-- Mark paid -->
                <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/paid" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"
                            style="background:#16a34a;color:#fff;border:0;padding:.25rem .7rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                        Paid
                    </button>
                </form>
                <!-- Mark short -->
                <details style="display:inline-block;">
                    <summary style="display:inline-block;background:#fef9c3;color:#854d0e;border:1px solid #fde68a;padding:.25rem .7rem;border-radius:4px;cursor:pointer;font-size:12px;list-style:none;">Short&hellip;</summary>
                    <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/short"
                          style="position:absolute;z-index:10;background:#fff;border:1px solid #cbd2da;border-radius:6px;padding:.6rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:18rem;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <label style="display:block;font-size:12px;margin-bottom:.3rem;">
                            Actual paid ($)<br>
                            <input type="number" name="actual_np" step="0.01" min="0" max="<?= e(number_format($np, 2, '.', '')) ?>" required
                                   style="width:8rem;padding:.3rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;">
                        </label>
                        <label style="display:block;font-size:12px;margin-bottom:.4rem;">
                            Note (optional)<br>
                            <textarea name="note" rows="2" maxlength="4000"
                                      style="width:100%;padding:.3rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;"></textarea>
                        </label>
                        <button type="submit"
                                style="background:#854d0e;color:#fff;border:0;padding:.3rem .8rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                            Save short
                        </button>
                    </form>
                </details>
                <!-- Dispute -->
                <details style="display:inline-block;">
                    <summary style="display:inline-block;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:.25rem .7rem;border-radius:4px;cursor:pointer;font-size:12px;list-style:none;">Dispute&hellip;</summary>
                    <form method="post" action="<?= e($base) ?>/reconcile/<?= $frtl ?>/dispute"
                          style="position:absolute;z-index:10;background:#fff;border:1px solid #cbd2da;border-radius:6px;padding:.6rem;margin-top:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);min-width:20rem;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <label style="display:block;font-size:12px;margin-bottom:.3rem;">
                            Actual paid ($) <span class="muted">(optional)</span><br>
                            <input type="number" name="actual_np" step="0.01" min="0"
                                   style="width:8rem;padding:.3rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;">
                        </label>
                        <label style="display:block;font-size:12px;margin-bottom:.4rem;">
                            Note (required) — what should payroll know?<br>
                            <textarea name="note" rows="3" maxlength="4000" required
                                      style="width:100%;padding:.3rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;"></textarea>
                        </label>
                        <label style="display:block;font-size:12px;margin-bottom:.4rem;">
                            <input type="checkbox" name="notify_email" value="1">
                            Include in next payroll batch email
                        </label>
                        <button type="submit"
                                style="background:#991b1b;color:#fff;border:0;padding:.3rem .8rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                            Flag dispute
                        </button>
                    </form>
                </details>
            <?php else: ?>
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
    // Detail row for short / disputed with note / actual.
    if ($reconRow !== null && ($state !== 'paid') && ($note !== '' || $actual !== null)):
        ?>
        <tr style="<?= $rowStyle ?>border-bottom:1px solid #e4e8ee;">
            <td colspan="7" style="padding:.5rem 1.4rem;font-size:13px;color:#475569;">
                <?php if ($actual !== null): ?>
                    <strong>Actual paid:</strong> <?= e($money($actual)) ?>
                <?php endif; ?>
                <?php if ($note !== ''): ?>
                    <?php if ($actual !== null): ?> &middot; <?php endif; ?>
                    <strong>Note:</strong> <?= e($note) ?>
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
