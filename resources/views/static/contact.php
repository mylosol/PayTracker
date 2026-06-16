<?php
/** @var string $base */
layout('layouts/app');
?>
<div class="max-w-3xl mx-auto">
<div class="card prose-static">
    <h1 class="m-0">Contact</h1>

    <p>
        Found a bug, have a feature request, or need an account seeded?
        Reach out and we'll get back to you.
    </p>

    <p>
        <strong>Email:</strong>
        <a href="mailto:support@paytracker.xyz">support@paytracker.xyz</a>
    </p>

    <p class="muted">
        When reporting a bug, please include:
    </p>
    <ul class="muted">
        <li>Your driver handle (the email you sign in with).</li>
        <li>The page or URL where you saw the problem.</li>
        <li>What you did, what you expected to happen, and what
            actually happened.</li>
        <li>The date / FRTL of any specific load involved, if
            applicable.</li>
    </ul>

    <h2>Requesting access</h2>
    <p>
        New driver accounts are seeded by an administrator. Email
        the address above with your name and the carrier you drive
        for; an admin will reply with sign-in instructions.
    </p>

    <p class="mt-8">
        <a href="<?= e($base) ?>/">← Back home</a>
    </p>
</div>
</div>
