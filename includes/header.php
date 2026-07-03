<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo defined('PAGE_TITLE') ? PAGE_TITLE.' – ' : ''; ?><?php echo APP_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?php echo APP_URL; ?>/css/style.css">
  <link rel="icon" href="<?php echo APP_URL; ?>/images/favicon.ico">
  <script>window.BASE_URL='<?php echo APP_URL; ?>';</script>
</head>
<?php
  if(session_status()===PHP_SESSION_NONE) {
    session_start();
  }
  if(!function_exists('getDB')){ require_once __DIR__.'/../config/database.php'; }
  $isDark = !empty($_SESSION['dark_mode']);
  // Debug: log navigation and session info to backups/nav_debug.log (temporary)
  try {
    $nf = __DIR__ . '/../backups/nav_debug.log';
    $data = date('Y-m-d H:i:s') . "\t" . ($_SERVER['REQUEST_METHOD']??'-') . "\t" . ($_SERVER['REQUEST_URI']??'-') . "\t" . (isset($_SESSION['user_id'])?$_SESSION['user_id']:'no-session') . "\t" . ($_SERVER['HTTP_REFERER']??'-') . "\n";
    @file_put_contents($nf, $data, FILE_APPEND | LOCK_EX);
  } catch (Exception $e) {}
?>
<script>
(function(){
  var s=localStorage.getItem('inventory_theme');
  var d=s?s==='dark':<?php echo $isDark?'true':'false';?>;
  if(d) document.documentElement.classList.add('dark-mode');
})();
</script>
<body<?php echo $isDark?' class="dark-mode"':'';?>>

<!-- Page Loader -->
<div id="pageLoader"><div class="loader-spinner"></div></div>

<script>
// Fallback: ensure page loader is removed even if main.js fails to load
document.addEventListener('DOMContentLoaded', function(){
  var l = document.getElementById('pageLoader');
  if(!l) return;
  // hide then remove shortly after
  l.classList.add('hidden');
  setTimeout(function(){ if(l.parentNode) l.parentNode.removeChild(l); }, 600);
});
</script>

<!-- Toast Container -->
<div id="toastContainer"></div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-icon danger"><i class="fa-solid fa-trash-can"></i></div>
    <div class="modal-title">Confirm Delete</div>
    <div class="modal-desc" id="deleteModalDesc">Are you sure? This action cannot be undone.</div>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal('deleteModal')"><i class="fa-solid fa-xmark"></i> Cancel</button>
      <a href="#" id="deleteModalConfirm" class="btn btn-danger"><i class="fa-solid fa-trash-can"></i> Delete</a>
    </div>
  </div>
</div>

<div class="app-layout">
<?php require __DIR__.'/sidebar.php'; ?>

<div class="main-area">
<!-- ============ TOP NAV ============ -->
<div class="top-nav">

  <!-- Mobile hamburger -->
  <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
    <i class="fa-solid fa-bars"></i>
  </button>

  <!-- Global Search -->
  <div class="global-search-wrap">
    <i class="fa-solid fa-magnifying-glass search-icon-pos"></i>
    <input type="text" id="quickSearchInput"
           placeholder="Search products, customers, suppliers…"
           autocomplete="off" aria-label="Global search">
    <div id="searchResults"></div>
  </div>

  <!-- Right actions -->
  <div class="topbar-right">

    <button class="icon-btn" id="themeToggle" title="Toggle theme">
      <i class="fa-solid fa-moon" id="themeIcon"></i>
    </button>

    <button class="icon-btn" id="fullscreenBtn" title="Fullscreen">
      <i class="fa-solid fa-expand"></i>
    </button>

    <a href="<?php echo APP_URL; ?>/settings/index.php" class="icon-btn" title="Settings">
      <i class="fa-solid fa-gear"></i>
    </a>

    <!-- User dropdown -->
    <div class="user-menu">
      <div class="user-menu-btn" id="userMenuBtn">
        <div class="user-avatar-sm"><?php echo strtoupper(substr($_SESSION['full_name']??'U',0,1)); ?></div>
        <div style="line-height:1.3;">
          <div class="user-name-text"><?php echo htmlspecialchars($_SESSION['full_name']??'User'); ?></div>
          <div class="user-role-text">Administrator</div>
        </div>
        <i class="fa-solid fa-chevron-down user-chevron"></i>
      </div>
      <div class="user-dropdown" id="userDropdown">
        <div class="udrop-head">
          <div class="ud-name"><?php echo htmlspecialchars($_SESSION['full_name']??'User'); ?></div>
          <div class="ud-role">Administrator</div>
        </div>
        <a href="<?php echo APP_URL; ?>/settings/index.php"><i class="fa-solid fa-gear"></i> Settings</a>
        <a href="<?php echo APP_URL; ?>/activity_logs/index.php"><i class="fa-solid fa-scroll"></i> Activity Log</a>
        <a href="<?php echo APP_URL; ?>/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
      </div>
    </div>

  </div>
</div><!-- /top-nav -->

<?php if(isset($_SESSION['success'])): ?>
<script>document.addEventListener('DOMContentLoaded',function(){showToast('success','Success','<?php echo addslashes(htmlspecialchars($_SESSION['success'])); ?>');});</script>
<?php unset($_SESSION['success']); endif; ?>
<?php if(isset($_SESSION['error'])): ?>
<script>document.addEventListener('DOMContentLoaded',function(){showToast('error','Error','<?php echo addslashes(htmlspecialchars($_SESSION['error'])); ?>');});</script>
<?php unset($_SESSION['error']); endif; ?>

