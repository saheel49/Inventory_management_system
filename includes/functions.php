<?php
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        // Debug: log denied access
        try {
            $nf = __DIR__ . '/../backups/nav_debug.log';
            $sid = session_id() ?: '-';
            $cookie = $_COOKIE[session_name()] ?? '-';
            $data = date('Y-m-d H:i:s') . "\tREQUIRE_LOGIN\t" . ($_SERVER['REQUEST_METHOD']??'-') . "\t" . ($_SERVER['REQUEST_URI']??'-') . "\tno-session\t" . ($cookie) . "\t" . $sid . "\t" . ($_SERVER['HTTP_REFERER']??'-') . "\n";
            @file_put_contents($nf, $data, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {}
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function get_setting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        $db = getDB();
        $result = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $db->close();
    }
    return $settings[$key] ?? $default;
}

function log_activity($action, $description = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt->bind_param('isss', $user_id, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
    $db->close();
}

function generate_backup_filename() {
    return 'backup_' . date('Ymd_His') . '.sql';
}

function time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    return date('Y-m-d', $time);
}

function format_date($date) {
    $format = get_setting('date_format', 'Y-m-d');
    $d = is_string($date) ? strtotime($date) : $date;
    return date($format, $d);
}

function currency_format($amount) {
    $symbol = get_setting('currency_symbol', '$');
    return $symbol . number_format($amount, 2);
}

