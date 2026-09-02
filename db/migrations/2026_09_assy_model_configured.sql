-- ------------------------------------------------------------
-- Assembling / Torque: mark whether a Model's checking items have been
-- reviewed/adjusted to its own standard. A newly added model is auto-seeded
-- with a template's checking items (see admin/assy_models.php) and starts as
-- configured=0 ("Baru · belum diset"); it flips to 1 once its checking items
-- are edited, or when marked done manually.
-- ------------------------------------------------------------
ALTER TABLE `m_assy_model`
    ADD COLUMN `configured` tinyint(1) NOT NULL DEFAULT 0;

-- All models that already exist are considered configured.
UPDATE `m_assy_model` SET `configured` = 1;
