import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration.
 *
 * Single browser project (Chromium) is enough for the current surface — the
 * legacy app's user base is consistent and we're not chasing cross-browser
 * pixel-perfect rendering yet. Adding firefox/webkit projects later is a
 * one-line change.
 *
 * BASE_URL defaults to the preview channel; override locally with
 *   BASE_URL=http://localhost:8080 npx playwright test
 * Override in CI by setting the env var in the workflow step.
 *
 * QA_TEST_USER / QA_TEST_PASSWORD are read by tests/e2e/helpers/auth.ts.
 * When unset, auth-requiring tests skip with a clear reason rather than
 * failing — keeps local-dev unblocked even without the secrets configured.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: process.env.CI ? [['github'], ['list']] : 'list',
    timeout: 30_000,
    expect: {
        timeout: 5_000,
    },
    use: {
        baseURL: process.env.BASE_URL ?? 'https://paytracker.xyz/preview/',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        // Be a polite UA — flagged so the legacy site can identify automated
        // traffic in its access logs if anyone ever looks.
        userAgent: 'PayTrackerE2E/1.0 (+playwright)',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
