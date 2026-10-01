-- "Keterangan tidak mengisi checksheet" — let a user mark a missing day as
-- excused with a reason (e.g. Preventive Maintenance, Stock Taking) instead of
-- requiring the sheet to be filled. Reasons are admin-managed; the excuse is
-- recorded per checksheet-type + department (+ condition) + date.
--
-- Additive & non-destructive. Safe to re-run.

-- Admin-managed reason list.
CREATE TABLE IF NOT EXISTS `m_offday_reason` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One excuse per checksheet-type + department (+ condition) + date. condition_id
-- is NULL for checksheets without conditions (Torque, FO Pump, Painting Daily
-- Report); set for Painting Checklist (per-condition).
CREATE TABLE IF NOT EXISTS `t_checksheet_offday` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checksheet_type` varchar(30) NOT NULL,
  `department_id` int(11) NOT NULL,
  `condition_id` int(11) NULL DEFAULT NULL,
  `tanggal` date NOT NULL,
  `reason_id` int(11) NOT NULL,
  `note` varchar(255) NULL DEFAULT NULL,
  `created_by` int(11) NULL DEFAULT NULL,
  `created_by_name` varchar(120) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offday` (`checksheet_type`, `department_id`, `condition_id`, `tanggal`),
  KEY `fk_offday_reason` (`reason_id`),
  CONSTRAINT `fk_offday_reason` FOREIGN KEY (`reason_id`) REFERENCES `m_offday_reason` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a few common reasons (only if the table is empty).
INSERT INTO `m_offday_reason` (name, sort_order)
SELECT * FROM (
    SELECT 'Preventive Maintenance' AS name, 1 AS sort_order UNION ALL
    SELECT 'Stock Taking', 2 UNION ALL
    SELECT 'Tidak Ada Produksi', 3 UNION ALL
    SELECT 'Libur', 4 UNION ALL
    SELECT 'Trial / Percobaan', 5
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `m_offday_reason`);
