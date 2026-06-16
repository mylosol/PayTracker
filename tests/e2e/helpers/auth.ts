import { expect, type Page } from '@playwright/test';

/**
 * Credentials for the QA test account on the preview channel.
 *
 * The preview deploy syncs the password from the same env vars before
 * Playwright runs — see deploy-preview.yml. So whatever value is set here
 * is also the value live in the database when the test executes.
 *
 * When unset (e.g. local development before secrets are configured), the
 * tests that need credentials should call `requireCredentials()` which
 * fails with a clear message rather than rendering a confusing login
 * failure.
 */
export const QA_USER = process.env.QA_TEST_USER ?? '';
export const QA_PASS = process.env.QA_TEST_PASSWORD ?? '';

export function hasCredentials(): boolean {
    return QA_USER !== '' && QA_PASS !== '';
}

/**
 * Sign in via the modern auth form. Asserts the form submits cleanly and
 * the post-login redirect lands on the home page with the welcome card.
 *
 * Idempotent: if the page is already authenticated, this navigates home
 * and returns without re-submitting.
 */
export async function signIn(page: Page): Promise<void> {
    if (!hasCredentials()) {
        throw new Error('signIn() called without QA_TEST_USER / QA_TEST_PASSWORD set — guard your test with hasCredentials() first.');
    }

    await page.goto('login');

    // If we somehow already had a session, the login route bounces home --
    // so the URL no longer ends in /login. Test for that semantic
    // ("am I still on the login page?") instead of hard-coding a domain,
    // which would have to change on every host migration.
    if (!page.url().endsWith('/login')) {
        return;
    }

    await page.locator('#handle').fill(QA_USER);
    await page.locator('#password').fill(QA_PASS);
    await page.getByRole('button', { name: /sign in/i }).click();

    // Wait for the post-login redirect. The home page has a "Welcome, "
    // heading we can wait on as a positive-state assertion.
    await expect(page.getByRole('heading', { name: /welcome,/i })).toBeVisible();
}

/**
 * Sign the current session out. Uses the form button on the home page.
 */
export async function signOut(page: Page): Promise<void> {
    await page.goto('');
    // The layout renders Sign-out buttons in both the desktop nav AND
    // the (hidden) mobile drawer, so the role lookup matches twice
    // even at desktop viewport. Pick the first visible one.
    const signOut = page.getByRole('button', { name: /sign out/i }).first();
    if (await signOut.isVisible()) {
        await signOut.click();
        await expect(page).toHaveURL(/\/login$/);
    }
}
