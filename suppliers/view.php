<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$id){ header('Location: index.php'); exit; }

$db = getDB();

$st = $db->prepare('SELECT * FROM suppliers WHERE id=? LIMIT 1');
$st->bind_param('i',$id); $st->execute();
$s  = $st->get_result()->fetch_assoc(); $st->close();
if(!$s){ $db->close(); header('Location: index.php'); exit; }

$page  = max(1,(int)($_GET['page']??1));
$limit = 25;
$off   = ($page-1)*$limit;

$ct = $db->prepare("SELECT COUNT(*) n FROM supplier_transactions WHERE supplier_id=?");
$ct->bind_param('i',$id); $ct->execute();
$total = (int)$ct->get_result()->fetch_assoc()['n']; $ct->close();
$pages = $total > 0 ? (int)ceil($total/$limit) : 1;

$ts = $db->prepare("
  SELECT st.transaction_date, st.quantity, st.unit_price, st.total_amount, st.notes,
         v.name variety_name, p.name product_name, p.unit,
         l.invoice_number
  FROM supplier_transactions st
  JOIN product_varieties v  ON st.variety_id  = v.id
  JOIN products p           ON v.product_id   = p.id
  LEFT JOIN ledger_transactions l ON st.ledger_id = l.id
  WHERE st.supplier_id = ?
  ORDER BY st.transaction_date DESC, st.id DESC
  LIMIT ? OFFSET ?
");
$ts->bind_param('iii',$id,$limit,$off); $ts->execute();
$txns = $ts->get_result()->fetch_all(MYSQLI_ASSOC); $ts->close();

$sr = $db->query("SELECT COUNT(*) n, COALESCE(SUM(quantity),0) qty, COALESCE(SUM(total_amount),0) val FROM supplier_transactions WHERE supplier_id=$id");
$stats = $sr ? $sr->fetch_assoc() : ['n'=>0,'qty'=>0,'val'=>0];
$db->close();

$sym = '$';
require __DIR__.'/../includes/header.php';
?>
<div class="main-content">

<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-truck-ramp-box"></i> <?php echo htmlspecialchars($s['name']); ?></h1>
    <div class="breadcrumb">
      <a href="<?php echo APP_URL;?>/dashboard.php">Dashboard</a><span class="sep">/</span>
      <a href="index.php">Suppliers</a><span class="sep">/</span>
      <span><?php echo htmlspecialchars($s['name']); ?></span>
    </div>
  </div>
  <div class="header-actions">
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <button type="button" onclick="toggleEdit()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Edit</button>
    <a href="transaction.php?id=<?php echo $id;?>" class="btn btn-success btn-sm"><i class="fa-solid fa-plus"></i> Stock IN</a>
    <a href="index.php?action=delete&id=<?php echo $id;?>"
       class="btn btn-danger btn-sm"
       onclick="return confirmDelete(this.href,'Delete supplier &quot;<?php echo addslashes(htmlspecialchars($s['name']));?>&quot; permanently?');return false;">
      <i class="fa-solid fa-trash"></i> Delete
    </a>
  </div>
</div>

<!-- INLINE EDIT -->
<div id="editPanel" style="display:none;margin-bottom:20px;">
  <div class="card" style="border:2px solid var(--success);margin-bottom:0;">
    <div class="card-header">
      <div class="card-title"><i class="fa-solid fa-pen"></i> Edit Supplier</div>
      <button onclick="toggleEdit()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Cancel</button>
    </div>
    <form method="POST" action="index.php?action=edit&id=<?php echo $id;?>">
      <input type="hidden" name="id"  value="<?php echo $id;?>">
      <input type="hidden" name="ref" value="view">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Name <span class="req">*</span></label>
          <div class="input-wrap"><i class="fi fa-solid fa-building"></i>
          <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($s['name']);?>"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <div class="input-wrap"><i class="fi fa-solid fa-phone"></i>
          <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($s['phone']);?>"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <div class="input-wrap"><i class="fi fa-solid fa-envelope"></i>
          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($s['email']);?>"></div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($s['address']);?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($s['notes']);?></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="button" onclick="toggleEdit()" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- STATS -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;">
  <div class="card" style="margin-bottom:0;text-align:center;padding:20px;">
    <div style="font-size:28px;font-weight:700;color:var(--warning);"><?php echo number_format((int)$stats['n']);?></div>
    <div style="font-size:11px;color:var(--text-3);margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">Transactions</div>
  </div>
  <div class="card" style="margin-bottom:0;text-align:center;padding:20px;">
    <div style="font-size:28px;font-weight:700;color:var(--success);"><?php echo number_format((float)$stats['qty'],2);?></div>
    <div style="font-size:11px;color:var(--text-3);margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">Total Qty IN</div>
  </div>
  <div class="card" style="margin-bottom:0;text-align:center;padding:20px;">
    <div style="font-size:28px;font-weight:700;color:var(--primary);"><?php echo $sym.number_format((float)$stats['val'],2);?></div>
    <div style="font-size:11px;color:var(--text-3);margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">Total Value</div>
  </div>
</div>

<!-- GRID -->
<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;" class="sv-layout">

  <div class="card" style="margin-bottom:0;">
    <div style="text-align:center;padding-bottom:16px;border-bottom:1px solid var(--border);margin-bottom:16px;">
      <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#F59E0B,#D97706);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:24px;font-weight:700;color:#fff;">
        <?php echo strtoupper(substr($s['name'],0,1));?>
      </div>
      <div style="font-size:15px;font-weight:700;color:var(--text-1);"><?php echo htmlspecialchars($s['name']);?></div>
      <span class="badge badge-warning" style="margin-top:6px;">Supplier</span>
    </div>

    <?php
    $info=[
      ['fa-phone','Phone',$s['phone']],
      ['fa-envelope','Email',$s['email']],
      ['fa-location-dot','Address',$s['address']],
      ['fa-note-sticky','Notes',$s['notes']],
    ];
    foreach($info as [$ico,$lbl,$val]):
      if(!trim($val??'')) continue;
    ?>
    <div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--border);">
      <div style="width:28px;height:28px;border-radius:6px;background:var(--warning-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
        <i class="fa-solid <?php echo $ico;?>" style="color:var(--warning);font-size:11px;"></i>
      </div>
      <div style="min-width:0;">
        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;"><?php echo $lbl;?></div>
        <div style="font-size:12px;font-weight:500;color:var(--text-1);word-break:break-word;"><?php echo nl2br(htmlspecialchars($val));?></div>
      </div>
    </div>
    <?php endforeach;?>
    <div style="font-size:10px;color:var(--text-3);margin-top:10px;">Added: <?php echo htmlspecialchars($s['created_at']??'—');?></div>

    <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
      <a href="transaction.php?id=<?php echo $id;?>" class="btn btn-success btn-block"><i class="fa-solid fa-plus"></i> New Stock IN</a>
      <button onclick="toggleEdit()" class="btn btn-secondary btn-block"><i class="fa-solid fa-pen"></i> Edit Supplier</button>
      <a href="index.php?action=delete&id=<?php echo $id;?>" class="btn btn-danger btn-block"
         onclick="return confirmDelete(this.href,'Delete &quot;<?php echo addslashes(htmlspecialchars($s['name']));?>&quot; permanently?');return false;">
        <i class="fa-solid fa-trash"></i> Delete Supplier
      </a>
    </div>
  </div>

  <div class="table-card" style="margin-bottom:0;">
    <div class="table-toolbar">
      <div class="table-toolbar-title"><i class="fa-solid fa-clock-rotate-left"></i> Purchase History</div>
      <div class="table-toolbar-actions">
        <span style="font-size:11px;color:var(--text-3);"><?php echo number_format($total);?> records</span>
        <a href="transaction.php?id=<?php echo $id;?>" class="btn btn-success btn-sm"><i class="fa-solid fa-plus"></i> Add</a>
      </div>
    </div>
    <div class="tbl-scroll">
      <table>
        <thead><tr>
          <th>Date</th><th>Product</th><th>Variety</th>
          <th class="number-col">Qty</th><th class="number-col">Price</th>
          <th class="number-col">Total</th><th>Invoice</th>
        </tr></thead>
        <tbody>
          <?php if(empty($txns)): ?>
          <tr class="empty-row"><td colspan="7">No transactions yet — <a href="transaction.php?id=<?php echo $id;?>" class="row-link">record first →</a></td></tr>
          <?php else: foreach($txns as $t): ?>
          <tr>
            <td style="white-space:nowrap;font-size:12px;"><?php echo htmlspecialchars($t['transaction_date']);?></td>
            <td style="font-weight:600;"><?php echo htmlspecialchars($t['product_name']);?></td>
            <td style="font-size:12px;"><?php echo htmlspecialchars($t['variety_name']);?></td>
            <td class="number-col"><?php echo number_format((float)$t['quantity'],3);?></td>
            <td class="number-col"><?php echo number_format((float)$t['unit_price'],2);?></td>
            <td class="number-col" style="font-weight:700;color:var(--success);"><?php echo number_format((float)$t['total_amount'],2);?></td>
            <td style="font-size:11px;color:var(--text-3);"><?php echo htmlspecialchars($t['invoice_number']??'—');?></td>
          </tr>
          <?php endforeach; endif;?>
        </tbody>
      </table>
    </div>
    <?php if($pages>1): ?>
    <div class="table-footer">
      <div class="table-info">Page <?php echo $page;?> of <?php echo $pages;?></div>
      <div class="pagination">
        <?php $b='?id='.$id.'&page=';
        if($page>1) echo '<a href="'.$b.($page-1).'" class="page-link"><i class="fa-solid fa-chevron-left"></i></a>';
        for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++) echo '<a href="'.$b.$i.'" class="page-link '.($i===$page?'active':'').'">'.$i.'</a>';
        if($page<$pages) echo '<a href="'.$b.($page+1).'" class="page-link"><i class="fa-solid fa-chevron-right"></i></a>';?>
      </div>
    </div>
    <?php endif;?>
  </div>
</div>
<style>@media(max-width:860px){.sv-layout{grid-template-columns:1fr!important;}}</style>

</div>
<script>
function toggleEdit(){
  var p=document.getElementById('editPanel');
  p.style.display=(p.style.display==='none'||p.style.display==='')?'block':'none';
  if(p.style.display==='block') p.scrollIntoView({behavior:'smooth',block:'nearest'});
}
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
