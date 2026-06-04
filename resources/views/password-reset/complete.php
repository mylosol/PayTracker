<?php
/** @var string $base */
layout('layouts/app');
?>
<div class="card">
    <h1>Password updated <span class="pill ok">done</span></h1>
    <p>
        Your new password is in effect. The reset link you just used
        is now consumed and can't be re-used.
    </p>
    <p>
        <a href="<?= e($base) ?>/login"
           style="display:inline-block;background:var(--accent);color:#fff;padding:.5rem 1.2rem;border-radius:6px;text-decoration:none;">
            Sign in &rarr;
        </a>
    </p>
</div>
