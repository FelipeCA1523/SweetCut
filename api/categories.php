<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query(
        "SELECT c.name, c.label, c.emoji, COUNT(p.id) AS cnt
         FROM categories c
         LEFT JOIN products p ON p.category = c.name AND p.active = 1
         GROUP BY c.id, c.name, c.label, c.emoji
         ORDER BY c.sort_order ASC, c.name ASC"
    );

    $rows = $stmt->fetchAll();

    $result = array_map(function ($c) {
        return [
            'name'  => $c['name'],
            'label' => $c['label'],
            'emoji' => $c['emoji'],
            'count' => (int)$c['cnt'],
        ];
    }, $rows);

    echo json_encode($result);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Hubo un error al obtener las categorías.']);
}