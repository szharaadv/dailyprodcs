-- FO Pump Check: make checking items GLOBAL (one master list for every model).
-- SAFE / NON-DESTRUCTIVE version: no DELETE, foreign-key checks stay ON. Old
-- per-model items are only DEACTIVATED (kept in the table so history/FK never
-- breaks); submitted detail rows are remapped onto the master by sort_order.
--
-- Run once on a database that does NOT yet have the global items (e.g. server).
-- Back up first as a precaution.

-- 1. Schema: model_id becomes nullable (NULL = global), add standard_source.
ALTER TABLE `m_fopump_check_item`
  MODIFY `model_id` int(11) NULL DEFAULT NULL,
  ADD COLUMN `standard_source` enum('static','part_no','fop_code') NOT NULL DEFAULT 'static' AFTER `standard`;

-- 2. Master = the model that currently has the most active checking items
--    (the most complete set); ties broken by lowest id.
SET @master := (
  SELECT model_id FROM m_fopump_check_item
  WHERE model_id IS NOT NULL AND is_active = 1
  GROUP BY model_id
  ORDER BY COUNT(*) DESC, model_id ASC
  LIMIT 1
);

-- 3. Remap submitted detail rows from every other model's item copies onto the
--    master's items, matched by sort_order. (No rows are deleted.)
UPDATE t_fopump_check_detail d
JOIN m_fopump_check_item legacy ON legacy.id = d.checklist_item_id AND legacy.model_id <> @master
JOIN m_fopump_check_item master ON master.model_id = @master AND master.sort_order = legacy.sort_order
SET d.checklist_item_id = master.id;

-- 4. Promote the master set to global.
UPDATE m_fopump_check_item SET model_id = NULL WHERE model_id = @master;

-- 5. Deactivate the remaining per-model copies — NOT deleted, so any old
--    reference stays valid and nothing is lost.
UPDATE m_fopump_check_item SET is_active = 0 WHERE model_id IS NOT NULL;

-- 6. Mark the dynamic label rows (their Standard now comes from the model).
UPDATE m_fopump_check_item
   SET standard_source = 'part_no', standard = NULL
 WHERE model_id IS NULL AND checking_item = 'Label check - Part no';
UPDATE m_fopump_check_item
   SET standard_source = 'fop_code', standard = NULL
 WHERE model_id IS NULL AND checking_item = 'Label check - Model code';
