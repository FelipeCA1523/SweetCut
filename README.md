# SweetCut — Catálogo de cortadores de galletas

Sitio de catálogo con frontend HTML/CSS/JS y panel de administración en PHP + MySQL.
Diseño para XAMPP / Apache siendo solo la base: la app es portátil (PHP 8 + PDO).

## Requisitos

- XAMPP (Apache + PHP 8.x + MySQL/MariaDB)
- PHP con extensiones: `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (esta última con soporte WebP para las miniaturas)
- En XAMPP, la extensión `gd` se habilita quitando el `;` de `;extension=gd` en `C:\xampp\php\php.ini`

## Instalación

1. Copia la carpeta del proyecto a `C:\xampp\htdocs\catalogo-galletas` (o similar).
2. Crea la base de datos:

   ```bash
   C:\xampp\mysql\bin\mysql -u root < db/setup.sql
   ```

   Si ya tienes una instalación previa, ejecuta en orden los scripts de `db/update-*.sql`
   (cada uno es de una sola vez por instalación; no re-ejecutar).

3. Configura la conexión (opcional): las credenciales de BD por defecto son
   `localhost` / `sweetcut_db` / `root` / sin contraseña (XAMPP). Se pueden
   sobrescribir con las variables de entorno `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

4. Configura el **número de WhatsApp de la tienda**: copia `js/config.local.example.js`
   como `js/config.local.js` y pon el número real (formato `56912345678`, sin `+`).
   Este archivo está **ignorado por git** para que el número personal nunca se suba
   al repositorio público. Es el número al que el cliente envía su pedido al confirmar.

5. Accede:
   - Catálogo: `http://localhost/catalogo-galletas/index.html`
   - Admin: `http://localhost/catalogo-galletas/admin/login.php`
   - Usuario por defecto: `admin` / `admin123` → **cámbiala en el panel** (menú "Cambiar contraseña").

## Estructura

```
admin/            Panel de administración (login, productos, CSV, contraseña, pedidos)
api/              Endpoints JSON públicos (products, categories, csrf, orders)
assets/images/    Imágenes de catálogo versionadas (SVG de ejemplo)
assets/uploads/   Subidas del admin NO versionadas en git (se generan en runtime)
config/           Conexión a la BD (protegida con .htaccess)
css/, js/         Estilos y scripts del catálogo público
db/               setup + migraciones SQL
index.html        Catálogo público (página única)
```

## Funcionalidades del admin

- CRUD de productos: imagen con validación real (`finfo`), emoji de respaldo,
  orden manual y estado activo/inactivo.
- **Miniaturas**: al subir JPG/PNG/WEBP/GIF se genera automáticamente un `.webp`
  400×400 (recorte central) en `assets/uploads/thumbs/` usando GD. Los SVG se
  muestran a tamaño original. Si GD no está disponible la app cae sin error a la
  imagen completa.
- **CSV**: exportar (`export.php`) e importar (`import.php`) productos.
  Separador `;` y BOM UTF-8 (compatible Excel es-CL). En la importación se
  actualiza si el CSV trae un `id` existente y se ignoran las columnas de imagen
  (las imágenes se suben por el formulario).
- Confirmación de borrado en modal, paginación (10/página) y filtros por categoría.
- **Stock / inventario**: cada producto admite **ilimitado** (vacío),
  **agotado** (0) o **quedan N** unidades. El catálogo muestra "Agotado" y
  bloquea cantidades mayores a las disponibles; el pedido valida y descuenta
  el stock en servidor (con bloqueo de fila `FOR UPDATE` para evitar ventas
  simultáneas). En el panel se ve una columna "Stock" con pills de estado
  (verde/amarillo/rojo/gris).
- **Dashboard de ventas** (en el panel): ventas y pedidos de los últimos 7 días
  (gráfico de barras), top 5 de productos más vendidos y conteo de pedidos por
  estado. Sin librerías externas; tolerante a instalaciones sin la tabla de pedidos.
- **Pedidos sin pasarela de pago**: el catálogo arma un pedido (carrito local + formulario
  de datos), lo guarda y lo confirma por WhatsApp (`wa.me`). Se gestiona en
  `admin/orders.php` con estados (Nuevo → Confirmado → En preparación → Listo para retiro /
  Despachado → Entregado, o Cancelado) y contacto directo con el cliente por WhatsApp.
  Los precios se recalculan en servidor (nunca se confía en los valores del navegador).
  **No se emiten boletas** ni se piden datos tributarios (sin RUT).

## Seguridad incluida

- CSRF (`hash_equals`) en todos los formularios del admin y en el pedido público (token por sesión)
- Sesiones endurecidas (`httponly`, `SameSite=Lax`, solo cookies, `use_strict_mode`)
  con timeout de inactividad (1 h)
- Anti-spam en pedidos: honeypot + límite de 5 pedidos / 10 min por IP + mínimo 5 s por sesión
- Escape de salida para evitar XSS en el catálogo
- Cabeceras `nosniff`, `Referrer-Policy`, `X-Frame-Options: DENY`, `Permissions-Policy`
- `.htaccess` con `Options -Indexes` y bloqueo de carpetas internas (`config/`, `db/`)
- Las subidas de imágenes se validan por contenido real (`finfo`) y la carpeta
  `assets/uploads/` bloquea la ejecución de scripts; los SVG se rechazan si traen scripts/eventos
- Credenciales de BD configurables por entorno (sin secretos en el repo)

## Notas

- La carpeta `assets/uploads/` no está versionada; respáldala por separado.
- **Sin pasarela de pago y sin boletas** (decisión del cliente): el pago
  (efectivo al retiro / entrega, transferencia o contra entrega) se coordina offline
  por WhatsApp; el sitio nunca cobra.
- El número de WhatsApp de la tienda se configura en `js/config.local.js`
  (no versionado; usa `js/config.local.example.js` como plantilla).
- **Stock**: NULL = ilimitado (se fabrica bajo pedido), 0 = agotado, N = quedan N.
  Se descuenta automáticamente al colocar un pedido. Instalaciones previas deben
  ejecutar `db/update-2.5.sql` (agrega la columna `stock`, por defecto ilimitado).
- El seguimiento del trabajo por sesión vive en [`changelog/`](changelog/README.md);
  cada sesión agrega un archivo con sus cambios, archivos tocados y pendientes.