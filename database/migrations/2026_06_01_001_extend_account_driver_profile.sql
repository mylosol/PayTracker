-- Driver-profile columns on `account`.
--
-- These power tenure-aware and shift-aware pay calculation. Until this
-- migration landed, the modern PayCalculator received a hardcoded
-- `'168-night--0'` variables blob for every load — meaning seniors got
-- correct pay, juniors had their pay inflated to the senior band, and
-- day-shift loads were always credited the night bonus. That is the
-- most likely contributor to np mismatches against legacy production.
--
-- hire_date: DATE, nullable
--   Driver-entered on the profile page. We compute months-since-hire at
--   load-write time to slot the driver into a tenure band (6, 12, 24,
--   60, 108, 168 MONTHS — matching the legacy `169+ M` pill, NOT weeks;
--   >168 stays at 168, matching the calculator's existing top band).
--   NULL means "not yet set" and we fall back to the JUNIOR band (6)
--   rather than the senior — under-paying a senior whose profile is
--   unset is recoverable via /pay-admin/recompute, over-paying a junior
--   is much harder to claw back, so floor is safer.
--
-- shift: ENUM('day','night'), default 'day'
--   Driver-entered on the profile page. Snapshotted into the load's
--   variables blob on every insert so the per-load record stays the
--   historical truth even if the driver swaps shifts later.

ALTER TABLE `account`
    ADD COLUMN `hire_date` DATE NULL DEFAULT NULL AFTER `locked_until`,
    ADD COLUMN `shift` ENUM('day','night') NOT NULL DEFAULT 'day' AFTER `hire_date`
