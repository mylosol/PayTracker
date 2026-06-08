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
<div class="card">
    <h1>Edit Begin Empty Location</h1>
    <p class="muted">
        Rename or relink. To remove a location, use the
        <em>Deactivate</em> button on the list view — that keeps the
        row's history intact while hiding it from the driver picker.
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#fee2e2;color:#991b1b;word-break:break-word;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e($base) ?>/admin/terminals/<?= (int) $row['id'] ?>/edit">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label for="name"><strong>Name</strong></label><br>
            <input id="name" name="name" type="text" required maxlength="120"
                   value="<?= e($row['name']) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
        </p>
        <p>
            <label for="city_id"><strong>Linked city</strong></label><br>
            <select id="city_id" name="city_id"
                    style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
                <option value="">— not linked —</option>
                <?php foreach ($cities as $c): ?>
                    <?php $cid = (int) ($c['id'] ?? 0); ?>
                    <option value="<?= $cid ?>" <?= $currentCityId === (string) $cid ? 'selected' : '' ?>>
                        <?= e((string) ($c['city'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.55rem 1.3rem;border-radius:6px;font:inherit;cursor:pointer;">
                Save
            </button>
            &nbsp;<a href="<?= e($base) ?>/admin/terminals">Cancel</a>
        </p>
    </form>
</div>
