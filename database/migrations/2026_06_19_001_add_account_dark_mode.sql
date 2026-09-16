-- Per-user dark-mode preference.
--
-- Stored on the account so the choice persists across logout/login
-- and across devices (vs. a client-side localStorage flag that
-- resets when the driver picks up a different phone). Default 0
-- (off) — light mode is the brand default; dark mode is opt-in
-- from the Profile page.

ALTER TABLE `account`
    ADD COLUMN `dark_mode` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `payroll_email`;
