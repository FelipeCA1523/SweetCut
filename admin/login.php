<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$error = '';
$username = '';
$expired = isset($_GET['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'La solicitud expiró. Intenta de nuevo.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Ingresa usuario y contraseña.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['last_activity'] = time();
                header('Location: index.php');
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SweetCut Admin - Iniciar sesión</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin-style.css">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="login-logo">Sweet<span>Cut</span> <small>Admin</small></div>
    <h1>Bienvenido</h1>
    <p class="login-sub">Inicia sesión para administrar el catálogo.</p>E

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($expired && !$error): ?>
      <div class="alert alert-success">Tu sesión expiró por inactividad. Inicia sesión nuevamente.</div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
      <?= csrf_field() ?>
      <label for="username">Usuario</label>
      <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required>

      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required>

      <button type="submit" class="btn-primary">Entrar</button>
    </form>

    <p class="login-hint">Por defecto: <code>admin</code> / <code>admin123</code></p>
    <a href="../index.html" class="back-link">← Volver al catálogo</a>
  </div>
</body>
</html>