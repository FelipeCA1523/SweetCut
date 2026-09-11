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

4. Accede:
   - Catálogo: `http://localhost/catalogo-galletas/index.html`
   - Admin: `http://localhost/catalogo-galletas/admin/login.php`
   - Usuario por defecto: `admin` / `admin123` → **cámbiala en el panel** (menú "Cambiar contraseña").

## Estructura

```
admin/            Panel de administración (login, productos, CSV, contraseña)
api/              Endpoints JSON públicos (products, categories)
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

## Seguridad incluida

- CSRF (`hash_equals`) en todos los formularios del admin
- Sesiones endurecidas (`httponly`, `SameSite=Lax`) con timeout de inactividad (1 h)
- Escape de salida para evitar XSS en el catálogo
- `.htaccess` con `Options -Indexes` y bloqueo de carpetas internas (`config/`, `db/`)
- Las subidas de imágenes se validan por contenido real (`finfo`) y la carpeta
  `assets/uploads/` bloquea la ejecución de scripts
- Credenciales de BD configurables por entorno (sin secretos en el repo)

## Notas

- La carpeta `assets/uploads/` no está versionada; respáldala por separado.
- El carrito del catálogo funciona todavía como aviso (alert) — pendiente de
  evolución.