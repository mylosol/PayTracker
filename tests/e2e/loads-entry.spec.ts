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

    test('9c — empty submission is rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');
        await page.locator('form').evaluate((f) => (f as HTMLFormElement).noValidate = true);
        await page.getByRole('button', { name: /add load/i }).click();
        await expect(page.getByText(/pick-up and delivery cities are required/i)).toBeVisible();
    });

    test('9d — same pickup and delivery rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');
        // pickup_city is now a <select> restricted to terminals.
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Panama City, FL');
        await page.getByRole('button', { name: /add load/i }).click();
        await expect(page.getByText(/cannot be the same city/i)).toBeVisible();
    });

    test('9e — unknown delivery city rejected', async ({ page }) => {
        // Pickup is now a constrained <select>, so the "unknown pickup"
        // case is structurally impossible. Cover the delivery-side check
        // instead — same controller branch, just the other input.
        await signIn(page);
        await page.goto('loads/new');
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Nowhereville, ZZ');
        await page.getByRole('button', { name: /add load/i }).click();
        await expect(page.getByText(/not in the city list/i)).toBeVisible();
    });

    test('9f — valid submission inserts and shows frtl in flash', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');

        // Use a pair we KNOW is in city_distances (the backfill loaded
        // both of these). If the deploy ever regresses this assumption
        // the test will surface it.
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Lynn Haven, FL');
        await page.locator('input[name="load_type"][value="0"]').check();
        await page.locator('#extra_pay').fill('0');
        await page.locator('#notes').fill('QA TEST automated load-entry — safe to clean up');
        await page.getByRole('button', { name: /add load/i }).click();

        // PRG completes on /loads with a flash that includes "frtl=NNN".
        await expect(page).toHaveURL(/\/loads$/);
        await expect(page.getByText(/added load frtl=\d+/i)).toBeVisible();
        await expect(page.getByText(/Panama City, FL.*Lynn Haven, FL/i)).toBeVisible();
    });
});
