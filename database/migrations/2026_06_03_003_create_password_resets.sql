-- `password_resets` — short-lived single-use tokens for the admin-
-- initiated password reset flow.
--
-- Schema rationale:
--   * `token_hash` — we store the SHA-256 hash of the token, never
--     the raw token. The raw token is shown ONCE to the admin who
--     generated it (and, when branch 4 lands, emailed to the user
--     via Resend). A DB leak therefore can't be replayed into an
--     account takeover.
--   * `expires_at` — tokens expire one hour after creation. The
--     consume path checks `expires_at > UTC_TIMESTAMP() AND used_at
--     IS NULL` atomically.
--   * `used_at` — set when the token is redeemed. We keep the row
--     rather than DELETE so the audit log (branch 3) has a permanent
--     record of "reset N was used at time T".
--   * `created_by_id` — the admin account that minted the token,
--     so audit queries can answer "who reset this user's password".
--
-- One-to-many on user_id: an admin can mint a fresh token for a
-- user even if a previous one is outstanding. The consume path
-- only honours the SPECIFIC token presented, so an older mint
-- doesn't become a back door once a new one exists. The PHP layer
-- additionally invalidates prior outstanding tokens for the same
-- user when a new one is minted (defence in depth).

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `user_id`        INT          NOT NULL,
    `token_hash`     CHAR(64)     NOT NULL,
    `expires_at`     DATETIME     NOT NULL,
    `used_at`        DATETIME     NULL DEFAULT NULL,
    `created_by_id`  INT          NULL DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_password_resets_token_hash` (`token_hash`),
    KEY `idx_password_resets_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
