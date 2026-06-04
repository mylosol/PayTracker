-- `invite_codes` — Admin-issued single-use registration tokens.
--
-- Each row is an entitlement: someone holding the `code` value can
-- create exactly ONE account via /register. Codes are 8-character
-- alphanumeric (uppercase letters + digits, ~2.8 trillion space)
-- so they're easy to read in an email AND brute-force-resistant
-- without additional rate limiting required for v1.
--
-- Schema rationale:
--   * `code` is the lookup key on the public registration path.
--     UNIQUE indexed so the registration query is sub-millisecond
--     regardless of table size.
--   * `expires_at` NULL means "never expires; lives until admin
--     deletes it or it's consumed". Non-null sets a soft sunset --
--     the read query treats `expires_at IS NOT NULL AND expires_at
--     <= UTC_TIMESTAMP()` as expired and refuses consumption.
--   * `used_at` / `used_by_id` are set atomically alongside the
--     account INSERT in a single transaction. A row with used_at
--     set can never be redeemed again.
--   * `auto_delete` toggles the post-consume behaviour:
--       - 1 (default): the consume path runs DELETE after the
--         transaction commits, leaving zero trace beyond the audit
--         log.
--       - 0: the row stays in place with used_at populated so the
--         admin can see "who used this code" via the list view.
--   * `invitee_email` is what Resend sends to. Admin can leave it
--     blank and just copy the URL out of the flash banner. When
--     populated AND Resend is configured, the create path fires
--     an email automatically.
--   * `created_by` is a soft FK to account.id for attribution.
--
-- Indexes: (code) UNIQUE for the consume lookup, (used_at,
-- expires_at) for the "list active" admin query.

CREATE TABLE IF NOT EXISTS `invite_codes` (
    `id`              INT          NOT NULL AUTO_INCREMENT,
    `code`            CHAR(8)      NOT NULL,
    `created_by`      INT          NULL DEFAULT NULL,
    `invitee_email`   VARCHAR(255) NULL DEFAULT NULL,
    `expires_at`      DATETIME     NULL DEFAULT NULL,
    `used_at`         DATETIME     NULL DEFAULT NULL,
    `used_by_id`      INT          NULL DEFAULT NULL,
    `auto_delete`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_invite_codes_code` (`code`),
    KEY `idx_invite_codes_state` (`used_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
