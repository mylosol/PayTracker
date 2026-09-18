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
        // The ladder reads as brackets, not per-mile rates: each row shows
        // the mileages it pays, and the page says so.
        const ratesTable = page.locator('table.data-table').first();
        await expect(ratesTable.locator('th', { hasText: 'Covers' })).toHaveCount(1);
        await expect(ratesTable.locator('th', { hasText: /row pay/ }).first()).toBeVisible();
        // Locator + hasText, not getByText(regex): the paragraph continues
        // with inline <strong> nodes and getByText matches full text.
        await expect(page.locator('p', { hasText: /flat pay for the whole bracket/i })).toBeVisible();
    });

    test('10c — start draft from current', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: ROUND_TRIP });

        // The Reset-Draft form has an onsubmit confirm("Discard the
        // current draft…") — Playwright dismisses unhandled dialogs,
        // which would silently cancel the POST and leave the flash
        // never appearing. Register the accepter up front so either
        // branch below works regardless of the preview DB's current
        // draft state.
        page.once('dialog', (d) => d.accept());

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

    test('10c2 — "Preview impact" is scoped to the signed-in account', async ({ page }) => {
        await signIn(page);

        // Everything the viewer legitimately owns this pay week. The pay
        // week start day is a per-account preference we can't read from
        // here, so walk the last 8 days — whichever weekday the viewer's
        // week starts on, it falls inside that span.
        const ownFrtls = new Set<string>();
        const now = new Date();
        for (let back = 0; back < 8; back += 1) {
            const day = new Date(now);
            day.setDate(now.getDate() - back);
            const iso = day.toISOString().slice(0, 10);
            await page.goto(`dashboard?date=${iso}`);
            const frtls = await page
                .locator('#dashboard-loads-table tbody tr > td:nth-child(2) code')
                .allTextContents();
            frtls.forEach((t) => ownFrtls.add(t.trim()));
        }

        await page.goto('pay-admin');
        await page
            .locator('div.card', { hasText: ROUND_TRIP })
            .getByRole('link', { name: /preview impact/i })
            .click();
        await expect(page).toHaveURL(/\/pay-admin\/preview\?trip_type=round_trip/);
        await expect(page.getByRole('heading', { name: /preview draft/i })).toBeVisible();

        // Regression: the first revision of this page rendered a Driver
        // column (login handle + account id) for EVERY driver's loads in
        // the window — a cross-driver PII leak from an admin+ page. The
        // identity column and the `#<account id>` marker must not come
        // back.
        await expect(page.locator('th', { hasText: /^Driver$/ })).toHaveCount(0);

        const perLoadCard = page.locator('div.card', { hasText: /Per-load diff/ });

        if (await perLoadCard.count() === 0) {
            // No rows for this viewer => the page must say so explicitly
            // rather than falling back to the fleet's loads.
            //
            // Locator + hasText (substring semantics) rather than
            // getByText(regex): Playwright matches a regex against the
            // element's FULL text, and this paragraph continues past the
            // phrase with inline <code> nodes.
            await expect(
                page.locator('div.card', { hasText: /Aggregate impact/ })
                    .locator('p', { hasText: /No round_trip loads on your dashboard/ }),
            ).toBeVisible();
            return;
        }

        // The old page stamped each row with the driver's account id.
        await expect(perLoadCard).not.toContainText(/#\d+\b/);

        const shown = await perLoadCard.locator('tbody tr td code').allTextContents();
        const foreign = shown
            .map((t) => t.trim())
            .filter((frtl) => frtl !== '' && !ownFrtls.has(frtl));

        expect(
            foreign,
            `preview rendered FRTL(s) that are not on the signed-in account's dashboard: ${foreign.join(', ')}`,
        ).toEqual([]);
    });

    test('10c3 — preview includes this browser\'s unconfirmed loads', async ({ page }) => {
        await signIn(page);

        // Enter the preview once with a clean scratchpad so we can read the
        // server's idea of "today" (the app runs America/Chicago; the runner
        // may not agree).
        await page.goto('pay-admin/preview?trip_type=round_trip');
        const form = page.locator('#preview-local-form');
        await expect(form).toBeVisible();
        const today = await form.getAttribute('data-today');
        expect(today).toMatch(/^\d{4}-\d{2}-\d{2}$/);

        // Seed the exact key/shape the dashboard hydrates from — with a
        // deliberately bogus np, because the server must reprice, never trust
        // the browser's money.
        await page.evaluate((iso) => {
            localStorage.setItem('paytracker.unsavedLoads', JSON.stringify([{
                local_id: 'u_e2e_fixture_1',
                created_at: Date.now(),
                computed: {
                    date: iso,
                    load_type: 1,
                    pickup_city: 'Pensacola, FL',
                    delivery_city: 'Mobile, AL',
                    end_empty_city: '',
                    end_empty_miles: 0,
                    empty_miles: 320,
                    begin_empty_miles: 0,
                    is_split: 0,
                    is_weekend: 0,
                    is_backhaul: 0,
                    extra_pay: 0,
                    dem_minutes: 0,
                    break_minutes: 0,
                    out_of_route_miles: 0,
                    out_of_route_ind: 0,
                    notes: 'QA TEST scratchpad — safe to clean up',
                    np: 999.99,
                    op: 999.99,
                    pay_breakdown: { np: 999.99 },
                },
            }]));
        }, today);

        try {
            // Arriving again auto-submits the scratchpad once and re-renders
            // with those rows repriced alongside the saved ones.
            await page.goto('pay-admin/preview?trip_type=round_trip');
            await expect(page.locator('#preview-local-status'))
                .toContainText(/Included\s*1\s*unconfirmed/i);

            const card = page.locator('div.card', { hasText: /Per-load diff/ });
            await expect(card).toBeVisible();
            await expect(card).toContainText('Pensacola, FL');
            await expect(card).toContainText('Mobile, AL');
            await expect(card).toContainText(/unconfirmed — in this browser only/i);
            // 999.99 never happened: the projection is server-computed.
            await expect(card).not.toContainText('999.99');
            // Per-load breakdown is rendered server-side for every row.
            const breakdown = card.getByText(/Projected pay breakdown under the draft/i).first();
            await expect(breakdown).toBeVisible();
            await breakdown.click();
            await expect(card.getByText(/Total Load Pay/i).first()).toBeVisible();
        } finally {
            // Leave no fixture behind for other specs.
            await page.evaluate(() => localStorage.removeItem('paytracker.unsavedLoads'));
        }
    });

    test('10c4 — combined preview covers every trip type', async ({ page }) => {
        await signIn(page);

        // Default scope: no trip_type at all.
        await page.goto('pay-admin/preview');
        const form = page.locator('#preview-local-form');
        await expect(form).toBeVisible();
        const today = await form.getAttribute('data-today');

        // One unconfirmed round-trip and one unconfirmed one-way — the page
        // must show BOTH, because the dashboard's week card adds both.
        await page.evaluate((iso) => {
            const base = {
                end_empty_city: '', end_empty_miles: 0, begin_empty_miles: 0,
                is_split: 0, is_weekend: 0, is_backhaul: 0, extra_pay: 0,
                dem_minutes: 0, break_minutes: 0, out_of_route_miles: 0,
                out_of_route_ind: 0, np: 111.11, op: 111.11,
                notes: 'QA TEST scratchpad — safe to clean up',
            };
            localStorage.setItem('paytracker.unsavedLoads', JSON.stringify([
                {
                    local_id: 'u_e2e_roundtrip',
                    created_at: Date.now(),
                    computed: {
                        ...base, date: iso, load_type: 1,
                        pickup_city: 'Pensacola, FL', delivery_city: 'Mobile, AL',
                        empty_miles: 320,
                    },
                },
                {
                    local_id: 'u_e2e_oneway',
                    created_at: Date.now(),
                    computed: {
                        ...base, date: iso, load_type: 0,
                        pickup_city: 'Pensacola, FL', delivery_city: 'Lynn Haven, FL',
                        empty_miles: 90,
                    },
                },
            ]));
        }, today);

        try {
            await page.goto('pay-admin/preview');
            await expect(page.locator('#preview-local-status'))
                .toContainText(/Included\s*2\s*unconfirmed/i);

            // Sub-total strip names both trip types and closes with a total.
            const aggregate = page.locator('div.card', { hasText: /Aggregate impact/ });
            await expect(aggregate.locator('tbody tr', { hasText: 'Round-trip' })).toHaveCount(1);
            await expect(aggregate.locator('tbody tr', { hasText: 'One-way' })).toHaveCount(1);
            // Unanchored: hasText normalises whitespace but does not trim the
            // leading newline inside the row, so /^Total/ never matches.
            await expect(aggregate.locator('tbody tr', { hasText: /Total/ })).toHaveCount(1);

            // Per-load table lists both loads, one of each type.
            const diff = page.locator('div.card', { hasText: /Per-load diff/ });
            await expect(diff).toContainText('Mobile, AL');
            await expect(diff).toContainText('Lynn Haven, FL');
            await expect(diff).toContainText('Round-trip');
            await expect(diff).toContainText('One-way');
            await expect(diff).not.toContainText('111.11');
            // The paying rate row is named, so an edit to a row below a
            // load's mileage is visible as a no-op instead of looking like
            // a broken preview.
            const ladder = page.locator('div.card', { hasText: /tiers — draft vs current/ }).first();
            await expect(ladder).toContainText('Covers');
            // Either the paying row is named, or the row is beyond the top of
            // the ladder and the page says so — both are the note rendering.
            await expect(diff).toContainText(/Paid from the \d+ mi rate row|No rate row reaches \d+ mi/);
        } finally {
            await page.evaluate(() => localStorage.removeItem('paytracker.unsavedLoads'));
        }
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

        // Flash includes the effective date from the new pay-rate
        // versioning flow ("Promoted round_trip draft → current,
        // effective YYYY-MM-DD. ..."). Match on the durable parts.
        await expect(page.getByText(/promoted round_trip draft.*current.*effective/i)).toBeVisible();
        await expect(
            page.locator('div.card', { hasText: ROUND_TRIP })
                .getByRole('button', { name: /start draft from current/i })
        ).toBeVisible();
    });

    test('10h — reset current ← default (restores preview state)', async ({ page }) => {
        await signIn(page);
        await page.goto('pay-admin');
        const card = page.locator('div.card', { hasText: ROUND_TRIP });

        // Two-step gate: click reveals a type-to-confirm input, then a
        // second click posts. Regex is anchored so it never matches the
        // reveal-and-input state's Confirm/Cancel buttons on other cards.
        await card.getByRole('button', { name: /reset current ← default…/i }).click();
        await card.getByRole('textbox', { name: /type round-trip to confirm reset/i })
            .fill('Round-trip');
        await card.getByRole('button', { name: /^confirm reset$/i }).click();

        await expect(page.getByText(/reset round_trip rates to defaults/i)).toBeVisible();
    });
});
