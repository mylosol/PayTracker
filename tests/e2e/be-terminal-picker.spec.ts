import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Section 28 of docs/qa/test_plan.md — the Begin Empty terminal
 * picker on the load entry form.
 *
 * What we exercise here (and what we deliberately don't):
 *   - The picker is gated to one-way (28a).
 *   - Selecting a real terminal fires /loads/preview-be-miles and
 *     fills + locks the miles input (28b).
 *   - "Other" releases the input for manual entry (28c).
 *   - The empty placeholder zeros + locks the input (28d).
 *
 * We don't actually save loads here — the existing loads-entry
 * spec covers the save path. The picker spec is scoped to the
 * UI plumbing.
 */
test.describe('loads/new — Begin Empty terminal picker', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    test('28a — Begin Empty checkbox + Round-trip combine to gate the section', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');

        const wrapper = page.locator('#begin-empty-wrapper');
        const beBox   = page.locator('#begin_empty_checkbox');

        // Default state: checkbox off → wrapper hidden even on one-way.
        await expect(beBox).not.toBeChecked();
        await expect(wrapper).toBeHidden();

        // Tick Begin Empty → wrapper reveals.
        await beBox.check();
        await expect(wrapper).toBeVisible();

        // Switch to round-trip → wrapper hides AND the checkbox
        // auto-unticks so flipping back to one-way doesn't surprise
        // the driver with the section reappearing.
        await page.locator('input[name="load_type"][value="1"]').check();
        await expect(wrapper).toBeHidden();
        await expect(beBox).not.toBeChecked();

        // Back to one-way: still hidden until the box is ticked again.
        await page.locator('input[name="load_type"][value="0"]').check();
        await expect(wrapper).toBeHidden();
        await beBox.check();
        await expect(wrapper).toBeVisible();
    });

    test('28b — picking a terminal fills + locks miles', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');

        // Drive the rest of the form into a sensible state first so
        // /preview-be-miles has a pickup to resolve against.
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Lynn Haven, FL');
        await page.locator('input[name="load_type"][value="0"]').check();
        // Reveal the Begin Empty section.
        await page.locator('#begin_empty_checkbox').check();

        // Pick a terminal that's guaranteed to exist post-backfill.
        const picker = page.locator('#begin_empty_terminal');
        await picker.selectOption({ label: 'Niceville, FL' });

        // Status line surfaces "N mi from Niceville, FL → Panama City, FL"
        // -- pattern-match instead of asserting an exact number, since the
        // cache content may shift across deploys.
        const status = page.locator('#begin-empty-status');
        await expect(status).toBeVisible();
        await expect(status).toContainText(/mi from niceville, fl → panama city, fl/i);

        // Miles input is read-only and has a numeric value.
        const miles = page.locator('#begin_empty_miles');
        await expect(miles).toHaveAttribute('readonly', '');
        const val = await miles.inputValue();
        expect(Number.parseInt(val, 10)).toBeGreaterThan(0);
    });

    test('28c — "Other" unlocks the miles input', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');

        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Lynn Haven, FL');
        await page.locator('#begin_empty_checkbox').check();

        const picker = page.locator('#begin_empty_terminal');
        await picker.selectOption({ label: 'Niceville, FL' });
        const miles = page.locator('#begin_empty_miles');
        await expect(miles).toHaveAttribute('readonly', '');

        await picker.selectOption('other');
        await expect(miles).not.toHaveAttribute('readonly', '');
        await expect(page.locator('#begin-empty-status')).toContainText(/type the miles yourself/i);

        // Now we should be able to type into it.
        await miles.fill('42');
        await expect(miles).toHaveValue('42');
    });

    test('28d — empty placeholder snaps miles to 0 and locks', async ({ page }) => {
        await signIn(page);
        await page.goto('loads/new');

        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Lynn Haven, FL');
        await page.locator('#begin_empty_checkbox').check();

        const picker = page.locator('#begin_empty_terminal');
        const miles  = page.locator('#begin_empty_miles');

        // Seed a non-zero value via the terminal path first.
        await picker.selectOption({ label: 'Niceville, FL' });
        await expect(miles).not.toHaveValue('0');

        // Switch to the empty placeholder — should snap back to 0 + lock.
        await picker.selectOption('');
        await expect(miles).toHaveValue('0');
        await expect(miles).toHaveAttribute('readonly', '');
    });
});
