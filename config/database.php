<?php
// Conexión PDO a MySQL (XAMPP por defecto: root sin contraseña)
// Las credenciales se pueden sobrescribir con variables de entorno
// (DB_HOST, DB_NAME, DB_USER, DB_PASS) sin tocar este archivo.
$DB_HOST = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$DB_NAME = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'sweetcut_db';
$DB_USER = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'No se pudo conectar a la base de datos. Ejecuta db/setup.sql primero.']));
}