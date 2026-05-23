<?php
/**
 * @var string $php
 * @var string $env
 * @var string $time
 * @var array{ok: bool, error?: string} $database
 */
layout('layouts/app');
$dbOk = $database['ok'] === true;
?>
<div class="card">
    <h1>Health</h1>
    <p>PHP version: <code><?= e($php) ?></code></p>
    <p>Environment: <code><?= e($env) ?></code></p>
    <p>Server time (UTC): <code><?= e($time) ?></code></p>
    <p>
        Database:
        <span class="pill <?= $dbOk ? 'ok' : 'err' ?>"><?= $dbOk ? 'reachable' : 'unavailable' ?></span>
        <?php if (! $dbOk && isset($database['error'])): ?>
            <br><span class="muted"><?= e($database['error']) ?></span>
        <?php endif; ?>
    </p>
</div>
