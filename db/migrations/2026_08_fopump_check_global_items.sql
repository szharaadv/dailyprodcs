-- FO Pump Check: make checking items GLOBAL (one master list for every model)
-- instead of a separate per-model copy. The two "Label check" rows (Part No,
-- Model code) become dynamic — their standard is pulled from each model's own
-- part_no / fop_code at display time (standard_source), so every model shows
-- the checklist immediately with its own label values, with zero per-model
-- setup.
--
-- Existing submitted records (headers 5/6/7 for models 1/2/3) are preserved:
-- their detail rows are remapped from the per-model item copies onto the
-- single global master (matched by sort_order) before the duplicate items are
-- removed.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Schema: model_id becomes nullable (NULL = global), add standard_source.
ALTER TABLE `m_fopump_check_item`
  MODIFY `model_id` int(11) NULL DEFAULT NULL,
  ADD COLUMN `standard_source` enum('static','part_no','fop_code') NOT NULL DEFAULT 'static' AFTER `standard`;

-- 2. Choose the lowest active model as the master set, remap every other
--    model's saved detail rows onto it (by sort_order), then drop the copies.
SET @master_model := (SELECT MIN(id) FROM m_fopump_check_model);

UPDATE t_fopump_check_detail d
JOIN m_fopump_check_item legacy ON legacy.id = d.checklist_item_id AND legacy.model_id <> @master_model
JOIN m_fopump_check_item master ON master.model_id = @master_model AND master.sort_order = legacy.sort_order
SET d.checklist_item_id = master.id;

DELETE FROM m_fopump_check_item WHERE model_id IS NOT NULL AND model_id <> @master_model;

-- 3. Promote the master set to global.
UPDATE m_fopump_check_item SET model_id = NULL WHERE model_id = @master_model;

-- 4. Mark the dynamic label rows (their standard now comes from the model).
UPDATE m_fopump_check_item
   SET standard_source = 'part_no', standard = NULL
 WHERE model_id IS NULL AND checking_item = 'Label check - Part no';
UPDATE m_fopump_check_item
   SET standard_source = 'fop_code', standard = NULL
 WHERE model_id IS NULL AND checking_item = 'Label check - Model code';

SET FOREIGN_KEY_CHECKS = 1;
