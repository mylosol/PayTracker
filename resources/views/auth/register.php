<?php
/**
 * @var string                                              $base
 * @var string                                              $csrfToken
 * @var int                                                 $minLen
 * @var ?string                                             $flash
 * @var array{invite:string,username:string,email:string}   $old
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Create your PayTracker account</h1>
    <p class="muted">
        Registration is invite-only. If you don't have a code yet,
        ask an administrator to send you one.
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#fee2e2;color:#991b1b;word-break:break-word;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e($base) ?>/register" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="invite"><strong>Invite code</strong></label><br>
            <input id="invite" name="invite" type="text" required maxlength="8" minlength="8"
                   value="<?= e($old['invite']) ?>"
                   autocomplete="off" autocapitalize="characters" spellcheck="false"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:14rem;letter-spacing:.15em;font-family:ui-monospace,monospace;">
            <small class="muted">
                8 uppercase letters / digits. Case-insensitive — we
                normalize before lookup.
            </small>
        </p>

        <p>
            <label for="username"><strong>Username</strong></label><br>
            <input id="username" name="username" type="text" required
                   value="<?= e($old['username']) ?>" pattern="[A-Za-z0-9._\-]{3,32}"
                   minlength="3" maxlength="32" autocomplete="username"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">
                3-32 characters — letters, digits, dot, underscore, dash.
                No spaces or <code>@</code>. This is what you'll sign in with.
            </small>
        </p>

        <p>
            <label for="email"><strong>Email</strong></label><br>
            <input id="email" name="email" type="email" required maxlength="255"
                   value="<?= e($old['email']) ?>" autocomplete="email"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">
                Where password-reset links would land. We'll never
                spam you; it's purely operational.
            </small>
        </p>

        <p>
            <label for="password"><strong>Password</strong></label><br>
            <input id="password" name="password" type="password" required minlength="<?= (int) $minLen ?>"
                   autocomplete="new-password"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">At least <?= (int) $minLen ?> characters.</small>
        </p>

        <p>
            <label for="password_confirmation"><strong>Confirm password</strong></label><br>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="<?= (int) $minLen ?>"
                   autocomplete="new-password"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
        </p>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Create account &amp; sign in
            </button>
            &nbsp;<a href="<?= e($base) ?>/login">Already have an account? Sign in</a>
        </p>
    </form>
</div>
