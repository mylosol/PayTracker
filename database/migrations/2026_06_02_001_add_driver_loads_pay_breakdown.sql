-- Per-load pay breakdown — the structured form of np that the
-- dashboard renders as the legacy "135 Miles Base / Shift Pay /
-- Seniority Pay" card.
--
-- Stored as JSON instead of a wide set of typed columns because:
--   1. The component list is expected to grow (slip pay, out-of-route
--      pay, future bonuses) and ALTERing for each is noisy.
--   2. The values are write-once-read-as-a-blob — we never JOIN or
--      aggregate on a single component; the np / op totals already
--      live in typed columns for that.
--
-- NULL means "not computed yet" (older rows backfilled before this
-- migration shipped). The dashboard falls back to "no breakdown
-- available — run /pay-admin/recompute" for those rows.

ALTER TABLE `driver_loads`
    ADD COLUMN `pay_breakdown` JSON NULL DEFAULT NULL AFTER `op`
