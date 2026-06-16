<?php
/**
 * @var string                                                   $base
 * @var array<string,mixed>                                      $actor
 * @var array{version:string,sapi:string,os:string,memoryLimit:string,timezone:string} $php
 * @var array{version:string,now_utc:string,account_rows:string,audit_rows:string} $db
 * @var list<array{name:string,applied_at:string}>               $migrationsTail
 * @var array<string,int>                                        $hourCounters
 * @var string                                                   $sinceHour
 */
layout('layouts/app');
?>
<div class="card">
    <h1 class="m-0">System diagnostics
        <span class="pill-ok align-middle ml-1 text-xs"><?= e((string) $actor['role']) ?></span>
    </h1>
    <p class="text-brand-muted mt-2">
        Runtime fingerprint and audit-log counters for the last hour.
    </p>
    <p class="text-sm mt-4 flex flex-wrap items-center gap-x-2 gap-y-1">
        <a href="<?= e($base) ?>/admin">← Admin Panel</a>
        <span class="text-brand-muted">·</span>
        <a href="<?= e($base) ?>/admin/audit">Audit log →</a>
    </p>
</div>

<div class="card">
    <h2 class="m-0">Runtime</h2>
    <table class="w-full mt-4">
        <tbody>
            <tr><td class="py-1.5 pr-4 text-brand-muted">PHP version</td>     <td><code><?= e($php['version']) ?></code></td></tr>
            <tr><td class="py-1.5 pr-4 text-brand-muted">SAPI</td>            <td><code><?= e($php['sapi']) ?></code></td></tr>
            <tr><td class="py-1.5 pr-4 text-brand-muted">OS family</td>       <td><code><?= e($php['os']) ?></code></td></tr>
            <tr><td class="py-1.5 pr-4 text-brand-muted">Memory limit</td>    <td><code><?= e($php['memoryLimit']) ?></code></td></tr>
            <tr><td class="py-1.5 pr-4 text-brand-muted">Default timezone</td><td><code><?= e($php['timezone']) ?></code></td></tr>
            <tr><td class="py-1.5 pr-4 text-brand-muted">DB version</td>      <td><code><?= e($db['version']) ?></code></td></tr>
            <tr><td class="py-1.5 pr-4 text-brand-muted">DB now (UTC)</td>    <td><code><?= e($db['now_utc']) ?></code></td></tr>
            <tr><td class="py-1.5 pr-4 text-brand-muted">account rows</td>    <td><code><?= e($db['account_rows']) ?></code></td></tr>
            <tr><td class="py-1.5 pr-4 text-brand-muted">audit_logs rows</td> <td><code><?= e($db['audit_rows']) ?></code></td></tr>
        </tbody>
    </table>
</div>

<div class="card">
    <h2 class="m-0">Audit counters — last hour</h2>
    <p class="text-brand-muted mt-2">Since <code><?= e($sinceHour) ?> UTC</code></p>
    <?php if ($hourCounters === []): ?>
        <p class="text-brand-muted mt-3 mb-0">No audit events in the last hour.</p>
    <?php else: ?>
        <div class="table-wrap mt-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th class="text-right">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hourCounters as $action => $count): ?>
                        <?php $isFail = str_contains((string) $action, 'FAILED'); ?>
                        <tr class="<?= $isFail && $count > 5 ? 'bg-rose-50/60' : '' ?>">
                            <td><code><?= e((string) $action) ?></code></td>
                            <td class="text-right"><strong><?= (int) $count ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-sm text-brand-muted mt-3 mb-0">
            Failed-login rows highlight red when the hourly count exceeds 5 —
            an early warning for a credential-stuffing attempt.
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="m-0">Recent migrations</h2>
    <?php if ($migrationsTail === []): ?>
        <p class="text-brand-muted mt-3 mb-0">No migrations recorded (or the <code>_migrations</code> table is missing).</p>
    <?php else: ?>
        <div class="table-wrap mt-4">
            <table class="data-table text-[13px]">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Applied (UTC)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($migrationsTail as $m): ?>
                        <tr>
                            <td><code><?= e($m['name']) ?></code></td>
                            <td><?= e($m['applied_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
