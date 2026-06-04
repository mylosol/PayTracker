-- `audit_logs` — append-only audit trail.
--
-- Design principles:
--   * WORM (Write-Once, Read-Many). The application layer exposes
--     only INSERT and SELECT — no UPDATE / DELETE API. A row written
--     here stays written.
--   * UTC timestamps. The default fires UTC_TIMESTAMP() so the
--     trail is timezone-agnostic regardless of the request's
--     APP_TIMEZONE. Localisation happens at the view, not in
--     storage.
--   * Nullable user_id so we can record failed logins where the
--     attempted handle didn't resolve to an existing account (no
--     account = no FK to point at, but the IP / username / UA we
--     captured are still investigable).
--   * `metadata` as JSON for the variable-shape context (target
--     account on admin actions, fingerprint headers on login
--     attempts). Indexed fields stay as dedicated columns so we
--     can query them without JSON_EXTRACT in the hot path.
--
-- Why a dedicated `ip_address` column rather than reading from
-- the metadata blob: the IP is the most-filtered field in the
-- audit view (security investigations follow IPs), and a JSON
-- function in the WHERE clause prevents index usage. Storing it
-- separately keeps the per-IP queries cheap.

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`         BIGINT       NOT NULL AUTO_INCREMENT,
    `user_id`    INT          NULL DEFAULT NULL,
    `action`     VARCHAR(50)  NOT NULL,
    `reason`     VARCHAR(40)  NULL DEFAULT NULL,
    `ip_address` VARCHAR(45)  NULL DEFAULT NULL,
    `metadata`   JSON         NULL DEFAULT NULL,
    `timestamp`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_user_ts`  (`user_id`,    `timestamp`),
    KEY `idx_audit_logs_action`   (`action`,     `timestamp`),
    KEY `idx_audit_logs_ip`       (`ip_address`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
