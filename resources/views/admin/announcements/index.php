<?php
/**
 * @var string                     $base
 * @var string                     $csrfToken
 * @var list<array<string,mixed>>  $rows
 * @var ?string                    $flash
 */
layout('layouts/app');

// Stored as UTC; render in admin's local tz with the abbreviation appended.
$fmt = static fn ($value): string => utc_to_local_display(is_string($value) ? $value : null);
?>
<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="m-0">Announcements
                <span class="pill ok align-middle ml-1 text-xs">super_admin</span>
            </h1>
            <p class="text-brand-muted mt-2">
                Subject + body, shown to every signed-in user as a modal on
                their next page render after login. Only ONE announcement
                can be active at a time. Templates are stored for reuse and
                are never shown to users.
            </p>
        </div>
        <a href="<?= e($base) ?>/admin/announcements/new" class="btn-primary">+ New announcement</a>
    </div>
    <p class="text-sm mt-4">
        <a href="<?= e($base) ?>/admin">← Admin Panel</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-ok break-words" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>State</th>
                    <th>Expires</th>
                    <th class="text-right">Seen by</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="text-brand-muted">No announcements yet. Create one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $rId       = (int) ($r['id'] ?? 0);
                    $isTplt    = (int) ($r['is_template'] ?? 0) === 1;
                    $isActive  = (int) ($r['is_active']   ?? 0) === 1;
                    $expired = false;
                    if (is_string($r['expires_at'] ?? null) && $r['expires_at'] !== '') {
                        try {
                            $expired = (new \DateTimeImmutable((string) $r['expires_at'], new \DateTimeZone('UTC')))
                                           ->getTimestamp() < time();
                        } catch (\Throwable) {}
                    }
                    ?>
                    <tr>
                        <td><code><?= $rId ?></code></td>
                        <td><a href="<?= e($base) ?>/admin/announcements/<?= $rId ?>"><?= e((string) ($r['subject'] ?? '')) ?></a></td>
                        <td>
                            <div class="flex flex-wrap items-center gap-1">
                                <?php if ($isTplt): ?>
                                    <span class="pill warn">template</span>
                                <?php endif; ?>
                                <?php if ($isActive && ! $isTplt && ! $expired): ?>
                                    <span class="pill ok">active</span>
                                <?php endif; ?>
                                <?php if ($expired): ?>
                                    <span class="pill err">expired</span>
                                <?php endif; ?>
                                <?php if (! $isTplt && ! $isActive && ! $expired): ?>
                                    <span class="pill muted">inactive</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-brand-muted text-sm"><?= e($fmt($r['expires_at'] ?? null)) ?></td>
                        <td class="text-right"><?= (int) ($r['viewer_count'] ?? 0) ?></td>
                        <td class="text-brand-muted text-sm"><?= e($fmt($r['updated_at'] ?? null)) ?></td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="<?= e($base) ?>/admin/announcements/<?= $rId ?>/edit"
                                   class="btn-secondary btn-sm">Edit</a>
                                <?php if ($isTplt): ?>
                                    <form method="post" action="<?= e($base) ?>/admin/announcements/<?= $rId ?>/use-template" class="inline">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <button type="submit" class="btn-secondary btn-sm">Use template</button>
                                    </form>
                                <?php elseif ($isActive): ?>
                                    <form method="post" action="<?= e($base) ?>/admin/announcements/<?= $rId ?>/deactivate" class="inline">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <button type="submit"
                                                class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 text-sm rounded-md font-semibold bg-amber-100 text-amber-900 border border-amber-200 hover:bg-amber-200 transition-colors cursor-pointer">
                                            Deactivate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= e($base) ?>/admin/announcements/<?= $rId ?>/activate" class="inline">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <button type="submit"
                                                class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 text-sm rounded-md font-semibold bg-emerald-100 text-emerald-900 border border-emerald-200 hover:bg-emerald-200 transition-colors cursor-pointer">
                                            Activate
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= e($base) ?>/admin/announcements/<?= $rId ?>/delete"
                                      onsubmit="return confirm('PERMANENTLY DELETE this announcement and every dismissal record for it?');"
                                      class="inline">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button type="submit" class="btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
