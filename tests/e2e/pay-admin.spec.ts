import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Modern pay-rate admin surface (/pay-admin).
 *
 * The tests mutate the round-trip draft and current stages against
 * the preview DB. We chose round-trip because both Default and Current
 * already exist with non-trivial data.
 *
 * Cleanup: 10g promotes the draft → current; 10h resets back to default.
 * The tests run sequentially in a single describe block to ensure 10h
 * lands LAST, so the preview's round-trip Current table ends each
 * Playwright run in the factory-default state.
 *
 * If a test fails mid-sequence the preview channel's round-trip Current
 * may end up in a draft-promoted state. That's still production-data-safe
 * (preview only) and re-running the suite repairs it via the trailing 10h.
 */
test.describe.serial('pay-rate admin (write path)', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    // Each card on /pay-admin is rendered in its own .card div. We scope
    // our assertions to the Round-trip card. The Long-haul card never
    // mentions "round-trip" in its body, so substring matching is safe;
    // anchored regexes don't work here because Playwright's hasText with
    // a RegExp tests against the card's FULL text content (including
    // tier rows and button labels).
    const ROUND_TRIP = /round-trip/i;

    test('10a — anonymous /pay-admin redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('pay-admin');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('10b — page renders with backfilled data', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        await expect(page.getByRole('heading', { name: /pay-rate admin/i })).toBeVisible();
        // Jump-to nav card (replaced the old Summary stats list)
        await expect(page.getByRole('heading', { name: /^Jump to$/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /round-trip rates/i })).toBeVisible();
        // Both editor cards present
        await expect(page.getByRole('heading', { name: /^Round-trip$/ })).toBeVisible();
        await expect(page.getByRole('heading', { name: /^Long-haul$/ })).toBeVisible();
    });

    test('10c — start draft from current', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: ROUND_TRIP });

        const startButton = card.getByRole('button', { name: /start draft from current/i });
        if (await startButton.isVisible()) {
            await startButton.click();
        } else {
            await card.getByRole('button', { name: /reset draft to current/i }).click();
        }

        await expect(page.getByText(/draft started for round_trip/i)).toBeVisible();
        await expect(
            page.locator('div.card', { hasText: ROUND_TRIP })
                .getByRole('button', { name: /promote draft/i })
        ).toBeVisible();
    });

    test('10d — edit a draft tier', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: ROUND_TRIP });

        const row100Form = card.locator('form', {
            has: page.locator('input[name="miles"][value="100"]'),
        }).filter({ has: page.locator('input[name="rate"]') }).first();
        await row100Form.locator('input[name="rate"]').fill('999.9999');
        await row100Form.getByRole('button', { name: /save/i }).click();

        await expect(page.getByText(/saved tier 100 → 999\.9999.*round_trip.*draft/i)).toBeVisible();
    });

    test('10e — add a new tier', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: ROUND_TRIP });

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
        const card = page.locator('div.card', { hasText: ROUND_TRIP });

        page.once('dialog', (d) => d.accept());

        const row9999Form = card.locator('form', {
            has: page.locator('input[name="miles"][value="9999"]'),
        }).filter({ hasText: /delete/i }).first();
        await row9999Form.getByRole('button', { name: /delete/i }).click();

        await expect(page.getByText(/deleted tier 9999 from round_trip draft/i)).toBeVisible();
    });

    test('10g — promote draft to current', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: ROUND_TRIP });

        page.once('dialog', (d) => d.accept());
        await card.getByRole('button', { name: /promote draft/i }).click();

        await expect(page.getByText(/promoted draft to current for round_trip/i)).toBeVisible();
        await expect(
            page.locator('div.card', { hasText: ROUND_TRIP })
                .getByRole('button', { name: /start draft from current/i })
        ).toBeVisible();
    });

    test('10h — reset current ← default (restores preview state)', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: ROUND_TRIP });

        page.once('dialog', (d) => d.accept());
        await card.getByRole('button', { name: /reset current/i }).click();

        await expect(page.getByText(/reset round_trip rates to defaults/i)).toBeVisible();
    });
});
