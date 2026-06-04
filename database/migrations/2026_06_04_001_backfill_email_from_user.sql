-- Backfill `email` from `user` for legacy accounts.
--
-- Background: the legacy registration form treated the email address
-- AS the login handle and stored it in the `user` column. The newer
-- `email` column was added in 2026_05_23_001 for the modern flow but
-- nothing has ever populated it for the legacy rows. Result: the
-- admin panel's Email column is empty for everyone, AND the password-
-- reset flow (which sends to `email`) can't reach anyone until
-- they manually visit /profile and fill it in.
--
-- This migration is a one-shot copy: when `email IS NULL` AND `user`
-- matches a basic email shape, set `email = user`. Conservative on
-- two fronts:
--
--   1. We do NOT touch rows where `email` is already set -- a real
--      user who visited /profile after the unique-index migration
--      doesn't get clobbered.
--   2. We do NOT touch rows where `user` doesn't look like an email
--      (legacy handles that were just usernames, junk-data rows the
--      spam-cleanup will get to, etc.).
--
-- After this runs, the Profile page can let users edit the two
-- fields independently and admin actions can rely on `email` being
-- the canonical address for outbound mail.
--
-- Idempotency: re-running is a no-op because the WHERE filter
-- excludes rows we already populated.

UPDATE `account`
   SET `email` = `user`
 WHERE `email` IS NULL
   AND `user` REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'
