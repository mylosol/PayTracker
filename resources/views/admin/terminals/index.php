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
    <h1>Begin Empty Locations</h1>
    <p class="muted">
        The dispatch hubs a driver can pick from when they checked
        "Begin Empty" on a load. Deactivating a row keeps it in the
        database (so historical loads still tell a coherent story)
        but hides it from the picker on new entries.
    </p>
    <p>
        <a href="<?= e($base) ?>/admin/terminals/new"
           style="display:inline-block;background:var(--accent);color:#fff;padding:.5rem 1.2rem;border-radius:6px;text-decoration:none;">
            + New location
        </a>
        &nbsp;<a href="<?= e($base) ?>/admin">&larr; Admin Panel</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#dcfce7;color:#166534;word-break:break-all;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:2px solid #cbd2da;">
                <th style="padding:.5rem .25rem;">Name</th>
                <th style="padding:.5rem .25rem;">Linked city</th>
                <th style="padding:.5rem .25rem;">State</th>
                <th style="padding:.5rem .25rem;">Updated</th>
                <th style="padding:.5rem .25rem;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="5" style="padding:.6rem;color:#5a6470;">No locations yet. Add one above.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $active = (int) ($r['active'] ?? 0) === 1;
                ?>
                <tr style="border-bottom:1px solid #e4e8ee;">
                    <td style="padding:.5rem .25rem;"><strong><?= e($r['name']) ?></strong></td>
                    <td style="padding:.5rem .25rem;">
                        <?php if ($r['city_id'] !== null): ?>
                            <code>city.id = <?= (int) $r['city_id'] ?></code>
                        <?php else: ?>
                            <span class="muted">— not linked</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.5rem .25rem;">
                        <?php if ($active): ?>
                            <span class="pill ok">active</span>
                        <?php else: ?>
                            <span class="pill warn">inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.5rem .25rem;"><?= e($fmt($r['updated_at'] ?? null)) ?></td>
                    <td style="padding:.5rem .25rem;">
                        <a href="<?= e($base) ?>/admin/terminals/<?= (int) $r['id'] ?>/edit"
                           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.25rem .6rem;border-radius:4px;text-decoration:none;font-size:13px;">
                            Edit
                        </a>
                        <?php if ($active): ?>
                            <form method="post" action="<?= e($base) ?>/admin/terminals/<?= (int) $r['id'] ?>/deactivate"
                                  onsubmit="return confirm('Deactivate &quot;<?= e($r['name']) ?>&quot;? It will be hidden from the driver picker but stay in the database.');"
                                  style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit"
                                        style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                    Deactivate
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e($base) ?>/admin/terminals/<?= (int) $r['id'] ?>/reactivate"
                                  style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit"
                                        style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                    Reactivate
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
