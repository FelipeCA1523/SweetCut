<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

if (!csrf_verify()) {
    $_SESSION['flash'] = 'La solicitud expiró. No se realizó ningún cambio.';
    header('Location: index.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    // Obtener el producto antes de eliminar para poder borrar los archivos
    $stmt = $pdo->prepare("SELECT image, image_thumb FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    foreach (['image', 'image_thumb'] as $col) {
        if ($row && !empty($row[$col]) && strpos($row[$col], 'assets/') === 0) {
            $file = __DIR__ . '/../' . $row[$col];
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    $_SESSION['flash'] = 'Producto eliminado correctamente.';
}

header('Location: index.php');
exit;