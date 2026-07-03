<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';

header('Content-Type: application/json');

$q    = trim($_GET['q'] ?? '');
$type = strtolower(trim($_GET['type'] ?? 'all'));

if (strlen($q) < 2) { echo json_encode([]); exit; }

$db      = getDB();
$results = [];
$term    = '%' . $q . '%';

/* ── CUSTOMERS ── */
if ($type === 'customer' || $type === 'all') {
    $st = $db->prepare("
        SELECT id, name, phone, email
        FROM customers
        WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ? OR notes LIKE ?
        ORDER BY name ASC LIMIT 12
    ");
    $st->bind_param('sssss', $term, $term, $term, $term, $term);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    foreach ($rows as $r) {
        $sub = trim(($r['phone'] ? $r['phone'] : '') . ($r['email'] ? ' · '.$r['email'] : ''));
        $results[] = [
            'name' => htmlspecialchars($r['name']),
            'type' => 'Customer',
            'icon' => '&#128100;',
            'sub'  => $sub,
            'url'  => APP_URL . '/customers/view.php?id=' . $r['id']
        ];
    }
}

/* ── SUPPLIERS ── */
if ($type === 'supplier' || $type === 'all') {
    $st = $db->prepare("
        SELECT id, name, phone, email
        FROM suppliers
        WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ? OR notes LIKE ?
        ORDER BY name ASC LIMIT 12
    ");
    $st->bind_param('sssss', $term, $term, $term, $term, $term);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    foreach ($rows as $r) {
        $sub = trim(($r['phone'] ? $r['phone'] : '') . ($r['email'] ? ' · '.$r['email'] : ''));
        $results[] = [
            'name' => htmlspecialchars($r['name']),
            'type' => 'Supplier',
            'icon' => '&#128188;',
            'sub'  => $sub,
            'url'  => APP_URL . '/suppliers/view.php?id=' . $r['id']
        ];
    }
}

/* ── PRODUCTS / VARIETIES ── */
if ($type === 'product' || $type === 'all') {
    $st = $db->prepare("
        SELECT p.id pid, p.name pname,
               v.id vid, v.name vname, v.current_stock
        FROM products p
        LEFT JOIN product_varieties v ON p.id = v.product_id
        WHERE p.name LIKE ? OR v.name LIKE ? OR p.category LIKE ?
        ORDER BY p.name ASC, v.name ASC
        LIMIT 20
    ");
    $st->bind_param('sss', $term, $term, $term);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    $seen = [];
    foreach ($rows as $r) {
        if ($r['vid']) {
            $key = 'v'.$r['vid'];
            if (isset($seen[$key])) continue;
            $seen[$key] = 1;
            $results[] = [
                'name' => htmlspecialchars($r['pname'] . ' – ' . $r['vname']),
                'type' => 'Variety',
                'icon' => '&#128230;',
                'sub'  => 'Stock: ' . number_format($r['current_stock'], 2),
                'url'  => APP_URL . '/ledger/index.php?variety_id=' . $r['vid']
            ];
        } else {
            $key = 'p'.$r['pid'];
            if (isset($seen[$key])) continue;
            $seen[$key] = 1;
            $results[] = [
                'name' => htmlspecialchars($r['pname']),
                'type' => 'Product',
                'icon' => '&#128230;',
                'sub'  => '',
                'url'  => APP_URL . '/products/index.php?action=view&id=' . $r['pid']
            ];
        }
    }
}

$db->close();
echo json_encode(array_values($results));
exit;
?>
