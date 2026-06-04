import { test, expect } from '@playwright/test';
import { hasCredentials, signIn } from './helpers/auth';

/**
 * Mirrors Section 14 of docs/qa/test_plan.md — the four public,
 * no-auth, no-DB housekeeping pages: /tutorial, /faq, /about,
 * /contact.
 *
 * Pages 14a–14d intentionally run WITHOUT signing in; the pages
 * must work for prospective drivers who don't have an account
 * yet (the /contact page in particular is how they request one).
 *
 * 14e covers the home page's Help nav — gated behind the auth
 * card so anonymous visitors see a clean Sign-In page. Skipped
 * when QA credentials aren't configured.
 */
test.describe('static housekeeping pages', () => {
    test('14a — /tutorial renders anonymously', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('tutorial');
        await expect(page.getByRole('heading', { name: /^tutorial$/i })).toBeVisible();
        // Section headings the human-walked plan expects.
        await expect(page.getByRole('heading', { name: /set up your profile/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /add a load/i })).toBeVisible();
        // Embedded YouTube iframe is present.
        await expect(page.locator('iframe[src*="youtube.com/embed"]')).toHaveCount(1);
    });

    test('14b — /faq renders anonymously', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('faq');
        await expect(page.getByRole('heading', { name: /frequently asked questions/i })).toBeVisible();
        // Sample a couple of headings to confirm the content shipped.
        await expect(page.getByRole('heading', { name: /how is my pay calculated/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /begin empty and end empty/i })).toBeVisible();
    });

    test('14c — /about renders anonymously', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('about');
        await expect(page.getByRole('heading', { name: /about paytracker/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /what's different/i })).toBeVisible();
    });

    test('14d — /contact renders anonymously', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('contact');
        await expect(page.getByRole('heading', { name: /^contact$/i })).toBeVisible();
        // mailto: surfaces; we don't pin the address so it can change
        // without breaking tests.
        await expect(page.locator('a[href^="mailto:"]')).toHaveCount(1);
    });

    test('14e — Help nav is hidden on the anonymous home page', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('');
        // Anon home is intentionally minimal — Sign-In CTA only, no
        // Help nav. Prospective drivers can still reach the four
        // static pages by direct URL (covered in 14a–14d).
        await expect(page.getByRole('heading', { name: /help & info/i })).toHaveCount(0);
    });

    test('14f — signed-in home page surfaces the Help nav', async ({ page }) => {
        test.skip(!hasCredentials(), 'QA_TEST_USER / QA_TEST_PASSWORD not configured');
        await signIn(page);
        await page.goto('');
        await expect(page.getByRole('heading', { name: /help & info/i })).toBeVisible();
        // The nav links should exist by accessible name. Use the link role
        // to avoid matching headings or body mentions.
        for (const name of [/tutorial/i, /^faq$/i, /^about$/i, /^contact$/i]) {
            await expect(page.getByRole('link', { name })).toBeVisible();
        }
    });
});
