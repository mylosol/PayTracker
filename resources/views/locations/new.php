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
<div class="card">
    <h1>Add a city</h1>

    <?php if ($flash !== null): ?>
        <p class="muted" style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:.6rem .8rem;">
            <?= e($flash) ?>
        </p>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/locations" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="name"><strong>City name</strong></label><br>
            <input id="name" name="name" type="text" required autofocus maxlength="48"
                   value="<?= e((string) $old['name']) ?>"
                   pattern="[A-Za-z][A-Za-z .'\-]{0,47}"
                   style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
            <small class="muted">Letters, spaces, periods, hyphens, apostrophes only.</small>
        </p>

        <p>
            <label for="state"><strong>State</strong></label><br>
            <select id="state" name="state" required
                    style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
                <option value="">— choose —</option>
                <?php foreach ($states as $code): ?>
                    <option value="<?= e($code) ?>" <?= $old['state'] === $code ? 'selected' : '' ?>>
                        <?= e($code) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Add city
            </button>
            &nbsp;<a href="<?= e($base) ?>/locations">Cancel</a>
        </p>
    </form>

    <p class="muted" style="margin-top:1.5rem;font-size:13px;">
        This modern form inserts a row into the <code>city</code> table only.
        The legacy <code>largeMiles</code> distance matrix is not updated yet
        &mdash; a follow-up branch will replace that anti-relational design
        with a normalised <code>city_distances</code> table.
    </p>
</div>
