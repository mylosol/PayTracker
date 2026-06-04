import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Section 19 of docs/qa/test_plan.md — Super-Admin-only role
 * assignment surface.
 *
 * The QA account is super_admin, so it can see the role
 * dropdown + Save button on every non-self row. We DON'T
 * exercise an actual promote/demote here because doing so
 * against arbitrary preview rows would mutate live data
 * outside the QA cleanup window AND could leave a row in a
 * state that affects other tests' assumptions.
 *
 * Coverage:
 *   - 19a: role column on a non-self row shows a select + Save
 *   - 19b: actor row shows role as static code, NOT a dropdown
 *     (sole-super-admin / self-target safeguard)
 *   - 19c: form posts to /admin/users/{id}/role with CSRF
 */
test.describe('Super Admin — role assignment', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('19a — non-self rows show role dropdown for super_admin', async ({ page }) => {
        await signIn(page);
        await page.goto('admin');
        // Find a non-self row by excluding the row carrying the 'you' badge.
        const otherRows = page.locator('tbody tr').filter({
            hasNot: page.locator('text=/\\byou\\b/i'),
        });
        // At least one non-self row exists on preview.
        await expect(otherRows.first()).toBeVisible();
        // That row exposes the role select + Save button.
        await expect(otherRows.first().locator('select[name="role"]')).toBeVisible();
        await expect(otherRows.first().getByRole('button', { name: /^save$/i })).toBeVisible();
    });

    test('19b — actor row shows role as static code, no dropdown', async ({ page }) => {
        await signIn(page);
        await page.goto('admin');
        const selfRow = page.locator('tbody tr').filter({ hasText: /\byou\b/i }).first();
        await expect(selfRow).toBeVisible();
        // No dropdown / Save button in the actor row.
        await expect(selfRow.locator('select[name="role"]')).toHaveCount(0);
        await expect(selfRow.getByRole('button', { name: /^save$/i })).toHaveCount(0);
        // Role is still SHOWN — just as immutable <code>.
        await expect(selfRow.locator('code', { hasText: /^super_admin$/ })).toBeVisible();
    });

    test('19c — role form posts to /admin/users/{id}/role with CSRF', async ({ page }) => {
        await signIn(page);
        await page.goto('admin');
        const otherRows = page.locator('tbody tr').filter({
            hasNot: page.locator('text=/\\byou\\b/i'),
        });
        const form = otherRows.first().locator('form').filter({
            has: page.locator('select[name="role"]'),
        });
        await expect(form).toHaveAttribute('action', /\/admin\/users\/\d+\/role$/);
        await expect(form).toHaveAttribute('method', /post/i);
        await expect(form.locator('input[name="_csrf"]')).toHaveCount(1);
    });
});
