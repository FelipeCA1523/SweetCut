# Sesión 02 — Pedidos sin pasarela de pago + confirmación por WhatsApp

> Fecha: 2026-09-11 · Objetivo del cliente: no pasarela de pago y no emisión de boletas.

## Alcance

Se reemplazó el placeholder de carrito por un **pedido real sin pago en línea**: el cliente
arma su pedido, deja sus datos, y la confirmación/pago se cierra por WhatsApp. No se cobra
en el sitio y no se emiten boletas ni se piden datos tributarios (sin RUT).

## Cambios realizados

### Base de datos
- Nuevas tablas en `db/update-2.3.sql` (migración para instalaciones existentes) y añadidas
  a `db/setup.sql` (instalaciones nuevas):
  - `orders`: datos del cliente (nombre, teléfono, correo opcional, región, comuna, dirección),
    tipo de entrega (`pickup`/`delivery`), método de pago manual
    (`efectivo`/`transferencia`/`contra_entrega`), notas, total y estado
    (`nuevo` → `confirmado` → `en_preparacion` → `listo_retiro`/`despachado` → `entregado`, `cancelado`)
    con número público único `SC-YYYYMMDD-XXXXXX`.
  - `order_items`: snapshot del producto al ordenar (nombre, precio, imagen, cantidad) con
    `ON DELETE CASCADE` sobre la orden.

### API pública (nueva)
- `api/csrf.php`: entrega un token CSRF por sesión para el formulario de pedido (el catálogo
  es estático, así que el token viaja en la cabecera `X-CSRF-Token`).
- `api/orders.php`: registra el pedido. **Los precios siempre se recalculan desde la BD**
  (nunca se confía en el cliente), se valida que los productos existan y estén activos, y todo
  se guarda en una transacción. Incluye CSRF (`hash_equals`), validación de campos (nombre,
  teléfono, email, región, comuna, dirección condicional, método de pago) y anti-spam básico
  (mínimo 5 s entre pedidos por sesión).

### Frontend
- `index.html`: drawer lateral del pedido (carrito) y modal de checkout con los campos
  comerciales, elección de entrega (retiro/despacho) y método de pago manual. Al confirmar se
  muestra el número de pedido y el botón **"Enviar por WhatsApp"** (`wa.me` con el resumen).
- `js/main.js`:
  - Carrito persistente en `localStorage` (cantidades, quitar ítem, total, contador en el botón).
  - `STORE_WHATSAPP` al inicio del archivo: **hay que configurarlo con el número de la tienda**.
  - Regiones de Chile en el selector.
  - Envío del pedido a `api/orders.php` con el token CSRF.
  - El modal de producto ahora "Agrega al pedido" y abre el drawer.
- `css/styles.css`: estilos del drawer, checkout, selector de región/comuna y método de pago.

### Admin
- `admin/order-constants.php`: etiquetas de estados, pagos y entregas + helpers `waNumber()`
  y `waContactLink()`.
- `admin/orders.php`: listado de pedidos con filtro por estado, paginación y acceso rápido a
  WhatsApp del cliente (link `wa.me`).
- `admin/order-detail.php`: detalle completo del pedido (datos, ítems con imagen, notas),
  cambio de estado con CSRF y botón "Contactar por WhatsApp".
- `admin/index.php`: link "Pedidos" en la barra superior y tarjeta de estadística "Pedidos
  nuevos" (con tolerancia a instalaciones sin la tabla `orders`).
- `admin/admin-style.css`: insignias por estado, tarjetas de detalle, botón WhatsApp.

## Configurar (importante)
**El número de WhatsApp de la tienda** se define en `js/main.js`
(constante `STORE_WHATSAPP`, formato `56912345678`). Sin él el botón "Enviar por WhatsApp"
apunta a un número de ejemplo.

## Verificado
- `php -l` sin errores en todos los archivos PHP.
- `node --check` sin errores en `js/main.js`.
- Se recomienda probar en navegador: agregar al pedido → finalizar pedido → confirmar →
  revisar el pedido en `admin/orders.php`.

## Pendientes / notas
- Emisión de boletas/facturas: **fuera de alcance** por decisión del cliente.
- El pago (efectivo/transferencia/contra entrega) se gestiona offline por WhatsApp; no hay
  registro de recepción del dinero más allá del estado manual.
- Futuro opcional: notificación por correo al confirmar el pedido, y compra mínima / días de
  retiro y despacho.