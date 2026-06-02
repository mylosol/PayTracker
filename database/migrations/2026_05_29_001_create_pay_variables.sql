-- pay_variables — relational replacement for the legacy variablesDefault /
-- variablesCurrent / variablesTest tables.
--
-- These hold the global constants the np/op formula reads:
--   raise            — multiplier applied to base rate from pay_rates
--   trainer_pay      — flat amount for load_type=4 (trainer)
--   demurrage        — cents-per-minute for dem_minutes
--   breakdown        — cents-per-minute for break_minutes
--   {X}_mt           — empty-mile rate ($/mile) for tenure band X
--   {X}_newBump      — seniority multiplier for tenure band X
--   {X}_night        — night-shift multiplier for tenure band X
--   {X}_wk           — weekend multiplier for tenure band X
--   {X}_tb           — historical "tenure bump"; queried but never used
--                      by the formula (we still backfill for fidelity).
-- where X ∈ {6, 12, 24, 60, 108, 168, max}.
--
-- The sampler captured 32 rows in each of Default and Current — same
-- shape, different amounts (preview shows ALL `_mt` and `raise` drift).
--
-- Stages (same model as pay_rates):
--   default — factory baseline; restored by "reset to default".
--   current — live values the formula reads.
--   draft   — admin's working copy; promote → current.
--
-- Variables don't vary per terminal or trip_type — they're global. So
-- the PK is just (stage, variable).

CREATE TABLE IF NOT EXISTS `pay_variables` (
    `stage`     VARCHAR(8)     NOT NULL,
    `variable`  VARCHAR(40)    NOT NULL,
    `amount`    DECIMAL(12,6)  NOT NULL,
    PRIMARY KEY (`stage`, `variable`),
    INDEX `idx_pay_variables_stage` (`stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
