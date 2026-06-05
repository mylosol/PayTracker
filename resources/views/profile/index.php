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
    // '!' resets unspecified time fields to 00:00:00 so the diff isn't
    // skewed by the current wall clock — see VariableBlobBuilder for why.
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
            <label for="username"><strong>Username</strong></label><br>
            <input id="username" name="username" type="text" required
                   value="<?= e($username) ?>" pattern="[A-Za-z0-9._\-]{3,32}"
                   minlength="3" maxlength="32" autocomplete="username"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">
                3-32 characters &mdash; letters, digits, dot, underscore, dash.
                No spaces or <code>@</code> (that's what Email is for).
                Used to sign in.
            </small>
        </p>

        <p>
            <label for="email"><strong>Email</strong></label><br>
            <input id="email" name="email" type="email" required
                   value="<?= e($email) ?>" maxlength="255" autocomplete="email"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">
                Required. Where admin-issued password-reset links and
                other operational mail land.
            </small>
        </p>

        <p>
            <label for="payroll_email"><strong>Payroll contact email</strong> <span class="muted">(optional)</span></label><br>
            <input id="payroll_email" name="payroll_email" type="email"
                   value="<?= e($payrollEmail) ?>" maxlength="255" autocomplete="off"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:24rem;max-width:100%;">
            <small class="muted">
                Optional. The address dispute notifications go to when you
                click <strong>Send batch</strong> on the Reconcile page.
                Leave blank to disable batch notifications &mdash; you can
                still mark loads paid / short / disputed without it.
            </small>
        </p>

        <p>
            <label for="hire_date"><strong>Hire date</strong> <span class="muted">(optional)</span></label><br>
            <input id="hire_date" name="hire_date" type="date"
                   value="<?= e($hireDate) ?>"
                   max="<?= e(date('Y-m-d')) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
            <small class="muted">
                Current band: <strong><?= e($bandPreview) ?></strong>.
                Leave blank and we default to the junior (6) band until you set a date.
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
            <label for="pay_week_start_day"><strong>Pay week starts on</strong></label><br>
            <select id="pay_week_start_day" name="pay_week_start_day"
                    style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
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
            <br>
            <small class="muted">
                Drives the dashboard's <strong>This Week</strong> card.
                Most US carriers use Sunday; pick whatever matches your payroll.
            </small>
        </p>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Save profile
            </button>
            &nbsp;<a href="<?= e($base) ?>/dashboard">Back to dashboard</a>
        </p>
    </form>
</div>
