-- Painting: Daily Production Report (F-PNT-PROD) — a production tally modelled
-- on the FO Pump Daily Report (F-FIP-03): one row per model with the five
-- painting quantity columns (CB / FOT / FW / PART / OTHERS) + a remark, a Total
-- row per column, and an Acumulation row that carries the month-to-date running
-- total per column (resets every month), exactly like FO Pump.
--
-- Additive & non-destructive. Safe to re-run.

-- ------------------------------------------------------------
-- Header: one per department per day (same lock/sign-off shape as FO Pump).
-- The paper form's header fields are Hari / Tanggal / Pekerja / Shift; "Hari"
-- is derived from the date. Pekerja is a person (checker_id -> m_user, the
-- Checked By) and Shift is a master row (shift_id -> m_shift). checker_at /
-- foreman_* / supervisor_* feed the shared Checker -> Foreman -> Supervisor
-- sign-off queue (includes/signoff.php).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `t_painting_prod_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `checker_id` int(11) NULL DEFAULT NULL,
  `checker_at` datetime NULL DEFAULT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `foreman_at` datetime NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `supervisor_at` datetime NULL DEFAULT NULL,
  `shift_id` int(11) NULL DEFAULT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pprodheader_date` (`department_id`, `tanggal`),
  KEY `fk_pprodheader_checker` (`checker_id`),
  KEY `fk_pprodheader_foreman` (`foreman_id`),
  KEY `fk_pprodheader_supervisor` (`supervisor_id`),
  KEY `fk_pprodheader_shift` (`shift_id`),
  CONSTRAINT `fk_pprodheader_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`),
  CONSTRAINT `fk_pprodheader_checker` FOREIGN KEY (`checker_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_pprodheader_foreman` FOREIGN KEY (`foreman_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_pprodheader_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_pprodheader_shift` FOREIGN KEY (`shift_id`) REFERENCES `m_shift` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Line: one model per row, five painting quantity buckets + a remark.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `t_painting_prod_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `line_no` tinyint(2) NOT NULL,
  `model` varchar(100) NULL DEFAULT NULL,
  `cb` int(11) NULL DEFAULT NULL,
  `fot` int(11) NULL DEFAULT NULL,
  `fw` int(11) NULL DEFAULT NULL,
  `part` int(11) NULL DEFAULT NULL,
  `others` int(11) NULL DEFAULT NULL,
  `keterangan` varchar(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pprodline_header` (`header_id`),
  CONSTRAINT `fk_pprodline_header` FOREIGN KEY (`header_id`) REFERENCES `t_painting_prod_header` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Register the new section under the Painting department (natural keys — ids
-- diverge between the local and server copies of the DB). Guarded so re-runs
-- don't duplicate the row.
-- ------------------------------------------------------------
INSERT INTO `m_checksheet_section` (department_id, name, route, section_type, sort_order)
SELECT d.id, 'Painting Daily Report', 'painting_prod_list.php', 'painting_prod', 2
FROM `m_department` d
WHERE d.name = 'Painting'
  AND NOT EXISTS (SELECT 1 FROM `m_checksheet_section` s WHERE s.route = 'painting_prod_list.php');
