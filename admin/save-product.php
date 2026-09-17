<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Verificación CSRF: bloquear solicitudes forjadas desde otros sitios
if (!csrf_verify()) {
    $_SESSION['error'] = 'La solicitud expiró. Vuelve a intentarlo.';
    header('Location: product-form.php' . ($id > 0 ? '?id=' . $id : ''));
    exit;
}

$name = trim($_POST['name'] ?? '');
$category = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$old_price = ($_POST['old_price'] ?? '') !== '' ? (float)$_POST['old_price'] : null;
$emoji = trim($_POST['emoji'] ?? '');
$sort_order = (int)($_POST['sort_order'] ?? 0);
$active = isset($_POST['active']) ? (int)$_POST['active'] : 1;
$removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

// Stock: vacío = ilimitado (NULL); 0 = agotado; N = quedan N. Solo enteros no negativos.
$stockRaw = trim((string)($_POST['stock'] ?? ''));
if ($stockRaw !== '' && !ctype_digit($stockRaw)) {
    $_SESSION['error'] = 'El stock debe ser un número entero no negativo (o dejarlo vacío para ilimitado).';
    redirectBack($id);
}
$stock = $stockRaw === '' ? null : (int)$stockRaw;

// Categorías válidas desde la tabla categories (no confiar en valores hardcodeados)
$stmtCats = $pdo->query("SELECT name FROM categories");
$allowedCategories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

function redirectBack(int $id): void {
    header('Location: product-form.php' . ($id > 0 ? '?id=' . $id : ''));
    exit;
}

// Genera una miniatura cuadrada .webp para catálogo y panel. Devuelve la ruta relativa o null.
function generateThumb(string $ext, string $destPath): ?string {
    if (!extension_loaded('gd') || !(imagetypes() & IMG_WEBP)) {
        return null; // GD no disponible: se usa la imagen original
    }

    switch ($ext) {
        case 'jpg':  $src = @imagecreatefromjpeg($destPath); break;
        case 'png':  $src = @imagecreatefrompng($destPath); break;
        case 'webp': $src = @imagecreatefromwebp($destPath); break;
        case 'gif':  $src = @imagecreatefromgif($destPath); break;
        default:     return null; // SVG: no se rasteriza
    }
    if (!$src) {
        return null;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $target = 400;
    $cx = (int)(($w - $side) / 2);
    $cy = (int)(($h - $side) / 2);

    $thumb = imagecreatetruecolor($target, $target);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
    imagefill($thumb, 0, 0, $transparent);
    imagecopyresampled($thumb, $src, 0, 0, $cx, $cy, $target, $target, $side, $side);

    $fileName = preg_replace('/\.\w+$/', '', basename($destPath)) . '.webp';
    $thumbDir = __DIR__ . '/../assets/uploads/thumbs/';
    if (!is_dir($thumbDir) && !mkdir($thumbDir, 0777, true)) {
        imagedestroy($src);
        imagedestroy($thumb);
        return null;
    }
    $thumbPath = $thumbDir . $fileName;
    $ok = imagewebp($thumb, $thumbPath, 82);
    imagedestroy($src);
    imagedestroy($thumb);

    return $ok ? 'assets/uploads/thumbs/' . $fileName : null;
}

if ($name === '' || !in_array($category, $allowedCategories, true) || $price <= 0) {
    $_SESSION['error'] = 'Datos inválidos. Verifica que el nombre, la categoría y el precio sean correctos.';
    redirectBack($id);
}

// Imagen existente del producto (para edición)
$existingImagePath = null;
$existingThumbPath = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT image, image_thumb FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $existingImagePath = $row ? $row['image'] : null;
    $existingThumbPath = $row ? $row['image_thumb'] : null;
}

$image = $existingImagePath;
$imageThumb = $existingThumbPath;
$uploadedFile = null;
$thumbFile = null;

// Subida de nueva imagen
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];

    if ($file['size'] > 2 * 1024 * 1024) {
        $_SESSION['error'] = 'La imagen supera el tamaño máximo de 2 MB.';
        redirectBack($id);
    }

    // Detectar el tipo real del archivo (no confiar en el MIME que envía el navegador)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (isset($allowedMimes[$mime])) {
        $ext = $allowedMimes[$mime];
    } elseif (strpos($mime, 'xml') !== false || $mime === 'text/plain') {
        // Posible SVG: algunos sistemas lo detectan como XML/texto.
        // Verificamos que el contenido realmente declare un SVG.
        $head = file_get_contents($file['tmp_name'], false, null, 0, 512);
        if ($head !== false && stripos($head, '<svg') !== false) {
            $ext = 'svg';
        } else {
            $_SESSION['error'] = 'El archivo no es una imagen válida (' . htmlspecialchars($mime) . ').';
            redirectBack($id);
        }
    } else {
        $_SESSION['error'] = 'Formato de imagen no permitido. Usa JPG, PNG, WEBP, GIF o SVG.';
        redirectBack($id);
    }

    // Los SVG son texto: rechazar los que intenten ejecutar scripts/eventos (XSS)
    if ($ext === 'svg') {
        $svg = file_get_contents($file['tmp_name']);
        if ($svg === false || preg_match(
            '~<\s*(script|iframe|object|embed|foreignobject)|on(?:load|error|click|mouseover|focus|blur|submit|scroll|keyup|keydown|keypress)\s*=|javascript\s*:~i',
            $svg
        )) {
            $_SESSION['error'] = 'El archivo SVG contiene contenido no permitido (scripts o eventos).';
            redirectBack($id);
        }
    }

    $filename = 'prod_' . uniqid() . '.' . $ext;
    $destDir = __DIR__ . '/../assets/uploads/';
    $dest = $destDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $image = 'assets/uploads/' . $filename;
        $uploadedFile = $dest;

        // Generar miniatura .webp recortada (si GD está disponible)
        $thumbRel = generateThumb($ext, $dest);
        if ($thumbRel !== null) {
            $imageThumb = $thumbRel;
            $thumbFile = __DIR__ . '/../' . $thumbRel;
        } else {
            $imageThumb = null;
        }
    } else {
        $_SESSION['error'] = 'No se pudo guardar la imagen. Intenta de nuevo.';
        redirectBack($id);
    }
}

// Eliminar imagen y miniatura si el usuario marcó la casilla o reemplazó el archivo
if ($removeImage || $uploadedFile !== null) {
    foreach ([$existingImagePath, $existingThumbPath] as $oldPath) {
        if ($oldPath && strpos($oldPath, 'assets/') === 0) {
            $old = __DIR__ . '/../' . $oldPath;
            if (file_exists($old)) {
                unlink($old);
            }
        }
    }
}

if ($removeImage) {
    $image = null;
    $imageThumb = null;
}

try {
    if ($id > 0) {
        // Actualizar
        $stmt = $pdo->prepare(
            "UPDATE products SET
                name = ?, category = ?, description = ?, price = ?,
                old_price = ?, emoji = ?, image = ?, image_thumb = ?,
                sort_order = ?, active = ?, stock = ?
             WHERE id = ?"
        );
        $stmt->execute([$name, $category, $description, $price, $old_price, $emoji, $image, $imageThumb, $sort_order, $active, $stock, $id]);
        $_SESSION['flash'] = 'Producto actualizado correctamente.';
    } else {
        // Crear
        $stmt = $pdo->prepare(
            "INSERT INTO products (name, category, description, price, old_price, emoji, image, image_thumb, sort_order, active, stock)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)"
        );
        $stmt->execute([$name, $category, $description, $price, $old_price, $emoji, $image, $imageThumb, $sort_order, $stock]);
        $_SESSION['flash'] = 'Producto creado correctamente.';
    }

    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    // Si la BD falla, borramos la imagen/miniatura recién subidas para no dejar huérfanos
    if ($uploadedFile !== null && file_exists($uploadedFile)) {
        unlink($uploadedFile);
    }
    if ($thumbFile !== null && file_exists($thumbFile)) {
        unlink($thumbFile);
    }
    $_SESSION['error'] = 'No se pudo guardar el producto. Intenta de nuevo.';
    redirectBack($id);
}