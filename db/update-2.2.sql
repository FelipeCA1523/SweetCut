-- ============================================================
-- Actualización v2.2 - SweetCut
-- Elimina la funcionalidad de etiquetas (badge) y destacado (featured).
-- Solo para bases de datos ya existentes (v2.1 o anterior).
-- Si NO has importado todavía, ignora este archivo y usa setup.sql.
-- ============================================================

SET NAMES utf8mb4;
USE sweetcut_db;

ALTER TABLE products DROP COLUMN badge;
ALTER TABLE products DROP COLUMN featured;