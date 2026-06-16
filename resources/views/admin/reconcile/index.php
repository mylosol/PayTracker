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
    <h1 class="m-0">Reconcile queue
        <span class="pill ok align-middle ml-1 text-xs">super_admin</span>
    </h1>
    <p class="text-brand-muted mt-2">
        Every open dispute across every driver. Read-only — drivers
        resolve their own disputes from <code>/reconcile</code> by
        downgrading to <em>paid</em> / <em>short</em> or pressing
        <em>Undo</em> once the issue is settled.
    </p>
    <p class="text-sm mt-3">
        <a href="<?= e($base) ?>/admin">← Admin Panel</a>
    </p>
</div>

<div class="card">
    <?php if ($rows === []): ?>
        <p class="text-brand-muted">No open disputes.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table text-[14px]">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>FRTL</th>
                        <th class="text-right">Expected</th>
                        <th class="text-right">Actual</th>
                        <th class="text-right">Gap</th>
                        <th>Note</th>
                        <th>Notify</th>
                        <th>Updated</th>
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
                        <tr class="align-top">
                            <td>
                                <strong><?= e($driverUser) ?></strong>
                                <?php if ($driverEmail !== ''): ?>
                                    <br><small class="text-brand-muted"><?= e($driverEmail) ?></small>
                                <?php endif; ?>
                                <?php if ($payrollAddr !== ''): ?>
                                    <br><small class="text-brand-muted">payroll: <?= e($payrollAddr) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?= (int) ($r['frtl'] ?? 0) ?></code></td>
                            <td class="text-right"><code><?= e($money($expected)) ?></code></td>
                            <td class="text-right"><?= $actual    !== null ? '<code>' . e($money($actual))    . '</code>' : '<span class="text-brand-muted">—</span>' ?></td>
                            <td class="text-right"><?= $shortfall !== null ? '<code>' . e($money($shortfall)) . '</code>' : '<span class="text-brand-muted">—</span>' ?></td>
                            <td class="text-[13px] text-brand-muted max-w-md break-words"><?= e($note) ?></td>
                            <td>
                                <?php if ($notify && $emailedAt === ''): ?>
                                    <span class="pill warn">in batch</span>
                                <?php elseif ($notify && $emailedAt !== ''): ?>
                                    <span class="pill ok">batched</span>
                                <?php else: ?>
                                    <span class="text-brand-muted">no</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-[12px] text-brand-muted whitespace-nowrap"><?= e(substr((string) ($r['updated_at'] ?? ''), 0, 16)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
