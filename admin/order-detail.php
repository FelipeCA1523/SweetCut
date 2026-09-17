<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/order-constants.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: orders.php');
    exit;
}

// Cambio de estado (POST, protegido con CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash'] = 'La solicitud expiró. Vuelve a intentarlo.';
    } else {
        $newStatus = $_POST['status'] ?? '';
        if (array_key_exists($newStatus, $ORDER_STATUSES)) {
            $upd = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $upd->execute([$newStatus, $id]);
            $_SESSION['flash'] = 'Estado del pedido actualizado.';
        } else {
            $_SESSION['flash'] = 'Estado no válido.';
        }
    }
    header('Location: order-detail.php?id=' . $id);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pedido <?= htmlspecialchars($order['order_no']) ?> - SweetCut Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin-style.css">
</head>
<body>

  <div class="admin-topbar">
    <div class="admin-logo">Sweet<span>Cut</span> <small>Admin</small></div>
    <div class="admin-user">
      <span>👋 <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
      <a href="index.php" class="link-light">Productos</a>
      <a href="orders.php" class="link-light">Pedidos</a>
      <a href="change-password.php" class="link-light">Cambiar contraseña</a>
      <a href="../index.html" class="link-light">Ver catálogo</a>
      <a href="logout.php" class="btn-logout">Salir</a>
    </div>
  </div>

  <main class="admin-main">

    <div class="admin-header">
      <div>
        <h1>Pedido <?= htmlspecialchars($order['order_no']) ?></h1>
        <p>Registrado el <?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))) ?></p>
      </div>
      <a href="orders.php" class="btn-secondary">← Volver a pedidos</a>
    </div>

    <?php if (isset($_SESSION['flash'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash']) ?></div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="order-meta">
      <div class="meta-grid">
        <div class="meta-card">
          <span>Cliente</span>
          <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
          <?php if ($order['customer_phone']): ?>
            <br><strong><?= htmlspecialchars($order['customer_phone']) ?></strong>
          <?php endif; ?>
          <?php if ($order['customer_email']): ?>
            <br><small><?= htmlspecialchars($order['customer_email']) ?></small>
          <?php endif; ?>
        </div>
        <div class="meta-card">
          <span>Entrega</span>
          <strong><?= htmlspecialchars(deliveryLabel($order['delivery_type'])) ?></strong>
          <?php if ($order['delivery_type'] === 'delivery'): ?>
            <br><strong><?= htmlspecialchars($order['address']) ?></strong>
          <?php endif; ?>
          <br><small><?= htmlspecialchars($order['commune'] . ', ' . $order['region']) ?></small>
        </div>
        <div class="meta-card">
          <span>Pago (manual, sin boleta)</span>
          <strong><?= htmlspecialchars(paymentLabel($order['payment_method'])) ?></strong>
          <br><small>Se coordina por WhatsApp</small>
        </div>
        <div class="meta-card">
          <span>Total</span>
          <strong><?= '$' . number_format($order['total'], 0, ',', '.') ?> CLP</strong>
          <br><small class="status-pill status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars(orderStatusLabel($order['status'])) ?></small>
        </div>
      </div>
    </div>

    <div class="table-card" style="margin-bottom:24px;">
      <table class="admin-table order-items-table">
        <thead>
          <tr>
            <th></th>
            <th>Producto</th>
            <th>Cant.</th>
            <th>Precio</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td>
                <?php if (!empty($it['image'])): ?>
                  <img src="../<?= htmlspecialchars($it['image']) ?>" alt="">
                <?php else: ?>
                  <span class="cell-emoji">🍪</span>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($it['name']) ?></strong></td>
              <td><?= (int)$it['qty'] ?></td>
              <td><?= '$' . number_format($it['price'], 0, ',', '.') ?> CLP</td>
              <td><strong><?= '$' . number_format($it['price'] * $it['qty'], 0, ',', '.') ?> CLP</strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($order['notes'])): ?>
      <div class="notes-box">
        <strong>Notas del cliente:</strong>
        <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
      </div>
    <?php endif; ?>

    <?php $wa = waContactLink($order['customer_phone'], $order['order_no']); ?>

    <div class="form-actions">
      <form method="POST" action="order-detail.php?id=<?= $id ?>" class="inline-form">
        <?= csrf_field() ?>
        <label for="status" style="display:none">Estado</label>
        <select name="status" id="status" class="status-select">
          <?php foreach ($ORDER_STATUSES as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary">Guardar estado</button>
      </form>
      <?php if ($wa !== ''): ?>
        <a href="<?= htmlspecialchars($wa) ?>" target="_blank" rel="noopener" class="btn-secondary wa-btn">Contactar por WhatsApp</a>
      <?php endif; ?>
    </div>

  </main>

</body>
</html>