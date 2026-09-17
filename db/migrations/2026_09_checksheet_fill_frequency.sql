-- Per-checksheet fill frequency, used by the dashboard to know which sheets are
-- "belum diisi" today vs which are as-needed (no data required that day).
-- Admin can change these in Management → Dashboard Settings. Non-destructive.

ALTER TABLE `m_checksheet_section`
  ADD COLUMN `fill_frequency` ENUM('daily','weekly','monthly','as_needed')
  NOT NULL DEFAULT 'daily' AFTER `route`;

-- Sensible defaults: weekly for the 3S-3T audit; as-needed for FO Pump
-- Check/Test (per-model, only when a unit is processed) and Daily Reject (only
-- when there are rejects). Everything else stays 'daily'.
UPDATE `m_checksheet_section` SET `fill_frequency` = 'weekly'   WHERE `route` = '3s3t_list.php';
UPDATE `m_checksheet_section` SET `fill_frequency` = 'as_needed' WHERE `route` IN
  ('fopump_check_list.php', 'fopump_test_list.php', 'fopump_reject_list.php');
