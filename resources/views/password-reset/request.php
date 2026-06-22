<?php
/**
 * @var string      $base
 * @var string      $csrfToken
 * @var string|null $flash
 */
layout('layouts/app');
?>
<div class="max-w-md mx-auto">
    <div class="card">
        <h1 class="m-0">Forgot your password?</h1>
        <p class="text-brand-muted mt-2 mb-5">
            Enter the email on your account and we'll send a link to set
            a new password. Links expire in one hour.
        </p>

        <?php if ($flash !== null): ?>
            <div class="flash-err" role="alert"><?= e($flash) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e($base) ?>/password-reset" novalidate autocomplete="on" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="email" class="field-label">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required autofocus
                       inputmode="email" class="field">
            </div>

            <button type="submit" class="btn-primary w-full sm:w-auto">
                Send reset link
            </button>
        </form>

        <p class="text-sm text-brand-muted mt-6">
            <a href="<?= e($base) ?>/login" class="font-semibold">← Back to sign in</a>
        </p>
    </div>
</div>
