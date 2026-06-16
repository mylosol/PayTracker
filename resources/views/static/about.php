<?php
/** @var string $base */
layout('layouts/app');
?>
<div class="max-w-3xl mx-auto">
<div class="card prose-static">
    <h1 class="m-0">About PayTracker</h1>

    <p class="mt-4">
        PayTracker is a per-driver pay-tracking tool for fuel-haul
        trucking: log a load, see what it pays, and know where your
        weekly total stands without waiting on the paper stub.
    </p>

    <p>
        The original PayTracker shipped in 2015 on PHP 5.6 and grew
        organically over the following years &mdash; cookies-as-state,
        per-driver tables, four pay-admin pages with copy-pasted forms,
        and a math formula that lived in two slightly different versions
        across the codebase. It served drivers reliably but had drifted
        far enough that a normal PHP-version upgrade broke it outright
        in 2019.
    </p>

    <p>
        This rebuild is a clean re-implementation on PHP 8.3 against
        the same MariaDB instance, written so that the legacy site and
        the modern site can run side-by-side until the modernized
        surface covers everything drivers need.
    </p>

    <h2>What's different</h2>
    <ul>
        <li><strong>Per-account login.</strong> The shared password is
            retired. Each driver has their own account and their own
            data is scoped to them server-side &mdash; not by URL or
            cookie.</li>
        <li><strong>Relational pay data.</strong> The 21 per-driver
            <code>loadsNN</code> tables and the two-table large-miles
            matrices are now <code>driver_loads</code> and
            <code>city_distances</code>. Same data, single source of
            truth, indexed for date-window queries.</li>
        <li><strong>Pay math in one place.</strong> The full per-load
            breakdown (base, empty, seniority, shift, weekend, split,
            extras) is computed by one service and stored alongside the
            load so the dashboard can show <em>why</em> a number came
            out the way it did.</li>
        <li><strong>Mileage cache.</strong> City distances are looked
            up in the local matrix first; on a miss we fall through to
            Google Maps and cache the result so the next load with the
            same pair stays local and free.</li>
        <li><strong>Safety first.</strong> CSRF on every POST, per-row
            driver scoping in every WHERE clause, deploy-driven
            migrations with explicit additive-only rules, and a
            preview channel that proves changes work end-to-end before
            production sees them.</li>
    </ul>

    <h2>Status</h2>
    <p>
        Currently in active development on the preview channel
        (<code>/preview/</code>). Production continues to run the
        legacy stack until parity is complete.
    </p>

    <p class="mt-8">
        <a href="<?= e($base) ?>/">← Back home</a>
    </p>
</div>
</div>
