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

        // 30 weeks ago → should map to the 60-week band on the preview.
        const date = new Date();
        date.setDate(date.getDate() - 30 * 7);
        const isoDate = date.toISOString().slice(0, 10);

        await page.locator('#hire_date').fill(isoDate);
        await page.locator('input[name="shift"][value="day"]').check();
        await page.getByRole('button', { name: /save profile/i }).click();

        await expect(page).toHaveURL(/\/profile$/);
        await expect(page.getByText(/profile saved/i)).toBeVisible();
        // Reload and verify the band preview reflects the saved date.
        await page.goto('profile');
        await expect(page.getByText(/band 60/i)).toBeVisible();
    });

    // Cleanup — reset to legacy defaults so subsequent runs of other
    // specs (especially loads-entry, which inserts and checks np) see
    // a consistent baseline.
    test.afterAll(async ({ browser }) => {
        const context = await browser.newContext();
        const page = await context.newPage();
        try {
            await signIn(page);
            await page.goto('profile');
            await page.locator('#hire_date').fill('');
            await page.locator('input[name="shift"][value="day"]').check();
            await page.getByRole('button', { name: /save profile/i }).click();
        } finally {
            await context.close();
        }
    });
});
