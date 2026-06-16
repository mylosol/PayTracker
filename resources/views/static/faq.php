<?php
/** @var string $base */
layout('layouts/app');
?>
<div class="max-w-3xl mx-auto">
<div class="card prose-static">
    <h1 class="m-0">Frequently asked questions</h1>
    <p class="muted">
        If your answer isn't here, head to
        <a href="<?= e($base) ?>/contact">Contact</a> and let us know &mdash;
        the list grows as questions come in.
    </p>

    <h2>How is my pay calculated?</h2>
    <p>
        Each load's pay is the sum of: <em>load pay</em> (miles &times; the
        rate band for your tenure), <em>empty pay</em> (any begin-empty
        and end-empty miles at the empty rate), an optional <em>night
        shift bonus</em>, a <em>seniority bonus</em> tied to your hire
        date, <em>weekend</em>/<em>split</em> bonuses where they apply,
        plus any <em>demurrage</em>, <em>breakdown</em>, or
        <em>extra pay</em> you entered.
    </p>
    <p>
        Click the breakdown panel on any load row in the dashboard to
        see exactly which components paid out, including the rate per
        mile used.
    </p>

    <h2>Why don't I see the old "OP" / Old Pay column anymore?</h2>
    <p>
        It was always zero in modern data &mdash; a legacy column from
        the original pay scheme that never got rewritten when rates
        moved to per-tier tables. We removed it from the UI so the
        totals you see match what hits your paycheck.
    </p>

    <h2>I set my hire date but my old loads still show the wrong band.</h2>
    <p>
        Pay calculations are <em>snapshotted</em> into each load at the
        moment it's submitted, so historical loads keep their original
        math even after you update your profile. To recompute history
        against your current profile, click <strong>Refresh my pay</strong>
        on the dashboard. The recompute is scoped to the last 30 days
        from the date you're viewing.
    </p>

    <h2>Can I back-date a load?</h2>
    <p>
        Yes. The load date field defaults to today but accepts any
        past date. Future dates are rejected &mdash; they'd silently
        drop off "today" until that day arrived.
    </p>

    <h2>What's the difference between Begin empty and End Empty?</h2>
    <p>
        Both appear only on <em>Loaded one-way</em> loads:
    </p>
    <ul>
        <li><strong>Begin empty miles</strong> &mdash; miles driven
            empty <em>before</em> pick-up (e.g. home &rarr; terminal).</li>
        <li><strong>End Empty location</strong> &mdash; where you
            ended after the delivery (typically the terminal you
            returned to). PayTracker resolves the miles automatically.</li>
    </ul>
    <p>
        Both pay at the empty-miles rate. Round-trip loads don't have
        separate empty legs &mdash; the return is implicit in the
        round-trip rate table.</p>

    <h2>When do "out-of-route miles" apply?</h2>
    <p>
        Only when a real-world detour added more than 3 miles beyond the
        map distance (a construction reroute, a flagged road closure,
        etc.). Type the <em>actual</em> miles you drove and PayTracker
        pays the higher number; otherwise the map distance pays.
    </p>

    <h2>My pay-week shows the wrong start day.</h2>
    <p>
        Visit <a href="<?= e($base) ?>/profile">/profile</a> and change
        <strong>Pay week starts on</strong>. The default is Sunday;
        Monday is common at carriers that pay on a calendar week.
    </p>

    <h2>I don't have an account.</h2>
    <p>
        Accounts are seeded by an administrator. Use
        <a href="<?= e($base) ?>/contact">Contact</a> to request access.
    </p>

    <p class="mt-8">
        <a href="<?= e($base) ?>/">← Back home</a>
    </p>
</div>
</div>
