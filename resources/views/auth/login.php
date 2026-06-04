<?php
/**
 * @var string      $csrfToken
 * @var string|null $flash
 * @var string      $base
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Sign in</h1>

    <?php if ($flash !== null): ?>
        <p class="muted" style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:.6rem .8rem;">
            <?= e($flash) ?>
        </p>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/login" novalidate autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="handle"><strong>Login or email</strong></label><br>
            <input id="handle" name="handle" type="text" autocomplete="username" required autofocus
                   style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
        </p>

        <p>
            <label for="password"><strong>Password</strong></label><br>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
        </p>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Sign in
            </button>
        </p>
    </form>

    <p class="muted" style="margin-top:1.5rem;font-size:13px;">
        Have an invite code?
        <a href="<?= e($base) ?>/register">Create an account &rarr;</a>.
        Otherwise, ask an administrator to send you one
        (<a href="<?= e($base) ?>/contact">/contact</a>).
    </p>
</div>
