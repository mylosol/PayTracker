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

> Requires sign-in as the **Super Admin** QA account (Section 5b).

This section verifies the **`driver_loads`** backfill — a migration that
copies the 21 legacy `loadsNN` per-driver tables (where `N` is the
driver's `account.id`) into a single relational table keyed by
`driver_id` + `frtl`. The new table is read-only for now.

**Access:** `/preview/loads` is the cross-driver survey surface — it
shows every driver's loads. Gated at **Super Admin** so a regular User
or Admin can't enumerate other drivers' data. Anonymous → `/login`;
User / Admin → 403 "Access denied"; Super Admin → 200. Per-driver
load entry (`/loads/new`, edit, delete) remains open to any
authenticated user against their OWN driver_id only.

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
- **FRTL #** is REQUIRED (HTML5 `required` + server-side check).
  Type the dispatch number from your paperwork. Must be a positive
  integer not already on file for this driver. Drivers without a
  number in hand should use the **Store Load Info** scratchpad path
  (covered in Section 21) — the FRTL field is hidden when that
  checkbox is OFF.
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

**Expected:** flash banner starts with "FRTL # is required to save a
load." FRTL is the first server-side check now; pickup/delivery
errors only surface after FRTL is supplied.

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
- Redirect to `/preview/dashboard`.
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
- **Username** input pre-populated with the current `account.user`
  value. Pattern: 3-32 characters, letters/digits/dot/underscore/
  dash, no spaces or `@`.
- **Email** input pre-populated with the current `account.email`
  value (after migration `2026_06_04_001`, this is backfilled from
  `user` for every legacy account whose handle looked like an
  email). **Required.** Every account must have a working address
  so admin-issued password-reset links can reach them.
- **Hire date** is an optional `<input type="date">` with `max=today`.
- Below the input is a "Current band: …" preview that maps the
  selected hire date to a tenure band (`6 / 12 / 24 / 60 / 108 / 168 / max`).
- **Default shift** is a Day / Night radio pair.
- **Pay week starts on** is a `<select>` of Sunday through Saturday.
- A hidden `_csrf` input is present.

### 13b.1. Legacy email-shaped usernames keep working

Legacy QA accounts have `user="mylosol@gmail.com"` (handle equals
email). The `@` character violates the new USER_PATTERN, but a
grandfather clause in `Account::updateBasics` skips the pattern
check when the submitted username matches the EXISTING one.

1. Visit `/preview/profile`. Don't change the Username input
   (leave it at the legacy email-shaped value). Edit only the
   shift radio. Save.

**Expected:** profile saves successfully — the grandfather
clause accepts the unchanged legacy handle even though it has
an `@`. Picking a new username at this point would require
the modern pattern, so once you change it you can't change
it back to an email-shaped value via this UI.

### 13b.2. New username must match the modern pattern

1. Change the Username to `bad name` (with a space). Save.

**Expected:** error flash:
`Username must be 3-32 characters, letters/digits/dot/underscore/dash only (no spaces or @).`

2. Change Email to `not an email`. Save.

**Expected:** error flash: `Email is not a valid address.`

3. Clear the Email field entirely. Save.

**Expected:** error flash: `Email is required.`
(Email is no longer optional — every account must have a working
address so password-reset emails can reach them.)

4. Change Username to a value already held by another account.
   Save.

**Expected:** error flash: `That username is already taken.`

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

### 14e. Help nav is hidden on the anonymous home page

1. Open a private tab, visit `/preview/`.

**Expected:**
- The anonymous landing shows the marketing card and Sign-In CTA
  only. The "Help & info" card is NOT shown.
- The four static pages remain reachable by direct URL
  (`/preview/tutorial`, `/preview/faq`, `/preview/about`,
  `/preview/contact`) — covered in 14a–14d.

### 14f. Help nav appears on the signed-in home page

1. Sign in, visit `/preview/`.

**Expected:**
- A "Help & info" card lists the four static pages with one-line
  summaries.
- Clicking each link navigates to the matching page.

---

## 15. RBAC — role-gated admin surfaces

The `account.role` column now drives access to Locations, City
Distances, and Pay-rate admin. Three roles exist:

| Role          | Can access                                            |
|---------------|-------------------------------------------------------|
| `user`        | Dashboard, Profile, Loads, all static pages           |
| `admin`       | Everything above + `/locations`, `/distances`, `/pay-admin` |
| `super_admin` | Everything above (Admin Panel lands in a later branch) |

The QA account (`mylosol@gmail.com`) is promoted to `super_admin` by
migration `2026_06_03_001_promote_super_admin.sql`. The bulk of the
QA walk runs as super_admin, so the previously-walked sections 6, 7,
10, 11 still pass unchanged.

### 15a. Admin nav is visible for super_admin

1. Sign in as the QA account, visit `/preview/`.

**Expected:**
- The welcome card's button row shows Driver loads, **Manage
  locations**, **City distances**, and **Pay-rate admin** — all
  three admin links are visible.
- "My pay (today)" remains the primary CTA.

### 15b. Admin pages return 200 for super_admin

1. Visit `/preview/locations`, `/preview/distances`, and
   `/preview/pay-admin` in sequence.

**Expected:** each renders normally (no 403 page).

### 15c. (Manual / future) base User gets a 403

This step requires a seeded test account with `role='user'`. We
don't have one on preview yet; once we do, the manual walk is:

1. Sign in as the User-role account.
2. Visit `/preview/pay-admin`.

**Expected:**
- Heading **Access denied** with a red `403` pill.
- Body reads "This area is restricted to **Admin** accounts or
  higher. Your account role is `user`."
- A "Back to dashboard" CTA returns to `/preview/dashboard`.
- The home page's button row hides the three admin links for
  this user.

For now the Playwright spec `tests/e2e/rbac.spec.ts` covers the
positive case (super_admin sees the nav + accesses the surfaces).
The negative case (User → 403) is exercised by the unit tests in
`tests/Unit/Models/AccountTest.php` against `Account::hasRole()`.

---

## 16. Admin Panel — user management

The `/admin` surface lets Admin and Super Admin accounts manage
the user list: see who's signed in recently, ban / unban, hard
delete, and generate a single-use password reset link.

Role assignment is intentionally NOT here — that's Super Admin
only and ships in a follow-up branch.

Email delivery of the reset link is also a follow-up (Resend
integration). For now the reset URL is shown in a flash banner
on `/admin` so the admin can copy/paste it to the user.

Pre-req: signed in as the QA Super Admin account.

### 16a. Anon redirect, base User → 403

1. Open a private tab, visit `/preview/admin`.

**Expected:** redirect to `/preview/login`.

2. (Manual / future) Sign in as a base User account, visit
   `/preview/admin`.

**Expected:** the 403 "Access denied" page from section 15.

### 16b. User table renders

1. Sign in, visit `/preview/admin`.

**Expected:**
- Heading **Admin Panel** with a role pill showing your role.
- A "User accounts" card with a filter row (search box, role
  dropdown, per-page selector, "Show spam-looking handles"
  checkbox, Apply, Clear) above the table.
- A count summary like "50 shown of N matching; M total in DB,
  spam-handles hidden".
- Default view hides obvious legacy spam-bot handles (SQL-
  injection probes, XSS payloads, URLs as usernames, names
  with whitespace / parens / quotes). Tick **Show spam-looking
  handles** to see everything.
- The table lists matching accounts with columns: ID, User,
  Email, Role, Last login, Status, Actions.
- Pagination (First / Prev / Next / Last) at the bottom when
  more than one page of results.
- Your own row carries a small **you** badge next to the user
  name AND shows "No self-actions" in the Actions column —
  the server-side guard against banning / deleting yourself
  is mirrored in the UI.
- Other rows show a **Reset PW**, **Ban**, and **Delete** button.

### 16b.1. Filters compose correctly

1. Type your email's local part into **Search**, click Apply.

**Expected:** the table narrows to rows whose user OR email
contains the substring. The count summary updates.

2. Pick **admin** in the Role dropdown, Apply.

**Expected:** only rows with role=admin remain (intersected with
the search).

3. Click **Clear**.

**Expected:** filters reset, default view restored.

### 16c. Reset link is generated AND emailed

1. Find a non-self test account that has an email on file. Click **Reset PW**.

**Expected:**
- A green flash banner at the top of `/admin` reading:
  `Reset link for <user> (expires YYYY-MM-DD HH:MM:SS UTC, emailed to <email> (msg <id>)): https://paytracker.xyz/preview/password-reset/<64-hex-chars>`
- The URL is copyable.
- An email arrives at the target's inbox (subject "Reset your
  PayTracker password") with a "Set a new password" button
  pointing at the same URL. **Check the Resend dashboard logs
  if the message doesn't arrive within a minute.**

When the target has NO email on file the flash reads
`... (no email on file): <url>` and only the in-flash URL is
available. When Resend itself returns a non-2xx, the flash reads
`... (email to <email> FAILED — see Resend logs): <url>` so an
admin knows to investigate without losing the URL.

### 16d. Reset link is single-use and time-bound

1. Copy the URL from 16c. Open a private tab, paste it.

**Expected:** a "Set a new password" form with two password
fields (minimum 12 characters).

2. Pick a password, submit.

**Expected:** "Password updated" confirmation page; a **Sign in**
button returns to `/login`.

3. Reload the URL from step 1.

**Expected:** "Reset link no longer valid" page with a `410` pill
— the token was consumed.

### 16e. Ban prevents login

1. From `/admin`, click **Ban** on a non-self test account. Confirm
   the browser dialog.

**Expected:** flash banner "Banned `<user>` (id `N`)."

2. Open a private tab. Try to sign in as the banned account with
   the **WRONG** password.

**Expected:**
- Generic "Incorrect login or password." error (same as any
  bad-password attempt — the ban isn't revealed to someone who
  hasn't proved they hold the password).

3. Try to sign in as the banned account with the **CORRECT**
   password.

**Expected:**
- Error message:
  *"This account has been suspended. Please contact an
  administrator at /contact for assistance."*
- The user is NOT redirected into the app; they stay on the
  login page with the explanatory flash.
- An audit row appears at
  `/preview/admin/audit?action=USER_LOGIN_FAILED` with
  reason=`banned` (only fires when the password verified).

4. Return to `/admin`, click **Unban**.

**Expected:** flash banner "Lifted ban on `<user>` (id `N`)."

5. Retry the login with the correct password.

**Expected:** sign-in succeeds; the suspension message no longer
appears.

### 16f. Delete is final

1. From `/admin`, click **Delete** on a non-self test account.
   Confirm the browser dialog.

**Expected:**
- Flash banner "Deleted `<user>` (id `N`)."
- The row no longer appears in the table.
- The deleted account can no longer sign in (login still
  shows the generic "wrong credentials" message — we do not
  reveal "no such account").

### 16g-edit. Admin can edit a user's username + email

The Actions column gains an **Edit** button on every non-self row.

1. Pick a non-self test account. Click **Edit**.

**Expected:**
- New page at `/admin/users/<id>/edit` with a form pre-populated
  from the target's current values: username + email inputs.
- Submitting unchanged values is a no-op save with flash
  `Updated user N: username=X, email=Y.`
- Validation errors (bad pattern / invalid email / taken
  username / taken email) surface as red flashes; the form
  preserves the typed values.
- The change is audited as `USER_EDITED` with previous + new
  values for both fields in the metadata blob.

The role-assignment / ban / delete / reset-password actions
stay on the list view — only the basics (username + email)
moved to the dedicated edit page.

### 16g.0. Bulk-clean legacy spam (admin-led, SSH)

The legacy `account` table contains years of bot-registration
junk (SQL-injection probes used as usernames, URLs as handles,
XSS payloads, etc.). The admin panel hides them by default
(see 16b "spam-handles hidden"), but to actually purge the
rows from the DB run the cleanup script over SSH:

```
ssh ...preview
cd /home/robshe48/paytracker/preview
php scripts/sample-suspicious-accounts.php   # read-only audit
php scripts/cleanup-spam-accounts.php        # dry-run, shows what would delete
php scripts/cleanup-spam-accounts.php --apply
```

**Hard safety rails** baked into the cleanup script:
- Dry-run by default; needs `--apply` to delete.
- Refuses APP_ENV=production unless `--confirm-production` is
  also supplied.
- Never deletes accounts that have a `password_hash` (real users
  who set a password).
- Never deletes accounts that own any `driver_loads` rows.

### 16g-chain. Privilege-chain protection

The actor can only mutate accounts whose role is **strictly
lower** than their own. The same rule applies across edit,
ban / unban, delete, reset-password, and role assignment.

1. Sign in as the QA Super Admin.
2. Promote a test account to **Admin** (via the role dropdown).
3. Sign out. Sign in as that **Admin** account.
4. Visit `/preview/admin`. Find your Super Admin row in the
   table.

**Expected:**
- The Super Admin row shows the muted text **"Outranks you"** in
  the Actions column instead of any buttons.
- The Role cell on the Super Admin row is read-only — no
  dropdown (Super Admin is restricted to the
  role-assignment surface, which Admin can't reach anyway).
- Other Admin rows ALSO show "Outranks you" — peer Admins
  can't mutate each other either.

5. Attempt to visit `/preview/admin/users/<super-admin-id>/edit`
   directly.

**Expected:** redirect back to `/admin` with the flash
`Cannot edit an account at your role tier or higher.`

6. Attempt a hand-crafted POST to
   `/preview/admin/users/<super-admin-id>/ban` (use the browser
   dev-tools console with a known CSRF token).

**Expected:** flash `Cannot ban an account at your role tier
or higher.` and no state change.

7. Sign back in as Super Admin. Demote the test Admin back to
   User to clean up.

### 16g. Self-target protection

1. From `/admin`, copy a button URL for one of YOUR OWN row's
   would-be actions (use dev tools — your row doesn't actually
   show buttons). Build a POST against
   `/admin/users/<your-id>/ban`.

**Expected:** server-side refusal with flash:
`Cannot ban your own account from the admin panel.`

Repeated for `/delete` and `/reset-password` — same opaque refusal.

---

## 17. Audit log + system diagnostics

The `audit_logs` table is append-only and records every auth
event (login success, failure with reason, logout) plus every
admin-panel mutation (ban / unban / delete / password-reset
issuance + consumption). `/admin/audit` is the searchable
viewer; `/admin/diagnostics` is the runtime fingerprint +
last-hour audit counters.

Pre-req: signed in as the QA Super Admin.

### 17a. Audit viewer renders + filters

1. From `/preview/admin` click **Audit log →**.

**Expected:**
- Heading "Audit log" with the role pill.
- Filter form: User id, Action (dropdown), IP, Limit, Apply, Clear.
- Table columns: Time (UTC), User, Action, Reason, IP, Metadata.
- Most-recent-first ordering.

2. Pick **USER_LOGIN** in the Action dropdown, Apply.

**Expected:** the table narrows to login-success rows only.

3. Click **Clear**.

**Expected:** filters reset, all event types visible again.

### 17b. Diagnostics renders

1. From `/preview/admin` click **System diagnostics →**.

**Expected:**
- Three cards: **Runtime**, **Audit counters — last hour**,
  **Recent migrations**.
- Runtime card shows PHP version (8.3.x on web SAPI), DB
  version (MariaDB 10.11.6), DB now (UTC), account row count,
  audit_logs row count.
- Audit counters card lists the action names that fired in
  the last 60 minutes with their counts. Failed-login rows
  highlight red when count > 5.
- Recent migrations card lists the 10 most recent applied
  migrations with their UTC timestamps.

### 17c. Login is audited

1. Sign out, sign back in.
2. Visit `/preview/admin/audit?action=USER_LOGIN`.

**Expected:** the top row's Action cell is **USER_LOGIN** with
your account user/id, your IP, and a JSON metadata blob
containing user_agent + accept_language + attempted_handle.

### 17d. Failed login is audited with a reason

1. Sign out. From `/preview/login`, type your QA handle but a
   WRONG password. Submit.

**Expected:** generic "invalid credentials" error (no leak).

2. Sign in correctly. Visit `/preview/admin/audit?action=USER_LOGIN_FAILED`.

**Expected:**
- A new row at the top with Action **USER_LOGIN_FAILED**, Reason
  `bad_password`, your IP, and the QA handle in the metadata
  blob's `attempted_handle` field.

3. Repeat with a username that doesn't exist (e.g.
   `not-a-real-handle@example.com`).

**Expected:** row appears with Reason `no_such_user`, user_id NULL.

### 17e. Admin actions are audited

1. From `/admin`, generate a reset link for any non-self test
   account (Reset PW button).

**Expected:** /admin/audit shows a **PASSWORD_RESET_SENT** row
with your id as the actor, the target user's id and name in
the metadata, plus the expiry timestamp.

2. Paste the URL into a private tab, set a new password.

**Expected:** a **PASSWORD_RESET_USED** row appears with the
target user as the actor (they're the one who consumed it),
not the admin who minted it.

3. Ban then unban a test account from /admin.

**Expected:** **USER_BANNED** and **USER_UNBANNED** rows
appear with the actor (you), target user id, and IP recorded.

---

## 18. Super Admin — role assignment

The user table on `/admin` swaps the read-only role <code> badge
for a role-select + Save button on every non-self row **when the
viewer is Super Admin**. Plain Admin accounts continue to see
the static badge.

Pre-req: signed in as the QA Super Admin account.

### 18a. Role dropdown is visible on non-self rows

1. Visit `/preview/admin`. Scroll the Role column.

**Expected:**
- Your own row still shows your role as plain `super_admin`
  text (no controls — self-target safeguard).
- Every other row shows a `<select>` with the three options
  `user / admin / super_admin` next to a **Save** button.
- The current role is pre-selected in each dropdown.

### 18b. Promotion fires audit + flash

1. Pick a non-self test account currently at `user`. Change
   the dropdown to `admin`. Click **Save**.

**Expected:**
- Flash banner: `Changed role of <user> (id N): user → admin.`
- Reloading `/admin` shows the row with `admin` pre-selected.
- `/admin/audit?action=USER_ROLE_CHANGED` lists a new row with
  your id as the actor, the target id + name in the metadata
  blob, and `"previous_role":"user","new_role":"admin"`.

### 18c. Demotion fires the same path

1. Change the same row back to `user`. Save.

**Expected:** flash `Changed role of <user> (id N): admin → user.`
A second `USER_ROLE_CHANGED` audit row exists with the inverse
transition.

### 18d. Same-role save is a no-op

1. Change a row's dropdown back to its current value. Save.

**Expected:**
- Flash: `No change — <user> already has role "<role>".`
- No new audit row is written.

### 18d.1. Peer Super Admin can be demoted (escape hatch)

The privilege-chain rule from section 16g-chain is STRICT
"strictly lower" for ban / unban / delete / reset-pw / edit.
**Role assignment is the deliberate exception:** a Super Admin
can demote a peer Super Admin back down. Without this, a
mistaken promotion would leave only a DB-edit recovery path.

1. From `/admin`, promote a test User account to **Super Admin**.

**Expected:** the role dropdown saves; the row now shows
`super_admin`.

2. Without signing out, find the same row again.

**Expected:**
- The Role cell STILL shows a working role dropdown (the
  peer-Super-Admin carve-out).
- The Actions cell still shows **"Outranks you"** — Edit / Ban
  / Delete / Reset PW are still blocked. Only role-change
  flows under the carve-out.

3. Change the dropdown back to `user`. Save.

**Expected:** flash `Changed role of <user> (id N): super_admin → user.`
The audit log records `USER_ROLE_CHANGED` with both values in
the metadata.

### 18e. Sole-Super-Admin safeguard

This requires a SECOND Super Admin account on preview.

1. Promote one other test account to `super_admin` first (so
   there are two Super Admins on file).
2. Demote that other account back to `admin`.

**Expected:** the demotion succeeds because you remain a Super
Admin afterwards.

3. (Cannot test on a single-Super-Admin preview.) If the QA
   account were the only Super Admin and a different Super
   Admin tried to demote them, the action would refuse with
   flash:
   `change role failed: Refusing to demote the only Super
   Admin — promote another account to Super Admin first.`

The matching self-demotion is already blocked by the
self-target guard from section 16g (`Cannot change role your own
account from the admin panel.`).

### 18f. Plain Admin sees the static role badge

This step requires a seeded test account with `role='admin'`.

1. Sign in as that account, visit `/preview/admin`.

**Expected:**
- The user table still renders (Admin has read access).
- Every Role cell shows the static `<code>` badge, NOT the
  select / Save form.
- A POST against `/preview/admin/users/{id}/role` from this
  account returns the 403 page (the controller enforces the
  Super Admin gate at action entry; the missing UI is just
  the visible mirror).

---

## 19. Announcements — login-modal broadcast

Super Admin authors a subject + body announcement; every signed-
in user sees it as a modal on their next page render after
login. "Okay" dismisses for the session; "Don't show again"
suppresses for that user permanently. Only one announcement
can be active at a time. Templates can be saved for reuse and
are never shown to users.

Pre-req: signed in as the QA Super Admin account.

### 19a. Admin surface renders + create

1. Visit `/preview/admin`. Click **Announcements**.

**Expected:**
- Heading "Announcements" with the role pill.
- A "+ New announcement" button and a table (likely empty
  on a fresh preview).

2. Click **+ New announcement**.

**Expected:**
- Form with Subject, Body, Expires at (datetime-local,
  optional, marked UTC), "Activate immediately" checkbox,
  "Save as template only" checkbox.

3. Fill Subject = "QA TEST announcement", Body = "Hello drivers."
   Leave expiry blank. Tick "Activate immediately". Save.

**Expected:** redirect to the list with a green flash
`Created announcement "QA TEST announcement" (id N) and
activated.`. The row shows the **active** pill, viewer count 0.

### 19b. Modal appears for users on next page render

The QA Super Admin account ALSO sees the modal — broadcast
includes admins.

1. Sign out (top-right). Sign back in.

**Expected:**
- After successful login + redirect, the landing page renders
  with a centered modal: subject as heading, body, "Okay"
  button, and an unticked "Don't show this again" checkbox.
- The page underneath (welcome card, etc.) is dimmed by the
  backdrop overlay.

### 19c. Plain dismiss returns next login

1. Leave the checkbox unticked. Click **Okay**.

**Expected:**
- Modal closes; page reloads to the same URL with the
  underlying content visible.
- The seen-by report at `/preview/admin/announcements/<id>`
  now lists your account with "Don't show again? — no — will
  see again".

2. Sign out, sign back in again.

**Expected:** modal reappears (you didn't tick "don't show
again").

### 19d. Permanent dismiss sticks

1. This time, tick **Don't show this again**. Click Okay.

**Expected:**
- Modal closes.
- Seen-by row updates to "yes — permanent".

2. Sign out, sign back in. Visit any page.

**Expected:** no modal — the suppression record blocks it.

### 19e. Audit log entries

1. Visit `/preview/admin/audit?action=ANNOUNCEMENT_DISMISSED`.

**Expected:** the dismissals from 19c-19d appear as
`ANNOUNCEMENT_DISMISSED` rows with metadata
`{"announcement_id":N,"suppressed":true|false}`.

2. Filter by `action=ANNOUNCEMENT_CREATED` /
   `ANNOUNCEMENT_ACTIVATED` and verify those rows from 19a
   are present.

### 19f. Only one active at a time

1. Create a SECOND announcement (Subject = "QA TEST 2"), tick
   Activate. Save.

**Expected:**
- The flash confirms activation.
- The list shows announcement #2 as **active**; #1 has
  reverted to **inactive** automatically. The deactivation
  also fires an `ANNOUNCEMENT_DEACTIVATED` audit row for #1.

### 19g. Templates are never shown

1. Click **+ New announcement**. Fill Subject = "QA TEST template",
   Body = "Holiday closure 2026.". Tick **Save as template only**
   (do NOT tick Activate). Save.

**Expected:**
- Row appears with the **template** pill.
- The Use template button appears on the row.

2. Sign out + back in.

**Expected:** modal still shows the currently active
announcement (#2), NOT the template.

3. From `/preview/admin/announcements`, click **Use template**
   on the template row.

**Expected:**
- A NEW announcement (id 4) is created with the template's
  subject + body and immediately activated.
- The template stays as a template — same row, still flagged.
- The previous active announcement (#2) is now inactive.

### 19h. Expiry honored

1. Edit announcement #4 to set Expires at = a time 5 minutes in
   the past. Save.

**Expected:** the list still shows #4 as "active" but the
modal does NOT appear on next login (expiry filter at read time
hides it).

2. Edit #4 to clear the expiry (or set it 1 day in the future).

**Expected:** modal returns on next login.

### 19i. Delete cascades

1. From the list, click **Delete** on the announcement that has
   "Seen by N" > 0. Confirm.

**Expected:**
- Row vanishes from the list.
- `/preview/admin/audit?action=ANNOUNCEMENT_DELETED` shows the
  delete row.
- Subsequent logins by the QA user no longer show the modal
  (the table row is gone; no active announcement).

Note: dismissals for the deleted announcement become orphan rows
(no FK cascade since `account` is MyISAM and we don't enforce
FKs). The seen-by report becomes inaccessible. A future cleanup
can prune; not blocking.

---

## 20. Invite codes (admin-managed, single-use)

Admin and above can mint 8-character uppercase alphanumeric codes
that gate the public `/register` path (registration ships in a
follow-up branch). Each code is single-use, optionally expiring,
and optionally tied to an invitee email so Resend can deliver
the link automatically.

Pre-req: signed in as the QA Super Admin (Admin works too).

### 20a. Admin surface renders

1. Visit `/preview/admin`. Click **Invite codes →**.

**Expected:**
- Heading "Invite codes".
- A "+ New invite" button and a table (likely empty on a fresh
  preview).

### 20b. Mint a code without email

1. Click **+ New invite**. Leave the email + expiry blank. Pick
   "Auto-delete the row" (default). Click **Mint code**.

**Expected:**
- Redirect to `/admin/invites` with a flash banner:
  `Created invite XXXXXXXX (no email on file): https://paytracker.xyz/preview/register?invite=XXXXXXXX`
- The list shows the new row with the **active** pill, the
  invite URL shown beneath the code for easy copy/paste, and
  invitee email `—`.
- `/preview/admin/audit?action=INVITE_CREATED` shows a new row
  with the code, expiry (null), and `email_status: "no email
  on file"` in the metadata blob.

### 20c. Mint a code WITH email (Resend delivery)

Pre-req: Resend configured + `MAIL_FROM` set to a verified
sender on preview.

1. Mint again. Set invitee email = a real address you can read.
   Leave expiry blank. Save.

**Expected:**
- Flash banner reads
  `Created invite YYYYYYYY (emailed to <email> (msg <id>)): https://…`
- The email arrives in the recipient's inbox with subject
  "You're invited to PayTracker" and a "Create your account"
  button pointing at the same URL.
- Audit metadata `email_status` reads
  `emailed to <email> (msg …)`.

When Resend is unconfigured the flash reads `Resend not
configured` and the URL is still shown for manual copy/paste.
When Resend returns an error the flash reads `email to <addr>
FAILED — see Resend logs`.

### 20d. Edit + re-send

1. From the list, click **Edit** on an active code.

**Expected:**
- Form pre-populated with the current invitee email, expiry,
  and auto-delete radio.

2. Change the email to a different address. Save.

**Expected:** flash `Updated invite YYYYYYYY.` and a new
`INVITE_UPDATED` audit row.

3. Click **Re-send email** on the row.

**Expected:** flash with the latest email-status string, plus
an `INVITE_EMAILED` audit row attributing the re-send to the
admin actor.

### 20e. Expiry honored on the list

1. Edit a code, set expires_at to a time a few minutes in the
   past. Save.

**Expected:** the row's state pill flips from **active** to
**expired**. The invite URL no longer renders under the code
(it's already invalid; rendering it would invite confusion).

### 20f. Cannot edit a consumed code

This step is informational until branch 2 ships — currently
nothing consumes codes. Once /register lands, edits on a
consumed row should fail with
`Cannot edit an invite that has already been consumed.`

### 20g. Revoke

1. Click **Revoke** on any row. Confirm.

**Expected:** flash `Revoked invite XXXXXXXX.`; row vanishes
from the list; `INVITE_REVOKED` audit row attributed to you.

### 20h. Cleanup

QA-test invites are caught by the qa-cleanup script's
`--invites` category (default category set). Run on the host:
```
ssh ...preview
cd /home/robshe48/paytracker/preview
php scripts/qa-cleanup.php --invites
php scripts/qa-cleanup.php --invites --apply
```

---

## 21. Invite-only registration (`/register`)

The public path to a brand-new account. No auth required (the
whole point is for someone WITHOUT an account to use it). The
invite code from Section 20 is the gate.

### 21a. Form renders from a URL invite

1. From `/preview/admin/invites`, copy an active code's
   `/register?invite=…` URL.
2. Open a private tab, paste the URL.

**Expected:**
- Heading "Create your PayTracker account".
- Invite-code field is pre-populated with the code from the URL.
- Email field is pre-populated with the row's `invitee_email`
  if one was set (admin pre-filled it at mint time).
- Username, password, confirm-password fields are blank.
- Hidden `_csrf` field exists.

### 21b. Successful registration auto-logs you in

1. Fill in:
   - Invite: leave the URL-pre-filled value as-is.
   - Username: `qareg<unique-suffix>` (matches `[A-Za-z0-9._-]{3,32}`).
   - Email: any valid address (you can change the pre-filled one).
   - Password: ≥ 12 characters.
   - Confirm password: matches.
2. Click **Create account & sign in**.

**Expected:**
- Redirect to `/preview/` (home).
- Green flash banner: `Welcome, <username> — your account is ready.`
- The home page shows the welcome card for your new account.
- `/preview/admin/audit?action=USER_REGISTERED` shows a row with
  your new account id, username, and email in the metadata.
- `/preview/admin/audit?action=INVITE_USED` shows the matching
  consume row with the code.
- The invite code in the admin list: if it was auto-delete=on, it's
  gone; if auto-delete=off, it shows "used by `<username>`".

### 21c. Validation errors preserve the form

Repeat 21b but with one bad field each — verify the flash text
matches and the OTHER fields stay populated on redirect (no
re-typing required, except passwords which are intentionally
not preserved). Test cases:

- Missing invite code → `You need an invite code…`
- Malformed invite (≠ 8 chars) → `That doesn't look like a valid invite code.`
- Bad username (`a b c` or `bad@user`) → `Username must be 3-32 characters…`
- Bad email → `Email is required and must be a valid address.`
- Short password (e.g. 6 chars) → `Password must be at least 12 characters.`
- Mismatched confirm → `Passwords do not match.`
- Existing username → `That username is already taken.`
- Existing email → `An account already exists for that email.`

Each failure also lands a row at
`/preview/admin/audit?action=USER_REGISTER_FAILED` with a
`reason` discriminator (`missing_invite`, `malformed_invite`,
`bad_username`, `bad_email`, `weak_password`, `password_mismatch`,
`user_taken`, `email_taken`).

### 21d. Consumed code cannot be reused

1. Take a code that was just successfully consumed in 21b (only
   relevant when auto_delete was off, otherwise the row's gone).
2. Visit `/preview/register?invite=<that-code>`. Submit with
   any valid form values.

**Expected:**
- Red flash: `That invite is no longer valid. It may have been
  used, expired, or revoked. Ask the admin for a new one.`
- No new account row.
- Audit shows `USER_REGISTER_FAILED` with reason `invite_not_live`.

### 21e. Expired code refused

1. Edit a code in `/admin/invites/<id>/edit` and set expires_at
   a few minutes in the past. Save.
2. Visit the registration URL with that code.

**Expected:** same flash as 21d (`That invite is no longer
valid…`). The user-facing message intentionally doesn't
distinguish "expired" from "used" — both are equally final from
the registrant's perspective.

### 21f. Concurrent submission lands at most one account

This is the transactional-consume invariant. Hard to script
manually; the documented test is to open the same registration
URL in two private tabs and submit both within a second or two
with different usernames/emails. Exactly ONE submission should
succeed; the other gets the "invite no longer valid" flash.

### 21g. Login page links here

1. Visit `/preview/login` while signed out.

**Expected:** the muted footer now reads
*"Have an invite code? Create an account →"* with a working link
to `/preview/register`.

---

## 22. Production is untouched

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

## 23. Security headers are present (optional — engineer-assisted)

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

## 24. Unconfirmed loads — "Store Load Info" OFF (localStorage path)

Drivers who don't have the dispatch FRTL # in hand can turn the
**Store Load Info** checkbox OFF at the top of `/loads/new`. The
load is computed by the server (so the pay math is identical),
returned as JSON, and stashed in the browser's `localStorage` under
the key `paytracker.unsavedLoads` (legacy storage key — the user-
facing term is "unconfirmed load"). Entries auto-expire after 24
hours from creation.

### 24a. Toggle hides FRTL + reveals pitfall box

1. Sign in. Visit `/preview/loads/new`.
2. Confirm **Store Load Info** is ON (default), the FRTL # field
   is visible, and the amber "Heads up" pitfall list is hidden.
3. Click the checkbox to turn it OFF.

**Expected:** the FRTL block collapses, the pitfall list appears
(bullets: today-only on dashboard, lost on browser/device change,
can't reconcile until edited to add FRTL, 24h auto-clear). Reloading
the page persists the OFF state.

### 24b. Scratchpad submit lands in localStorage and renders on dashboard

1. With **Store Load Info** OFF, fill pickup = `Panama City, FL`,
   delivery = `Lynn Haven, FL`, load type one-way, extra pay 0,
   notes `QA TEST scratchpad — safe to clean up`. Submit.

**Expected:**

- Lands on `/preview/dashboard`.
- Today's **Loads** table shows the new row at the top with FRTL `—`,
  the pay value the server computed, and the muted "unconfirmed —
  in this browser only" footer.
- The **Today** + **This Week** totals cards are bumped: Loads
  count +1, Net Pay shaded amber with a tooltip "Includes 1
  unconfirmed load(s)", Miles +(pickup→delivery distance).
- The **This Week** card shows an amber disclaimer paragraph:
  *"Heads up: these weekly totals include 1 unconfirmed load(s)..."*.
- The PHP database has NO new row.
- Browser DevTools → Application → Local Storage shows a
  `paytracker.unsavedLoads` key with the entry inside.

### 24c. Past-date dashboard hides unconfirmed rows

1. From the dashboard, click the `← <yesterday>` arrow.

**Expected:** the unconfirmed row from 24b does NOT appear (past
dates show only DB-backed loads — by spec). The This Week
disclaimer is also gone since hydration doesn't run on past dates.

### 24d. Edit unconfirmed row, type FRTL → auto-flip + save

1. Return to today's dashboard.
2. Click **Edit** on the unconfirmed row.

**Expected:** lands at `/preview/loads/new?unsaved=<localId>` (the
`unsaved` query param is the legacy on-wire name; the user-facing
heading reads "Edit unconfirmed load"). Form prefills with the
stored values, **Store Load Info** is OFF, FRTL block is hidden.

3. Toggle the checkbox ON (FRTL block reveals). Type a fresh
   FRTL like `999900050`. Submit.

**Expected:**

- Lands on `/preview/dashboard` with flash "Added load frtl=999900050".
- Back on the dashboard, the unconfirmed row is GONE (the localId
  was consumed). The new DB-backed row appears instead with the
  real FRTL in the column.
- The Today totals are unchanged from before — the unconfirmed
  amount was already counted; now the DB row contributes the same
  amount via the server-rendered totals.

### 24e. Discard button

1. Add another unconfirmed load (24b steps).
2. On the dashboard, click **Discard** on that row. Confirm the
   prompt.

**Expected:** page reloads; the row is gone from both the table
and `localStorage`.

### 24f. Rolling 24-hour expiry (engineer-assisted)

1. With one unconfirmed entry on the dashboard, open DevTools and
   manually edit the `created_at` field on the entry to
   `Date.now() - 25*60*60*1000` (25 hours ago). Reload.

**Expected:** the entry is purged from `localStorage` on next read
and the dashboard row vanishes.

---

## 25. Reconcile — per-load paid / short / disputed

The driver-facing `/reconcile` page lists every load from the current
pay week + the previous 4 weeks, lets the driver mark each one
**paid**, **short** (got less than expected), or **disputed**
(formally flagged with a note), and optionally queues disputes for
a batched email to a configured payroll contact.

### 25a. Anonymous access redirects to login

1. Sign out. Visit `/preview/reconcile` directly.

**Expected:** redirect to `/preview/login`.

### 25b. Signed-in render

1. Sign in. Visit `/preview/reconcile`.

**Expected:**

- Header "Reconcile your pay" card.
- "Pending payroll batch" card with a pill showing `0` (no
  disputes opted in yet).
- "This week" card listing your current pay-week loads, each with
  FRTL / Date / Pickup→Delivery / Expected pay / state pill =
  `pending` / action buttons.
- Four collapsible "Week of …" cards below for the previous 4 weeks.

### 25c. Mark Paid + Undo

1. Click **Paid** on a pending row.

**Expected:** flash `Marked load N paid.`, row tints green with a
`paid` pill.

2. Click **Undo** on the same row and confirm.

**Expected:** flash `Reset load N back to pending.`, row returns
to the default tint and `pending` pill.

### 25d. Mark Short

1. Click **Short…** on a pending row to reveal the inline form.
2. Enter an actual amount less than expected (e.g. `expected − 1`).
3. Optionally note "Mile rate looked low" and submit.

**Expected:** flash `Marked load N short by $1.00.`, row tints amber
with a `short` pill and the shortfall amount displayed below it.

### 25e. Dispute + payroll batch queue

1. Click **Dispute…** on a pending row.
2. Enter a note (required) and tick **Include in next payroll batch**.
3. Submit.

**Expected:**

- Flash `Flagged load N as disputed (added to the pending payroll batch).`
- Row tints red with a `disputed` pill and an `in next batch` sub-pill.
- "Pending payroll batch" pill count goes up by 1.

### 25f. Payroll contact email + Send batch button states

1. Visit `/preview/profile`. Confirm the **Payroll contact email**
   field is present, optional, type=email, validated client-side.
2. Leave it blank → Pending batch card shows a
   *"Set a payroll contact email →"* link to `/profile`; no send button.
3. Fill in a valid address (e.g. your own), save profile.
4. Return to `/reconcile`. If the server's `RESEND_API` is not set
   (mail not configured), the button reads
   *"Send batch (delivery pending)"* and is disabled with an amber
   "delivery pending" note.
5. Once `RESEND_API` is configured AND DNS verification has cleared,
   the button activates as **Send batch to &lt;your address&gt;**.
6. Click it. A confirm dialog appears with the address + count.

**Expected:** flash `Sent N disputed load(s) to <address>.`,
batched rows' sub-pill flips from `in next batch` to `batched`,
"Pending payroll batch" count returns to 0.

### 25g. Admin queue (Super Admin)

1. As a Super Admin, visit `/preview/admin/reconcile`.

**Expected:** table of every open disputed claim across every
driver, with driver `user` + login email + payroll email, FRTL,
expected / actual / shortfall, note, batch state, and updated
timestamp. Read-only — no action buttons.

2. As a base **Admin** (not Super Admin), visit the same URL.

**Expected:** 403 "Access denied — Super Admin or higher".

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
