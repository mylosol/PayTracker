<?php
/**
 * @var string      $csrfToken
 * @var string|null $flash
 * @var string      $base
 */
layout('layouts/app');
?>
<div class="max-w-md mx-auto">
    <div class="card">
        <h1 class="m-0">Sign in</h1>
        <p class="text-brand-muted mt-2 mb-5">Welcome back.</p>

        <?php if ($flash !== null): ?>
            <div class="flash-err" role="alert"><?= e($flash) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e($base) ?>/login" novalidate autocomplete="on" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="handle" class="field-label">Login or email</label>
                <input id="handle" name="handle" type="text" autocomplete="username" required autofocus
                       class="field" inputmode="email">
            </div>

            <div>
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="field">
            </div>

            <label class="inline-flex items-center gap-2 min-h-[44px]">
                <input type="checkbox" name="remember" value="1" class="field-checkbox">
                <span>Keep me logged in for 30 days</span>
            </label>

            <button type="submit" class="btn-primary w-full sm:w-auto">
                Sign in
            </button>
        </form>

        <p class="text-sm text-brand-muted mt-6">
            Have an invite code?
            <a href="<?= e($base) ?>/register" class="font-semibold">Create an account →</a><br>
            Otherwise, ask an administrator to send you one
            (<a href="<?= e($base) ?>/contact">/contact</a>).
        </p>
    </div>
</div>
