import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Driver profile page — hire_date + shift drive PayCalculator's
 * tenure band and night bonus. Until this page existed every load
 * was computed against a hardcoded "168-night--0" blob, inflating
 * np for junior and day-shift drivers.
 *
 * Cleanup: at suite end we reset shift to 'day' and hire_date to
 * blank so the QA test account doesn't carry state across reruns.
 */
test.describe('driver profile', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('p1 — anonymous /profile redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('profile');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('p2 — form renders with hire_date and shift', async ({ page }) => {
        await signIn(page);
        await page.goto('profile');
        await expect(page.getByRole('heading', { name: /^profile$/i })).toBeVisible();
        await expect(page.locator('#hire_date')).toBeVisible();
        await expect(page.locator('input[name="shift"][value="day"]')).toBeVisible();
        await expect(page.locator('input[name="shift"][value="night"]')).toBeVisible();
    });

    test('p3 — invalid hire_date is rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('profile');
        // The native date picker rejects garbage at the browser layer;
        // force it through by setting noValidate on the form.
        await page.locator('form').evaluate((f) => (f as HTMLFormElement).noValidate = true);
        await page.locator('#hire_date').evaluate((el) => {
            (el as HTMLInputElement).type = 'text';
            (el as HTMLInputElement).value = 'not-a-date';
        });
        await page.locator('input[name="shift"][value="day"]').check();
        await page.getByRole('button', { name: /save profile/i }).click();
        await expect(page.getByText(/hire date must be in yyyy-mm-dd/i)).toBeVisible();
    });

    test('p4 — saving day-shift + recent hire persists', async ({ page }) => {
        await signIn(page);
        await page.goto('profile');

        // Snapshot the driver's existing values so we can restore them
        // after the test. The preview DB is shared with whoever's
        // logged in as QA_TEST_USER (often a real user during dogfooding),
        // and clobbering their profile every deploy is a real bug.
        const originalHireDate = await page.locator('#hire_date').inputValue();
        const originalShift    = await page.locator('input[name="shift"]:checked').getAttribute('value') ?? 'day';
        const originalPayWeek  = await page.locator('select[name="pay_week_start_day"]').inputValue();

        try {
            // ~40 months ago → should map to the 60-month band.
            const date = new Date();
            date.setMonth(date.getMonth() - 40);
            const isoDate = date.toISOString().slice(0, 10);

            await page.locator('#hire_date').fill(isoDate);
            await page.locator('input[name="shift"][value="day"]').check();
            await page.getByRole('button', { name: /save profile/i }).click();

            await expect(page).toHaveURL(/\/profile$/);
            await expect(page.getByText(/profile saved/i)).toBeVisible();
            await page.goto('profile');
            await expect(page.getByText(/band 60/i)).toBeVisible();
        } finally {
            // Restore — even on test failure, so a fresh deploy doesn't
            // strand the QA user with stale test values.
            await page.goto('profile');
            await page.locator('#hire_date').fill(originalHireDate);
            await page.locator(`input[name="shift"][value="${originalShift}"]`).check();
            await page.locator('select[name="pay_week_start_day"]').selectOption(originalPayWeek);
            await page.getByRole('button', { name: /save profile/i }).click();
        }
    });
});
