-- `announcement_dismissals` — per-user acknowledgement record.
--
-- One row per (announcement_id, account_id). Captures BOTH:
--   * "who has seen this announcement at least once" — for the
--     /admin/announcements/{id} seen-by report; AND
--   * "who has chosen NOT to see this announcement again" — the
--     `suppressed` flag.
--
-- Lifecycle:
--   1. User logs in. Session flag `announcement_pending=true` is
--      set. The next page render checks for an active announcement
--      AND a row in this table with suppressed=1; the modal shows
--      only if active exists AND no suppressed row exists for this
--      user.
--   2. User clicks "Okay" on the modal. The dismiss endpoint
--      INSERTs (or UPDATEs) a row with the checkbox value as
--      `suppressed`. The session flag is also cleared so the
--      modal disappears for the rest of the session.
--   3. On NEXT login the session flag is set again. If the user
--      ticked "don't show again", the suppressed row blocks the
--      modal forever. If they didn't, they see it again.
--
-- UNIQUE on (announcement_id, account_id) makes the
-- "INSERT ... ON DUPLICATE KEY UPDATE" pattern correct without
-- a race. No FK enforcement (account is MyISAM); model-layer
-- checks fill the gap.

CREATE TABLE IF NOT EXISTS `announcement_dismissals` (
    `id`                INT         NOT NULL AUTO_INCREMENT,
    `announcement_id`   INT         NOT NULL,
    `account_id`        INT         NOT NULL,
    `dismissed_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `suppressed`        TINYINT(1)  NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_dismissals_ann_acct` (`announcement_id`, `account_id`),
    KEY `idx_dismissals_account`         (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
