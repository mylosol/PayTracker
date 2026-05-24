# PayTracker — QA Test Plan (Preview Channel)

This document is the single source of truth for verifying a PayTracker
feature build on the preview channel. It is written for a human tester
who has never seen the codebase: every step is something you can do in a
web browser.

If you find a problem at any step, **stop and report it**. Note the step
number, what you expected, and what you actually saw. Do **not** touch
the production site (https://paytracker.xyz/) while testing — the preview
lives at a separate URL specifically so production stays untouched.

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

## 7. Production is untouched

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

## 8. Security headers are present (optional — engineer-assisted)

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
