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

// datetime-local wants YYYY-MM-DDTHH:MM. Storage is YYYY-MM-DD HH:MM:SS.
$expiresInput = '';
$rawExpires   = $row['expires_at'] ?? null;
if (is_string($rawExpires) && $rawExpires !== '') {
    $expiresInput = str_replace(' ', 'T', substr($rawExpires, 0, 16));
}
?>
<div class="card">
    <h1>Edit announcement #<?= (int) ($row['id'] ?? 0) ?></h1>
    <p>
        <a href="<?= e($base) ?>/admin/announcements">&larr; Back to list</a>
        &middot;
        <a href="<?= e($base) ?>/admin/announcements/<?= (int) ($row['id'] ?? 0) ?>">Seen-by report</a>
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="card" style="background:#fee2e2;color:#991b1b;"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e($base) ?>/admin/announcements/<?= (int) ($row['id'] ?? 0) ?>/edit" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="subject"><strong>Subject</strong></label><br>
            <input id="subject" name="subject" type="text" required
                   value="<?= e((string) ($row['subject'] ?? '')) ?>" maxlength="<?= (int) $subjectMax ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:100%;">
        </p>

        <p>
            <label for="body"><strong>Body</strong></label><br>
            <textarea id="body" name="body" required rows="8" maxlength="<?= (int) $bodyMax ?>"
                      style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;"><?= e((string) ($row['body'] ?? '')) ?></textarea>
        </p>

        <p>
            <label for="expires_at"><strong>Expires at</strong> <span class="muted">(optional, UTC)</span></label><br>
            <input id="expires_at" name="expires_at" type="datetime-local"
                   value="<?= e($expiresInput) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
        </p>

        <p>
            <label>
                <input type="checkbox" name="is_template" value="1" <?= (int) ($row['is_template'] ?? 0) === 1 ? 'checked' : '' ?>>
                <strong>Treat as template only</strong> — never shown to users.
            </label>
            <br>
            <small class="muted">
                Toggling templating doesn't change is_active. Use the list
                view's Activate / Deactivate buttons for that.
            </small>
        </p>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Save
            </button>
            &nbsp;<a href="<?= e($base) ?>/admin/announcements">Cancel</a>
        </p>
    </form>
</div>
