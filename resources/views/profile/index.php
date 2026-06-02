<?php
/**
 * @var string                  $base
 * @var string                  $csrfToken
 * @var array<string,mixed>     $driver
 * @var string                  $hireDate  YYYY-MM-DD or empty
 * @var string                  $shift     'day' | 'night'
 * @var string|null             $flash
 */
layout('layouts/app');

// Show the driver what tenure band their hire_date currently maps to,
// so they can sanity-check the choice before saving.
$bandPreview = '168 (senior fallback)';
if ($hireDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate) === 1) {
    $hire = DateTimeImmutable::createFromFormat('Y-m-d', $hireDate);
    if ($hire !== false) {
        $weeks = max(0, (int) floor((time() - $hire->getTimestamp()) / 604800));
        $band  = '168';
        foreach ([6, 12, 24, 60, 108, 168] as $b) {
            if ($weeks <= $b) { $band = (string) $b; break; }
        }
        $bandPreview = sprintf('%d weeks → band %s', $weeks, $band);
    }
}
?>
<?php if ($flash !== null): ?>
    <div class="card" style="background:#dcfce7;color:#166534;">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h1>Profile</h1>
    <p class="muted">
        Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong>
        (driver id <?= (int) ($driver['id'] ?? 0) ?>).
        These values drive the per-load pay math &mdash; tenure decides
        your rate band, shift decides whether you get the night bonus.
    </p>

    <form method="post" action="<?= e($base) ?>/profile" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <p>
            <label for="hire_date"><strong>Hire date</strong> <span class="muted">(optional)</span></label><br>
            <input id="hire_date" name="hire_date" type="date"
                   value="<?= e($hireDate) ?>"
                   max="<?= e(date('Y-m-d')) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
            <small class="muted">
                Current band: <strong><?= e($bandPreview) ?></strong>.
                Leave blank to default to the senior (168) band.
            </small>
        </p>

        <fieldset style="border:1px solid #cbd2da;border-radius:6px;padding:.6rem 1rem;margin:0 0 1rem 0;">
            <legend><strong>Default shift</strong></legend>
            <label style="margin-right:1.2rem;">
                <input type="radio" name="shift" value="day" <?= $shift === 'day' ? 'checked' : '' ?>>
                Day
            </label>
            <label>
                <input type="radio" name="shift" value="night" <?= $shift === 'night' ? 'checked' : '' ?>>
                Night
            </label>
            <br>
            <small class="muted">Applied to every load you submit. If you swap shifts for a day, an admin recompute won't change history &mdash; the load's variables blob is snapshotted at write time.</small>
        </fieldset>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Save profile
            </button>
            &nbsp;<a href="<?= e($base) ?>/dashboard">Back to dashboard</a>
        </p>
    </form>
</div>
