<?php
/**
 * @var string $appName
 * @var string $env
 * @var string $version
 */
layout('layouts/app');
$isProduction = $env === 'production';
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

<div class="card">
    <h2>Where to go next</h2>
    <ul>
        <li><a href="/health">/health</a> &mdash; runtime, environment and database probe.</li>
        <li><a href="/health.json">/health.json</a> &mdash; same probe, machine-readable.</li>
    </ul>
    <p class="muted">
        QA testers: the browser-based walkthrough lives in
        <code>docs/qa/test_plan.md</code> in the repository.
    </p>
</div>
