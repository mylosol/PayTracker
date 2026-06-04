<?php
/**
 * @var string  $base
 * @var string  $csrfToken
 * @var string  $token
 * @var int     $minLen
 * @var ?string $flash
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Set a new password</h1>
    <p class="muted">
        This reset link is single-use and expires one hour after the
        admin generated it. Pick a strong password &mdash; minimum
        <strong><?= (int) $minLen ?></strong> characters.
    </p>

    <?php if ($flash !== null): ?>
        <p style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:.6rem .8rem;">
            <?= e($flash) ?>
        </p>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/password-reset/<?= e($token) ?>" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="password"><strong>New password</strong></label><br>
            <input id="password" name="password" type="password"
                   minlength="<?= (int) $minLen ?>" required autofocus
                   style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
        </p>
        <p>
            <label for="password_confirmation"><strong>Confirm password</strong></label><br>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   minlength="<?= (int) $minLen ?>" required
                   style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
        </p>
        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Set password
            </button>
        </p>
    </form>
</div>
