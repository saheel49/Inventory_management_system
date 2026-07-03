<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();

$action=$_GET['action']??'index';
if($action==='add')    { if($_SERVER['REQUEST_METHOD']==='POST') add_product();    else show_form(); }
elseif($action==='edit')  { if($_SERVER['REQUEST_METHOD']==='POST') edit_product();   else show_edit_form(); }
elseif($action==='view')    show_view();
elseif($action==='delete')  delete_product();
else index_page();

/* ── INDEX ── */
function index_page(){
  $db=getDB(); $search=$_GET['search']??'';
  $page=max(1,(int)($_GET['page']??1));
  $lim=(int)(get_setting('rows_per_page')?:25); $off=($page-1)*$lim;
  $where=''; $bp=[]; $bt='';
  if($search){ $where="WHERE p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?"; $st='%'.$search.'%'; $bp=[$st,$st,$st]; $bt='sss'; }
  $cs=$db->prepare("SELECT COUNT(*) t FROM products p $where");
  if($bp) $cs->bind_param($bt,...$bp); $cs->execute();
  $total=(int)$cs->get_result()->fetch_assoc()['t']; $cs->close(); $pages=ceil($total/$lim);
  $sql="SELECT p.id,p.name,p.description,p.category,p.unit,COALESCE(v.vc,0) vc,COALESCE(v.ts,0) ts FROM products p LEFT JOIN (SELECT product_id,COUNT(*) vc,SUM(current_stock) ts FROM product_varieties GROUP BY product_id) v ON v.product_id=p.id $where ORDER BY p.name ASC LIMIT ? OFFSET ?";
  $lp=$bp; $lt=$bt.'ii'; $lp[]=$lim; $lp[]=$off;
  $ps=$db->prepare($sql); $ps->bind_param($lt,...$lp); $ps->execute();
  $products=$ps->get_result()->fetch_all(MYSQLI_ASSOC); $ps->close();
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <h1><i class="fa-solid fa-box-open"></i> Products</h1>
      <div class="breadcrumb"><a href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span>Products</span></div>
    </div>
    <div class="header-actions">
      <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-house"></i> Dashboard</a>
      <a href="?action=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Product</a>
    </div>
  </div>

  <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success" data-autodismiss="4000"><i class="fa-solid fa-circle-check"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?><button class="alert-close"><i class="fa-solid fa-xmark"></i></button></div><?php endif; ?>
  <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-xmark"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?><button class="alert-close"><i class="fa-solid fa-xmark"></i></button></div><?php endif; ?>

  <div class="card" style="margin-bottom:18px;padding:14px 18px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <div class="autocomplete page-autocomplete" data-search-type="product" style="flex:1;min-width:220px;position:relative;">
        <div class="input-wrap"><i class="fi fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" class="form-control" placeholder="Search products by name, category…" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off"></div>
        <div class="autocomplete-list"></div>
      </div>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-search"></i> Search</button>
      <?php if($search): ?><a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if(empty($products)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="fa-solid fa-box-open"></i></div>
    <div class="empty-state-title">No Products Found</div>
    <div class="empty-state-sub"><?php echo $search?'No results for "'.htmlspecialchars($search).'".':'Add your first product to get started.'; ?></div>
    <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Product</a>
  </div>
  <?php else: ?>
  <div class="products-grid">
    <?php foreach($products as $p): ?>
    <div class="product-card">
      <a class="product-card-link" href="?action=view&id=<?php echo $p['id']; ?>"></a>
      <div class="product-header">
        <h3><?php echo htmlspecialchars($p['name']); ?></h3>
        <div class="product-actions">
          <a href="?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-secondary btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
          <a href="?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-danger btn-sm" title="Delete"
             onclick="return confirmDelete(this.href,'Delete product: <?php echo addslashes(htmlspecialchars($p['name'])); ?>?');return false;"><i class="fa-solid fa-trash"></i></a>
        </div>
      </div>
      <div class="product-info">
        <div class="product-category"><?php echo htmlspecialchars($p['category']?:'Uncategorized'); ?></div>
        <div class="product-desc"><?php echo htmlspecialchars($p['description']?:'No description'); ?></div>
        <div class="product-stats">
          <span class="ps"><i class="fa-solid fa-layer-group" style="font-size:10px;margin-right:3px;"></i><strong><?php echo $p['vc']; ?></strong> Varieties</span>
          <span class="ps"><i class="fa-solid fa-cubes" style="font-size:10px;margin-right:3px;"></i><strong><?php echo number_format($p['ts'],2); ?></strong></span>
        </div>
      </div>
      <?php
      $db2=getDB();
      $vs=$db2->prepare("SELECT id,name,current_stock FROM product_varieties WHERE product_id=? ORDER BY name ASC LIMIT 8");
      $vs->bind_param('i',$p['id']); $vs->execute();
      $vars=$vs->get_result()->fetch_all(MYSQLI_ASSOC); $vs->close(); $db2->close();
      ?>
      <div class="product-varieties">
        <?php foreach($vars as $v): ?>
        <a href="<?php echo APP_URL; ?>/ledger/index.php?variety_id=<?php echo $v['id']; ?>" class="variety-chip">
          <?php echo htmlspecialchars($v['name']); ?>
          <span class="variety-stock"><?php echo number_format($v['current_stock'],2); ?></span>
        </a>
        <?php endforeach; ?>
        <?php if($p['vc']>8): ?><span class="variety-more">+<?php echo $p['vc']-8; ?> more</span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if($pages>1): ?>
  <div class="pagination" style="margin-top:16px;">
    <?php $qp=array_diff_key($_GET,['page'=>'']); $base='?'.http_build_query($qp).'&page=';
    if($page>1) echo '<a href="'.$base.($page-1).'" class="page-link"><i class="fa-solid fa-chevron-left"></i></a>';
    for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++) echo '<a href="'.$base.$i.'" class="page-link '.($i===$page?'active':'').'">'.$i.'</a>';
    if($page<$pages) echo '<a href="'.$base.($page+1).'" class="page-link"><i class="fa-solid fa-chevron-right"></i></a>'; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
  <?php $db->close(); ?>
</div>
<?php require __DIR__.'/../includes/footer.php'; }

/* ── VIEW ── */
function show_view(){
  $db=getDB(); $id=(int)($_GET['id']??0);
  if(!$id){ header('Location: index.php'); exit; }
  $st=$db->prepare("SELECT p.*,COALESCE(v.vc,0) vc,COALESCE(v.ts,0) ts FROM products p LEFT JOIN (SELECT product_id,COUNT(*) vc,SUM(current_stock) ts FROM product_varieties GROUP BY product_id) v ON v.product_id=p.id WHERE p.id=?");
  $st->bind_param('i',$id); $st->execute(); $product=$st->get_result()->fetch_assoc(); $st->close();
  if(!$product){ header('Location: index.php'); exit; }
  $vs=$db->prepare("SELECT * FROM product_varieties WHERE product_id=? ORDER BY name ASC");
  $vs->bind_param('i',$id); $vs->execute(); $vars=$vs->get_result()->fetch_all(MYSQLI_ASSOC); $vs->close(); $db->close();
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <h1><i class="fa-solid fa-box-open"></i> <?php echo htmlspecialchars($product['name']); ?></h1>
      <div class="breadcrumb"><a href="index.php">Products</a><span class="sep">/</span><span><?php echo htmlspecialchars($product['name']); ?></span></div>
    </div>
    <div class="header-actions">
      <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
      <a href="?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Edit</a>
    </div>
  </div>
  <div class="card" style="margin-bottom:20px;">
    <div style="display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap;">
      <div style="width:54px;height:54px;border-radius:14px;background:linear-gradient(135deg,var(--primary),#7C3AED);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fa-solid fa-box-open" style="color:#fff;font-size:22px;"></i></div>
      <div style="flex:1;">
        <h2 style="font-size:19px;font-weight:700;margin-bottom:6px;color:var(--text-1);"><?php echo htmlspecialchars($product['name']); ?></h2>
        <div style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:10px;">
          <?php if($product['category']): ?><span class="badge badge-primary"><?php echo htmlspecialchars($product['category']); ?></span><?php endif; ?>
          <span class="badge badge-gray"><i class="fa-solid fa-ruler"></i> <?php echo htmlspecialchars($product['unit']); ?></span>
          <span class="badge badge-success"><?php echo $product['vc']; ?> Varieties</span>
          <span class="badge badge-info">Stock: <?php echo number_format($product['ts'],2); ?></span>
        </div>
        <?php if($product['description']): ?><p style="font-size:13px;color:var(--text-2);"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="table-card">
    <div class="table-toolbar"><div class="table-toolbar-title"><i class="fa-solid fa-layer-group"></i> Varieties</div></div>
    <?php if(empty($vars)): ?>
    <div class="empty-state"><div class="empty-state-title">No varieties yet.</div></div>
    <?php else: ?>
    <div class="tbl-scroll">
      <table><thead><tr><th>Variety Name</th><th class="number-col">Current Stock</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($vars as $v): ?>
        <tr>
          <td><?php echo htmlspecialchars($v['name']); ?></td>
          <td class="number-col <?php echo $v['current_stock']<=10?'text-danger font-bold':''; ?>"><?php echo number_format($v['current_stock'],3); ?></td>
          <td style="font-size:11px;color:var(--text-3);"><?php echo htmlspecialchars($v['created_at']); ?></td>
          <td><a href="<?php echo APP_URL; ?>/ledger/index.php?variety_id=<?php echo $v['id']; ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-book-open"></i> Ledger</a>
              <a href="<?php echo APP_URL; ?>/products/variety_edit.php?id=<?php echo $v['id']; ?>" class="btn btn-info btn-sm"><i class="fa-solid fa-pen"></i> Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/../includes/footer.php'; }

/* ── ADD FORM ── */
function show_form(){
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <h1><i class="fa-solid fa-plus-circle"></i> Add Product</h1>
      <div class="breadcrumb"><a href="index.php">Products</a><span class="sep">/</span><span>Add</span></div>
    </div>
    <div class="header-actions">
      <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
  </div>
  <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-xmark"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
  <div class="card" style="max-width:760px;">
    <form method="POST">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Product Name <span class="req">*</span></label><input type="text" name="name" class="form-control" required placeholder="e.g. Paint"></div>
        <div class="form-group"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Construction"></div>
        <div class="form-group"><label class="form-label">Unit</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;">
            <select name="unit" class="form-control">
              <?php foreach(['pcs'=>'Pieces','kg'=>'Kilograms','liter'=>'Liters','meter'=>'Meters','yard'=>'Yards','bag'=>'Bags','box'=>'Boxes','roll'=>'Rolls','set'=>'Sets','pair'=>'Pairs','dozen'=>'Dozen'] as $k=>$l): ?>
              <option value="<?php echo $k; ?>"><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="unit_custom" class="form-control" placeholder="Or type custom unit">
          </div>
          <p class="form-hint">Use a standard unit or enter a custom value like kilograms, yards, cartons, or sheets.</p>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea></div>
      <div class="form-group">
        <label class="form-label">Varieties</label>
        <div id="varWrap" class="variety-inputs">
          <div class="variety-row">
            <input type="text" name="varieties[]" class="form-control" placeholder="Variety name">
            <select name="variety_units[]" class="form-control">
              <?php foreach(['pcs'=>'Pieces','kg'=>'Kilograms','liter'=>'Liters','meter'=>'Meters','yard'=>'Yards','bag'=>'Bags','box'=>'Boxes'] as $uk=>$ul): ?>
              <option value="<?php echo $uk; ?>"><?php echo $ul; ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="variety_unit_customs[]" class="form-control" placeholder="Custom unit">
            <input type="number" name="variety_unit_prices[]" class="form-control" step="0.01" min="0" placeholder="Unit price">
            <input type="number" name="variety_stocks[]" class="form-control" step="0.001" min="0" placeholder="Initial stock">
            <button type="button" class="btn btn-danger btn-sm remove-variety"><i class="fa-solid fa-xmark"></i></button>
          </div>
        </div>
        <button type="button" id="addVarBtn" class="btn btn-secondary btn-sm" style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Add Variety</button>
        <p class="form-hint">Enter named varieties with their starting stock quantities.</p>
      </div>
      <div class="form-actions">
        <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Add Product</button>
      </div>
    </form>
  </div>
</div>
<script>
const vw=document.getElementById('varWrap');
document.getElementById('addVarBtn').addEventListener('click',()=>{
  const r=document.createElement('div'); r.className='variety-row';
  r.innerHTML='<input type="text" name="varieties[]" class="form-control" placeholder="Variety name"><select name="variety_units[]" class="form-control'><option value="pcs">Pieces</option><option value="kg">Kilograms</option><option value="liter">Liters</option><option value="meter">Meters</option><option value="yard">Yards</option><option value="bag">Bags</option><option value="box">Boxes</option></select><input type="text" name="variety_unit_customs[]" class="form-control" placeholder="Custom unit"><input type="number" name="variety_unit_prices[]" class="form-control" step="0.01" min="0" placeholder="Unit price"><input type="number" name="variety_stocks[]" class="form-control" step="0.001" min="0" placeholder="Initial stock"><button type="button" class="btn btn-danger btn-sm remove-variety"><i class="fa-solid fa-xmark"></i></button>';
  vw.appendChild(r);
});
vw.addEventListener('click',e=>{ if(e.target.closest('.remove-variety')) e.target.closest('.variety-row')?.remove(); });
</script>
<?php require __DIR__.'/../includes/footer.php'; }

/* ── ADD PRODUCT ── */
function add_product(){
  $db=getDB(); $name=trim($_POST['name']??''); $desc=trim($_POST['description']??''); $cat=trim($_POST['category']??''); $unit=$_POST['unit']??'pcs';
  $unit_custom = trim($_POST['unit_custom']??'');
  if(!$name){ $_SESSION['error']='Product name is required.'; header('Location: ?action=add'); exit; }
  $ds=$db->prepare("SELECT COUNT(*) c FROM products WHERE LOWER(name)=LOWER(?)"); $ds->bind_param('s',$name); $ds->execute();
  if($ds->get_result()->fetch_assoc()['c']>0){ $ds->close(); $_SESSION['error']='Product name already exists.'; header('Location: ?action=add'); exit; }
  $ds->close();
  $vnames=$_POST['varieties']??[]; $vstocks=$_POST['variety_stocks']??[]; $vprices=$_POST['variety_unit_prices']??[]; $vunits=$_POST['variety_units']??[]; $vunit_customs=$_POST['variety_unit_customs']??[]; $vars=[];
  foreach($vnames as $i=>$vn){
    $vn=trim($vn);
    $vs=(float)($vstocks[$i]??0);
    $vp=(float)($vprices[$i]??0);
    $vu=trim($vunits[$i]??'');
    $vuc=trim($vunit_customs[$i]??'');
    $unitValue = $vuc !== '' ? $vuc : ($vu ?: '');
    if($vn!=='') $vars[]=['name'=>$vn,'stock'=>$vs,'unit_price'=>$vp,'unit'=>$unitValue];
  }
  $uid=$_SESSION['user_id']??null;
  $unitValue = $unit_custom !== '' ? $unit_custom : $unit;
  $st=$db->prepare("INSERT INTO products (name,description,category,unit,created_by) VALUES (?,?,?,?,?)");
  $st->bind_param('ssssi',$name,$desc,$cat,$unitValue,$uid); $st->execute(); $pid=$db->insert_id; $st->close();
  if($vars){ $vs=$db->prepare("INSERT INTO product_varieties (product_id,name,current_stock,unit_price,unit) VALUES (?,?,?,?,?)"); foreach($vars as $v){ $vs->bind_param('isdds',$pid,$v['name'],$v['stock'],$v['unit_price'],$v['unit']); $vs->execute(); } $vs->close(); }
  log_activity('product_add','Added: '.$name);
  $_SESSION['success']='Product added successfully.'; $db->close(); header('Location: index.php'); exit;
}

/* ── EDIT FORM ── */
function show_edit_form(){
  $db=getDB(); $id=(int)($_GET['id']??0);
  $st=$db->prepare("SELECT * FROM products WHERE id=?"); $st->bind_param('i',$id); $st->execute();
  $product=$st->get_result()->fetch_assoc(); $st->close();
  if(!$product){ $_SESSION['error']='Product not found.'; header('Location: index.php'); exit; }
  $vs=$db->prepare("SELECT * FROM product_varieties WHERE product_id=? ORDER BY name ASC");
  $vs->bind_param('i',$id); $vs->execute(); $vars=$vs->get_result()->fetch_all(MYSQLI_ASSOC); $vs->close(); $db->close();
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
  <div class="page-header">
    <div class="page-header-left">
      <h1><i class="fa-solid fa-pen"></i> Edit Product</h1>
      <div class="breadcrumb"><a href="index.php">Products</a><span class="sep">/</span><span>Edit</span></div>
    </div>
    <div class="header-actions">
      <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
  </div>
  <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-xmark"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
  <div class="card" style="max-width:760px;">
    <form method="POST">
      <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Product Name <span class="req">*</span></label><input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($product['name']); ?>"></div>
        <div class="form-group"><label class="form-label">Category</label><input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($product['category']); ?>"></div>
        <div class="form-group"><label class="form-label">Unit</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;">
            <select name="unit" class="form-control">
              <?php foreach(['pcs'=>'Pieces','kg'=>'Kilograms','liter'=>'Liters','meter'=>'Meters','yard'=>'Yards','bag'=>'Bags','box'=>'Boxes','roll'=>'Rolls','set'=>'Sets','pair'=>'Pairs','dozen'=>'Dozen'] as $k=>$l): ?>
              <option value="<?php echo $k; ?>" <?php echo $product['unit']===$k?'selected':''; ?>><?php echo $l; ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="unit_custom" class="form-control" placeholder="Or type custom unit" value="<?php echo htmlspecialchars(in_array($product['unit'], ['pcs','kg','liter','meter','yard','bag','box','roll','set','pair','dozen']) ? '' : $product['unit']); ?>">
          </div>
          <p class="form-hint">Use a standard unit or enter a custom value like kilograms, yards, cartons, or sheets.</p>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($product['description']); ?></textarea></div>
      <div class="form-group">
        <label class="form-label">Add New Varieties</label>
        <div id="newVarWrap" class="variety-inputs"></div>
        <button type="button" id="addNewVar" class="btn btn-secondary btn-sm" style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Add Variety</button>
        <p class="form-hint">Add one or more new product varieties. You can set an initial stock quantity for each.</p>
      </div>
      <div class="form-group">
        <label class="form-label">Existing Varieties</label>
        <div class="variety-inputs">
          <?php foreach($vars as $v): ?>
          <div class="variety-item">
            <input type="text" name="variety_ids[<?php echo $v['id']; ?>][name]" class="form-control" value="<?php echo htmlspecialchars($v['name']); ?>">
            <span class="stock-badge"><i class="fa-solid fa-cubes" style="font-size:10px;margin-right:3px;"></i><?php echo number_format($v['current_stock'],2); ?></span>
            <a href="variety_edit.php?id=<?php echo $v['id']; ?>" class="btn btn-info btn-sm" title="Edit Variety &amp; Stock"><i class="fa-solid fa-pen"></i></a>
            <a href="<?php echo APP_URL; ?>/ledger/index.php?variety_id=<?php echo $v['id']; ?>" class="btn btn-secondary btn-sm" title="Ledger"><i class="fa-solid fa-book-open"></i></a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-actions">
        <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update Product</button>
      </div>
    </form>
  </div>
</div>
<script>
const newVarWrap = document.getElementById('newVarWrap');
document.getElementById('addNewVar').addEventListener('click',()=>{
  const r=document.createElement('div'); r.className='variety-row';
  r.innerHTML='<input type="text" name="new_varieties[]" class="form-control" placeholder="New variety name"><select name="new_variety_units[]" class="form-control"><option value="pcs">Pieces</option><option value="kg">Kilograms</option><option value="liter">Liters</option><option value="meter">Meters</option><option value="yard">Yards</option><option value="bag">Bags</option><option value="box">Boxes</option></select><input type="text" name="new_variety_unit_customs[]" class="form-control" placeholder="Custom unit"><input type="number" name="new_variety_unit_prices[]" class="form-control" step="0.01" min="0" placeholder="Unit price"><input type="number" name="new_variety_stocks[]" class="form-control" step="0.001" min="0" placeholder="Initial stock"><button type="button" class="btn btn-danger btn-sm remove-variety"><i class="fa-solid fa-xmark"></i></button>';
  newVarWrap.appendChild(r);
});
newVarWrap.addEventListener('click',e=>{ const btn=e.target.closest('.remove-variety'); if(btn) btn.closest('.variety-row')?.remove(); });
</script>
<?php require __DIR__.'/../includes/footer.php'; }

/* ── EDIT PRODUCT ── */
function edit_product(){
  $db=getDB(); $id=(int)$_POST['id']; $name=trim($_POST['name']??''); $desc=trim($_POST['description']??''); $cat=trim($_POST['category']??''); $unit=$_POST['unit']??'pcs';
  $unit_custom = trim($_POST['unit_custom']??'');
  if(!$name){ $_SESSION['error']='Name required.'; header('Location: ?action=edit&id='.$id); exit; }
  $ds=$db->prepare("SELECT COUNT(*) c FROM products WHERE LOWER(name)=LOWER(?) AND id!=?"); $ds->bind_param('si',$name,$id); $ds->execute();
  if($ds->get_result()->fetch_assoc()['c']>0){ $ds->close(); $_SESSION['error']='Name already exists.'; header('Location: ?action=edit&id='.$id); exit; }
  $ds->close();
  $unitValue = $unit_custom !== '' ? $unit_custom : $unit;
  $st=$db->prepare("UPDATE products SET name=?,description=?,category=?,unit=? WHERE id=?"); $st->bind_param('ssssi',$name,$desc,$cat,$unitValue,$id); $st->execute(); $st->close();
  if(isset($_POST['variety_ids'])&&is_array($_POST['variety_ids'])){
    $vs=$db->prepare("UPDATE product_varieties SET name=? WHERE id=? AND product_id=?");
    foreach($_POST['variety_ids'] as $vid=>$data){ $vn=trim($data['name']??''); if($vn){ $vs->bind_param('sii',$vn,$vid,$id); $vs->execute(); } }
    $vs->close();
  }
  $newVarieties = $_POST['new_varieties'] ?? [];
  $newStocks = $_POST['new_variety_stocks'] ?? [];
  $newPrices = $_POST['new_variety_unit_prices'] ?? [];
  $newUnits = $_POST['new_variety_units'] ?? [];
  $newUnitCustoms = $_POST['new_variety_unit_customs'] ?? [];
  if(!empty($newVarieties) && is_array($newVarieties)){
    $nv=$db->prepare("INSERT INTO product_varieties (product_id,name,current_stock,unit_price,unit) VALUES (?,?,?,?,?)");
    foreach($newVarieties as $index=>$vn){
      $vn=trim($vn); if($vn==='') continue;
      $stock = isset($newStocks[$index]) ? (float)$newStocks[$index] : 0.0;
      $price = isset($newPrices[$index]) ? (float)$newPrices[$index] : 0.0;
      $u = trim($newUnits[$index]??''); $uc = trim($newUnitCustoms[$index]??''); $unitVal = $uc!=='' ? $uc : ($u?:'');
      $nv->bind_param('isdds',$id,$vn,$stock,$price,$unitVal); $nv->execute();
    }
    $nv->close();
  }
  log_activity('product_edit','Edited: '.$name);
  $_SESSION['success']='Product updated successfully.'; $db->close(); header('Location: index.php'); exit;
}

/* ── DELETE ── */
function delete_product(){
  $db=getDB(); $id=(int)($_GET['id']??0);
  $st=$db->prepare("SELECT name FROM products WHERE id=?"); $st->bind_param('i',$id); $st->execute();
  $p=$st->get_result()->fetch_assoc(); $st->close();
  if($p){ $d=$db->prepare("DELETE FROM products WHERE id=?"); $d->bind_param('i',$id); $d->execute(); $d->close(); log_activity('product_delete','Deleted: '.$p['name']); $_SESSION['success']='Product deleted.'; }
  else $_SESSION['error']='Product not found.';
  $db->close(); header('Location: index.php'); exit;
}
?>
