<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();
$action=$_GET['action']??'index';
if($action==='generate') doGenerate();
elseif($action==='export_csv') doCSV();
elseif($action==='export_excel') doExcel();
else doIndex();

function doIndex(){
  $db=getDB();
  $prods=$db->query("SELECT id,name FROM products ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
  $db->close();
  $sel=$_GET['type']??'daily'; $df=$_GET['date_from']??date('Y-m-01'); $dt=$_GET['date_to']??date('Y-m-d');
  $pid=isset($_GET['product_id'])?(int)$_GET['product_id']:0; $cu=trim($_GET['customer']??'');
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-chart-line"></i> Reports</h1>
    <div class="breadcrumb"><a href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span>Reports</span></div>
  </div>
  <div class="header-actions">
    <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-house"></i> Dashboard</a>
  </div>
</div>

<!-- Filter Card -->
<div class="card">
  <div class="card-header"><div class="card-title"><i class="fa-solid fa-filter"></i> Report Filters</div></div>
  <form method="GET" action="index.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    <input type="hidden" name="action" value="generate">
    <div style="min-width:160px;">
      <label class="form-label">Report Type</label>
      <select name="type" class="form-control">
        <?php foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','yearly'=>'Yearly','product'=>'Product Stock','variety'=>'Variety Stock','customer'=>'Customer/Supplier','stock_movement'=>'Stock Movement','current_stock'=>'Current Stock','low_stock'=>'Low Stock'] as $k=>$l): ?>
        <option value="<?php echo $k; ?>" <?php echo $sel===$k?'selected':''; ?>><?php echo $l; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:130px;">
      <label class="form-label">From</label>
      <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($df); ?>">
    </div>
    <div style="min-width:130px;">
      <label class="form-label">To</label>
      <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dt); ?>">
    </div>
    <div style="min-width:160px;">
      <label class="form-label">Product</label>
      <select name="product_id" class="form-control">
        <option value="0">All Products</option>
        <?php foreach($prods as $pr): ?><option value="<?php echo $pr['id']; ?>" <?php echo $pid===$pr['id']?'selected':''; ?>><?php echo htmlspecialchars($pr['name']); ?></option><?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:160px;">
      <label class="form-label">Supplier / Customer</label>
      <input type="text" name="customer" class="form-control" value="<?php echo htmlspecialchars($cu); ?>" placeholder="Search by name…">
    </div>
    <div style="display:flex;gap:8px;align-self:flex-end;">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-chart-bar"></i> Generate</button>
      <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-rotate"></i> Reset</a>
    </div>
  </form>
</div>

<!-- Report Type Cards -->
<div class="card">
  <div class="card-header"><div class="card-title"><i class="fa-solid fa-th"></i> Quick Report Types</div></div>
  <div class="report-types-grid">
    <?php $rtypes=[['daily','fa-calendar-day','Daily'],['weekly','fa-calendar-week','Weekly'],['monthly','fa-calendar','Monthly'],['yearly','fa-calendar-check','Yearly'],['product','fa-box-open','Products'],['variety','fa-layer-group','Varieties'],['customer','fa-users','Customers'],['stock_movement','fa-exchange-alt','Stock Movement'],['current_stock','fa-chart-pie','Current Stock'],['low_stock','fa-triangle-exclamation','Low Stock']];
    foreach($rtypes as [$k,$ic,$n]): ?>
    <a href="?action=generate&type=<?php echo $k; ?>" class="rtc">
      <div class="rtc-icon"><i class="fa-solid <?php echo $ic; ?>"></i></div>
      <div class="rtc-name"><?php echo $n; ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
</div>
<?php require __DIR__.'/../includes/footer.php';
}

function getReportData($type,$df,$dt,$pid,$cu){
  $db=getDB();
  if(in_array($type,['current_stock','low_stock','variety','product'])){
    $sql="SELECT p.name product_name,v.name variety_name,v.current_stock,p.unit FROM product_varieties v JOIN products p ON v.product_id=p.id";
    if($type==='low_stock') $sql.=" WHERE v.current_stock<=10";
    if($pid>0) $sql.=($type==='low_stock'?' AND':' WHERE')." p.id=$pid";
    $sql.=" ORDER BY p.name ASC,v.name ASC";
    $st=$db->prepare($sql);
  } else {
    $sql="SELECT l.*,v.name variety_name,p.name product_name,p.unit FROM ledger_transactions l JOIN product_varieties v ON l.variety_id=v.id JOIN products p ON v.product_id=p.id WHERE l.transaction_date BETWEEN ? AND ?";
    $bp=[$df,$dt]; $bt='ss';
    if($pid>0){ $sql.=" AND p.id=?"; $bp[]=$pid; $bt.='i'; }
    if($cu!=='') { $sql.=" AND l.customer LIKE ?"; $bp[]='%'.$cu.'%'; $bt.='s'; }
    $sql.=" ORDER BY l.transaction_date DESC,l.id DESC";
    $st=$db->prepare($sql); $st->bind_param($bt,...$bp);
  }
  $st->execute(); $data=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close(); $db->close();
  return $data;
}

function doGenerate(){
  $type=$_GET['type']??'daily'; $df=$_GET['date_from']??date('Y-m-01'); $dt=$_GET['date_to']??date('Y-m-d');
  $pid=isset($_GET['product_id'])?(int)$_GET['product_id']:0; $cu=trim($_GET['customer']??'');
  $data=getReportData($type,$df,$dt,$pid,$cu);
  $title=ucwords(str_replace('_',' ',$type)).' Report';
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-file-chart-column"></i> <?php echo htmlspecialchars($title); ?></h1>
    <div class="breadcrumb"><a href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a><span class="sep">/</span><a href="index.php">Reports</a><span class="sep">/</span><span><?php echo htmlspecialchars($title); ?></span></div>
  </div>
  <div class="header-actions">
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i> Print</button>
    <?php if(!empty($data)): ?>
    <a href="?action=export_csv&type=<?php echo urlencode($type); ?>&date_from=<?php echo urlencode($df); ?>&date_to=<?php echo urlencode($dt); ?>&product_id=<?php echo $pid; ?>&customer=<?php echo urlencode($cu); ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-csv"></i> CSV</a>
    <a href="?action=export_excel&type=<?php echo urlencode($type); ?>&date_from=<?php echo urlencode($df); ?>&date_to=<?php echo urlencode($dt); ?>&product_id=<?php echo $pid; ?>&customer=<?php echo urlencode($cu); ?>" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel"></i> Excel</a>
    <?php endif; ?>
  </div>
</div>
<div class="table-card">
  <div class="table-toolbar">
    <div class="table-toolbar-title"><i class="fa-solid fa-table"></i> <?php echo htmlspecialchars($title); ?></div>
    <div class="table-toolbar-actions"><span style="font-size:11px;color:var(--text-3);"><?php echo number_format(count($data)); ?> records</span></div>
  </div>
  <?php if(empty($data)): ?>
  <div class="empty-state"><div class="empty-state-icon"><i class="fa-solid fa-folder-open"></i></div><div class="empty-state-title">No Data Found</div><a href="index.php" class="btn btn-primary btn-sm">Back to Reports</a></div>
  <?php else: ?>
  <div class="tbl-scroll">
    <table>
      <thead><tr>
        <?php if(in_array($type,['current_stock','low_stock','variety','product'])): ?>
          <th>Product</th><th>Variety</th><th class="number-col">Stock</th><th>Unit</th>
        <?php else: ?>
          <th>Date</th><th>Product</th><th>Variety</th><th>Supplier/Customer</th><th>Invoice #</th><th>D.Note</th><th class="number-col">IN</th><th class="number-col">OUT</th><th>Remarks</th>
        <?php endif; ?>
      </tr></thead>
      <tbody>
      <?php foreach($data as $row): ?>
      <tr>
        <?php if(in_array($type,['current_stock','low_stock','variety','product'])): ?>
          <td><?php echo htmlspecialchars($row['product_name']); ?></td>
          <td><?php echo htmlspecialchars($row['variety_name']); ?></td>
          <td class="number-col <?php echo $row['current_stock']<=10?'text-danger font-bold':''; ?>"><?php echo number_format($row['current_stock'],3); ?></td>
          <td><?php echo htmlspecialchars($row['unit']); ?></td>
        <?php else: ?>
          <td style="white-space:nowrap;"><?php echo htmlspecialchars($row['transaction_date']); ?></td>
          <td><?php echo htmlspecialchars($row['product_name']); ?></td>
          <td><?php echo htmlspecialchars($row['variety_name']); ?></td>
          <td><?php echo htmlspecialchars($row['customer']??'-'); ?></td>
          <td><?php echo htmlspecialchars($row['invoice_number']??'-'); ?></td>
          <td><?php echo htmlspecialchars($row['delivery_note']??'-'); ?></td>
          <td class="number-col" style="color:var(--success);"><?php echo $row['stock_in']>0?number_format($row['stock_in'],3):'-'; ?></td>
          <td class="number-col" style="color:var(--danger);"><?php echo $row['stock_out']>0?number_format($row['stock_out'],3):'-'; ?></td>
          <td><?php echo htmlspecialchars($row['remarks']??'-'); ?></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</div>
<?php require __DIR__.'/../includes/footer.php';
}

function doCSV(){
  $type=$_GET['type']??'daily'; $df=$_GET['date_from']??date('Y-m-01'); $dt=$_GET['date_to']??date('Y-m-d');
  $pid=isset($_GET['product_id'])?(int)$_GET['product_id']:0; $cu=trim($_GET['customer']??'');
  $data=getReportData($type,$df,$dt,$pid,$cu);
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="report_'.$type.'_'.date('YmdHis').'.csv"');
  $f=fopen('php://output','w');
  if(!empty($data)){ fputcsv($f,array_keys($data[0])); foreach($data as $r) fputcsv($f,$r); }
  fclose($f); exit;
}
function doExcel(){
  $type=$_GET['type']??'daily'; $df=$_GET['date_from']??date('Y-m-01'); $dt=$_GET['date_to']??date('Y-m-d');
  $pid=isset($_GET['product_id'])?(int)$_GET['product_id']:0; $cu=trim($_GET['customer']??'');
  $data=getReportData($type,$df,$dt,$pid,$cu);
  header('Content-Type: application/vnd.ms-excel');
  header('Content-Disposition: attachment; filename="report_'.$type.'_'.date('YmdHis').'.xls"');
  echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
  if(!empty($data)){
    echo '<tr>'; foreach(array_keys($data[0]) as $h) echo '<th>'.htmlspecialchars(ucwords(str_replace('_',' ',$h))).'</th>'; echo '</tr>';
    foreach($data as $r){ echo '<tr>'; foreach($r as $c) echo '<td>'.htmlspecialchars((string)($c??'')).'</td>'; echo '</tr>'; }
  }
  echo '</table></body></html>'; exit;
}
?>

