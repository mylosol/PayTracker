<?php
/**
 * @var string                                              $base
 * @var string                                              $csrfToken
 * @var int                                                 $minLen
 * @var ?string                                             $flash
 * @var ?array{href:string,label:string}                    $flashLink
 * @var array{invite:string,username:string,email:string}   $old
 */
layout('layouts/app');
?>
<div class="max-w-xl mx-auto">
    <div class="card">
        <h1 class="m-0">Create your PayTracker account</h1>
        <p class="text-brand-muted mt-2">
            Registration is invite-only. If you don't have a code yet,
            ask an administrator to send you one.
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert">
            <div><?= e($flash) ?></div>
            <?php if (isset($flashLink) && $flashLink !== null): ?>
                <p class="mt-2 mb-0">
                    <a href="<?= e($flashLink['href']) ?>"
                       class="font-semibold text-rose-900 underline">
                        <?= e($flashLink['label']) ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/register" novalidate autocomplete="off" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="invite" class="field-label">Invite code</label>
                <input id="invite" name="invite" type="text" required maxlength="8" minlength="8"
                       value="<?= e($old['invite']) ?>"
                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                       class="field font-mono tracking-[0.15em] uppercase w-56">
                <span class="field-hint">
                    8 uppercase letters / digits. Case-insensitive — we
                    normalize before lookup.
                </span>
            </div>

            <div>
                <label for="username" class="field-label">Username</label>
                <input id="username" name="username" type="text" required
                       value="<?= e($old['username']) ?>" pattern="[A-Za-z0-9._\-]{3,32}"
                       minlength="3" maxlength="32" autocomplete="username"
                       class="field">
                <span class="field-hint">
                    3-32 characters — letters, digits, dot, underscore, dash.
                    No spaces or <code>@</code>. This is what you'll sign in with.
                </span>
            </div>

            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" required maxlength="255"
                       value="<?= e($old['email']) ?>" autocomplete="email"
                       class="field">
                <span class="field-hint">
                    Where password-reset links would land. We'll never
                    spam you; it's purely operational.
                </span>
            </div>

            <div>
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" required
                       minlength="<?= (int) $minLen ?>" autocomplete="new-password"
                       class="field">
                <span class="field-hint">At least <?= (int) $minLen ?> characters.</span>
            </div>

            <div>
                <label for="password_confirmation" class="field-label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       minlength="<?= (int) $minLen ?>" autocomplete="new-password"
                       class="field">
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Create account &amp; sign in</button>
                <a href="<?= e($base) ?>/login" class="text-sm">Already have an account? Sign in</a>
            </div>
        </form>
    </div>
</div>
