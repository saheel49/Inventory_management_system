<?php
if ($argc < 2) {
    echo "Usage: php import_products_cli.php path/to/file.csv\n";
    exit(1);
}
$path = $argv[1];
if (!file_exists($path)) {
    echo "File not found: $path\n";
    exit(1);
}
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$firstChunk = file_get_contents($path, false, null, 0, 4096);
$detectedDelimiter = ',';
if (substr_count($firstChunk, ';') > substr_count($firstChunk, ',')) $detectedDelimiter = ';';
elseif (substr_count($firstChunk, "\t") > substr_count($firstChunk, ',')) $detectedDelimiter = "\t";
$handle = fopen($path, 'r');
$headers = fgetcsv($handle, 0, $detectedDelimiter);
if ($headers && isset($headers[0])) $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
$map = [];
foreach ($headers as $i => $h) {
    $h = strtolower(trim(preg_replace('/\s+/', ' ', $h)));
    $h = preg_replace('/[^a-z0-9_ ]/', '', $h);
    $map[$h] = $i;
}
$rows = $createdP = $createdV = $updatedV = 0;
$createdNames = [];
$createdVarieties = [];
while (($data = fgetcsv($handle, 0, $detectedDelimiter)) !== false) {
    $rows++;
    $pname = '';
    if (isset($map['product'])) $pname = trim($data[$map['product']] ?? '');
    elseif (isset($map['name'])) $pname = trim($data[$map['name']] ?? '');
    elseif (isset($map['item'])) $pname = trim($data[$map['item']] ?? '');
    if ($pname === '') continue;
    $category = isset($map['category']) ? trim($data[$map['category']] ?? '') : '';
    $unit = isset($map['unit']) ? trim($data[$map['unit']] ?? 'pcs') : 'pcs';
    if (isset($map['variety'])) $variety = trim($data[$map['variety']] ?? '');
    elseif (isset($map['variant'])) $variety = trim($data[$map['variant']] ?? '');
    elseif (isset($map['variation'])) $variety = trim($data[$map['variation']] ?? '');
    else $variety = '';
    $stock = 0;
    if (isset($map['stock'])) $stock = $data[$map['stock']];
    elseif (isset($map['quantity'])) $stock = $data[$map['quantity']];
    elseif (isset($map['current_stock'])) $stock = $data[$map['current_stock']];
    elseif (isset($map['qty'])) $stock = $data[$map['qty']];
    $stock = is_numeric($stock) ? (float)$stock : (is_numeric(str_replace(',', '', $stock)) ? (float)str_replace(',', '', $stock) : 0.0);

    $stmt = $db->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $pname);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($res) {
        $product_id = $res['id'];
    } else {
        $ins = $db->prepare("INSERT INTO products (name, description, category, unit, created_by) VALUES (?, '', ?, ?, NULL)");
        $ins->bind_param('sss', $pname, $category, $unit);
        $ins->execute();
        $product_id = $db->insert_id;
        $ins->close();
        $createdP++;
        $createdNames[] = $pname;
    }

    if ($variety !== '') {
        $v_stmt = $db->prepare("SELECT id FROM product_varieties WHERE product_id = ? AND name = ? LIMIT 1");
        $v_stmt->bind_param('is', $product_id, $variety);
        $v_stmt->execute();
        $vres = $v_stmt->get_result()->fetch_assoc();
        $v_stmt->close();
        if ($vres) {
            $vid = $vres['id'];
            $u_stmt = $db->prepare("UPDATE product_varieties SET current_stock = ? WHERE id = ?");
            $u_stmt->bind_param('di', $stock, $vid);
            $u_stmt->execute();
            $u_stmt->close();
            $updatedV++;
        } else {
            $iv = $db->prepare("INSERT INTO product_varieties (product_id, name, current_stock) VALUES (?, ?, ?)");
            $iv->bind_param('isd', $product_id, $variety, $stock);
            $iv->execute();
            $iv->close();
            $createdV++;
            $createdVarieties[] = $pname . ' -> ' . $variety;
        }
    }
}
fclose($handle);
$db->close();
echo "Processed rows: $rows\n";
echo "Products created: $createdP\n";
echo "Varieties created: $createdV\n";
echo "Varieties updated: $updatedV\n";
if ($createdP) echo "Created products sample: " . implode(', ', array_slice($createdNames,0,20)) . "\n";
if ($createdV) echo "Created varieties sample: " . implode(', ', array_slice($createdVarieties,0,20)) . "\n";
