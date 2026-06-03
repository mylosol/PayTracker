<?php
/**
 * @var string $base
 * @var string $requiredRole  e.g. 'admin' or 'super_admin'
 * @var string $actorRole     e.g. 'user' or 'anonymous'
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Access denied <span class="pill err">403</span></h1>
    <p>
        This area is restricted to
        <strong><?= e(ucwords(str_replace('_', ' ', $requiredRole))) ?></strong>
        accounts or higher. Your account role is
        <code><?= e($actorRole) ?></code>.
    </p>
    <p class="muted">
        If you believe you should have access, contact a Super Admin
        at <a href="<?= e($base) ?>/contact">/contact</a>.
    </p>
    <p>
        <a href="<?= e($base) ?>/dashboard"
           style="display:inline-block;background:var(--accent);color:#fff;padding:.5rem 1.2rem;border-radius:6px;text-decoration:none;">
            Back to dashboard &rarr;
        </a>
    </p>
</div>
