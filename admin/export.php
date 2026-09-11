<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$stmt = $pdo->query(
    "SELECT id, name, category, description, price, old_price, emoji, image, image_thumb, sort_order, active
     FROM products
     ORDER BY sort_order ASC, id ASC"
);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="productos_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');

// BOM UTF-8 para que Excel (es-CL) reconozca acentos y emojis
fwrite($out, "\xEF\xBB\xBF");

// Cabecera
$header = [
    'id', 'name', 'category', 'description', 'price', 'old_price',
    'emoji', 'image', 'image_thumb', 'sort_order', 'active'
];
fputcsv($out, $header, ';');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        $r['name'],
        $r['category'],
        $r['description'],
        $r['price'],
        $r['old_price'] ?? '',
        $r['emoji'] ?? '',
        $r['image'] ?? '',
        $r['image_thumb'] ?? '',
        (int)$r['sort_order'],
        (int)$r['active'],
    ], ';');
}

fclose($out);
exit;