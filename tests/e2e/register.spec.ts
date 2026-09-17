import { expect, test } from '@playwright/test';
import { hasCredentials, QA_PASS, QA_USER, signIn, signOut } from './helpers/auth';

/**
 * Registration end-to-end spec — Section 26 of docs/qa/test_plan.md.
 *
 * Why this exists: prior to 2026-09-16 the /register write path had
 * zero test coverage. The QA Playwright account is seeded via
 * scripts/set-password.php on every deploy, so nothing ever
 * exercised InviteCode::consume. A real invitee hit
 *   SQLSTATE[HY000] 1364 "Field 'emailValid' doesn't have a
 *   default value"
 * because the legacy `emailValid` column wasn't in the insert.
 *
 * This spec walks the flow admins actually use in production:
 *   1. Sign in as admin, mint a fresh invite code
 *   2. Sign out, register as a new user with that code
 *   3. Confirm the auto-sign-in landing (welcome heading)
 *   4. Sign back out and sign in explicitly with the new creds
 *   5. Best-effort delete the test account so /admin stays tidy
 *
 * The username prefix `qatest_` is the cleanup marker. Even when
 * the in-test delete step is skipped (e.g. test aborts early),
 * scripts/qa-cleanup.php --accounts sweeps user LIKE 'qatest_%'
 * after every deploy so the account table doesn't accumulate.
 */

test.describe('registration', () => {
    test.beforeEach(async ({ page }) => {
        // Start each test anonymous to avoid cross-test session bleed.
        await page.context().clearCookies();
        // Auto-accept the JS confirm() on the /admin delete button.
        // Playwright's default is to dismiss unhandled dialogs, which
        // would silently cancel the cleanup step.
        page.on('dialog', dialog => { void dialog.accept(); });
    });

    test.skip(
        !hasCredentials(),
        'QA_TEST_USER / QA_TEST_PASSWORD required — see helpers/auth.ts.'
    );

    test('26a — mint invite → register → sign in → cleanup', async ({ page }) => {
        // ---- 1. Mint a fresh invite as admin ---------------------------
        await signIn(page);
        await page.goto('admin/invites/new');
        // Leave invitee_email blank; the mint step's flash carries the
        // full ?invite=CODE URL regardless. Auto-delete stays on so
        // the code disappears from the invite list after we consume it.
        await page.getByRole('button', { name: /mint code/i }).click();

        // We land on /admin/invites with a flash like
        //   "Created invite ABC12345 (…): https://…/register?invite=ABC12345"
        // Parse the code out of that. The 8-char [A-Z0-9] shape is
        // fixed by InviteCode::normalize.
        await expect(page).toHaveURL(/\/admin\/invites(\?|$)/);
        const flashText = await page.getByRole('status').first().textContent();
        const match = flashText?.match(/invite ([A-Z0-9]{8})/);
        const code  = match?.[1];
        expect(code, `couldn't extract invite code from flash: "${flashText}"`).toBeTruthy();

        // ---- 2. Sign out and register as a fresh user ------------------
        await signOut(page);

        // qatest_ prefix is the cleanup marker (qa-cleanup --accounts).
        // Suffix mixes epoch-ms + random so parallel/repeated runs never
        // collide.
        const suffix   = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
        const newUser  = `qatest_${suffix}`.slice(0, 32); // schema caps at 32
        const newEmail = `${newUser}@example.test`;
        const newPass  = 'RegisterSpec-Password-1!'; // >= 12 chars

        await page.goto(`register?invite=${code}`);
        await page.locator('#username').fill(newUser);
        await page.locator('#email').fill(newEmail);
        await page.locator('#password').fill(newPass);
        await page.locator('#password_confirmation').fill(newPass);
        await page.getByRole('button', { name: /create account/i }).click();

        // ---- 3. Successful register auto-signs in and lands home -------
        await expect(page.getByRole('heading', { name: /welcome,/i })).toBeVisible();
        await expect(page.getByText(new RegExp(newUser, 'i')).first()).toBeVisible();

        // ---- 4. Sign out and sign back in with the new creds ----------
        await signOut(page);
        await expect(page).toHaveURL(/\/login$/);

        await page.locator('#handle').fill(newUser);
        await page.locator('#password').fill(newPass);
        await page.getByRole('button', { name: /sign in/i }).click();
        await expect(page.getByRole('heading', { name: /welcome,/i })).toBeVisible();

        // ---- 5. Best-effort cleanup: delete the account via /admin ----
        // Sign back in as the QA super-admin, find the row, hit Delete.
        // The confirm() dialog is auto-accepted by the beforeEach hook.
        // qa-cleanup.php --accounts sweeps qatest_* nightly as a backstop
        // in case this step is skipped by a mid-test abort above.
        await signOut(page);
        await page.goto('login');
        await page.locator('#handle').fill(QA_USER);
        await page.locator('#password').fill(QA_PASS);
        await page.getByRole('button', { name: /sign in/i }).click();

        await page.goto(`admin?search=${newUser}`);
        const row = page.locator('tr', { hasText: newUser }).first();
        await expect(row).toBeVisible();
        await row.getByRole('button', { name: /^delete$/i }).click();
        // After the POST redirects, the row is gone from the results.
        await expect(page.locator('tr', { hasText: newUser })).toHaveCount(0);
    });
});
