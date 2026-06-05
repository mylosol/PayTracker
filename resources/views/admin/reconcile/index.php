<?php
/**
 * @var string                          $base
 * @var array<string,mixed>             $driver
 * @var list<array<string,mixed>>       $rows
 */
layout('layouts/app');

$money = static fn ($v): string => '$' . number_format((float) $v, 2);
?>
<div class="card">
    <h1>Reconcile queue <span class="pill ok">super_admin</span></h1>
    <p class="muted">
        Every open dispute across every driver. Read-only — drivers
        resolve their own disputes from <code>/reconcile</code> by
        downgrading to <em>paid</em> / <em>short</em> or pressing
        <em>Undo</em> once the issue is settled.
    </p>
    <p><a href="<?= e($base) ?>/admin">&larr; Admin Panel</a></p>
</div>

<div class="card">
    <?php if ($rows === []): ?>
        <p class="muted">No open disputes.</p>
    <?php else: ?>
        <table style="border-collapse:collapse;width:100%;font-size:14px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e4e8ee;background:#f1f5f9;">
                    <th style="padding:.5rem .5rem;">Driver</th>
                    <th style="padding:.5rem .5rem;">FRTL</th>
                    <th style="padding:.5rem .5rem;text-align:right;">Expected</th>
                    <th style="padding:.5rem .5rem;text-align:right;">Actual</th>
                    <th style="padding:.5rem .5rem;text-align:right;">Gap</th>
                    <th style="padding:.5rem .5rem;">Note</th>
                    <th style="padding:.5rem .5rem;">Notify</th>
                    <th style="padding:.5rem .5rem;">Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $driverUser  = (string) ($r['driver_user']   ?? '');
                    $driverEmail = (string) ($r['driver_email']  ?? '');
                    $payrollAddr = (string) ($r['payroll_email'] ?? '');
                    $expected    = (float) ($r['expected_np']    ?? 0);
                    $actual      = $r['actual_np'] !== null ? (float) $r['actual_np'] : null;
                    $shortfall   = $r['shortfall'] !== null ? (float) $r['shortfall'] : null;
                    $note        = (string) ($r['note']        ?? '');
                    $notify      = (int) ($r['notify_email']   ?? 0) === 1;
                    $emailedAt   = is_string($r['emailed_at'] ?? null) ? (string) $r['emailed_at'] : '';
                    ?>
                    <tr style="border-bottom:1px solid #f0f2f6;vertical-align:top;">
                        <td style="padding:.4rem .5rem;">
                            <strong><?= e($driverUser) ?></strong>
                            <?php if ($driverEmail !== ''): ?>
                                <br><small class="muted"><?= e($driverEmail) ?></small>
                            <?php endif; ?>
                            <?php if ($payrollAddr !== ''): ?>
                                <br><small class="muted">payroll: <?= e($payrollAddr) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding:.4rem .5rem;"><code><?= (int) ($r['frtl'] ?? 0) ?></code></td>
                        <td style="padding:.4rem .5rem;text-align:right;"><code><?= e($money($expected)) ?></code></td>
                        <td style="padding:.4rem .5rem;text-align:right;"><?= $actual    !== null ? '<code>' . e($money($actual))    . '</code>' : '<span class="muted">&mdash;</span>' ?></td>
                        <td style="padding:.4rem .5rem;text-align:right;"><?= $shortfall !== null ? '<code>' . e($money($shortfall)) . '</code>' : '<span class="muted">&mdash;</span>' ?></td>
                        <td style="padding:.4rem .5rem;font-size:13px;color:#475569;max-width:24rem;word-break:break-word;"><?= e($note) ?></td>
                        <td style="padding:.4rem .5rem;">
                            <?php if ($notify && $emailedAt === ''): ?>
                                <span class="pill warn">in batch</span>
                            <?php elseif ($notify && $emailedAt !== ''): ?>
                                <span class="pill ok">batched</span>
                            <?php else: ?>
                                <span class="muted">no</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:.4rem .5rem;font-size:12px;color:#475569;"><?= e(substr((string) ($r['updated_at'] ?? ''), 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
