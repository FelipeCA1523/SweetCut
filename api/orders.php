<?php
// Endpoint público que registra un pedido.
// SIN pasarela de pago y SIN boleta: el pago se coordina por WhatsApp.
// Los precios SIEMPRE se recalculan desde la BD (nunca se confía en el cliente).
// El stock se valida con bloqueo de fila (FOR UPDATE) y se descuenta en la misma transacción.
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

require_once __DIR__ . '/../config/database.php';

const ALLOWED_DELIVERY = ['pickup', 'delivery'];
const ALLOWED_PAYMENT  = ['efectivo', 'transferencia', 'contra_entrega'];
const MAX_ITEMS        = 30;

function fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Método no permitido.');
}

// --- CSRF (el token llega por cabecera desde api/csrf.php) ---
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($token === '' || empty($_SESSION['pub_csrf']) || !is_string($token) || !hash_equals($_SESSION['pub_csrf'], $token)) {
    fail(419, 'Tu sesión expiró. Recarga la página e intenta de nuevo.');
}

// --- Anti-spam básico: mínimo 5 s entre pedidos de la misma sesión ---
$now = time();
if (!empty($_SESSION['last_order_at']) && $now - (int)$_SESSION['last_order_at'] < 5) {
    fail(429, 'Estás enviando demasiado rápido. Espera unos segundos.');
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    fail(400, 'Datos inválidos.');
}

// --- Honeypot: los bots rellenan este campo oculto; un humano no lo ve ---
if (!empty($data['website'])) {
    // Respuesta falsa para no revelar la trampa; no se guarda nada.
    echo json_encode(['ok' => true, 'order_id' => 0, 'order_no' => 'SC-IGNORED', 'total' => 0]);
    exit;
}

// --- Límite por IP (anti-spam): máx. 5 pedidos por 10 min desde misma IP ---
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip !== '') {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM order_rate WHERE ip = ? AND created_at > NOW() - INTERVAL 10 MINUTE"
        );
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 5) {
            fail(429, 'Estás enviando demasiados pedidos. Intenta de nuevo más tarde.');
        }
    } catch (PDOException $e) {
        // Tabla order_rate ausente (instalación previa v2.3): se omite el límite.
    }
}

// --- Validación de campos comerciales (sin RUT ni datos tributarios) ---
$customer_name    = trim($data['customer_name'] ?? '');
$customer_phone   = trim($data['customer_phone'] ?? '');
$customer_email   = trim($data['customer_email'] ?? '');
$region           = trim($data['region'] ?? '');
$commune          = trim($data['commune'] ?? '');
$address          = trim($data['address'] ?? '');
$delivery_type    = $data['delivery_type'] ?? '';
$payment_method   = $data['payment_method'] ?? '';
$notes            = trim($data['notes'] ?? '');

if ($customer_name === '' || mb_strlen($customer_name) > 100) {
    fail(422, 'Ingresa un nombre válido.');
}
if ($customer_phone === '' || !preg_match('/^[0-9+\-()\s]{8,20}$/', $customer_phone)) {
    fail(422, 'Ingresa un teléfono válido.');
}
if ($customer_email !== '' && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
    fail(422, 'Ingresa un correo electrónico válido.');
}
if ($region === '' || mb_strlen($region) > 80) {
    fail(422, 'Selecciona tu región.');
}
if ($commune === '' || mb_strlen($commune) > 80) {
    fail(422, 'Ingresa tu comuna.');
}
if (!in_array($delivery_type, ALLOWED_DELIVERY, true)) {
    fail(422, 'Selecciona un tipo de entrega válido.');
}
if ($delivery_type === 'delivery' && $address === '') {
    fail(422, 'Ingresa tu dirección de despacho.');
}
if ($address !== '' && mb_strlen($address) > 255) {
    fail(422, 'La dirección es demasiado larga.');
}
if (!in_array($payment_method, ALLOWED_PAYMENT, true)) {
    fail(422, 'Selecciona un método de pago válido.');
}
if (mb_strlen($notes) > 500) {
    fail(422, 'Las notas son demasiado largas.');
}

// --- Ítems del pedido (id + cantidad; el resto lo resuelve la BD) ---
$items = $data['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    fail(422, 'Tu pedido está vacío.');
}
if (count($items) > MAX_ITEMS) {
    fail(422, 'Demasiados productos en el pedido.');
}

$cleanItems = [];
foreach ($items as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    if ($pid <= 0 || $qty <= 0 || $qty > 99) {
        fail(422, 'Pedido inválido (producto o cantidad incorrectos).');
    }
    $cleanItems[$pid] = ($cleanItems[$pid] ?? 0) + $qty;
}

// --- Guardar pedido + ítems + descuento de stock en una transacción ---
function randomOrderNo(): string {
    return 'SC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

$orderId = 0;
$orderNo = '';

for ($attempt = 0; $attempt < 3; $attempt++) {
    try {
        $pdo->beginTransaction();

        // Bloquear las filas de los productos contra compras simultáneas
        $ids  = array_keys($cleanItems);
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, name, price, image_thumb, image, active, stock
             FROM products WHERE id IN ($in) FOR UPDATE"
        );
        $stmt->execute($ids);

        $products = [];
        foreach ($stmt->fetchAll() as $p) {
            if (!$p['active']) {
                fail(422, 'Uno de los productos de tu pedido ya no está disponible.');
            }
            $products[(int)$p['id']] = $p;
        }

        // Precios reales + comprobación de stock (desde la BD, no del navegador)
        $orderItems = [];
        $total      = 0.0;
        foreach ($cleanItems as $pid => $qty) {
            if (!isset($products[$pid])) {
                fail(422, 'Uno de los productos de tu pedido ya no está disponible.');
            }
            $p     = $products[$pid];
            $price = (float)$p['price'];
            $stock = $p['stock'] !== null ? (int)$p['stock'] : null;
            if ($stock !== null && $qty > $stock) {
                fail(422, "'" . $p['name'] . "' solo tiene " . $stock . ' unidades disponibles.');
            }
            $total += $price * $qty;
            $orderItems[] = [
                'product_id' => $pid,
                'name'       => $p['name'],
                'price'      => $price,
                'image'      => $p['image_thumb'] ?: $p['image'],
                'qty'        => $qty,
                'stock'      => $stock,
            ];
        }

        if ($total <= 0) {
            fail(422, 'Total inválido.');
        }

        $orderNo = randomOrderNo();

        $ins = $pdo->prepare(
            "INSERT INTO orders (order_no, customer_name, customer_phone, customer_email, region, commune, address, delivery_type, payment_method, notes, total, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nuevo')"
        );
        $ins->execute([
            $orderNo,
            $customer_name,
            $customer_phone,
            $customer_email !== '' ? $customer_email : null,
            $region,
            $commune,
            $address !== '' ? $address : null,
            $delivery_type,
            $payment_method,
            $notes !== '' ? $notes : null,
            sprintf('%.2f', $total),
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $insItem = $pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, name, price, image, qty) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $updStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        foreach ($orderItems as $it) {
            $insItem->execute([
                $orderId,
                $it['product_id'],
                $it['name'],
                sprintf('%.2f', $it['price']),
                $it['image'],
                $it['qty'],
            ]);
            // Descuenta stock solo en productos con stock definido (NULL = ilimitado)
            if ($it['stock'] !== null) {
                $updStock->execute([$it['qty'], $it['product_id']]);
            }
        }

        $pdo->commit();
        break;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Colisión de order_no (poco probable); reintenta con otro número.
        if ($e->getCode() === '23000' && $attempt < 2) {
            continue;
        }
        fail(500, 'No se pudo registrar tu pedido. Intenta de nuevo.');
    }
}

$_SESSION['last_order_at'] = time();

// Registrar la IP para el límite anti-spam y limpiar registros antiguos con poca frecuencia
if ($ip !== '') {
    try {
        $ins = $pdo->prepare("INSERT INTO order_rate (ip) VALUES (?)");
        $ins->execute([$ip]);
        if (mt_rand(1, 100) === 1) {
            $pdo->exec("DELETE FROM order_rate WHERE created_at < NOW() - INTERVAL 1 DAY");
        }
    } catch (PDOException $e) {
        // Tabla ausente: no bloquea al cliente real.
    }
}

echo json_encode([
    'ok'       => true,
    'order_id' => $orderId,
    'order_no' => $orderNo,
    'total'    => round($total, 2),
]);