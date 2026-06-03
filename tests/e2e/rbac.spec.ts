import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 17 of docs/qa/test_plan.md — the RBAC foundation.
 *
 * The QA account (mylosol@gmail.com) gets promoted to 'super_admin'
 * by the 2026_06_03_001 migration. These tests verify the home
 * page exposes the admin nav block for that account, and that the
 * three admin surfaces accept the request (200 / no 403 page).
 *
 * Negative testing of the User role (verifying the 403 page
 * renders correctly for a base User who tries to reach /pay-admin)
 * is deferred to a manual walk in the QA plan until we have a
 * seeded User-role test account on preview. The unit tests in
 * tests/Unit/Models/AccountTest.php cover the hasRole() math
 * against every rung of the hierarchy.
 */
test.describe('RBAC — admin nav and access', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('17a — signed-in home surfaces the admin links for super_admin', async ({ page }) => {
        await signIn(page);
        await page.goto('');
        // Three admin-only nav links must be visible because the QA
        // account is super_admin (which inherits admin).
        await expect(page.getByRole('link', { name: /manage locations/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /city distances/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /pay-rate admin/i })).toBeVisible();
        // Driver-facing links must still be visible.
        await expect(page.getByRole('link', { name: /my pay/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /driver loads/i })).toBeVisible();
    });

    test('17b — super_admin can open /pay-admin without a 403', async ({ page }) => {
        await signIn(page);
        const res = await page.goto('pay-admin');
        expect(res?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: /pay-rate admin/i })).toBeVisible();
        // 403 view's heading must NOT appear.
        await expect(page.getByRole('heading', { name: /access denied/i })).toHaveCount(0);
    });

    test('17c — super_admin can open /locations without a 403', async ({ page }) => {
        await signIn(page);
        const res = await page.goto('locations');
        expect(res?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: /access denied/i })).toHaveCount(0);
    });

    test('17d — super_admin can open /distances without a 403', async ({ page }) => {
        await signIn(page);
        const res = await page.goto('distances');
        expect(res?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: /access denied/i })).toHaveCount(0);
    });
});
