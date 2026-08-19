-- Safe supplementary rows for shared master-data tables (users, sections,
-- section-user assignments) needed by the new Paint Viscosity + 3S-3T
-- checksheets. Uses INSERT IGNORE / ON DUPLICATE so it's safe to run even
-- if some of these rows already exist on the target database.

-- m_user
INSERT IGNORE INTO m_user (name, role, is_active) VALUES ('Rinaldi', 'user', 1);
INSERT IGNORE INTO m_user (name, role, is_active) VALUES ('Yayat', 'user', 1);
INSERT IGNORE INTO m_user (name, role, is_active) VALUES ('Syahdan', 'user', 1);

-- m_checksheet_section
INSERT IGNORE INTO m_checksheet_section (department_id, name, group_label, route, section_type, sort_order, is_active) VALUES (1, 'Paint Viscosity Check', NULL, 'paint_viscosity_list.php', 'paint_viscosity_monthly', 3, 1);
INSERT IGNORE INTO m_checksheet_section (department_id, name, group_label, route, section_type, sort_order, is_active) VALUES (1, 'Checksheet 3S-3T', NULL, '3s3t_list.php', '3s3t_weekly', 4, 1);
INSERT IGNORE INTO m_checksheet_section (department_id, name, group_label, route, section_type, sort_order, is_active) VALUES (2, 'Checksheet 3S-3T', NULL, '3s3t_list.php', '3s3t_weekly', 8, 1);

-- m_user_section (resolved by section route + user name, not raw ids)
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Tri' AND s.route = 'paint_viscosity_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Mita' AND s.route = 'paint_viscosity_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Uwes' AND s.route = 'paint_viscosity_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Tri' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Reza Kurnia S' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Trisna Ashari' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Mita' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Uwes' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Faozi' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Rinaldi' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Yayat' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Syahdan' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Tri' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Reza Kurnia S' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Trisna Ashari' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Mita' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Uwes' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Rinaldi' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Yayat' AND s.route = '3s3t_list.php';
INSERT IGNORE INTO m_user_section (user_id, section_id)
  SELECT u.id, s.id FROM m_user u, m_checksheet_section s
  WHERE u.name = 'Syahdan' AND s.route = '3s3t_list.php';
