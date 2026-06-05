-- Extend pay_reconciliations with itemised dispute details.
--
-- The redesign collapses the per-load "short" state into the existing
-- "disputed" state — being short IS dispute-worthy. The dispute form
-- now asks the driver to check WHICH pay components were missing /
-- wrong (so payroll knows what to fix, not just that the total was
-- off) and to enter the actual amount they were paid (required, not
-- optional).
--
-- Schema additions:
--   disputed_components    JSON list of pay-component keys the driver
--                          checked. Each value is one of the
--                          PayCalculator breakdown slots (base_pay,
--                          empty_pay, shift_pay, seniority_pay,
--                          weekend_pay, split_pay, dem_pay, break_pay,
--                          extra_pay). Stored as TEXT so older MariaDB
--                          builds without a true JSON type still work;
--                          PHP serialises via json_encode/json_decode.
--   disputed_other_amount  Catch-all dollar value for shortfalls that
--                          don't map to a single component (e.g. the
--                          paystub-total math is off by some odd
--                          figure). Optional.
--
-- The legacy `state = 'short'` rows from prior QA passes are swept
-- by the qa-cleanup pre-test step, so we don't migrate them.

ALTER TABLE `pay_reconciliations`
    ADD COLUMN `disputed_components`   TEXT          DEFAULT NULL AFTER `note`,
    ADD COLUMN `disputed_other_amount` DECIMAL(10,2) DEFAULT NULL AFTER `disputed_components`;
