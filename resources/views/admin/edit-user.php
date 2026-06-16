<?php
/**
 * @var string                 $base
 * @var string                 $csrfToken
 * @var array<string,mixed>    $actor
 * @var array<string,mixed>    $target
 * @var ?string                $flash
 */
layout('layouts/app');
$isSelf = (int) ($actor['id'] ?? 0) === (int) ($target['id'] ?? 0);
?>
<div class="max-w-2xl mx-auto">
    <div class="card">
        <h1 class="m-0">Edit account #<?= (int) ($target['id'] ?? 0) ?></h1>
        <p class="text-brand-muted mt-2">
            Editing <strong><?= e((string) ($target['user'] ?? '')) ?></strong>
            as <code><?= e((string) ($actor['user'] ?? '')) ?></code>.
            <?php if ($isSelf): ?>
                (This is your own account — saving here is the same as
                using <a href="<?= e($base) ?>/profile">/profile</a>.)
            <?php endif; ?>
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/admin/users/<?= (int) ($target['id'] ?? 0) ?>/edit"
              novalidate autocomplete="off" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="username" class="field-label">Username</label>
                <input id="username" name="username" type="text" required
                       value="<?= e((string) ($target['user'] ?? '')) ?>"
                       pattern="[A-Za-z0-9._\-]{3,32}" minlength="3" maxlength="32"
                       class="field">
                <span class="field-hint">
                    3-32 characters — letters, digits, dot, underscore, dash.
                    No spaces or <code>@</code>. Used to sign in.
                </span>
            </div>

            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" required
                       value="<?= e((string) ($target['email'] ?? '')) ?>"
                       maxlength="255" class="field">
                <span class="field-hint">
                    Required. Where admin-issued password-reset links and
                    operational mail land.
                </span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Save</button>
                <a href="<?= e($base) ?>/admin" class="text-sm">Cancel</a>
            </div>
        </form>

        <p class="text-sm text-brand-muted mt-6">
            Role assignment, ban / unban, delete, and reset-password live on
            the <a href="<?= e($base) ?>/admin">admin list view</a>.
        </p>
    </div>
</div>
