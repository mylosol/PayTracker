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
<div class="max-w-2xl mx-auto">
    <div class="card">
        <h1 class="m-0">Edit invite <code><?= e((string) ($row['code'] ?? '')) ?></code></h1>
        <p class="text-brand-muted mt-2">
            <a href="<?= e($base) ?>/admin/invites">← Back to list</a>
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/admin/invites/<?= (int) ($row['id'] ?? 0) ?>/edit"
              novalidate autocomplete="off" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="invitee_email" class="field-label">
                    Invitee email <span class="text-brand-muted font-normal">(optional)</span>
                </label>
                <input id="invitee_email" name="invitee_email" type="email" maxlength="255"
                       value="<?= e((string) ($row['invitee_email'] ?? '')) ?>" class="field">
            </div>

            <div>
                <label for="expires_at" class="field-label">
                    Expires at <span class="text-brand-muted font-normal">(<?= e(app_tz_abbrev()) ?>; blank = no expiry)</span>
                </label>
                <input id="expires_at" name="expires_at" type="datetime-local"
                       value="<?= e($expiresInput) ?>" class="field max-w-xs">
            </div>

            <fieldset class="border border-brand-line rounded-lg p-4">
                <legend class="px-2 text-sm font-semibold text-brand-ink">After consumption</legend>
                <div class="space-y-2 mt-1">
                    <label class="flex items-center gap-2 min-h-[44px]">
                        <input type="radio" name="auto_delete" value="1" class="field-radio"
                               <?= $autoDelete ? 'checked' : '' ?>>
                        <span>Auto-delete the row.</span>
                    </label>
                    <label class="flex items-center gap-2 min-h-[44px]">
                        <input type="radio" name="auto_delete" value="0" class="field-radio"
                               <?= $autoDelete ? '' : 'checked' ?>>
                        <span>Keep the row to track who redeemed it.</span>
                    </label>
                </div>
            </fieldset>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Save</button>
                <a href="<?= e($base) ?>/admin/invites" class="text-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
