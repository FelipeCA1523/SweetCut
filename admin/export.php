<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

// Evitar que la configuración regional (es-CL) convierta 8990.5 en "8990,5"
setlocale(LC_NUMERIC, 'C');

// Previene "inyección de fórmulas" en Excel: si una celda empieza con = + - @
// Excel la interpreta como fórmula; se antepone una comilla para neutralizarla.
function csvCell($v): string {
    if ($v !== '' && strpos('=+-@', $v[0]) !== false) {
        return "'" . $v;
    }
    return $v;
}

$stmt = $pdo->query(
    "SELECT id, name, category, description, price, old_price, emoji, image, image_thumb, sort_order, active
     FROM products
     ORDER BY sort_order ASC, id ASC"
);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="productos_' . date('Ymd_His') . '.csv"');
header('X-Content-Type-Options: nosniff');

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
        (int)$r['id'],
        csvCell($r['name']),
        csvCell($r['category']),
        csvCell($r['description'] ?? ''),
        $r['price'],
        $r['old_price'] ?? '',
        csvCell($r['emoji'] ?? ''),
        csvCell($r['image'] ?? ''),
        csvCell($r['image_thumb'] ?? ''),
        (int)$r['sort_order'],
        (int)$r['active'],
    ], ';');
}

fclose($out);
exit;