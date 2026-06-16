<?php
/**
 * @var string                                              $base
 * @var string                                              $csrfToken
 * @var list<array{id:int,name:string,city_id:?int,active:int,created_at:?string,updated_at:?string}> $rows
 * @var ?string                                             $flash
 */
layout('layouts/app');

$fmt = static fn ($value): string => utc_to_local_display(is_string($value) ? $value : null);
?>
<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="m-0">Begin Empty Locations</h1>
            <p class="text-brand-muted mt-2">
                The dispatch hubs a driver can pick from when they checked
                "Begin Empty" on a load. Deactivating a row keeps it in the
                database (so historical loads still tell a coherent story)
                but hides it from the picker on new entries.
            </p>
        </div>
        <a href="<?= e($base) ?>/admin/terminals/new" class="btn-primary">+ New location</a>
    </div>
    <p class="text-sm mt-4">
        <a href="<?= e($base) ?>/admin">← Admin Panel</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-ok" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <?php if ($rows === []): ?>
        <p class="text-brand-muted m-0">No locations yet. Add one above.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Linked city</th>
                        <th>State</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php $active = (int) ($r['active'] ?? 0) === 1; ?>
                        <tr>
                            <td><strong><?= e($r['name']) ?></strong></td>
                            <td>
                                <?php if ($r['city_id'] !== null): ?>
                                    <code>city.id = <?= (int) $r['city_id'] ?></code>
                                <?php else: ?>
                                    <span class="text-brand-muted">— not linked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($active): ?>
                                    <span class="pill ok">active</span>
                                <?php else: ?>
                                    <span class="pill warn">inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-brand-muted text-sm"><?= e($fmt($r['updated_at'] ?? null)) ?></td>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="<?= e($base) ?>/admin/terminals/<?= (int) $r['id'] ?>/edit"
                                       class="btn-secondary btn-sm">Edit</a>
                                    <?php if ($active): ?>
                                        <form method="post" action="<?= e($base) ?>/admin/terminals/<?= (int) $r['id'] ?>/deactivate"
                                              onsubmit="return confirm('Deactivate &quot;<?= e($r['name']) ?>&quot;? It will be hidden from the driver picker but stay in the database.');"
                                              class="inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 text-sm rounded-md font-semibold bg-amber-100 text-amber-900 border border-amber-200 hover:bg-amber-200 transition-colors cursor-pointer">
                                                Deactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= e($base) ?>/admin/terminals/<?= (int) $r['id'] ?>/reactivate"
                                              class="inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 text-sm rounded-md font-semibold bg-emerald-100 text-emerald-900 border border-emerald-200 hover:bg-emerald-200 transition-colors cursor-pointer">
                                                Reactivate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
