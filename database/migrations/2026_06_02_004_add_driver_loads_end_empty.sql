-- End-empty leg on one-way loads.
--
-- A one-way load has THREE locations: pickup terminal, delivery, and
-- where the driver ends up after dropping the load (typically driving
-- empty back to a terminal). The empty leg gets paid at the band's
-- `mt` rate and the seniority/shift/weekend overlays scale on the
-- combined (loaded + empty) total.
--
-- Until now the modern form captured only pickup + delivery, so the
-- calculator received empty_miles=0 and one-way loads under-paid by
-- the empty-leg amount plus the overlays on it.
--
-- Two new typed columns:
--   end_empty_city  VARCHAR(80) NULL  — city where the driver ended.
--                                       NULL = round-trip or no empty
--                                       leg recorded.
--   end_empty_miles INT NULL          — distance delivery → end_empty,
--                                       resolved from city_distances
--                                       at write time (Google Maps
--                                       fallback if not cached).
--
-- Why not reuse the existing `empty_miles` column? The legacy schema
-- used `empty_miles` for the LOADED leg distance (a historical
-- misnomer the modern code inherited). Adding distinct columns
-- avoids overloading semantics. The misnamed column stays for read
-- back-compat; the PayCalculator now reads loaded miles from
-- `empty_miles` and empty leg from `end_empty_miles`.

ALTER TABLE `driver_loads`
    ADD COLUMN `end_empty_city`  VARCHAR(80) NULL DEFAULT NULL AFTER `delivery_city`,
    ADD COLUMN `end_empty_miles` INT         NULL DEFAULT NULL AFTER `end_empty_city`
