<?php
/**
 * @var list<array{id:int, city:string}> $cities
 * @var string                           $base
 * @var string|null                      $flash
 */
layout('layouts/app');
?>
<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="m-0">Locations</h1>
            <p class="text-brand-muted mt-2">
                <?= count($cities) ?> cities on file.
            </p>
        </div>
        <a href="<?= e($base) ?>/locations/new" class="btn-primary">+ Add city</a>
    </div>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-ok" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <?php if ($cities === []): ?>
        <p class="text-brand-muted m-0">No cities recorded yet.</p>
    <?php else: ?>
        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 list-none p-0 m-0 text-[0.95rem]">
            <?php foreach ($cities as $city): ?>
                <li class="py-1.5 border-b border-brand-line/60"><?= e((string) $city['city']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
