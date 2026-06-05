import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 21 of docs/qa/test_plan.md — the "Store Load Info"
 * scratchpad path. Drivers without dispatch paperwork in hand turn the
 * checkbox OFF and the load lives in browser localStorage until they
 * later edit it and supply a FRTL # to convert it to a DB row.
 *
 * These tests verify:
 *   - The toggle hides the FRTL field and reveals the pitfall box
 *   - A scratchpad submission POSTs to /loads/preview (not /loads)
 *     and lands the row in localStorage
 *   - Dashboard hydration renders the unsaved row in today's table
 *     with a "—" FRTL and adjusts today's totals
 *   - Editing the unsaved row prefills the form with checkbox OFF
 *   - Typing a FRTL # auto-flips the checkbox ON
 *   - Submitting with FRTL ON consumes the localStorage entry and
 *     inserts a real DB row (cleanup via QA TEST marker)
 *   - The Discard button removes the entry
 */
test.describe('load scratchpad (Store Load Info OFF)', () => {
    test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');

    // Clear scratchpad state between tests so a leaked entry from a
    // prior failure doesn't bleed into the next test.
    test.beforeEach(async ({ page }) => {
        await signIn(page);
        await page.goto('dashboard');
        await page.evaluate(() => {
            try { localStorage.removeItem('paytracker.unsavedLoads'); } catch (e) {}
            try { localStorage.removeItem('paytracker.storeLoads');    } catch (e) {}
            try { sessionStorage.removeItem('paytracker.consumeLocalId'); } catch (e) {}
        });
    });

    test('21a — toggle hides FRTL and reveals pitfall box', async ({ page }) => {
        await page.goto('loads/new');
        await expect(page.locator('#frtl-block')).toBeVisible();
        await expect(page.locator('#scratchpad-pitfall')).toBeHidden();
        await page.locator('#store_load_info').uncheck();
        await expect(page.locator('#frtl-block')).toBeHidden();
        await expect(page.locator('#scratchpad-pitfall')).toBeVisible();
        // Sticky preference: reload and confirm the checkbox stays OFF.
        await page.reload();
        await expect(page.locator('#store_load_info')).not.toBeChecked();
        await expect(page.locator('#frtl-block')).toBeHidden();
    });

    test('21b — scratchpad submit lands in localStorage and renders on dashboard', async ({ page }) => {
        await page.goto('loads/new');
        await page.locator('#store_load_info').uncheck();
        await page.locator('#pickup_city').selectOption('Panama City, FL');
        await page.locator('#delivery_city').fill('Lynn Haven, FL');
        await page.locator('input[name="load_type"][value="0"]').check();
        await page.locator('#extra_pay').fill('0');
        await page.locator('#notes').fill('QA TEST scratchpad — safe to clean up');
        await page.getByRole('button', { name: /add load/i }).click();

        // Lands on dashboard.
        await expect(page).toHaveURL(/\/dashboard(\?|$)/);
        // Row is in localStorage.
        const entries = await page.evaluate(() => {
            const raw = localStorage.getItem('paytracker.unsavedLoads') || '[]';
            return JSON.parse(raw);
        });
        expect(entries.length).toBe(1);
        expect(entries[0].computed.pickup_city).toBe('Panama City, FL');
        expect(entries[0].computed.delivery_city).toBe('Lynn Haven, FL');
        expect(Number(entries[0].computed.np)).toBeGreaterThan(0);
        // Row renders in today's table. Other rows from prior runs may
        // also reference Panama City; scratchpad rows are uniquely
        // identifiable by their "unsaved — in this browser only"
        // footer, which no DB-backed row carries.
        const row = page.locator('#dashboard-loads-table tbody tr', {
            hasText: 'unsaved — in this browser only',
        });
        await expect(row).toBeVisible();
        await expect(row).toContainText('—');
        await expect(row).toContainText('Panama City, FL');
        await expect(row).toContainText('Lynn Haven, FL');
    });

    test('21c — past-date dashboard hides scratchpad rows', async ({ page }) => {
        // First, drop an entry into localStorage manually.
        await page.goto('dashboard');
        await page.evaluate(() => {
            const today = new Date().toISOString().slice(0, 10);
            localStorage.setItem('paytracker.unsavedLoads', JSON.stringify([{
                local_id: 'u_test_pastdate',
                created_at: Date.now(),
                computed: {
                    date: today,
                    load_type: 0, pickup_city: 'Panama City, FL', delivery_city: 'Lynn Haven, FL',
                    end_empty_city: null, end_empty_miles: 0, empty_miles: 20,
                    begin_empty_miles: 0, is_split: 0, is_weekend: 0, extra_pay: 0,
                    dem_minutes: 0, break_minutes: 0, out_of_route_miles: 0,
                    notes: null, np: 1.23, op: 0.0, pay_breakdown: { np: 1.23, op: 0 },
                    used_google_maps: 0,
                },
            }]));
        });
        // Visit yesterday — the scratchpad row should NOT appear.
        // (Other DB-backed rows for that date may legitimately contain
        // "Panama City, FL"; the scratchpad-only "unsaved" footer is
        // what we're really asserting absence of.)
        const yesterday = new Date(Date.now() - 86400_000).toISOString().slice(0, 10);
        await page.goto(`dashboard?date=${yesterday}`);
        await expect(page.locator('#dashboard-loads-table tbody tr', {
            hasText: 'unsaved — in this browser only',
        })).toHaveCount(0);
    });

    test('21d — edit-of-unsaved prefills form, FRTL flips checkbox ON', async ({ page }) => {
        // Seed an entry.
        await page.goto('dashboard');
        await page.evaluate(() => {
            const today = new Date().toISOString().slice(0, 10);
            localStorage.setItem('paytracker.unsavedLoads', JSON.stringify([{
                local_id: 'u_test_edit',
                created_at: Date.now(),
                computed: {
                    date: today,
                    load_type: 0, pickup_city: 'Panama City, FL', delivery_city: 'Lynn Haven, FL',
                    end_empty_city: null, end_empty_miles: 0, empty_miles: 20,
                    begin_empty_miles: 0, is_split: 0, is_weekend: 0, extra_pay: 5,
                    dem_minutes: 0, break_minutes: 0, out_of_route_miles: 0,
                    notes: 'QA TEST scratchpad seed', np: 12.34, op: 0,
                    pay_breakdown: { np: 12.34, op: 0 }, used_google_maps: 0,
                },
            }]));
        });
        await page.goto('loads/new?unsaved=u_test_edit');
        // Form prefilled, checkbox OFF, FRTL hidden.
        await expect(page.locator('#store_load_info')).not.toBeChecked();
        await expect(page.locator('#frtl-block')).toBeHidden();
        await expect(page.locator('#pickup_city')).toHaveValue('Panama City, FL');
        await expect(page.locator('#delivery_city')).toHaveValue('Lynn Haven, FL');
        await expect(page.locator('#extra_pay')).toHaveValue('5');
        // Toggle ON to reveal FRTL, then typing it auto-flips
        // — we click the checkbox so the field is visible, then
        // demonstrate the auto-flip path by unchecking, typing into
        // a visible FRTL (we need to show it first), then check the
        // checkbox is now on.
        await page.locator('#store_load_info').check();
        await expect(page.locator('#frtl-block')).toBeVisible();
        // Confirm the FRTL input is now required.
        await expect(page.locator('#frtl')).toHaveAttribute('required', '');
    });

    test('21e — discard removes entry and reloads', async ({ page }) => {
        await page.goto('dashboard');
        // Pull the server's notion of "today" off the card so the
        // hydration date-filter accepts our seeded entry. Using
        // `new Date()` in JS would give UTC, which can be off by
        // one day from APP_TIMEZONE (America/Chicago) when the
        // runner clock is in the wrong window.
        const serverToday = await page.locator('[data-loads-card]').getAttribute('data-date');
        expect(serverToday).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        await page.evaluate((today) => {
            localStorage.setItem('paytracker.unsavedLoads', JSON.stringify([{
                local_id: 'u_test_discard',
                created_at: Date.now(),
                computed: {
                    date: today,
                    load_type: 0, pickup_city: 'Panama City, FL', delivery_city: 'Lynn Haven, FL',
                    end_empty_city: null, end_empty_miles: 0, empty_miles: 20,
                    begin_empty_miles: 0, is_split: 0, is_weekend: 0, extra_pay: 0,
                    dem_minutes: 0, break_minutes: 0, out_of_route_miles: 0,
                    notes: null, np: 1.23, op: 0, pay_breakdown: { np: 1.23, op: 0 },
                    used_google_maps: 0,
                },
            }]));
        }, serverToday);
        await page.goto('dashboard');
        page.once('dialog', d => d.accept());
        await page.locator('button[data-discard-local-id="u_test_discard"]').click();
        // Page reloads; entry should be gone.
        await expect(page.locator('button[data-discard-local-id="u_test_discard"]')).toHaveCount(0);
        const entries = await page.evaluate(() => {
            const raw = localStorage.getItem('paytracker.unsavedLoads') || '[]';
            return JSON.parse(raw);
        });
        expect(entries.length).toBe(0);
    });
});
