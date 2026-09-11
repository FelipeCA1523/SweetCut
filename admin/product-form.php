<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$isEdit = $id > 0;

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: index.php');
        exit;
    }
}

$categories = $pdo->query("SELECT name, label, emoji FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEdit ? 'Editar' : 'Nuevo' ?> producto - SweetCut Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin-style.css">
</head>
<body>

  <div class="admin-topbar">
    <div class="admin-logo">Sweet<span>Cut</span> <small>Admin</small></div>
    <div class="admin-user">
      <span>👋 <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
      <a href="logout.php" class="btn-logout">Salir</a>
    </div>
  </div>

  <main class="admin-main admin-main-narrow">

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="admin-header">
      <div>
        <h1><?= $isEdit ? 'Editar producto' : 'Nuevo producto' ?></h1>
        <p>Los campos con * son obligatorios.</p>
      </div>
      <a href="index.php" class="btn-secondary">← Volver</a>
    </div>

    <div class="table-card">
      <form method="POST" action="save-product.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
          <input type="hidden" name="id" value="<?= $product['id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
          <div class="form-group">
            <label for="name">Nombre *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label for="category">Categoría *</label>
            <select id="category" name="category" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c['name']) ?>" <?= (isset($product['category']) && $product['category'] === $c['name']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars(($c['emoji'] ?? '') . ' ' . ($c['label'] ?? $c['name'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="price">Precio (CLP) *</label>
            <input type="number" id="price" name="price" step="1" min="0" value="<?= $product['price'] ?? '' ?>" required>
          </div>

          <div class="form-group">
            <label for="old_price">Precio anterior (CLP, opcional)</label>
            <input type="number" id="old_price" name="old_price" step="1" min="0" value="<?= $product['old_price'] ?? '' ?>">
          </div>

          <div class="form-group">
            <label for="image" class="label-upload">Imagen del producto</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
            <small class="hint">JPG, PNG, WEBP, GIF o SVG. Máximo 2 MB. Si no subes imagen, se usará el emoji.</small>
          </div>

          <div class="form-group">
            <label for="sort_order">Orden manual (menor = primero)</label>
            <input type="number" id="sort_order" name="sort_order" step="1" value="<?= (int)($product['sort_order'] ?? 0) ?>">
            <small class="hint">Menor = primero en el catálogo.</small>
          </div>

          <div class="form-group">
            <label for="emoji">Emoji / ícono (respaldo)</label>
            <input type="text" id="emoji" name="emoji" maxlength="10" value="<?= htmlspecialchars($product['emoji'] ?? '') ?>" placeholder="Ej: ⭐ 🐱 🚀">
          </div>

          <div class="form-group form-group-full">
            <label for="description">Descripción</label>
            <textarea id="description" name="description" rows="3"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
          </div>

          <?php if ($isEdit && !empty($product['image'])): ?>
            <div class="form-group form-group-full form-image-preview">
                <label>Imagen actual</label>
                <div class="preview-row">
                  <img src="../<?= htmlspecialchars($product['image']) ?>" class="preview-img" alt="">
                  <?php if (!empty($product['image_thumb'])): ?>
                    <div class="thumb-preview">
                      <img src="../<?= htmlspecialchars($product['image_thumb']) ?>" class="preview-img" alt="">
                      <small class="hint">Miniatura .webp</small>
                    </div>
                  <?php endif; ?>
                  <label class="checkbox-label">
                    <input type="checkbox" name="remove_image" value="1"> Eliminar imagen actual
                  </label>
                </div>
                <small class="hint">Al subir una imagen se genera automáticamente una miniatura .webp (400×400, recorte central) para el listado y el panel.</small>
              </div>
          <?php endif; ?>

          <?php if ($isEdit): ?>
            <div class="form-group">
              <label for="active">Estado</label>
              <select id="active" name="active">
                <option value="1" <?= $product['active'] ? 'selected' : '' ?>>Activo (visible en el catálogo)</option>
                <option value="0" <?= !$product['active'] ? 'selected' : '' ?>>Inactivo (oculto)</option>
              </select>
            </div>
          <?php endif; ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Crear producto' ?></button>
          <a href="index.php" class="btn-secondary">Cancelar</a>
        </div>
      </form>
    </div>

  </main>

</body>
</html>