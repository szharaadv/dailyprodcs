-- Deduplicate FO Pump Assy Check models (m_fopump_check_model).
--
-- A previous id-based sync left duplicate rows: the same model name appears
-- twice (once per id set), and submitted records (t_fopump_check_header) ended
-- up split across both ids — so the Model dropdown showed every model twice and
-- you couldn't tell which one held the data.
--
-- This keeps ONE active row per (department_id, name): the one that already has
-- data (a header), else the lowest id. The redundant duplicates are HIDDEN via
-- is_active = 0 — NOTHING IS DELETED, so it's fully reversible and no submitted
-- data is lost. Matching is by name (not id) so it works on the server too,
-- where the ids differ. Safe to run more than once (idempotent).

UPDATE m_fopump_check_model t
JOIN (
    SELECT m.id, m.department_id, m.name,
           CONCAT(LPAD(1000000 - COALESCE(h.cnt, 0), 7, '0'), '-', LPAD(m.id, 7, '0')) AS rank_id
    FROM m_fopump_check_model m
    LEFT JOIN (SELECT model_id, COUNT(*) cnt FROM t_fopump_check_header GROUP BY model_id) h
           ON h.model_id = m.id
    WHERE m.is_active = 1
) me ON me.id = t.id
JOIN (
    SELECT department_id, name, MIN(rank_id) AS keep_rank
    FROM (
        SELECT m.department_id, m.name,
               CONCAT(LPAD(1000000 - COALESCE(h.cnt, 0), 7, '0'), '-', LPAD(m.id, 7, '0')) AS rank_id
        FROM m_fopump_check_model m
        LEFT JOIN (SELECT model_id, COUNT(*) cnt FROM t_fopump_check_header GROUP BY model_id) h
               ON h.model_id = m.id
        WHERE m.is_active = 1
    ) r
    GROUP BY department_id, name
) w ON w.department_id = me.department_id AND w.name = me.name
SET t.is_active = 0
WHERE me.rank_id <> w.keep_rank;

-- Verify: each model name should now appear exactly once.
-- SELECT department_id, name, COUNT(*) FROM m_fopump_check_model
--   WHERE is_active = 1 GROUP BY department_id, name HAVING COUNT(*) > 1;

-- To undo (re-show everything again):
-- UPDATE m_fopump_check_model SET is_active = 1 WHERE is_active = 0;
