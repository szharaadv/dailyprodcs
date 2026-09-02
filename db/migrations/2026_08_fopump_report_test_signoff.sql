-- FO Pump Report & FO Pump Test: add the sign-off timestamps. Both already
-- carry the role id columns (Report uses operator_id as the checker; Test uses
-- checker_id) plus foreman_id/supervisor_id — they were only missing the "when
-- signed" timestamps. Additive & non-destructive; existing submitted records
-- get checker_at = created_at so they don't flood the approval queue.

-- FO Pump Report (daily, per department) — operator_id is the checker.
ALTER TABLE `t_fopump_header`
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL AFTER `operator_id`,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL AFTER `foreman_id`,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`;
UPDATE `t_fopump_header` SET `checker_at` = `created_at` WHERE `operator_id` IS NOT NULL AND `checker_at` IS NULL;

-- FO Pump Test (one ongoing record per model, no date).
ALTER TABLE `t_fopump_test_header`
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL AFTER `checker_id`,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL AFTER `foreman_id`,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`;
UPDATE `t_fopump_test_header` SET `checker_at` = `created_at` WHERE `checker_id` IS NOT NULL AND `checker_at` IS NULL;
