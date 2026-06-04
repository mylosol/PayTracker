-- Bootstrap the Super Admin role.
--
-- The `role` column already exists on `account` (added by
-- 2026_05_23_001_extend_account_for_modern_auth.sql as VARCHAR(20)
-- DEFAULT 'user'). This migration:
--
--   1. Normalises any pre-existing role values that don't match the
--      RBAC enum {'user','admin','super_admin'}. Defensive — the
--      column was free-form so a stray manual edit could have left
--      something else there.
--
--   2. Promotes the bootstrap admin account (mylosol@gmail.com) to
--      'super_admin'. Idempotent — running this twice is a no-op.
--
-- Why mylosol@gmail.com specifically:
--   That's the QA / dogfood account the project owner signs in as on
--   both preview and production. It's the only account guaranteed to
--   exist with an `email` value across both channels, so a single
--   migration row works in both environments without per-channel
--   branching.
--
-- We do NOT add a CHECK constraint here — the PHP layer (Account
-- model's ROLES constant) is the authoritative whitelist. A DB
-- constraint would make future role additions a two-step deploy
-- (migration + code) instead of one.

-- Step 1: normalise stragglers to 'user' so the app never sees an
-- unknown role string. Anything not in the enum was either a typo
-- or a placeholder; we default it to the least-privileged bucket.
UPDATE `account`
   SET `role` = 'user'
 WHERE `role` NOT IN ('user', 'admin', 'super_admin');

-- Step 2: promote the bootstrap admin. The email match is
-- case-insensitive (MySQL's default collation on the unique index
-- folds case) so a stray 'Mylosol@Gmail.COM' still matches.
UPDATE `account`
   SET `role` = 'super_admin'
 WHERE `email` = 'mylosol@gmail.com';
