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
