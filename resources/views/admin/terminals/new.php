<?php
/**
 * @var string                              $base
 * @var string                              $csrfToken
 * @var list<array<string,mixed>>           $cities
 * @var ?string                             $flash
 * @var array{name:string,city_id:string}   $old
 */
layout('layouts/app');
?>
<div class="max-w-xl mx-auto">
    <div class="card">
        <h1 class="m-0">New Begin Empty Location</h1>
        <p class="text-brand-muted mt-2">
            Display name should match the form used elsewhere
            (e.g. <code>Panama City, FL</code>) so distance lookups via
            the cached city pairs resolve cleanly. Linking it to a city
            row is optional but recommended — without the link the
            Begin Empty picker falls back to the manual miles input.
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/admin/terminals" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="name" class="field-label">Name</label>
                <input id="name" name="name" type="text" required maxlength="120"
                       value="<?= e($old['name']) ?>" class="field">
            </div>

            <div>
                <label for="city_id" class="field-label">Linked city (optional)</label>
                <select id="city_id" name="city_id" class="field-select">
                    <option value="">— not linked —</option>
                    <?php foreach ($cities as $c): ?>
                        <?php $cid = (int) ($c['id'] ?? 0); ?>
                        <option value="<?= $cid ?>" <?= $old['city_id'] === (string) $cid ? 'selected' : '' ?>>
                            <?= e((string) ($c['city'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="field-hint">
                    Used by the Begin Empty picker to look up miles from
                    this terminal to the load's pickup city via the cached
                    <code>city_distances</code> matrix.
                </span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Create</button>
                <a href="<?= e($base) ?>/admin/terminals" class="text-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
