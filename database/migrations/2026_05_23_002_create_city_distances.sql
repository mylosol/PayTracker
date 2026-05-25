-- city_distances — relational replacement for the column-per-city `largeMiles`
-- / `pcola_largeMiles` matrix tables.
--
-- Each row records the road distance between two cities under a named
-- "source" (the legacy table this datum came from — different terminal
-- regions historically maintained their own matrices, sometimes with
-- different values for the same pair). The composite primary key
-- (from_city_id, to_city_id, source) lets us carry that fidelity forward
-- without losing data on backfill, and lets future writes record new
-- pair-source-miles tuples without colliding with backfilled rows.
--
-- Why no FOREIGN KEY constraints: the `city` table is MyISAM, which does
-- not support enforced foreign keys. Migrating `city` to InnoDB is a
-- separate concern and out of scope for this branch (the legacy app
-- continues to read from `city` and the change would touch live data).
-- The model layer enforces the city_id → city.id link instead.
--
-- Index strategy:
--   - PRIMARY KEY (from_city_id, to_city_id, source) — fast lookup in the
--     "from this city, where can I go?" direction.
--   - INDEX idx_distances_to_from (to_city_id, from_city_id) — fast lookup
--     in the reverse direction (less common but used by the "incoming
--     loads" reports the legacy app surfaces).
--
-- Engine choice: InnoDB. We want row-level locking for the eventual write
-- path (next branch) and proper transactional semantics. Legacy tables
-- stay MyISAM — they aren't touched.

CREATE TABLE IF NOT EXISTS `city_distances` (
    `from_city_id` INT NOT NULL,
    `to_city_id`   INT NOT NULL,
    `miles`        INT NOT NULL,
    `source`       VARCHAR(40) NOT NULL,
    PRIMARY KEY (`from_city_id`, `to_city_id`, `source`),
    INDEX `idx_distances_to_from` (`to_city_id`, `from_city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
