-- Configurable payroll-contact email per driver.
--
-- Drivers can opt-in dispute notifications to a contact at their
-- carrier's payroll desk; the email is sent ONLY in batches when
-- the driver explicitly clicks "Send batch" on /reconcile, never
-- automatically.
--
-- Independent of `account.email` (which is the driver's own login /
-- password-reset address). Nullable + no default — drivers without
-- a payroll contact simply can't trigger batch notifications, which
-- the UI gracefully degrades around.

ALTER TABLE `account`
    ADD COLUMN `payroll_email` VARCHAR(255) NULL DEFAULT NULL
    AFTER `email`;
