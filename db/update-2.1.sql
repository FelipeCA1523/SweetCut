-- ============================================================
-- Actualización v2.1 - SweetCut
-- Agrega la columna de miniatura (.webp) para listados.
-- Si NO has importado todavía, ignora este archivo y usa setup.sql.
-- ============================================================

USE sweetcut_db;

SET NAMES utf8mb4;

ALTER TABLE products ADD COLUMN image_thumb VARCHAR(255) DEFAULT NULL AFTER image;