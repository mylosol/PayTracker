<?php
/**
 * @var string                                                                       $base
 * @var string                                                                       $csrfToken
 * @var array<string,mixed>                                                          $row
 * @var list<array{account_id:int,user:?string,email:?string,dismissed_at:string,suppressed:int}> $viewers
 * @var ?string                                                                      $flash
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Announcement #<?= (int) ($row['id'] ?? 0) ?></h1>
    <p>
        <a href="<?= e($base) ?>/admin/announcements">&larr; Back to list</a>
        &middot;
        <a href="<?= e($base) ?>/admin/announcements/<?= (int) ($row['id'] ?? 0) ?>/edit">Edit</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#dcfce7;color:#166534;"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <h2><?= e((string) ($row['subject'] ?? '')) ?></h2>
    <p class="muted">
        State:
        <?php if ((int) ($row['is_template'] ?? 0) === 1): ?>
            <span class="pill warn">template</span>
        <?php elseif ((int) ($row['is_active'] ?? 0) === 1): ?>
            <span class="pill ok">active</span>
        <?php else: ?>
            <span class="pill">inactive</span>
        <?php endif; ?>
        &middot; Created by <code><?= e((string) ($row['created_by_user'] ?? '—')) ?></code>
        on <?= e(utc_to_local_display(is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : null)) ?>
        <?php if (is_string($row['expires_at'] ?? null) && $row['expires_at'] !== ''): ?>
            &middot; Expires <strong><?= e(utc_to_local_display((string) $row['expires_at'])) ?></strong>
        <?php endif; ?>
    </p>
    <hr>
    <div style="white-space:pre-wrap;line-height:1.55;">
        <?= e((string) ($row['body'] ?? '')) ?>
    </div>
</div>

<div class="card">
    <h2>Seen by <span class="muted" style="font-size:14px;">(<?= count($viewers) ?> users)</span></h2>
    <?php if ($viewers === []): ?>
        <p class="muted">No users have dismissed this announcement yet.</p>
    <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #cbd2da;">
                    <th style="padding:.3rem .25rem;">Account ID</th>
                    <th style="padding:.3rem .25rem;">User</th>
                    <th style="padding:.3rem .25rem;">Email</th>
                    <th style="padding:.3rem .25rem;">Dismissed at</th>
                    <th style="padding:.3rem .25rem;">Don't show again?</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($viewers as $v): ?>
                    <tr style="border-bottom:1px solid #e4e8ee;">
                        <td style="padding:.3rem .25rem;"><code><?= (int) ($v['account_id'] ?? 0) ?></code></td>
                        <td style="padding:.3rem .25rem;"><?= e((string) ($v['user'] ?? '(deleted)')) ?></td>
                        <td style="padding:.3rem .25rem;"><?= e((string) ($v['email'] ?? '—')) ?></td>
                        <td style="padding:.3rem .25rem;"><?= e(utc_to_local_display(is_string($v['dismissed_at'] ?? null) ? (string) $v['dismissed_at'] : null)) ?></td>
                        <td style="padding:.3rem .25rem;">
                            <?php if ((int) ($v['suppressed'] ?? 0) === 1): ?>
                                <span class="pill ok">yes — permanent</span>
                            <?php else: ?>
                                <span class="pill">no — will see again</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
