<?php
/**
 * @var string                                                                       $base
 * @var string                                                                       $csrfToken
 * @var array<string,mixed>                                                          $row
 * @var list<array{account_id:int,user:?string,email:?string,dismissed_at:string,suppressed:int}> $viewers
 * @var ?string                                                                      $flash
 */
layout('layouts/app');

$id = (int) ($row['id'] ?? 0);
?>
<div class="card">
    <h1 class="m-0">Announcement #<?= $id ?></h1>
    <p class="text-sm mt-3 flex flex-wrap items-center gap-x-2 gap-y-1">
        <a href="<?= e($base) ?>/admin/announcements">← Back to list</a>
        <span class="text-brand-muted">·</span>
        <a href="<?= e($base) ?>/admin/announcements/<?= $id ?>/edit">Edit</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-ok" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="m-0"><?= e((string) ($row['subject'] ?? '')) ?></h2>
    <p class="text-brand-muted text-sm mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
        <span>State:</span>
        <?php if ((int) ($row['is_template'] ?? 0) === 1): ?>
            <span class="pill warn">template</span>
        <?php elseif ((int) ($row['is_active'] ?? 0) === 1): ?>
            <span class="pill ok">active</span>
        <?php else: ?>
            <span class="pill">inactive</span>
        <?php endif; ?>
        <span class="text-brand-muted">·</span>
        <span>Created by <code><?= e((string) ($row['created_by_user'] ?? '—')) ?></code>
            on <?= e(utc_to_local_display(is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : null)) ?></span>
        <?php if (is_string($row['expires_at'] ?? null) && $row['expires_at'] !== ''): ?>
            <span class="text-brand-muted">·</span>
            <span>Expires <strong><?= e(utc_to_local_display((string) $row['expires_at'])) ?></strong></span>
        <?php endif; ?>
    </p>
    <hr class="border-brand-line my-4">
    <div class="whitespace-pre-wrap leading-relaxed">
        <?= e((string) ($row['body'] ?? '')) ?>
    </div>
</div>

<div class="card">
    <h2 class="m-0">Seen by <span class="text-brand-muted text-sm font-normal">(<?= count($viewers) ?> users)</span></h2>
    <?php if ($viewers === []): ?>
        <p class="text-brand-muted mt-3">No users have dismissed this announcement yet.</p>
    <?php else: ?>
        <div class="table-wrap mt-4">
            <table class="data-table text-[13px]">
                <thead>
                    <tr>
                        <th>Account ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Dismissed at</th>
                        <th>Don't show again?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($viewers as $v): ?>
                        <tr>
                            <td><code><?= (int) ($v['account_id'] ?? 0) ?></code></td>
                            <td><?= e((string) ($v['user'] ?? '(deleted)')) ?></td>
                            <td><?= e((string) ($v['email'] ?? '—')) ?></td>
                            <td><?= e(utc_to_local_display(is_string($v['dismissed_at'] ?? null) ? (string) $v['dismissed_at'] : null)) ?></td>
                            <td>
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
        </div>
    <?php endif; ?>
</div>
