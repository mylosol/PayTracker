import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 7 of docs/qa/test_plan.md — the city_distances backfill
 * verification page.
 */
test.describe('city distances', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('7d — anonymous /distances redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('distances');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('7a — summary shows non-zero counts and both sources', async ({ page }) => {
        await signIn(page);
        await page.goto('distances');

        await expect(page.getByRole('heading', { name: /city distances/i })).toBeVisible();
        // Both legacy matrices must have contributed rows.
        await expect(page.getByText(/largeMiles/)).toBeVisible();
        await expect(page.getByText(/pcola_largeMiles/)).toBeVisible();

        // "Total rows: <code>NNN</code>" — assert it's > 0.
        const totalText = await page.getByText(/total rows:/i).textContent();
        const total = Number(totalText?.match(/\d+/)?.[0] ?? '0');
        expect(total).toBeGreaterThan(0);
    });

    test('7b — sample table renders rows with positive miles', async ({ page }) => {
        await signIn(page);
        await page.goto('distances');

        const firstRow = page.locator('table tbody tr').first();
        await expect(firstRow).toBeVisible();
        const cells = await firstRow.locator('td').allTextContents();
        // [from, to, miles, source]
        expect(cells.length).toBeGreaterThanOrEqual(4);
        const miles = Number(cells[2].trim());
        expect(miles).toBeGreaterThan(0);
        // From and To are not identical (no self-distances in the backfill).
        expect(cells[0].trim()).not.toBe(cells[1].trim());
    });

    test('7c — pair lookup with non-existent pair returns the no-match message', async ({ page }) => {
        await signIn(page);
        await page.goto('distances');

        await page.locator('input[name="from"]').fill('Definitely Not A City, ZZ');
        await page.locator('input[name="to"]').fill('Also Not A City, ZZ');
        await page.getByRole('button', { name: /look up/i }).click();

        await expect(page.getByText(/no recorded distance/i)).toBeVisible();
    });
});
