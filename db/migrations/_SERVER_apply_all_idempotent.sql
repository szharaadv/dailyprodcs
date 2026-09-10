-- =====================================================================
-- SERVER MIGRATION (IDEMPOTENT / AMAN DIJALANKAN BERULANG)
-- Menerapkan semua perubahan struktur yang belum ada. Setiap kolom/index
-- dicek dulu sebelum dibuat, jadi TIDAK akan error "Duplicate column" dan
-- TIDAK berhenti di tengah walau sebagian sudah pernah diterapkan.
-- Additive / non-destructive: tidak ada DROP TABLE / DELETE / TRUNCATE.
-- CATATAN: seed_fopump_check_models (local-only) SENGAJA TIDAK disertakan.
-- BACKUP DB DULU sebagai pengaman.
-- =====================================================================

-- ---------- Helper procedures ----------
DELIMITER $$

DROP PROCEDURE IF EXISTS _mig_addcol $$
CREATE PROCEDURE _mig_addcol(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tbl AND COLUMN_NAME=col) = 0 THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$

DROP PROCEDURE IF EXISTS _mig_addkey $$
CREATE PROCEDURE _mig_addkey(IN tbl VARCHAR(64), IN kname VARCHAR(64), IN ddl TEXT)
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tbl AND INDEX_NAME=kname) = 0 THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$

DROP PROCEDURE IF EXISTS _mig_dropkey $$
CREATE PROCEDURE _mig_dropkey(IN tbl VARCHAR(64), IN kname VARCHAR(64))
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tbl AND INDEX_NAME=kname) > 0 THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` DROP INDEX `', kname, '`');
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$

DELIMITER ;

-- =====================================================================
-- 01  assy_engine_revision  (CREATE TABLE IF NOT EXISTS = sudah idempotent)
-- =====================================================================
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

-- =====================================================================
-- 02  assy_signoff
-- =====================================================================
CALL _mig_addcol('t_assy_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL AFTER `checker_id`');
CALL _mig_addcol('t_assy_header','foreman_id',   '`foreman_id` int(11) NULL DEFAULT NULL AFTER `checker_at`');
CALL _mig_addcol('t_assy_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL AFTER `foreman_id`');
CALL _mig_addcol('t_assy_header','supervisor_id','`supervisor_id` int(11) NULL DEFAULT NULL AFTER `foreman_at`');
CALL _mig_addcol('t_assy_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`');
CALL _mig_addkey('t_assy_header','fk_assyheader_foreman',   'KEY `fk_assyheader_foreman` (`foreman_id`)');
CALL _mig_addkey('t_assy_header','fk_assyheader_supervisor','KEY `fk_assyheader_supervisor` (`supervisor_id`)');
UPDATE `t_assy_header` SET `checker_at` = `created_at` WHERE `checker_id` IS NOT NULL AND `checker_at` IS NULL;

-- =====================================================================
-- 03  fill_requests
-- =====================================================================
ALTER TABLE `t_edit_request` MODIFY `header_id` int(11) NULL DEFAULT NULL;
CALL _mig_addcol('t_edit_request','target_date',  '`target_date` date NULL DEFAULT NULL AFTER `header_id`');
CALL _mig_addcol('t_edit_request','department_id','`department_id` int(11) NULL DEFAULT NULL AFTER `target_date`');
CALL _mig_addcol('t_edit_request','condition_id', '`condition_id` int(11) NULL DEFAULT NULL AFTER `department_id`');
CALL _mig_addkey('t_edit_request','idx_fill_lookup',
  'KEY `idx_fill_lookup` (`checksheet_type`, `department_id`, `condition_id`, `target_date`, `status`)');

-- =====================================================================
-- 04  fopump_check_daily
-- =====================================================================
CALL _mig_addcol('t_fopump_check_header','tanggal','`tanggal` date NULL AFTER `model_id`');
UPDATE `t_fopump_check_header` SET `tanggal` = DATE(`created_at`) WHERE `tanggal` IS NULL;
ALTER TABLE `t_fopump_check_header` MODIFY `tanggal` date NOT NULL;
CALL _mig_dropkey('t_fopump_check_header','uq_fopumpcheckheader_model');
CALL _mig_addkey('t_fopump_check_header','uq_fopumpcheckheader_model_date',
  'UNIQUE KEY `uq_fopumpcheckheader_model_date` (`model_id`, `tanggal`)');

-- =====================================================================
-- 05  fopump_check_global_items  (transform data; aman diulang)
-- =====================================================================
ALTER TABLE `m_fopump_check_item` MODIFY `model_id` int(11) NULL DEFAULT NULL;
CALL _mig_addcol('m_fopump_check_item','standard_source',
  "`standard_source` enum('static','part_no','fop_code') NOT NULL DEFAULT 'static' AFTER `standard`");

SET @master := (
  SELECT model_id FROM m_fopump_check_item
  WHERE model_id IS NOT NULL AND is_active = 1
  GROUP BY model_id ORDER BY COUNT(*) DESC, model_id ASC LIMIT 1);

UPDATE t_fopump_check_detail d
JOIN m_fopump_check_item legacy ON legacy.id = d.checklist_item_id AND legacy.model_id <> @master
JOIN m_fopump_check_item master ON master.model_id = @master AND master.sort_order = legacy.sort_order
SET d.checklist_item_id = master.id;

UPDATE m_fopump_check_item SET model_id = NULL WHERE model_id = @master;
UPDATE m_fopump_check_item SET is_active = 0 WHERE model_id IS NOT NULL;
UPDATE m_fopump_check_item SET standard_source = 'part_no', standard = NULL
 WHERE model_id IS NULL AND checking_item = 'Label check - Part no';
UPDATE m_fopump_check_item SET standard_source = 'fop_code', standard = NULL
 WHERE model_id IS NULL AND checking_item = 'Label check - Model code';

-- =====================================================================
-- 06  fopump_check_signoff
-- =====================================================================
ALTER TABLE `t_fopump_check_header` MODIFY `checker_id` int(11) NULL DEFAULT NULL;
CALL _mig_addcol('t_fopump_check_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL AFTER `checker_id`');
CALL _mig_addcol('t_fopump_check_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL AFTER `foreman_id`');
CALL _mig_addcol('t_fopump_check_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`');
CALL _mig_addcol('m_user','email','`email` varchar(150) NULL DEFAULT NULL AFTER `title`');
UPDATE `t_fopump_check_header` SET `checker_at`    = `created_at` WHERE `checker_id`    IS NOT NULL AND `checker_at`    IS NULL;
UPDATE `t_fopump_check_header` SET `foreman_at`    = `created_at` WHERE `foreman_id`    IS NOT NULL AND `foreman_at`    IS NULL;
UPDATE `t_fopump_check_header` SET `supervisor_at` = `created_at` WHERE `supervisor_id` IS NOT NULL AND `supervisor_at` IS NULL;

-- =====================================================================
-- 07  fopump_report_test_signoff
-- =====================================================================
CALL _mig_addcol('t_fopump_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL AFTER `operator_id`');
CALL _mig_addcol('t_fopump_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL AFTER `foreman_id`');
CALL _mig_addcol('t_fopump_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`');
UPDATE `t_fopump_header` SET `checker_at` = `created_at` WHERE `operator_id` IS NOT NULL AND `checker_at` IS NULL;

CALL _mig_addcol('t_fopump_test_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL AFTER `checker_id`');
CALL _mig_addcol('t_fopump_test_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL AFTER `foreman_id`');
CALL _mig_addcol('t_fopump_test_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`');
UPDATE `t_fopump_test_header` SET `checker_at` = `created_at` WHERE `checker_id` IS NOT NULL AND `checker_at` IS NULL;

-- =====================================================================
-- 08  monthly_signoff
-- =====================================================================
CALL _mig_addcol('t_paint_viscosity_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL AFTER `checker_id`');
CALL _mig_addcol('t_paint_viscosity_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL AFTER `foreman_id`');
CALL _mig_addcol('t_paint_viscosity_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`');

CALL _mig_addcol('t_washing_header','checker_id',   '`checker_id` int(11) NULL DEFAULT NULL');
CALL _mig_addcol('t_washing_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL');
CALL _mig_addcol('t_washing_header','foreman_id',   '`foreman_id` int(11) NULL DEFAULT NULL');
CALL _mig_addcol('t_washing_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL');
CALL _mig_addcol('t_washing_header','supervisor_id','`supervisor_id` int(11) NULL DEFAULT NULL');
CALL _mig_addcol('t_washing_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL');

CALL _mig_addcol('t_fopump_reject_header','checker_id',   '`checker_id` int(11) NULL DEFAULT NULL');
CALL _mig_addcol('t_fopump_reject_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL');
CALL _mig_addcol('t_fopump_reject_header','foreman_id',   '`foreman_id` int(11) NULL DEFAULT NULL');
CALL _mig_addcol('t_fopump_reject_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL');
CALL _mig_addcol('t_fopump_reject_header','supervisor_id','`supervisor_id` int(11) NULL DEFAULT NULL');
CALL _mig_addcol('t_fopump_reject_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL');

CALL _mig_addcol('t_3s3t_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL AFTER `operator_id`');
CALL _mig_addcol('t_3s3t_header','foreman_id',   '`foreman_id` int(11) NULL DEFAULT NULL');
CALL _mig_addcol('t_3s3t_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL');
CALL _mig_addcol('t_3s3t_header','supervisor_id','`supervisor_id` int(11) NULL DEFAULT NULL');
CALL _mig_addcol('t_3s3t_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL');

-- =====================================================================
-- 09  painting_signoff
-- =====================================================================
CALL _mig_addcol('t_checksheet_header','checker_at',   '`checker_at` datetime NULL DEFAULT NULL AFTER `checker_id`');
CALL _mig_addcol('t_checksheet_header','foreman_id',   '`foreman_id` int(11) NULL DEFAULT NULL AFTER `checker_at`');
CALL _mig_addcol('t_checksheet_header','foreman_at',   '`foreman_at` datetime NULL DEFAULT NULL AFTER `foreman_id`');
CALL _mig_addcol('t_checksheet_header','supervisor_id','`supervisor_id` int(11) NULL DEFAULT NULL AFTER `foreman_at`');
CALL _mig_addcol('t_checksheet_header','supervisor_at','`supervisor_at` datetime NULL DEFAULT NULL AFTER `supervisor_id`');
CALL _mig_addkey('t_checksheet_header','fk_checksheetheader_foreman',   'KEY `fk_checksheetheader_foreman` (`foreman_id`)');
CALL _mig_addkey('t_checksheet_header','fk_checksheetheader_supervisor','KEY `fk_checksheetheader_supervisor` (`supervisor_id`)');
UPDATE `t_checksheet_header` SET `checker_at` = `created_at` WHERE `checker_id` IS NOT NULL AND `checker_at` IS NULL;

-- =====================================================================
-- 10  section_doc_meta
-- =====================================================================
CALL _mig_addcol('m_checksheet_section','doc_title','`doc_title` varchar(150) NULL DEFAULT NULL');
CALL _mig_addcol('m_checksheet_section','doc_no',   '`doc_no` varchar(60) NULL DEFAULT NULL');
CALL _mig_addcol('m_checksheet_section','doc_rev',  '`doc_rev` varchar(20) NULL DEFAULT NULL');
CALL _mig_addcol('m_checksheet_section','doc_date', '`doc_date` varchar(20) NULL DEFAULT NULL');
UPDATE `m_checksheet_section`
   SET `doc_title` = 'PAINTING MONTHLY CHECK SHEET REPORT',
       `doc_no` = 'QCPC/PTG/MTC-01', `doc_rev` = '00', `doc_date` = '01-01-2026'
 WHERE `route` = 'painting_list.php' AND `department_id` = 1 AND `doc_no` IS NULL;

-- =====================================================================
-- 12  assy_model_configured  (backfill hanya saat kolom pertama dibuat)
-- =====================================================================
SET @assy_had_configured := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='m_assy_model' AND COLUMN_NAME='configured');
CALL _mig_addcol('m_assy_model','configured','`configured` tinyint(1) NOT NULL DEFAULT 0');
SET @s := IF(@assy_had_configured = 0, 'UPDATE `m_assy_model` SET `configured` = 1', 'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- Bersihkan helper ----------
DROP PROCEDURE IF EXISTS _mig_addcol;
DROP PROCEDURE IF EXISTS _mig_addkey;
DROP PROCEDURE IF EXISTS _mig_dropkey;

-- Selesai.
