<?php
/** @var string $base */
layout('layouts/app');
?>
<div class="max-w-md mx-auto">
    <div class="card">
        <h1 class="m-0">Reset link no longer valid
            <span class="pill-warn align-middle ml-1 text-xs">410</span>
        </h1>
        <p class="text-brand-muted mt-2">
            This password reset link has expired, already been used, or
            was superseded by a newer one. Each link is good for one hour
            and one use.
        </p>
        <p class="text-brand-muted mt-2">
            If you still need to sign in, ask an administrator to
            generate a new link — see <a href="<?= e($base) ?>/contact">/contact</a>.
        </p>
        <div class="mt-5">
            <a href="<?= e($base) ?>/login" class="btn-secondary">← Back to sign in</a>
        </div>
    </div>
</div>
