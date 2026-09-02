-- FO Pump Check: switch from one ongoing record per model to one record per
-- model PER DAY, so the sheet "resets" each day while past days are kept as
-- history. Fill/sign-off always targets today's record; the dashboard can
-- browse other dates. Additive & non-destructive (existing records keep their
-- data, dated by their created_at day).

-- 1. Add the date column, backfill existing rows from their creation day.
ALTER TABLE `t_fopump_check_header` ADD COLUMN `tanggal` date NULL AFTER `model_id`;
UPDATE `t_fopump_check_header` SET `tanggal` = DATE(`created_at`) WHERE `tanggal` IS NULL;

-- 2. Make it required and re-key: unique per (model, day) instead of per model.
--    The new unique key keeps model_id as its leftmost column, so the existing
--    foreign key on model_id still has its required index.
ALTER TABLE `t_fopump_check_header`
  MODIFY `tanggal` date NOT NULL,
  DROP INDEX `uq_fopumpcheckheader_model`,
  ADD UNIQUE KEY `uq_fopumpcheckheader_model_date` (`model_id`, `tanggal`);
