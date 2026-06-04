<?php
/**
 * @var string                     $base
 * @var string                     $csrfToken
 * @var array<string,mixed>        $actor
 * @var list<array<string,mixed>>  $users
 * @var ?string                    $flash
 * @var bool                       $isSuperAdmin
 */
use PayTracker\Models\Account;

layout('layouts/app');

// Convenience: format a nullable UTC datetime for display. We
// don't localise — admins reading this surface generally want
// UTC for consistency with audit logs and server times.
$fmt = static function ($value): string {
    if (! is_string($value) || $value === '') {
        return '—';
    }
    return $value . ' UTC';
};
?>
<div class="card">
    <h1>Admin Panel <span class="pill ok"><?= e((string) $actor['role']) ?></span></h1>
    <p class="muted">
        Signed in as <strong><?= e((string) $actor['user']) ?></strong>
        (id <?= (int) $actor['id'] ?>).
        <?php if (! $isSuperAdmin): ?>
            Role assignment is restricted to Super Admin and is hidden
            from this surface for you.
        <?php endif; ?>
    </p>
    <p>
        <a href="<?= e($base) ?>/admin/audit"
           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
            Audit log &rarr;
        </a>
        &nbsp;
        <a href="<?= e($base) ?>/admin/diagnostics"
           style="display:inline-block;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.4rem 1rem;border-radius:6px;text-decoration:none;">
            System diagnostics &rarr;
        </a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#dcfce7;color:#166534;word-break:break-all;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2>User accounts <span class="muted" style="font-size:14px;">(<?= count($users) ?> shown)</span></h2>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:2px solid #cbd2da;">
                <th style="padding:.5rem .25rem;">ID</th>
                <th style="padding:.5rem .25rem;">User</th>
                <th style="padding:.5rem .25rem;">Email</th>
                <th style="padding:.5rem .25rem;">Role</th>
                <th style="padding:.5rem .25rem;">Last login</th>
                <th style="padding:.5rem .25rem;">Status</th>
                <th style="padding:.5rem .25rem;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <?php
                $uId      = (int) $u['id'];
                $isSelf   = $uId === (int) $actor['id'];
                $banned   = is_string($u['banned_at'] ?? null) && $u['banned_at'] !== '';
                $locked   = is_string($u['locked_until'] ?? null) && $u['locked_until'] !== ''
                            && strtotime((string) $u['locked_until']) > time();
                $statusPills = [];
                if ($banned) { $statusPills[] = '<span class="pill err">banned</span>'; }
                if ($locked) { $statusPills[] = '<span class="pill warn">locked</span>'; }
                if (! $banned && ! $locked) { $statusPills[] = '<span class="pill ok">active</span>'; }
                ?>
                <tr style="border-bottom:1px solid #e4e8ee;">
                    <td style="padding:.5rem .25rem;"><code><?= $uId ?></code></td>
                    <td style="padding:.5rem .25rem;">
                        <?= e((string) $u['user']) ?>
                        <?php if ($isSelf): ?>
                            <span class="pill ok" style="margin-left:.4rem;">you</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.5rem .25rem;"><?= e((string) ($u['email'] ?? '—')) ?></td>
                    <td style="padding:.5rem .25rem;">
                        <?php $currentRole = is_string($u['role'] ?? null) ? (string) $u['role'] : 'user'; ?>
                        <?php if ($isSuperAdmin && ! $isSelf): ?>
                            <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/role"
                                  style="display:flex;gap:.25rem;align-items:center;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <select name="role"
                                        style="padding:.25rem;border:1px solid #cbd2da;border-radius:4px;font:inherit;font-size:13px;">
                                    <?php foreach (Account::ROLES as $r): ?>
                                        <option value="<?= e($r) ?>" <?= $currentRole === $r ? 'selected' : '' ?>>
                                            <?= e($r) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit"
                                        style="background:#fff;color:#101418;border:1px solid #cbd2da;padding:.25rem .5rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                                    Save
                                </button>
                            </form>
                        <?php else: ?>
                            <code><?= e($currentRole) ?></code>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.5rem .25rem;"><?= e($fmt($u['last_login_at'] ?? null)) ?></td>
                    <td style="padding:.5rem .25rem;"><?= implode(' ', $statusPills) ?></td>
                    <td style="padding:.5rem .25rem;">
                        <?php if ($isSelf): ?>
                            <span class="muted" style="font-size:12px;">No self-actions</span>
                        <?php else: ?>
                            <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/reset-password" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit"
                                        style="background:#fff;color:#101418;border:1px solid #cbd2da;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                    Reset PW
                                </button>
                            </form>
                            <?php if ($banned): ?>
                                <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/unban" style="display:inline;">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button type="submit"
                                            style="background:#dcfce7;color:#166534;border:1px solid #86efac;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                        Unban
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/ban"
                                      onsubmit="return confirm('Ban <?= e((string) $u['user']) ?>? They will be logged out and unable to sign in.');"
                                      style="display:inline;">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button type="submit"
                                            style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                        Ban
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/delete"
                                  onsubmit="return confirm('PERMANENTLY DELETE <?= e((string) $u['user']) ?> (id <?= $uId ?>)? This cannot be undone.');"
                                  style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit"
                                        style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:.25rem .6rem;border-radius:4px;font:inherit;cursor:pointer;font-size:13px;">
                                    Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <p>
        <a href="<?= e($base) ?>/">&larr; Back home</a>
    </p>
</div>
