<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$res = $db->query("SELECT id, name FROM products WHERE name = 'Copilot Import Test'");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $var_res = $db->query("SELECT COUNT(*) as cnt FROM product_varieties WHERE product_id = " . (int)$row['id']);
    $var_count = $var_res ? $var_res->fetch_assoc()['cnt'] : 0;
    echo "product_id={$row['id']}\nvariety_count={$var_count}\n";
} else {
    echo "product_not_found\n";
}
$db->close();
