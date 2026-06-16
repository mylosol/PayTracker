<?php
/**
 * @var string $base
 * @var array{total_rows:int, drivers_with_loads:int, oldest_date:?string, newest_date:?string} $summary
 * @var list<array{driver_id:int, user:?string, n:int}> $perDriver
 * @var list<array{driver_id:int, user:?string, frtl:int, date:string, np:string, op:string}> $recent
 * @var int $driverFilter
 * @var list<array<string,mixed>> $driverRows
 * @var string|null $flash
 */
layout('layouts/app');
?>
<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="m-0">Driver loads</h1>
            <p class="text-brand-muted mt-2">
                Cross-driver survey of the <code>driver_loads</code> table.
                Writes go through the modern entry form; reads here cover
                both backfilled history and new rows.
            </p>
        </div>
        <a href="<?= e($base) ?>/loads/new" class="btn-primary">+ Add load</a>
    </div>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-ok" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="m-0">Loads per driver (top 50)</h2>
    <?php if ($perDriver === []): ?>
        <p class="text-brand-muted mt-3 mb-0">None.</p>
    <?php else: ?>
        <div class="table-wrap mt-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Driver id</th>
                        <th>User</th>
                        <th class="text-right">Loads</th>
                        <th>Spot-check</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($perDriver as $row): ?>
                        <tr>
                            <td><code><?= e((string) $row['driver_id']) ?></code></td>
                            <td>
                                <?php if ($row['user'] === null): ?>
                                    <em class="text-brand-muted">(orphan)</em>
                                <?php else: ?>
                                    <?= e((string) $row['user']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><code><?= e((string) $row['n']) ?></code></td>
                            <td>
                                <a href="<?= e($base) ?>/loads?driver_id=<?= (int) $row['driver_id'] ?>">view recent →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($driverFilter > 0): ?>
    <div class="card">
        <h2 class="m-0">Recent loads for driver <code><?= (int) $driverFilter ?></code></h2>
        <?php if ($driverRows === []): ?>
            <p class="text-brand-muted mt-3 mb-0">No rows for that driver_id.</p>
        <?php else: ?>
            <div class="table-wrap mt-4">
                <table class="data-table text-[13px]">
                    <thead>
                        <tr>
                            <th>FRTL</th>
                            <th>Date</th>
                            <th>Variables</th>
                            <th>Load info</th>
                            <th>Paid flags</th>
                            <th class="text-right">Net Pay</th>
                            <th class="text-right">Extras</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($driverRows as $row): ?>
                            <tr>
                                <td><code><?= e((string) $row['frtl']) ?></code></td>
                                <td><?= e((string) $row['date']) ?></td>
                                <td class="font-mono text-[11px]"><?= e((string) $row['variables']) ?></td>
                                <td class="font-mono text-[11px]"><?= e((string) $row['loadinfo']) ?></td>
                                <td class="font-mono text-[11px]"><?= e((string) $row['paid']) ?></td>
                                <td class="text-right"><?= e((string) $row['np']) ?></td>
                                <td class="text-right"><?= e((string) $row['op']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2 class="m-0">Recent loads across all drivers (sample of 25)</h2>
    <?php if ($recent === []): ?>
        <p class="text-brand-muted mt-3 mb-0">None.</p>
    <?php else: ?>
        <div class="table-wrap mt-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>FRTL</th>
                        <th>Date</th>
                        <th class="text-right">NP</th>
                        <th class="text-right">OP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td>
                                <code><?= e((string) $row['driver_id']) ?></code>
                                <?php if ($row['user'] !== null): ?>
                                    <span class="text-brand-muted">· <?= e((string) $row['user']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e((string) $row['frtl']) ?></code></td>
                            <td><?= e((string) $row['date']) ?></td>
                            <td class="text-right"><?= e((string) $row['np']) ?></td>
                            <td class="text-right"><?= e((string) $row['op']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="m-0">Backfill summary</h2>
    <ul class="mt-3 space-y-1 list-none p-0">
        <li>Total rows: <code><?= e((string) $summary['total_rows']) ?></code></li>
        <li>Drivers with at least one load: <code><?= e((string) $summary['drivers_with_loads']) ?></code></li>
        <li>Date range:
            <code><?= e((string) ($summary['oldest_date'] ?? 'n/a')) ?></code>
            to
            <code><?= e((string) ($summary['newest_date'] ?? 'n/a')) ?></code>
        </li>
    </ul>
    <?php if ($summary['total_rows'] === 0): ?>
        <div class="flash-err mt-4">
            No rows found. The backfill migration may not have run yet —
            check <code>_migrations</code> on the server.
        </div>
    <?php endif; ?>
</div>
