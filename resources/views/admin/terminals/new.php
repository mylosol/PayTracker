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
<div class="card">
    <h1>New Begin Empty Location</h1>
    <p class="muted">
        Display name should match the form used elsewhere
        (e.g. <code>Panama City, FL</code>) so distance lookups via
        the cached city pairs resolve cleanly. Linking it to a city
        row is optional but recommended — without the link the
        Begin Empty picker falls back to the manual miles input.
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#fee2e2;color:#991b1b;word-break:break-word;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e($base) ?>/admin/terminals">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label for="name"><strong>Name</strong></label><br>
            <input id="name" name="name" type="text" required maxlength="120"
                   value="<?= e($old['name']) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
        </p>
        <p>
            <label for="city_id"><strong>Linked city (optional)</strong></label><br>
            <select id="city_id" name="city_id"
                    style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
                <option value="">— not linked —</option>
                <?php foreach ($cities as $c): ?>
                    <?php $cid = (int) ($c['id'] ?? 0); ?>
                    <option value="<?= $cid ?>" <?= $old['city_id'] === (string) $cid ? 'selected' : '' ?>>
                        <?= e((string) ($c['city'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <br><small class="muted">
                Used by the Begin Empty picker to look up miles from
                this terminal to the load's pickup city via the cached
                <code>city_distances</code> matrix.
            </small>
        </p>
        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.55rem 1.3rem;border-radius:6px;font:inherit;cursor:pointer;">
                Create
            </button>
            &nbsp;<a href="<?= e($base) ?>/admin/terminals">Cancel</a>
        </p>
    </form>
</div>
