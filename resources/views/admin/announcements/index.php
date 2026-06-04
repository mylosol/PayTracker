<?php
/**
 * @var string                     $base
 * @var string                     $csrfToken
 * @var list<array<string,mixed>>  $rows
 * @var ?string                    $flash
 */
layout('layouts/app');

// Stored as UTC; render in admin's local tz with the
// abbreviation appended.
$fmt = static fn ($value): string => utc_to_local_display(is_string($value) ? $value : null);
?>
<div class="card">
    <h1>Announcements <span class="pill ok">super_admin</span></h1>
    <p class="muted">
        Subject + body, shown to every signed-in user as a modal on
        their next page render after login. Only ONE announcement
        can be active at a time. Templates are stored for reuse and
        are never shown to users.
    </p>
    <p>
        <a href="<?= e($base) ?>/admin/announcements/new"
           style="display:inline-block;background:var(--accent);color:#fff;padding:.5rem 1.2rem;border-radius:6px;text-decoration:none;">
            + New announcement
        </a>
        &nbsp;<a href="<?= e($base) ?>/admin">&larr; Admin Panel</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#dcfce7;color:#166534;word-break:break-word;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:2px solid #cbd2da;">
                <th style="padding:.5rem .25rem;">ID</th>
                <th style="padding:.5rem .25rem;">Subject</th>
                <th style="padding:.5rem .25rem;">State</th>
                <th style="padding:.5rem .25rem;">Expires</th>
                <th style="padding:.5rem .25rem;">Seen by</th>
                <th style="padding:.5rem .25rem;">Updated</th>
                <th style="padding:.5rem .25rem;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" style="padding:.6rem;color:#5a6470;">No announcements yet. Create one above.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $rId       = (int) ($r['id'] ?? 0);
                $isTplt    = (int) ($r['is_template'] ?? 0) === 1;
                $isActive  = (int) ($r['is_active']   ?? 0) === 1;
                // Stored value is UTC; parse it explicitly so the
                // expiry check works regardless of the PHP default
                // timezone (which strtotime would otherwise honour).
                $expired = false;
                if (is_string($r['expires_at'] ?? null) && $r['expires_at'] !== '') {
                    try {
                        $expired = (new \DateTimeImmutable((string) $r['expires_at'], new \DateTimeZone('UTC')))
                                       ->getTimestamp() < time();
                    } catch (\Throwable) {}
                }
                $pills = [];
                if ($isTplt)            { $pills[] = '<span class="pill warn">template</span>'; }
                if ($isActive && ! $isTplt && ! $expired) { $pills[] = '<span class="pill ok">active</span>'; }
                if ($expired)           { $pills[] = '<span class="pill err">expired</span>'; }
                if ($pills === [])      { $pills[] = '<span class="pill">inactive</span>'; }
                ?>
                <tr style="border-bottom:1px solid #e4e8ee;">
                    <td style="padding:.5rem .25rem;"><code><?= $rId ?></code></td>
                    <td style="padding:.5rem .25rem;"><a href="<?= e($base) ?>/admin/announcements/<?= $rId ?>"><?= e((string) ($r['subject'] ?? '')) ?></a></td>
                    <td style="padding:.5rem .25rem;"><?= implode(' ', $pills) ?></td>
                    <td style="padding:.5rem .25rem;"><?= e($fmt($r['expires_at'] ?? null)) ?></td>
                    <td style="padding:.5rem .25rem;"><?= (int) ($r['viewer_count'] ?? 0) ?></td>
                    <td style="padding:.5rem .25rem;"><?= e($fmt($r['updated_at'] ?? null)) ?></td>
                    <td style="padding:.5rem .25rem;">
                        <a href="<?= e($base) ?>/admin/announcements/<?= $rId ?>/edit"
                           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.25rem .6rem;border-radius:4px;text-decoration:none;font-size:13px;">
                            Edit
                        </a>
                        <?php if ($isTplt): ?>
                            <form method="post" action="<?= e($base) ?>/admin/announcements/<?= $rId ?>/use-template" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit" style="background:#fff;color:#101418;border:1px solid #cbd2da;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                    Use template
                                </button>
                            </form>
                        <?php elseif ($isActive): ?>
                            <form method="post" action="<?= e($base) ?>/admin/announcements/<?= $rId ?>/deactivate" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit" style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                    Deactivate
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e($base) ?>/admin/announcements/<?= $rId ?>/activate" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit" style="background:#dcfce7;color:#166534;border:1px solid #86efac;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                    Activate
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e($base) ?>/admin/announcements/<?= $rId ?>/delete"
                              onsubmit="return confirm('PERMANENTLY DELETE this announcement and every dismissal record for it?');"
                              style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <button type="submit" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
