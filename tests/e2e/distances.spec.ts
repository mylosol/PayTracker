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

        // Scope source-name assertions to the "Backfill summary" card. The
        // page also renders 25 sample rows; a page-wide getByText would
        // resolve to 10+ elements. Even card-scoped, getByText(/largeMiles/)
        // matches BOTH the "largeMiles" row and the "pcola_largeMiles" row
        // because the former is a substring of the latter. Read the card's
        // textContent and assert with a negative-lookbehind-style regex
        // (no `_` before "largeMiles") to disambiguate.
        const summaryCard = page.locator('div.card', { hasText: /backfill summary/i });
        const summaryText = (await summaryCard.textContent()) ?? '';
        expect(summaryText).toMatch(/(^|[^_])largeMiles/);
        expect(summaryText).toContain('pcola_largeMiles');

        // "Total rows: <code>NNN</code>" — assert it's > 0.
        const totalText = await summaryCard.getByText(/total rows:/i).textContent();
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
