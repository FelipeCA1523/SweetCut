<?php
// Endpoint público que entrega el token CSRF para el formulario de pedido.
// El catálogo es estático (index.html), así que el token vive en la sesión
// del visitante y se envía en la cabecera X-CSRF-Token al crear el pedido.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'path'     => '/',
    ]);
    session_start();
}

if (empty($_SESSION['pub_csrf'])) {
    $_SESSION['pub_csrf'] = bin2hex(random_bytes(32));
}

echo json_encode(['token' => $_SESSION['pub_csrf']]);