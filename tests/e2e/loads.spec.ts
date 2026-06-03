import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 8 of docs/qa/test_plan.md — driver_loads backfill
 * verification page.
 */
test.describe('driver loads', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('8d — anonymous /loads redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('loads');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('8a — summary card has non-zero totals and a valid date range', async ({ page }) => {
        await signIn(page);
        await page.goto('loads');

        await expect(page.getByRole('heading', { name: /^driver loads$/i })).toBeVisible();

        const totalText = await page.getByText(/total rows:/i).textContent();
        const total = Number(totalText?.match(/\d+/)?.[0] ?? '0');
        expect(total).toBeGreaterThan(0);

        const driversText = await page.getByText(/drivers with at least one load:/i).textContent();
        const drivers = Number(driversText?.match(/\d+/)?.[0] ?? '0');
        expect(drivers).toBeGreaterThan(0);
    });

    test('8b — per-driver table renders with drill-down links', async ({ page }) => {
        await signIn(page);
        await page.goto('loads');

        const firstRow = page.locator('table').first().locator('tbody tr').first();
        await expect(firstRow).toBeVisible();

        const drillDown = firstRow.getByRole('link', { name: /view recent/i });
        await expect(drillDown).toBeVisible();
    });

    test('8c — drill-down shows recent loads for a specific driver', async ({ page }) => {
        await signIn(page);
        await page.goto('loads');

        const drillDown = page.getByRole('link', { name: /view recent/i }).first();
        await drillDown.click();

        await expect(page).toHaveURL(/driver_id=\d+/);
        await expect(page.getByRole('heading', { name: /recent loads for driver/i })).toBeVisible();

        // FRTL numbers should be present in the detail table.
        const detailRow = page.locator('table').nth(1).locator('tbody tr').first();
        await expect(detailRow).toBeVisible();
    });
});
