-- ============================================================
-- Actualización v2.3 - SweetCut
-- Agrega pedidos SIN pasarela de pago ni boleta: pedidos por
-- WhatsApp con métodos de pago manuales (efectivo/transferencia/contra entrega).
-- Si NO has importado todavía, ignora este archivo y usa setup.sql.
-- ============================================================

USE sweetcut_db;

SET NAMES utf8mb4;

-- Tabla de pedidos
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(20) UNIQUE NOT NULL,
  customer_name VARCHAR(100) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  customer_email VARCHAR(100) DEFAULT NULL,
  region VARCHAR(80) NOT NULL,
  commune VARCHAR(80) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  delivery_type ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
  payment_method ENUM('efectivo','transferencia','contra_entrega') NOT NULL DEFAULT 'transferencia',
  notes TEXT,
  total DECIMAL(12,2) NOT NULL,
  status ENUM('nuevo','confirmado','en_preparacion','listo_retiro','despachado','entregado','cancelado') NOT NULL DEFAULT 'nuevo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ítems de cada pedido (snapshot del producto al momento de ordenar)
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  image VARCHAR(255) DEFAULT NULL,
  qty INT NOT NULL DEFAULT 1,
  INDEX idx_order (order_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;