<?php
/**
 * @var string $base
 * @var string $email
 */
layout('layouts/app');
?>
<div class="max-w-xl mx-auto">
    <div class="card">
        <h1 class="m-0">Request sent</h1>
        <p class="text-brand-muted mt-3">
            Thanks — an admin has been notified. If everything checks
            out we'll email an invite code to
            <strong><?= e($email) ?></strong>
            within a business day or two.
        </p>
        <p class="text-brand-muted mt-3 mb-0">
            When your code arrives, use it on the
            <a href="<?= e($base) ?>/register" class="font-semibold">registration page</a>.
        </p>
    </div>

    <div class="card">
        <p class="m-0 text-sm"><a href="<?= e($base) ?>/">← Back home</a></p>
    </div>
</div>
