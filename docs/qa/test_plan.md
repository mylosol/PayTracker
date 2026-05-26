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

## 5. Production is untouched

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

## 6. Security headers are present (optional — engineer-assisted)

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
