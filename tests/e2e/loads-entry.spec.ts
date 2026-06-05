import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 9 of docs/qa/test_plan.md — the modern load-entry write
 * path (/loads/new).
 *
 * These tests insert real rows into driver_loads on the preview channel.
 * The qa-cleanup.php script's notes-prefixed marker (`QA TEST`) is what
 * lets the cleanup sweep them after the suite finishes. Any test that
 * inserts MUST set notes to a string that starts with `QA TEST ` so the
 * cleanup script's LIKE pattern catches it.
 */
test.describe('load entry (write path)', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('9a — anonymous /loads/new redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('loads/new');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('9b — signed-in form renders with pickup/delivery and CSRF', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');
        await expect(page.getByRole('heading', { name: /add a load/i })).toBeVisible();
        await expect(page.locator('#pickup_city')).toBeVisible();
        await expect(page.locator('#delivery_city')).toBeVisible();
        // CSRF token must be present as a hidden field — the POST handler
        // refuses anything else.
        await expect(page.locator('input[name="_csrf"]')).toHaveCount(1);
    });

    test('9c — empty submission is rejected (FRTL required)', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');
        await page.locator('form').evaluate((f) => (f as HTMLFormElement).noValidate = true);
        await page.getByRole('button', { name: /add load/i }).click();
        // FRTL is the FIRST required check now — drivers who don't
        // have one should use the "Store Load Info" scratchpad path
        // instead (covered in loads-scratchpad.spec.ts).
        await expect(page.getByText(/frtl # is required to save a load/i)).toBeVisible();
    });

    // Pick a FRTL well above the existing-data range so reruns don't collide
    // with rows already on file. The cleanup script sweeps these after
    // every Playwright pass via the notes-prefix marker.
    const qaFrtl = () => 999_000_000 + Math.floor(Math.random() * 999_999);

    test('9d — same pickup and delivery rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');
        await page.locator('#frtl').fill(String(qaFrtl()));
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Panama City, FL');
        await page.getByRole('button', { name: /add load/i }).click();
        await expect(page.getByText(/cannot be the same city/i)).toBeVisible();
    });

    test('9e — unknown delivery city rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');
        await page.locator('#frtl').fill(String(qaFrtl()));
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Nowhereville, ZZ');
        await page.getByRole('button', { name: /add load/i }).click();
        await expect(page.getByText(/not in the city list/i)).toBeVisible();
    });

    test('9f — valid submission inserts and shows frtl in flash', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');

        const frtl = qaFrtl();
        await page.locator('#frtl').fill(String(frtl));
        // Use a pair we KNOW is in city_distances (the backfill loaded
        // both of these). If the deploy ever regresses this assumption
        // the test will surface it.
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Lynn Haven, FL');
        await page.locator('input[name="load_type"][value="0"]').check();
        await page.locator('#extra_pay').fill('0');
        await page.locator('#notes').fill('QA TEST automated load-entry — safe to clean up');
        await page.getByRole('button', { name: /add load/i }).click();

        // PRG completes on /dashboard (since /loads is super-admin-only
        // now). The flash carries the user-typed FRTL — NOT a synthetic
        // one, since auto-assign is gone.
        await expect(page).toHaveURL(/\/dashboard(\?|$)/);
        await expect(page.getByText(new RegExp(`added load frtl=${frtl}\\b`, 'i'))).toBeVisible();
        await expect(page.getByText(/Panama City, FL/i).first()).toBeVisible();
    });

    test('9g — blank FRTL with disabled HTML5 validation is server-rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');

        // The HTML5 `required` attribute on #frtl would normally block
        // submission. We disable it here to exercise the SERVER's
        // required-FRTL rule (defense in depth — JS-off clients hit
        // the same friendly error).
        await page.locator('form').evaluate((f) => (f as HTMLFormElement).noValidate = true);
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Lynn Haven, FL');
        await page.locator('input[name="load_type"][value="0"]').check();
        await page.locator('#extra_pay').fill('0');
        await page.locator('#notes').fill('QA TEST automated load-entry (blank frtl) — safe to clean up');
        await page.getByRole('button', { name: /add load/i }).click();

        await expect(page.getByText(/frtl # is required to save a load/i)).toBeVisible();
    });
});
