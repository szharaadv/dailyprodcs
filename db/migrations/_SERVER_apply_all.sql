-- =====================================================================
-- GABUNGAN MIGRATION UNTUK SERVER  (generated 2026-09-07)
-- Jalankan SEKALI pada database yang SEMUA migration-nya masih BELUM.
-- Additive / non-destructive: tidak ada DROP TABLE / DELETE / TRUNCATE.
-- CATATAN: seed_fopump_check_models (local-only) SENGAJA TIDAK disertakan.
-- BACKUP DB DULU sebelum import.
-- =====================================================================


-- ==================================================================
-- >>> 2026_08_assy_engine_revision.sql
-- ==================================================================
-- ------------------------------------------------------------
-- Assembling / Torque: Engine Revision log.
-- When an engine (t_assy_header) has one or more checking-item readings that
-- fall out of standard (NG), it is reworked and re-measured. Each corrected
-- reading is recorded here as an old->new pair so there is an audit trail of
-- the revision, while the checksheet detail itself is updated to the new value.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `t_assy_revision` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `checklist_item_id` int(11) NOT NULL,
  `old_value` varchar(100) NULL DEFAULT NULL,
  `new_value` varchar(100) NULL DEFAULT NULL,
  `note` varchar(255) NULL DEFAULT NULL,
  `revised_by` int(11) NULL DEFAULT NULL,
  `revised_by_name` varchar(150) NULL DEFAULT NULL,
  `revised_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_assyrev_header` (`header_id`),
  KEY `fk_assyrev_item` (`checklist_item_id`),
  KEY `fk_assyrev_user` (`revised_by`),
  CONSTRAINT `fk_assyrev_header` FOREIGN KEY (`header_id`) REFERENCES `t_assy_header` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assyrev_item` FOREIGN KEY (`checklist_item_id`) REFERENCES `m_assy_checklist_item` (`id`),
  CONSTRAINT `fk_assyrev_user` FOREIGN KEY (`revised_by`) REFERENCES `m_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==================================================================
-- >>> 2026_08_assy_signoff.sql
-- ==================================================================
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


-- ==================================================================
-- >>> 2026_08_fill_requests.sql
-- ==================================================================
-- Missed-date fill requests.
-- An edit-request row can now also represent a request to FILL a day that was
-- never submitted (no existing header_id): header_id becomes NULL and the
-- target day is stored in target_date, scoped by department (+ condition for
-- checksheets that have conditions, e.g. Painting). Once an Admin approves it,
-- has_active_fill_unlock() (includes/edit_requests.php) lets the requester
-- create a record for that specific past date, the same way the one-day
-- "yesterday" grace window works — but for any approved missed day.

ALTER TABLE `t_edit_request`
  MODIFY `header_id` int(11) NULL DEFAULT NULL,
  ADD COLUMN `target_date` date NULL DEFAULT NULL AFTER `header_id`,
  ADD COLUMN `department_id` int(11) NULL DEFAULT NULL AFTER `target_date`,
  ADD COLUMN `condition_id` int(11) NULL DEFAULT NULL AFTER `department_id`,
  ADD KEY `idx_fill_lookup` (`checksheet_type`, `department_id`, `condition_id`, `target_date`, `status`);


-- ==================================================================
-- >>> 2026_08_fopump_check_daily.sql
-- ==================================================================
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


-- ==================================================================
-- >>> 2026_08_fopump_check_global_items.sql
-- ==================================================================
-- FO Pump Check: make checking items GLOBAL (one master list for every model).
-- SAFE / NON-DESTRUCTIVE version: no DELETE, foreign-key checks stay ON. Old
-- per-model items are only DEACTIVATED (kept in the table so history/FK never
-- breaks); submitted detail rows are remapped onto the master by sort_order.
--
-- Run once on a database that does NOT yet have the global items (e.g. server).
-- Back up first as a precaution.

-- 1. Schema: model_id becomes nullable (NULL = global), add standard_source.
ALTER TABLE `m_fopump_check_item`
  MODIFY `model_id` int(11) NULL DEFAULT NULL,
  ADD COLUMN `standard_source` enum('static','part_no','fop_code') NOT NULL DEFAULT 'static' AFTER `standard`;

-- 2. Master = the model that currently has the most active checking items
--    (the most complete set); ties broken by lowest id.
SET @master := (
  SELECT model_id FROM m_fopump_check_item
  WHERE model_id IS NOT NULL AND is_active = 1
  GROUP BY model_id
  ORDER BY COUNT(*) DESC, model_id ASC
  LIMIT 1
);

-- 3. Remap submitted detail rows from every other model's item copies onto the
--    master's items, matched by sort_order. (No rows are deleted.)
UPDATE t_fopump_check_detail d
JOIN m_fopump_check_item legacy ON legacy.id = d.checklist_item_id AND legacy.model_id <> @master
JOIN m_fopump_check_item master ON master.model_id = @master AND master.sort_order = legacy.sort_order
SET d.checklist_item_id = master.id;

-- 4. Promote the master set to global.
UPDATE m_fopump_check_item SET model_id = NULL WHERE model_id = @master;

-- 5. Deactivate the remaining per-model copies — NOT deleted, so any old
--    reference stays valid and nothing is lost.
UPDATE m_fopump_check_item SET is_active = 0 WHERE model_id IS NOT NULL;

-- 6. Mark the dynamic label rows (their Standard now comes from the model).
UPDATE m_fopump_check_item
   SET standard_source = 'part_no', standard = NULL
 WHERE model_id IS NULL AND checking_item = 'Label check - Part no';
UPDATE m_fopump_check_item
   SET standard_source = 'fop_code', standard = NULL
 WHERE model_id IS NULL AND checking_item = 'Label check - Model code';


-- ==================================================================
-- >>> 2026_08_fopump_check_signoff.sql
-- ==================================================================
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


-- ==================================================================
-- >>> 2026_08_fopump_report_test_signoff.sql
-- ==================================================================
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


-- ==================================================================
-- >>> 2026_08_monthly_signoff.sql
-- ==================================================================
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


-- ==================================================================
-- >>> 2026_08_painting_signoff.sql
-- ==================================================================
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


-- ==================================================================
-- >>> 2026_08_section_doc_meta.sql
-- ==================================================================
-- Excel export: per-section document metadata shown in the report header box
-- (No. Doc / Revisi / Tgl). Editable by Admin (see admin/section_docs.php).
-- The export title is derived from the section name; these are the doc box.

ALTER TABLE `m_checksheet_section`
  ADD COLUMN `doc_title` varchar(150) NULL DEFAULT NULL,
  ADD COLUMN `doc_no`    varchar(60)  NULL DEFAULT NULL,
  ADD COLUMN `doc_rev`   varchar(20)  NULL DEFAULT NULL,
  ADD COLUMN `doc_date`  varchar(20)  NULL DEFAULT NULL;

-- Seed the one we know from the sample (Painting). doc_title is the exact
-- report heading; when empty the export falls back to "<NAME> MONTHLY CHECK
-- SHEET REPORT".
UPDATE `m_checksheet_section`
   SET `doc_title` = 'PAINTING MONTHLY CHECK SHEET REPORT',
       `doc_no` = 'QCPC/PTG/MTC-01', `doc_rev` = '00', `doc_date` = '01-01-2026'
 WHERE `route` = 'painting_list.php' AND `department_id` = 1;


-- ==================================================================
-- >>> 2026_09_assy_model_configured.sql
-- ==================================================================
-- ------------------------------------------------------------
-- Assembling / Torque: mark whether a Model's checking items have been
-- reviewed/adjusted to its own standard. A newly added model is auto-seeded
-- with a template's checking items (see admin/assy_models.php) and starts as
-- configured=0 ("Baru · belum diset"); it flips to 1 once its checking items
-- are edited, or when marked done manually.
-- ------------------------------------------------------------
ALTER TABLE `m_assy_model`
    ADD COLUMN `configured` tinyint(1) NOT NULL DEFAULT 0;

-- All models that already exist are considered configured.
UPDATE `m_assy_model` SET `configured` = 1;

