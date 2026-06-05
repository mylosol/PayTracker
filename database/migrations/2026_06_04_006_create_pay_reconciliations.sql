-- Per-load reconcile state.
--
-- One row per (driver_id, frtl) the driver has acted on. No row means
-- the load is still pending reconcile. The unique key guarantees a
-- single open claim per load — re-marking a load updates the existing
-- row rather than stacking.
--
-- Snapshots `expected_np` at reconcile time so historical claims
-- survive later rate edits or PayCalculator changes. `actual_np` is
-- only meaningful when state in ('short','disputed') — for `paid` we
-- treat actual == expected.
--
-- `notify_email` is the "include this dispute in the next payroll
-- batch" opt-in flag, set when the driver tickets it in the UI.
-- `emailed_at` is set when a batch email containing this row is
-- successfully sent; (notify_email=1 AND emailed_at IS NULL) is the
-- pending-batch predicate the UI counts on.

CREATE TABLE IF NOT EXISTS `pay_reconciliations` (
    `id`             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `driver_id`      INT               NOT NULL,
    `frtl`           INT               NOT NULL,
    `state`          ENUM('paid','short','disputed') NOT NULL,
    `expected_np`    DECIMAL(10,2)     NOT NULL,
    `actual_np`      DECIMAL(10,2)     DEFAULT NULL,
    `shortfall`      DECIMAL(10,2)     DEFAULT NULL,
    `note`           TEXT              DEFAULT NULL,
    `notify_email`   TINYINT(1)        NOT NULL DEFAULT 0,
    `emailed_at`     DATETIME          DEFAULT NULL,
    `reconciled_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_driver_frtl` (`driver_id`, `frtl`),
    KEY `idx_state` (`state`),
    KEY `idx_driver_state` (`driver_id`, `state`),
    KEY `idx_notify_pending` (`notify_email`, `emailed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
