<?php
/** @var string $base */
layout('layouts/app');
?>
<div class="max-w-3xl mx-auto">
<div class="card prose-static">
    <h1 class="m-0">Tutorial</h1>
    <p class="muted">
        A walkthrough of submitting a load, reviewing your daily and
        weekly pay totals, and editing or back-dating an entry.
    </p>

    <h2>1. Set up your profile first</h2>
    <p>
        Visit <a href="<?= e($base) ?>/profile"><code>/profile</code></a> and set
        three values <em>before</em> entering any loads:
    </p>
    <ul>
        <li><strong>Hire date</strong> &mdash; drives your tenure band
            (the rate you're paid at). PayTracker assumes the junior
            (6&nbsp;month) band until you set this.</li>
        <li><strong>Default shift</strong> &mdash; Day or Night. Night
            shifts get a small per-load bonus.</li>
        <li><strong>Pay week starts on</strong> &mdash; the day your
            employer's pay week begins. Drives the dashboard's
            <strong>This Week</strong> totals.</li>
    </ul>
    <p class="muted">
        These values are snapshotted into every NEW load you submit, so
        changing them later only affects loads going forward unless you
        click <em>Refresh my pay</em> on the dashboard.
    </p>

    <h2>2. Add a load</h2>
    <p>
        From the dashboard, click <strong>+ Add load</strong>, or go
        directly to <a href="<?= e($base) ?>/loads/new"><code>/loads/new</code></a>.
    </p>
    <ul>
        <li><strong>FRTL #</strong> is required to save the load &mdash;
            it's your dispatch number and the system uses it to match
            loads to pay records. If you don't have it in hand, leave
            <em>Store Load Info</em> turned OFF: the load lives in the
            browser scratchpad and you can add the FRTL later by editing
            the entry.</li>
        <li><strong>Load date</strong> defaults to today; back-date if
            you're entering paperwork after the fact.</li>
        <li><strong>Load type</strong>: Loaded one-way or Round-trip.
            The form reveals <em>Begin empty miles</em> and
            <em>End Empty location</em> only when one-way is selected
            &mdash; round-trip loads don't have separate empty legs.</li>
        <li><strong>Out-of-route miles</strong>: leave at 0 unless a
            detour added more than 3 miles beyond the map distance.</li>
    </ul>
    <p class="muted">
        Mileage is looked up in our city-distances matrix first; on a
        miss we fall through to Google Maps and cache the result so the
        next load with the same pair stays local.
    </p>

    <h2>3. Review your pay</h2>
    <p>
        The <a href="<?= e($base) ?>/dashboard"><code>/dashboard</code></a> shows
        three things:
    </p>
    <ul>
        <li><strong>This Week</strong> &mdash; running totals across the
            current pay week.</li>
        <li><strong>Today</strong> &mdash; single-day totals for the
            viewed date (use the prev/next links to walk earlier days).</li>
        <li><strong>Loads</strong> &mdash; the per-load list with an
            expandable breakdown showing where each dollar came from.</li>
    </ul>

    <h2>4. Edit or delete a load</h2>
    <p>
        Each row on the dashboard has Edit and Delete actions. Editing
        recomputes pay against your <em>current</em> profile &mdash; useful
        if your tenure band has moved since the load was originally
        entered.
    </p>

    <h2>Video</h2>
    <p class="muted">
        A video walkthrough of the modern UI is in the works. The
        legacy tutorial is available below for reference, but the
        interface it shows is no longer current.
    </p>
    <div class="aspect-video rounded-lg overflow-hidden border border-brand-line mt-3">
        <iframe
            src="https://www.youtube.com/embed/Wy8e1iz9qZo"
            class="w-full h-full"
            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            title="PayTracker legacy tutorial"></iframe>
    </div>

    <p class="mt-6">
        <a href="<?= e($base) ?>/">← Back home</a>
    </p>
</div>
</div>
