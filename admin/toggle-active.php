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
    $stmt = $pdo->prepare("UPDATE products SET active = 1 - active WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: index.php');
exit;