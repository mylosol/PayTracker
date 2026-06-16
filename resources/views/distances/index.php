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
$buildPageUrl = static function (int $p) use ($base, $q): string {
    $qs = ['page' => $p];
    if ($q !== '') { $qs['q'] = $q; }
    return $base . '/distances?' . http_build_query($qs) . '#all-rows';
};
?>
<div class="card">
    <h1 class="m-0">City distances</h1>
    <p class="text-brand-muted mt-2">
        Cached (from, to) pairs that power PayCalculator and the load form's
        mile lookups. Admin edits land as an <code>admin</code>-source row that
        sorts ahead of legacy / Google answers — deleting the override reverts
        the cache. Original legacy rows are never overwritten.
    </p>
    <p class="text-sm mt-4"><a href="<?= e($base) ?>/">← Back</a></p>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-ok break-words" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="m-0"><?= $prefill !== null ? 'Override existing distance' : 'Add / override distance' ?></h2>
    <p class="text-brand-muted mt-2 mb-4">
        Saving inserts (or updates) a row with <code>source=admin</code>. That row wins
        lookups over any legacy / Google value for the same pair. Type
        city names exactly as they appear in the list.
    </p>
    <form method="post" action="<?= e($base) ?>/distances"
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 items-end gap-3">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <div>
            <label for="from_name" class="field-label">From</label>
            <input id="from_name" name="from_name" type="text" required autocomplete="off"
                   list="distance-cities"
                   value="<?= e($prefill['from'] ?? '') ?>" class="field">
        </div>
        <div>
            <label for="to_name" class="field-label">To</label>
            <input id="to_name" name="to_name" type="text" required autocomplete="off"
                   list="distance-cities"
                   value="<?= e($prefill['to'] ?? '') ?>" class="field">
        </div>
        <div>
            <label for="miles" class="field-label">Miles</label>
            <input id="miles" name="miles" type="number" min="1" max="9999" step="1" required
                   value="<?= e($prefill['miles'] ?? '') ?>" class="field">
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary btn-sm">Save override</button>
            <?php if ($prefill !== null): ?>
                <a href="<?= e($base) ?>/distances" class="text-sm">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
    <datalist id="distance-cities">
        <?php foreach ($cities as $c): ?>
            <option value="<?= e((string) ($c['city'] ?? '')) ?>"></option>
        <?php endforeach; ?>
    </datalist>
</div>

<div class="card">
    <h2 class="m-0">Look up a pair</h2>
    <p class="text-brand-muted mt-2 mb-4">Returns every source row recorded for the pair, in priority order.</p>
    <form method="get" action="<?= e($base) ?>/distances"
          class="grid grid-cols-1 sm:grid-cols-3 items-end gap-3">
        <div>
            <label for="lookup_from" class="field-label">From</label>
            <input id="lookup_from" name="from" type="text" value="<?= e($from) ?>"
                   autocomplete="off" list="distance-cities" class="field">
        </div>
        <div>
            <label for="lookup_to" class="field-label">To</label>
            <input id="lookup_to" name="to" type="text" value="<?= e($to) ?>"
                   autocomplete="off" list="distance-cities" class="field">
        </div>
        <button type="submit" class="btn-secondary btn-sm">Look up</button>
    </form>

    <?php if ($lookup !== null): ?>
        <div class="mt-4">
            <?php if ($lookup === []): ?>
                <p class="text-brand-muted m-0">No recorded distance for <code><?= e($from) ?></code> → <code><?= e($to) ?></code>.</p>
            <?php else: ?>
                <p class="m-0">Recorded distance for <code><?= e($from) ?></code> → <code><?= e($to) ?></code>:</p>
                <ul class="mt-2 space-y-1 list-disc list-inside">
                    <?php foreach ($lookup as $row): ?>
                        <li><code><?= e((string) $row['miles']) ?></code> miles
                            <span class="text-brand-muted">(source: <code><?= e($row['source']) ?></code>)</span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card" id="all-rows">
    <h2 class="m-0">All rows
        <span class="block text-sm font-normal text-brand-muted mt-1">
            <?= (int) $totalRows ?> total, showing page <?= (int) $page ?> / <?= (int) $totalPages ?>
        </span>
    </h2>

    <form method="get" action="<?= e($base) ?>/distances#all-rows"
          class="flex flex-wrap items-end gap-3 mt-4 mb-4">
        <div class="flex-1 min-w-[200px]">
            <label for="search-city" class="field-label">Search city</label>
            <input id="search-city" name="q" type="text" value="<?= e($q) ?>" autocomplete="off"
                   placeholder="matches From OR To" class="field">
        </div>
        <button type="submit" class="btn-secondary btn-sm">Filter</button>
        <?php if ($q !== ''): ?>
            <a href="<?= e($base) ?>/distances" class="text-sm">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <p class="text-brand-muted m-0">No rows match.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th class="text-right">Miles</th>
                        <th>Source</th>
                        <th>Actions</th>
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
                        <tr class="<?= $shadowed ? 'opacity-60' : '' ?>">
                            <td><?= e((string) $row['from']) ?></td>
                            <td><?= e((string) $row['to']) ?></td>
                            <td class="text-right"><?= e((string) $row['miles']) ?></td>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <code><?= e((string) $row['source']) ?></code>
                                    <?php if ($isAdmin): ?>
                                        <span class="pill-ok">override</span>
                                    <?php elseif ($shadowed): ?>
                                        <span class="pill-warn" title="An admin override exists for this pair; this row is dormant.">shadowed</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="<?= e($editUrl) ?>" class="btn-secondary btn-sm">
                                        <?= $isAdmin ? 'Edit' : 'Override' ?>
                                    </a>
                                    <form method="post" action="<?= e($base) ?>/distances/delete"
                                          onsubmit="return confirm('Delete <?= e($row['from']) ?> → <?= e($row['to']) ?> (source=<?= e($row['source']) ?>, <?= (int) $row['miles'] ?> mi)?<?= $isAdmin ? ' Cache will revert to the next-best source.' : '' ?>');"
                                          class="inline">
                                        <input type="hidden" name="_csrf"   value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="from_id" value="<?= (int) $row['from_id'] ?>">
                                        <input type="hidden" name="to_id"   value="<?= (int) $row['to_id'] ?>">
                                        <input type="hidden" name="source"  value="<?= e((string) $row['source']) ?>">
                                        <button type="submit" class="btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <p class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                <?php if ($page > 1): ?>
                    <a href="<?= e($buildPageUrl(1)) ?>">« First</a>
                    <a href="<?= e($buildPageUrl($page - 1)) ?>">← Prev</a>
                <?php endif; ?>
                <span class="text-brand-muted">page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= e($buildPageUrl($page + 1)) ?>">Next →</a>
                    <a href="<?= e($buildPageUrl($totalPages)) ?>">Last »</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<span id="override-form"></span>

<div class="card">
    <h2 class="m-0">Backfill summary</h2>
    <ul class="mt-3 space-y-1 list-none p-0">
        <li>Total rows: <code><?= e((string) $summary['total_rows']) ?></code></li>
        <li>Unique (from, to) pairs: <code><?= e((string) $summary['unique_pairs']) ?></code></li>
        <li>Distinct cities referenced: <code><?= e((string) $summary['unique_cities']) ?></code></li>
        <li>By source:
            <?php if ($summary['by_source'] === []): ?>
                <em>none yet</em>
            <?php else: ?>
                <ul class="list-disc list-inside ml-4 mt-1">
                <?php foreach ($summary['by_source'] as $src => $n): ?>
                    <li><code><?= e($src) ?></code> — <code><?= e((string) $n) ?></code></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
    </ul>
</div>
