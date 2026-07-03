<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

function detect_csv_delimiter($content) {
    if (substr_count($content, ';') > substr_count($content, ',')) return ';';
    if (substr_count($content, "\t") > substr_count($content, ',')) return "\t";
    return ',';
}

function normalize_csv_headers(array $headers) {
    $map = [];
    foreach ($headers as $i => $h) {
        $normalized = strtolower(trim($h));
        $normalized = preg_replace('/[^\w]+/', '_', $normalized);
        $normalized = trim($normalized, '_');
        $map[$normalized] = $i;
    }
    return $map;
}

function normalize_csv_value($value) {
    return trim(preg_replace('/\s+/', ' ', $value));
}

function parse_csv_rows($tmp, &$error) {
    $firstChunk = file_get_contents($tmp, false, null, 0, 4096);
    $detectedDelimiter = detect_csv_delimiter($firstChunk);
    $handle = fopen($tmp, 'r');
    if ($handle === false) {
        $error = 'Unable to open uploaded file.';
        return false;
    }
    $headers = fgetcsv($handle, 0, $detectedDelimiter);
    if ($headers && isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    }
    if ($headers === false) {
        $error = 'Empty CSV.';
        fclose($handle);
        return false;
    }
    $map = normalize_csv_headers($headers);
    if (!isset($map['product']) && !isset($map['product_name']) && !isset($map['name']) && !isset($map['item']) && !isset($map['item_name'])) {
        $error = 'CSV must include a Product column.';
        fclose($handle);
        return false;
    }
    if (!isset($map['variety']) && !isset($map['variant']) && !isset($map['variation']) && !isset($map['variation_name']) && !isset($map['variant_name']) && !isset($map['item_variant']) && !isset($map['item_variation'])) {
        $error = 'CSV must include a Variety column.';
        fclose($handle);
        return false;
    }
    $rows = [];
    while (($data = fgetcsv($handle, 0, $detectedDelimiter)) !== false) {
        $pname = '';
        foreach (['product', 'product_name', 'name', 'item', 'item_name'] as $key) {
            if (isset($map[$key])) {
                $pname = normalize_csv_value($data[$map[$key]] ?? '');
                break;
            }
        }
        if ($pname === '') {
            continue;
        }
        $category = '';
        foreach (['category', 'type', 'product_type', 'item_type', 'product_category', 'item_category'] as $key) {
            if (isset($map[$key])) {
                $category = normalize_csv_value($data[$map[$key]] ?? '');
                break;
            }
        }
        $unit = 'pcs';
        foreach (['unit', 'uom', 'measurement'] as $key) {
            if (isset($map[$key])) {
                $unit = normalize_csv_value($data[$map[$key]] ?? 'pcs');
                break;
            }
        }
        $variety = '';
        foreach (['variety', 'variant', 'variation', 'variation_name', 'variant_name', 'item_variant', 'item_variation'] as $key) {
            if (isset($map[$key])) {
                $variety = normalize_csv_value($data[$map[$key]] ?? '');
                break;
            }
        }
        if ($variety === '') {
            continue;
        }
        $stock = '';
        foreach (['stock', 'quantity', 'qty', 'current_stock', 'stock_level', 'available', 'inventory'] as $key) {
            if (isset($map[$key])) {
                $stock = normalize_csv_value($data[$map[$key]] ?? '');
                break;
            }
        }
        $stock = is_numeric($stock) ? (float)$stock : (is_numeric(str_replace(',', '', $stock)) ? (float)str_replace(',', '', $stock) : 0.0);
        $rows[] = [
            'product' => $pname,
            'category' => $category,
            'unit' => $unit ?: 'pcs',
            'variety' => $variety,
            'stock' => $stock,
        ];
    }
    fclose($handle);
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid CSV file.';
    } else {
        $tmp = $_FILES['csv_file']['tmp_name'];
        $rows = parse_csv_rows($tmp, $error);
        if ($rows !== false) {
            if (count($rows) === 0) {
                $error = 'No valid rows found in the CSV. Check header names and data formatting.';
            } else {
                $db = getDB();
                if (isset($_POST['delete_csv'])) {
                    $deletedProducts = 0;
                    $deletedVarieties = 0;
                    $deletedProductNames = [];
                    $deletedVarietyNames = [];
                    $seenProducts = [];
                    $seenVarieties = [];

                    foreach ($rows as $row) {
                        $pname = $row['product'];
                        $variety = $row['variety'];

                        if ($variety === '') {
                            if (isset($seenProducts[$pname])) {
                                continue;
                            }
                            $seenProducts[$pname] = true;
                            $del = $db->prepare("DELETE FROM products WHERE name = ?");
                            $del->bind_param('s', $pname);
                            $del->execute();
                            if ($del->affected_rows > 0) {
                                $deletedProducts += $del->affected_rows;
                                $deletedProductNames[] = $pname;
                            }
                            $del->close();
                            continue;
                        }

                        $varKey = $pname . '||' . $variety;
                        if (isset($seenVarieties[$varKey])) {
                            continue;
                        }
                        $seenVarieties[$varKey] = true;

                        $stmt = $db->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
                        $stmt->bind_param('s', $pname);
                        $stmt->execute();
                        $res = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if (!$res) {
                            continue;
                        }

                        $product_id = $res['id'];
                        $delVar = $db->prepare("DELETE FROM product_varieties WHERE product_id = ? AND name = ?");
                        $delVar->bind_param('is', $product_id, $variety);
                        $delVar->execute();
                        if ($delVar->affected_rows > 0) {
                            $deletedVarieties += $delVar->affected_rows;
                            $deletedVarietyNames[] = $pname . ' -> ' . $variety;
                        }
                        $delVar->close();

                        $countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM product_varieties WHERE product_id = ?");
                        $countStmt->bind_param('i', $product_id);
                        $countStmt->execute();
                        $remaining = $countStmt->get_result()->fetch_assoc()['cnt'];
                        $countStmt->close();
                        if ($remaining == 0) {
                            $delProd = $db->prepare("DELETE FROM products WHERE id = ?");
                            $delProd->bind_param('i', $product_id);
                            $delProd->execute();
                            if ($delProd->affected_rows > 0) {
                                $deletedProducts += $delProd->affected_rows;
                                $deletedProductNames[] = $pname;
                            }
                            $delProd->close();
                        }
                    }

                    $db->close();
                    $success = "Deleted $deletedVarieties imported varieties and $deletedProducts imported products.";
                    if ($deletedProductNames) {
                        $success .= "\nDeleted products: " . implode(', ', array_slice(array_unique($deletedProductNames), 0, 20));
                        if (count(array_unique($deletedProductNames)) > 20) {
                            $success .= ' + more';
                        }
                    }
                    if ($deletedVarietyNames) {
                        $success .= "\nDeleted varieties: " . implode(', ', array_slice(array_unique($deletedVarietyNames), 0, 20));
                        if (count(array_unique($deletedVarietyNames)) > 20) {
                            $success .= ' + more';
                        }
                    }
                } else {
                    $createdProducts = 0;
                    $createdVarieties = 0;
                    $updatedVarieties = 0;
                    $createdProductNames = [];
                    $createdVarietyNames = [];

                    foreach ($rows as $row) {
                        $pname = $row['product'];
                        $category = $row['category'];
                        $unit = $row['unit'];
                        $variety = $row['variety'];
                        $stock = $row['stock'];

                        $stmt = $db->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
                        $stmt->bind_param('s', $pname);
                        $stmt->execute();
                        $res = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($res) {
                            $product_id = $res['id'];
                        } else {
                            $ins = $db->prepare("INSERT INTO products (name, description, category, unit, created_by) VALUES (?, '', ?, ?, ?)");
                            $user_id = $_SESSION['user_id'] ?? null;
                            $ins->bind_param('sssi', $pname, $category, $unit, $user_id);
                            $ins->execute();
                            $product_id = $db->insert_id;
                            $ins->close();
                            $createdProducts++;
                            $createdProductNames[] = $pname;
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
                                $updatedVarieties++;
                            } else {
                                $iv = $db->prepare("INSERT INTO product_varieties (product_id, name, current_stock) VALUES (?, ?, ?)");
                                $iv->bind_param('isd', $product_id, $variety, $stock);
                                $iv->execute();
                                $iv->close();
                                $createdVarieties++;
                                $createdVarietyNames[] = $pname . ' -> ' . $variety;
                            }
                        }
                    }

                    $db->close();
                    $success = "Processed rows: " . count($rows) . ". Products created: $createdProducts. Varieties created: $createdVarieties. Varieties updated: $updatedVarieties.";
                    if ($createdProductNames) {
                        $success .= "\nCreated products: " . implode(', ', array_slice($createdProductNames, 0, 20));
                        if (count($createdProductNames) > 20) {
                            $success .= ' + more';
                        }
                    }
                    if ($createdVarietyNames) {
                        $success .= "\nCreated varieties (sample): " . implode(', ', array_slice($createdVarietyNames, 0, 20));
                        if (count($createdVarietyNames) > 20) {
                            $success .= ' + more';
                        }
                    }
                }
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="main-content">
    <div class="page-header"><h1>Import Products / Varieties (CSV)</h1></div>
    <div class="card">
        <p>Upload a CSV with headers: <strong>Product, Category, Unit, Variety, Stock</strong>. You can export your Excel file as CSV first.</p>
        <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo nl2br(htmlspecialchars($success)); ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <input type="file" name="csv_file" accept="text/csv,.csv" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Import CSV</button>
                <button type="submit" name="delete_csv" value="1" class="btn btn-danger">Delete Imported CSV Rows</button>
                <a class="btn btn-secondary" href="<?php echo APP_URL; ?>/products/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php';
