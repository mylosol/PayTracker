<?php
/**
 * @var string                  $base
 * @var string                  $csrfToken
 * @var array<string,mixed>     $driver
 * @var string                  $username         3-32 chars [A-Za-z0-9._-]
 * @var string                  $email            full address or empty
 * @var string                  $payrollEmail     full address or empty (optional)
 * @var string                  $hireDate         YYYY-MM-DD or empty
 * @var string                  $shift            'day' | 'night'
 * @var string                  $payWeekStartDay  'sun' | 'mon' | ... | 'sat'
 * @var bool                    $darkMode
 * @var string|null             $flash
 */
layout('layouts/app');

// Show the driver what tenure band their hire_date currently maps to,
// so they can sanity-check the choice before saving. When no date is
// set we surface the junior-band default; see VariableBlobBuilder
// for why that's the chosen fallback (it under-pays rather than
// over-pays).
$bandPreview = '6 (junior fallback — set your hire date for the correct band)';
if ($hireDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate) === 1) {
    $hire = DateTimeImmutable::createFromFormat('!Y-m-d', $hireDate);
    if ($hire !== false) {
        $now    = new DateTimeImmutable('today');
        if ($hire >= $now) {
            $months = 0;
        } else {
            $diff   = $hire->diff($now);
            $months = ($diff->y * 12) + $diff->m;
        }
        $band  = 'max';
        foreach ([6, 12, 24, 60, 108, 168] as $b) {
            if ($months <= $b) { $band = (string) $b; break; }
        }
        $bandPreview = sprintf('%d months → band %s', $months, $band);
    }
}
?>
<div class="max-w-2xl mx-auto">
    <?php if ($flash !== null): ?>
        <div class="flash-ok" role="status"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <h1 class="m-0">Profile</h1>
        <p class="text-brand-muted mt-2">
            Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong>
            (driver id <?= (int) ($driver['id'] ?? 0) ?>).
            These values drive the per-load pay math — tenure decides
            your rate band, shift decides whether you get the night bonus.
        </p>
    </div>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/profile" novalidate autocomplete="off" class="space-y-6">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="username" class="field-label">Username</label>
                <input id="username" name="username" type="text" required
                       value="<?= e($username) ?>" pattern="[A-Za-z0-9._\-]{3,32}"
                       minlength="3" maxlength="32" autocomplete="username"
                       class="field">
                <span class="field-hint">
                    3-32 characters — letters, digits, dot, underscore, dash.
                    No spaces or <code>@</code> (that's what Email is for).
                    Used to sign in.
                </span>
            </div>

            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" required
                       value="<?= e($email) ?>" maxlength="255" autocomplete="email"
                       class="field">
                <span class="field-hint">
                    Required. Where admin-issued password-reset links and
                    other operational mail land.
                </span>
            </div>

            <div>
                <label for="payroll_email" class="field-label">
                    Payroll contact email <span class="text-brand-muted font-normal">(optional)</span>
                </label>
                <input id="payroll_email" name="payroll_email" type="email"
                       value="<?= e($payrollEmail) ?>" maxlength="255" autocomplete="off"
                       class="field">
                <span class="field-hint">
                    The address dispute notifications go to when you click
                    <strong>Send batch</strong> on the Reconcile page. Leave
                    blank to disable batch notifications — you can still
                    mark loads paid / disputed without it.
                </span>
            </div>

            <div>
                <label for="hire_date" class="field-label">
                    Hire date <span class="text-brand-muted font-normal">(optional)</span>
                </label>
                <input id="hire_date" name="hire_date" type="date"
                       value="<?= e($hireDate) ?>"
                       max="<?= e(date('Y-m-d')) ?>"
                       class="field max-w-xs">
                <span class="field-hint">
                    Current band: <strong><?= e($bandPreview) ?></strong>.
                    Leave blank and we default to the junior (6) band until you set a date.
                </span>
            </div>

            <fieldset class="border border-brand-line rounded-lg p-4">
                <legend class="px-2 text-sm font-semibold text-brand-ink">Default shift</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2 mt-1">
                    <label class="inline-flex items-center gap-2 min-h-[44px]">
                        <input type="radio" name="shift" value="day" class="field-radio"
                               <?= $shift === 'day' ? 'checked' : '' ?>>
                        <span>Day</span>
                    </label>
                    <label class="inline-flex items-center gap-2 min-h-[44px]">
                        <input type="radio" name="shift" value="night" class="field-radio"
                               <?= $shift === 'night' ? 'checked' : '' ?>>
                        <span>Night</span>
                    </label>
                </div>
                <p class="field-hint mt-2">
                    Applied to every load you submit. If you swap shifts for a day, an admin
                    recompute won't change history — the load's variables blob is snapshotted
                    at write time.
                </p>
            </fieldset>

            <div>
                <label for="pay_week_start_day" class="field-label">Pay week starts on</label>
                <select id="pay_week_start_day" name="pay_week_start_day" class="field-select max-w-xs">
                    <?php foreach ([
                        'sun' => 'Sunday',
                        'mon' => 'Monday',
                        'tue' => 'Tuesday',
                        'wed' => 'Wednesday',
                        'thu' => 'Thursday',
                        'fri' => 'Friday',
                        'sat' => 'Saturday',
                    ] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $payWeekStartDay === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="field-hint">
                    Drives the dashboard's <strong>This Week</strong> card.
                    Most US carriers use Sunday; pick whatever matches your payroll.
                </span>
            </div>

            <fieldset class="border border-brand-line rounded-lg p-4">
                <legend class="px-2 text-sm font-semibold text-brand-muted">Appearance</legend>
                <label class="inline-flex items-center gap-3 min-h-[44px]">
                    <input type="checkbox" name="dark_mode" value="1" class="field-checkbox"
                           <?= $darkMode ? 'checked' : '' ?>>
                    <span><strong>Dark mode</strong> <span class="text-brand-muted">— easier on the eyes after sundown. Saved per account.</span></span>
                </label>
            </fieldset>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Save profile</button>
                <a href="<?= e($base) ?>/dashboard" class="text-sm">Back to dashboard</a>
            </div>
        </form>
    </div>
</div>
