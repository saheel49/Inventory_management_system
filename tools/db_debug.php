<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$p = $db->query("SELECT COUNT(*) as cnt FROM products")->fetch_assoc();
$v = $db->query("SELECT COUNT(*) as cnt FROM product_varieties")->fetch_assoc();
$recentP = $db->query("SELECT id, name FROM products ORDER BY id DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$recentV = $db->query("SELECT id, product_id, name, current_stock FROM product_varieties ORDER BY id DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$db->close();
echo "Products: " . ($p['cnt'] ?? 0) . PHP_EOL;
echo "Varieties: " . ($v['cnt'] ?? 0) . PHP_EOL;
echo "Recent products:\n";
foreach ($recentP as $r) echo $r['id'] . ' - ' . $r['name'] . PHP_EOL;
echo "Recent varieties:\n";
foreach ($recentV as $r) echo $r['id'] . ' - pid:' . $r['product_id'] . ' - ' . $r['name'] . ' (' . $r['current_stock'] . ')\n';
