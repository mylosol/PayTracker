<?php
/**
 * @var string                       $appName
 * @var string                       $env
 * @var string                       $version
 * @var array<string,mixed>|null     $account
 * @var string                       $csrfToken
 * @var string                       $base
 */
layout('layouts/app');
$isProduction  = $env === 'production';
$authenticated = is_array($account);
?>
<div class="card">
    <h1><?= e($appName) ?> <span class="pill <?= $isProduction ? 'ok' : 'warn' ?>"><?= e($env) ?></span></h1>
    <p class="muted">
        You are viewing the modernized PayTracker stack
        <?php if (! $isProduction): ?>
            on an <strong>isolated preview channel</strong>. Production data is untouched.
        <?php else: ?>
            in production.
        <?php endif; ?>
    </p>
    <p>Build: <code><?= e($version) ?></code></p>
</div>

<?php if ($authenticated): ?>
    <div class="card">
        <h2>Welcome, <?= e((string) $account['user']) ?> <span class="pill ok">signed in</span></h2>
        <p class="muted">
            Role: <code><?= e((string) $account['role']) ?></code>
            <?php if (! empty($account['last_login_at'])): ?>
                &middot; Last login: <code><?= e((string) $account['last_login_at']) ?> UTC</code>
            <?php endif; ?>
        </p>
        <p>The modernized dashboard surfaces will land here as feature
            branches port them off the legacy app.</p>

        <p>
            <a href="<?= e($base) ?>/dashboard"
               style="display:inline-block;background:var(--accent);color:#fff;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
                My pay (today) &rarr;
            </a>
            &nbsp;
            <a href="<?= e($base) ?>/locations"
               style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
                Manage locations &rarr;
            </a>
            &nbsp;
            <a href="<?= e($base) ?>/distances"
               style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
                City distances &rarr;
            </a>
            &nbsp;
            <a href="<?= e($base) ?>/loads"
               style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
                Driver loads &rarr;
            </a>
            &nbsp;
            <a href="<?= e($base) ?>/pay-admin"
               style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
                Pay-rate admin &rarr;
            </a>
        </p>

        <form method="post" action="<?= e($base) ?>/logout" style="margin-top:1rem;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit"
                    style="background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1rem;border-radius:6px;font:inherit;cursor:pointer;">
                Sign out
            </button>
        </form>
    </div>
<?php else: ?>
    <div class="card">
        <h2>Sign in to continue</h2>
        <p class="muted">
            Modern PayTracker requires a per-account login — the shared
            password is retired. If you don't have an account on this
            channel yet, an administrator can seed one for you.
        </p>
        <p>
            <a href="<?= e($base) ?>/login"
               style="display:inline-block;background:var(--accent);color:#fff;padding:.5rem 1.2rem;border-radius:6px;text-decoration:none;">
                Sign in &rarr;
            </a>
        </p>
    </div>
<?php endif; ?>

<?php if ($authenticated): ?>
    <div class="card">
        <h2>Help &amp; info</h2>
        <ul>
            <li><a href="<?= e($base) ?>/tutorial">Tutorial</a> &mdash;
                walkthrough of profile setup, adding loads, and reading
                the dashboard.</li>
            <li><a href="<?= e($base) ?>/faq">FAQ</a> &mdash; the
                short version of "how does pay actually work?" plus the
                common driver questions.</li>
            <li><a href="<?= e($base) ?>/about">About</a> &mdash; what
                this rebuild is and how it differs from the legacy site.</li>
            <li><a href="<?= e($base) ?>/contact">Contact</a> &mdash;
                email for bug reports, feature requests, and access
                seeding.</li>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Where to go next</h2>
    <ul>
        <li><a href="<?= e($base) ?>/health">/health</a> &mdash; runtime, environment and database probe.</li>
        <li><a href="<?= e($base) ?>/health.json">/health.json</a> &mdash; same probe, machine-readable.</li>
    </ul>
    <p class="muted">
        QA testers: the browser-based walkthrough lives in
        <code>docs/qa/test_plan.md</code> in the repository.
    </p>
</div>
