<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();
$action=$_GET['action']??'index';
if($action==='update') doUpdate();
elseif($action==='backup') doBackup();
else doIndex();

function doIndex(){
  $db=getDB();
  $cfg=[]; $r=$db->query("SELECT * FROM settings");
  while($row=$r->fetch_assoc()) $cfg[$row['setting_key']]=$row['setting_value'];
  $db->close();
  require __DIR__.'/../includes/header.php';
?>
<div class="main-content">
<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-sliders"></i> Settings</h1>
    <div class="breadcrumb"><a href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span>Settings</span></div>
  </div>
  <div class="header-actions">
    <a href="?action=backup" class="btn btn-secondary btn-sm"><i class="fa-solid fa-download"></i> Backup DB</a>
    <button form="settingsForm" type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
  </div>
</div>

<form method="POST" action="?action=update" id="settingsForm">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="settings-layout">

  <!-- General -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header"><div class="card-title"><i class="fa-solid fa-building"></i> General</div></div>
    <div class="form-group"><label class="form-label">Company Name</label>
      <div class="input-wrap"><i class="fi fa-solid fa-building"></i>
      <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($cfg['company_name']??''); ?>"></div></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group"><label class="form-label">Currency Code</label>
        <input type="text" name="currency" class="form-control" value="<?php echo htmlspecialchars($cfg['currency']??'USD'); ?>" maxlength="3"></div>
      <div class="form-group"><label class="form-label">Currency Symbol</label>
        <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars($cfg['currency_symbol']??'$'); ?>" maxlength="3"></div>
    </div>
    <div class="form-group"><label class="form-label">Date Format</label>
      <select name="date_format" class="form-control">
        <option value="Y-m-d" <?php echo($cfg['date_format']??'')==='Y-m-d'?'selected':''; ?>>YYYY-MM-DD</option>
        <option value="d/m/Y" <?php echo($cfg['date_format']??'')==='d/m/Y'?'selected':''; ?>>DD/MM/YYYY</option>
        <option value="m/d/Y" <?php echo($cfg['date_format']??'')==='m/d/Y'?'selected':''; ?>>MM/DD/YYYY</option>
      </select></div>
    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Rows Per Page</label>
      <select name="rows_per_page" class="form-control">
        <?php foreach([10,25,50,100] as $n): ?>
        <option value="<?php echo $n; ?>" <?php echo($cfg['rows_per_page']??'25')==(string)$n?'selected':''; ?>><?php echo $n; ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>

  <!-- Appearance + Database -->
  <div>
    <div class="card" style="margin-bottom:18px;">
      <div class="card-header"><div class="card-title"><i class="fa-solid fa-palette"></i> Appearance</div></div>
      <div class="toggle-wrap">
        <label class="toggle-switch">
          <input type="checkbox" name="dark_mode" value="1" id="dmToggle" <?php echo($cfg['dark_mode']??'0')==='1'?'checked':''; ?>>
          <span class="toggle-slider"></span>
        </label>
        <span class="toggle-label">Dark Mode</span>
      </div>
      <p class="form-hint" style="margin-top:8px;">Dark mode preference is also saved in your browser's localStorage.</p>
    </div>
    <div class="card" style="margin-bottom:0;">
      <div class="card-header"><div class="card-title"><i class="fa-solid fa-database"></i> Database Backup</div></div>
      <p style="font-size:13px;color:var(--text-2);margin-bottom:16px;">Export a full SQL backup or restore from a previous file.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="?action=backup" class="btn btn-primary btn-sm"><i class="fa-solid fa-download"></i> Download Backup</a>
        <label class="btn btn-secondary btn-sm" style="cursor:pointer;"><i class="fa-solid fa-upload"></i> Restore
          <input type="file" name="restore_file" accept=".sql" style="display:none;"></label>
      </div>
    </div>
  </div>
</div>

<div style="margin-top:20px;display:flex;justify-content:flex-end;">
  <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save All Settings</button>
</div>
</form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var dm = document.getElementById('dmToggle');
  if(!dm) return;
  function applyLocalTheme(theme){
    var dark = theme === 'dark';
    document.documentElement.classList.toggle('dark-mode', dark);
    document.body.classList.toggle('dark-mode', dark);
    var icon = document.getElementById('themeIcon');
    var btn  = document.getElementById('themeToggle');
    if(icon) icon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    if(btn) btn.title = dark ? 'Switch to Light Mode' : 'Switch to Dark Mode';
    try{ localStorage.setItem('inventory_theme', theme); }catch(e){}
  }
  dm.addEventListener('change', function(){
    var theme = dm.checked ? 'dark' : 'light';
    applyLocalTheme(theme);
    // update server session + user preference immediately
    fetch(window.BASE_URL + '/tools/toggle_theme.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ theme: theme })
    }).catch(function(){});
  });
});
</script>
<?php require __DIR__.'/../includes/footer.php';
}

function doUpdate(){
  $db=getDB();
  $map=[
    'company_name'  => trim($_POST['company_name']??''),
    'currency'      => strtoupper(trim($_POST['currency']??'USD')),
    'currency_symbol'=> trim($_POST['currency_symbol']??'$'),
    'date_format'   => $_POST['date_format']??'Y-m-d',
    'rows_per_page' => $_POST['rows_per_page']??'25',
    'dark_mode'     => isset($_POST['dark_mode'])?'1':'0',
  ];
  $st=$db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
  foreach($map as $k=>$v){ $st->bind_param('ss',$v,$k); $st->execute(); }
  $st->close(); $db->close();
  log_activity('settings_update','Updated settings');
  // Persist preference to session so server-side rendering honors it
  $_SESSION['dark_mode'] = ($map['dark_mode']==='1') ? 1 : 0;
  // Return a small response that updates localStorage then redirects
  $theme = $_SESSION['dark_mode'] ? 'dark' : 'light';
  $_SESSION['success']='Settings saved successfully.';
  $db->close();
  echo "<!doctype html><html><head><meta charset=\"utf-8\"></head><body><script>try{localStorage.setItem('inventory_theme', '". $theme ."');}catch(e){}window.location='index.php';</script></body></html>";
  exit;
}

function doBackup(){
  $db=getDB();
  $fn='backup_'.date('Ymd_His').'.sql';
  $fp=BACKUP_DIR.$fn;
  if(!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR,0755,true);
  $tables=$db->query("SHOW TABLES")->fetch_all(MYSQLI_NUM);
  $out="-- InvenPro Backup ".date('Y-m-d H:i:s')."\n\n";
  foreach($tables as $t){
    $tn=$t[0]; $out.="DROP TABLE IF EXISTS `$tn`;\n";
    $cr=$db->query("SHOW CREATE TABLE `$tn`")->fetch_row();
    $out.=$cr[1].";\n\n";
    $rows=$db->query("SELECT * FROM `$tn`");
    while($row=$rows->fetch_assoc()){
      $vals=array_map(fn($v)=>$v===null?'NULL':"'".$db->real_escape_string($v)."'",array_values($row));
      $out.="INSERT INTO `$tn` VALUES (".implode(',',$vals).");\n";
    }
    $out.="\n";
  }
  file_put_contents($fp,$out);
  log_activity('backup','Backup: '.$fn);
  $db->close();
  header('Content-Type: application/sql');
  header('Content-Disposition: attachment; filename="'.$fn.'"');
  header('Content-Length: '.filesize($fp));
  readfile($fp); exit;
}
?>

