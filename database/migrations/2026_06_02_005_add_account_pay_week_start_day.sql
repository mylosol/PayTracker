-- Per-driver pay-week start day.
--
-- Different carriers use different weekly boundaries:
--   sun → Sun-Sat (US default — most common)
--   mon → Mon-Sun (European convention; some US carriers too)
--   fri → Fri-Thu (a few carriers settle paychecks Thursday)
--   etc.
--
-- The dashboard's "This Week" card computes its window from this
-- column, so a driver only sees the boundary their pay actually
-- uses. ENUM keeps storage tight and validation server-side; the
-- profile form exposes a dropdown.
--
-- Default 'sun' preserves the previous hardcoded behaviour for
-- accounts that haven't set a preference yet.

ALTER TABLE `account`
    ADD COLUMN `pay_week_start_day`
        ENUM('sun','mon','tue','wed','thu','fri','sat')
        NOT NULL DEFAULT 'sun'
        AFTER `shift`
