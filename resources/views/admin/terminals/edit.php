<?php
/**
 * @var string                                                $base
 * @var string                                                $csrfToken
 * @var array{id:int,name:string,city_id:?int,active:int}     $row
 * @var list<array<string,mixed>>                             $cities
 * @var ?string                                               $flash
 */
layout('layouts/app');
$currentCityId = $row['city_id'] !== null ? (string) $row['city_id'] : '';
?>
<div class="max-w-xl mx-auto">
    <div class="card">
        <h1 class="m-0">Edit Begin Empty Location</h1>
        <p class="text-brand-muted mt-2">
            Rename or relink. To remove a location, use the
            <em>Deactivate</em> button on the list view — that keeps the
            row's history intact while hiding it from the driver picker.
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/admin/terminals/<?= (int) $row['id'] ?>/edit"
              class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="name" class="field-label">Name</label>
                <input id="name" name="name" type="text" required maxlength="120"
                       value="<?= e($row['name']) ?>" class="field">
            </div>

            <div>
                <label for="city_id" class="field-label">Linked city</label>
                <select id="city_id" name="city_id" class="field-select">
                    <option value="">— not linked —</option>
                    <?php foreach ($cities as $c): ?>
                        <?php $cid = (int) ($c['id'] ?? 0); ?>
                        <option value="<?= $cid ?>" <?= $currentCityId === (string) $cid ? 'selected' : '' ?>>
                            <?= e((string) ($c['city'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Save</button>
                <a href="<?= e($base) ?>/admin/terminals" class="text-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
