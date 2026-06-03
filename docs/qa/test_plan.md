# PayTracker — QA Test Plan (Preview Channel)

This document is the single source of truth for verifying a PayTracker
feature build on the preview channel. It is written for a human tester
who has never seen the codebase: every step is something you can do in a
web browser.

If you find a problem at any step, **stop and report it**. Note the step
number, what you expected, and what you actually saw. Do **not** touch
the production site (https://paytracker.xyz/) while testing — the preview
lives at a separate URL specifically so production stays untouched.

> **Automation status**: Sections 5–8 are now covered by a Playwright
> suite that runs against the live preview channel on every deploy. CI
> will fail the deploy if any of those sections regress, so by the time
> you're walking this document the automated checks have already passed.
> Your job is the visual / judgement-based parts — layout, typography,
> "does this read right to a real user" — that automation can't replace.
> The Playwright suite lives in `tests/e2e/` if you want to read or
> extend it.

---

## What you'll need

- Any modern desktop browser (Chrome, Firefox, Safari, or Edge).
- An incognito / private window — keeps your session cookies isolated
  from anything else you're signed into.
- The preview URL: **https://paytracker.xyz/preview/**

> If the preview URL is unreachable, the deploy did not finish. Ask the
> engineering team to re-run the **Deploy preview** GitHub Actions
> workflow before continuing.

---

## 1. Landing page loads

1. Open an incognito window.
2. Visit **https://paytracker.xyz/preview/**.

**Expected:**

- The page title reads "PayTracker (preview: …)" (the branch name
  appears in parentheses).
- A large heading shows the app name with a coloured tag next to it.
  The tag should say **`preview`** in yellow — *not* `production` in
  green.
- A paragraph confirms you are on an *isolated preview channel* and that
  *production data is untouched*.
- A footer at the bottom of the page shows three pieces of information:
  the app name, the environment (`preview`), and the PHP version
  (should start with `8.3`).

**Fail conditions:**

- The page shows raw PHP code or a blank white screen.
- The environment tag says `production`.
- The PHP version starts with `7.` — that means the deploy landed on the
  wrong PHP runtime; flag immediately.
- A "Welcome, …" card appears even though you used an incognito window —
  that means session isolation is broken.

You should see a card labelled **"Sign in to continue"** with a blue
**Sign in →** button. That's the new per-account login replacing the
old shared "monkey" cookie. The login flow itself is tested in step 5.

---

## 2. Health probe responds

1. From the preview landing page, click the **`/health`** link.

**Expected:**

- The URL in the browser bar reads
  `https://paytracker.xyz/preview/health`.
- The page lists:
  - **PHP version** — starts with `8.3`.
  - **Environment** — `preview`.
  - **Server time (UTC)** — a timestamp dated today.
  - **Database** — a green **`reachable`** tag.

**Fail conditions:**

- A red **`unavailable`** tag appears next to "Database". Copy any
  error message shown underneath it into your report.

---

## 3. Machine-readable health probe

1. In the same browser tab, change the URL to
   `https://paytracker.xyz/preview/health.json` and press Enter.

**Expected:**

- A small JSON document is displayed. It should include
  `"database":{"ok":true}` and `"env":"preview"`.

**Fail conditions:**

- The page returns an HTTP error code (e.g., 503). The
  body will still contain JSON — copy `database.error` into your report.

---

## 4. Unknown page returns a friendly 404

1. Visit **https://paytracker.xyz/preview/this-page-does-not-exist**.

**Expected:**

- A styled page reading **"404 — Not found"** with a yellow `404` tag.
- A link labelled "Return home" takes you back to the preview landing
  page.

**Fail conditions:**

- A raw Apache or DreamHost 404 page (no styling, no "Return home"
  link) — that means the front-controller rewrite is not active.

---

## 5. Sign-in flow (per-account auth)

> Before this step you need a test account on the preview channel. Ask
> engineering to seed one for you — they'll run
> `php scripts/set-password.php <your-handle> <your-password>` on the
> preview server and tell you the handle + password to use. The same
> account works for every QA pass; you don't need a new one each time.

### 5a. Wrong password is rejected

1. From the preview landing page click **Sign in →**.
2. You should arrive at `https://paytracker.xyz/preview/login` with a
   form titled **"Sign in"**.
3. Enter your test handle but a deliberately wrong password
   (e.g. `definitely-not-it`).
4. Click **Sign in**.

**Expected:**

- The page reloads at `/preview/login` with a red banner reading
  **"Incorrect login or password."**
- The URL bar still shows `/login`. You are NOT signed in.

**Fail conditions:**

- The banner reveals which part was wrong (e.g. "no such user" or
  "wrong password"). The system must give the same generic message for
  every failure so an attacker can't enumerate accounts.
- You land on the home page even though the password was wrong.

### 5b. Correct credentials sign you in

1. Stay on the login page.
2. Enter your test handle and the **correct** password.
3. Click **Sign in**.

**Expected:**

- The URL changes to `https://paytracker.xyz/preview/`.
- A new card appears reading **"Welcome, *your-handle*"** with a green
  `signed in` tag.
- The card shows your role (`user` or `admin`) and the timestamp of
  the previous successful login (blank on the very first sign-in).
- A **Sign out** button is visible at the bottom of that card.

**Fail conditions:**

- A 500 page appears — copy the error and flag.
- You stay on `/login` despite using the correct credentials.

### 5c. Sign out returns you to the login page

1. Click the **Sign out** button on the home card.

**Expected:**

- The URL becomes `https://paytracker.xyz/preview/login`.
- The login form is empty and ready to receive a fresh sign-in.
- If you press the browser **Back** button you do NOT see the
  "Welcome" card — the session is genuinely gone, not just hidden.

**Fail conditions:**

- The browser back button reveals the post-login dashboard. That means
  the session cookie wasn't truly invalidated; flag immediately.

### 5d. Repeated bad guesses lock the account

> Skip this section if your test account is already locked from a
> previous run — engineering can reset it with
> `php scripts/set-password.php …` which clears the lockout counters.

1. From the login page, submit the **wrong** password five times in
   a row using your test handle.
2. After the fifth failure, submit the **correct** password.

**Expected:**

- Even with the correct password, login is refused with the same
  generic **"Incorrect login or password."** banner.
- Engineering can confirm the account row's `locked_until` column is
  set ~15 minutes into the future.

**Fail conditions:**

- The correct password works immediately after five failures — the
  lockout did not engage.

---

## 6. Add a location (first ported legacy feature)

> Requires you to be signed in (Section 5b). If you're not, do that first.

### 6a. The link is gated behind authentication

1. Sign out of the preview if you're currently signed in.
2. Manually type **https://paytracker.xyz/preview/locations** into the
   browser address bar.

**Expected:**

- You are redirected to **`/preview/login`**. You never see the
  Locations page.

**Fail conditions:**

- The Locations page renders for an anonymous visitor — that means the
  auth gate isn't running.

### 6b. The list page renders for a signed-in user

1. Sign back in.
2. From the home page, click **Manage locations →**.

**Expected:**

- URL changes to **`/preview/locations`**.
- Heading reads **"Locations"** with a green **+ Add city** button.
- A paragraph reading **"N cities on file."** appears (N matches the
  current count — at least a few, since the legacy app has been
  populating this table for years).
- A two-column list of city names appears below, alphabetically
  sorted.

### 6c. The add-city form validates input

1. Click **+ Add city**.
2. URL changes to `/preview/locations/new`.
3. Leave the city name blank, pick **FL**, click **Add city**.

**Expected:** red banner reads **"Enter both a city name and a state."**

4. Type a city name with digits in it (e.g. `Testville2`), pick **FL**,
   click **Add city**.

**Expected:** red banner reads **"City name may only contain letters,
spaces, periods, hyphens, apostrophes, and commas."** Your typed value
is preserved in the form (so you don't have to retype it).

### 6d. A valid submission inserts the city

1. Type a city name you know is NOT in the legacy list yet. To keep
   test data identifiable AND respect the digits-forbidden rule from
   step 6c, use the convention **`Qa Test <letter-word>, FL`** —
   pick any NATO phonetic letter that other testers haven't used
   recently (`Alpha`, `Bravo`, `Charlie`, `Delta`, `Echo`, etc.).
   Example: **`Qa Test Echo, FL`**. Avoid real city names so we
   don't pollute production data.
2. Pick a state.
3. Click **Add city**.

**Expected:**

- You land on `/preview/locations`.
- A green banner reads **`Added "Qa Test Echo, FL" (id NNN).`**
- Scrolling the list, the new city appears alphabetically.

**Fail conditions:**

- 500 page — copy and flag.
- Banner says the city already exists when it shouldn't (means a
  previous tester used the same NATO letter and didn't clean up —
  pick a different one and try again).

### 6e. Duplicate detection works

1. Submit the EXACT same city name + state again.

**Expected:** red banner reads **`"Qa Test Echo, FL" is already in
the list.`** No second row is inserted.

> Cleanup: there's a dedicated script for this. From the preview shell:
> `php scripts/qa-cleanup.php` (dry-run, lists what it would do)
> then `php scripts/qa-cleanup.php --apply` to actually delete the
> `Qa Test ...` rows and clear any lockout counters left by Section 5d.
> Default mode is dry-run; APP_ENV=production refuses without
> `--confirm-production`. Run this at the end of every QA pass so the
> production list stays tidy when the branch eventually merges.
>
> If you accidentally used a different naming convention (e.g.
> `Test City, FL`), pass your own prefix:
> `php scripts/qa-cleanup.php --apply --pattern='Test City%'`. The
> pattern is parameter-bound (no SQL injection) and the script
> refuses patterns with fewer than 3 literal characters so a stray
> `%` can't sweep the whole table.

---

## 7. City distances (normalized matrix)

> Requires sign-in (Section 5b).

This section verifies the **`city_distances`** backfill — a one-time
migration that copies the legacy column-per-city matrix tables
(`largeMiles` / `pcola_largeMiles`) into a relational table. The new
table is read-only for now; writes will land in a future branch.

### 7a. The page loads and reports plausible counts

1. From the home page, click **City distances →**.

**Expected:**

- URL changes to `/preview/distances`.
- A **"Backfill summary"** card shows:
    - Total rows: at least a few hundred (depends on legacy density —
      the matrix is sparse, but should not be zero).
    - Unique (from, to) pairs: a similar number, often equal to total
      when no pair appears in both source tables.
    - Distinct cities referenced: somewhere between 50 and 150.
    - **By source:** two lines — one for `largeMiles` and one for
      `pcola_largeMiles`, each with a non-zero count.

**Fail conditions:**

- Total rows is `0` and the red banner about a missing migration
  appears. The backfill migration didn't run; check `_migrations` on
  the server.

### 7b. Sample table looks right

Below the summary, the page shows the first 25 rows alphabetically.

**Expected:**

- Each row has a city name in **From** and **To** columns, a positive
  integer in **Miles**, and either `largeMiles` or `pcola_largeMiles`
  in the **Source** column.
- City names include a state code (e.g. `Andalusia, AL`,
  `Bonifay, FL`) — the backfill auto-registered these in the `city`
  table from the matrix labels.

**Fail conditions:**

- A row shows zero miles, NULL anywhere, or a city name with double
  spaces or a stray space before the comma (the backfill is supposed
  to normalize these — flag if you spot one).
- The `From` and `To` columns contain the same city (self-distances
  should have been skipped).

### 7c. Pair lookup works

1. From the sample table, copy one **From** city and one **To** city.
2. Paste them into the lookup form's **From** and **To** fields.
3. Click **Look up**.

**Expected:**

- A new section appears below the form listing the miles + source for
  that pair. The miles value matches the row in the sample table.
- If the same pair appears in both source tables (rare), both lines
  are listed.

4. Now type a pair you know is *not* in the list (e.g. `Foo, ZZ` →
   `Bar, ZZ`) and click **Look up**.

**Expected:**

- The form returns **"No recorded distance for …"** — confirming the
  lookup query is real, not a hardcoded list.

### 7d. Auth gate still applies

1. Sign out.
2. Manually type `https://paytracker.xyz/preview/distances` into the
   address bar.

**Expected:** redirected to `/preview/login`. The distances page is
behind the same auth gate as `/locations`.

> No cleanup needed for this section — it's purely a read-only
> verification page.

---

## 8. Driver loads (normalized per-driver tables)

> Requires sign-in (Section 5b).

This section verifies the **`driver_loads`** backfill — a migration that
copies the 21 legacy `loadsNN` per-driver tables (where `N` is the
driver's `account.id`) into a single relational table keyed by
`driver_id` + `frtl`. The new table is read-only for now.

### 8a. The page loads and reports plausible counts

1. From the home page, click **Driver loads →**.

**Expected:**

- URL changes to `/preview/loads`.
- **"Backfill summary"** card shows:
    - Total rows: in the low thousands (the 21 legacy tables hold
      around 3,300 rows combined, dominated by one driver with ~2,500).
    - Drivers with at least one load: between 10 and 20 — most legacy
      `loadsNN` tables are empty, so this number is smaller than the
      table count.
    - Date range: oldest is several years ago; newest is within the
      last few weeks (depending on legacy app usage).

**Fail conditions:**

- Total rows is `0` and the red banner about a missing migration
  appears.

### 8b. Per-driver counts look right

The next card lists each driver with at least one load, sorted by row
count.

**Expected:**

- The top row has a high count (the most active driver). The handle
  next to the driver_id is an email-shaped string from the `account`
  table.
- One row may show **`(orphan)`** instead of a handle — that's the
  legacy `loads65` data preserved with no matching account row. Not
  a bug; flagged as a follow-up cleanup concern.

### 8c. Spot-check against a legacy table

Pick any driver_id from the per-driver list. Click **view recent →**
next to it.

**Expected:**

- A new card appears showing the 25 most recent loads for that driver.
- Each row has a 6–7 digit FRTL number, a date, the dash-separated
  `variables` / `loadinfo` / `paid` legacy encodings, and NP / OP
  amounts.

Engineer-assisted parity check (optional but reassuring):

```bash
# On the preview shell — replace <id> with the driver_id you picked.
mysql -h <host> -u <user> -p<pw> paytracking -e \
  "SELECT (SELECT COUNT(*) FROM loads<id>) AS legacy,
          (SELECT COUNT(*) FROM driver_loads WHERE driver_id = <id>) AS modern"
```

Both numbers should match exactly (the backfill is a verbatim copy).

### 8d. Auth gate still applies

1. Sign out.
2. Manually type `https://paytracker.xyz/preview/loads` into the
   address bar.

**Expected:** redirected to `/preview/login`.

> No cleanup needed — read-only verification page.

---

## 9. Add a load (modern write path)

This is the first port of the legacy load-entry surface — one row per
submit, mileage looked up from `city_distances` first with a Google Maps
fallback that caches results back into the matrix. The Playwright suite
covers this section automatically; manual steps below mirror what the
spec exercises.

### 9a. Anonymous redirect
1. Sign out (or open a private window).
2. Visit `/preview/loads/new`.

**Expected:** redirected to `/preview/login`.

### 9b. Form renders for signed-in user
1. Sign in.
2. Visit `/preview/loads/new`.

**Expected:**
- Heading "Add a load".
- **FRTL #** is optional — leave blank to auto-assign MAX+1 for this
  driver, or type the dispatch number from your paperwork. When
  supplied it must be a positive integer not already on file for
  this driver.
- **Load date** is a `<input type="date">` defaulting to **today**,
  with `max=today` (can't enter future dates). Set it to the actual
  delivery date if you're entering paperwork after the fact — the
  dashboard groups by this date, not entry time.
- **Pick-up terminal** renders as a dropdown restricted to the
  dispatch terminals (`terminal` ∪ `pcola_terminal`) — e.g. Panama
  City FL, Niceville FL, Freeport FL, Pelham GA, Pensacola FL,
  Montgomery AL, Birmingham AL, DeFuniak Springs FL, Bainbridge GA.
- **Delivery city** is a free-text input backed by the full city
  autocompletion datalist.
- **Load type** radios default to "Loaded one-way".
- **End Empty location** is a free-text city input that is visible
  ONLY when "Loaded one-way" is selected. Switching to Round-trip
  hides it and clears any typed value.
- **Begin empty miles** is a plain number input (0–9999), also visible
  ONLY when "Loaded one-way" is selected. Round-trip loads don't have
  a separate empty pre-leg in the legacy formula, so toggling to
  Round-trip hides this field and resets the value to 0.
- **Out-of-route miles** is a plain number input (0–9999). Used
  only when the actual detoured distance exceeds the map distance
  by more than 3 miles — otherwise the map distance pays.
- A hidden `_csrf` input is present (inspect the form HTML).

### 9c. Empty submission rejected
1. From `/loads/new`, clear pickup terminal and click **Add load**
   (use the browser's "Inspect → form noValidate" trick if the
   native validators block the submit).

**Expected:** flash banner "Pick-up and delivery cities are required."
FRTL is optional, so a missing FRTL no longer trips the first check.

### 9d. Same pickup and delivery rejected
1. FRTL = a fresh 9-digit number (e.g. `999100001`).
2. Select Pick-up = `Panama City, FL`.
3. Type Delivery = `Panama City, FL`. Submit.

**Expected:** flash banner "Pick-up and delivery cannot be the same city."

### 9e. Unknown delivery city rejected
1. FRTL = a fresh number (e.g. `999100002`).
2. Select Pick-up = `Panama City, FL`.
3. Type Delivery = `Nowhereville, ZZ`. Submit.

**Expected:** flash banner "...is not in the city list. Add it first."

### 9e2. Duplicate FRTL rejected (new)

1. FRTL = the SAME number you used in a previous successful test
   submission for this driver.
2. Fill the rest with any valid values. Submit.

**Expected:** flash banner "FRTL N is already on file for this driver."
The form preserves your typed inputs so you can correct the FRTL.

### 9f. Valid submission inserts and shows the FRTL you typed
1. FRTL = a fresh number (e.g. `999100099`).
2. Leave **Load date** at today's date.
2. Select Pick-up = `Panama City, FL`, type Delivery = `Lynn Haven, FL`.
2. Notes: `QA TEST manual walk` (the `QA TEST ` prefix is what lets
   `scripts/qa-cleanup.php --loads` sweep it later).
3. Submit.

**Expected:**
- Redirect to `/preview/loads`.
- Flash banner: `Added load frtl=NNN: Panama City, FL → Lynn Haven, FL, NN miles. Pay: $X.XX.`
  (the trailing `(via Google Maps, now cached)` only appears the first
   time a pair is resolved that wasn't already in `city_distances`;
   the `Pay: $X.XX` is the np value PayCalculator returned at insert time).
- The "Recent loads" table on `/loads` now shows the new row.

### 9g. End Empty + Begin Empty are one-way-only

1. From `/loads/new`, with **Load type = Loaded one-way** selected,
   fill in End Empty location = `DeFuniak Springs, FL` and Begin
   empty miles = `25`. Take note of the visible state.
2. Click the **Round-trip** radio.

**Expected:**
- Both the End Empty input row AND the Begin empty miles row hide
  immediately.
- The previously-typed End Empty value is cleared; Begin empty miles
  is reset to `0` (both visible again if you flip back to Loaded
  one-way — empty / zero).
- Submitting a Round-trip load with either value filled in silently
  drops them (the controller treats both as one-way-only — round-trip
  loads don't have a separate empty pre- or post-leg in the legacy
  formula).

### 9h. Back-dating a load

1. FRTL = fresh number. Pick-up + delivery as in 9f.
2. Set **Load date** to two days ago.
3. Notes: `QA TEST back-dated walk`. Submit.

**Expected:**
- Insert succeeds with the chosen date stored in `driver_loads.date`
  (verify on `/dashboard?date=YYYY-MM-DD` for that day — the load
  appears there, NOT on today's dashboard).

### 9i. Begin-empty + out-of-route miles flow into pay

1. FRTL = fresh number. Pick-up = `Panama City, FL`, Delivery =
   `Lynn Haven, FL` (a short loaded leg).
2. **Begin empty miles** = `25`.
3. **Out-of-route miles** = blank (or 0).
4. Notes: `QA TEST begin-empty walk`. Submit.
5. Note the `Pay: $X.XX` value in the flash banner.
6. Repeat with begin empty miles = `0` for the same lane.

**Expected:**
- The first insert's np is higher than the second by approximately
  `25 × $0.4962` (the current empty rate). The exact difference
  depends on tenure band; what matters is that the value moves.

### 9j. Future-dated load rejected

1. From `/loads/new`, set **Load date** to tomorrow.

**Expected:** the native date picker enforces `max=today` and won't
let you advance. If you bypass it via dev-tools, submitting trips
the server-side check: flash banner "Load date cannot be in the future."

### Cleanup

```
ssh ...preview
cd /home/robshe48/paytracker/preview
php scripts/qa-cleanup.php --loads          # dry-run
php scripts/qa-cleanup.php --loads --apply  # delete the test rows
```

The deploy workflow runs `--apply --loads` automatically after the
Playwright suite, so manual cleanup is only needed if you tested by
hand without notes prefixed `QA TEST `.

---

## 10. Pay-rate admin (modern write path)

Pre-req: signed in as the QA admin account.

Visit `https://paytracker.xyz/preview/pay-admin`.

### 10a. Anon visitor is redirected

1. Open a private tab, navigate to `/preview/pay-admin`.

**Expected:** redirect to `/preview/login`.

### 10b. Page renders with backfilled data

1. Sign in, visit `/preview/pay-admin`.

**Expected:**
- "Pay-rate admin" heading + Summary card showing non-zero
  `Total rate rows` (preview backfill seeded ~496 rows: 114 + 114 + 114
  + 114 + 40 across the five legacy tables, with PanamaPay seeded into
  both default and current stages).
- Three editor cards: **Pensacola — Round-trip**, **Pensacola — Long-haul**,
  **Panama City — Round-trip**.
- Each card shows a "Start draft from current" button and a "Reset
  current ← default" button.

### 10c. Start a draft

1. On the Pensacola — Round-trip card, click **Start draft from current**.

**Expected:**
- Flash banner: "Draft started for pensacola (round_trip)."
- The Draft column in the rates table now mirrors the Current column.
- The card now shows **Promote draft → current** and **Reset draft to current**
  buttons.

### 10d. Edit a draft tier

1. Pick any tier (e.g. miles=100), change the Rate input next to it
   (e.g. from `101.8385` to `999.9999`), click **Save**.

**Expected:**
- Flash banner: "Saved tier 100 → 999.9999 in pensacola (round_trip) draft."
- The Draft column for miles=100 now shows `999.9999`; Current still
  shows the original.

### 10e. Add a new tier

1. In the "Add tier" form, enter Miles=`9999` Rate=`123.4567`, click
   **Add to draft**.

**Expected:**
- Flash banner: "Saved tier 9999 → 123.4567..."
- A new row appears at the bottom of the rates table with Current
  showing "(dropped)" and Draft showing `123.4567` — the convention
  for "this tier exists in draft but not in current".

### 10f. Delete a draft tier

1. On the row you just added (miles=9999), click **Delete** (confirm).

**Expected:**
- Flash banner: "Deleted tier 9999 from pensacola (round_trip) draft."
- The row vanishes from the table.

### 10g. Promote draft to current

1. Click **Promote draft → current** (confirm).

**Expected:**
- Flash banner: "Promoted draft to current for pensacola (round_trip)."
- The card returns to "no draft" state with the **Start draft from current**
  button visible again.
- The miles=100 tier now shows `999.9999` in the Current column.

### 10h. Reset current ← default

1. Click **Reset current ← default** (confirm).

**Expected:**
- Flash banner: "Reset pensacola (round_trip) rates to defaults."
- miles=100 goes back to its factory default (preview sample:
  `85.1492`).

### Cleanup

Section 10g and 10h leave the Pensacola RT current table at factory
defaults. Re-apply any customisations you want preserved before
signing off (or just leave it defaulted — the preview channel is
disposable).

---

## 11. Pay calculator (recompute np/op)

Pre-req: signed in; PR #12 (pay-calculator) deployed; `pay_variables`
backfill has run (preview log shows `backfill complete: {"default":32,"current":32}`).

Sign in, visit `/preview/pay-admin`. Scroll past the rate-editor cards
to the **Recompute pay (np/op)** card.

### 11a. Recompute respects the date filter

1. Leave the **Driver id** field blank.
2. Set **Since** to a date in the last 7 days.
3. Click **Recompute np/op** (confirm the dialog).

**Expected:**
- Flash banner like:
  `Recompute complete (since YYYY-MM-DD): considered=N, updated=M,
   unchanged=K, skipped=S.`
- `considered` should be a small number (just last week's loads).
- `updated + unchanged + skipped == considered` (every row gets a
  verdict).

### 11b. Recompute respects the driver_id filter

1. From `/preview/loads`, pick a driver_id that has loads in the recent
   sample.
2. On the pay-admin recompute card, set **Driver id** to that number,
   leave **Since** blank (defaults to last 30 days).
3. Submit.

**Expected:**
- Flash banner shows `driver_id=N, since YYYY-MM-DD` in the scope
  description.
- `considered` is bounded by the loads visible for that driver on
  `/loads?driver_id=N`.

### 11c. Recompute is idempotent on a stable input

1. Without changing any rates or variables, run **11a** again with the
   same Since date.
2. Observe the new flash banner.

**Expected:**
- `updated` is **0** on the second run (the math is deterministic).
- `unchanged` matches `considered` (all rows already had the right
  np/op from the first run).

### 11d. Editing a rate then recomputing changes np

1. On the Pensacola Round-trip card, click **Start draft from current**.
2. Pick a common-tier row (e.g. miles=100), bump the rate up by $10,
   click **Save**, then **Promote draft → current**.
3. Run a recompute scoped to the last 30 days.

**Expected:**
- Flash banner shows non-zero `updated` (round-trip loads in the date
  range had their np bumped).
- After running, click **Reset current ← default** to restore the
  rates so subsequent QA runs see a clean baseline.

### Cleanup

11d leaves the Pensacola RT rates at the factory defaults after the
reset step. Re-apply any customisations you want preserved (or leave
defaulted — the preview is disposable).

---

## 12. Driver dashboard ("my pay")

Pre-req: signed in as the QA admin account.

Visit `https://paytracker.xyz/preview/dashboard`.

### 12a. Anon redirect

1. Open a private tab, navigate to `/preview/dashboard`.

**Expected:** redirect to `/preview/login`.

### 12b. Today's view renders

1. Sign in, visit `/preview/dashboard`.

**Expected:**
- Heading reads **My pay — `YYYY-MM-DD`** with a `today` pill.
- Four cards in order: heading + date-nav, **This Week** totals,
  **Today (`YYYY-MM-DD`)** totals, **Loads**.
- The **This Week** card shows the pay-week window (e.g.
  `Sun 2026-05-31 → Sat 2026-06-06` for the default Sunday start) with
  summed Net Pay, total miles, and load count.
- The **Today** card shows the single-day totals for the viewed date.
- Totals cards show **Net Pay**; the per-load breakdown shows
  **Load Pay** + **Extras**. The legacy "OP" / "Old Pay" metric is
  NOT displayed — it was always zero in modern data.
- Date-nav has prev-day and next-day links and the **+ Add load** CTA.
- If you have no loads today (likely, given the preview backfill is
  from 2023), Loads shows "No loads on `YYYY-MM-DD`." and both totals
  cards show zeros. This is the normal state for the QA account.

### 12c. Empty-day view

1. Append `?date=1999-01-01` to the URL.

**Expected:** Loads card shows "No loads on 1999-01-01." The Today
totals card shows `Loads = 0`. The This Week card reflects the week
containing 1999-01-01 (also zero given the preview backfill range).

### 12d. Date-nav preserves auth

1. Append `?date=2023-04-10` to the URL.
2. Click the **← 2023-04-09** link.

**Expected:** URL becomes `/dashboard?date=2023-04-09`; you stay
signed in and see the same dashboard layout.

### 12e. Stale-totals warning (when applicable)

If the QA account happens to have loads on the viewed date but the
**Today → Net Pay** value is `$0.00`, you should see an amber warning
banner reading:

> Heads up: there are N load(s) on this date but np total is $0.00 —
> the stored pay columns may not have been computed yet. Ask the admin
> to run /pay-admin → Recompute pay scoped to this date.

That banner is correct behaviour, not a bug — it's the signal that a
PayCalculator recompute is needed.

### 12f. Pay-week window honors profile setting

1. Note the current pay-week window shown in the **This Week** card
   (e.g. `Sun 2026-05-31 → Sat 2026-06-06` for the default Sunday start).
2. Visit `/preview/profile`, change **Pay week starts on** to
   **Monday**, save.
3. Return to `/preview/dashboard`.

**Expected:**
- The This Week card now shows a Monday → Sunday window covering the
  current date.
- The summed totals shift to reflect the new window's loads.
- Setting it back to Sunday restores the original window.

### 12g. Self-serve "Refresh my pay"

1. From `/preview/dashboard`, click the **Refresh my pay** button.

**Expected:**
- Flash banner like
  `Refreshed pay (since YYYY-MM-DD): N load(s) considered, M updated, K already up-to-date.`
- Spam-clicking is harmless — the recomputer short-circuits rows whose
  computed values already match storage, so the second click shows
  `updated=0`.

---

## 13. Driver profile (hire date, shift, pay-week start day)

Pre-req: signed in.

The profile page drives PayCalculator's tenure-band selection
(`hire_date` → months-since-hire → band) and the night-bonus flag
(`shift`), and configures the dashboard's pay-week window
(`pay_week_start_day`). Until this surface existed, every new load
used a hardcoded `168-night--0` blob that inflated pay for junior /
day-shift drivers.

Visit `https://paytracker.xyz/preview/profile`.

### 13a. Anon redirect

1. Open a private tab, navigate to `/preview/profile`.

**Expected:** redirect to `/preview/login`.

### 13b. Form renders

1. Sign in, visit `/preview/profile`.

**Expected:**
- Heading "Profile" with the signed-in user + driver id.
- **Hire date** is an optional `<input type="date">` with `max=today`.
- Below the input is a "Current band: …" preview that maps the
  selected hire date to a tenure band (`6 / 12 / 24 / 60 / 108 / 168 / max`).
- **Default shift** is a Day / Night radio pair.
- **Pay week starts on** is a `<select>` of Sunday through Saturday.
- A hidden `_csrf` input is present.

### 13c. Invalid hire date is rejected

1. Open the dev tools, set the date input's `type` to `text`, type
   `not-a-date`, then submit (set `form.noValidate = true` first).

**Expected:** flash banner "Hire date must be in YYYY-MM-DD format."

### 13d. Future hire date is rejected

1. Bypass the native `max=today` via dev-tools, set hire date to
   tomorrow, submit.

**Expected:** flash banner "Hire date cannot be in the future."

### 13e. Saving persists across reload

1. Set hire date to ~40 months ago, shift to **Day**, pay-week to
   **Monday**. Click **Save profile**.
2. Reload the page.

**Expected:**
- Flash banner: `Profile saved. Tenure date: YYYY-MM-DD. Shift: Day. Pay week starts Mon.`
- After reload, all three fields retain the saved values.
- The "Current band" preview shows `40 months → band 60`.

### 13f. Persistence across preview deploys

The Playwright `profile.spec.ts` 13e/p4 test now saves and restores the
QA user's hire date, shift, and pay-week selection in a `try/finally`.
That means **deploying a new preview build does NOT reset your profile**.

To verify: after a preview redeploy, sign in and confirm your saved
hire date, shift, and pay-week start day are still in place.

---

## 14. Static housekeeping pages (tutorial, FAQ, about, contact)

These are public-facing read-only pages. No database access, no auth
requirement &mdash; a brand-new driver who hasn't been seeded an
account can read the tutorial and contact info before they sign in.

### 14a. Tutorial renders for anonymous and signed-in users

1. Open a private tab, visit `/preview/tutorial`.

**Expected:**
- Heading "Tutorial" with sections for profile setup, adding a
  load, reviewing pay, and editing.
- An embedded YouTube video plays in a responsive iframe.
- A "Back home" link returns to `/preview/`.

2. Sign in as the QA account and visit the same URL.

**Expected:** identical page renders (no auth gate, no redirect).

### 14b. FAQ renders

1. Visit `/preview/faq`.

**Expected:** Heading "Frequently asked questions" with seven Q&A
blocks covering pay math, the OP removal, hire-date snapshotting,
back-dating, Begin/End Empty, out-of-route miles, pay-week config,
and how to get an account.

### 14c. About renders

1. Visit `/preview/about`.

**Expected:** Heading "About PayTracker" with a status note that the
modern build runs on the preview channel and production still runs
legacy until parity is complete.

### 14d. Contact renders

1. Visit `/preview/contact`.

**Expected:**
- Heading "Contact" with a `mailto:` link.
- A "Requesting access" section explains how new accounts are seeded.

### 14e. Help nav appears on the home page

1. Visit `/preview/`.

**Expected:**
- A new "Help & info" card lists the four static pages with
  one-line summaries.
- Clicking each link navigates to the matching page.

---

## 15. Production is untouched

1. In a separate tab, visit **https://paytracker.xyz/** (no `/preview`).

**Expected:**

- You see the existing, legacy PayTracker login screen — *not* the new
  preview landing page.
- The browser address bar still shows `paytracker.xyz` with no
  `/preview/` segment.

**Fail conditions:**

- The legacy site is broken, blank, or redirecting to the preview.
  This indicates the production deploy was accidentally affected.
  Report immediately and tag the engineering team.

---

## 16. Security headers are present (optional — engineer-assisted)

If you are comfortable with browser developer tools:

1. Open the preview landing page.
2. Open developer tools → Network tab.
3. Reload the page and click the document request.
4. Look at the **Response Headers**.

**Expected** (any subset is acceptable; only flag if `X-Frame-Options`
or `X-Content-Type-Options` are missing):

- `Strict-Transport-Security` present.
- `X-Frame-Options: SAMEORIGIN`.
- `X-Content-Type-Options: nosniff`.
- `Referrer-Policy: same-origin`.

---

## Reporting template

Copy this into the issue / chat thread when filing a bug:

```
PayTracker preview QA — failure
Step:           <number from this document>
URL visited:    <full URL>
What I expected: <brief>
What I saw:      <brief, with the error message if any>
Browser:        <Chrome 1xx / Safari 1x / etc.>
Tested at:      <date, time, timezone>
```

---

*Maintainers: this is the canonical QA file. Do not create per-build
test logs in this repository — update this document instead so future
testers always see the latest steps.*
