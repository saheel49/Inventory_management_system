<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$sample = ['Paint','Cement','Steel Rod','Electrical Wire','PVC Pipe'];
$db = getDB();

if (isset($_POST['confirm']) && $_POST['confirm'] === '1') {
    $deleted = 0;
    $stmt = $db->prepare("DELETE FROM products WHERE name = ?");
    foreach ($sample as $name) {
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $deleted += $stmt->affected_rows;
    }
    $stmt->close();
    $db->close();
    $message = "Deleted products matching sample names (and related varieties/transactions). Rows affected: $deleted";
} else {
    $message = '';
}
require __DIR__ . '/../includes/header.php';
?>
<div class="main-content">
    <div class="page-header"><h1>Remove Sample Products</h1></div>
    <div class="card">
        <p>This will permanently delete the following products and all related varieties and transactions (foreign keys cascade):</p>
        <ul>
            <?php foreach ($sample as $s) echo '<li>' . htmlspecialchars($s) . '</li>'; ?>
        </ul>
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="confirm" value="1">
            <div class="form-actions">
                <button class="btn btn-danger">Confirm and Delete</button>
                <a class="btn btn-secondary" href="<?php echo APP_URL; ?>/tools/import_products.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php';
