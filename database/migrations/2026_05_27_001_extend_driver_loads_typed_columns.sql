-- Extend driver_loads with typed columns parsed from the legacy `loadinfo`
-- blob. The legacy hyphen-string format stays in place (variables / loadinfo
-- / paid) so the existing read-only /loads surface and any unported legacy
-- pages continue to work; the new load-entry write path populates BOTH the
-- typed columns AND the blobs.
--
-- Column shapes were derived by sampling 3,313 live rows on preview (see
-- scripts/sample-driver-loads.php output captured in CI on 2026-05-27).
-- 3307 rows have the well-formed 14-field loadinfo; 6 are legacy-corrupted
-- with only 2 fields and will be left NULL by the backfill.
--
-- Position mapping (loadinfo[0..13]):
--   0  load_type           tinyint  (0=loaded/oneway, 1=round-trip, 4=other)
--   1  empty_miles         int
--   2  pickup_city         varchar(64)  — terminal/yard ("Panama City, FL")
--   3  delivery_city       varchar(64)  — destination
--   4  is_split            tinyint (0/1)
--   5  is_weekend          tinyint (0/1)
--   6  legacy constant "2" — preserved in loadinfo, not extracted
--   7  begin_empty_miles   int
--   8  used_google_maps    tinyint (0/1)
--   9  extra_pay           decimal(6,2)
--  10  dem_minutes         int
--  11  break_minutes       int
--  12  out_of_route_ind    tinyint
--  13  out_of_route_miles  int
--
-- The terminal_pcola flag tracks which terminal-group the load belongs to
-- (Panama City vs Pensacola — legacy `pcola` cookie). It is derived from
-- the driver's account row at write time and persisted here so historical
-- queries don't have to re-resolve it.

ALTER TABLE `driver_loads`
    ADD COLUMN `load_type`          TINYINT       NULL AFTER `op`,
    ADD COLUMN `empty_miles`        INT           NULL,
    ADD COLUMN `pickup_city`        VARCHAR(64)   NULL,
    ADD COLUMN `delivery_city`      VARCHAR(64)   NULL,
    ADD COLUMN `is_split`           TINYINT       NULL,
    ADD COLUMN `is_weekend`         TINYINT       NULL,
    ADD COLUMN `begin_empty_miles`  INT           NULL,
    ADD COLUMN `used_google_maps`   TINYINT       NULL,
    ADD COLUMN `extra_pay`          DECIMAL(6,2)  NULL,
    ADD COLUMN `dem_minutes`        INT           NULL,
    ADD COLUMN `break_minutes`      INT           NULL,
    ADD COLUMN `out_of_route_ind`   TINYINT       NULL,
    ADD COLUMN `out_of_route_miles` INT           NULL,
    ADD COLUMN `terminal_pcola`     TINYINT       NULL,
    ADD INDEX `idx_driver_loads_pickup`   (`pickup_city`),
    ADD INDEX `idx_driver_loads_delivery` (`delivery_city`)
