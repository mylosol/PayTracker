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

// All stored datetimes are UTC; render in the admin's local tz
// so they don't have to translate in their head.
$fmt = static fn ($value): string => utc_to_local_display(is_string($value) ? $value : null);
?>
<div class="card">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="m-0">Invite codes</h1>
            <p class="text-brand-muted mt-2">
                Each code grants one new account. Admin and above can mint,
                edit, email, or revoke. Codes are 8-character uppercase
                alphanumeric and validated on the public
                <code>/register?invite=…</code> page.
            </p>
        </div>
        <a href="<?= e($base) ?>/admin/invites/new" class="btn-primary">+ New invite</a>
    </div>
    <p class="text-sm mt-4">
        <a href="<?= e($base) ?>/admin">← Admin Panel</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-ok break-all" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Invitee email</th>
                    <th>State</th>
                    <th>Expires</th>
                    <th>Created</th>
                    <th>Auto-delete?</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="text-brand-muted">No invite codes yet. Create one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $rId      = (int) ($r['id'] ?? 0);
                    $code     = (string) ($r['code'] ?? '');
                    $used     = is_string($r['used_at']    ?? null) && $r['used_at']    !== '';
                    $expired  = false;
                    if (is_string($r['expires_at'] ?? null) && $r['expires_at'] !== '') {
                        try {
                            $expired = (new \DateTimeImmutable((string) $r['expires_at'], new \DateTimeZone('UTC')))
                                           ->getTimestamp() < time();
                        } catch (\Throwable) {}
                    }
                    $active   = ! $used && ! $expired;
                    $autoDel  = (int) ($r['auto_delete'] ?? 0) === 1;
                    $inviteUrl = $baseUrl . $basePath . '/register?invite=' . $code;
                    ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-2 flex-wrap">
                                <code><?= e($code) ?></code>
                                <button type="button" class="copy-btn btn-secondary btn-sm"
                                        data-copy="<?= e($code) ?>"
                                        title="Copy code to clipboard">
                                    Copy
                                </button>
                            </div>
                            <?php if ($active): ?>
                                <div class="text-xs text-brand-muted mt-1 break-all">
                                    <?= e($inviteUrl) ?>
                                    <button type="button" class="copy-btn btn-secondary btn-sm ml-1"
                                            data-copy="<?= e($inviteUrl) ?>"
                                            title="Copy invite URL to clipboard">
                                        Copy URL
                                    </button>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($r['invitee_email'] ?? '—')) ?></td>
                        <td>
                            <div class="flex flex-wrap items-center gap-1">
                                <?php if ($used):    ?><span class="pill warn">used</span><?php endif; ?>
                                <?php if ($expired && ! $used): ?><span class="pill err">expired</span><?php endif; ?>
                                <?php if ($active):  ?><span class="pill ok">active</span><?php endif; ?>
                            </div>
                        </td>
                        <td class="text-brand-muted text-sm"><?= e($fmt($r['expires_at'] ?? null)) ?></td>
                        <td class="text-sm">
                            <?= e($fmt($r['created_at'] ?? null)) ?>
                            <span class="block text-brand-muted">by <?= e((string) ($r['created_by_user'] ?? '—')) ?></span>
                        </td>
                        <td>
                            <?php if ($autoDel): ?>
                                <span class="pill muted">yes</span>
                            <?php else: ?>
                                <span class="text-brand-muted text-sm">no — kept after consume</span>
                            <?php endif; ?>
                            <?php if ($used): ?>
                                <span class="block text-xs text-brand-muted mt-1">
                                    used by <?= e((string) ($r['used_by_user'] ?? '(deleted)')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <?php if (! $used): ?>
                                    <a href="<?= e($base) ?>/admin/invites/<?= $rId ?>/edit"
                                       class="btn-secondary btn-sm">Edit</a>
                                    <?php if (is_string($r['invitee_email'] ?? null) && $r['invitee_email'] !== ''): ?>
                                        <form method="post" action="<?= e($base) ?>/admin/invites/<?= $rId ?>/email" class="inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <button type="submit" class="btn-secondary btn-sm">Re-send email</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <form method="post" action="<?= e($base) ?>/admin/invites/<?= $rId ?>/revoke"
                                      onsubmit="return confirm('Revoke invite code <?= e($code) ?>? This deletes the row; if a user clicks the link they\'ll see Invite not found.');"
                                      class="inline">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <button type="submit" class="btn-danger btn-sm">Revoke</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
/*
 * Copy-to-clipboard for the per-row "Copy" / "Copy URL" buttons.
 * One delegated handler on body, navigator.clipboard with a
 * textarea fallback. Visual feedback only -- button text flips to
 * "Copied!" for ~1.2s.
 */
(function () {
    var FALLBACK = function (text) {
        try {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.top = '-1000px';
            document.body.appendChild(ta);
            ta.select();
            var ok = document.execCommand('copy');
            document.body.removeChild(ta);
            return ok;
        } catch (e) { return false; }
    };
    document.body.addEventListener('click', function (ev) {
        var btn = ev.target;
        if (!(btn instanceof HTMLElement) || !btn.classList.contains('copy-btn')) {
            return;
        }
        var text = btn.getAttribute('data-copy') || '';
        if (text === '') { return; }
        ev.preventDefault();
        var done = function (ok) {
            var label = btn.textContent;
            btn.textContent = ok ? 'Copied!' : 'Copy failed';
            btn.disabled = true;
            setTimeout(function () {
                btn.textContent = label;
                btn.disabled = false;
            }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                done(true);
            }, function () {
                done(FALLBACK(text));
            });
        } else {
            done(FALLBACK(text));
        }
    });
})();
</script>
