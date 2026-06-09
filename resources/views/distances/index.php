<?php
/**
 * @var string                                              $base
 * @var string                                              $csrfToken
 * @var ?string                                             $flash
 * @var array{total_rows:int, unique_pairs:int, unique_cities:int, by_source: array<string,int>} $summary
 * @var list<array<string,mixed>>                           $cities
 * @var string                                              $from
 * @var string                                              $to
 * @var string                                              $q
 * @var int                                                 $page
 * @var int                                                 $perPage
 * @var list<array{from_id:int,to_id:int,from:string,to:string,miles:int,source:string,override_active:bool}> $rows
 * @var int                                                 $totalRows
 * @var list<array{miles:int, source:string}>|null          $lookup
 * @var array{from:string,to:string,miles:string}|null      $prefill
 * @var string                                              $adminSource
 */
layout('layouts/app');

$totalPages = max(1, (int) ceil($totalRows / max(1, $perPage)));
// All pagination + filter links land on #all-rows so the page
// scrolls straight to the results instead of starting at the top.
$buildPageUrl = static function (int $p) use ($base, $q): string {
    $qs = ['page' => $p];
    if ($q !== '') { $qs['q'] = $q; }
    return $base . '/distances?' . http_build_query($qs) . '#all-rows';
};
?>
<div class="card">
    <h1>City distances</h1>
    <p class="muted">
        Cached (from, to) pairs that power PayCalculator and the load form's
        mile lookups. Admin edits land as an <code>admin</code>-source row that
        sorts ahead of legacy / Google answers — deleting the override reverts
        the cache. Original legacy rows are never overwritten.
        <a href="<?= e($base) ?>/">&larr; Back</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#dcfce7;color:#166534;word-break:break-word;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2><?= $prefill !== null ? 'Override existing distance' : 'Add / override distance' ?></h2>
    <p class="muted">
        Saving inserts (or updates) a row with <code>source=admin</code>. That row wins
        lookups over any legacy / Google value for the same pair. Type
        city names exactly as they appear in the list — bare names without
        a state suffix were consolidated in 2026_06_09_001.
    </p>
    <form method="post" action="<?= e($base) ?>/distances"
          style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>From<br>
            <input name="from_name" type="text" required autocomplete="off"
                   list="distance-cities"
                   value="<?= e($prefill['from'] ?? '') ?>"
                   style="padding:.45rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;min-width:220px;">
        </label>
        <label>To<br>
            <input name="to_name" type="text" required autocomplete="off"
                   list="distance-cities"
                   value="<?= e($prefill['to'] ?? '') ?>"
                   style="padding:.45rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;min-width:220px;">
        </label>
        <label>Miles<br>
            <input name="miles" type="number" min="1" max="9999" step="1" required
                   value="<?= e($prefill['miles'] ?? '') ?>"
                   style="padding:.45rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:7rem;">
        </label>
        <button type="submit"
                style="background:var(--accent);color:#fff;border:0;padding:.5rem 1.2rem;border-radius:6px;font:inherit;cursor:pointer;">
            Save override
        </button>
        <?php if ($prefill !== null): ?>
            <a href="<?= e($base) ?>/distances" class="muted" style="align-self:center;text-decoration:none;">Cancel</a>
        <?php endif; ?>
    </form>
    <datalist id="distance-cities">
        <?php foreach ($cities as $c): ?>
            <option value="<?= e((string) ($c['city'] ?? '')) ?>"></option>
        <?php endforeach; ?>
    </datalist>
</div>

<div class="card">
    <h2>Look up a pair</h2>
    <p class="muted">Returns every source row recorded for the pair, source-ASC ordered.</p>
    <form method="get" action="<?= e($base) ?>/distances"
          style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;">
        <label>From<br>
            <input name="from" type="text" value="<?= e($from) ?>" autocomplete="off"
                   list="distance-cities"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;min-width:200px;">
        </label>
        <label>To<br>
            <input name="to" type="text" value="<?= e($to) ?>" autocomplete="off"
                   list="distance-cities"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;min-width:200px;">
        </label>
        <button type="submit"
                style="background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1.1rem;border-radius:6px;font:inherit;cursor:pointer;">
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

<div class="card" id="all-rows">
    <h2>All rows
        <span class="muted" style="font-size:14px;font-weight:normal;">
            (<?= (int) $totalRows ?> total, showing page <?= (int) $page ?> / <?= (int) $totalPages ?>)
        </span>
    </h2>

    <form method="get" action="<?= e($base) ?>/distances#all-rows" style="margin:0 0 1rem 0;">
        <label>Search city
            <input name="q" type="text" value="<?= e($q) ?>" autocomplete="off"
                   placeholder="matches From OR To"
                   style="padding:.4rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;min-width:240px;">
        </label>
        <button type="submit"
                style="background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1.1rem;border-radius:6px;font:inherit;cursor:pointer;">
            Filter
        </button>
        <?php if ($q !== ''): ?>
            &nbsp;<a href="<?= e($base) ?>/distances" class="muted">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <p class="muted">No rows match.</p>
    <?php else: ?>
        <table style="border-collapse:collapse;font-size:14px;width:100%;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                    <th style="padding:.3rem .5rem;">From</th>
                    <th style="padding:.3rem .5rem;">To</th>
                    <th style="padding:.3rem .5rem;text-align:right;">Miles</th>
                    <th style="padding:.3rem .5rem;">Source</th>
                    <th style="padding:.3rem .5rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $isAdmin   = $row['source'] === $adminSource;
                    $shadowed  = ! $isAdmin && $row['override_active'];
                    $editUrl   = $base . '/distances?'
                               . http_build_query(['edit_from' => $row['from'], 'edit_to' => $row['to']])
                               . '#override-form';
                    ?>
                    <tr style="border-bottom:1px solid #f0f2f6;<?= $shadowed ? 'opacity:.65;' : '' ?>">
                        <td style="padding:.25rem .5rem;"><?= e((string) $row['from']) ?></td>
                        <td style="padding:.25rem .5rem;"><?= e((string) $row['to']) ?></td>
                        <td style="padding:.25rem .5rem;text-align:right;"><?= e((string) $row['miles']) ?></td>
                        <td style="padding:.25rem .5rem;">
                            <code><?= e((string) $row['source']) ?></code>
                            <?php if ($isAdmin): ?>
                                <span class="pill ok">override</span>
                            <?php elseif ($shadowed): ?>
                                <span class="pill warn" title="An admin override exists for this pair; this row is dormant.">shadowed</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:.25rem .5rem;white-space:nowrap;">
                            <a href="<?= e($editUrl) ?>"
                               style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.2rem .55rem;border-radius:4px;text-decoration:none;font-size:12px;">
                                <?= $isAdmin ? 'Edit' : 'Override' ?>
                            </a>
                            <form method="post" action="<?= e($base) ?>/distances/delete"
                                  onsubmit="return confirm('Delete <?= e($row['from']) ?> → <?= e($row['to']) ?> (source=<?= e($row['source']) ?>, <?= (int) $row['miles'] ?> mi)?<?= $isAdmin ? ' Cache will revert to the next-best source.' : '' ?>');"
                                  style="display:inline;">
                                <input type="hidden" name="_csrf"   value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="from_id" value="<?= (int) $row['from_id'] ?>">
                                <input type="hidden" name="to_id"   value="<?= (int) $row['to_id'] ?>">
                                <input type="hidden" name="source"  value="<?= e((string) $row['source']) ?>">
                                <button type="submit"
                                        style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:.2rem .55rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <p style="margin-top:1rem;">
                <?php if ($page > 1): ?>
                    <a href="<?= e($buildPageUrl(1)) ?>">&laquo; First</a>
                    &nbsp;<a href="<?= e($buildPageUrl($page - 1)) ?>">&larr; Prev</a>
                <?php endif; ?>
                <span class="muted" style="margin:0 .6rem;">page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e($buildPageUrl($page + 1)) ?>">Next &rarr;</a>
                    &nbsp;<a href="<?= e($buildPageUrl($totalPages)) ?>">Last &raquo;</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<span id="override-form"></span>

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
</div>
