# Sesión 04 — Stock / inventario + Dashboard de ventas

Fecha: 2026-09-11
Tanda elegida por el cliente (continuar desarrollo): **Stock / inventario** y **Dashboard de ventas** en el admin.

## Alcance

- Control de stock por producto, con validación y descuento **en servidor** dentro de la misma transacción.
- Dashboard de ventas (últimos 7 días, top productos, pedidos por estado) integrado en el panel.

## Base de datos

- Nuevo `db/update-2.5.sql` (aplicada y también en `setup.sql`):
  `ALTER TABLE products ADD COLUMN stock INT DEFAULT NULL`.
  - Semántica: `NULL` = ilimitado (se fabrica bajo pedido); `0` = agotado; `N > 0` = quedan N (se descuenta al ordenar).

## API pública

- `api/products.php`: incluye `stock` en el SELECT y en el mapeo (`null` cuando es ilimitado).
- `api/orders.php`: reescrito el bloque de persistencia:
  - Los ítems se validan contra la BD con `SELECT ... FOR UPDATE` (bloqueo de fila ante compras simultáneas).
  - Se rechazan productos inactivos o inexistentes y cantidades superiores al stock
    (HTTP 422: `'<producto>' solo tiene N unidades disponibles.`).
  - El stock se descuenta (`stock = stock - ?`) solo en productos con stock definido
    (NULL ilimitado no se toca), en la misma transacción que inserta el pedido.
  - Se mantienen CSRF, honeypot, límite por IP/sesión y reintento por colisión de `order_no`.

## Catálogo (frontend)

- `js/main.js`:
  - `productCard`: badge "Agotado" y botón `+` deshabilitado cuando stock ≤ 0.
  - `addToCart` / `changeQty`: respetan el límite (`itemLimit`, stock o 99) y avisan por toast.
  - `renderCart`: el `+` se deshabilita al llegar al límite.
  - `openProduct`: el botón del modal muestra "Agotado" y se deshabilita.
  - Nueva función `toast(msg)` con auto-ocultado (2,6 s).
- `index.html`: contenedor `<div class="toast" id="toast" role="status">`.
- `css/styles.css`: `.stock-badge` (sobre la imagen), `.toast`, estados `:disabled`
  para `+` del carrito y del catálogo.

## Admin

- `product-form.php`: campo "Stock disponible" (vacío = ilimitado; 0 = agotado).
- `save-product.php`: valida con `ctype_digit` (vacío → `NULL`, solo enteros no negativos)
  y persiste la columna en INSERT/UPDATE.
- `admin/index.php`:
  - Nueva columna "Stock" con pills: `Ilimitado`, `Agotado`, `Quedan N` (≤5 destacado), `N u.`
    (colspan de la fila vacía actualizado a 8).
  - **Dashboard de ventas**: tarjetas con gráfico de barras de los últimos 7 días
    (totales y número de pedidos), top 5 productos (barras horizontales) y leyenda de
    estados (reusa `order-constants.php`). Todo calculado con try/catch para tolerar
    instalaciones sin la tabla `orders`.
- `admin-style.css`: `.sales-grid`, `.sales-card`, `.bar-chart`, `.hbar-*`, `.legend-*` y
  pills de stock (`--mint`/`--peach`/`--blush`/gris).

## Archivos tocados

- Nuevos: `db/update-2.5.sql`, `changelog/2026-09-11-sesion-04-stock-y-dashboard.md`.
- Modificados: `db/setup.sql`, `api/products.php`, `api/orders.php`, `js/main.js`,
  `index.html`, `css/styles.css`, `admin/product-form.php`, `admin/save-product.php`,
  `admin/index.php`, `admin/admin-style.css`, `README.md`, `changelog/README.md`.

## Verificación

- `php -l` en todo el proyecto y `node --check js/main.js` sin errores.
- Migración `update-2.5.sql` aplicada (`stock int(11) YES NULL`).
- API: `products.php` devuelve `stock` (valor y `null` según el producto).
- Pedido de 3 unidades con stock 2 → **422** `'Estrella de Navidad' solo tiene 2 unidades disponibles.`
- Pedido dentro de stock (1 + 2 ilimitado) → **201/200 OK** y `stock` 2→1;
  el producto con `NULL` no se modifica.
- Dashboard: login real `admin`/`admin123` por curl → el HTML incluye `sales-grid`,
  `bar-chart`, top productos y pills de stock.
- Datos de prueba eliminados y stock de prueba restaurado a `NULL`.

## Pendientes / notas

- El stock es orientativo para el cliente: no hay alertas de "pocas unidades" por correo/WhatsApp todavía
  (las pills `Quedan N` ya lo señalan en el panel).
- No hay validación que impida dejar stock en 0 al confirmar un pedido manualmente: el descuento
  automático cubre los pedidos del sitio.
- Instalaciones previas deben ejecutar `db/update-2.5.sql`.