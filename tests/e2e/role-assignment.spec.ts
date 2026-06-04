import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Section 18 of docs/qa/test_plan.md — Super-Admin-only role
 * assignment surface.
 *
 * The QA account is super_admin, so it can see the role
 * dropdown + Save button on every non-self row. We DON'T
 * exercise an actual promote/demote here because doing so
 * against arbitrary preview rows would mutate live data
 * outside the QA cleanup window AND could leave a row in a
 * state that affects other tests' assumptions.
 */
test.describe('Super Admin — role assignment', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('19a — at least one row has the role dropdown for super_admin', async ({ page }) => {
        await signIn(page);
        await page.goto('admin');
        // Filter rows DOWN to the ones carrying the role select. That's
        // the cleaner direction than "all rows except the self row" --
        // we directly assert the feature exists somewhere in the table.
        const rowsWithDropdown = page.locator('tbody tr').filter({
            has: page.locator('select[name="role"]'),
        });
        await expect(rowsWithDropdown.first()).toBeVisible();
        await expect(rowsWithDropdown.first().getByRole('button', { name: /^save$/i })).toBeVisible();
        // The dropdown must offer all three roles.
        const options = await rowsWithDropdown.first().locator('select[name="role"] option').allTextContents();
        const trimmed = options.map(o => o.trim());
        expect(trimmed).toEqual(expect.arrayContaining(['user', 'admin', 'super_admin']));
    });

    test('19b — actor row shows role as static code, no dropdown', async ({ page }) => {
        await signIn(page);
        await page.goto('admin');
        const selfRow = page.locator('tbody tr').filter({ hasText: /\byou\b/i }).first();
        await expect(selfRow).toBeVisible();
        // No dropdown / Save button in the actor row -- self-target guard.
        await expect(selfRow.locator('select[name="role"]')).toHaveCount(0);
        await expect(selfRow.getByRole('button', { name: /^save$/i })).toHaveCount(0);
    });

    test('19c — role form posts to /admin/users/{id}/role with CSRF', async ({ page }) => {
        await signIn(page);
        await page.goto('admin');
        const form = page.locator('form').filter({
            has: page.locator('select[name="role"]'),
        }).first();
        await expect(form).toHaveAttribute('action', /\/admin\/users\/\d+\/role$/);
        await expect(form).toHaveAttribute('method', /post/i);
        await expect(form.locator('input[name="_csrf"]')).toHaveCount(1);
    });
});
