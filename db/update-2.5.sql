-- ============================================================
-- Actualización v2.5 - SweetCut
-- Agrega control de stock por producto.
-- Solo para bases de datos ya existentes (v2.4 o anterior).
-- Si NO has importado todavía, ignora este archivo y usa setup.sql.
--
-- stock NULL  = sin stock definido (ilimitado, ¡se fabrica bajo pedido!)
-- stock 0     = agotado
-- stock N > 0 = quedan N unidades (se descuenta al ordenar)
-- ============================================================

USE sweetcut_db;

SET NAMES utf8mb4;

ALTER TABLE products ADD COLUMN stock INT DEFAULT NULL;