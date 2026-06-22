<?php
/**
 * @var string $base
 * @var string $email
 *
 * Confirmation page rendered after a POST to /password-reset, REGARDLESS
 * of whether the supplied email matched a real account. The page never
 * confirms or denies that the address is on file — that's the
 * anti-enumeration guarantee.
 */
layout('layouts/app');
?>
<div class="max-w-md mx-auto">
    <div class="card">
        <h1 class="m-0">Check your email</h1>
        <p class="mt-4">
            If <strong><?= e($email) ?></strong> is associated with a PayTracker
            account, we've sent a password-reset link there. The link will
            work for one hour.
        </p>
        <p class="text-brand-muted mt-4 text-sm">
            Didn't receive it? Check your spam folder, then double-check the
            address — accounts are stored against the exact email you signed
            up with. Still stuck? Reach out via <a href="<?= e($base) ?>/contact">/contact</a>.
        </p>
        <p class="mt-6">
            <a href="<?= e($base) ?>/login" class="btn-primary">Back to sign in</a>
        </p>
    </div>
</div>
