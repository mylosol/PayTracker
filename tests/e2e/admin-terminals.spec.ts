import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Section 27 of docs/qa/test_plan.md — Begin Empty Locations admin
 * CRUD against /admin/terminals.
 *
 * The spec drives the surface end-to-end:
 *   - 27a anonymous gate
 *   - 27b index renders + nav link from /admin
 *   - 27c create with QA-TEST prefix
 *   - 27d edit + name-collision refusal
 *   - 27e deactivate hides from /loads/new
 *   - 27f reactivate restores it
 *
 * Test rows are named `QA TEST hub …, FL` so an SQL sweep can clean
 * them up cheaply if the test exits early.
 */
test.describe('admin — Begin Empty Locations', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    // Random-ish suffix so reruns don't collide on the UNIQUE(name)
    // constraint. The QA TEST prefix is the cleanup marker.
    const seedName = () =>
        'QA TEST hub ' + Math.floor(Math.random() * 9_000_000 + 1_000_000) + ', FL';

    test('27a — anonymous /admin/terminals redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('admin/terminals');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('27b — admin index links to /admin/terminals', async ({ page }) => {
        await signIn(page);
        await page.goto('admin');
        await expect(page.getByRole('link', { name: /begin empty locations/i })).toBeVisible();
        await page.getByRole('link', { name: /begin empty locations/i }).click();
        await expect(page).toHaveURL(/\/admin\/terminals$/);
        await expect(page.getByRole('heading', { name: /begin empty locations/i })).toBeVisible();
    });

    test('27c–f — create, edit, collision refusal, deactivate, reactivate', async ({ page }) => {
        await signIn(page);

        // ---- 27c. Create a QA TEST row -----------------------------
        const name = seedName();
        await page.goto('admin/terminals/new');
        await page.locator('#name').fill(name);
        // Leave city_id at "not linked".
        await page.getByRole('button', { name: /^create$/i }).click();
        await expect(page).toHaveURL(/\/admin\/terminals$/);
        await expect(page.getByText(new RegExp(`added begin empty location "${escapeRe(name)}"`, 'i'))).toBeVisible();

        // ---- 27d. Edit + collision ---------------------------------
        const row = page.locator('tbody tr', { hasText: name });
        await expect(row).toBeVisible();
        await row.getByRole('link', { name: /^edit$/i }).click();
        const renamed = name.replace(', FL', ' renamed, FL');
        await page.locator('#name').fill(renamed);
        await page.getByRole('button', { name: /^save$/i }).click();
        await expect(page.getByText(new RegExp(`updated begin empty location "${escapeRe(renamed)}"`, 'i'))).toBeVisible();

        // Collision refusal — try to rename to "Panama City, FL"
        // which the legacy backfill guarantees is already there.
        const renamedRow = page.locator('tbody tr', { hasText: renamed });
        await renamedRow.getByRole('link', { name: /^edit$/i }).click();
        await page.locator('#name').fill('Panama City, FL');
        await page.getByRole('button', { name: /^save$/i }).click();
        // We should still be on the edit page with a refusal flash.
        await expect(page).toHaveURL(/\/admin\/terminals\/\d+\/edit$/);
        await expect(page.getByText(/could not save/i)).toBeVisible();

        // Go back to the index and find our row (still named "renamed").
        await page.goto('admin/terminals');
        const liveRow = page.locator('tbody tr', { hasText: renamed });
        await expect(liveRow.locator('.pill.ok', { hasText: /active/i })).toBeVisible();

        // ---- 27e. Deactivate ---------------------------------------
        page.once('dialog', d => d.accept());
        await liveRow.getByRole('button', { name: /^deactivate$/i }).click();
        await expect(page.getByText(new RegExp(`deactivated "${escapeRe(renamed)}"`, 'i'))).toBeVisible();
        const deadRow = page.locator('tbody tr', { hasText: renamed });
        await expect(deadRow.locator('.pill.warn', { hasText: /inactive/i })).toBeVisible();

        // Picker no longer surfaces it.
        await page.goto('loads/new');
        const pickupOptions = await page.locator('#pickup_city option').allTextContents();
        expect(pickupOptions.some(t => t.trim() === renamed)).toBe(false);

        // ---- 27f. Reactivate ---------------------------------------
        await page.goto('admin/terminals');
        const revivedRow = page.locator('tbody tr', { hasText: renamed });
        await revivedRow.getByRole('button', { name: /^reactivate$/i }).click();
        await expect(page.getByText(new RegExp(`reactivated "${escapeRe(renamed)}"`, 'i'))).toBeVisible();
        const greenRow = page.locator('tbody tr', { hasText: renamed });
        await expect(greenRow.locator('.pill.ok', { hasText: /active/i })).toBeVisible();
    });
});

/**
 * Escape RegExp metacharacters in a string so it can be safely
 * inlined into a `new RegExp(...)` call. The test row name carries
 * a comma and a period, both of which are regex-significant.
 */
function escapeRe(s: string): string {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
