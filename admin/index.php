<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

// Filtro por categoría
$cat = isset($_GET['cat']) ? $_GET['cat'] : '';

// Categorías válidas desde la tabla categories
$categories = $pdo->query("SELECT name, label, emoji FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
$catNames = array_column($categories, 'name');

// Si la categoría enviada no existe, la ignoramos
if ($cat !== '' && !in_array($cat, $catNames, true)) {
    $cat = '';
}

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Contar total según filtro para paginar
if ($cat !== '') {
    $countSql = "SELECT COUNT(*) FROM products WHERE category = ?";
    $countParams = [$cat];
    $where = " WHERE category = ?";
    $whereParams = [$cat];
} else {
    $countSql = "SELECT COUNT(*) FROM products";
    $countParams = [];
    $where = "";
    $whereParams = [];
}
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($countParams);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$sql = "SELECT * FROM products$where ORDER BY sort_order ASC, id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($whereParams);
$products = $stmt->fetchAll();

$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn();

// Base de la URL para la paginación (preserva el filtro de categoría)
$baseUrl = 'index.php' . ($cat !== '' ? '?cat=' . urlencode($cat) . '&' : '?');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SweetCut Admin - Panel</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin-style.css">
</head>
<body>

  <div class="admin-topbar">
    <div class="admin-logo">Sweet<span>Cut</span> <small>Admin</small></div>
    <div class="admin-user">
      <span>👋 <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
      <a href="../index.html" class="link-light">Ver catálogo</a>
      <a href="change-password.php" class="link-light">Cambiar contraseña</a>
      <a href="logout.php" class="btn-logout">Salir</a>
    </div>
  </div>

  <main class="admin-main">

    <div class="admin-header">
      <div>
        <h1>Panel de productos</h1>
        <p>Administra el catálogo: agrega, edita y elimina cortadores.</p>
      </div>
      <div class="header-actions">
        <a href="export.php" class="btn-secondary">Exportar CSV</a>
        <a href="import.php" class="btn-secondary">Importar CSV</a>
        <a href="product-form.php" class="btn-primary">+ Nuevo producto</a>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card"><span class="stat-label">Total</span><span class="stat-value"><?= $totalProducts ?></span></div>
      <div class="stat-card"><span class="stat-label">Activos</span><span class="stat-value"><?= $totalActive ?></span></div>
      <div class="stat-card"><span class="stat-label">Categorías</span><span class="stat-value"><?= count($categories) ?></span></div>
    </div>

    <?php if (isset($_SESSION['flash'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash']) ?></div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="filter-bar">
      <a href="index.php" class="filter-chip <?= $cat === '' ? 'active' : '' ?>">Todos</a>
      <?php foreach ($categories as $c): ?>
        <a href="index.php?cat=<?= urlencode($c['name']) ?>" class="filter-chip <?= $cat === $c['name'] ? 'active' : '' ?>">
          <?= htmlspecialchars($c['emoji'] ?? '') ?> <?= htmlspecialchars($c['label'] ?? $c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="table-card">
      <table class="admin-table">
        <thead>
          <tr>
            <th></th>
            <th>Producto</th>
            <th>Categoría</th>
            <th>Precio</th>
            <th>Orden</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($products) === 0): ?>
            <tr><td colspan="7" class="empty-cell">No hay productos en esta vista. Haz clic en "Nuevo producto".</td></tr>
          <?php endif; ?>
          <?php foreach ($products as $p): ?>
            <tr>
              <td class="cell-emoji">
                <?php if (!empty($p['image'])): ?>
                  <img src="../<?= htmlspecialchars($p['image_thumb'] ?: $p['image']) ?>" class="cell-thumb" alt="<?= htmlspecialchars($p['name']) ?>">
                <?php else: ?>
                  <?= htmlspecialchars($p['emoji'] ?? '🍪') ?>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= htmlspecialchars($p['name']) ?></strong>
                <br><small class="muted"><?= htmlspecialchars(mb_substr($p['description'] ?? '', 0, 50)) ?></small>
              </td>
              <td><span class="pill pill-<?= htmlspecialchars($p['category']) ?>"><?= htmlspecialchars($p['category']) ?></span></td>
              <td>
                <?= '$' . number_format($p['price'], 0, ',', '.') ?> CLP
                <?php if ($p['old_price']): ?><br><small class="muted old-price"><?= '$' . number_format($p['old_price'], 0, ',', '.') ?> CLP</small><?php endif; ?>
              </td>
              <td><span class="muted"><?= (int)$p['sort_order'] ?></span></td>
              <td>
                <form method="POST" action="toggle-active.php" class="inline-form">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <?= csrf_field() ?>
                  <button type="submit" class="badge-status <?= $p['active'] ? 'status-on' : 'status-off' ?>">
                    <?= $p['active'] ? 'Activo' : 'Inactivo' ?>
                  </button>
                </form>
              </td>
              <td class="cell-actions">
                <a href="product-form.php?id=<?= $p['id'] ?>" class="btn-small btn-edit">Editar</a>
                <form method="POST" action="delete-product.php" id="delete-form-<?= $p['id'] ?>" class="inline-form">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <?= csrf_field() ?>
                  <button type="button" class="btn-small btn-delete" data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" onclick="openDeleteModal(this)">Eliminar</button>
                </form>
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

  </main>

  <!-- MODAL CONFIRMACIÓN DE ELIMINACIÓN -->
  <div class="modal-overlay" id="deleteModal" onclick="if(event.target===this) closeDeleteModal()">
    <div class="modal-box">
      <h3>¿Eliminar producto?</h3>
      <p>Se eliminará <strong id="delName"></strong> junto con sus imágenes. Esta acción no se puede deshacer.</p>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
        <button type="button" class="btn-danger" onclick="submitDelete()">Eliminar</button>
      </div>
    </div>
  </div>

  <script>
    let deleteForm = null;
    function openDeleteModal(btn) {
      deleteForm = btn.closest('form');
      document.getElementById('delName').textContent = btn.dataset.name || 'este producto';
      document.getElementById('deleteModal').classList.add('open');
    }
    function closeDeleteModal() {
      document.getElementById('deleteModal').classList.remove('open');
      deleteForm = null;
    }
    function submitDelete() {
      if (deleteForm) deleteForm.submit();
    }
  </script>

</body>
</html>