<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/order-constants.php';

// Filtro por estado
$status = isset($_GET['status']) ? (string)$_GET['status'] : '';
if ($status !== '' && !array_key_exists($status, $ORDER_STATUSES)) {
    $status = '';
}

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($status !== '') {
    $where = ' WHERE status = ?';
    $params = [$status];
}

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM orders$where");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$stmt = $pdo->prepare("SELECT * FROM orders$where ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$newOrders = 0;
try {
    $newOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'nuevo'")->fetchColumn();
} catch (PDOException $e) {
    $newOrders = 0;
}

$baseUrl = 'orders.php' . ($status !== '' ? '?status=' . urlencode($status) . '&' : '?');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pedidos - SweetCut Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin-style.css">
</head>
<body>

  <div class="admin-topbar">
    <div class="admin-logo">Sweet<span>Cut</span> <small>Admin</small></div>
    <div class="admin-user">
      <span>👋 <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
      <a href="index.php" class="link-light">Productos</a>
      <a href="change-password.php" class="link-light">Cambiar contraseña</a>
      <a href="../index.html" class="link-light">Ver catálogo</a>
      <a href="logout.php" class="btn-logout">Salir</a>
    </div>
  </div>

  <main class="admin-main">

    <div class="admin-header">
      <div>
        <h1>Pedidos</h1>
        <p>Pedidos sin pago en línea: se confirman y cierran por WhatsApp. No se emiten boletas.</p>
      </div>
      <a href="index.php" class="btn-secondary">← Volver a productos</a>
    </div>

    <?php if (isset($_SESSION['flash'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash']) ?></div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="filter-bar">
      <a href="orders.php" class="filter-chip <?= $status === '' ? 'active' : '' ?>">Todos</a>
      <?php foreach ($ORDER_STATUSES as $key => $label): ?>
        <a href="orders.php?status=<?= urlencode($key) ?>" class="filter-chip <?= $status === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="table-card">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Pedido</th>
            <th>Cliente</th>
            <th>Entrega</th>
            <th>Método</th>
            <th>Total</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($orders) === 0): ?>
            <tr><td colspan="7" class="empty-cell">No hay pedidos<?= $status !== '' ? ' con ese estado' : '' ?>.</td></tr>
          <?php endif; ?>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($o['order_no']) ?></strong>
                <br><small class="muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($o['created_at']))) ?></small>
              </td>
              <td>
                <strong><?= htmlspecialchars($o['customer_name']) ?></strong>
                <br><small class="muted"><?= htmlspecialchars($o['customer_phone']) ?></small>
              </td>
              <td>
                <?= htmlspecialchars(deliveryLabel($o['delivery_type'])) ?>
                <br><small class="muted"><?= htmlspecialchars($o['commune'] . ', ' . $o['region']) ?></small>
              </td>
              <td><?= htmlspecialchars(paymentLabel($o['payment_method'])) ?></td>
              <td><?= '$' . number_format($o['total'], 0, ',', '.') ?> CLP</td>
              <td><span class="status-pill status-<?= htmlspecialchars($o['status']) ?>"><?= htmlspecialchars(orderStatusLabel($o['status'])) ?></span></td>
              <td class="cell-actions">
                <a href="order-detail.php?id=<?= $o['id'] ?>" class="btn-small btn-edit">Ver</a>
                <?php $wa = waContactLink($o['customer_phone'], $o['order_no']); ?>
                <?php if ($wa !== ''): ?>
                  <a href="<?= htmlspecialchars($wa) ?>" target="_blank" rel="noopener" class="btn-small wa-btn">WhatsApp</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="admin-pagination">
        <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">← Anterior</a>
        <span class="page-info">Página <?= $page ?> de <?= $totalPages ?></span>
        <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>">Siguiente →</a>
      </div>
    <?php endif; ?>

    <?php if ($newOrders > 0): ?>
      <p class="muted" style="margin-top:24px; text-align:center;">🔔 Tienes <?= $newOrders ?> pedido(s) nuevo(s) esperando confirmación.</p>
    <?php endif; ?>

  </main>

</body>
</html>