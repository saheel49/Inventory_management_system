<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    log_activity('logout', 'User logged out');
}

session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
?>
