<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();

$supplier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = getDB();
$ss = $db->prepare('SELECT * FROM suppliers WHERE id=? LIMIT 1');
$ss->bind_param('i',$supplier_id); $ss->execute();
$supplier = $ss->get_result()->fetch_assoc(); $ss->close();
if(!$supplier){ header('Location: index.php'); exit; }

$errors = [];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $date=$_POST['transaction_date']??date('Y-m-d');
    $inv=trim($_POST['invoice_number']??''); $del=trim($_POST['delivery_note']??''); $rem=trim($_POST['remarks']??'');
    $vids=$_POST['variety_ids']??[]; $qtys=$_POST['quantities']??[];
    $items=[];
    foreach($vids as $i=>$vid){
        $vid=(int)$vid; $qty=(float)str_replace(',','.',trim($qtys[$i]??'0'));
        if($vid>0&&$qty>0) $items[$vid]=($items[$vid]??0)+$qty;
    }
    if(empty($items)) $errors[]='Add at least one item with quantity > 0.';
    if(empty($errors)){
        foreach($items as $vid=>$qty){
            $vs=$db->prepare('SELECT v.*,p.name product_name FROM product_varieties v JOIN products p ON v.product_id=p.id WHERE v.id=? LIMIT 1');
            $vs->bind_param('i',$vid); $vs->execute(); $variety=$vs->get_result()->fetch_assoc(); $vs->close();
            if(!$variety) continue;
            $up=(float)($variety['unit_price']??0); $ta=$qty*$up; $si=$qty; $so=0.0;
            $uid=$_SESSION['user_id']??null;
            $ls=$db->prepare('INSERT INTO ledger_transactions (variety_id,transaction_date,customer,invoice_number,delivery_note,stock_in,stock_out,remarks,created_by) VALUES (?,?,?,?,?,?,?,?,?)');
            $ls->bind_param('issssddsi',$vid,$date,$supplier['name'],$inv,$del,$si,$so,$rem,$uid);
            $ls->execute(); $lid=$db->insert_id; $ls->close();
            $us=$db->prepare('UPDATE product_varieties SET current_stock=current_stock+? WHERE id=?');
            $us->bind_param('di',$qty,$vid); $us->execute(); $us->close();
            $st=$db->prepare('INSERT INTO supplier_transactions (supplier_id,variety_id,ledger_id,quantity,unit_price,total_amount,transaction_date,notes) VALUES (?,?,?,?,?,?,?,?)');
            $st->bind_param('iiidddss',$supplier_id,$vid,$lid,$qty,$up,$ta,$date,$rem);
            $st->execute(); $st->close();
        }
        log_activity('supplier_in','Supplier transaction for '.$supplier['name']);
        $_SESSION['success']='Transaction recorded successfully.';
        $db->close(); header('Location: index.php'); exit;
    }
}
$varieties=$db->query('SELECT v.id,v.name variety_name,v.current_stock,v.unit_price,p.name product_name,p.unit FROM product_varieties v JOIN products p ON v.product_id=p.id ORDER BY p.name ASC,v.name ASC')->fetch_all(MYSQLI_ASSOC);
$db->close();
require __DIR__.'/../includes/header.php';
?>
<div class="main-content">

<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-arrow-right-arrow-left"></i> Supplier Transaction</h1>
    <div class="breadcrumb">
      <a href="index.php">Suppliers</a><span class="sep">/</span>
      <span><?php echo htmlspecialchars($supplier['name']); ?></span>
    </div>
  </div>
  <div class="header-actions">
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
  </div>
</div>

<div class="trx-band" style="margin-bottom:20px;background:linear-gradient(135deg,#065F46,#047857);">
  <div class="trx-avatar"><i class="fa-solid fa-truck" style="color:#fff;font-size:18px;"></i></div>
  <div>
    <div class="trx-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
    <div class="trx-type">Supplier &middot; Stock IN Transaction</div>
    <?php if($supplier['phone']): ?>
    <div style="font-size:11px;color:rgba(255,255,255,0.55);margin-top:2px;"><i class="fa-solid fa-phone" style="margin-right:4px;"></i><?php echo htmlspecialchars($supplier['phone']); ?></div>
    <?php endif; ?>
  </div>
</div>

<?php if(!empty($errors)): ?>
<div class="alert alert-danger"><i class="fa-solid fa-circle-xmark"></i><div><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div></div>
<?php endif; ?>

<div class="card">
  <form method="POST">
    <div class="form-row" style="margin-bottom:18px;">
      <div class="form-group">
        <label class="form-label">Date <span class="req">*</span></label>
        <input type="date" name="transaction_date" class="form-control" required value="<?php echo htmlspecialchars($_POST['transaction_date']??date('Y-m-d')); ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Invoice #</label>
        <input type="text" name="invoice_number" class="form-control" value="<?php echo htmlspecialchars($_POST['invoice_number']??''); ?>" placeholder="Invoice number">
      </div>
      <div class="form-group">
        <label class="form-label">Delivery Note</label>
        <input type="text" name="delivery_note" class="form-control" value="<?php echo htmlspecialchars($_POST['delivery_note']??''); ?>" placeholder="Delivery note">
      </div>
    </div>

    <div class="card" style="border:1px solid var(--border);padding:18px;margin-bottom:18px;">
      <div class="card-header" style="margin-bottom:14px;">
        <div class="card-title"><i class="fa-solid fa-boxes-stacked"></i> Items (Stock IN)</div>
      </div>
      <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px;">
        <div class="autocomplete" style="flex:2;min-width:200px;position:relative;">
          <label class="form-label">Search Product / Variety</label>
          <div class="input-wrap">
            <i class="fi fa-solid fa-magnifying-glass"></i>
            <input type="text" id="itemSearch" class="form-control" placeholder="Type to search…" autocomplete="off">
          </div>
          <div class="autocomplete-list" id="itemDropdown"></div>
        </div>
        <div style="min-width:120px;">
          <label class="form-label">Quantity</label>
          <input type="number" id="itemQuantity" class="form-control" min="0" step="0.001" value="1">
        </div>
        <div style="align-self:flex-end;">
          <button type="button" id="addItemBtn" class="btn btn-success"><i class="fa-solid fa-plus"></i> Add Item</button>
        </div>
      </div>
      <div class="table-card" style="margin-bottom:0;box-shadow:none;">
        <div class="tbl-scroll">
          <table id="itemsTable">
            <thead>
              <tr><th>Product</th><th>Variety</th><th class="number-col">Stock</th><th>Unit</th><th class="number-col">Price</th><th>Qty</th><th class="number-col">New Stock</th><th></th></tr>
            </thead>
            <tbody>
              <?php
              $posted=[];
              if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['variety_ids'],$_POST['quantities'])){
                foreach($_POST['variety_ids'] as $i=>$vid){
                  $vid=(int)$vid; $qty=(float)str_replace(',','.',trim($_POST['quantities'][$i]??'0'));
                  if($vid>0&&$qty>0) $posted[$vid]=($posted[$vid]??0)+$qty;
                }
              }
              $vl=[]; foreach($varieties as $v) $vl[$v['id']]=$v;
              foreach($posted as $vid=>$qty): if(!isset($vl[$vid])) continue; $v=$vl[$vid]; $ns=$v['current_stock']+$qty; ?>
              <tr data-item-id="<?php echo $v['id']; ?>">
                <td><?php echo htmlspecialchars($v['product_name']); ?><input type="hidden" name="variety_ids[]" value="<?php echo $v['id']; ?>"></td>
                <td><?php echo htmlspecialchars($v['variety_name']); ?></td>
                <td class="number-col"><?php echo number_format($v['current_stock'],3); ?></td>
                <td><?php echo htmlspecialchars($v['unit']); ?></td>
                <td class="number-col"><?php echo number_format($v['unit_price'],2); ?></td>
                <td><input type="number" name="quantities[]" value="<?php echo $qty; ?>" step="0.001" min="0" class="row-qty" data-stock="<?php echo $v['current_stock']; ?>"></td>
                <td class="number-col new-stock"><?php echo number_format($ns,3); ?></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="fa-solid fa-xmark"></i></button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:18px;">
      <label class="form-label">Remarks</label>
      <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks"><?php echo htmlspecialchars($_POST['remarks']??''); ?></textarea>
    </div>
    <div class="form-actions">
      <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back</a>
      <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i> Record Transaction</button>
    </div>
  </form>
</div>
</div>

<script>
var varieties=<?php echo json_encode($varieties,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
var inp=document.getElementById('itemSearch'), drop=document.getElementById('itemDropdown');
function fv(id){ return varieties.find(v=>v.id==id); }
function clrDrop(){ drop.innerHTML=''; drop.classList.remove('active'); }
inp.addEventListener('input',function(){
  this.dataset.selectedId=''; var q=this.value.trim().toLowerCase();
  if(!q){ clrDrop(); return; }
  var m=varieties.filter(v=>v.product_name.toLowerCase().includes(q)||v.variety_name.toLowerCase().includes(q));
  if(!m.length){ drop.innerHTML='<div class="ac-item no-result">No items found</div>'; drop.classList.add('active'); return; }
  drop.innerHTML=m.slice(0,10).map(v=>`<div class="ac-item" data-id="${v.id}">
    <div class="ac-title">${v.product_name} <span class="ac-badge">${v.variety_name}</span></div>
    <div class="ac-meta">Stock: ${parseFloat(v.current_stock).toFixed(3)}</div>
  </div>`).join('');
  drop.classList.add('active');
});
drop.addEventListener('click',function(e){
  var item=e.target.closest('.ac-item'); if(!item||item.classList.contains('no-result')) return;
  var v=fv(item.dataset.id); if(!v) return;
  inp.value=v.product_name+' – '+v.variety_name; inp.dataset.selectedId=v.id; clrDrop();
});
document.addEventListener('click',e=>{ if(!inp.closest('.autocomplete').contains(e.target)) clrDrop(); });
document.getElementById('addItemBtn').addEventListener('click',function(){
  var id=parseInt(inp.dataset.selectedId||0,10);
  var qty=parseFloat(document.getElementById('itemQuantity').value)||0;
  if(!id){ showToast('warning','Select Item','Please select an item from the search results.'); return; }
  if(qty<=0){ showToast('warning','Invalid Qty','Enter a quantity greater than zero.'); return; }
  if(document.querySelector('#itemsTable tbody tr[data-item-id="'+id+'"]')){ showToast('warning','Duplicate','Item already added.'); return; }
  var v=fv(id); if(!v) return;
  var ns=(parseFloat(v.current_stock)+qty).toFixed(3);
  var row=document.createElement('tr'); row.dataset.itemId=id;
  row.innerHTML=`<td>${v.product_name}<input type="hidden" name="variety_ids[]" value="${id}"></td>
    <td>${v.variety_name}</td>
    <td class="number-col">${parseFloat(v.current_stock).toFixed(3)}</td>
    <td>${v.unit||'pcs'}</td>
    <td class="number-col">${parseFloat(v.unit_price).toFixed(2)}</td>
    <td><input type="number" name="quantities[]" value="${qty.toFixed(3)}" step="0.001" min="0" class="row-qty" data-stock="${v.current_stock}"></td>
    <td class="number-col new-stock">${ns}</td>
    <td><button type="button" class="btn btn-danger btn-sm remove-item"><i class="fa-solid fa-xmark"></i></button></td>`;
  document.querySelector('#itemsTable tbody').appendChild(row);
  inp.value=''; inp.dataset.selectedId='';
  document.getElementById('itemQuantity').value='1';
  showToast('success','Added',v.product_name+' – '+v.variety_name+' added.');
});
document.querySelector('#itemsTable tbody').addEventListener('click',e=>{
  if(e.target.closest('.remove-item')) e.target.closest('tr').remove();
});
document.querySelector('#itemsTable tbody').addEventListener('input',e=>{
  if(e.target.classList.contains('row-qty')){
    var tr=e.target.closest('tr');
    var s=parseFloat(e.target.dataset.stock)||0, q=parseFloat(e.target.value)||0;
    tr.querySelector('.new-stock').textContent=(s+q).toFixed(3);
  }
});
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
