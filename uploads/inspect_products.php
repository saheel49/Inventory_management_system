<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$res = $db->query('SELECT name FROM products ORDER BY id');
while ($row = $res->fetch_assoc()) {
    echo $row['name'] . PHP_EOL;
}
$db->close();
