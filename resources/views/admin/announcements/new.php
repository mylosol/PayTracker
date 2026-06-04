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
<div class="card">
    <h1>New announcement</h1>
    <p>
        <a href="<?= e($base) ?>/admin/announcements">&larr; Back to list</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#fee2e2;color:#991b1b;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e($base) ?>/admin/announcements" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="subject"><strong>Subject</strong></label><br>
            <input id="subject" name="subject" type="text" required
                   value="<?= e($old['subject']) ?>" maxlength="<?= (int) $subjectMax ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:100%;">
            <small class="muted">Max <?= (int) $subjectMax ?> characters.</small>
        </p>

        <p>
            <label for="body"><strong>Body</strong></label><br>
            <textarea id="body" name="body" required rows="8" maxlength="<?= (int) $bodyMax ?>"
                      style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;"><?= e($old['body']) ?></textarea>
            <small class="muted">Plain text. Line breaks preserved when rendered. Max <?= (int) $bodyMax ?> characters.</small>
        </p>

        <p>
            <label for="expires_at"><strong>Expires at</strong> <span class="muted">(optional, UTC)</span></label><br>
            <input id="expires_at" name="expires_at" type="datetime-local"
                   value="<?= e($old['expires_at']) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
            <small class="muted">
                Leave blank to keep until manually deactivated. The value is
                stored in UTC; the picker shows your local time but the
                server treats whatever you type as UTC.
            </small>
        </p>

        <fieldset style="border:1px solid #cbd2da;border-radius:6px;padding:.6rem 1rem;margin:0 0 1rem 0;">
            <legend><strong>What to do with it</strong></legend>
            <label style="display:block;margin:.3rem 0;">
                <input type="checkbox" name="activate" value="1" <?= $old['activate'] === '1' ? 'checked' : '' ?>>
                <strong>Activate immediately</strong> — every other active announcement will be deactivated.
            </label>
            <label style="display:block;margin:.3rem 0;">
                <input type="checkbox" name="is_template" value="1" <?= $old['is_template'] === '1' ? 'checked' : '' ?>>
                <strong>Save as template only</strong> — never shown to users; can be cloned later.
            </label>
            <small class="muted">Selecting both saves as a template (and ignores the activate flag — templates are never active).</small>
        </fieldset>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Save
            </button>
            &nbsp;<a href="<?= e($base) ?>/admin/announcements">Cancel</a>
        </p>
    </form>
</div>
