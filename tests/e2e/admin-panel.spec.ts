import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Section 16 of docs/qa/test_plan.md — the Admin Panel user
 * management surface.
 *
 * The QA account is super_admin (so it can reach /admin). We
 * intentionally do NOT exercise the destructive actions
 * (ban / delete / reset-password) against arbitrary rows here
 * because that would mutate live preview data outside the
 * notes-prefixed QA cleanup window. Those paths are verified
 * by the manual walk in the QA plan instead.
 *
 * What IS covered:
 *   - 16a: /admin renders for super_admin with the user table
 *   - 16b: self-row carries a 'you' marker and exposes no action buttons
 *   - 16c: nav from home page reaches /admin
 *   - 16d: password-reset claim page returns 410 Gone for a bogus token
 */
test.describe('Admin Panel — user management', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('16a — /admin renders with user table for super_admin', async ({ page }) => {
        await signIn(page);
        const res = await page.goto('admin');
        expect(res?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: /admin panel/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /user accounts/i })).toBeVisible();
        // Table headers we expect to see.
        await expect(page.getByRole('columnheader', { name: /^role$/i })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: /last login/i })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: /status/i })).toBeVisible();
    });

    test('16b — actor row shows "you" badge and no action buttons', async ({ page }) => {
        await signIn(page);
        await page.goto('admin');
        // Find the row containing the 'you' chip and confirm it lacks
        // Ban / Delete / Reset PW buttons — the self-action guard
        // surfaces on the page, not just the server.
        const selfRow = page.locator('tr').filter({ hasText: /\byou\b/i }).first();
        await expect(selfRow).toBeVisible();
        await expect(selfRow.getByRole('button', { name: /^ban$/i })).toHaveCount(0);
        await expect(selfRow.getByRole('button', { name: /^delete$/i })).toHaveCount(0);
        await expect(selfRow.getByRole('button', { name: /reset pw/i })).toHaveCount(0);
        // The "no self-actions" muted note IS visible.
        await expect(selfRow.getByText(/no self-actions/i)).toBeVisible();
    });

    test('16c — home nav surfaces an Admin Panel link for super_admin', async ({ page }) => {
        await signIn(page);
        await page.goto('');
        await expect(page.getByRole('link', { name: /admin panel/i })).toBeVisible();
    });

    test('16d — bogus password-reset token returns 410 Gone', async ({ page }) => {
        // No sign-in: the reset claim path is intentionally public.
        await page.context().clearCookies();
        const res = await page.goto('password-reset/this-is-not-a-real-token');
        expect(res?.status()).toBe(410);
        await expect(page.getByRole('heading', { name: /no longer valid/i })).toBeVisible();
    });
});
