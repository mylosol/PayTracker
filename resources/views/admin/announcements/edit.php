<?php
/**
 * @var string                  $base
 * @var string                  $csrfToken
 * @var array<string,mixed>     $row
 * @var ?string                 $flash
 * @var int                     $subjectMax
 * @var int                     $bodyMax
 */
layout('layouts/app');

// Convert stored UTC value back to admin's local timezone so the
// picker shows the same wall-clock time they typed at create.
$expiresInput = utc_to_local_for_input(is_string($row['expires_at'] ?? null) ? (string) $row['expires_at'] : null);
?>
<div class="max-w-3xl mx-auto">
    <div class="card">
        <h1 class="m-0">Edit announcement #<?= (int) ($row['id'] ?? 0) ?></h1>
        <p class="text-brand-muted mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
            <a href="<?= e($base) ?>/admin/announcements">← Back to list</a>
            <span class="text-brand-muted">·</span>
            <a href="<?= e($base) ?>/admin/announcements/<?= (int) ($row['id'] ?? 0) ?>">Seen-by report</a>
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/admin/announcements/<?= (int) ($row['id'] ?? 0) ?>/edit"
              novalidate autocomplete="off" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="subject" class="field-label">Subject</label>
                <input id="subject" name="subject" type="text" required
                       value="<?= e((string) ($row['subject'] ?? '')) ?>"
                       maxlength="<?= (int) $subjectMax ?>" class="field">
            </div>

            <div>
                <label for="body" class="field-label">Body</label>
                <textarea id="body" name="body" required rows="8" maxlength="<?= (int) $bodyMax ?>"
                          class="field"><?= e((string) ($row['body'] ?? '')) ?></textarea>
            </div>

            <div>
                <label for="expires_at" class="field-label">
                    Expires at <span class="text-brand-muted font-normal">(optional, <?= e(app_tz_abbrev()) ?>)</span>
                </label>
                <input id="expires_at" name="expires_at" type="datetime-local"
                       value="<?= e($expiresInput) ?>" class="field max-w-xs">
            </div>

            <div>
                <label class="flex items-start gap-2 min-h-[44px]">
                    <input type="checkbox" name="is_template" value="1" class="field-checkbox mt-1"
                           <?= (int) ($row['is_template'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <span><strong>Treat as template only</strong> — never shown to users.</span>
                </label>
                <span class="field-hint">
                    Toggling templating doesn't change <code>is_active</code>. Use the list
                    view's Activate / Deactivate buttons for that.
                </span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Save</button>
                <a href="<?= e($base) ?>/admin/announcements" class="text-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
