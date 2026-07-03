<?php
require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/functions.php';
require_login();

$db = getDB();
$tp  = (int)$db->query("SELECT COUNT(*) c FROM products")->fetch_assoc()['c'];
$tv  = (int)$db->query("SELECT COUNT(*) c FROM product_varieties")->fetch_assoc()['c'];
$tc  = (int)$db->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'];
$tsu = (int)$db->query("SELECT COUNT(*) c FROM suppliers")->fetch_assoc()['c'];
$tt  = (int)$db->query("SELECT COUNT(*) c FROM ledger_transactions")->fetch_assoc()['c'];
$cs  = (float)$db->query("SELECT COALESCE(SUM(current_stock),0) s FROM product_varieties")->fetch_assoc()['s'];
$ls  = (int)$db->query("SELECT COUNT(*) c FROM product_varieties WHERE current_stock<=10")->fetch_assoc()['c'];
$today = date('Y-m-d');
$st = $db->prepare("SELECT COUNT(*) c FROM ledger_transactions WHERE transaction_date=?");
$st->bind_param('s',$today); $st->execute();
$ttx = (int)$st->get_result()->fetch_assoc()['c']; $st->close();

/* Monthly chart */
$mlbl=[]; $min=[]; $mout=[];
for($i=5;$i>=0;$i--){
  $m   = date('Y-m',strtotime("-$i months"));
  $mlbl[] = date('M',strtotime("-$i months"));
  $r = $db->query("SELECT COALESCE(SUM(stock_in),0) si,COALESCE(SUM(stock_out),0) so FROM ledger_transactions WHERE DATE_FORMAT(transaction_date,'%Y-%m')='$m'");
  $row=$r->fetch_assoc(); $min[]=(float)$row['si']; $mout[]=(float)$row['so'];
}

$logs     = $db->query("SELECT l.*,u.full_name fn FROM activity_logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.created_at DESC LIMIT 7")->fetch_all(MYSQLI_ASSOC);
$rproducts= $db->query("SELECT p.id,p.name,p.category,COUNT(pv.id) vc,COALESCE(SUM(pv.current_stock),0) ts FROM products p LEFT JOIN product_varieties pv ON p.id=pv.product_id GROUP BY p.id ORDER BY p.updated_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$lstock   = $db->query("SELECT pv.id,pv.name vn,p.name pn,pv.current_stock,p.unit FROM product_varieties pv JOIN products p ON pv.product_id=p.id WHERE pv.current_stock<=10 ORDER BY pv.current_stock ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$db->close();

$hr = (int)date('H');
$greet = $hr<12 ? 'Good Morning ☀️' : ($hr<17 ? 'Good Afternoon 🌤️' : 'Good Evening 🌙');

require __DIR__.'/includes/header.php';
?>
<div class="main-content">

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-gauge-high"></i> Dashboard</h1>
    <div class="breadcrumb">
      <i class="fa-solid fa-house" style="font-size:10px;"></i>
      <span class="sep">/</span><span>Dashboard</span>
    </div>
  </div>
  <div class="header-actions">
    <a href="<?php echo APP_URL; ?>/reports/index.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-chart-bar"></i> Reports
    </a>
    <a href="<?php echo APP_URL; ?>/customers/index.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-users"></i> Customers
    </a>
    <a href="<?php echo APP_URL; ?>/products/index.php?action=add" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-plus"></i> Add Product
    </a>
  </div>
</div>

<!-- Welcome Banner -->
<div class="welcome-card">
  <div class="welcome-left">
    <div class="wc-greeting"><?php echo $greet; ?></div>
    <div class="wc-name"><?php echo htmlspecialchars($_SESSION['full_name']??'User'); ?></div>
    <div class="wc-date" id="wcDate"></div>
  </div>
  <div class="welcome-right">
    <div class="wc-clock" id="wcClock">--:--:--</div>
    <div class="wc-ampm" id="wcAmpm"></div>
  </div>
</div>

<!-- Stats Grid — 4 columns always -->
<div class="stats-grid">
  <a href="<?php echo APP_URL; ?>/products/index.php" class="stat-card">
    <div class="si blue"><i class="fa-solid fa-box-open"></i></div>
    <div>
      <div class="stat-num" data-count="<?php echo $tp; ?>"><?php echo number_format($tp); ?></div>
      <div class="stat-lbl">Total Products</div>
    </div>
  </a>
  <a href="<?php echo APP_URL; ?>/products/index.php" class="stat-card">
    <div class="si purple"><i class="fa-solid fa-layer-group"></i></div>
    <div>
      <div class="stat-num" data-count="<?php echo $tv; ?>"><?php echo number_format($tv); ?></div>
      <div class="stat-lbl">Varieties</div>
    </div>
  </a>
  <a href="<?php echo APP_URL; ?>/customers/index.php" class="stat-card">
    <div class="si green"><i class="fa-solid fa-users"></i></div>
    <div>
      <div class="stat-num" data-count="<?php echo $tc; ?>"><?php echo number_format($tc); ?></div>
      <div class="stat-lbl">Customers</div>
    </div>
  </a>
  <a href="<?php echo APP_URL; ?>/suppliers/index.php" class="stat-card">
    <div class="si orange"><i class="fa-solid fa-truck-ramp-box"></i></div>
    <div>
      <div class="stat-num" data-count="<?php echo $tsu; ?>"><?php echo number_format($tsu); ?></div>
      <div class="stat-lbl">Suppliers</div>
    </div>
  </a>
  <a href="<?php echo APP_URL; ?>/reports/index.php?action=generate&type=current_stock" class="stat-card">
    <div class="si cyan"><i class="fa-solid fa-cubes"></i></div>
    <div>
      <div class="stat-num" data-count="<?php echo (int)$cs; ?>"><?php echo number_format($cs,0); ?></div>
      <div class="stat-lbl">Total Stock</div>
    </div>
  </a>
  <a href="<?php echo APP_URL; ?>/reports/index.php?action=generate&type=daily&date_from=<?php echo $today; ?>&date_to=<?php echo $today; ?>" class="stat-card">
    <div class="si blue"><i class="fa-solid fa-calendar-day"></i></div>
    <div>
      <div class="stat-num" data-count="<?php echo $ttx; ?>"><?php echo number_format($ttx); ?></div>
      <div class="stat-lbl">Today's Txns</div>
    </div>
  </a>
  <a href="<?php echo APP_URL; ?>/ledger/index.php" class="stat-card">
    <div class="si purple"><i class="fa-solid fa-receipt"></i></div>
    <div>
      <div class="stat-num" data-count="<?php echo $tt; ?>"><?php echo number_format($tt); ?></div>
      <div class="stat-lbl">All Transactions</div>
    </div>
  </a>
  <a href="<?php echo APP_URL; ?>/reports/index.php?action=generate&type=low_stock" class="stat-card">
    <div class="si red"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div>
      <div class="stat-num" data-count="<?php echo $ls; ?>"><?php echo number_format($ls); ?></div>
      <div class="stat-lbl">Low Stock</div>
    </div>
  </a>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;" class="dashboard-charts-row">
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <div class="card-title"><i class="fa-solid fa-chart-bar"></i> Stock Movement – Last 6 Months</div>
    </div>
    <div style="height:220px;position:relative;">
      <canvas id="stockChart"></canvas>
    </div>
  </div>
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <div class="card-title"><i class="fa-solid fa-chart-pie"></i> Distribution</div>
    </div>
    <div style="height:220px;position:relative;display:flex;align-items:center;justify-content:center;">
      <canvas id="distChart"></canvas>
    </div>
  </div>
</div>

<!-- Bottom Row -->
<div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:20px;margin-bottom:20px;" class="dashboard-bottom-row">

  <!-- Recent Activity -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</div>
      <a href="<?php echo APP_URL; ?>/activity_logs/index.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <?php if(empty($logs)): ?>
      <p style="color:var(--text-3);font-size:13px;text-align:center;padding:20px 0;">No activity yet.</p>
    <?php else: foreach($logs as $log):
      $a = strtolower($log['action']);
      if(strpos($a,'login')!==false)      { $ic='fa-right-to-bracket';$dc='blue'; }
      elseif(strpos($a,'logout')!==false) { $ic='fa-right-from-bracket';$dc='orange'; }
      elseif(strpos($a,'add')!==false||strpos($a,'create')!==false) { $ic='fa-plus';$dc='green'; }
      elseif(strpos($a,'edit')!==false||strpos($a,'update')!==false){ $ic='fa-pen';$dc='purple'; }
      elseif(strpos($a,'delete')!==false) { $ic='fa-trash';$dc='red'; }
      elseif(strpos($a,'in')!==false)     { $ic='fa-arrow-down';$dc='green'; }
      elseif(strpos($a,'out')!==false)    { $ic='fa-arrow-up';$dc='orange'; }
      else                                { $ic='fa-scroll';$dc='blue'; }
    ?>
    <div class="activity-item">
      <div class="ac-dot <?php echo $dc; ?>"><i class="fa-solid <?php echo $ic; ?>"></i></div>
      <div style="flex:1;min-width:0;">
        <div class="ac-text" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
          <?php echo htmlspecialchars($log['description']?:$log['action']); ?>
        </div>
        <div class="ac-time"><?php echo time_ago($log['created_at']); ?> · <?php echo htmlspecialchars($log['fn']??'System'); ?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Quick Actions -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <div class="card-title"><i class="fa-solid fa-bolt"></i> Quick Actions</div>
    </div>
    <div class="quick-actions-grid">
      <a href="<?php echo APP_URL; ?>/products/index.php?action=add" class="qa-btn">
        <div class="qa-icon"><i class="fa-solid fa-box"></i></div><span>New Product</span>
      </a>
      <a href="<?php echo APP_URL; ?>/customers/index.php" class="qa-btn">
        <div class="qa-icon" style="background:var(--success-light);color:var(--success);"><i class="fa-solid fa-user-plus"></i></div><span>New Customer</span>
      </a>
      <a href="<?php echo APP_URL; ?>/suppliers/index.php" class="qa-btn">
        <div class="qa-icon" style="background:var(--warning-light);color:var(--warning);"><i class="fa-solid fa-truck"></i></div><span>New Supplier</span>
      </a>
      <a href="<?php echo APP_URL; ?>/reports/index.php" class="qa-btn">
        <div class="qa-icon" style="background:var(--purple-light);color:var(--purple);"><i class="fa-solid fa-chart-line"></i></div><span>Reports</span>
      </a>
      <a href="<?php echo APP_URL; ?>/settings/index.php?action=backup" class="qa-btn">
        <div class="qa-icon" style="background:var(--info-light);color:var(--info);"><i class="fa-solid fa-database"></i></div><span>Backup</span>
      </a>
      <a href="<?php echo APP_URL; ?>/activity_logs/index.php" class="qa-btn">
        <div class="qa-icon" style="background:var(--danger-light);color:var(--danger);"><i class="fa-solid fa-scroll"></i></div><span>Logs</span>
      </a>
    </div>
  </div>

  <!-- Low Stock -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <div class="card-title" style="color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</div>
      <a href="<?php echo APP_URL; ?>/reports/index.php?action=generate&type=low_stock" class="btn btn-secondary btn-sm">All</a>
    </div>
    <?php if(empty($lstock)): ?>
    <div style="text-align:center;padding:20px 0;">
      <i class="fa-solid fa-circle-check" style="font-size:26px;color:var(--success);display:block;margin-bottom:8px;"></i>
      <span style="font-size:12px;color:var(--text-3);">All stock levels healthy!</span>
    </div>
    <?php else: foreach($lstock as $item): ?>
    <div style="display:flex;align-items:center;gap:9px;padding:9px 0;border-bottom:1px solid var(--border);">
      <div style="width:34px;height:34px;border-radius:8px;background:var(--danger-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa-solid fa-box" style="color:var(--danger);font-size:12px;"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-1);">
          <?php echo htmlspecialchars($item['pn'].' – '.$item['vn']); ?>
        </div>
        <div style="font-size:11px;color:var(--text-3);"><?php echo number_format($item['current_stock'],2).' '.$item['unit']; ?></div>
      </div>
      <span class="badge badge-danger"><?php echo number_format($item['current_stock'],0); ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Products Table -->
<div class="table-card">
  <div class="table-toolbar">
    <div class="table-toolbar-title"><i class="fa-solid fa-box-open"></i> Recent Products</div>
    <div class="table-toolbar-actions">
      <a href="<?php echo APP_URL; ?>/products/index.php" class="btn btn-secondary btn-sm">View All</a>
      <a href="<?php echo APP_URL; ?>/products/index.php?action=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add</a>
    </div>
  </div>
  <div class="tbl-scroll">
    <table>
      <thead>
        <tr><th>#</th><th>Product</th><th>Category</th><th>Varieties</th><th class="number-col">Total Stock</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if(empty($rproducts)): ?>
        <tr class="empty-row"><td colspan="6">No products yet. <a href="<?php echo APP_URL; ?>/products/index.php?action=add" class="row-link">Add first product →</a></td></tr>
        <?php else: foreach($rproducts as $i=>$p): ?>
        <tr>
          <td style="color:var(--text-3);font-size:11px;"><?php echo $i+1; ?></td>
          <td><a href="<?php echo APP_URL; ?>/products/index.php?action=view&id=<?php echo $p['id']; ?>" class="row-link"><?php echo htmlspecialchars($p['name']); ?></a></td>
          <td><?php echo $p['category'] ? '<span class="badge badge-primary">'.htmlspecialchars($p['category']).'</span>' : '<span style="color:var(--text-3);">—</span>'; ?></td>
          <td><?php echo number_format($p['vc']); ?></td>
          <td class="number-col"><?php echo number_format($p['ts'],2); ?></td>
          <td class="actions-col">
            <a href="<?php echo APP_URL; ?>/products/index.php?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-secondary btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
            <a href="<?php echo APP_URL; ?>/products/index.php?action=view&id=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm" title="View"><i class="fa-solid fa-eye"></i></a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div><!-- /main-content -->

<script>
/* Live clock */
(function tick(){
  const n=new Date();
  const h=n.toLocaleTimeString('en-US',{hour12:false});
  const d=n.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  const ap=n.getHours()>=12?'PM':'AM';
  const c=document.getElementById('wcClock'); if(c) c.textContent=h;
  const dt=document.getElementById('wcDate'); if(dt) dt.textContent=d;
  const am=document.getElementById('wcAmpm'); if(am) am.textContent=ap;
  setTimeout(tick,1000);
})();

/* Charts */
document.addEventListener('DOMContentLoaded',()=>{
  const lbl=<?php echo json_encode($mlbl); ?>;
  const di=<?php echo json_encode($min); ?>;
  const dout=<?php echo json_encode($mout); ?>;
  const dark=document.body.classList.contains('dark-mode')||document.documentElement.classList.contains('dark-mode');
  const gc=dark?'rgba(255,255,255,0.06)':'rgba(0,0,0,0.05)';
  const tc=dark?'#64748B':'#94A3B8';
  const fonts={family:'Poppins',size:11};

  const c1=document.getElementById('stockChart');
  if(c1) new Chart(c1,{
    type:'bar',
    data:{labels:lbl,datasets:[
      {label:'Stock IN', data:di,  backgroundColor:'rgba(16,185,129,.75)',borderColor:'#10B981',borderWidth:1.5,borderRadius:4},
      {label:'Stock OUT',data:dout,backgroundColor:'rgba(239,68,68,.75)', borderColor:'#EF4444',borderWidth:1.5,borderRadius:4}
    ]},
    options:{responsive:true,maintainAspectRatio:false,
      plugins:{legend:{labels:{color:tc,font:fonts}}},
      scales:{x:{grid:{color:gc},ticks:{color:tc,font:fonts}},
              y:{grid:{color:gc},ticks:{color:tc,font:fonts},beginAtZero:true}}}
  });

  const c2=document.getElementById('distChart');
  const totalIn=di.reduce((a,b)=>a+b,0), totalOut=dout.reduce((a,b)=>a+b,0);
  if(c2) new Chart(c2,{
    type:'doughnut',
    data:{labels:['Stock IN','Stock OUT','Current'],
      datasets:[{data:[totalIn,totalOut,Math.max(<?php echo (float)$cs; ?>,0)],
        backgroundColor:['rgba(16,185,129,.8)','rgba(239,68,68,.8)','rgba(37,99,235,.8)'],
        borderColor:['#10B981','#EF4444','#2563EB'],borderWidth:2,hoverOffset:6}]},
    options:{responsive:true,maintainAspectRatio:false,cutout:'65%',
      plugins:{legend:{position:'bottom',labels:{color:tc,font:fonts,padding:10}}}}
  });
});
</script>

<?php require __DIR__.'/includes/footer.php'; ?>

