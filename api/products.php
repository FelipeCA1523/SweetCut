<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';

try {
    // Endpoint unificado: una sola petición trae productos + categorías
    $stmt = $pdo->query(
        "SELECT p.id, p.name, p.category, p.description, p.price, p.old_price, p.emoji, p.image, p.image_thumb, p.stock
         FROM products p
         WHERE p.active = 1
         ORDER BY p.sort_order ASC, p.id DESC"
    );

    $products = $stmt->fetchAll();

    // Mapear a los nombres de campos que usa main.js
    $result = array_map(function ($p) {
        return [
            'id'          => (int)$p['id'],
            'name'        => $p['name'],
            'cat'         => $p['category'],
            'desc'        => $p['description'],
            'price'       => (float)$p['price'],
            'oldPrice'    => $p['old_price'] !== null ? (float)$p['old_price'] : null,
            'emoji'       => $p['emoji'],
            'image'       => $p['image'],
            'imageThumb'  => $p['image_thumb'],
            'stock'       => $p['stock'] !== null ? (int)$p['stock'] : null,
        ];
    }, $products);

    $stmt = $pdo->query(
        "SELECT c.name, c.label, c.emoji, COUNT(p.id) AS cnt
         FROM categories c
         LEFT JOIN products p ON p.category = c.name AND p.active = 1
         GROUP BY c.id, c.name, c.label, c.emoji
         ORDER BY c.sort_order ASC, c.name ASC"
    );

    $categories = array_map(function ($c) {
        return [
            'name'  => $c['name'],
            'label' => $c['label'],
            'emoji' => $c['emoji'],
            'count' => (int)$c['cnt'],
        ];
    }, $stmt->fetchAll());

    echo json_encode(['products' => $result, 'categories' => $categories]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Hubo un error al obtener los productos.']);
}