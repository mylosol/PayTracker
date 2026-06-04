<?php
/**
 * @var string                                                         $base
 * @var string                                                         $csrfToken
 * @var ?string                                                        $flash
 * @var array{invitee_email:string,expires_at:string,auto_delete:string} $old
 */
layout('layouts/app');
?>
<div class="card">
    <h1>New invite code</h1>
    <p>
        <a href="<?= e($base) ?>/admin/invites">&larr; Back to list</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#fee2e2;color:#991b1b;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e($base) ?>/admin/invites" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="invitee_email"><strong>Invitee email</strong> <span class="muted">(optional, used for Resend)</span></label><br>
            <input id="invitee_email" name="invitee_email" type="email" maxlength="255"
                   value="<?= e($old['invitee_email']) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">
                Leave blank to just mint a code — the URL appears in the
                flash banner so you can copy/paste it manually.
                When set, the page automatically sends the invite via
                Resend (when configured).
            </small>
        </p>

        <p>
            <label for="expires_at"><strong>Expires at</strong>
                <span class="muted">(optional, <?= e(app_tz_abbrev()) ?>)</span></label><br>
            <input id="expires_at" name="expires_at" type="datetime-local"
                   value="<?= e($old['expires_at']) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
            <small class="muted">
                Leave blank to keep the code live until manually revoked
                or consumed. Pick a time in your local clock
                (<?= e(app_tz_abbrev()) ?>); the server converts to UTC
                for storage.
            </small>
        </p>

        <fieldset style="border:1px solid #cbd2da;border-radius:6px;padding:.6rem 1rem;margin:0 0 1rem 0;">
            <legend><strong>After consumption</strong></legend>
            <label style="display:block;margin:.3rem 0;">
                <input type="radio" name="auto_delete" value="1" <?= $old['auto_delete'] !== '0' ? 'checked' : '' ?>>
                <strong>Auto-delete the row</strong> — recommended; leaves no trace beyond the audit log.
            </label>
            <label style="display:block;margin:.3rem 0;">
                <input type="radio" name="auto_delete" value="0" <?= $old['auto_delete'] === '0' ? 'checked' : '' ?>>
                <strong>Keep the row</strong> — shows "used by &lt;handle&gt;" in the list so an admin can see who redeemed it.
            </label>
        </fieldset>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Mint code
            </button>
            &nbsp;<a href="<?= e($base) ?>/admin/invites">Cancel</a>
        </p>
    </form>
</div>
