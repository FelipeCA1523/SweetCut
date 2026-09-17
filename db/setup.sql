-- ============================================================
-- SweetCut v2.0 - Base de datos para el catálogo de cortadores
-- Precios en pesos chilenos (CLP). Productos con imagen.
-- Categorías en tabla propia + orden manual.
-- Ejecutar en phpMyAdmin (XAMPP) o con:
--   C:\xampp\mysql\bin\mysql -u root < db/setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS sweetcut_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE sweetcut_db;

-- ------------------------------------------------------------
-- Tabla de categorías
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) UNIQUE NOT NULL,
  label VARCHAR(50) NOT NULL,
  emoji VARCHAR(10) DEFAULT NULL,
  sort_order INT DEFAULT 0,
  INDEX idx_cat_sort (sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO categories (name, label, emoji, sort_order) VALUES
('navidad',  'Navidad',  '🎄', 1),
('animales', 'Animales', '🦋', 2),
('infantil', 'Infantil', '🎈', 3),
('elegante', 'Elegante', '💎', 4);

-- ------------------------------------------------------------
-- Tabla de productos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  category VARCHAR(20) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  old_price DECIMAL(10,2) DEFAULT NULL,
  emoji VARCHAR(10) DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  image_thumb VARCHAR(255) DEFAULT NULL,
  sort_order INT DEFAULT 0,
  active TINYINT(1) DEFAULT 1,
  stock INT DEFAULT NULL, -- NULL = ilimitado; 0 = agotado; N = quedan N
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_prod_cat_active (category, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabla de usuarios administradores
-- Usuario por defecto: admin  |  Contraseña: admin123
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO admin_users (username, password) VALUES
('admin', '$2y$10$xU0QCZeXEJxpR.8w3je/WeMF8hUm/zGXj9VS/4Iua8tMsqA.pMQZW');

-- ------------------------------------------------------------
-- Productos de ejemplo (precios en CLP, sin decimales)
-- ------------------------------------------------------------
INSERT INTO products (name, category, description, price, old_price, emoji, image, sort_order) VALUES
('Estrella de Navidad', 'navidad', 'Cortador estrella con relieve de copo de nieve interior.', 8990, 12990, '⭐', 'assets/images/estrella-navidena.svg', 1),
('Reno Saltarín', 'navidad', 'Forma de reno con astas detalladas para galletas perfectas.', 9490, NULL, '🦌', 'assets/images/reno-saltarin.svg', 2),
('Copito de Nieve', 'navidad', 'Diseño hexagonal con patrón de cristal de hielo.', 7990, NULL, '❄️', 'assets/images/copito-de-nieve.svg', 3),
('Gato Juguetón', 'animales', 'Silueta de gato sentado con cola curvada.', 8490, NULL, '🐱', 'assets/images/gato-jugoton.svg', 1),
('Mariposa Tropical', 'animales', 'Alas detalladas con patrones de diseño floral.', 9990, NULL, '🦋', 'assets/images/mariposa-tropical.svg', 2),
('León Valiente', 'animales', 'Cabeza de león con melena esculpida.', 10490, NULL, '🦁', 'assets/images/leon-valiente.svg', 3),
('Unicornio Mágico', 'infantil', 'Unicornio con cuerno y crin detallados.', 11990, NULL, '🦄', 'assets/images/unicornio-magico.svg', 1),
('Cohete Espacial', 'infantil', 'Cohete listo para despegar hacia las estrellas.', 9490, NULL, '🚀', 'assets/images/cohete-espacial.svg', 2),
('Corazón Elegante', 'elegante', 'Corazón geométrico con facetas de diamante.', 7490, NULL, '💎', 'assets/images/corazon-elegante.svg', 1),
('Rosa Vintage', 'elegante', 'Rosa abierta con pétalos en capas.', 8990, NULL, '🌹', 'assets/images/rosa-vintage.svg', 2),
('Osito Polar', 'animales', 'Osito polar con forma redondeada y adorable.', 9990, NULL, '🐻‍❄️', 'assets/images/osito-polar.svg', 4),
('Campana Navideña', 'navidad', 'Campana con moño detallado para la temporada.', 7490, NULL, '🔔', 'assets/images/campana-navidena.svg', 4);

-- ------------------------------------------------------------
-- Tabla de pedidos (sin pasarela de pago ni boleta)
-- El pago se coordina por WhatsApp (efectivo / transferencia / contra entrega)
-- ------------------------------------------------------------
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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_status (status),
  INDEX idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Ítems de cada pedido (snapshot del producto al ordenar)
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- Tabla anti-spam: registra pedidos por IP (límite por tiempo)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_rate (
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;