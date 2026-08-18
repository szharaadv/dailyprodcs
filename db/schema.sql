-- ============================================================
-- dailyprod — Daily Production Check Sheet (Painting & Assembling)
-- Fresh schema, modeled after the checksheet-prod app.
-- ============================================================

CREATE DATABASE IF NOT EXISTS dailyprod CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE dailyprod;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Master Engine — global list of engine model codes (DOM/EXP), shared as
-- the searchable source for every "Model" field across the app (FO Pump
-- production/reject reports use it directly as free-text autocomplete;
-- per-checksheet model masters like Torque/FO Pump Check/Test Record stay
-- separate since they carry their own extra spec fields, e.g. fop_code).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_engine`;
CREATE TABLE `m_engine` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_type` enum('DOM','EXP') NOT NULL DEFAULT 'DOM',
  `model` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_engine_model` (`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Users (simple name-picker login, no passwords)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_user`;
CREATE TABLE `m_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `role` enum('superadmin','admin','user') NOT NULL DEFAULT 'user',
  `title` varchar(50) NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which check-sheet sections a user shows up in as "Checked by".
-- No rows for a user = visible on every section (the default).
DROP TABLE IF EXISTS `m_user_section`;
CREATE TABLE `m_user_section` (
  `user_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`, `section_id`),
  CONSTRAINT `fk_usersection_user` FOREIGN KEY (`user_id`) REFERENCES `m_user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usersection_section` FOREIGN KEY (`section_id`) REFERENCES `m_checksheet_section` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Department: Painting (checklist) / Assembling (assembly)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_department`;
CREATE TABLE `m_department` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `form_type` enum('checklist','assembly') NOT NULL DEFAULT 'checklist',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- group_label: sections sharing the same label collapse into a single card
-- on select_section.php that opens select_group.php as a sub-picker (e.g.
-- FO Pump's several check sheets), instead of each one being its own loose
-- top-level card. NULL = shown directly, ungrouped (e.g. Torque).
DROP TABLE IF EXISTS `m_checksheet_section`;
CREATE TABLE `m_checksheet_section` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `group_label` varchar(100) NULL DEFAULT NULL,
  `route` varchar(100) NOT NULL,
  `section_type` varchar(30) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_section_department` (`department_id`),
  CONSTRAINT `fk_section_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_checker`;
CREATE TABLE `m_checker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) NULL DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `section_id` int(11) NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_checker_department` (`department_id`),
  KEY `fk_checker_section` (`section_id`),
  CONSTRAINT `fk_checker_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`),
  CONSTRAINT `fk_checker_section` FOREIGN KEY (`section_id`) REFERENCES `m_checksheet_section` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- App-wide key/value settings. `delete_pin` gates deleting a submitted
-- checksheet from any "View Checksheets" list (see ajax/delete_checksheet.php).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_setting`;
CREATE TABLE `m_setting` (
  `setting_key` varchar(50) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_shift`;
CREATE TABLE `m_shift` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Company calendar (YADIN Working Calendar) — feeds every checksheet's Date
-- picker (assets/js/holiday-calendar.js via ajax/get_holidays.php). A date
-- with is_workday=0 is a holiday/collective-leave and disabled in the
-- picker; is_workday=1 marks a normally-off day (a Saturday) converted into
-- a mandatory working day to compensate for a holiday moved elsewhere.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_holiday`;
CREATE TABLE `m_holiday` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `label` varchar(150) NOT NULL,
  `is_workday` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_holiday_date` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Painting: Condition -> Checklist Item -> Header/Detail
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_condition`;
CREATE TABLE `m_condition` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_condition_department` (`department_id`),
  CONSTRAINT `fk_condition_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_checklist_item`;
CREATE TABLE `m_checklist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `condition_id` int(11) NOT NULL,
  `checking_item` varchar(255) NOT NULL,
  `metode_pengecekan` varchar(100) NOT NULL DEFAULT 'Visual',
  `standard_min` varchar(50) NULL DEFAULT NULL,
  `standard_max` varchar(50) NULL DEFAULT NULL,
  `tank_tube` varchar(50) NULL DEFAULT '-',
  `satuan` varchar(50) NULL DEFAULT '-',
  `actual_input_type` enum('number','text','select') NOT NULL DEFAULT 'number',
  `actual_options` varchar(255) NULL DEFAULT NULL,
  `category_options` varchar(255) NULL DEFAULT 'OK,NG',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_item_condition` (`condition_id`),
  CONSTRAINT `fk_item_condition` FOREIGN KEY (`condition_id`) REFERENCES `m_condition` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_checksheet_header`;
CREATE TABLE `t_checksheet_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `condition_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `checker_id` int(11) NOT NULL,
  `jam` time NOT NULL,
  `shift_id` int(11) NOT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_header_checker` (`checker_id`),
  KEY `fk_header_condition` (`condition_id`),
  KEY `fk_header_department` (`department_id`),
  KEY `fk_header_shift` (`shift_id`),
  CONSTRAINT `fk_header_checker` FOREIGN KEY (`checker_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_header_condition` FOREIGN KEY (`condition_id`) REFERENCES `m_condition` (`id`),
  CONSTRAINT `fk_header_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`),
  CONSTRAINT `fk_header_shift` FOREIGN KEY (`shift_id`) REFERENCES `m_shift` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_checksheet_detail`;
CREATE TABLE `t_checksheet_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `checklist_item_id` int(11) NOT NULL,
  `actual_result` varchar(100) NULL DEFAULT NULL,
  `category` varchar(50) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_detail_header` (`header_id`),
  KEY `fk_detail_item` (`checklist_item_id`),
  CONSTRAINT `fk_detail_header` FOREIGN KEY (`header_id`) REFERENCES `t_checksheet_header` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_item` FOREIGN KEY (`checklist_item_id`) REFERENCES `m_checklist_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Assembling: Model -> Checklist Item -> Header/Detail
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_assy_model`;
CREATE TABLE `m_assy_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_assymodel_department` (`department_id`),
  CONSTRAINT `fk_assymodel_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_assy_checklist_item`;
CREATE TABLE `m_assy_checklist_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `checking_item` varchar(255) NOT NULL,
  `standard` varchar(255) NULL DEFAULT NULL,
  `standard_min` varchar(50) NULL DEFAULT NULL,
  `standard_max` varchar(50) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_assyitem_model` (`model_id`),
  CONSTRAINT `fk_assyitem_model` FOREIGN KEY (`model_id`) REFERENCES `m_assy_model` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_assy_header`;
CREATE TABLE `t_assy_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `department_id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `mark_crank_shaft` varchar(100) NULL DEFAULT NULL,
  `mark_conrod` varchar(100) NULL DEFAULT NULL,
  `mark_fo_pump` varchar(100) NULL DEFAULT NULL,
  `no_cyl_block` varchar(100) NULL DEFAULT NULL,
  `no_engine` varchar(100) NULL DEFAULT NULL,
  `detail_model` varchar(150) NULL DEFAULT NULL,
  `checker_id` int(11) NOT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_assyheader_checker` (`checker_id`),
  KEY `fk_assyheader_department` (`department_id`),
  KEY `fk_assyheader_model` (`model_id`),
  CONSTRAINT `fk_assyheader_checker` FOREIGN KEY (`checker_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_assyheader_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`),
  CONSTRAINT `fk_assyheader_model` FOREIGN KEY (`model_id`) REFERENCES `m_assy_model` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_assy_detail`;
CREATE TABLE `t_assy_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `checklist_item_id` int(11) NOT NULL,
  `actual_result` varchar(100) NULL DEFAULT NULL,
  `consumable_item` varchar(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_assydetail_header` (`header_id`),
  KEY `fk_assydetail_item` (`checklist_item_id`),
  CONSTRAINT `fk_assydetail_header` FOREIGN KEY (`header_id`) REFERENCES `t_assy_header` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assydetail_item` FOREIGN KEY (`checklist_item_id`) REFERENCES `m_assy_checklist_item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Assembling: Sub Assembly (Jig monthly OK/NG check sheet)
-- Jig -> Jig Item -> Header (one per Jig+Month+Year) -> Detail (one per Item+Day)
--
-- NOTE: the item/header tables are named `m_jigitem`/`t_jigheader` (no
-- underscore before "item"/"header"), not `m_jig_item`/`t_jig_header` as
-- you'd expect from the naming convention elsewhere. This app's MariaDB
-- instance developed a corrupted InnoDB dictionary entry for those exact
-- names (INNODB_SYS_TABLES kept a phantom record even after DROP TABLE,
-- blocking every attempt to recreate them — same root cause that has hit
-- other tables in this install before, see mysql_error.log history). The
-- data in the original tables was unrecoverable; these were recreated
-- under new names as the practical fix. Do not rename back without first
-- confirming `dailyprod/m_jig_item` and `dailyprod/t_jig_header` are truly
-- clear of ghost registrations.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_jig`;
CREATE TABLE `m_jig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `part_name` varchar(255) NULL DEFAULT NULL,
  `checking_method` varchar(100) NULL DEFAULT NULL,
  `frequency` varchar(100) NULL DEFAULT NULL,
  `pic` varchar(150) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_jig_department` (`department_id`),
  CONSTRAINT `fk_jig_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_jigitem`;
CREATE TABLE `m_jigitem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jig_id` int(11) NOT NULL,
  `checking_item` varchar(255) NOT NULL,
  `photo` varchar(255) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_jigitem2_jig` (`jig_id`),
  CONSTRAINT `fk_jigitem2_jig` FOREIGN KEY (`jig_id`) REFERENCES `m_jig` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_jigheader`;
CREATE TABLE `t_jigheader` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jig_id` int(11) NOT NULL,
  `month` tinyint(2) NOT NULL,
  `year` smallint(6) NOT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `checker_id` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jigheader2_period` (`jig_id`, `month`, `year`),
  KEY `fk_jigheader2_supervisor` (`supervisor_id`),
  KEY `fk_jigheader2_foreman` (`foreman_id`),
  KEY `fk_jigheader2_checker` (`checker_id`),
  CONSTRAINT `fk_jigheader2_jig` FOREIGN KEY (`jig_id`) REFERENCES `m_jig` (`id`),
  CONSTRAINT `fk_jigheader2_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_jigheader2_foreman` FOREIGN KEY (`foreman_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_jigheader2_checker` FOREIGN KEY (`checker_id`) REFERENCES `m_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_jig_detail`;
CREATE TABLE `t_jig_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `jig_item_id` int(11) NOT NULL,
  `day` tinyint(2) NOT NULL,
  `result` enum('OK','NG') NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jigdetail_cell` (`header_id`, `jig_item_id`, `day`),
  KEY `fk_jigdetail_item` (`jig_item_id`),
  CONSTRAINT `fk_jigdetail2_header` FOREIGN KEY (`header_id`) REFERENCES `t_jigheader` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jigdetail2_item` FOREIGN KEY (`jig_item_id`) REFERENCES `m_jigitem` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Painting: Bake Oven Temperature (F-PS-07) — times as rows, days as
-- columns, one actual-temperature reading per Time+Day, plus a daily Paraf
-- (who checked that day) and a monthly Asst.Foreman/Foreman/Supervisor sign-off.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_bakeoven`;
CREATE TABLE `m_bakeoven` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `standard_min` varchar(20) NULL DEFAULT NULL,
  `standard_max` varchar(20) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_bakeoven_department` (`department_id`),
  CONSTRAINT `fk_bakeoven_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_bakeoven_time`;
CREATE TABLE `m_bakeoven_time` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bakeoven_id` int(11) NOT NULL,
  `time_label` varchar(10) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_bakeoventime_bakeoven` (`bakeoven_id`),
  CONSTRAINT `fk_bakeoventime_bakeoven` FOREIGN KEY (`bakeoven_id`) REFERENCES `m_bakeoven` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_bakeoven_header`;
CREATE TABLE `t_bakeoven_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bakeoven_id` int(11) NOT NULL,
  `month` tinyint(2) NOT NULL,
  `year` smallint(6) NOT NULL,
  `asst_foreman_id` int(11) NULL DEFAULT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `notes` text NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bakeovenheader_period` (`bakeoven_id`, `month`, `year`),
  KEY `fk_bakeovenheader_asstforeman` (`asst_foreman_id`),
  KEY `fk_bakeovenheader_foreman` (`foreman_id`),
  KEY `fk_bakeovenheader_supervisor` (`supervisor_id`),
  CONSTRAINT `fk_bakeovenheader_bakeoven` FOREIGN KEY (`bakeoven_id`) REFERENCES `m_bakeoven` (`id`),
  CONSTRAINT `fk_bakeovenheader_asstforeman` FOREIGN KEY (`asst_foreman_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_bakeovenheader_foreman` FOREIGN KEY (`foreman_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_bakeovenheader_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `m_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_bakeoven_detail`;
CREATE TABLE `t_bakeoven_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `time_id` int(11) NOT NULL,
  `day` tinyint(2) NOT NULL,
  `actual_temp` varchar(20) NULL DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bakeovendetail_cell` (`header_id`, `time_id`, `day`),
  KEY `fk_bakeovendetail_time` (`time_id`),
  CONSTRAINT `fk_bakeovendetail_header` FOREIGN KEY (`header_id`) REFERENCES `t_bakeoven_header` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bakeovendetail_time` FOREIGN KEY (`time_id`) REFERENCES `m_bakeoven_time` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_bakeoven_paraf`;
CREATE TABLE `t_bakeoven_paraf` (
  `header_id` int(11) NOT NULL,
  `day` tinyint(2) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`header_id`, `day`),
  KEY `fk_bakeovenparaf_user` (`user_id`),
  CONSTRAINT `fk_bakeovenparaf_header` FOREIGN KEY (`header_id`) REFERENCES `t_bakeoven_header` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bakeovenparaf_user` FOREIGN KEY (`user_id`) REFERENCES `m_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Assembling: FO Pump (F-FIP-03) — one daily report per date, up to 9 line
-- items each moving quantities of a Model into 3 destinations (Production /
-- To Assembly Line / To Sparepart PTC). Total is summed live; Accumulation is a
-- running sum of Total within the calendar month (computed on read, resets
-- naturally every month via a month-scoped SQL window).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `t_fopump_header`;
CREATE TABLE `t_fopump_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `employee_count` int(11) NULL DEFAULT NULL,
  `working_minutes` int(11) NULL DEFAULT NULL,
  `shift_label` varchar(50) NULL DEFAULT NULL,
  `operator_id` int(11) NULL DEFAULT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `convert_production` int(11) NULL DEFAULT NULL,
  `convert_assembly` int(11) NULL DEFAULT NULL,
  `convert_export` int(11) NULL DEFAULT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fopumpheader_date` (`department_id`, `tanggal`),
  KEY `fk_fopumpheader_operator` (`operator_id`),
  KEY `fk_fopumpheader_foreman` (`foreman_id`),
  KEY `fk_fopumpheader_supervisor` (`supervisor_id`),
  CONSTRAINT `fk_fopumpheader_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`),
  CONSTRAINT `fk_fopumpheader_operator` FOREIGN KEY (`operator_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_fopumpheader_foreman` FOREIGN KEY (`foreman_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_fopumpheader_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `m_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_line`;
CREATE TABLE `t_fopump_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `line_no` tinyint(2) NOT NULL,
  `production_model` varchar(100) NULL DEFAULT NULL,
  `production_qty` int(11) NULL DEFAULT NULL,
  `assembly_model` varchar(100) NULL DEFAULT NULL,
  `assembly_qty` int(11) NULL DEFAULT NULL,
  `export_model` varchar(100) NULL DEFAULT NULL,
  `export_qty` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fopumpline_header` (`header_id`),
  CONSTRAINT `fk_fopumpline_header` FOREIGN KEY (`header_id`) REFERENCES `t_fopump_header` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Assembling: FO Pump Assy Daily Check Sheet (F-FIP-01) — quality checklist,
-- separate from the F-FIP-03 production report above.
-- Model -> Checklist Item (standard text, per model; result_type/expected_value
-- drive the conforming default shown in each new sample column — 'boolean'
-- pre-fills TRUE, 'value' pre-fills the expected reading e.g. "OK" or "4.5")
-- -> Header (one per Model+Date) -> Sample (a dynamic set of "checking number"
-- columns the user adds per header, e.g. unit #1, #11, #21 sampled that day)
-- -> Detail (one actual result per Item x Sample).
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_fopump_check_model`;
CREATE TABLE `m_fopump_check_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `fop_code` varchar(50) NULL DEFAULT NULL,
  `part_no` varchar(100) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_fopumpcheckmodel_department` (`department_id`),
  CONSTRAINT `fk_fopumpcheckmodel_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `m_fopump_check_item`;
CREATE TABLE `m_fopump_check_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `checking_item` varchar(255) NOT NULL,
  `standard` varchar(255) NULL DEFAULT NULL,
  `result_type` enum('boolean','value') NOT NULL DEFAULT 'value',
  `expected_value` varchar(50) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_fopumpcheckitem_model` (`model_id`),
  CONSTRAINT `fk_fopumpcheckitem_model` FOREIGN KEY (`model_id`) REFERENCES `m_fopump_check_model` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_check_header`;
CREATE TABLE `t_fopump_check_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `department_id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `prod_date_code` varchar(50) NULL DEFAULT NULL,
  `checker_id` int(11) NOT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_fopumpcheckheader_department` (`department_id`),
  KEY `fk_fopumpcheckheader_model` (`model_id`),
  KEY `fk_fopumpcheckheader_checker` (`checker_id`),
  KEY `fk_fopumpcheckheader_foreman` (`foreman_id`),
  KEY `fk_fopumpcheckheader_supervisor` (`supervisor_id`),
  CONSTRAINT `fk_fopumpcheckheader_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`),
  CONSTRAINT `fk_fopumpcheckheader_model` FOREIGN KEY (`model_id`) REFERENCES `m_fopump_check_model` (`id`),
  CONSTRAINT `fk_fopumpcheckheader_checker` FOREIGN KEY (`checker_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_fopumpcheckheader_foreman` FOREIGN KEY (`foreman_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_fopumpcheckheader_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `m_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_check_sample`;
CREATE TABLE `t_fopump_check_sample` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `sample_no` varchar(20) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_fopumpchecksample_header` (`header_id`),
  CONSTRAINT `fk_fopumpchecksample_header` FOREIGN KEY (`header_id`) REFERENCES `t_fopump_check_header` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_check_detail`;
CREATE TABLE `t_fopump_check_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `checklist_item_id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `actual_result` varchar(100) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fopumpcheckdetail_header` (`header_id`),
  KEY `fk_fopumpcheckdetail_item` (`checklist_item_id`),
  KEY `fk_fopumpcheckdetail_sample` (`sample_id`),
  CONSTRAINT `fk_fopumpcheckdetail_header` FOREIGN KEY (`header_id`) REFERENCES `t_fopump_check_header` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fopumpcheckdetail_item` FOREIGN KEY (`checklist_item_id`) REFERENCES `m_fopump_check_item` (`id`),
  CONSTRAINT `fk_fopumpcheckdetail_sample` FOREIGN KEY (`sample_id`) REFERENCES `t_fopump_check_sample` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Assembling: FO Pump Test Record (F-FIP-02) — FOP tester data log.
-- One header per Model+Date; a dynamic, user-extendable list of readings
-- (Rpm / cc-per-sec / Shim), each new row pre-filled from the model's target
-- spec so the operator only edits a row that deviates.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `m_fopump_test_model`;
CREATE TABLE `m_fopump_test_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `fop_code` varchar(50) NULL DEFAULT NULL,
  `standard_cc_sec` varchar(50) NULL DEFAULT NULL,
  `rpm` varchar(20) NULL DEFAULT NULL,
  `master_test` varchar(50) NULL DEFAULT NULL,
  `default_shim` varchar(50) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_fopumptestmodel_department` (`department_id`),
  CONSTRAINT `fk_fopumptestmodel_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_test_header`;
CREATE TABLE `t_fopump_test_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `department_id` int(11) NOT NULL,
  `model_id` int(11) NOT NULL,
  `oil_pressure` varchar(50) NULL DEFAULT NULL,
  `oil_temp` varchar(50) NULL DEFAULT NULL,
  `room_temp` varchar(50) NULL DEFAULT NULL,
  `start_test_time` varchar(20) NULL DEFAULT NULL,
  `checker_id` int(11) NOT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_fopumptestheader_department` (`department_id`),
  KEY `fk_fopumptestheader_model` (`model_id`),
  KEY `fk_fopumptestheader_checker` (`checker_id`),
  KEY `fk_fopumptestheader_foreman` (`foreman_id`),
  KEY `fk_fopumptestheader_supervisor` (`supervisor_id`),
  CONSTRAINT `fk_fopumptestheader_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`),
  CONSTRAINT `fk_fopumptestheader_model` FOREIGN KEY (`model_id`) REFERENCES `m_fopump_test_model` (`id`),
  CONSTRAINT `fk_fopumptestheader_checker` FOREIGN KEY (`checker_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_fopumptestheader_foreman` FOREIGN KEY (`foreman_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_fopumptestheader_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `m_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_test_row`;
CREATE TABLE `t_fopump_test_row` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `row_no` int(11) NOT NULL,
  `rpm` varchar(50) NULL DEFAULT NULL,
  `cc_sec` varchar(50) NULL DEFAULT NULL,
  `shim` varchar(50) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_fopumptestrow_header` (`header_id`),
  CONSTRAINT `fk_fopumptestrow_header` FOREIGN KEY (`header_id`) REFERENCES `t_fopump_test_header` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Assembling: FO Pump Daily Reject (F-FIP-04) — monthly reject log, one
-- header per Department+Month+Year (no per-line date, matches the paper
-- form), with a dynamic, user-extendable list of Model/Quantity/Remarks
-- lines added as rejects occur through the month.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `t_fopump_reject_header`;
CREATE TABLE `t_fopump_reject_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `month` tinyint(2) NOT NULL,
  `year` smallint(6) NOT NULL,
  `target` int(11) NULL DEFAULT NULL,
  `status` enum('draft','submitted') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fopumprejectheader_month` (`department_id`, `year`, `month`),
  CONSTRAINT `fk_fopumprejectheader_department` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_fopump_reject_line`;
CREATE TABLE `t_fopump_reject_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `header_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL,
  `model` varchar(100) NULL DEFAULT NULL,
  `quantity` int(11) NULL DEFAULT NULL,
  `remarks` varchar(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fopumprejectline_header` (`header_id`),
  CONSTRAINT `fk_fopumprejectline_header` FOREIGN KEY (`header_id`) REFERENCES `t_fopump_reject_header` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
