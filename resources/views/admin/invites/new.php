<?php
/**
 * @var string                                                         $base
 * @var string                                                         $csrfToken
 * @var ?string                                                        $flash
 * @var array{invitee_email:string,expires_at:string,auto_delete:string} $old
 */
layout('layouts/app');
?>
<div class="max-w-2xl mx-auto">
    <div class="card">
        <h1 class="m-0">New invite code</h1>
        <p class="text-brand-muted mt-2">
            <a href="<?= e($base) ?>/admin/invites">← Back to list</a>
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/admin/invites" novalidate autocomplete="off"
              class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="invitee_email" class="field-label">
                    Invitee email <span class="text-brand-muted font-normal">(optional, used for Resend)</span>
                </label>
                <input id="invitee_email" name="invitee_email" type="email" maxlength="255"
                       value="<?= e($old['invitee_email']) ?>" class="field">
                <span class="field-hint">
                    Leave blank to just mint a code — the URL appears in the
                    flash banner so you can copy/paste it manually.
                    When set, the page automatically sends the invite via
                    Resend (when configured).
                </span>
            </div>

            <div>
                <label for="expires_at" class="field-label">
                    Expires at <span class="text-brand-muted font-normal">(optional, <?= e(app_tz_abbrev()) ?>)</span>
                </label>
                <input id="expires_at" name="expires_at" type="datetime-local"
                       value="<?= e($old['expires_at']) ?>" class="field max-w-xs">
                <span class="field-hint">
                    Leave blank to keep the code live until manually revoked
                    or consumed. Pick a time in your local clock
                    (<?= e(app_tz_abbrev()) ?>); the server converts to UTC
                    for storage.
                </span>
            </div>

            <fieldset class="border border-brand-line rounded-lg p-4">
                <legend class="px-2 text-sm font-semibold text-brand-ink">After consumption</legend>
                <div class="space-y-2 mt-1">
                    <label class="flex items-start gap-2 min-h-[44px]">
                        <input type="radio" name="auto_delete" value="1" class="field-radio mt-1"
                               <?= $old['auto_delete'] !== '0' ? 'checked' : '' ?>>
                        <span>
                            <strong>Auto-delete the row</strong> — recommended;
                            leaves no trace beyond the audit log.
                        </span>
                    </label>
                    <label class="flex items-start gap-2 min-h-[44px]">
                        <input type="radio" name="auto_delete" value="0" class="field-radio mt-1"
                               <?= $old['auto_delete'] === '0' ? 'checked' : '' ?>>
                        <span>
                            <strong>Keep the row</strong> — shows "used by &lt;handle&gt;" in
                            the list so an admin can see who redeemed it.
                        </span>
                    </label>
                </div>
            </fieldset>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Mint code</button>
                <a href="<?= e($base) ?>/admin/invites" class="text-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
