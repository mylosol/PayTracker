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
<div class="card">
    <h1>Edit account #<?= (int) ($target['id'] ?? 0) ?></h1>
    <p class="muted">
        Editing <strong><?= e((string) ($target['user'] ?? '')) ?></strong>
        as <code><?= e((string) ($actor['user'] ?? '')) ?></code>.
        <?php if ($isSelf): ?>
            (This is your own account &mdash; saving here is the same as
            using <a href="<?= e($base) ?>/profile">/profile</a>.)
        <?php endif; ?>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#fee2e2;color:#991b1b;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e($base) ?>/admin/users/<?= (int) ($target['id'] ?? 0) ?>/edit"
          novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="username"><strong>Username</strong></label><br>
            <input id="username" name="username" type="text" required
                   value="<?= e((string) ($target['user'] ?? '')) ?>"
                   pattern="[A-Za-z0-9._\-]{3,32}" minlength="3" maxlength="32"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">
                3-32 characters &mdash; letters, digits, dot, underscore, dash.
                No spaces or <code>@</code>. Used to sign in.
            </small>
        </p>

        <p>
            <label for="email"><strong>Email</strong> <span class="muted">(optional)</span></label><br>
            <input id="email" name="email" type="email"
                   value="<?= e((string) ($target['email'] ?? '')) ?>"
                   maxlength="255"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">
                Where admin-initiated password-reset links land.
                Leave blank to disable outbound email for this account.
            </small>
        </p>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Save
            </button>
            &nbsp;<a href="<?= e($base) ?>/admin">Cancel</a>
        </p>
    </form>

    <p class="muted" style="margin-top:1rem;font-size:13px;">
        Role assignment, ban / unban, delete, and reset-password live on
        the <a href="<?= e($base) ?>/admin">admin list view</a>.
    </p>
</div>
