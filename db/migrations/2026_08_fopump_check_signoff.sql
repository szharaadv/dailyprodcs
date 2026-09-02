-- FO Pump Check: role-based sign-off trail (Checker → Foreman → Supervisor).
-- Each role signs its own line when that person logs in; timestamps record when.
-- Foreman/Supervisor may sign even if the operator hasn't (so checker_id may be
-- empty) → checker_id becomes nullable. Additive & non-destructive.

ALTER TABLE `t_fopump_check_header`
  MODIFY `checker_id` int(11) NULL DEFAULT NULL,
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL AFTER `checker_id`,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL AFTER `foreman_id`,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`;

-- Optional email per user, for the "notify the next role if they have an email"
-- notification. NULL = no email (that person is simply notified in-app only).
ALTER TABLE `m_user`
  ADD COLUMN `email` varchar(150) NULL DEFAULT NULL AFTER `title`;

-- Backfill existing records: rows submitted before this feature already carry
-- checker/foreman/supervisor ids (picked from dropdowns) but no timestamps.
-- Treat an existing id as "already signed" (stamped at the record's created_at)
-- so old records don't flood the new "Persetujuan Saya" queue.
UPDATE `t_fopump_check_header` SET `checker_at`    = `created_at` WHERE `checker_id`    IS NOT NULL AND `checker_at`    IS NULL;
UPDATE `t_fopump_check_header` SET `foreman_at`    = `created_at` WHERE `foreman_id`    IS NOT NULL AND `foreman_at`    IS NULL;
UPDATE `t_fopump_check_header` SET `supervisor_at` = `created_at` WHERE `supervisor_id` IS NOT NULL AND `supervisor_at` IS NULL;
