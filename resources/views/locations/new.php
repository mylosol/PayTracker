<?php
/**
 * @var string              $csrfToken
 * @var string              $base
 * @var list<string>        $states
 * @var string|null         $flash
 * @var array{name:string,state:string} $old
 */
layout('layouts/app');
?>
<div class="max-w-xl mx-auto">
    <div class="card">
        <h1 class="m-0">Add a city</h1>
        <p class="text-brand-muted mt-2">
            Inserts a row into the <code>city</code> table for use across the
            pickup / delivery pickers and the distance cache.
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/locations" novalidate autocomplete="off" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="name" class="field-label">City name</label>
                <input id="name" name="name" type="text" required autofocus maxlength="48"
                       value="<?= e((string) $old['name']) ?>"
                       pattern="[A-Za-z][A-Za-z .'\-]{0,47}"
                       class="field">
                <span class="field-hint">Letters, spaces, periods, hyphens, apostrophes only.</span>
            </div>

            <div>
                <label for="state" class="field-label">State</label>
                <select id="state" name="state" required class="field-select w-32">
                    <option value="">— choose —</option>
                    <?php foreach ($states as $code): ?>
                        <option value="<?= e($code) ?>" <?= $old['state'] === $code ? 'selected' : '' ?>>
                            <?= e($code) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Add city</button>
                <a href="<?= e($base) ?>/locations" class="text-sm">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="text-sm text-brand-muted m-0">
            This form inserts a row into the <code>city</code> table only.
            The legacy <code>largeMiles</code> distance matrix is not updated
            here — the modern lookup goes through <code>city_distances</code>
            (with a Google Maps fill-fallback) and admins can override pair
            distances on the <a href="<?= e($base) ?>/distances">distances</a> page.
        </p>
    </div>
</div>
