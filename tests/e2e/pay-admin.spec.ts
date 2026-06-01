import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 10 of docs/qa/test_plan.md — the modern pay-rate admin
 * surface (/pay-admin).
 *
 * The tests mutate the Pensacola round-trip draft and current stages
 * against the preview DB. We chose Pensacola RT because both Default and
 * Current already exist with non-trivial data (the preview sampler showed
 * 114 rows in each), so resetCurrentToDefault is a safe round-trip.
 *
 * Cleanup: 10g promotes the draft → current; 10h resets back to default.
 * The tests run sequentially in a single describe block to ensure 10h
 * lands LAST, so the preview's Pensacola RT Current table ends each
 * Playwright run in the factory-default state. No qa-cleanup hook is
 * needed because there are no orphan rows to sweep — we only touch
 * stages, never blob columns.
 *
 * If a test fails mid-sequence the preview channel's Pensacola RT Current
 * may end up in a draft-promoted state. That's still production-data-safe
 * (preview only) and re-running the suite repairs it via the trailing 10h.
 */
test.describe.serial('pay-rate admin (write path)', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    // Each card on /pay-admin is rendered in its own .card div. We scope
    // our assertions to the Pensacola Round-trip card so changes to other
    // buckets don't leak into the test.
    const PENSACOLA_RT = /pensacola — round-trip/i;

    test('10a — anonymous /pay-admin redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('pay-admin');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('10b — page renders with backfilled data', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        await expect(page.getByRole('heading', { name: /pay-rate admin/i })).toBeVisible();
        // Summary card shows non-zero total
        await expect(page.locator('div.card', { hasText: /summary/i })).toContainText(/Total rate rows/i);
        // All three editor cards present
        await expect(page.getByRole('heading', { name: PENSACOLA_RT })).toBeVisible();
        await expect(page.getByRole('heading', { name: /pensacola — long-haul/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /panama city — round-trip/i })).toBeVisible();
    });

    test('10c — start draft from current', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: PENSACOLA_RT });

        // If a prior failed run left a draft around, this button won't be
        // visible. In that case we hit "Reset draft to current" first to
        // get back to a clean known state.
        const startButton = card.getByRole('button', { name: /start draft from current/i });
        if (await startButton.isVisible()) {
            await startButton.click();
        } else {
            await card.getByRole('button', { name: /reset draft to current/i }).click();
        }

        await expect(page.getByText(/draft started for pensacola \(round_trip\)/i)).toBeVisible();
        // Promote button now visible — proves we're in draft state
        await expect(
            page.locator('div.card', { hasText: PENSACOLA_RT })
                .getByRole('button', { name: /promote draft/i })
        ).toBeVisible();
    });

    test('10d — edit a draft tier', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: PENSACOLA_RT });

        // Find the row for miles=100 and use ITS Save form. Scope by
        // hidden miles input value to avoid clicking the wrong row's Save.
        const row100Form = card.locator('form', {
            has: page.locator('input[name="miles"][value="100"]'),
        }).filter({ has: page.locator('input[name="rate"]') }).first();
        await row100Form.locator('input[name="rate"]').fill('999.9999');
        await row100Form.getByRole('button', { name: /save/i }).click();

        await expect(page.getByText(/saved tier 100 → 999\.9999.*pensacola.*round_trip.*draft/i)).toBeVisible();
    });

    test('10e — add a new tier', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: PENSACOLA_RT });

        // The "Add tier" form is the last form on the card — scope by its
        // placeholder-bearing rate input.
        const addForm = card.locator('form', {
            has: page.locator('input[name="rate"][placeholder*="85.1492"]'),
        });
        await addForm.locator('input[name="miles"]').fill('9999');
        await addForm.locator('input[name="rate"]').fill('123.4567');
        await addForm.getByRole('button', { name: /add to draft/i }).click();

        await expect(page.getByText(/saved tier 9999 → 123\.4567/i)).toBeVisible();
    });

    test('10f — delete the newly added tier', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: PENSACOLA_RT });

        // Auto-confirm the JS confirm() prompt before clicking Delete.
        page.once('dialog', (d) => d.accept());

        const row9999Form = card.locator('form', {
            has: page.locator('input[name="miles"][value="9999"]'),
        }).filter({ hasText: /delete/i }).first();
        await row9999Form.getByRole('button', { name: /delete/i }).click();

        await expect(page.getByText(/deleted tier 9999 from pensacola \(round_trip\) draft/i)).toBeVisible();
    });

    test('10g — promote draft to current', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: PENSACOLA_RT });

        page.once('dialog', (d) => d.accept());
        await card.getByRole('button', { name: /promote draft/i }).click();

        await expect(page.getByText(/promoted draft to current for pensacola \(round_trip\)/i)).toBeVisible();
        // Back to "no draft" state — Start button visible again
        await expect(
            page.locator('div.card', { hasText: PENSACOLA_RT })
                .getByRole('button', { name: /start draft from current/i })
        ).toBeVisible();
    });

    test('10h — reset current ← default (restores preview state)', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: PENSACOLA_RT });

        page.once('dialog', (d) => d.accept());
        await card.getByRole('button', { name: /reset current/i }).click();

        await expect(page.getByText(/reset pensacola \(round_trip\) rates to defaults/i)).toBeVisible();
    });
});
