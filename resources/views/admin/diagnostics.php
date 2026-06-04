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
    <h1>System diagnostics <span class="pill ok"><?= e((string) $actor['role']) ?></span></h1>
    <p class="muted">
        Runtime fingerprint and audit-log counters for the last hour.
    </p>
    <p>
        <a href="<?= e($base) ?>/admin">&larr; Admin Panel</a> &middot;
        <a href="<?= e($base) ?>/admin/audit">Audit log &rarr;</a>
    </p>
</div>

<div class="card">
    <h2>Runtime</h2>
    <table style="width:100%;border-collapse:collapse;">
        <tr><td style="padding:.3rem 0;color:#5a6470;">PHP version</td>          <td><code><?= e($php['version']) ?></code></td></tr>
        <tr><td style="padding:.3rem 0;color:#5a6470;">SAPI</td>                  <td><code><?= e($php['sapi']) ?></code></td></tr>
        <tr><td style="padding:.3rem 0;color:#5a6470;">OS family</td>             <td><code><?= e($php['os']) ?></code></td></tr>
        <tr><td style="padding:.3rem 0;color:#5a6470;">Memory limit</td>          <td><code><?= e($php['memoryLimit']) ?></code></td></tr>
        <tr><td style="padding:.3rem 0;color:#5a6470;">Default timezone</td>      <td><code><?= e($php['timezone']) ?></code></td></tr>
        <tr><td style="padding:.3rem 0;color:#5a6470;">DB version</td>            <td><code><?= e($db['version']) ?></code></td></tr>
        <tr><td style="padding:.3rem 0;color:#5a6470;">DB now (UTC)</td>          <td><code><?= e($db['now_utc']) ?></code></td></tr>
        <tr><td style="padding:.3rem 0;color:#5a6470;">account rows</td>         <td><code><?= e($db['account_rows']) ?></code></td></tr>
        <tr><td style="padding:.3rem 0;color:#5a6470;">audit_logs rows</td>      <td><code><?= e($db['audit_rows']) ?></code></td></tr>
    </table>
</div>

<div class="card">
    <h2>Audit counters — last hour</h2>
    <p class="muted">Since <code><?= e($sinceHour) ?> UTC</code></p>
    <?php if ($hourCounters === []): ?>
        <p class="muted">No audit events in the last hour.</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #cbd2da;">
                    <th style="padding:.3rem .25rem;">Action</th>
                    <th style="padding:.3rem .25rem;text-align:right;">Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hourCounters as $action => $count): ?>
                    <?php $isFail = str_contains((string) $action, 'FAILED'); ?>
                    <tr style="border-bottom:1px solid #e4e8ee;<?= $isFail && $count > 5 ? 'background:#fee2e2;' : '' ?>">
                        <td style="padding:.3rem .25rem;"><code><?= e((string) $action) ?></code></td>
                        <td style="padding:.3rem .25rem;text-align:right;"><strong><?= (int) $count ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted" style="margin-top:.5rem;font-size:13px;">
            Failed-login rows highlight red when the hourly count exceeds 5 —
            an early warning for a credential-stuffing attempt.
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Recent migrations</h2>
    <?php if ($migrationsTail === []): ?>
        <p class="muted">No migrations recorded (or the _migrations table is missing).</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #cbd2da;">
                    <th style="padding:.3rem .25rem;">Name</th>
                    <th style="padding:.3rem .25rem;">Applied (UTC)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($migrationsTail as $m): ?>
                    <tr style="border-bottom:1px solid #e4e8ee;">
                        <td style="padding:.3rem .25rem;"><code><?= e($m['name']) ?></code></td>
                        <td style="padding:.3rem .25rem;"><?= e($m['applied_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
