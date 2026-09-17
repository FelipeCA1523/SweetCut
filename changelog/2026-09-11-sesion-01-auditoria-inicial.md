# Sesión 01 — Auditoría inicial y correcciones

> Fecha: 2026-09-11 · Proyecto transversado: catálogo (sin carrito, ver pendientes)

## Alcance

Auditoría completa del proyecto SweetCut (seguridad, diseño, rendimiento), corrección de
problemas encontrados y creación del registro de cambios por sesión.

## Cambios realizados

### Seguridad

- `admin/auth.php`
  - Cabeceras de seguridad en todo el panel: `X-Frame-Options: DENY` (anti-clickjacking),
    `X-Content-Type-Options: nosniff` y `Referrer-Policy: same-origin`.
  - `session.use_strict_mode=1`: rechaza ids de sesión generadas por terceros.
  - Cookie de sesión con `secure` automático cuando la app corre sobre HTTPS.
- `admin/login.php`
  - Protección básica contra fuerza bruta: tras 5 intentos fallidos se bloquea temporalmente
    la sesión (espera progresiva, máx. 5 min).
- `admin/logout.php`
  - Se invalida también la cookie de sesión en el navegador al cerrar sesión.
- `admin/save-product.php`
  - Rechazo de archivos SVG con contenido peligroso (`<script>`, `<iframe>`, `on*=` eventos,
    `javascript:`), para prevenir XSS almacenado.
- `admin/export.php`
  - Mitigación de "inyección de fórmulas" en Excel: celdas que empiezan con `= + - @` se
    neutralizan con una comilla inicial.
  - `setlocale(LC_NUMERIC, 'C')` para evitar que precios con punto decimal se serialicen como
    "8990,5" según el locale del servidor.
- `api/products.php` y `api/categories.php`
  - Se eliminó `Access-Control-Allow-Origin: *` (el catálogo es mismo-origen; abre la API a
    lectura remota sin necesidad).
  - `X-Content-Type-Options: nosniff` y `Cache-Control: no-store` (datos siempre frescos).
- `.htaccess` raíz
  - `ServerSignature Off`, `DirectoryIndex index.html index.php`, cabeceras `nosniff` /
    `Referrer-Policy` y `AddDefaultCharset UTF-8`.

### Bugs / corrección de funcionamiento

- `admin/login.php`: se eliminó un carácter "E" suelto que se renderizaba en la página de login.
- `admin/import.php`: los productos nuevos importados quedaban siempre `active=1` aunque el CSV
  indicara estado inactivo; ahora se respeta la columna `active`.
- `js/main.js`: se eliminó el XSS potencial por handlers inline (`onclick` con string
  interpolado) en los botones de filtro y en el botón "+" del carrito. Ahora los datos viajan
  por `data-*` y por id, resolviéndose contra el array `products`.

### Diseño / UX

- `index.html`: se cargaban `preconnect` de Google Fonts pero **nunca el stylesheet** de las
  fuentes (`Playfair Display` / `Inter`), por lo que la tipografía caía en serif/sans-serif.
  Se agregó el `<link>` de fuentes.
- `index.html`: `meta description`, `theme-color` y favicon SVG (🍪) inline.
- `admin/admin-style.css`: se eliminaron reglas duplicadas de `.checkbox-label`.

## Archivos tocados

| Archivo | Tipo de cambio |
|---------|----------------|
| `index.html` | Diseño/SEO (fuentes, meta, favicon) |
| `css/styles.css` | Sin cambios |
| `js/main.js` | Seguridad + refactor de eventos |
| `admin/auth.php` | Seguridad (headers, sesión) |
| `admin/login.php` | Seguridad (fuerza bruta) + bug carácter |
| `admin/logout.php` | Seguridad (cookie inválida) |
| `admin/import.php` | Bug (estado inactivo ignorado) |
| `admin/export.php` | Seguridad (CSV injection, locale) |
| `admin/save-product.php` | Seguridad (SVG malicioso) |
| `admin/admin-style.css` | Limpieza CSS |
| `api/products.php`, `api/categories.php` | Seguridad (CORS, headers) |
| `.htaccess` | Seguridad/rendimiento |
| `changelog/*` | Nuevo registro de cambios |

## Verificado

- `php -l` sin errores en los 8 archivos PHP tocados.
- `node --check` sin errores en `js/main.js`.

## Pendientes / notas

- **Carrito:** el usuario NO quiere carrito de compras por ahora. Se mantuvo el placeholder
  existente (botón "Carrito" + `addToCart` vía `alert`), documentado en el README como pendiente.
  No se añadió ninguna lógica nueva de carrito.
- Limitar intentos de login a nivel de IP/BD sería más robusto que la sesión (anotado para
  futuro si la app sale de XAMPP).
- Los SVG subidos por el admin se muestran a tamaño original (no generan miniatura `.webp`);
  si crecen mucho, evaluar rasterizarlos con GD al subir.
- Validar `description` y largo de campos en `import.php` (opcional).
- Probar el flujo completo en el navegador (login, subir imagen con/sin GD, CSV) antes del
  primer deploy.