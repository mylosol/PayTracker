<?php
/** @var string $base */
layout('layouts/app');
?>
<div class="max-w-md mx-auto">
    <div class="card">
        <h1 class="m-0">Password updated
            <span class="pill ok align-middle ml-1 text-xs">done</span>
        </h1>
        <p class="text-brand-muted mt-2">
            Your new password is in effect. The reset link you just used
            is now consumed and can't be re-used.
        </p>
        <div class="mt-5">
            <a href="<?= e($base) ?>/login" class="btn-primary">Sign in →</a>
        </div>
    </div>
</div>
