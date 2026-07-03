<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();

$action=$_GET['action']??'index';
if($action==='add')    { if($_SERVER['REQUEST_METHOD']==='POST') add_txn(); else show_form(); }
elseif($action==='delete') del_txn();
else index_page();

/* ── INDEX ── */
function index_page(){
  $db=getDB(); $vid=(int)($_GET['variety_id']??0);
  $vs=$db->prepare("SELECT v.*,p.name pname,p.unit FROM product_varieties v JOIN products p ON v.product_id=p.id WHERE v.id=?");
  $vs->bind_param('i',$vid); $vs->execute(); $var=$vs->get_result()->fetch_assoc(); $vs->close();
  if(!$var){ header('Location: ../products/index.php'); exit; }

  $search=$_GET['search']??''; $fd=$_GET['filter_date']??'';
  $fdf=$_GET['filter_date_from']??''; $fdt=$_GET['filter_date_to']??'';
  $fc=$_GET['filter_customer']??''; $ftype=$_GET['filter_type']??'';
  $pg=max(1,(int)($_GET['page']??1));
  $lim=(int)(get_setting('rows_per_page')?:25); $off=($pg-1)*$lim;
  $sort=$_GET['sort']??'date'; $dir=strtoupper($_GET['dir']??'DESC');
  if(!in_array($dir,['ASC','DESC'])) $dir='DESC';
  $smap=['id'=>'l.id','date'=>'l.transaction_date','customer'=>'l.customer','invoice'=>'l.invoice_number','in'=>'l.stock_in','out'=>'l.stock_out'];
  if(!isset($smap[$sort])) $sort='date';

  $w="WHERE l.variety_id=?"; $p=[$vid]; $t='i';
  if($search){ $w.=" AND (l.customer LIKE ? OR l.invoice_number LIKE ? OR l.remarks LIKE ?)"; $s='%'.$search.'%'; $p[]=$s;$p[]=$s;$p[]=$s; $t.='sss'; }
  if($fd)      { $w.=" AND l.transaction_date=?"; $p[]=$fd; $t.='s'; }
  if($fdf&&$fdt){ $w.=" AND l.transaction_date BETWEEN ? AND ?"; $p[]=$fdf;$p[]=$fdt; $t.='ss'; }
  if($fc)      { $w.=" AND l.customer LIKE ?"; $p[]='%'.$fc.'%'; $t.='s'; }
  if($ftype==='in')  $w.=" AND l.stock_in>0";
  if($ftype==='out') $w.=" AND l.stock_out>0";

  $cs=$db->prepare("SELECT COUNT(*) total FROM ledger_transactions l $w");
  $cs->bind_param($t,...$p); $cs->execute(); $total=(int)$cs->get_result()->fetch_assoc()['total']; $cs->close();
  $pages=ceil($total/$lim);
  $lp=$p; $lt=$t;
  $ls=$db->prepare("SELECT l.* FROM ledger_transactions l $w ORDER BY {$smap[$sort]} $dir,l.id DESC LIMIT $lim OFFSET $off");
  if($lp) $ls->bind_param($lt,...$lp);
  $ls->execute(); $txns=$ls->get_result()->fetch_all(MYSQLI_ASSOC); $ls->close(); $db->close();

  function su($col){
    $cur=$_GET['sort']??'date'; $d=strtoupper($_GET['dir']??'DESC');
    $nd=($col===$cur&&$d==='ASC')?'DESC':'ASC';
    $q=$_GET; $q['sort']=$col; $q['dir']=$nd;
    return '?'.http_build_query($q);
  }
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <h1><i class="fa-solid fa-book-open"></i> Ledger</h1>
      <div class="breadcrumb">
        <a href="<?php echo APP_URL; ?>/products/index.php">Products</a><span class="sep">/</span>
        <span><?php echo htmlspecialchars($var['pname'].' – '.$var['name']); ?></span>
      </div>
    </div>
    <div class="header-actions">
      <a href="<?php echo APP_URL; ?>/products/index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Products</a>
      <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i></button>
      <a href="?variety_id=<?php echo $vid; ?>&action=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Transaction</a>
    </div>
  </div>

  <!-- Info band -->
  <div class="info-band" style="margin-bottom:20px;">
    <div class="ib-item"><div class="ib-label">Product</div><div class="ib-value"><?php echo htmlspecialchars($var['pname']); ?></div></div>
    <div class="ib-item"><div class="ib-label">Variety</div><div class="ib-value"><?php echo htmlspecialchars($var['name']); ?></div></div>
    <div class="ib-item"><div class="ib-label">Current Stock</div><div class="ib-value big"><?php echo number_format($var['current_stock'],3); ?></div></div>
    <div class="ib-item"><div class="ib-label">Unit</div><div class="ib-value"><?php echo htmlspecialchars($var['unit']??'pcs'); ?></div></div>
    <div class="ib-item"><div class="ib-label">Total Records</div><div class="ib-value"><?php echo number_format($total); ?></div></div>
  </div>

  <!-- Filters -->
  <div class="card" style="margin-bottom:16px;padding:14px 18px;">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:9px;align-items:flex-end;">
      <input type="hidden" name="variety_id" value="<?php echo $vid; ?>">
      <div class="input-wrap" style="flex:1;min-width:150px;">
        <i class="fi fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" class="form-control" placeholder="Search…" value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <input type="date" name="filter_date"      class="form-control" value="<?php echo htmlspecialchars($fd); ?>"  title="Exact date"  style="min-width:130px;">
      <input type="date" name="filter_date_from" class="form-control" value="<?php echo htmlspecialchars($fdf); ?>" title="From date"   style="min-width:130px;">
      <input type="date" name="filter_date_to"   class="form-control" value="<?php echo htmlspecialchars($fdt); ?>" title="To date"     style="min-width:130px;">
      <input type="text" name="filter_customer"  class="form-control" value="<?php echo htmlspecialchars($fc); ?>"  placeholder="Supplier/Customer" style="min-width:130px;">
      <select name="filter_type" class="form-control" style="min-width:110px;">
        <option value="">All Types</option>
        <option value="in"  <?php echo $ftype==='in' ?'selected':''; ?>>Stock IN</option>
        <option value="out" <?php echo $ftype==='out'?'selected':''; ?>>Stock OUT</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
      <a href="?variety_id=<?php echo $vid; ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
    </form>
  </div>

  <div class="table-card">
    <div class="table-toolbar">
      <div class="table-toolbar-title"><i class="fa-solid fa-table-list"></i> Transactions</div>
    </div>
    <div class="tbl-scroll">
      <table class="ledger-datatable">
        <thead><tr>
          <th class="sticky-col" style="min-width:42px;"><a href="<?php echo su('id'); ?>" class="sort-link">#</a></th>
          <th style="min-width:110px;"><a href="<?php echo su('date'); ?>" class="sort-link">Date <i class="fa-solid fa-sort" style="font-size:9px;"></i></a></th>
          <th style="min-width:140px;"><a href="<?php echo su('customer'); ?>" class="sort-link">Supplier / Customer</a></th>
          <th style="min-width:80px;">Type</th>
          <th style="min-width:110px;"><a href="<?php echo su('invoice'); ?>" class="sort-link">Invoice #</a></th>
          <th style="min-width:110px;">Delivery Note</th>
          <th class="number-col" style="min-width:90px;"><a href="<?php echo su('in'); ?>" class="sort-link">IN</a></th>
          <th class="number-col" style="min-width:90px;"><a href="<?php echo su('out'); ?>" class="sort-link">OUT</a></th>
          <th class="number-col" style="min-width:90px;">Balance</th>
          <th style="min-width:120px;">Remarks</th>
          <th style="min-width:60px;">Del</th>
        </tr></thead>
        <tbody>
          <?php if(empty($txns)): ?>
          <tr class="empty-row"><td colspan="11">No transactions found.</td></tr>
          <?php else:
            // Compute running balance from current stock backwards
            $bal = (float)$var['current_stock'];
            foreach(array_reverse($txns) as $t2) $bal += $t2['stock_out'] - $t2['stock_in'];
            $idx = $off;
            foreach(array_reverse($txns) as $tx):
              $bal += $tx['stock_in'] - $tx['stock_out'];
          ?>
          <tr>
            <td class="sticky-col" style="color:var(--text-3);font-size:11px;"><?php echo ++$idx; ?></td>
            <td style="white-space:nowrap;font-size:12px;"><?php echo htmlspecialchars($tx['transaction_date']); ?></td>
            <td style="font-size:12px;"><?php echo htmlspecialchars($tx['customer'])?:'-'; ?></td>
            <td>
              <?php if($tx['stock_in']>0): ?>
              <span class="badge badge-success" style="font-size:10px;"><i class="fa-solid fa-arrow-down"></i> IN</span>
              <?php else: ?>
              <span class="badge badge-danger" style="font-size:10px;"><i class="fa-solid fa-arrow-up"></i> OUT</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;"><?php echo htmlspecialchars($tx['invoice_number'])?:'-'; ?></td>
            <td style="font-size:12px;"><?php echo htmlspecialchars($tx['delivery_note'])?:'-'; ?></td>
            <td class="number-col" style="color:var(--success);"><?php echo $tx['stock_in']>0?number_format($tx['stock_in'],3):'-'; ?></td>
            <td class="number-col" style="color:var(--danger);"><?php echo $tx['stock_out']>0?number_format($tx['stock_out'],3):'-'; ?></td>
            <td class="number-col font-bold"><?php echo number_format($bal,3); ?></td>
            <td style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px;"><?php echo htmlspecialchars($tx['remarks'])?:'-'; ?></td>
            <td>
              <form method="POST" action="?variety_id=<?php echo $vid; ?>&action=delete" style="display:inline;" onsubmit="return confirm('Delete this transaction?');">
                <input type="hidden" name="id" value="<?php echo $tx['id']; ?>">
                <input type="hidden" name="variety_id" value="<?php echo $vid; ?>">
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="table-footer">
      <div class="table-info">Showing <?php echo count($txns); ?> of <?php echo number_format($total); ?></div>
      <?php if($pages>1): ?>
      <div class="pagination">
        <?php $qp=array_diff_key($_GET,['page'=>'']); $base='?'.http_build_query($qp).'&page=';
        if($pg>1) echo '<a href="'.$base.($pg-1).'" class="page-link"><i class="fa-solid fa-chevron-left"></i></a>';
        for($i=max(1,$pg-2);$i<=min($pages,$pg+2);$i++) echo '<a href="'.$base.$i.'" class="page-link '.($i===$pg?'active':'').'">'.$i.'</a>';
        if($pg<$pages) echo '<a href="'.$base.($pg+1).'" class="page-link"><i class="fa-solid fa-chevron-right"></i></a>'; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__.'/../includes/footer.php'; }

/* ── ADD TRANSACTION FORM ── */
function show_form(){
  $db=getDB(); $vid=(int)($_GET['variety_id']??0);
  $vs=$db->prepare("SELECT v.*,p.name pname FROM product_varieties v JOIN products p ON v.product_id=p.id WHERE v.id=?");
  $vs->bind_param('i',$vid); $vs->execute(); $var=$vs->get_result()->fetch_assoc(); $vs->close();
  if(!$var){ header('Location: ../products/index.php'); exit; }
  $customers=$db->query('SELECT id,name FROM customers ORDER BY name ASC')->fetch_all(MYSQLI_ASSOC);
  $suppliers=$db->query('SELECT id,name FROM suppliers ORDER BY name ASC')->fetch_all(MYSQLI_ASSOC);
  $db->close();
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <h1><i class="fa-solid fa-plus-circle"></i> New Transaction</h1>
      <div class="breadcrumb">
        <a href="../products/index.php">Products</a><span class="sep">/</span>
        <a href="?variety_id=<?php echo $vid; ?>">Ledger</a><span class="sep">/</span>
        <span>New</span>
      </div>
    </div>
    <div class="header-actions">
      <a href="?variety_id=<?php echo $vid; ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
  </div>
  <div class="card" style="max-width:720px;">
    <form method="POST">
      <input type="hidden" name="variety_id" value="<?php echo $vid; ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date <span class="req">*</span></label>
          <input type="date" name="transaction_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Transaction Type</label>
          <select name="transaction_type" id="trxType" class="form-control">
            <option value="customer">Customer Out (Stock OUT)</option>
            <option value="supplier">Supplier In (Stock IN)</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group" id="custGrp">
          <label class="form-label">Customer</label>
          <select name="customer_id" class="form-control">
            <option value="">Select customer…</option>
            <?php foreach($customers as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="supGrp" style="display:none;">
          <label class="form-label">Supplier</label>
          <select name="supplier_id" class="form-control">
            <option value="">Select supplier…</option>
            <?php foreach($suppliers as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Name (manual override)</label>
          <input type="text" name="customer_name" class="form-control" placeholder="Or type name manually">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Quantity <span class="req">*</span></label>
          <input type="number" name="quantity" class="form-control" step="0.001" min="0" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Invoice #</label>
          <input type="text" name="invoice_number" class="form-control" placeholder="Invoice number">
        </div>
        <div class="form-group">
          <label class="form-label">Delivery Note</label>
          <input type="text" name="delivery_note" class="form-control" placeholder="Delivery note">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks"></textarea>
      </div>
      <div class="form-actions">
        <a href="?variety_id=<?php echo $vid; ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Add Transaction</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const tt=document.getElementById('trxType');
  const cg=document.getElementById('custGrp'); const sg=document.getElementById('supGrp');
  function tog(){ if(tt.value==='supplier'){ cg.style.display='none'; sg.style.display=''; } else { cg.style.display=''; sg.style.display='none'; } }
  tog(); tt.addEventListener('change',tog);
});
</script>
<?php require __DIR__.'/../includes/footer.php'; }

/* ── ADD TRANSACTION ── */
function add_txn(){
  $db=getDB();
  $vid=(int)$_POST['variety_id']; $date=$_POST['transaction_date']??date('Y-m-d');
  $ttype=$_POST['transaction_type']??'customer'; $cname=trim($_POST['customer_name']??'');
  $cid=(int)($_POST['customer_id']??0); $sid=(int)($_POST['supplier_id']??0);
  $inv=$_POST['invoice_number']??''; $del=$_POST['delivery_note']??'';
  $qty=(float)($_POST['quantity']??0); $rem=$_POST['remarks']??'';
  if(!$date||$qty<=0){ $_SESSION['error']='Date and quantity required.'; header('Location: ?variety_id='.$vid.'&action=add'); exit; }
  if($ttype==='supplier'){
    if($sid>0){ $r=$db->prepare('SELECT name FROM suppliers WHERE id=? LIMIT 1'); $r->bind_param('i',$sid); $r->execute(); $rr=$r->get_result()->fetch_assoc(); $r->close(); if($rr) $cname=$rr['name']; }
    $si=$qty; $so=0.0;
  } else {
    if($cid>0){ $r=$db->prepare('SELECT name FROM customers WHERE id=? LIMIT 1'); $r->bind_param('i',$cid); $r->execute(); $rr=$r->get_result()->fetch_assoc(); $r->close(); if($rr) $cname=$rr['name']; }
    $si=0.0; $so=$qty;
  }
  if(!$cname) $cname=$ttype==='supplier'?'Supplier':'Customer';
  $uid=$_SESSION['user_id']??null;
  $st=$db->prepare("INSERT INTO ledger_transactions (variety_id,transaction_date,customer,invoice_number,delivery_note,stock_in,stock_out,remarks,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
  $st->bind_param('issssddsi',$vid,$date,$cname,$inv,$del,$si,$so,$rem,$uid); $st->execute(); $st->close();
  $us=$db->prepare('UPDATE product_varieties SET current_stock=current_stock+?-? WHERE id=?');
  $us->bind_param('ddi',$si,$so,$vid); $us->execute(); $us->close();
  $pr=$db->prepare('SELECT unit_price FROM product_varieties WHERE id=? LIMIT 1');
  $pr->bind_param('i',$vid); $pr->execute(); $prr=$pr->get_result()->fetch_assoc(); $pr->close();
  $up=(float)($prr['unit_price']??0); $ta=$qty*$up;
  if($ttype==='supplier'&&$sid>0){ $is=$db->prepare('INSERT INTO supplier_transactions (supplier_id,variety_id,quantity,unit_price,total_amount,transaction_date,notes) VALUES (?,?,?,?,?,?,?)'); $is->bind_param('iiddiss',$sid,$vid,$qty,$up,$ta,$date,$rem); $is->execute(); $is->close(); }
  elseif($ttype==='customer'&&$cid>0){ $ic=$db->prepare('INSERT INTO customer_transactions (customer_id,variety_id,quantity,unit_price,total_amount,transaction_date,notes) VALUES (?,?,?,?,?,?,?)'); $ic->bind_param('iiddiss',$cid,$vid,$qty,$up,$ta,$date,$rem); $ic->execute(); $ic->close(); }
  log_activity($ttype==='supplier'?'supplier_in':'customer_out','Txn Qty:'.$qty);
  $_SESSION['success']='Transaction added.';
  $db->close(); header('Location: ?variety_id='.$vid); exit;
}

/* ── DELETE TRANSACTION ── */
function del_txn(){
  $db=getDB(); $id=(int)$_POST['id']; $vid=(int)$_POST['variety_id'];
  $st=$db->prepare("DELETE FROM ledger_transactions WHERE id=?"); $st->bind_param('i',$id); $st->execute(); $st->close();
  log_activity('transaction_delete','Deleted #'.$id);
  $_SESSION['success']='Transaction deleted.';
  $db->close(); header('Location: ?variety_id='.$vid); exit;
}
?>
