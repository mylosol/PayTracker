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

    // --- Admin CRUD ----------------------------------------------------
    //
    // The override pair is intentionally synthetic ("QA From, ZZ" / "QA To, ZZ")
    // so the qa-cleanup script's cities sweep removes the rows after the
    // suite ends -- they share the "Qa Test" prefix shape it sweeps on.
    // (We pass the prefix directly via --pattern in the workflow.)
    const FROM_CITY = 'Qa Test From, ZZ';
    const TO_CITY   = 'Qa Test To, ZZ';

    test('7e — admin can save a new override + sees it as source=admin', async ({ page }) => {
        await signIn(page);
        await page.goto('distances');

        await page.locator('input[name="from_name"]').fill(FROM_CITY);
        await page.locator('input[name="to_name"]').fill(TO_CITY);
        await page.locator('input[name="miles"]').fill('77');
        await page.getByRole('button', { name: /save override/i }).click();

        await expect(page.getByText(new RegExp(`Set ${FROM_CITY} → ${TO_CITY} = 77 mi`, 'i'))).toBeVisible();

        // The new row shows up under the "All rows" section with the
        // override pill. Search to scope.
        await page.locator('input[name="q"]').fill('Qa Test');
        await page.getByRole('button', { name: /^filter$/i }).click();
        const newRow = page.locator('tbody tr', { hasText: FROM_CITY });
        await expect(newRow).toBeVisible();
        await expect(newRow.locator('.pill.ok', { hasText: /override/i })).toBeVisible();
        await expect(newRow).toContainText(/^.*?\b77\b/);
    });

    test('7f — re-saving the same pair updates miles in place', async ({ page }) => {
        await signIn(page);
        await page.goto('distances');

        // Seed (idempotent re-save uses the existing row).
        await page.locator('input[name="from_name"]').fill(FROM_CITY);
        await page.locator('input[name="to_name"]').fill(TO_CITY);
        await page.locator('input[name="miles"]').fill('77');
        await page.getByRole('button', { name: /save override/i }).click();

        // Bump miles.
        await page.locator('input[name="from_name"]').fill(FROM_CITY);
        await page.locator('input[name="to_name"]').fill(TO_CITY);
        await page.locator('input[name="miles"]').fill('99');
        await page.getByRole('button', { name: /save override/i }).click();

        await expect(page.getByText(new RegExp(`Updated ${FROM_CITY} → ${TO_CITY} = 99 mi`, 'i'))).toBeVisible();
    });

    test('7g — delete on an admin row drops the override', async ({ page }) => {
        await signIn(page);
        await page.goto('distances');

        // Make sure the override exists so we have something to delete.
        await page.locator('input[name="from_name"]').fill(FROM_CITY);
        await page.locator('input[name="to_name"]').fill(TO_CITY);
        await page.locator('input[name="miles"]').fill('55');
        await page.getByRole('button', { name: /save override/i }).click();

        // Filter to scope, then delete the row.
        await page.locator('input[name="q"]').fill('Qa Test');
        await page.getByRole('button', { name: /^filter$/i }).click();
        const row = page.locator('tbody tr', { hasText: FROM_CITY });
        page.once('dialog', d => d.accept());
        await row.getByRole('button', { name: /^delete$/i }).click();

        await expect(page.getByText(/deleted.*qa test from.*qa test to.*source=admin/i)).toBeVisible();
    });
});
