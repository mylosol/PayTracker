import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 6 of docs/qa/test_plan.md.
 *
 * Uses a unique NATO-phonetic suffix per test run so concurrent CI runs
 * (or the same one with retries) don't collide on the duplicate-detection
 * test. The qa-cleanup.php script's `Qa Test %` pattern matches these so
 * data hygiene is automatic if the script runs after the suite.
 */

const NATO = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel', 'India'];

function uniqueCity(): string {
    // Random NATO word + a timestamp-derived disambiguator (letters only —
    // the validation rejects digits). We bucketise the minute count into
    // a letter to satisfy the no-digit rule while still being unique per
    // run.
    const word = NATO[Math.floor(Math.random() * NATO.length)];
    const bucket = String.fromCharCode(65 + (new Date().getMinutes() % 26));
    return `Qa Test ${word} ${bucket}`;
}

test.describe('locations (add-city)', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('6a — anonymous /locations redirects to /login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('locations');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('6b — signed-in list page renders', async ({ page }) => {
        await signIn(page);
        await page.goto('locations');
        await expect(page.getByRole('heading', { name: /^locations$/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /\+ add city/i })).toBeVisible();
        await expect(page.getByText(/\d+ cities on file/i)).toBeVisible();
    });

    test('6c — empty submission is rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('locations/new');
        // Bypass HTML5 validation by setting the field then submitting via JS.
        await page.locator('#name').fill('');
        await page.locator('#state').selectOption('FL');
        await page.locator('main form').first().evaluate((f) => (f as HTMLFormElement).noValidate = true);
        await page.getByRole('button', { name: /add city/i }).click();
        await expect(page.getByText(/enter both a city name and a state/i)).toBeVisible();
    });

    test('6c — digits in city name are rejected', async ({ page }) => {
        await signIn(page);
        await page.goto('locations/new');
        await page.locator('main form').first().evaluate((f) => (f as HTMLFormElement).noValidate = true);
        await page.locator('#name').fill('Testville2');
        await page.locator('#state').selectOption('FL');
        await page.getByRole('button', { name: /add city/i }).click();

        // Match the flash banner specifically. The static helper text under
        // the input ("Letters, spaces, periods, hyphens, apostrophes only.")
        // would also satisfy a "letters, spaces, periods, hyphens" regex —
        // anchor on the flash's unique opening phrase instead.
        await expect(page.getByText(/city name may only contain/i)).toBeVisible();
        // Value preserved across the failed redirect — UX guarantee.
        await expect(page.locator('#name')).toHaveValue('Testville2');
    });

    test('6d + 6e — valid submission inserts, duplicate is rejected', async ({ page }) => {
        await signIn(page);
        const cityName = uniqueCity();
        const fullName = `${cityName}, FL`;

        // First submission succeeds.
        await page.goto('locations/new');
        await page.locator('#name').fill(cityName);
        await page.locator('#state').selectOption('FL');
        await page.getByRole('button', { name: /add city/i }).click();
        await expect(page).toHaveURL(/\/locations$/);
        await expect(page.getByText(new RegExp(`added\\s+"${fullName.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')}"`, 'i'))).toBeVisible();

        // Second submission with the same name is rejected as duplicate.
        await page.goto('locations/new');
        await page.locator('#name').fill(cityName);
        await page.locator('#state').selectOption('FL');
        await page.getByRole('button', { name: /add city/i }).click();
        await expect(page.getByText(new RegExp(`"${fullName.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')}" is already in the list`, 'i'))).toBeVisible();
    });
});
