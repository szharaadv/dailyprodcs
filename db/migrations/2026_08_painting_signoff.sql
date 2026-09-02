-- Painting: add the Checker → Foreman → Supervisor sign-off trail (same as
-- Torque / FO Pump Check). checker_id already exists; add its timestamp + the
-- foreman/supervisor columns. Additive & non-destructive; existing submitted
-- records get checker_at = created_at so they don't flood the approval queue.

ALTER TABLE `t_checksheet_header`
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL AFTER `checker_id`,
  ADD COLUMN `foreman_id`    int(11)  NULL DEFAULT NULL AFTER `checker_at`,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL AFTER `foreman_id`,
  ADD COLUMN `supervisor_id` int(11)  NULL DEFAULT NULL AFTER `foreman_at`,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`,
  ADD KEY `fk_checksheetheader_foreman` (`foreman_id`),
  ADD KEY `fk_checksheetheader_supervisor` (`supervisor_id`);

UPDATE `t_checksheet_header` SET `checker_at` = `created_at` WHERE `checker_id` IS NOT NULL AND `checker_at` IS NULL;
