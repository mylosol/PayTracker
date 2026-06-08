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
                // Stored value is UTC; parse it explicitly so the
                // expiry check works regardless of the PHP default
                // timezone (which strtotime would otherwise honour).
                $expired  = false;
                if (is_string($r['expires_at'] ?? null) && $r['expires_at'] !== '') {
                    try {
                        $expired = (new \DateTimeImmutable((string) $r['expires_at'], new \DateTimeZone('UTC')))
                                       ->getTimestamp() < time();
                    } catch (\Throwable) {}
                }
                $active   = ! $used && ! $expired;
                $autoDel  = (int) ($r['auto_delete'] ?? 0) === 1;
                $pills = [];
                if ($used)     { $pills[] = '<span class="pill warn">used</span>'; }
                if ($expired && ! $used) { $pills[] = '<span class="pill err">expired</span>'; }
                if ($active)   { $pills[] = '<span class="pill ok">active</span>'; }
                ?>
                <?php $inviteUrl = $baseUrl . $basePath . '/register?invite=' . $code; ?>
                <tr style="border-bottom:1px solid #e4e8ee;">
                    <td style="padding:.5rem .25rem;">
                        <code><?= e($code) ?></code>
                        <button type="button" class="copy-btn"
                                data-copy="<?= e($code) ?>"
                                title="Copy code to clipboard"
                                style="margin-left:.35rem;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.1rem .45rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                            Copy
                        </button>
                        <?php if ($active): ?>
                            <br><small class="muted" style="word-break:break-all;">
                                <?= e($inviteUrl) ?>
                                <button type="button" class="copy-btn"
                                        data-copy="<?= e($inviteUrl) ?>"
                                        title="Copy invite URL to clipboard"
                                        style="margin-left:.25rem;background:#fff;color:#101418;border:1px solid #cbd2da;padding:.1rem .45rem;border-radius:4px;font:inherit;cursor:pointer;font-size:12px;">
                                    Copy URL
                                </button>
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

<script>
/*
 * Copy-to-clipboard for the per-row "Copy" / "Copy URL" buttons.
 *
 * One delegated handler attached to <body> so newly-rendered rows
 * (after re-issuing an invite, etc.) keep working without a re-bind.
 * The visual feedback (button text flips to "Copied!" for ~1.2s)
 * is the entire success contract -- no flash card, no aria-live
 * because the user just clicked and is looking right at the
 * button.
 *
 * navigator.clipboard.writeText is the modern path; we fall back
 * to a hidden <textarea> + document.execCommand('copy') for the
 * couple of legacy browsers (e.g. Safari < 13.1) where the async
 * Clipboard API isn't available. The fallback runs in the same
 * user-gesture frame so the browser doesn't refuse the copy.
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
