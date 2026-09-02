-- Torque (Assembly): add the Checker → Foreman → Supervisor sign-off trail,
-- same shape as FO Pump Check. checker_id already exists; add its timestamp and
-- the foreman/supervisor columns. Additive & non-destructive. Existing
-- submitted records get checker_at = created_at (treated as already signed by
-- the checker) so they don't flood the approval queue.

ALTER TABLE `t_assy_header`
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL AFTER `checker_id`,
  ADD COLUMN `foreman_id`    int(11)  NULL DEFAULT NULL AFTER `checker_at`,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL AFTER `foreman_id`,
  ADD COLUMN `supervisor_id` int(11)  NULL DEFAULT NULL AFTER `foreman_at`,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`,
  ADD KEY `fk_assyheader_foreman` (`foreman_id`),
  ADD KEY `fk_assyheader_supervisor` (`supervisor_id`);

UPDATE `t_assy_header` SET `checker_at` = `created_at` WHERE `checker_id` IS NOT NULL AND `checker_at` IS NULL;
