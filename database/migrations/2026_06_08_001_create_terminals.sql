-- Consolidated Begin Empty / pickup-terminal list.
--
-- Replaces the legacy split between `terminal` (non-Pcola drivers' allowed
-- pickup hubs) and `pcola_terminal` (Pcola drivers' allowed hubs). The split
-- was a cookie-driven scoping artifact of the two-app era; the modern app
-- already unions both tables via Terminal::all() and per-driver scoping is
-- dead code.
--
-- This is the single source of truth going forward:
--
--   `name`     The display string (e.g. "Panama City, FL"). Must match the
--              format used in `city.city` so distance-cache lookups via
--              CityDistance::lookupOrFetch resolve cleanly. The UNIQUE
--              constraint guarantees the admin CRUD can use ON DUPLICATE
--              KEY UPDATE semantics safely.
--
--   `city_id`  Optional FK into `city`. Populated by the backfill where a
--              name match exists; left NULL when the legacy row's terminal
--              string never made it into the city list. The Begin Empty
--              picker will use city_id (when present) for distance
--              lookups; the name fallback covers the missing cases.
--
--   `active`   Soft-deactivate flag. The admin surface DELETEs by flipping
--              this to 0 rather than dropping rows, so historical loads
--              that referenced a now-removed terminal still tell a
--              coherent story in audit + future analytics. The picker
--              only shows active=1 rows.
--
-- The two legacy tables stay in place for now — drop migration is staged
-- for a follow-up branch after the modern flow soaks in production.

CREATE TABLE IF NOT EXISTS `terminals` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120)  NOT NULL,
    `city_id`    INT           DEFAULT NULL,
    `active`     TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_name` (`name`),
    KEY `idx_active` (`active`),
    KEY `idx_city` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
