<?php
if(!function_exists('getDB')){ require_once __DIR__.'/../config/database.php'; }
$sDb = getDB();
$curPath = $_SERVER['REQUEST_URI'] ?? '';
function isActive($p){ global $curPath; return strpos($curPath,$p)!==false ? 'active' : ''; }
?>
<nav class="sidebar" id="sidebar">

  <div class="sidebar-header">
    <div class="logo-wrap">
      <div class="logo-icon-box"><i class="fa-solid fa-boxes-stacked"></i></div>
      <div>
        <div class="logo-text">InvenPro</div>
        <div class="logo-sub">Management System</div>
      </div>
    </div>
    <button class="sidebar-close" id="sidebarClose" aria-label="Close">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <ul class="sidebar-menu">
    <li class="sidebar-section-label">Main Menu</li>

    <li>
      <a href="<?php echo APP_URL; ?>/dashboard.php"
         class="nav-item <?php echo isActive('/dashboard'); ?>">
        <i class="nav-icon fa-solid fa-gauge-high"></i>
        <span>Dashboard</span>
      </a>
    </li>

    <li>
      <a href="<?php echo APP_URL; ?>/products/index.php"
         class="nav-item <?php echo isActive('/products'); ?>">
        <i class="nav-icon fa-solid fa-box-open"></i>
        <span>Products</span>
      </a>
    </li>

    <li>
      <a href="<?php echo APP_URL; ?>/customers/index.php"
         class="nav-item <?php echo isActive('/customers'); ?>">
        <i class="nav-icon fa-solid fa-users"></i>
        <span>Customers</span>
      </a>
    </li>

    <li>
      <a href="<?php echo APP_URL; ?>/suppliers/index.php"
         class="nav-item <?php echo isActive('/suppliers'); ?>">
        <i class="nav-icon fa-solid fa-truck-ramp-box"></i>
        <span>Suppliers</span>
      </a>
    </li>

    <!-- Ledgers submenu -->
    <li class="has-submenu <?php echo isActive('/ledger') ? 'open' : ''; ?>">
      <a href="#" class="nav-item <?php echo isActive('/ledger'); ?>">
        <i class="nav-icon fa-solid fa-book-open"></i>
        <span>Ledgers</span>
      </a>
      <ul class="submenu">
        <?php
        $sRes = $sDb->query("SELECT p.id,p.name FROM products p JOIN product_varieties pv ON p.id=pv.product_id GROUP BY p.id ORDER BY p.name ASC LIMIT 30");
        if($sRes && $sRes->num_rows > 0){
          while($p = $sRes->fetch_assoc()){
            echo '<li class="has-submenu">';
            echo '<a href="'.APP_URL.'/products/index.php?action=view&id='.$p['id'].'" class="nav-item"><i class="nav-icon fa-regular fa-folder"></i>'.htmlspecialchars($p['name']).'</a>';
            echo '<ul class="submenu submenu-varieties">';
            $vs = $sDb->prepare("SELECT id,name FROM product_varieties WHERE product_id=? ORDER BY name ASC LIMIT 12");
            $vs->bind_param('i',$p['id']); $vs->execute();
            $vr = $vs->get_result();
            while($v = $vr->fetch_assoc()){
              echo '<li><a href="'.APP_URL.'/ledger/index.php?variety_id='.$v['id'].'" class="nav-item">'
                  .'<i class="nav-icon fa-solid fa-circle-dot" style="font-size:7px;"></i>'
                  .htmlspecialchars($v['name']).'</a></li>';
            }
            $vs->close();
            echo '</ul></li>';
          }
        }
        ?>
      </ul>
    </li>

    <li class="sidebar-section-label">Analytics</li>

    <li>
      <a href="<?php echo APP_URL; ?>/reports/index.php"
         class="nav-item <?php echo isActive('/reports'); ?>">
        <i class="nav-icon fa-solid fa-chart-line"></i>
        <span>Reports</span>
      </a>
    </li>

    <li class="sidebar-section-label">System</li>

    <li>
      <a href="<?php echo APP_URL; ?>/tools/import_products.php"
         class="nav-item <?php echo isActive('/import_products'); ?>">
        <i class="nav-icon fa-solid fa-file-import"></i>
        <span>Import Stock</span>
      </a>
    </li>

    <li>
      <a href="<?php echo APP_URL; ?>/activity_logs/index.php"
         class="nav-item <?php echo isActive('/activity_logs'); ?>">
        <i class="nav-icon fa-solid fa-scroll"></i>
        <span>Activity Log</span>
      </a>
    </li>

    <li>
      <a href="<?php echo APP_URL; ?>/settings/index.php"
         class="nav-item <?php echo isActive('/settings'); ?>">
        <i class="nav-icon fa-solid fa-sliders"></i>
        <span>Settings</span>
      </a>
    </li>
  </ul>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?php echo strtoupper(substr($_SESSION['full_name']??'U',0,1)); ?>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="sidebar-username"><?php echo htmlspecialchars($_SESSION['full_name']??'User'); ?></div>
        <div class="sidebar-role">Administrator</div>
      </div>
      <a href="<?php echo APP_URL; ?>/logout.php"
         style="color:#64748B;font-size:14px;flex-shrink:0;" title="Logout">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </div>

</nav>
<?php $sDb->close(); ?>

