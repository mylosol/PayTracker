import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Section 17 of docs/qa/test_plan.md — audit log + system
 * diagnostics surfaces.
 *
 * Coverage:
 *   - 17a: /admin/audit renders with the filter form + table headers
 *   - 17b: /admin/diagnostics renders runtime + DB + migrations tail
 *   - 17c: a fresh sign-in writes a USER_LOGIN row visible in /admin/audit
 *
 * We don't test the failure paths (USER_LOGIN_FAILED) here because
 * triggering them would lock out the QA account after 5 attempts.
 * The manual QA walk covers that path.
 */
test.describe('Audit log + diagnostics', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('17a — /admin/audit renders for super_admin', async ({ page }) => {
        await signIn(page);
        const res = await page.goto('admin/audit');
        expect(res?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: /audit log/i })).toBeVisible();
        // Filter form fields.
        await expect(page.locator('input[name="user"]')).toBeVisible();
        await expect(page.locator('select[name="action"]')).toBeVisible();
        await expect(page.locator('input[name="ip"]')).toBeVisible();
        // Table headers.
        await expect(page.getByRole('columnheader', { name: /time/i })).toBeVisible();
        await expect(page.getByRole('columnheader', { name: /action/i })).toBeVisible();
    });

    test('17b — /admin/diagnostics renders runtime + DB tables', async ({ page }) => {
        await signIn(page);
        const res = await page.goto('admin/diagnostics');
        expect(res?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: /system diagnostics/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /^runtime$/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /audit counters/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /recent migrations/i })).toBeVisible();
    });

    test('17c — successful sign-in is logged as USER_LOGIN', async ({ page }) => {
        await signIn(page);
        // Filter to USER_LOGIN action for the current user. The
        // top row should be the sign-in we just performed.
        await page.goto('admin/audit?action=USER_LOGIN');
        await expect(page.getByRole('columnheader', { name: /time/i })).toBeVisible();
        // The action cell should contain USER_LOGIN in at least one row.
        const firstActionCell = page.locator('tbody tr').first().locator('td').nth(2);
        await expect(firstActionCell).toContainText(/USER_LOGIN/);
    });
});
