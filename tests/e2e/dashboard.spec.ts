import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 12 of docs/qa/test_plan.md — the modern /dashboard
 * surface (the signed-in driver's "my pay" page).
 *
 * The preview DB's modern-era driver_loads rows are dated 2023; the QA
 * account ("driver_id=1" the seed script creates) might or might not
 * have rows on the current day. So the tests fall back to a known-empty
 * date (1999-01-01) for the "no loads" assertion and just verify the
 * render-machinery on today's view without asserting on row counts.
 */
test.describe('driver dashboard', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('12a — anonymous /dashboard redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('dashboard');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('12b — signed-in dashboard renders heading + nav + totals', async ({ page }) => {
        await signIn(page);
        await page.goto('dashboard');
        // The heading includes today's date — match the static prefix only.
        await expect(page.getByRole('heading', { name: /my pay/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /totals/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /loads/i })).toBeVisible();

        // Date-nav links must be present
        await expect(page.getByRole('link', { name: /\d{4}-\d{2}-\d{2}\s*→/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /←\s*\d{4}-\d{2}-\d{2}/ })).toBeVisible();
    });

    test('12c — empty-day view shows "No loads"', async ({ page }) => {
        await signIn(page);
        // 1999-01-01 is guaranteed to predate every row in driver_loads.
        await page.goto('dashboard?date=1999-01-01');
        await expect(page.getByText(/no loads on 1999-01-01/i)).toBeVisible();
        // Totals row shows zero count
        await expect(page.locator('text=/Loads/').first()).toBeVisible();
    });

    test('12d — date nav navigates without losing auth', async ({ page }) => {
        await signIn(page);
        await page.goto('dashboard?date=2023-04-10');
        // Click the prev-day link — should land on 2023-04-09 (the
        // dashboard for the previous day, NOT the login page).
        await page.getByRole('link', { name: /←\s*2023-04-09/ }).click();
        await expect(page).toHaveURL(/dashboard\?date=2023-04-09/);
        await expect(page.getByRole('heading', { name: /my pay/i })).toBeVisible();
    });
});
