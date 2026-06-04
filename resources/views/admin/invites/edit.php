<?php
/**
 * @var string                  $base
 * @var string                  $csrfToken
 * @var array<string,mixed>     $row
 * @var ?string                 $flash
 */
layout('layouts/app');

// Pre-fill the datetime-local input by converting the stored UTC
// value back to the admin's local timezone so the picker shows
// the same wall-clock time the admin typed when creating.
$expiresInput = utc_to_local_for_input(is_string($row['expires_at'] ?? null) ? (string) $row['expires_at'] : null);
$autoDelete   = (int) ($row['auto_delete'] ?? 0) === 1;
?>
<div class="card">
    <h1>Edit invite <code><?= e((string) ($row['code'] ?? '')) ?></code></h1>
    <p>
        <a href="<?= e($base) ?>/admin/invites">&larr; Back to list</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#fee2e2;color:#991b1b;"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e($base) ?>/admin/invites/<?= (int) ($row['id'] ?? 0) ?>/edit" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="invitee_email"><strong>Invitee email</strong> <span class="muted">(optional)</span></label><br>
            <input id="invitee_email" name="invitee_email" type="email" maxlength="255"
                   value="<?= e((string) ($row['invitee_email'] ?? '')) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
        </p>

        <p>
            <label for="expires_at"><strong>Expires at</strong>
                <span class="muted">(<?= e(app_tz_abbrev()) ?>; blank = no expiry)</span></label><br>
            <input id="expires_at" name="expires_at" type="datetime-local"
                   value="<?= e($expiresInput) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
        </p>

        <fieldset style="border:1px solid #cbd2da;border-radius:6px;padding:.6rem 1rem;margin:0 0 1rem 0;">
            <legend><strong>After consumption</strong></legend>
            <label style="display:block;margin:.3rem 0;">
                <input type="radio" name="auto_delete" value="1" <?= $autoDelete ? 'checked' : '' ?>>
                Auto-delete the row.
            </label>
            <label style="display:block;margin:.3rem 0;">
                <input type="radio" name="auto_delete" value="0" <?= $autoDelete ? '' : 'checked' ?>>
                Keep the row to track who redeemed it.
            </label>
        </fieldset>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Save
            </button>
            &nbsp;<a href="<?= e($base) ?>/admin/invites">Cancel</a>
        </p>
    </form>
</div>
