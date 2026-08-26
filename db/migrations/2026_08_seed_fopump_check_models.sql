-- Local seed: full FO Pump Check model list (17 models) to match the master
-- data. Existing rows (TF 55 / TF 105 / TS 190) are re-ordered; the 14 others
-- are inserted. A stray local-only test model (TF65R-E) is deactivated.

-- Names follow the master-engine convention: no spaces (e.g. TF110L, TF70V).

-- Normalise any existing rows that were entered with spaces.
UPDATE m_fopump_check_model SET name = REPLACE(name, ' ', '');

-- Re-order the three that already exist.
UPDATE m_fopump_check_model SET sort_order = 1 WHERE name = 'TF55'  AND department_id = 2;
UPDATE m_fopump_check_model SET sort_order = 5 WHERE name = 'TF105' AND department_id = 2;
UPDATE m_fopump_check_model SET sort_order = 9 WHERE name = 'TS190' AND department_id = 2;

-- Hide the stray local test model that isn't in the master list.
UPDATE m_fopump_check_model SET is_active = 0 WHERE name = 'TF65R-E';

INSERT INTO m_fopump_check_model (department_id, name, fop_code, part_no, sort_order, is_active) VALUES
  (2, 'TF70',   '715',  '705200-51120',  2,  1),
  (2, 'TF75',   '703',  '705300-51102-D',3,  1),
  (2, 'TF90',   '716',  '705400-51110',  4,  1),
  (2, 'TF110L', '718',  '70550G-51130',  6,  1),
  (2, 'TF120',  '719',  '705600-51110',  7,  1),
  (2, 'TF160',  '720',  '705800-51110',  8,  1),
  (2, 'TS230',  '722',  '705990-51110', 10,  1),
  (2, 'TF300',  '723',  '705950-51110', 11,  1),
  (2, 'TF70V',  '713',  '70520H-51200', 12,  1),
  (2, 'TF85M',  '8J11', '70547H-51201', 13,  1),
  (2, 'TF105M', '0J11', '70557H-51201', 14,  1),
  (2, 'TF115M', '1J11', '70567H-51201', 15,  1),
  (2, 'TF150',  '714',  '70571H-51100', 16,  1),
  (2, 'TF155',  'N391', '70580H-51100', 17,  1);
