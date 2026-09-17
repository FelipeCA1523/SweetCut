<?php
// Middleware de autenticación y seguridad para el panel de administración

// Configuración previa de la sesión antes de iniciarla
ini_set('session.use_strict_mode', '1'); // Rechaza ids de sesión no emitidas por el servidor
ini_set('session.use_only_cookies', '1'); // No aceptar ids de sesión por URL
ini_set('session.gc_maxlifetime', '3600'); // Coincide con SESSION_TIMEOUT (1 h)
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

// Cabeceras de seguridad básicas (anti-clickjacking, anti-sniffing, referrer)
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Tiempo máximo de inactividad antes de cerrar sesión (en segundos): 1 hora
const SESSION_TIMEOUT = 3600;

function require_login(): void {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    // Expiración por inactividad
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function require_login_api(): void {
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(401);
        die(json_encode(['error' => 'No autorizado']));
    }
}

// Genera (o reutiliza) el token CSRF de la sesión
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Devuelve un campo oculto listo para insertar en formularios
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

// Compara el token enviado con el de la sesión de forma segura contra timing attacks
function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}