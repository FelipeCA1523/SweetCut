-- ============================================================
-- Actualización v2.4 - SweetCut
-- Índices de rendimiento + tabla anti-spam para pedidos públicos.
-- Solo para bases de datos ya existentes (v2.3 o anterior).
-- Si NO has importado todavía, ignora este archivo y usa setup.sql.
-- ============================================================

USE sweetcut_db;

SET NAMES utf8mb4;

-- Registro de pedidos por IP para limitar el abuso del formulario público.
CREATE TABLE IF NOT EXISTS order_rate (
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices de rendimiento
CREATE INDEX idx_prod_cat_active ON products (category, active);
CREATE INDEX idx_cat_sort ON categories (sort_order, name);
CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_orders_created ON orders (created_at);