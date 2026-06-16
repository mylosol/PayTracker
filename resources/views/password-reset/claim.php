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
<div class="max-w-md mx-auto">
    <div class="card">
        <h1 class="m-0">Set a new password</h1>
        <p class="text-brand-muted mt-2">
            This reset link is single-use and expires one hour after the
            admin generated it. Pick a strong password — minimum
            <strong><?= (int) $minLen ?></strong> characters.
        </p>

        <?php if ($flash !== null): ?>
            <div class="flash-err mt-4" role="alert"><?= e($flash) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e($base) ?>/password-reset/<?= e($token) ?>"
              autocomplete="off" class="space-y-5 mt-6">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="password" class="field-label">New password</label>
                <input id="password" name="password" type="password"
                       minlength="<?= (int) $minLen ?>" required autofocus
                       autocomplete="new-password" class="field">
            </div>

            <div>
                <label for="password_confirmation" class="field-label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       minlength="<?= (int) $minLen ?>" required
                       autocomplete="new-password" class="field">
            </div>

            <button type="submit" class="btn-primary w-full sm:w-auto">Set password</button>
        </form>
    </div>
</div>
