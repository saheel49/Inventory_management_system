<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();

$vid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$vid){ header('Location: index.php'); exit; }

$db = getDB();
$vs = $db->prepare("SELECT v.*,p.name pname,p.id pid,p.unit,p.category FROM product_varieties v JOIN products p ON v.product_id=p.id WHERE v.id=?");
$vs->bind_param('i',$vid); $vs->execute();
$variety = $vs->get_result()->fetch_assoc(); $vs->close();
if(!$variety){ $db->close(); header('Location: index.php'); exit; }

$standard_units = [
  'pcs'=>'Pieces','kg'=>'Kilograms','gram'=>'Grams','liter'=>'Liters','meter'=>'Meters','yard'=>'Yards',
  'bag'=>'Bags','box'=>'Boxes','roll'=>'Rolls','set'=>'Sets','pair'=>'Pairs','dozen'=>'Dozen'
];

$errors = []; $success = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? 'update';

    if($action === 'delete'){
        $dd = $db->prepare("DELETE FROM product_varieties WHERE id=?");
        $dd->bind_param('i',$vid); $dd->execute(); $dd->close();
        log_activity('variety_delete','Deleted variety: '.$variety['name'].' from '.$variety['pname']);
        $_SESSION['success'] = 'Variety deleted successfully.';
        $db->close();
        header('Location: index.php?action=edit&id='.$variety['pid']); exit;
    }

    $name            = trim($_POST['name'] ?? '');
    $unit_price      = (float)($_POST['unit_price'] ?? 0);
    $product_name    = trim($_POST['product_name'] ?? '');
    $product_category= trim($_POST['product_category'] ?? '');
    $product_unit    = trim($_POST['product_unit'] ?? 'pcs');
    $product_unit_custom = trim($_POST['product_unit_custom'] ?? '');
    $adj_type        = $_POST['adj_type'] ?? 'none';
    $adj_qty         = (float)($_POST['adj_qty'] ?? 0);
    $adj_note        = trim($_POST['adj_note'] ?? '');

    if(!$name) $errors[] = 'Variety name is required.';
    if(!$product_name) $errors[] = 'Product name is required.';
    else {
        $dup = $db->prepare("SELECT COUNT(*) c FROM product_varieties WHERE LOWER(name)=LOWER(?) AND product_id=? AND id!=?");
        $dup->bind_param('sii',$name,$variety['pid'],$vid); $dup->execute();
        if($dup->get_result()->fetch_assoc()['c'] > 0) $errors[] = 'A variety with that name already exists in this product.';
        $dup->close();
    }

    if(empty($errors)){
        // Update variety details
        $up = $db->prepare("UPDATE product_varieties SET name=?, unit_price=? WHERE id=?");
        $up->bind_param('sdi',$name,$unit_price,$vid); $up->execute(); $up->close();

        // Update product reference data when editing from variety page
        $product_unit_value = $product_unit_custom !== '' ? $product_unit_custom : $product_unit;
        $pu = $db->prepare("UPDATE products SET name=?, category=?, unit=? WHERE id=?");
        $pu->bind_param('sssi',$product_name,$product_category,$product_unit_value,$variety['pid']);
        $pu->execute(); $pu->close();

        // Stock adjustment
        if($adj_type !== 'none' && $adj_qty > 0){
            $si = $adj_type === 'add' ? $adj_qty : 0;
            $so = $adj_type === 'sub' ? $adj_qty : 0;
            $uid = $_SESSION['user_id'] ?? null;
            $today = date('Y-m-d');
            $note = $adj_note ?: ($adj_type==='add' ? 'Manual stock addition' : 'Manual stock deduction');

            $ls = $db->prepare("INSERT INTO ledger_transactions (variety_id,transaction_date,customer,stock_in,stock_out,remarks,created_by) VALUES (?,?,?,?,?,?,?)");
            $label = $adj_type==='add' ? 'Stock Adjustment IN' : 'Stock Adjustment OUT';
            $ls->bind_param('issddsi',$vid,$today,$label,$si,$so,$note,$uid); $ls->execute(); $ls->close();

            $us = $db->prepare("UPDATE product_varieties SET current_stock=current_stock+?-? WHERE id=?");
            $us->bind_param('ddi',$si,$so,$vid); $us->execute(); $us->close();

            log_activity('stock_adjust','Adjusted stock for variety: '.$name.' ('.($adj_type==='add'?'+':'-').$adj_qty.')');
        }

        log_activity('variety_edit','Edited variety: '.$name);
        $_SESSION['success'] = 'Variety updated successfully.';
        $db->close();
        // Reload to show fresh data
        header('Location: variety_edit.php?id='.$vid); exit;
    }
}

// Reload variety after possible update
$vs2 = $db->prepare("SELECT v.*,p.name pname,p.id pid,p.unit,p.category FROM product_varieties v JOIN products p ON v.product_id=p.id WHERE v.id=?");
$vs2->bind_param('i',$vid); $vs2->execute();
$variety = $vs2->get_result()->fetch_assoc(); $vs2->close();

// Recent ledger history for this variety
$hist = $db->query("SELECT * FROM ledger_transactions WHERE variety_id=$vid ORDER BY transaction_date DESC, id DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$db->close();

require __DIR__.'/../includes/header.php';
?>
<div class="main-content">

<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-layer-group"></i> Edit Variety</h1>
    <div class="breadcrumb">
      <a href="index.php">Products</a><span class="sep">/</span>
      <a href="index.php?action=edit&id=<?php echo $variety['pid'];?>"><?php echo htmlspecialchars($variety['pname']);?></a><span class="sep">/</span>
      <span><?php echo htmlspecialchars($variety['name']);?></span>
    </div>
  </div>
  <div class="header-actions">
    <a href="index.php?action=edit&id=<?php echo $variety['pid'];?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Product</a>
    <a href="<?php echo APP_URL;?>/ledger/index.php?variety_id=<?php echo $vid;?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-book-open"></i> Full Ledger</a>
  </div>
</div>

<!-- Current Stock Banner -->
<div class="info-band" style="margin-bottom:20px;">
  <div class="ib-item"><div class="ib-label">Product</div><div class="ib-value"><?php echo htmlspecialchars($variety['pname']);?></div></div>
  <div class="ib-item"><div class="ib-label">Variety</div><div class="ib-value"><?php echo htmlspecialchars($variety['name']);?></div></div>
  <div class="ib-item"><div class="ib-label">Current Stock</div><div class="ib-value big"><?php echo number_format($variety['current_stock'],3);?></div></div>
  <div class="ib-item"><div class="ib-label">Unit Price</div><div class="ib-value"><?php echo number_format($variety['unit_price']??0,2);?></div></div>
</div>

<?php if(!empty($errors)): ?>
<div class="alert alert-danger"><i class="fa-solid fa-circle-xmark"></i><div><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><button class="alert-close"><i class="fa-solid fa-xmark"></i></button></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="ve-layout">

  <!-- Edit Form -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-pen"></i> Update Variety Details</div></div>
    <form method="POST">
      <input type="hidden" name="action" value="update">

      <div class="section-title">General Information</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Variety Name <span class="req">*</span></label>
          <div class="input-wrap"><i class="fi fa-solid fa-tag"></i>
          <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($variety['name']);?>"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Unit Price</label>
          <div class="input-wrap"><i class="fi fa-solid fa-dollar-sign"></i>
          <input type="number" name="unit_price" class="form-control" step="0.01" min="0" value="<?php echo number_format($variety['unit_price']??0,2,'.','');?>"></div>
          <p class="form-hint">Used to calculate transaction values.</p>
        </div>
      </div>

      <div class="section-title">Product Reference</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Product Name <span class="req">*</span></label>
          <input type="text" name="product_name" class="form-control" required value="<?php echo htmlspecialchars($variety['pname']);?>">
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;">
            <select name="product_unit" class="form-control">
              <?php foreach($standard_units as $k=>$label): ?>
              <option value="<?php echo $k; ?>" <?php echo ($variety['unit'] === $k ? 'selected' : ''); ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="product_unit_custom" class="form-control" placeholder="Custom unit" value="<?php echo in_array($variety['unit'], array_keys($standard_units)) ? '' : htmlspecialchars($variety['unit']); ?>">
          </div>
          <p class="form-hint">Choose a standard unit or enter a custom unit like cartons, sheets, or tubes.</p>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Category</label>
          <input type="text" name="product_category" class="form-control" value="<?php echo htmlspecialchars($variety['category'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Current Stock</label>
          <input type="text" class="form-control" disabled value="<?php echo number_format($variety['current_stock'],3);?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Created</label>
          <input type="text" class="form-control" disabled value="<?php echo htmlspecialchars($variety['created_at'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Last Updated</label>
          <input type="text" class="form-control" disabled value="<?php echo htmlspecialchars($variety['updated_at'] ?? ''); ?>">
        </div>
      </div>

      <div class="section-title">Stock Adjustment</div>
      <div style="border-top:1px solid var(--border);padding-top:16px;margin-top:4px;">
        <label class="form-label">Adjustment Type</label>
        <div style="display:flex;gap:10px;margin-bottom:12px;">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500;color:var(--text-1);">
            <input type="radio" name="adj_type" value="none" checked onchange="toggleAdj(this.value)"> No Adjustment
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500;color:var(--success);">
            <input type="radio" name="adj_type" value="add" onchange="toggleAdj(this.value)"> <i class="fa-solid fa-plus"></i> Add Stock
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500;color:var(--danger);">
            <input type="radio" name="adj_type" value="sub" onchange="toggleAdj(this.value)"> <i class="fa-solid fa-minus"></i> Remove Stock
          </label>
        </div>
        <div id="adjFields" style="display:none;">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Adjustment Quantity</label>
              <input type="number" name="adj_qty" id="adjQty" class="form-control" step="0.001" min="0" value="0">
              <p class="form-hint" id="adjPreview" style="color:var(--success);">New stock will be: <?php echo number_format($variety['current_stock'],3);?></p>
            </div>
            <div class="form-group">
              <label class="form-label">Reason / Note</label>
              <input type="text" name="adj_note" class="form-control" placeholder="Reason for adjustment">
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
      </div>
    </form>

    <!-- Delete -->
    <div style="border-top:1px solid var(--border);margin-top:20px;padding-top:16px;">
      <p style="font-size:12px;color:var(--text-3);margin-bottom:10px;">
        <i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);margin-right:4px;"></i>
        Deleting this variety will remove all its ledger history permanently.
      </p>
      <form method="POST" onsubmit="return confirmDelete2();">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> Delete This Variety</button>
      </form>
    </div>
  </div>

  <!-- Recent History -->
  <div class="table-card" style="margin-bottom:0;">
    <div class="table-toolbar">
      <div class="table-toolbar-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Transactions</div>
      <div class="table-toolbar-actions">
        <a href="<?php echo APP_URL;?>/ledger/index.php?variety_id=<?php echo $vid;?>" class="btn btn-secondary btn-sm">View All</a>
      </div>
    </div>
    <div class="tbl-scroll">
      <table>
        <thead><tr><th>Date</th><th>Supplier/Customer</th><th class="number-col">IN</th><th class="number-col">OUT</th><th>Note</th></tr></thead>
        <tbody>
          <?php if(empty($hist)): ?>
          <tr class="empty-row"><td colspan="5">No transactions yet.</td></tr>
          <?php else: foreach($hist as $h): ?>
          <tr>
            <td style="font-size:12px;white-space:nowrap;"><?php echo htmlspecialchars($h['transaction_date']);?></td>
            <td style="font-size:12px;"><?php echo htmlspecialchars($h['customer'])?:'-';?></td>
            <td class="number-col" style="color:var(--success);"><?php echo $h['stock_in']>0?number_format($h['stock_in'],3):'-';?></td>
            <td class="number-col" style="color:var(--danger);"><?php echo $h['stock_out']>0?number_format($h['stock_out'],3):'-';?></td>
            <td style="font-size:11px;color:var(--text-3);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($h['remarks'])?:'-';?></td>
          </tr>
          <?php endforeach; endif;?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<style>@media(max-width:860px){.ve-layout{grid-template-columns:1fr!important;}}</style>

</div>
<script>
var curStock = <?php echo (float)$variety['current_stock'];?>;
var adjType  = 'none';
function toggleAdj(v){
  adjType = v;
  document.getElementById('adjFields').style.display = (v==='none') ? 'none' : 'block';
  updatePreview();
}
function updatePreview(){
  var qty = parseFloat(document.getElementById('adjQty').value)||0;
  var ns  = adjType==='add' ? curStock+qty : curStock-qty;
  var el  = document.getElementById('adjPreview');
  el.textContent = 'New stock will be: '+ns.toFixed(3);
  el.style.color = adjType==='add' ? 'var(--success)' : (ns<0?'var(--danger)':'var(--warning)');
}
document.getElementById('adjQty')?.addEventListener('input',updatePreview);
function confirmDelete2(){
  return confirm('Delete variety "<?php echo addslashes(htmlspecialchars($variety['name']));?>"?\nThis removes all ledger history for this variety. Cannot be undone.');
}
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
