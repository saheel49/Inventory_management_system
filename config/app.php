<?php
define('APP_NAME', 'Inventory Ledger System');
if (!defined('APP_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', $scheme . '://' . $host . '/inventory_system');
}
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SESSION_TIMEOUT', 0);
define('PASSWORD_HISTORY_LIMIT', 10);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('BACKUP_DIR', __DIR__ . '/../backups/');

if (session_status() === PHP_SESSION_NONE) {
    session_name('inventory_system_session');
    ini_set('session.gc_maxlifetime', 0);
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', '/');
    session_start();
}


