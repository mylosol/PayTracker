<?php
/**
 * @var string $base
 * @var array{total_rows:int, drivers_with_loads:int, oldest_date:?string, newest_date:?string} $summary
 * @var list<array{driver_id:int, user:?string, n:int}> $perDriver
 * @var list<array{driver_id:int, user:?string, frtl:int, date:string, np:string, op:string}> $recent
 * @var int $driverFilter
 * @var list<array<string,mixed>> $driverRows
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Driver loads</h1>
    <p class="muted">
        Relational replacement for the 21 legacy <code>loadsNN</code>
        per-driver tables. Read-only on this branch &mdash; writes will
        land in a follow-up that ports the load-entry surfaces.
        <a href="<?= e($base) ?>/">&larr; Back</a>
    </p>
</div>

<div class="card">
    <h2>Backfill summary</h2>
    <ul>
        <li>Total rows: <code><?= e((string) $summary['total_rows']) ?></code></li>
        <li>Drivers with at least one load: <code><?= e((string) $summary['drivers_with_loads']) ?></code></li>
        <li>Date range:
            <code><?= e((string) ($summary['oldest_date'] ?? 'n/a')) ?></code>
            to
            <code><?= e((string) ($summary['newest_date'] ?? 'n/a')) ?></code>
        </li>
    </ul>
    <?php if ($summary['total_rows'] === 0): ?>
        <p class="muted" style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:.6rem .8rem;">
            No rows found. The backfill migration may not have run yet &mdash; check
            <code>_migrations</code> on the server.
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Loads per driver (top 50)</h2>
    <?php if ($perDriver === []): ?>
        <p class="muted">None.</p>
    <?php else: ?>
        <table style="border-collapse:collapse;font-size:14px;width:100%;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                    <th style="padding:.3rem .5rem;">Driver id</th>
                    <th style="padding:.3rem .5rem;">User</th>
                    <th style="padding:.3rem .5rem;text-align:right;">Loads</th>
                    <th style="padding:.3rem .5rem;">Spot-check</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($perDriver as $row): ?>
                    <tr style="border-bottom:1px solid #f0f2f6;">
                        <td style="padding:.25rem .5rem;"><code><?= e((string) $row['driver_id']) ?></code></td>
                        <td style="padding:.25rem .5rem;">
                            <?php if ($row['user'] === null): ?>
                                <em class="muted">(orphan)</em>
                            <?php else: ?>
                                <?= e((string) $row['user']) ?>
                            <?php endif; ?>
                        </td>
                        <td style="padding:.25rem .5rem;text-align:right;"><code><?= e((string) $row['n']) ?></code></td>
                        <td style="padding:.25rem .5rem;">
                            <a href="<?= e($base) ?>/loads?driver_id=<?= (int) $row['driver_id'] ?>">view recent &rarr;</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($driverFilter > 0): ?>
    <div class="card">
        <h2>Recent loads for driver <code><?= (int) $driverFilter ?></code></h2>
        <?php if ($driverRows === []): ?>
            <p class="muted">No rows for that driver_id.</p>
        <?php else: ?>
            <table style="border-collapse:collapse;font-size:13px;width:100%;">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                        <th style="padding:.3rem .5rem;">FRTL</th>
                        <th style="padding:.3rem .5rem;">Date</th>
                        <th style="padding:.3rem .5rem;">Variables</th>
                        <th style="padding:.3rem .5rem;">Load info</th>
                        <th style="padding:.3rem .5rem;">Paid flags</th>
                        <th style="padding:.3rem .5rem;text-align:right;">NP</th>
                        <th style="padding:.3rem .5rem;text-align:right;">OP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($driverRows as $row): ?>
                        <tr style="border-bottom:1px solid #f0f2f6;">
                            <td style="padding:.25rem .5rem;"><code><?= e((string) $row['frtl']) ?></code></td>
                            <td style="padding:.25rem .5rem;"><?= e((string) $row['date']) ?></td>
                            <td style="padding:.25rem .5rem;font-family:monospace;font-size:11px;"><?= e((string) $row['variables']) ?></td>
                            <td style="padding:.25rem .5rem;font-family:monospace;font-size:11px;"><?= e((string) $row['loadinfo']) ?></td>
                            <td style="padding:.25rem .5rem;font-family:monospace;font-size:11px;"><?= e((string) $row['paid']) ?></td>
                            <td style="padding:.25rem .5rem;text-align:right;"><?= e((string) $row['np']) ?></td>
                            <td style="padding:.25rem .5rem;text-align:right;"><?= e((string) $row['op']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Recent loads across all drivers (sample of 25)</h2>
    <?php if ($recent === []): ?>
        <p class="muted">None.</p>
    <?php else: ?>
        <table style="border-collapse:collapse;font-size:14px;width:100%;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e4e8ee;">
                    <th style="padding:.3rem .5rem;">Driver</th>
                    <th style="padding:.3rem .5rem;">FRTL</th>
                    <th style="padding:.3rem .5rem;">Date</th>
                    <th style="padding:.3rem .5rem;text-align:right;">NP</th>
                    <th style="padding:.3rem .5rem;text-align:right;">OP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr style="border-bottom:1px solid #f0f2f6;">
                        <td style="padding:.25rem .5rem;">
                            <code><?= e((string) $row['driver_id']) ?></code>
                            <?php if ($row['user'] !== null): ?>
                                <span class="muted">&middot; <?= e((string) $row['user']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:.25rem .5rem;"><code><?= e((string) $row['frtl']) ?></code></td>
                        <td style="padding:.25rem .5rem;"><?= e((string) $row['date']) ?></td>
                        <td style="padding:.25rem .5rem;text-align:right;"><?= e((string) $row['np']) ?></td>
                        <td style="padding:.25rem .5rem;text-align:right;"><?= e((string) $row['op']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
