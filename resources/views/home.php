<?php
/**
 * @var string                       $appName
 * @var string                       $env
 * @var string                       $version
 * @var array<string,mixed>|null     $account
 * @var string                       $csrfToken
 * @var string                       $base
 */
use PayTracker\Models\Account;

layout('layouts/app');
$isProduction  = $env === 'production';
$authenticated = is_array($account);
$isAdmin       = $authenticated && Account::hasRole($account, Account::ROLE_ADMIN);
$isSuperAdmin  = $authenticated && Account::hasRole($account, Account::ROLE_SUPER_ADMIN);
?>

<?php if ($authenticated): ?>
    <section class="card">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="m-0">Welcome, <?= e((string) $account['user']) ?>
                    <span class="pill ok align-middle ml-1 text-xs">signed in</span>
                </h1>
                <p class="text-brand-muted mt-2 flex flex-wrap items-center gap-2">
                    <span class="pill muted">role: <?= e((string) $account['role']) ?></span>
                    <?php if (! empty($account['last_login_at'])): ?>
                        <span class="text-sm">Last login <code><?= e((string) $account['last_login_at']) ?> UTC</code></span>
                    <?php endif; ?>
                </p>
            </div>
            <span class="pill <?= $isProduction ? 'ok' : 'warn' ?>"><?= e($env) ?></span>
        </div>
    </section>

    <section class="card">
        <h2 class="m-0">Jump in</h2>
        <p class="text-brand-muted mt-2 mb-5">Pick where you're headed today.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="<?= e($base) ?>/dashboard" class="btn-primary w-full">
                My pay (today)
            </a>
            <a href="<?= e($base) ?>/loads/new" class="btn-secondary w-full">
                Add a load
            </a>
            <a href="<?= e($base) ?>/reconcile" class="btn-secondary w-full">
                Reconcile pay
            </a>
            <a href="<?= e($base) ?>/profile" class="btn-secondary w-full">
                Driver profile
            </a>
        </div>
    </section>

    <?php if ($isAdmin): ?>
        <section class="card">
            <h2 class="m-0">Admin tools</h2>
            <p class="text-brand-muted mt-2 mb-5">Available to your <code><?= e((string) $account['role']) ?></code> role.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="<?= e($base) ?>/admin" class="btn-secondary w-full">Admin panel</a>
                <a href="<?= e($base) ?>/distances" class="btn-secondary w-full">City distances</a>
                <a href="<?= e($base) ?>/locations" class="btn-secondary w-full">Manage locations</a>
                <a href="<?= e($base) ?>/pay-admin" class="btn-secondary w-full">Pay-rate admin</a>
                <?php if ($isSuperAdmin): ?>
                    <a href="<?= e($base) ?>/loads" class="btn-secondary w-full">Driver loads</a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2 class="m-0">Help &amp; info</h2>
        <ul class="mt-3 space-y-2 list-none p-0">
            <li><a href="<?= e($base) ?>/tutorial" class="font-medium">Tutorial</a>
                <span class="text-brand-muted">— walkthrough of profile setup, adding loads, and reading the dashboard.</span></li>
            <li><a href="<?= e($base) ?>/faq" class="font-medium">FAQ</a>
                <span class="text-brand-muted">— short version of "how does pay actually work?" plus common questions.</span></li>
            <li><a href="<?= e($base) ?>/about" class="font-medium">About</a>
                <span class="text-brand-muted">— what this rebuild is and how it differs from the legacy site.</span></li>
            <li><a href="<?= e($base) ?>/contact" class="font-medium">Contact</a>
                <span class="text-brand-muted">— email for bug reports, feature requests, and access seeding.</span></li>
        </ul>
    </section>

<?php else: ?>
    <section class="card">
        <h1 class="m-0">PayTracker
            <span class="pill <?= $isProduction ? 'ok' : 'warn' ?> ml-2 align-middle text-base">
                <?= e($env) ?>
            </span>
        </h1>
        <p class="text-brand-muted text-lg mt-3">
            Pay tracking for fleet drivers.
            <?php if (! $isProduction): ?>
                You're on the <strong>isolated preview channel</strong> — production data is untouched.
            <?php endif; ?>
        </p>
    </section>

    <section class="card">
        <h2 class="m-0">Sign in to continue</h2>
        <p class="text-brand-muted mt-2">
            Modern PayTracker requires a per-account login — the shared
            password is retired. If you don't have an account yet, ask an
            administrator for an invite code.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 mt-5">
            <a href="<?= e($base) ?>/login" class="btn-primary">Sign in</a>
            <a href="<?= e($base) ?>/register" class="btn-secondary">Have an invite code?</a>
        </div>
    </section>

    <section class="card">
        <h2 class="m-0">What is this?</h2>
        <p class="text-brand-muted mt-2 mb-0">
            A modern rewrite of the long-running PayTracker app — a tool that lets
            drivers log their loads, see their pay add up in real time, and reconcile
            against actual payroll. Same data, friendlier interface.
        </p>
    </section>
<?php endif; ?>
