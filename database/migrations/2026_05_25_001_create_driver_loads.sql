-- driver_loads — relational replacement for the 21 per-driver `loadsNN`
-- tables, where N is an `account.id`.
--
-- Schema rationale:
--   - Columns mirror the live shape of loadsNN exactly (variables is 100 not
--     30 as the old SQL dump claimed; the live schema drifted over the
--     years). The backfill is a verbatim copy with `driver_id = N` added.
--   - Composite PRIMARY KEY (driver_id, frtl) clusters rows by driver on
--     InnoDB, which is the access pattern that dominates: "show this
--     driver's loads", and "find this driver's load with this frtl".
--   - Index on (driver_id, date) for the date-range queries (weekly pay
--     reports, recent loads, etc.).
--   - No FK to `account.id` — the legacy `account` table is MyISAM and
--     enforcing FKs would require migrating it. The model layer rejects
--     unknown driver_ids on the write path (next branch).
--
-- Engine choice: InnoDB. Same reasoning as city_distances — row-level
-- locking for the eventual write path, transactional semantics, proper
-- clustering. The legacy MyISAM `loadsNN` tables stay where they are.

CREATE TABLE IF NOT EXISTS `driver_loads` (
    `driver_id` INT          NOT NULL,
    `frtl`      INT          NOT NULL,
    `date`      DATETIME     NOT NULL,
    `variables` VARCHAR(100) NOT NULL DEFAULT '',
    `loadinfo`  VARCHAR(100) NOT NULL DEFAULT '',
    `paid`      VARCHAR(20)  NOT NULL DEFAULT '1-0-0-0-0-0-0-0',
    `notPaid`   INT          NOT NULL DEFAULT 0,
    `notes`     VARCHAR(1000) NULL DEFAULT NULL,
    `np`        DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `op`        DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`driver_id`, `frtl`),
    INDEX `idx_driver_loads_date` (`driver_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
