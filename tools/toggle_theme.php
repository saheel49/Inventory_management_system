<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method']); exit; }
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Not logged in']); exit; }
$body = json_decode(file_get_contents('php://input'), true);
$theme = $body['theme'] ?? '';
$dark = $theme === 'dark' ? 1 : 0;
$db = getDB();
$stmt = $db->prepare("UPDATE users SET dark_mode = ? WHERE id = ?");
$stmt->bind_param('ii', $dark, $_SESSION['user_id']);
$stmt->execute();
$stmt->close();
$db->close();
$_SESSION['dark_mode'] = $dark;
echo json_encode(['ok'=>true, 'dark_mode'=>$dark]);
