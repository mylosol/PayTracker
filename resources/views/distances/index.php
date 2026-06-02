<?php
/**
 * @var string                                              $base
 * @var array{total_rows:int, unique_pairs:int, unique_cities:int, by_source: array<string,int>} $summary
 * @var list<array{from:string, to:string, miles:int, source:string}> $sample
 * @var string                                              $from
 * @var string                                              $to
 * @var list<array{miles:int, source:string}>|null          $lookup
 */
layout('layouts/app');
?>
<div class="card">
    <h1>City distances</h1>
    <p class="muted">
        Relational replacement for the legacy <code>largeMiles</code> /
        <code>pcola_largeMiles</code> column-per-city matrix tables.
        Read-only for now — writes land in a follow-up branch.
        <a href="<?= e($base) ?>/">&larr; Back</a>
    </p>
</div>

<div class="card">
    <h2>Backfill summary</h2>
    <ul>
        <li>Total rows: <code><?= e((string) $summary['total_rows']) ?></code></li>
        <li>Unique (from, to) pairs: <code><?= e((string) $summary['unique_pairs']) ?></code></li>
        <li>Distinct cities referenced: <code><?= e((string) $summary['unique_cities']) ?></code></li>
        <li>By source:
            <?php if ($summary['by_source'] === []): ?>
                <em>none yet</em>
            <?php else: ?>
                <ul>
                <?php foreach ($summary['by_source'] as $src => $n): ?>
                    <li><code><?= e($src) ?></code> &mdash; <code><?= e((string) $n) ?></code></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
    </ul>
    <?php if ($summary['total_rows'] === 0): ?>
        <p class="muted" style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:.6rem .8rem;">
            No rows found. The backfill migration may not have run yet — check
            <code>_migrations</code> on the server.
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Look up a pair</h2>
    <p class="muted">Enter exact city names as they appear in the sample table below.</p>
    <form method="get" action="<?= e($base) ?>/distances"
          style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;">
        <label>From<br>
            <input name="from" type="text" value="<?= e($from) ?>" autocomplete="off"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;min-width:200px;">
        </label>
        <label>To<br>
            <input name="to" type="text" value="<?= e($to) ?>" autocomplete="off"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;min-width:200px;">
        </label>
        <button type="submit"
                style="background:var(--accent);color:#fff;border:0;padding:.45rem 1.2rem;border-radius:6px;font:inherit;cursor:pointer;">
            Look up
        </button>
    </form>

    <?php if ($lookup !== null): ?>
        <div style="margin-top:1rem;">
            <?php if ($lookup === []): ?>
                <p class="muted">No recorded distance for <code><?= e($from) ?></code> &rarr; <code><?= e($to) ?></code>.</p>
            <?php else: ?>
                <p>Recorded distance for <code><?= e($from) ?></code> &rarr; <code><?= e($to) ?></code>:</p>
                <ul>
                    <?php foreach ($lookup as $row): ?>
                        <li><code><?= e((string) $row['miles']) ?></code> miles
                            <span class="muted">(source: <code><?= e($row['source']) ?></code>)</span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Sample (first <?= count($sample) ?> rows, alphabetical)</h2>
    <?php if ($sample === []): ?>
        <p class="muted">No rows to display.</p>
    <?php else: ?>
        <table style="border-collapse:collapse;font-size:14px;width:100%;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                    <th style="padding:.3rem .5rem;">From</th>
                    <th style="padding:.3rem .5rem;">To</th>
                    <th style="padding:.3rem .5rem;text-align:right;">Miles</th>
                    <th style="padding:.3rem .5rem;">Source</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sample as $row): ?>
                    <tr style="border-bottom:1px solid #f0f2f6;">
                        <td style="padding:.25rem .5rem;"><?= e((string) $row['from']) ?></td>
                        <td style="padding:.25rem .5rem;"><?= e((string) $row['to']) ?></td>
                        <td style="padding:.25rem .5rem;text-align:right;"><?= e((string) $row['miles']) ?></td>
                        <td style="padding:.25rem .5rem;"><code><?= e((string) $row['source']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
