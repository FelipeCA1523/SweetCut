-- ============================================================
-- Actualización v1.1 - SweetCut
-- Solo para bases de datos ya creadas con el setup.sql anterior.
-- Si NO has importado todavía, ignora este archivo y usa setup.sql.
-- ============================================================

USE sweetcut_db;

-- 1. Agregar columna de imagen
ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER emoji;

-- 2. Convertir precios de USD a CLP en los productos de ejemplo (ids 1 a 12)
UPDATE products SET price = 8990,  old_price = 12990 WHERE id = 1;
UPDATE products SET price = 9490,  old_price = NULL   WHERE id = 2;
UPDATE products SET price = 7990,  old_price = NULL   WHERE id = 3;
UPDATE products SET price = 8490,  old_price = NULL   WHERE id = 4;
UPDATE products SET price = 9990,  old_price = NULL   WHERE id = 5;
UPDATE products SET price = 10490, old_price = NULL   WHERE id = 6;
UPDATE products SET price = 11990, old_price = NULL   WHERE id = 7;
UPDATE products SET price = 9490,  old_price = NULL   WHERE id = 8;
UPDATE products SET price = 7490,  old_price = NULL   WHERE id = 9;
UPDATE products SET price = 8990,  old_price = NULL   WHERE id = 10;
UPDATE products SET price = 9990,  old_price = NULL   WHERE id = 11;
UPDATE products SET price = 7490,  old_price = NULL   WHERE id = 12;

-- 3. Asignar imágenes a los productos de ejemplo
UPDATE products SET image = 'assets/images/estrella-navidena.svg'  WHERE id = 1;
UPDATE products SET image = 'assets/images/reno-saltarin.svg'      WHERE id = 2;
UPDATE products SET image = 'assets/images/copito-de-nieve.svg'    WHERE id = 3;
UPDATE products SET image = 'assets/images/gato-jugoton.svg'       WHERE id = 4;
UPDATE products SET image = 'assets/images/mariposa-tropical.svg'  WHERE id = 5;
UPDATE products SET image = 'assets/images/leon-valiente.svg'      WHERE id = 6;
UPDATE products SET image = 'assets/images/unicornio-magico.svg'   WHERE id = 7;
UPDATE products SET image = 'assets/images/cohete-espacial.svg'    WHERE id = 8;
UPDATE products SET image = 'assets/images/corazon-elegante.svg'   WHERE id = 9;
UPDATE products SET image = 'assets/images/rosa-vintage.svg'       WHERE id = 10;
UPDATE products SET image = 'assets/images/osito-polar.svg'        WHERE id = 11;
UPDATE products SET image = 'assets/images/campana-navidena.svg'   WHERE id = 12;