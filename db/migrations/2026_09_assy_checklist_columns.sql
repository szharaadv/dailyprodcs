-- Torque / Assembling: pastikan kolom m_assy_checklist_item lengkap.
-- Kolom standard_min / standard_max / blocked ada di schema.sql tapi tidak
-- pernah punya file migration, sehingga server lama bisa kekurangan kolom ini
-- (menyebabkan ajax/get_assy_items.php error → checksheet mentok "Loading data").
-- Idempotent & non-destructive: hanya menambah kolom yang belum ada.

DELIMITER $$
DROP PROCEDURE IF EXISTS _mig_addcol2 $$
CREATE PROCEDURE _mig_addcol2(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tbl AND COLUMN_NAME=col) = 0 THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$
DELIMITER ;

CALL _mig_addcol2('m_assy_checklist_item','standard_min','`standard_min` varchar(50) NULL DEFAULT NULL AFTER `standard`');
CALL _mig_addcol2('m_assy_checklist_item','standard_max','`standard_max` varchar(50) NULL DEFAULT NULL AFTER `standard_min`');
CALL _mig_addcol2('m_assy_checklist_item','blocked','`blocked` tinyint(1) NOT NULL DEFAULT 0');

DROP PROCEDURE IF EXISTS _mig_addcol2;
