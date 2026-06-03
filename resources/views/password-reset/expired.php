<?php
/** @var string $base */
layout('layouts/app');
?>
<div class="card">
    <h1>Reset link no longer valid <span class="pill warn">410</span></h1>
    <p>
        This password reset link has expired, already been used, or
        was superseded by a newer one. Each link is good for one hour
        and one use.
    </p>
    <p>
        If you still need to sign in, ask an administrator to
        generate a new link &mdash; see <a href="<?= e($base) ?>/contact">/contact</a>.
    </p>
    <p>
        <a href="<?= e($base) ?>/login">&larr; Back to sign in</a>
    </p>
</div>
