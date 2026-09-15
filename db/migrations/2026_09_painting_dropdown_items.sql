-- Painting checklist: non-numeric items (a qualitative pass/fail state, not a
-- measured number) get a dropdown for Actual Result instead of a free text box,
-- matching the existing "Water pump leak" items. Numeric / formula standards
-- (e.g. 1.2, 60, "0.5 = 1") are left as number inputs.
--
-- Keyed on the standard text (not id) so it applies cleanly on the server too.
-- Non-destructive: only sets actual_input_type / actual_options; no data removed.
-- Safe to re-run.

UPDATE m_checklist_item SET actual_input_type='select', actual_options='Tidak Bocor,Bocor'
  WHERE standard_min='Tidak Bocor' AND (actual_input_type IS NULL OR actual_input_type<>'select');
UPDATE m_checklist_item SET actual_input_type='select', actual_options='Tidak Mampet,Mampet'
  WHERE standard_min='Tidak Mampet' AND (actual_input_type IS NULL OR actual_input_type<>'select');
UPDATE m_checklist_item SET actual_input_type='select', actual_options='Tidak Macet,Macet'
  WHERE standard_min='Tidak Macet' AND (actual_input_type IS NULL OR actual_input_type<>'select');
UPDATE m_checklist_item SET actual_input_type='select', actual_options='Tidak Pecah/Mati,Pecah/Mati'
  WHERE standard_min='Tidak Pecah/Mati' AND (actual_input_type IS NULL OR actual_input_type<>'select');
UPDATE m_checklist_item SET actual_input_type='select', actual_options='Kabut,Tidak Kabut'
  WHERE standard_min='Kabut' AND (actual_input_type IS NULL OR actual_input_type<>'select');
UPDATE m_checklist_item SET actual_input_type='select', actual_options='PB Lamp ON,PB Lamp OFF'
  WHERE standard_min='PB Lamp ON' AND (actual_input_type IS NULL OR actual_input_type<>'select');
UPDATE m_checklist_item SET actual_input_type='select', actual_options='Arah Air Ke Dalam,Arah Air Ke Luar'
  WHERE standard_min='Arah Air Ke Dalam' AND (actual_input_type IS NULL OR actual_input_type<>'select');
UPDATE m_checklist_item SET actual_input_type='select', actual_options='48Hz,NG'
  WHERE standard_min='48Hz' AND (actual_input_type IS NULL OR actual_input_type<>'select');
