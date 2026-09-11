-- ============================================================
-- Actualización v2.0 - SweetCut
-- Para bases de datos creadas con el setup de la v1.1.
-- Si NO has importado todavía, ignora este archivo y usa setup.sql.
-- ============================================================

USE sweetcut_db;

SET NAMES utf8mb4;

-- 1. Tabla de categorías
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) UNIQUE NOT NULL,
  label VARCHAR(50) NOT NULL,
  emoji VARCHAR(10) DEFAULT NULL,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO categories (name, label, emoji, sort_order) VALUES
('navidad',  'Navidad',  '🎄', 1),
('animales', 'Animales', '🦋', 2),
('infantil', 'Infantil', '🎈', 3),
('elegante', 'Elegante', '💎', 4);

-- 2. Columnas nuevas: destacado y orden manual
ALTER TABLE products ADD COLUMN featured TINYINT(1) DEFAULT 0 AFTER image;
ALTER TABLE products ADD COLUMN sort_order INT DEFAULT 0 AFTER featured;

-- 3. Marcar algunos productos como destacados (por id, es inmune a diferencias de charset)
UPDATE products SET featured = 1 WHERE id = 1;
UPDATE products SET featured = 1 WHERE id = 7;