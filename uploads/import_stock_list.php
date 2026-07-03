<?php
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$parentProducts = [
    'Ahram Security Locks',
    'Aluminium Vacuum Bottles and Straws',
    'Auction Items',
    'Glass Ware',
    'Max Plast Plastic Products',
    'Parita Products',
    'Vitaplus',
    'Water Bottles',
];

function parse_quantity_value($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/\s+/', ' ', $value);
    if (preg_match('/([-+]?\d+(?:[.,]\d+)?)/', $value, $matches)) {
        return (float) str_replace(',', '', $matches[1]);
    }

    return null;
}

function normalize_unit($value) {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return 'pcs';
    }

    if (preg_match('/\b(sets?|pcs?|pieces|units?|ctns?|bags?|bales?|kgs?|mtrs?|yards?|yard)\b/', $value, $matches)) {
        $unit = $matches[1];
        if (strpos($unit, 'set') === 0) {
            return 'sets';
        }
        if ($unit === 'kg' || $unit === 'kgs') {
            return 'kgs';
        }
        if ($unit === 'mtr' || $unit === 'mtrs') {
            return 'mtrs';
        }
        if ($unit === 'yard' || $unit === 'yards') {
            return 'yards';
        }
        if ($unit === 'ctn' || $unit === 'ctns') {
            return 'ctns';
        }
        if ($unit === 'bag' || $unit === 'bags') {
            return 'bags';
        }
        if ($unit === 'bale' || $unit === 'bales') {
            return 'bales';
        }
        return 'pcs';
    }

    return 'pcs';
}

function is_metadata_row($name) {
    $name = trim((string) $name);
    $patterns = [
        '^D CHANDULAL', '^P\.O BOX', '^PIN NO\.', '^VAT NO\.', '^E-Mail', '^Main Location',
        '^Location Summary', '^1-Jun-26', '^Particulars', '^Closing Balance', '^Quantity', '^,$'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match('/' . $pattern . '/i', $name)) {
            return true;
        }
    }

    return false;
}

$db->query('DELETE FROM ledger_transactions');
$db->query('DELETE FROM customer_transactions');
$db->query('DELETE FROM supplier_transactions');
$db->query('DELETE FROM product_varieties');
$db->query('DELETE FROM products');

$source = __DIR__ . '/../tools/excel_list.csv';
$handle = fopen($source, 'r');
if ($handle === false) {
    echo "Unable to open source file\n";
    exit(1);
}

$createdProducts = 0;
$createdVarieties = 0;
$updatedVarieties = 0;
$skipped = 0;
$currentProductId = null;
$currentProductName = null;

while (($row = fgetcsv($handle)) !== false) {
    $cells = array_values(array_filter($row, function ($cell) {
        return trim((string) $cell) !== '';
    }));
    if (empty($cells)) {
        continue;
    }

    $name = trim((string) ($cells[0] ?? ''));
    $quantityRaw = trim((string) ($cells[1] ?? ''));
    $quantityValue = parse_quantity_value($quantityRaw);
    $unit = $quantityValue !== null ? normalize_unit($quantityRaw) : 'pcs';

    if ($name === '' || is_metadata_row($name)) {
        $skipped++;
        continue;
    }

    if (in_array($name, $parentProducts, true)) {
        $stmt = $db->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
            $currentProductId = $res['id'];
        } else {
            $insertProduct = $db->prepare('INSERT INTO products (name, description, category, unit, created_by) VALUES (?, \'\', \'\', ?, NULL)');
            $insertProduct->bind_param('ss', $name, $unit);
            $insertProduct->execute();
            $currentProductId = $db->insert_id;
            $insertProduct->close();
            $createdProducts++;
        }

        $currentProductName = $name;
        continue;
    }

    if ($currentProductId === null) {
        $skipped++;
        continue;
    }

    $varietyName = $name;
    $varietyStmt = $db->prepare('SELECT id FROM product_varieties WHERE product_id = ? AND name = ? LIMIT 1');
    $varietyStmt->bind_param('is', $currentProductId, $varietyName);
    $varietyStmt->execute();
    $varietyRes = $varietyStmt->get_result()->fetch_assoc();
    $varietyStmt->close();

    if ($varietyRes) {
        $updateVariety = $db->prepare('UPDATE product_varieties SET current_stock = ?, unit_price = 0 WHERE id = ?');
        $updateVariety->bind_param('di', $quantityValue, $varietyId);
        $varietyId = $varietyRes['id'];
        $updateVariety->execute();
        $updateVariety->close();
        $updatedVarieties++;
    } else {
        $insertVariety = $db->prepare('INSERT INTO product_varieties (product_id, name, current_stock, unit_price) VALUES (?, ?, ?, 0)');
        $insertVariety->bind_param('isd', $currentProductId, $varietyName, $quantityValue);
        $insertVariety->execute();
        $insertVariety->close();
        $createdVarieties++;
    }
}

fclose($handle);
$db->close();

echo "Imported products: $createdProducts\n";
echo "Imported varieties: $createdVarieties\n";
echo "Updated varieties: $updatedVarieties\n";
echo "Skipped rows: $skipped\n";
