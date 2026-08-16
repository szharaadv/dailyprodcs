-- ============================================================
-- dailyprod — Daily Production Check Sheet (Painting & Assembling)
-- Fresh schema, modeled after the checksheet-prod app.
-- ============================================================

CREATE DATABASE IF NOT EXISTS dailyprod CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE dailyprod;

SET FOREIGN_KEY_CHECKS = 0;

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

DROP TABLE IF EXISTS `m_checksheet_section`;
CREATE TABLE `m_checksheet_section` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
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

DROP TABLE IF EXISTS `m_shift`;
CREATE TABLE `m_shift` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
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

DROP TABLE IF EXISTS `m_jig_item`;
CREATE TABLE `m_jig_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jig_id` int(11) NOT NULL,
  `checking_item` varchar(255) NOT NULL,
  `photo` varchar(255) NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_jigitem_jig` (`jig_id`),
  CONSTRAINT `fk_jigitem_jig` FOREIGN KEY (`jig_id`) REFERENCES `m_jig` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `t_jig_header`;
CREATE TABLE `t_jig_header` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jig_id` int(11) NOT NULL,
  `month` tinyint(2) NOT NULL,
  `year` smallint(6) NOT NULL,
  `supervisor_id` int(11) NULL DEFAULT NULL,
  `foreman_id` int(11) NULL DEFAULT NULL,
  `checker_id` int(11) NULL DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jigheader_period` (`jig_id`, `month`, `year`),
  KEY `fk_jigheader_supervisor` (`supervisor_id`),
  KEY `fk_jigheader_foreman` (`foreman_id`),
  KEY `fk_jigheader_checker` (`checker_id`),
  CONSTRAINT `fk_jigheader_jig` FOREIGN KEY (`jig_id`) REFERENCES `m_jig` (`id`),
  CONSTRAINT `fk_jigheader_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_jigheader_foreman` FOREIGN KEY (`foreman_id`) REFERENCES `m_user` (`id`),
  CONSTRAINT `fk_jigheader_checker` FOREIGN KEY (`checker_id`) REFERENCES `m_user` (`id`)
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
  CONSTRAINT `fk_jigdetail_header` FOREIGN KEY (`header_id`) REFERENCES `t_jig_header` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jigdetail_item` FOREIGN KEY (`jig_item_id`) REFERENCES `m_jig_item` (`id`)
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

SET FOREIGN_KEY_CHECKS = 1;
