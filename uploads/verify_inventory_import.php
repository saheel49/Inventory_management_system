<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$products = $db->query('SELECT COUNT(*) as cnt FROM products');
$varieties = $db->query('SELECT COUNT(*) as cnt FROM product_varieties');
echo 'products=' . $products->fetch_assoc()['cnt'] . PHP_EOL;
echo 'varieties=' . $varieties->fetch_assoc()['cnt'] . PHP_EOL;
$db->close();
