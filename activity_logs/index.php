<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();
$db=getDB();
$af=$_GET['action_filter']??''; $df=$_GET['date_from']??''; $dt=$_GET['date_to']??''; $sr=$_GET['search']??'';
$pg=isset($_GET['page'])?(int)$_GET['page']:1;
$lim=(int)(get_setting('rows_per_page')?:25); $off=($pg-1)*$lim;
$w='WHERE 1=1'; $p=[]; $tp='';
if($af){ $w.=' AND action=?'; $p[]=$af; $tp.='s'; }
if($df){ $w.=' AND DATE(created_at)>=?'; $p[]=$df; $tp.='s'; }
if($dt){ $w.=' AND DATE(created_at)<=?'; $p[]=$dt; $tp.='s'; }
if($sr){ $w.=' AND (description LIKE ? OR ip_address LIKE ?)'; $ss='%'.$sr.'%'; $p[]=$ss; $p[]=$ss; $tp.='ss'; }
$cs=$db->prepare("SELECT COUNT(*) total FROM activity_logs $w");
if($p) $cs->bind_param($tp,...$p); $cs->execute();
$total=$cs->get_result()->fetch_assoc()['total']; $cs->close();
$pages=ceil($total/$lim);
$lp=$p; $lt=$tp;
$ls=$db->prepare("SELECT l.*,u.full_name fn FROM activity_logs l LEFT JOIN users u ON l.user_id=u.id $w ORDER BY l.created_at DESC LIMIT $lim OFFSET $off");
if($lp) $ls->bind_param($lt,...$lp);
$ls->execute();
$logs=$ls->get_result()->fetch_all(MYSQLI_ASSOC); $ls->close(); $db->close();
require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-scroll"></i> Activity Log</h1>
    <div class="breadcrumb"><a href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span>Activity Log</span></div>
  </div>
  <div class="header-actions">
    <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-house"></i> Dashboard</a>
  </div>
</div>

<div class="card filter-card" style="margin-bottom:16px;">
  <form method="GET" class="filter-form">
    <select name="action_filter" class="form-control" style="min-width:160px;">
      <option value="">All Actions</option>
      <?php foreach(['login','logout','product_add','product_edit','product_delete','stock_in','stock_out','backup','password_change','customer_out','supplier_in','settings_update','transaction_delete'] as $a): ?>
      <option value="<?php echo $a; ?>" <?php echo $af===$a?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$a)); ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($df); ?>" style="min-width:130px;">
    <input type="date" name="date_to"   class="form-control" value="<?php echo htmlspecialchars($dt); ?>" style="min-width:130px;">
    <div class="input-wrap" style="flex:1;min-width:180px;">
      <i class="fi fa-solid fa-magnifying-glass"></i>
      <input type="text" name="search" class="form-control" placeholder="Search description…" value="<?php echo htmlspecialchars($sr); ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
  </form>
</div>

<div class="table-card">
  <div class="table-toolbar">
    <div class="table-toolbar-title"><i class="fa-solid fa-list"></i> Log Entries</div>
    <div class="table-toolbar-actions"><span style="font-size:11px;color:var(--text-3);"><?php echo number_format($total); ?> total</span></div>
  </div>
  <div class="tbl-scroll">
    <table>
      <thead><tr><th style="width:50px;">ID</th><th>Date / Time</th><th>User</th><th>Action</th><th>Description</th><th>IP Address</th></tr></thead>
      <tbody>
        <?php if(empty($logs)): ?>
        <tr class="empty-row"><td colspan="6">No log entries found.</td></tr>
        <?php else: foreach($logs as $log):
          $a=strtolower($log['action']);
          if(strpos($a,'login')!==false&&strpos($a,'logout')===false) $bc='login';
          elseif(strpos($a,'logout')!==false) $bc='logout';
          elseif(strpos($a,'add')!==false||strpos($a,'create')!==false||strpos($a,'in')!==false) $bc='create';
          elseif(strpos($a,'edit')!==false||strpos($a,'update')!==false||strpos($a,'settings')!==false) $bc='update';
          elseif(strpos($a,'delete')!==false||strpos($a,'out')!==false) $bc='delete';
          elseif(strpos($a,'backup')!==false||strpos($a,'export')!==false) $bc='export';
          else $bc='default';
        ?>
        <tr>
          <td style="color:var(--text-3);font-family:var(--mono);font-size:11px;"><?php echo $log['id']; ?></td>
          <td style="white-space:nowrap;font-size:12px;"><?php echo htmlspecialchars($log['created_at']); ?></td>
          <td style="font-size:12px;"><?php echo htmlspecialchars($log['fn']??'System'); ?></td>
          <td><span class="action-badge <?php echo $bc; ?>"><?php echo htmlspecialchars($log['action']); ?></span></td>
          <td style="max-width:280px;font-size:12px;"><?php echo htmlspecialchars($log['description']); ?></td>
          <td style="font-family:var(--mono);font-size:11px;color:var(--text-3);"><?php echo htmlspecialchars($log['ip_address']); ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="table-footer">
    <div class="table-info">Showing <?php echo count($logs); ?> of <?php echo number_format($total); ?></div>
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
<?php require __DIR__.'/../includes/footer.php'; ?>

