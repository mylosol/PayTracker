-- `announcements` — Super-Admin authored site-wide messages
-- displayed to users as a modal at login.
--
-- Schema rationale:
--   * subject + body kept in one row -- the legacy site-down page
--     proved how cheap a single-row CMS is for a use case this
--     light. No need for revisions.
--   * is_active TINYINT plus an enforcement note: only ONE
--     announcement is shown to users at a time. We enforce this
--     in the controller (activating one row deactivates all
--     others in the same transaction). A UNIQUE-on-(is_active=1)
--     constraint would also work but MariaDB 10.11 doesn't have
--     filtered unique indexes; PHP-side enforcement is enough.
--   * is_template TINYINT separates "ready to broadcast" from
--     "saved for later reuse". A template is never shown to users
--     even if its is_active gets set by accident -- the read query
--     filters both flags.
--   * expires_at NULL means "never expires; lives until deleted".
--     Non-null sets a soft sunset -- the read query checks
--     `expires_at IS NULL OR expires_at > UTC_TIMESTAMP()`.
--   * created_by is a soft FK to account.id (no enforced FK
--     because account is MyISAM legacy). Used to attribute audit
--     events.
--
-- TEXT body, not VARCHAR, because admins occasionally want a
-- multi-paragraph notice and TEXT scales to 64KB on InnoDB without
-- the row-size pressure VARCHAR(8000) would impose.

CREATE TABLE IF NOT EXISTS `announcements` (
    `id`            INT             NOT NULL AUTO_INCREMENT,
    `subject`       VARCHAR(255)    NOT NULL,
    `body`          TEXT            NOT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 0,
    `is_template`   TINYINT(1)      NOT NULL DEFAULT 0,
    `expires_at`    DATETIME        NULL DEFAULT NULL,
    `created_by`    INT             NULL DEFAULT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_announcements_active`   (`is_active`,   `expires_at`),
    KEY `idx_announcements_template` (`is_template`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
