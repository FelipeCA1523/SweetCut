<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query(
        "SELECT id, name, category, description, price, old_price, emoji, image, image_thumb, sort_order
         FROM products
         WHERE active = 1
         ORDER BY sort_order ASC, id DESC"
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
        ];
    }, $products);

    echo json_encode($result);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Hubo un error al obtener los productos.']);
}