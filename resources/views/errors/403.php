<?php
/**
 * @var string $base
 * @var string $requiredRole  e.g. 'admin' or 'super_admin'
 * @var string $actorRole     e.g. 'user' or 'anonymous'
 */
layout('layouts/app');
?>
<div class="max-w-md mx-auto">
    <div class="card prose-static">
        <h1 class="m-0">Access denied
            <span class="pill err align-middle ml-1 text-xs">403</span>
        </h1>
        <p>
            This area is restricted to
            <strong><?= e(ucwords(str_replace('_', ' ', $requiredRole))) ?></strong>
            accounts or higher. Your account role is
            <code><?= e($actorRole) ?></code>.
        </p>
        <p class="text-brand-muted">
            If you believe you should have access, contact a Super Admin
            at <a href="<?= e($base) ?>/contact">/contact</a>.
        </p>
        <p class="mt-6">
            <a href="<?= e($base) ?>/dashboard" class="btn-primary inline-block">
                Back to dashboard →
            </a>
        </p>
    </div>
</div>
