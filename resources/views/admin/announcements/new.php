<?php
/**
 * @var string                          $base
 * @var string                          $csrfToken
 * @var ?string                         $flash
 * @var int                             $subjectMax
 * @var int                             $bodyMax
 * @var array{subject:string,body:string,expires_at:string,is_template:string,activate:string} $old
 */
layout('layouts/app');
?>
<div class="max-w-3xl mx-auto">
    <div class="card">
        <h1 class="m-0">New announcement</h1>
        <p class="text-brand-muted mt-2">
            <a href="<?= e($base) ?>/admin/announcements">← Back to list</a>
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/admin/announcements"
              novalidate autocomplete="off" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="subject" class="field-label">Subject</label>
                <input id="subject" name="subject" type="text" required
                       value="<?= e($old['subject']) ?>" maxlength="<?= (int) $subjectMax ?>"
                       class="field">
                <span class="field-hint">Max <?= (int) $subjectMax ?> characters.</span>
            </div>

            <div>
                <label for="body" class="field-label">Body</label>
                <textarea id="body" name="body" required rows="8" maxlength="<?= (int) $bodyMax ?>"
                          class="field"><?= e($old['body']) ?></textarea>
                <span class="field-hint">
                    Plain text. Line breaks preserved when rendered. Max
                    <?= (int) $bodyMax ?> characters.
                </span>
            </div>

            <div>
                <label for="expires_at" class="field-label">
                    Expires at <span class="text-brand-muted font-normal">(optional, <?= e(app_tz_abbrev()) ?>)</span>
                </label>
                <input id="expires_at" name="expires_at" type="datetime-local"
                       value="<?= e($old['expires_at']) ?>" class="field max-w-xs">
                <span class="field-hint">
                    Leave blank to keep until manually deactivated. Pick a
                    time in your local clock (<?= e(app_tz_abbrev()) ?>);
                    the server converts to UTC for storage.
                </span>
            </div>

            <fieldset class="border border-brand-line rounded-lg p-4">
                <legend class="px-2 text-sm font-semibold text-brand-ink">What to do with it</legend>
                <div class="space-y-2 mt-1">
                    <label class="flex items-start gap-2 min-h-[44px]">
                        <input type="checkbox" name="activate" value="1" class="field-checkbox mt-1"
                               <?= $old['activate'] === '1' ? 'checked' : '' ?>>
                        <span>
                            <strong>Activate immediately</strong> — every other
                            active announcement will be deactivated.
                        </span>
                    </label>
                    <label class="flex items-start gap-2 min-h-[44px]">
                        <input type="checkbox" name="is_template" value="1" class="field-checkbox mt-1"
                               <?= $old['is_template'] === '1' ? 'checked' : '' ?>>
                        <span>
                            <strong>Save as template only</strong> — never shown to users;
                            can be cloned later.
                        </span>
                    </label>
                </div>
                <p class="field-hint mt-2">
                    Selecting both saves as a template (and ignores the activate
                    flag — templates are never active).
                </p>
            </fieldset>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Save</button>
                <a href="<?= e($base) ?>/admin/announcements" class="text-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
