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
