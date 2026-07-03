<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmt = $db->prepare('SELECT p.name, pv.name AS variety_name, pv.current_stock FROM products p JOIN product_varieties pv ON p.id = pv.product_id WHERE p.name = ? LIMIT 1');
$name = 'Ahram Security Locks';
$stmt->bind_param('s', $name);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();
echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
