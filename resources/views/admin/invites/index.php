<?php
/**
 * @var string                     $base
 * @var string                     $csrfToken
 * @var list<array<string,mixed>>  $rows
 * @var ?string                    $flash
 * @var string                     $baseUrl   absolute scheme+host
 * @var string                     $basePath  path-only basePath
 */
layout('layouts/app');

$fmt = static function ($value): string {
    return is_string($value) && $value !== '' ? $value . ' UTC' : '—';
};
?>
<div class="card">
    <h1>Invite codes</h1>
    <p class="muted">
        Each code grants one new account. Admin and above can mint
        / edit / email / revoke. Codes are 8-character uppercase
        alphanumeric and validated on the public
        <code>/register?invite=…</code> page.
    </p>
    <p>
        <a href="<?= e($base) ?>/admin/invites/new"
           style="display:inline-block;background:var(--accent);color:#fff;padding:.5rem 1.2rem;border-radius:6px;text-decoration:none;">
            + New invite
        </a>
        &nbsp;<a href="<?= e($base) ?>/admin">&larr; Admin Panel</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#dcfce7;color:#166534;word-break:break-all;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:2px solid #cbd2da;">
                <th style="padding:.5rem .25rem;">Code</th>
                <th style="padding:.5rem .25rem;">Invitee email</th>
                <th style="padding:.5rem .25rem;">State</th>
                <th style="padding:.5rem .25rem;">Expires</th>
                <th style="padding:.5rem .25rem;">Created</th>
                <th style="padding:.5rem .25rem;">Auto-delete?</th>
                <th style="padding:.5rem .25rem;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" style="padding:.6rem;color:#5a6470;">No invite codes yet. Create one above.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $rId      = (int) ($r['id'] ?? 0);
                $code     = (string) ($r['code'] ?? '');
                $used     = is_string($r['used_at']    ?? null) && $r['used_at']    !== '';
                $expired  = is_string($r['expires_at'] ?? null) && $r['expires_at'] !== ''
                            && strtotime((string) $r['expires_at']) < time();
                $active   = ! $used && ! $expired;
                $autoDel  = (int) ($r['auto_delete'] ?? 0) === 1;
                $pills = [];
                if ($used)     { $pills[] = '<span class="pill warn">used</span>'; }
                if ($expired && ! $used) { $pills[] = '<span class="pill err">expired</span>'; }
                if ($active)   { $pills[] = '<span class="pill ok">active</span>'; }
                ?>
                <tr style="border-bottom:1px solid #e4e8ee;">
                    <td style="padding:.5rem .25rem;">
                        <code><?= e($code) ?></code>
                        <?php if ($active): ?>
                            <br><small class="muted" style="word-break:break-all;">
                                <?= e($baseUrl . $basePath) ?>/register?invite=<?= e($code) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.5rem .25rem;">
                        <?= e((string) ($r['invitee_email'] ?? '—')) ?>
                    </td>
                    <td style="padding:.5rem .25rem;"><?= implode(' ', $pills) ?></td>
                    <td style="padding:.5rem .25rem;"><?= e($fmt($r['expires_at'] ?? null)) ?></td>
                    <td style="padding:.5rem .25rem;">
                        <?= e($fmt($r['created_at'] ?? null)) ?>
                        <br><small class="muted">by <?= e((string) ($r['created_by_user'] ?? '—')) ?></small>
                    </td>
                    <td style="padding:.5rem .25rem;">
                        <?php if ($autoDel): ?>
                            <span class="pill">yes</span>
                        <?php else: ?>
                            <span class="muted">no — kept after consume</span>
                        <?php endif; ?>
                        <?php if ($used): ?>
                            <br><small class="muted">used by <?= e((string) ($r['used_by_user'] ?? '(deleted)')) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.5rem .25rem;">
                        <?php if (! $used): ?>
                            <a href="<?= e($base) ?>/admin/invites/<?= $rId ?>/edit"
                               style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.25rem .6rem;border-radius:4px;text-decoration:none;font-size:13px;">
                                Edit
                            </a>
                            <?php if (is_string($r['invitee_email'] ?? null) && $r['invitee_email'] !== ''): ?>
                                <form method="post" action="<?= e($base) ?>/admin/invites/<?= $rId ?>/email" style="display:inline;">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button type="submit"
                                            style="background:#fff;color:#101418;border:1px solid #cbd2da;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                        Re-send email
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <form method="post" action="<?= e($base) ?>/admin/invites/<?= $rId ?>/revoke"
                              onsubmit="return confirm('Revoke invite code <?= e($code) ?>? This deletes the row; if a user clicks the link they\'ll see Invite not found.');"
                              style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <button type="submit"
                                    style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                Revoke
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
