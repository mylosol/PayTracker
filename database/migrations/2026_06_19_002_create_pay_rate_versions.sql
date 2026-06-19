-- Pay-rate version history.
--
-- The existing `pay_rates` table is a 3-stage editor surface
-- (default | current | draft). `current` is the live tier set
-- PayCalculator reads — but it has no notion of WHEN it became
-- live, so a Recompute after a rate change retroactively revalues
-- every historical load at the new rates. Not what payroll wants.
--
-- This table is the immutable history.
--
--   trip_type        same enum as pay_rates ('round_trip' | 'long_haul')
--   miles            tier ceiling (same semantics as pay_rates.miles)
--   rate             dollar rate at that tier
--   effective_from   DATE the version became live (inclusive)
--   effective_until  DATE the version was superseded (exclusive),
--                    NULL while the version is the active one
--
-- Promote flow (PayRate::promoteDraftToCurrent):
--   1. Stamp every active version (effective_until IS NULL) for the
--      trip_type with effective_until = <admin-chosen date>.
--   2. Insert new active versions from the draft tier rows with
--      effective_from = <admin-chosen date>, effective_until = NULL.
--
-- Lookup (PayRateVersion::lookup):
--   "Latest tier ≥ load_miles whose effective_from ≤ load_date AND
--    (effective_until IS NULL OR load_date < effective_until)".
--
-- The terminal dimension is omitted — pay_rates already collapses
-- it to a single canonical 'pensacola' set on every read/write, so
-- carrying it here would be cargo-culted complexity.

CREATE TABLE IF NOT EXISTS `pay_rate_versions` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trip_type`       VARCHAR(16)  NOT NULL,
    `miles`           SMALLINT UNSIGNED NOT NULL,
    `rate`            DECIMAL(10,4) NOT NULL,
    `effective_from`  DATE NOT NULL,
    `effective_until` DATE NULL DEFAULT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lookup` (`trip_type`, `effective_from`, `effective_until`, `miles`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the first version from whatever is currently live. The epoch
-- 2020-01-01 sits comfortably before any load in the system, so on
-- the first deploy every historical load resolves to today's rates
-- (no value change vs. the pre-versioning behaviour). Future
-- promotes layer real-effective-date versions on top.
INSERT INTO `pay_rate_versions` (`trip_type`, `miles`, `rate`, `effective_from`, `effective_until`)
SELECT `trip_type`, `miles`, `rate`, '2020-01-01', NULL
FROM `pay_rates`
WHERE `terminal` = 'pensacola' AND `stage` = 'current';
