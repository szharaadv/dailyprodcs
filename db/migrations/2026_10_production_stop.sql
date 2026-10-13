-- Production Stop calendar: Admin marks a date range (plant-wide) where
-- production is stopped (Preventive Maintenance, Stock Taking, …) so no
-- checksheet is expected. Those days are removed from the "working days" used by
-- every checksheet's missing-check banner (see includes/calendar_lib.php).
--
-- Additive & non-destructive. Safe to re-run.

-- Admin-managed reason list.
CREATE TABLE IF NOT EXISTS `m_prodstop_reason` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Plant-wide production-stop periods (inclusive date range).
CREATE TABLE IF NOT EXISTS `t_prodstop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason_id` int(11) NOT NULL,
  `note` varchar(255) NULL DEFAULT NULL,
  `created_by` int(11) NULL DEFAULT NULL,
  `created_by_name` varchar(120) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prodstop_range` (`start_date`, `end_date`),
  KEY `fk_prodstop_reason` (`reason_id`),
  CONSTRAINT `fk_prodstop_reason` FOREIGN KEY (`reason_id`) REFERENCES `m_prodstop_reason` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed common reasons (only if empty).
INSERT INTO `m_prodstop_reason` (name, sort_order)
SELECT * FROM (
    SELECT 'Preventive Maintenance' AS name, 1 AS sort_order UNION ALL
    SELECT 'Stock Taking', 2 UNION ALL
    SELECT 'Tidak Ada Produksi', 3 UNION ALL
    SELECT 'Trial / Percobaan', 4
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `m_prodstop_reason`);
