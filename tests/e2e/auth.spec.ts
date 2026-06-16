import { test, expect } from '@playwright/test';
import { hasCredentials, QA_USER, QA_PASS, signIn, signOut } from './helpers/auth';

/**
 * Mirrors Section 5 of docs/qa/test_plan.md.
 *
 * Skips wholesale when QA_TEST_USER / QA_TEST_PASSWORD are unset, with a
 * clear reason — keeps local-dev unblocked without secrets and avoids
 * confusing "wrong password" failures in CI when the cause is missing
 * configuration.
 */
test.describe('auth flow', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test.beforeEach(async ({ page }) => {
        // Each spec starts anonymous to keep cross-test interference at zero.
        await page.context().clearCookies();
    });

    test('5a — anonymous landing offers Sign in', async ({ page }) => {
        await page.goto('');
        await expect(page.getByRole('heading', { name: /sign in to continue/i })).toBeVisible();
        // The new shell renders a Sign in link in the header AND a
        // Sign in button on the hero card. Both should be reachable;
        // assert at least one is visible.
        await expect(page.getByRole('link', { name: /sign in/i }).first()).toBeVisible();
    });

    test('5a — wrong password rejected with generic banner', async ({ page }) => {
        await page.goto('login');
        await page.locator('#handle').fill(QA_USER);
        await page.locator('#password').fill('definitely-not-it');
        await page.getByRole('button', { name: /sign in/i }).click();

        // Generic message — must not reveal which part was wrong.
        await expect(page.getByText(/incorrect login or password/i)).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
    });

    test('5b — correct credentials reach the dashboard', async ({ page }) => {
        await signIn(page);
        await expect(page.getByRole('heading', { name: /welcome,/i })).toBeVisible();
        await expect(page.getByText(/signed in/i)).toBeVisible();
    });

    test('5c — sign-out invalidates the session', async ({ page }) => {
        await signIn(page);
        await signOut(page);
        await expect(page).toHaveURL(/\/login$/);

        // Browser-back should NOT restore the authenticated view.
        await page.goBack();
        await page.goto(''); // re-fetch to defeat any browser cache
        await expect(page.getByRole('heading', { name: /sign in to continue/i })).toBeVisible();
    });
});
