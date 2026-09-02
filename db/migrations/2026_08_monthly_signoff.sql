-- Monthly grid checksheets: add the Checker → Foreman → Supervisor sign-off
-- (per month). These are department-keyed. Checker "finalizes" the month
-- (checker_at), then Foreman/Supervisor sign from "Persetujuan Saya".
-- Additive & non-destructive; no backfill (past months carry no sign-off).

-- Paint Viscosity (already has the role id columns).
ALTER TABLE `t_paint_viscosity_header`
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL AFTER `checker_id`,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL AFTER `foreman_id`,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`;

-- Washing (no role columns yet).
ALTER TABLE `t_washing_header`
  ADD COLUMN `checker_id`    int(11)  NULL DEFAULT NULL,
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL,
  ADD COLUMN `foreman_id`    int(11)  NULL DEFAULT NULL,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL,
  ADD COLUMN `supervisor_id` int(11)  NULL DEFAULT NULL,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL;

-- FO Pump Daily Reject (no role columns yet).
ALTER TABLE `t_fopump_reject_header`
  ADD COLUMN `checker_id`    int(11)  NULL DEFAULT NULL,
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL,
  ADD COLUMN `foreman_id`    int(11)  NULL DEFAULT NULL,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL,
  ADD COLUMN `supervisor_id` int(11)  NULL DEFAULT NULL,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL;

-- 3S-3T (operator_id is the checker).
ALTER TABLE `t_3s3t_header`
  ADD COLUMN `checker_at`    datetime NULL DEFAULT NULL AFTER `operator_id`,
  ADD COLUMN `foreman_id`    int(11)  NULL DEFAULT NULL,
  ADD COLUMN `foreman_at`    datetime NULL DEFAULT NULL,
  ADD COLUMN `supervisor_id` int(11)  NULL DEFAULT NULL,
  ADD COLUMN `supervisor_at` datetime NULL DEFAULT NULL;
