-- Add a `banned_at` column to `account` for soft-suspending an
-- account without deleting its rows.
--
-- Behaviour (enforced in AuthService::attempt):
--   * banned_at IS NULL    → normal account, can log in
--   * banned_at IS NOT NULL → login refused with the same opaque
--                            error a wrong-password attempt receives,
--                            so a banned attacker can't distinguish
--                            "banned" from "wrong credentials" by
--                            response signature.
--
-- We deliberately do NOT cascade the ban into existing sessions in
-- this branch (that needs a session-version column + revocation
-- check on every request, which is a separate concern). A banned
-- account stays signed in until their cookie expires or they log
-- out manually. The audit-log branch will add server-side session
-- invalidation; for now, ban is enforced at login time only.
--
-- Idempotent: re-running this migration is harmless because the
-- column already exists after the first run (the migrator skips
-- successful migrations via the _migrations tracking table).

ALTER TABLE `account`
    ADD COLUMN `banned_at` DATETIME NULL DEFAULT NULL AFTER `locked_until`;
