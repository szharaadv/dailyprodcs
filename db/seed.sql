-- ============================================================
-- dailyprod — initial seed data
-- Run this AFTER schema.sql
-- ============================================================

USE dailyprod;

-- ------------------------------------------------------------
-- Users (name-picker login)
-- ------------------------------------------------------------
INSERT INTO m_user (name, role) VALUES
    ('Admin', 'admin');

-- ------------------------------------------------------------
-- Departments + sections
-- ------------------------------------------------------------
INSERT INTO m_department (name, form_type, sort_order) VALUES
    ('Painting', 'checklist', 1),
    ('Assembling', 'assembly', 2);

SET @painting_dept = (SELECT id FROM m_department WHERE name = 'Painting');
SET @assembling_dept = (SELECT id FROM m_department WHERE name = 'Assembling');

INSERT INTO m_checksheet_section (department_id, name, route, section_type, sort_order) VALUES
    (@painting_dept, 'Painting Checklist', 'painting_list.php', 'painting_checklist', 1),
    (@assembling_dept, 'Torque', 'assembly_list.php', 'assembly_checklist', 1);

-- ------------------------------------------------------------
-- Shifts
-- ------------------------------------------------------------
INSERT INTO m_shift (name, sort_order) VALUES
    ('Shift 1', 1),
    ('Shift 2', 2),
    ('Shift 3', 3);

-- ------------------------------------------------------------
-- Checked By (Painting)
-- ------------------------------------------------------------
SET @painting_section = (SELECT id FROM m_checksheet_section WHERE route = 'painting_list.php');
INSERT INTO m_checker (name, role, department_id, section_id) VALUES
    ('Foreman Painting', 'foreman', @painting_dept, @painting_section),
    ('Supervisor Painting', 'supervisor', @painting_dept, @painting_section);

-- ------------------------------------------------------------
-- Checked By (Assembling / Torque)
-- ------------------------------------------------------------
SET @assembling_section = (SELECT id FROM m_checksheet_section WHERE route = 'assembly_list.php');
INSERT INTO m_checker (name, role, department_id, section_id) VALUES
    ('Foreman Assembling', 'foreman', @assembling_dept, @assembling_section),
    ('Supervisor Assembling', 'supervisor', @assembling_dept, @assembling_section);

-- ------------------------------------------------------------
-- Painting conditions + checklist items
-- ------------------------------------------------------------
INSERT INTO m_condition (department_id, name, sort_order) VALUES
    (@painting_dept, 'Pretreatment', 1),
    (@painting_dept, 'Painting Line', 2),
    (@painting_dept, 'Water Daily Phosphate', 3);

SET @pretreatment = (SELECT id FROM m_condition WHERE department_id = @painting_dept AND name = 'Pretreatment');
SET @painting_line = (SELECT id FROM m_condition WHERE department_id = @painting_dept AND name = 'Painting Line');
SET @water_phosphate = (SELECT id FROM m_condition WHERE department_id = @painting_dept AND name = 'Water Daily Phosphate');

INSERT INTO m_checklist_item
    (condition_id, checking_item, metode_pengecekan, standard_min, standard_max, tank_tube, satuan, actual_input_type, actual_options, sort_order)
VALUES
(@pretreatment, 'Water pump pressure (tank 1)',        'Visual', '1.2', '1.4', '-', '-', 'number', NULL, 1),
(@pretreatment, 'Water phosphat temperature (tank 1)',  'Visual', '50',  '60',  '-', '-', 'number', NULL, 2),
(@pretreatment, 'Water pump leak tube1',                'Visual', 'Tidak Bocor', 'Tidak Bocor', '-', '-', 'select', 'Tidak Bocor,Bocor', 3),
(@pretreatment, 'Filter condition tank1',                'Visual', 'Tidak Mampet', 'Tidak Mampet', '-', '-', 'select', 'Tidak Mampet,Mampet', 4),
(@pretreatment, 'Total Acid phosphate value',           'Test',   '6.5', '7.5', '-', '-', 'number', NULL, 5);

INSERT INTO m_checklist_item
    (condition_id, checking_item, metode_pengecekan, standard_min, standard_max, tank_tube, satuan, actual_input_type, actual_options, sort_order)
VALUES
(@painting_line, 'Temperatur oven (drying after treatment)', 'Baca Digital',   '60', '70', '-', '-', 'number', NULL, 1),
(@painting_line, 'Infra red oven condition',                 'Visual',         'Tidak Pecah/Mati', 'Tidak Pecah/Mati', '-', '-', 'select', 'Tidak Pecah/Mati,Pecah/Mati', 2),
(@painting_line, 'Paint mixer condition',                     'Visual',         'Tidak Macet', 'Tidak Macet', '-', '-', 'select', 'Tidak Macet,Macet', 3),
(@painting_line, 'Exhaust fan',                               'Visual PB lamp', 'PB Lamp ON', 'PB Lamp ON', '-', '-', 'select', 'PB Lamp ON,PB Lamp OFF', 4),
(@painting_line, 'Water circulating pump',                    'Visual',         'Arah Air Ke Dalam', 'Arah Air Ke Dalam', '-', '-', 'select', 'Arah Air Ke Dalam,Tidak Sesuai', 5);

INSERT INTO m_checklist_item
    (condition_id, checking_item, metode_pengecekan, standard_min, standard_max, tank_tube, satuan, actual_input_type, actual_options, sort_order)
VALUES
(@water_phosphate, 'Total Acid Phosphate',                  'Test', '6.5', '7.5',     '-', '-',  'number', NULL, 1),
(@water_phosphate, 'Penambahan Phalphost',                  'Test', '0.5', '1',       '-', 'kg', 'number', NULL, 2),
(@water_phosphate, 'Acid Consume Phosphate',                 'Test', '0.4', '0.5',     '-', '-',  'number', NULL, 3);

-- ------------------------------------------------------------
-- Assembling models + checklist items
-- ------------------------------------------------------------
INSERT INTO m_assy_model (department_id, name, sort_order) VALUES
    (@assembling_dept, 'TF70', 1),
    (@assembling_dept, 'TF90V', 2);

SET @tf70 = (SELECT id FROM m_assy_model WHERE department_id = @assembling_dept AND name = 'TF70');
SET @tf90v = (SELECT id FROM m_assy_model WHERE department_id = @assembling_dept AND name = 'TF90V');

INSERT INTO m_assy_checklist_item (model_id, checking_item, standard, standard_min, standard_max, sort_order) VALUES
    (@tf70, 'Torque Cylinder Head Bolt', 'Nm', '18', '22', 1),
    (@tf70, 'Torque Con-rod Bolt',       'Nm', '20', '24', 2),
    (@tf70, 'Torque Crank Shaft Bolt',   'Nm', '25', '30', 3);

INSERT INTO m_assy_checklist_item (model_id, checking_item, standard, standard_min, standard_max, sort_order) VALUES
    (@tf90v, 'Torque Cylinder Head Bolt', 'Nm', '20', '25', 1),
    (@tf90v, 'Torque Con-rod Bolt',       'Nm', '22', '26', 2),
    (@tf90v, 'Torque Crank Shaft Bolt',   'Nm', '28', '33', 3);
