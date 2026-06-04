-- pay_rates — relational replacement for the six legacy pay-rate tables:
--
--   PensacolaPayDefault   (Pensacola, round-trip,  factory)
--   PensacolaPayCurrent   (Pensacola, round-trip,  live)
--   LHPensacolaPayDefault (Pensacola, long-haul,   factory)
--   LHPensacolaPayCurrent (Pensacola, long-haul,   live)
--   PanamaPay             (Panama City, single,    live)
--
-- The legacy schema split rate data across one table per (terminal × trip-type
-- × stage) combination, plus extra Test/Temp shadow tables driving the
-- legacy "preview before going live" admin flow. We collapse all of that
-- into a single table keyed by (terminal, trip_type, stage, miles).
--
-- Stages:
--   default — read-mostly factory baseline. Reset-to-defaults restores from
--             these rows. Backfilled from *PayDefault tables (or from the
--             only live snapshot for terminals that have no Default twin —
--             PanamaPay falls into this bucket).
--   current — the live rate used to compute pay. Backfilled from *PayCurrent.
--   draft   — admin's working copy. Edits land here first; "Promote to
--             current" copies draft → current atomically. "Reset draft"
--             copies current → draft. (Collapses the legacy Test+Temp
--             two-step into a single draft stage — the legacy distinction
--             between them is an artefact of the cookie-driven flow with
--             no semantic value.)
--
-- Terminals: 'pensacola' and 'panama'. Stored as short string keys rather
-- than an enum so we can add more terminals later without an ALTER. The
-- model layer validates them against a known list.
--
-- Trip types: 'round_trip' (rtb) and 'long_haul' (lhb). Panama only uses
-- 'round_trip' currently — the legacy PanamaPay table has no long-haul
-- counterpart and the schema permits future extension to long-haul without
-- changing.
--
-- Miles: SMALLINT UNSIGNED — preview sample shows max=250, fits easily.
-- Rate: DECIMAL(10,4) — preview sample uses 4 decimal places (e.g. 32.0039)
-- and a sentinel value of 999.9999 exists in PensacolaPayDefault.miles=122.
-- Keeping the legacy precision avoids drift on backfill. The math is dollars
-- so 4dp is overkill, but reproducing the legacy values exactly is the goal.
--
-- Engine: InnoDB for proper transactional promote/reset semantics. The
-- legacy MyISAM tables stay where they are; we read them only at backfill.

CREATE TABLE IF NOT EXISTS `pay_rates` (
    `terminal`   VARCHAR(16)        NOT NULL,
    `trip_type`  VARCHAR(16)        NOT NULL,
    `stage`      VARCHAR(8)         NOT NULL,
    `miles`      SMALLINT UNSIGNED  NOT NULL,
    `rate`       DECIMAL(10,4)      NOT NULL,
    PRIMARY KEY (`terminal`, `trip_type`, `stage`, `miles`),
    INDEX `idx_pay_rates_lookup` (`terminal`, `trip_type`, `stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
