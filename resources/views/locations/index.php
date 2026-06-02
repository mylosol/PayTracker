<?php
/**
 * @var list<array{id:int, city:string}> $cities
 * @var string                           $base
 * @var string|null                      $flash
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Locations</h1>

    <?php if ($flash !== null): ?>
        <p class="muted" style="background:#dcfce7;color:#166534;border-radius:6px;padding:.6rem .8rem;">
            <?= e($flash) ?>
        </p>
    <?php endif; ?>

    <p>
        <a href="<?= e($base) ?>/locations/new"
           style="display:inline-block;background:var(--accent);color:#fff;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
            + Add city
        </a>
        &nbsp;<a href="<?= e($base) ?>/">&larr; Back</a>
    </p>

    <p class="muted">
        <?= count($cities) ?> cities on file.
    </p>

    <?php if ($cities === []): ?>
        <p>No cities recorded yet.</p>
    <?php else: ?>
        <ul style="columns:2;column-gap:1.5rem;font-size:14px;">
        <?php foreach ($cities as $city): ?>
            <li><?= e((string) $city['city']) ?></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
