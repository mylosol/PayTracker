-- Backhaul flag on driver_loads.
--
-- Drivers who take a backhaul on a load earn a flat $40 differential
-- on top of the normal pay math. Modeled as a boolean column on the
-- load row (mirrors is_split / is_weekend) rather than a separate
-- ledger row so a load's pay summary stays a single record and the
-- PayCalculator can add the $40 as another "extra" alongside split
-- ($15), extra_pay, demurrage, and breakdown.
--
-- Amount is hard-coded in PayCalculator for now (same pattern as
-- split's $15). If payroll ever changes it, we'll promote it to
-- pay_variables like the demurrage/breakdown rates.

ALTER TABLE `driver_loads`
    ADD COLUMN `is_backhaul` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_weekend`
