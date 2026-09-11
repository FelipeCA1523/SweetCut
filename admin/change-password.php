<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'La solicitud expiró. Vuelve a intentarlo.';
    } else {
        $current = $_POST['current'] ?? '';
        $new = $_POST['new'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'No se encontró el usuario. Cierra sesión y vuelve a entrar.';
        } elseif (!password_verify($current, $row['password'])) {
            $error = 'La contraseña actual es incorrecta.';
        } elseif (mb_strlen($new) < 8) {
            $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif ($current === $new) {
            $error = 'La nueva contraseña debe ser distinta a la actual.';
        } elseif ($new !== $confirm) {
            $error = 'La nueva contraseña y su confirmación no coinciden.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $upd->execute([$hash, $_SESSION['admin_id']]);
            $_SESSION['flash'] = 'Contraseña actualizada correctamente.';
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cambiar contraseña - SweetCut Admin</title>
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

    <div class="admin-header">
      <div>
        <h1>Cambiar contraseña</h1>
        <p>Actualiza la contraseña de tu cuenta de administración.</p>
      </div>
      <a href="index.php" class="btn-secondary">← Volver al panel</a>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="table-card">
      <form method="POST" action="change-password.php" autocomplete="off">
        <?= csrf_field() ?>

        <div class="form-grid">
          <div class="form-group">
            <label for="current">Contraseña actual *</label>
            <input type="password" id="current" name="current" required>
          </div>

          <div class="form-group">
            <label for="new">Nueva contraseña *</label>
            <input type="password" id="new" name="new" required minlength="8">
            <small class="hint">Mínimo 8 caracteres.</small>
          </div>

          <div class="form-group">
            <label for="confirm">Confirmar nueva contraseña *</label>
            <input type="password" id="confirm" name="confirm" required>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">Cambiar contraseña</button>
          <a href="index.php" class="btn-secondary">Cancelar</a>
        </div>
      </form>
    </div>

  </main>

</body>
</html>