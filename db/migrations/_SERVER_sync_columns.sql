-- =====================================================================
-- SINKRONISASI KOLOM (IDEMPOTENT / NON-DESTRUCTIVE)
-- Dibangkitkan dari struktur DB lokal. Memastikan setiap kolom (yang aman
-- ditambah) ada di server. Hanya ADD COLUMN untuk yang KURANG; yang sudah
-- ada dilewati. Tidak ada DROP/DELETE. Aman dijalankan berkali-kali.
-- Jalankan SETELAH backup DB.
-- =====================================================================

DELIMITER $$
DROP PROCEDURE IF EXISTS _sync_addcol $$
CREATE PROCEDURE _sync_addcol(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tbl) = 1
     AND (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tbl AND COLUMN_NAME=col) = 0 THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END $$
DELIMITER ;
CALL _sync_addcol('m_3s3t_item','category','`category` varchar(100) NULL AFTER `department_id`');
CALL _sync_addcol('m_3s3t_item','standar_kriteria','`standar_kriteria` varchar(255) NULL AFTER `item_pemeriksaan`');
CALL _sync_addcol('m_3s3t_item','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `standar_kriteria`');
CALL _sync_addcol('m_3s3t_item','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_assy_checklist_item','standard','`standard` varchar(255) NULL AFTER `checking_item`');
CALL _sync_addcol('m_assy_checklist_item','standard_min','`standard_min` varchar(50) NULL AFTER `standard`');
CALL _sync_addcol('m_assy_checklist_item','standard_max','`standard_max` varchar(50) NULL AFTER `standard_min`');
CALL _sync_addcol('m_assy_checklist_item','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `standard_max`');
CALL _sync_addcol('m_assy_checklist_item','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_assy_checklist_item','blocked','`blocked` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_active`');
CALL _sync_addcol('m_assy_model','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `name`');
CALL _sync_addcol('m_assy_model','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_assy_model','configured','`configured` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_active`');
CALL _sync_addcol('m_bakeoven','standard_min','`standard_min` varchar(20) NULL AFTER `name`');
CALL _sync_addcol('m_bakeoven','standard_max','`standard_max` varchar(20) NULL AFTER `standard_min`');
CALL _sync_addcol('m_bakeoven','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `standard_max`');
CALL _sync_addcol('m_bakeoven','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_bakeoven_time','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `time_label`');
CALL _sync_addcol('m_bakeoven_time','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_checker','role','`role` varchar(50) NULL AFTER `name`');
CALL _sync_addcol('m_checker','section_id','`section_id` int(11) NULL AFTER `department_id`');
CALL _sync_addcol('m_checker','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `section_id`');
CALL _sync_addcol('m_checklist_item','metode_pengecekan','`metode_pengecekan` varchar(100) NOT NULL DEFAULT ''Visual'' AFTER `checking_item`');
CALL _sync_addcol('m_checklist_item','standard_min','`standard_min` varchar(50) NULL AFTER `metode_pengecekan`');
CALL _sync_addcol('m_checklist_item','standard_max','`standard_max` varchar(50) NULL AFTER `standard_min`');
CALL _sync_addcol('m_checklist_item','tank_tube','`tank_tube` varchar(50) NULL AFTER `standard_max`');
CALL _sync_addcol('m_checklist_item','satuan','`satuan` varchar(50) NULL AFTER `tank_tube`');
CALL _sync_addcol('m_checklist_item','actual_input_type','`actual_input_type` enum(''number'',''text'',''select'') NOT NULL DEFAULT ''number'' AFTER `satuan`');
CALL _sync_addcol('m_checklist_item','actual_options','`actual_options` varchar(255) NULL AFTER `actual_input_type`');
CALL _sync_addcol('m_checklist_item','category_options','`category_options` varchar(255) NULL AFTER `actual_options`');
CALL _sync_addcol('m_checklist_item','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `category_options`');
CALL _sync_addcol('m_checklist_item','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_checksheet_section','group_label','`group_label` varchar(100) NULL AFTER `name`');
CALL _sync_addcol('m_checksheet_section','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `section_type`');
CALL _sync_addcol('m_checksheet_section','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_checksheet_section','doc_title','`doc_title` varchar(150) NULL AFTER `is_active`');
CALL _sync_addcol('m_checksheet_section','doc_no','`doc_no` varchar(60) NULL AFTER `doc_title`');
CALL _sync_addcol('m_checksheet_section','doc_rev','`doc_rev` varchar(20) NULL AFTER `doc_no`');
CALL _sync_addcol('m_checksheet_section','doc_date','`doc_date` varchar(20) NULL AFTER `doc_rev`');
CALL _sync_addcol('m_condition','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `name`');
CALL _sync_addcol('m_condition','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_department','form_type','`form_type` enum(''checklist'',''assembly'') NOT NULL DEFAULT ''checklist'' AFTER `name`');
CALL _sync_addcol('m_department','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `form_type`');
CALL _sync_addcol('m_department','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_engine','sales_type','`sales_type` enum(''DOM'',''EXP'') NOT NULL DEFAULT ''DOM'' AFTER `id`');
CALL _sync_addcol('m_engine','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `model`');
CALL _sync_addcol('m_engine','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_fopump_check_item','model_id','`model_id` int(11) NULL AFTER `id`');
CALL _sync_addcol('m_fopump_check_item','standard','`standard` varchar(255) NULL AFTER `checking_item`');
CALL _sync_addcol('m_fopump_check_item','standard_source','`standard_source` enum(''static'',''part_no'',''fop_code'') NOT NULL DEFAULT ''static'' AFTER `standard`');
CALL _sync_addcol('m_fopump_check_item','result_type','`result_type` enum(''boolean'',''value'') NOT NULL DEFAULT ''value'' AFTER `standard_source`');
CALL _sync_addcol('m_fopump_check_item','expected_value','`expected_value` varchar(50) NULL AFTER `result_type`');
CALL _sync_addcol('m_fopump_check_item','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `expected_value`');
CALL _sync_addcol('m_fopump_check_item','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_fopump_check_model','fop_code','`fop_code` varchar(50) NULL AFTER `name`');
CALL _sync_addcol('m_fopump_check_model','part_no','`part_no` varchar(100) NULL AFTER `fop_code`');
CALL _sync_addcol('m_fopump_check_model','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `part_no`');
CALL _sync_addcol('m_fopump_check_model','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_fopump_test_model','fop_code','`fop_code` varchar(50) NULL AFTER `name`');
CALL _sync_addcol('m_fopump_test_model','standard_cc_sec','`standard_cc_sec` varchar(50) NULL AFTER `fop_code`');
CALL _sync_addcol('m_fopump_test_model','rpm','`rpm` varchar(20) NULL AFTER `standard_cc_sec`');
CALL _sync_addcol('m_fopump_test_model','master_test','`master_test` varchar(50) NULL AFTER `rpm`');
CALL _sync_addcol('m_fopump_test_model','default_shim','`default_shim` varchar(50) NULL AFTER `master_test`');
CALL _sync_addcol('m_fopump_test_model','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `default_shim`');
CALL _sync_addcol('m_fopump_test_model','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_holiday','is_workday','`is_workday` tinyint(1) NOT NULL DEFAULT 0 AFTER `label`');
CALL _sync_addcol('m_jig','part_name','`part_name` varchar(255) NULL AFTER `name`');
CALL _sync_addcol('m_jig','checking_method','`checking_method` varchar(100) NULL AFTER `part_name`');
CALL _sync_addcol('m_jig','frequency','`frequency` varchar(100) NULL AFTER `checking_method`');
CALL _sync_addcol('m_jig','pic','`pic` varchar(150) NULL AFTER `frequency`');
CALL _sync_addcol('m_jig','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `pic`');
CALL _sync_addcol('m_jig','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_jigitem','photo','`photo` varchar(255) NULL AFTER `checking_item`');
CALL _sync_addcol('m_jigitem','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `photo`');
CALL _sync_addcol('m_jigitem','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_paint_viscosity_item','process_name','`process_name` varchar(100) NULL AFTER `department_id`');
CALL _sync_addcol('m_paint_viscosity_item','maker_brand','`maker_brand` varchar(100) NULL AFTER `product_name`');
CALL _sync_addcol('m_paint_viscosity_item','standard_min','`standard_min` varchar(20) NULL AFTER `maker_brand`');
CALL _sync_addcol('m_paint_viscosity_item','standard_max','`standard_max` varchar(20) NULL AFTER `standard_min`');
CALL _sync_addcol('m_paint_viscosity_item','standard_unit','`standard_unit` varchar(50) NULL AFTER `standard_max`');
CALL _sync_addcol('m_paint_viscosity_item','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `standard_unit`');
CALL _sync_addcol('m_paint_viscosity_item','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_shift','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `name`');
CALL _sync_addcol('m_shift','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
CALL _sync_addcol('m_user','role','`role` enum(''superadmin'',''admin'',''user'') NOT NULL DEFAULT ''user'' AFTER `name`');
CALL _sync_addcol('m_user','pin','`pin` char(4) NULL AFTER `role`');
CALL _sync_addcol('m_user','title','`title` varchar(50) NULL AFTER `pin`');
CALL _sync_addcol('m_user','email','`email` varchar(150) NULL AFTER `title`');
CALL _sync_addcol('m_user','is_active','`is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `email`');
CALL _sync_addcol('m_user','created_at','`created_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `is_active`');
CALL _sync_addcol('t_3s3t_detail','week1','`week1` varchar(10) NULL AFTER `item_id`');
CALL _sync_addcol('t_3s3t_detail','week2','`week2` varchar(10) NULL AFTER `week1`');
CALL _sync_addcol('t_3s3t_detail','week3','`week3` varchar(10) NULL AFTER `week2`');
CALL _sync_addcol('t_3s3t_detail','week4','`week4` varchar(10) NULL AFTER `week3`');
CALL _sync_addcol('t_3s3t_detail','week5','`week5` varchar(10) NULL AFTER `week4`');
CALL _sync_addcol('t_3s3t_detail','remarks','`remarks` varchar(255) NULL AFTER `week5`');
CALL _sync_addcol('t_3s3t_detail','pic_id','`pic_id` int(11) NULL AFTER `remarks`');
CALL _sync_addcol('t_3s3t_header','operator_id','`operator_id` int(11) NULL AFTER `year`');
CALL _sync_addcol('t_3s3t_header','checker_at','`checker_at` datetime NULL AFTER `operator_id`');
CALL _sync_addcol('t_3s3t_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `checker_at`');
CALL _sync_addcol('t_3s3t_header','foreman_id','`foreman_id` int(11) NULL AFTER `created_at`');
CALL _sync_addcol('t_3s3t_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_3s3t_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_3s3t_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_assy_detail','actual_result','`actual_result` varchar(100) NULL AFTER `checklist_item_id`');
CALL _sync_addcol('t_assy_detail','consumable_item','`consumable_item` varchar(100) NULL AFTER `actual_result`');
CALL _sync_addcol('t_assy_header','mark_crank_shaft','`mark_crank_shaft` varchar(100) NULL AFTER `model_id`');
CALL _sync_addcol('t_assy_header','mark_conrod','`mark_conrod` varchar(100) NULL AFTER `mark_crank_shaft`');
CALL _sync_addcol('t_assy_header','mark_fo_pump','`mark_fo_pump` varchar(100) NULL AFTER `mark_conrod`');
CALL _sync_addcol('t_assy_header','no_cyl_block','`no_cyl_block` varchar(100) NULL AFTER `mark_fo_pump`');
CALL _sync_addcol('t_assy_header','no_engine','`no_engine` varchar(100) NULL AFTER `no_cyl_block`');
CALL _sync_addcol('t_assy_header','detail_model','`detail_model` varchar(150) NULL AFTER `no_engine`');
CALL _sync_addcol('t_assy_header','checker_at','`checker_at` datetime NULL AFTER `checker_id`');
CALL _sync_addcol('t_assy_header','foreman_id','`foreman_id` int(11) NULL AFTER `checker_at`');
CALL _sync_addcol('t_assy_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_assy_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_assy_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_assy_header','status','`status` enum(''draft'',''submitted'') NOT NULL DEFAULT ''submitted'' AFTER `supervisor_at`');
CALL _sync_addcol('t_assy_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `status`');
CALL _sync_addcol('t_assy_revision','old_value','`old_value` varchar(100) NULL AFTER `checklist_item_id`');
CALL _sync_addcol('t_assy_revision','new_value','`new_value` varchar(100) NULL AFTER `old_value`');
CALL _sync_addcol('t_assy_revision','note','`note` varchar(255) NULL AFTER `new_value`');
CALL _sync_addcol('t_assy_revision','revised_by','`revised_by` int(11) NULL AFTER `note`');
CALL _sync_addcol('t_assy_revision','revised_by_name','`revised_by_name` varchar(150) NULL AFTER `revised_by`');
CALL _sync_addcol('t_assy_revision','revised_at','`revised_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `revised_by_name`');
CALL _sync_addcol('t_bakeoven_detail','actual_temp','`actual_temp` varchar(20) NULL AFTER `day`');
CALL _sync_addcol('t_bakeoven_detail','updated_at','`updated_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `actual_temp`');
CALL _sync_addcol('t_bakeoven_header','asst_foreman_id','`asst_foreman_id` int(11) NULL AFTER `year`');
CALL _sync_addcol('t_bakeoven_header','foreman_id','`foreman_id` int(11) NULL AFTER `asst_foreman_id`');
CALL _sync_addcol('t_bakeoven_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_id`');
CALL _sync_addcol('t_bakeoven_header','notes','`notes` text NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_bakeoven_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `notes`');
CALL _sync_addcol('t_checksheet_detail','actual_result','`actual_result` varchar(100) NULL AFTER `checklist_item_id`');
CALL _sync_addcol('t_checksheet_detail','category','`category` varchar(50) NULL AFTER `actual_result`');
CALL _sync_addcol('t_checksheet_header','checker_at','`checker_at` datetime NULL AFTER `checker_id`');
CALL _sync_addcol('t_checksheet_header','foreman_id','`foreman_id` int(11) NULL AFTER `checker_at`');
CALL _sync_addcol('t_checksheet_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_checksheet_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_checksheet_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_checksheet_header','status','`status` enum(''draft'',''submitted'') NOT NULL DEFAULT ''submitted'' AFTER `shift_id`');
CALL _sync_addcol('t_checksheet_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `status`');
CALL _sync_addcol('t_edit_request','header_id','`header_id` int(11) NULL AFTER `checksheet_type`');
CALL _sync_addcol('t_edit_request','target_date','`target_date` date NULL AFTER `header_id`');
CALL _sync_addcol('t_edit_request','department_id','`department_id` int(11) NULL AFTER `target_date`');
CALL _sync_addcol('t_edit_request','condition_id','`condition_id` int(11) NULL AFTER `department_id`');
CALL _sync_addcol('t_edit_request','label','`label` varchar(255) NULL AFTER `condition_id`');
CALL _sync_addcol('t_edit_request','requested_by','`requested_by` int(11) NULL AFTER `label`');
CALL _sync_addcol('t_edit_request','status','`status` enum(''pending'',''approved'',''denied'') NOT NULL DEFAULT ''pending'' AFTER `reason`');
CALL _sync_addcol('t_edit_request','admin_note','`admin_note` varchar(500) NULL AFTER `status`');
CALL _sync_addcol('t_edit_request','unlock_expires_at','`unlock_expires_at` datetime NULL AFTER `admin_note`');
CALL _sync_addcol('t_edit_request','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `unlock_expires_at`');
CALL _sync_addcol('t_edit_request','resolved_at','`resolved_at` datetime NULL AFTER `created_at`');
CALL _sync_addcol('t_fopump_check_detail','actual_result','`actual_result` varchar(100) NULL AFTER `sample_id`');
CALL _sync_addcol('t_fopump_check_header','prod_date_code','`prod_date_code` varchar(50) NULL AFTER `tanggal`');
CALL _sync_addcol('t_fopump_check_header','checker_id','`checker_id` int(11) NULL AFTER `prod_date_code`');
CALL _sync_addcol('t_fopump_check_header','checker_at','`checker_at` datetime NULL AFTER `checker_id`');
CALL _sync_addcol('t_fopump_check_header','foreman_id','`foreman_id` int(11) NULL AFTER `checker_at`');
CALL _sync_addcol('t_fopump_check_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_fopump_check_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_fopump_check_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_fopump_check_header','status','`status` enum(''draft'',''submitted'') NOT NULL DEFAULT ''submitted'' AFTER `supervisor_at`');
CALL _sync_addcol('t_fopump_check_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `status`');
CALL _sync_addcol('t_fopump_check_sample','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `sample_no`');
CALL _sync_addcol('t_fopump_header','employee_count','`employee_count` int(11) NULL AFTER `tanggal`');
CALL _sync_addcol('t_fopump_header','working_minutes','`working_minutes` int(11) NULL AFTER `employee_count`');
CALL _sync_addcol('t_fopump_header','shift_label','`shift_label` varchar(50) NULL AFTER `working_minutes`');
CALL _sync_addcol('t_fopump_header','operator_id','`operator_id` int(11) NULL AFTER `shift_label`');
CALL _sync_addcol('t_fopump_header','checker_at','`checker_at` datetime NULL AFTER `operator_id`');
CALL _sync_addcol('t_fopump_header','foreman_id','`foreman_id` int(11) NULL AFTER `checker_at`');
CALL _sync_addcol('t_fopump_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_fopump_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_fopump_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_fopump_header','convert_production','`convert_production` int(11) NULL AFTER `supervisor_at`');
CALL _sync_addcol('t_fopump_header','convert_assembly','`convert_assembly` int(11) NULL AFTER `convert_production`');
CALL _sync_addcol('t_fopump_header','convert_export','`convert_export` int(11) NULL AFTER `convert_assembly`');
CALL _sync_addcol('t_fopump_header','status','`status` enum(''draft'',''submitted'') NOT NULL DEFAULT ''submitted'' AFTER `convert_export`');
CALL _sync_addcol('t_fopump_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `status`');
CALL _sync_addcol('t_fopump_line','production_model','`production_model` varchar(100) NULL AFTER `line_no`');
CALL _sync_addcol('t_fopump_line','production_qty','`production_qty` int(11) NULL AFTER `production_model`');
CALL _sync_addcol('t_fopump_line','assembly_model','`assembly_model` varchar(100) NULL AFTER `production_qty`');
CALL _sync_addcol('t_fopump_line','assembly_qty','`assembly_qty` int(11) NULL AFTER `assembly_model`');
CALL _sync_addcol('t_fopump_line','export_model','`export_model` varchar(100) NULL AFTER `assembly_qty`');
CALL _sync_addcol('t_fopump_line','export_qty','`export_qty` int(11) NULL AFTER `export_model`');
CALL _sync_addcol('t_fopump_reject_header','target','`target` int(11) NULL AFTER `year`');
CALL _sync_addcol('t_fopump_reject_header','status','`status` enum(''draft'',''submitted'') NOT NULL DEFAULT ''submitted'' AFTER `target`');
CALL _sync_addcol('t_fopump_reject_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `status`');
CALL _sync_addcol('t_fopump_reject_header','checker_id','`checker_id` int(11) NULL AFTER `created_at`');
CALL _sync_addcol('t_fopump_reject_header','checker_at','`checker_at` datetime NULL AFTER `checker_id`');
CALL _sync_addcol('t_fopump_reject_header','foreman_id','`foreman_id` int(11) NULL AFTER `checker_at`');
CALL _sync_addcol('t_fopump_reject_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_fopump_reject_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_fopump_reject_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_fopump_reject_line','model','`model` varchar(100) NULL AFTER `line_no`');
CALL _sync_addcol('t_fopump_reject_line','quantity','`quantity` int(11) NULL AFTER `model`');
CALL _sync_addcol('t_fopump_reject_line','remarks','`remarks` varchar(255) NULL AFTER `quantity`');
CALL _sync_addcol('t_fopump_test_header','destination','`destination` enum(''local'',''export'') NOT NULL DEFAULT ''local'' AFTER `model_id`');
CALL _sync_addcol('t_fopump_test_header','oil_pressure','`oil_pressure` varchar(50) NULL AFTER `destination`');
CALL _sync_addcol('t_fopump_test_header','oil_temp','`oil_temp` varchar(50) NULL AFTER `oil_pressure`');
CALL _sync_addcol('t_fopump_test_header','room_temp','`room_temp` varchar(50) NULL AFTER `oil_temp`');
CALL _sync_addcol('t_fopump_test_header','start_test_time','`start_test_time` varchar(20) NULL AFTER `room_temp`');
CALL _sync_addcol('t_fopump_test_header','checker_at','`checker_at` datetime NULL AFTER `checker_id`');
CALL _sync_addcol('t_fopump_test_header','foreman_id','`foreman_id` int(11) NULL AFTER `checker_at`');
CALL _sync_addcol('t_fopump_test_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_fopump_test_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_fopump_test_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_fopump_test_header','status','`status` enum(''draft'',''submitted'') NOT NULL DEFAULT ''submitted'' AFTER `supervisor_at`');
CALL _sync_addcol('t_fopump_test_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `status`');
CALL _sync_addcol('t_fopump_test_row','rpm','`rpm` varchar(50) NULL AFTER `row_no`');
CALL _sync_addcol('t_fopump_test_row','cc_sec','`cc_sec` varchar(50) NULL AFTER `rpm`');
CALL _sync_addcol('t_fopump_test_row','shim','`shim` varchar(50) NULL AFTER `cc_sec`');
CALL _sync_addcol('t_fopump_test_row','sort_order','`sort_order` int(11) NOT NULL DEFAULT 0 AFTER `shim`');
CALL _sync_addcol('t_jigheader','supervisor_id','`supervisor_id` int(11) NULL AFTER `year`');
CALL _sync_addcol('t_jigheader','foreman_id','`foreman_id` int(11) NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_jigheader','checker_id','`checker_id` int(11) NULL AFTER `foreman_id`');
CALL _sync_addcol('t_jigheader','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `checker_id`');
CALL _sync_addcol('t_jig_detail','updated_at','`updated_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `result`');
CALL _sync_addcol('t_paint_viscosity_detail','actual_result','`actual_result` varchar(20) NULL AFTER `day`');
CALL _sync_addcol('t_paint_viscosity_detail','updated_at','`updated_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `actual_result`');
CALL _sync_addcol('t_paint_viscosity_header','checker_id','`checker_id` int(11) NULL AFTER `year`');
CALL _sync_addcol('t_paint_viscosity_header','checker_at','`checker_at` datetime NULL AFTER `checker_id`');
CALL _sync_addcol('t_paint_viscosity_header','foreman_id','`foreman_id` int(11) NULL AFTER `checker_at`');
CALL _sync_addcol('t_paint_viscosity_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_paint_viscosity_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_paint_viscosity_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');
CALL _sync_addcol('t_paint_viscosity_header','notes','`notes` text NULL AFTER `supervisor_at`');
CALL _sync_addcol('t_paint_viscosity_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `notes`');
CALL _sync_addcol('t_washing_detail','ganti_air','`ganti_air` varchar(50) NULL AFTER `day`');
CALL _sync_addcol('t_washing_detail','temperatur_air','`temperatur_air` varchar(20) NULL AFTER `ganti_air`');
CALL _sync_addcol('t_washing_detail','penambahan_gildaon','`penambahan_gildaon` varchar(50) NULL AFTER `temperatur_air`');
CALL _sync_addcol('t_washing_detail','total_acid','`total_acid` varchar(20) NULL AFTER `penambahan_gildaon`');
CALL _sync_addcol('t_washing_detail','checker_id','`checker_id` int(11) NULL AFTER `total_acid`');
CALL _sync_addcol('t_washing_detail','control_id','`control_id` int(11) NULL AFTER `checker_id`');
CALL _sync_addcol('t_washing_detail','updated_at','`updated_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `control_id`');
CALL _sync_addcol('t_washing_header','created_at','`created_at` datetime NOT NULL DEFAULT current_timestamp() AFTER `year`');
CALL _sync_addcol('t_washing_header','checker_id','`checker_id` int(11) NULL AFTER `created_at`');
CALL _sync_addcol('t_washing_header','checker_at','`checker_at` datetime NULL AFTER `checker_id`');
CALL _sync_addcol('t_washing_header','foreman_id','`foreman_id` int(11) NULL AFTER `checker_at`');
CALL _sync_addcol('t_washing_header','foreman_at','`foreman_at` datetime NULL AFTER `foreman_id`');
CALL _sync_addcol('t_washing_header','supervisor_id','`supervisor_id` int(11) NULL AFTER `foreman_at`');
CALL _sync_addcol('t_washing_header','supervisor_at','`supervisor_at` datetime NULL AFTER `supervisor_id`');

DROP PROCEDURE IF EXISTS _sync_addcol;

-- ---------------------------------------------------------------------
-- Tidak diikutkan (NOT NULL tanpa default = kolom inti, pasti sudah ada
-- di server). Kalau ternyata ada yang kurang, tangani manual:
--   m_3s3t_item.department_id (int(11))
--   m_3s3t_item.item_pemeriksaan (varchar(255))
--   m_assy_checklist_item.model_id (int(11))
--   m_assy_checklist_item.checking_item (varchar(255))
--   m_assy_model.department_id (int(11))
--   m_assy_model.name (varchar(50))
--   m_bakeoven.department_id (int(11))
--   m_bakeoven.name (varchar(150))
--   m_bakeoven_time.bakeoven_id (int(11))
--   m_bakeoven_time.time_label (varchar(10))
--   m_checker.name (varchar(100))
--   m_checker.department_id (int(11))
--   m_checklist_item.condition_id (int(11))
--   m_checklist_item.checking_item (varchar(255))
--   m_checksheet_section.department_id (int(11))
--   m_checksheet_section.name (varchar(150))
--   m_checksheet_section.route (varchar(100))
--   m_checksheet_section.section_type (varchar(30))
--   m_condition.department_id (int(11))
--   m_condition.name (varchar(100))
--   m_department.name (varchar(100))
--   m_engine.model (varchar(100))
--   m_fopump_check_item.checking_item (varchar(255))
--   m_fopump_check_model.department_id (int(11))
--   m_fopump_check_model.name (varchar(50))
--   m_fopump_test_model.department_id (int(11))
--   m_fopump_test_model.name (varchar(50))
--   m_holiday.tanggal (date)
--   m_holiday.label (varchar(150))
--   m_jig.department_id (int(11))
--   m_jig.name (varchar(150))
--   m_jigitem.jig_id (int(11))
--   m_jigitem.checking_item (varchar(255))
--   m_paint_viscosity_item.department_id (int(11))
--   m_paint_viscosity_item.product_name (varchar(150))
--   m_setting.setting_key (varchar(50))
--   m_setting.value (varchar(255))
--   m_shift.name (varchar(50))
--   m_user.name (varchar(150))
--   m_user_section.user_id (int(11))
--   m_user_section.section_id (int(11))
--   t_3s3t_detail.header_id (int(11))
--   t_3s3t_detail.item_id (int(11))
--   t_3s3t_header.department_id (int(11))
--   t_3s3t_header.line (varchar(100))
--   t_3s3t_header.month (tinyint(2))
--   t_3s3t_header.year (smallint(6))
--   t_assy_detail.header_id (int(11))
--   t_assy_detail.checklist_item_id (int(11))
--   t_assy_header.tanggal (date)
--   t_assy_header.department_id (int(11))
--   t_assy_header.model_id (int(11))
--   t_assy_header.checker_id (int(11))
--   t_assy_revision.header_id (int(11))
--   t_assy_revision.checklist_item_id (int(11))
--   t_bakeoven_detail.header_id (int(11))
--   t_bakeoven_detail.time_id (int(11))
--   t_bakeoven_detail.day (tinyint(2))
--   t_bakeoven_header.bakeoven_id (int(11))
--   t_bakeoven_header.month (tinyint(2))
--   t_bakeoven_header.year (smallint(6))
--   t_bakeoven_paraf.header_id (int(11))
--   t_bakeoven_paraf.day (tinyint(2))
--   t_bakeoven_paraf.user_id (int(11))
--   t_checksheet_detail.header_id (int(11))
--   t_checksheet_detail.checklist_item_id (int(11))
--   t_checksheet_header.tanggal (date)
--   t_checksheet_header.condition_id (int(11))
--   t_checksheet_header.department_id (int(11))
--   t_checksheet_header.checker_id (int(11))
--   t_checksheet_header.jam (time)
--   t_checksheet_header.shift_id (int(11))
--   t_edit_request.checksheet_type (varchar(30))
--   t_edit_request.reason (varchar(500))
--   t_fopump_check_detail.header_id (int(11))
--   t_fopump_check_detail.checklist_item_id (int(11))
--   t_fopump_check_detail.sample_id (int(11))
--   t_fopump_check_header.department_id (int(11))
--   t_fopump_check_header.model_id (int(11))
--   t_fopump_check_header.tanggal (date)
--   t_fopump_check_sample.header_id (int(11))
--   t_fopump_check_sample.sample_no (varchar(20))
--   t_fopump_header.department_id (int(11))
--   t_fopump_header.tanggal (date)
--   t_fopump_line.header_id (int(11))
--   t_fopump_line.line_no (tinyint(2))
--   t_fopump_reject_header.department_id (int(11))
--   t_fopump_reject_header.month (tinyint(2))
--   t_fopump_reject_header.year (smallint(6))
--   t_fopump_reject_line.header_id (int(11))
--   t_fopump_reject_line.line_no (int(11))
--   t_fopump_test_header.department_id (int(11))
--   t_fopump_test_header.model_id (int(11))
--   t_fopump_test_header.checker_id (int(11))
--   t_fopump_test_row.header_id (int(11))
--   t_fopump_test_row.row_no (int(11))
--   t_jigheader.jig_id (int(11))
--   t_jigheader.month (tinyint(2))
--   t_jigheader.year (smallint(6))
--   t_jig_detail.header_id (int(11))
--   t_jig_detail.jig_item_id (int(11))
--   t_jig_detail.day (tinyint(2))
--   t_jig_detail.result (enum('OK','NG'))
--   t_paint_viscosity_detail.header_id (int(11))
--   t_paint_viscosity_detail.item_id (int(11))
--   t_paint_viscosity_detail.day (tinyint(2))
--   t_paint_viscosity_header.department_id (int(11))
--   t_paint_viscosity_header.month (tinyint(2))
--   t_paint_viscosity_header.year (smallint(6))
--   t_washing_detail.header_id (int(11))
--   t_washing_detail.day (tinyint(2))
--   t_washing_header.department_id (int(11))
--   t_washing_header.month (tinyint(2))
--   t_washing_header.year (smallint(6))
-- ---------------------------------------------------------------------
