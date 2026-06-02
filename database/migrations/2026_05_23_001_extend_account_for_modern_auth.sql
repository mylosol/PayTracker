-- Modern authentication columns for the `account` table.
--
-- Rationale: the legacy auth model is a single shared password ("monkey")
-- stored in a client cookie. We're moving to per-account auth with
-- bcrypt password hashes, optional lockout for failed attempts, and a
-- simple role marker. This migration only ADDS columns — it does not
-- drop, rename, or back-fill any existing data, so the legacy app
-- continues to work unchanged while the new auth flow is built out.
--
-- Every column is either NULLable or has a safe DEFAULT, so the ALTER
-- can run against a populated table without breaking existing rows.
-- `email` and `password_hash` are nullable on purpose: existing legacy
-- accounts have neither, and the new flow will fill them in over time
-- (admin seeding via scripts/set-password.php; later, a first-login
-- claim flow).

ALTER TABLE `account`
    ADD COLUMN `email`              VARCHAR(255) NULL DEFAULT NULL AFTER `user`,
    ADD COLUMN `password_hash`      VARCHAR(255) NULL DEFAULT NULL AFTER `email`,
    ADD COLUMN `role`               VARCHAR(20)  NOT NULL DEFAULT 'user' AFTER `password_hash`,
    ADD COLUMN `last_login_at`      DATETIME     NULL DEFAULT NULL AFTER `role`,
    ADD COLUMN `failed_login_count` INT          NOT NULL DEFAULT 0   AFTER `last_login_at`,
    ADD COLUMN `locked_until`       DATETIME     NULL DEFAULT NULL AFTER `failed_login_count`;

-- Unique index on email. MySQL treats multiple NULLs as distinct, so
-- existing rows without an email don't collide. New rows that set an
-- email value are guaranteed unique.
ALTER TABLE `account`
    ADD UNIQUE INDEX `idx_account_email` (`email`);

-- Index on `user` for login-by-handle lookups. The table previously had
-- no index on this column despite `findByUser` queries hitting it.
ALTER TABLE `account`
    ADD INDEX `idx_account_user` (`user`)
