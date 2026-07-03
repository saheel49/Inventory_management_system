<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_login();

$db = getDB();
$errors = []; $success = '';
$nm = $ph = $em = $ad = $nt = '';
$search  = trim($_GET['search'] ?? '');
$editing = false;
$eid     = 0;
$ref     = $_GET['ref'] ?? '';

/* ──────────────── LOAD FOR EDIT ──────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $eid = (int)$_GET['id'];
    $st  = $db->prepare('SELECT * FROM suppliers WHERE id=? LIMIT 1');
    $st->bind_param('i',$eid); $st->execute();
    $s   = $st->get_result()->fetch_assoc(); $st->close();
    if ($s) {
        $editing = true;
        $nm = $s['name']; $ph = $s['phone']; $em = $s['email'];
        $ad = $s['address']; $nt = $s['notes'];
    }
}

/* ──────────────── DELETE ──────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $sid = (int)$_GET['id'];
    $st  = $db->prepare('SELECT name FROM suppliers WHERE id=? LIMIT 1');
    $st->bind_param('i',$sid); $st->execute();
    $s   = $st->get_result()->fetch_assoc(); $st->close();
    if ($s) {
        $st = $db->prepare('DELETE FROM suppliers WHERE id=?');
        $st->bind_param('i',$sid); $st->execute(); $st->close();
        log_activity('supplier_delete','Deleted supplier: '.$s['name']);
        $_SESSION['success'] = 'Supplier "'.$s['name'].'" deleted.';
    } else {
        $_SESSION['error'] = 'Supplier not found.';
    }
    $db->close();
    header('Location: index.php'); exit;
}

/* ──────────────── SAVE / UPDATE ──────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nm  = trim($_POST['name']    ?? '');
    $ph  = trim($_POST['phone']   ?? '');
    $em  = trim($_POST['email']   ?? '');
    $ad  = trim($_POST['address'] ?? '');
    $nt  = trim($_POST['notes']   ?? '');
    $ref = $_POST['ref'] ?? '';

    if (!$nm) {
        $errors[] = 'Supplier name is required.';
    } else {
        if ($eid > 0) {
            $ds = $db->prepare('SELECT COUNT(*) c FROM suppliers WHERE LOWER(name)=LOWER(?) AND id!=?');
            $ds->bind_param('si',$nm,$eid);
        } else {
            $ds = $db->prepare('SELECT COUNT(*) c FROM suppliers WHERE LOWER(name)=LOWER(?)');
            $ds->bind_param('s',$nm);
        }
        $ds->execute();
        if ($ds->get_result()->fetch_assoc()['c'] > 0) $errors[] = 'A supplier with that name already exists.';
        $ds->close();
    }

    if (empty($errors)) {
        if ($eid > 0) {
            $st = $db->prepare('UPDATE suppliers SET name=?,phone=?,email=?,address=?,notes=? WHERE id=?');
            $st->bind_param('sssssi',$nm,$ph,$em,$ad,$nt,$eid);
            $st->execute(); $st->close();
            log_activity('supplier_edit','Updated supplier: '.$nm);
            $_SESSION['success'] = 'Supplier updated successfully.';
            $db->close();
            if ($ref === 'view') {
                header('Location: view.php?id='.$eid); exit;
            }
            header('Location: index.php'); exit;
        } else {
            $st = $db->prepare('INSERT INTO suppliers (name,phone,email,address,notes) VALUES (?,?,?,?,?)');
            $st->bind_param('sssss',$nm,$ph,$em,$ad,$nt);
            $st->execute();
            $new_id = $db->insert_id;
            $st->close();
            log_activity('supplier_add','Added supplier: '.$nm);
            $_SESSION['success'] = 'Supplier added successfully.';
            $db->close();
            header('Location: view.php?id='.$new_id); exit;
        }
    }
}

/* ──────────────── LIST ──────────────── */
if ($search !== '') {
    $sp = '%'.$search.'%';
    $st = $db->prepare('SELECT * FROM suppliers WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ? OR notes LIKE ? ORDER BY name ASC');
    $st->bind_param('sssss',$sp,$sp,$sp,$sp,$sp);
    $st->execute(); $result = $st->get_result(); $st->close();
} else {
    $result = $db->query('SELECT * FROM suppliers ORDER BY name ASC');
}

require __DIR__.'/../includes/header.php';
?>
<div class="main-content">

<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-truck-ramp-box"></i> Suppliers</h1>
    <div class="breadcrumb">
      <a href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a>
      <span class="sep">/</span><span>Suppliers</span>
    </div>
  </div>
  <div class="header-actions">
    <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-house"></i> Dashboard
    </a>
  </div>
</div>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success" data-autodismiss="4000">
  <i class="fa-solid fa-circle-check"></i>
  <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
  <button class="alert-close"><i class="fa-solid fa-xmark"></i></button>
</div>
<?php endif; ?>
<?php if(isset($_SESSION['error'])): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-circle-xmark"></i>
  <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
  <button class="alert-close"><i class="fa-solid fa-xmark"></i></button>
</div>
<?php endif; ?>
<?php if(!empty($errors)): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-circle-xmark"></i>
  <div><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div>
  <button class="alert-close"><i class="fa-solid fa-xmark"></i></button>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;" class="sup-layout">

  <!-- ── ADD / EDIT FORM ── -->
  <div class="card" style="margin-bottom:0;position:sticky;top:76px;">
    <div class="card-header">
      <div class="card-title">
        <i class="fa-solid fa-<?php echo $editing ? 'pen' : 'truck'; ?>"></i>
        <?php echo $editing ? 'Edit Supplier' : 'Add New Supplier'; ?>
      </div>
      <?php if($editing): ?>
      <a href="index.php" class="btn btn-secondary btn-sm" title="Cancel edit">
        <i class="fa-solid fa-xmark"></i>
      </a>
      <?php endif; ?>
    </div>

    <form method="POST" action="<?php echo $editing
        ? '?action=edit&id='.$eid.'&search='.urlencode($search)
        : ''; ?>">
      <?php if($editing): ?>
        <input type="hidden" name="id"  value="<?php echo $eid; ?>">
        <input type="hidden" name="ref" value="<?php echo htmlspecialchars($ref); ?>">
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label">Name <span class="req">*</span></label>
        <div class="input-wrap">
          <i class="fi fa-solid fa-building"></i>
          <input type="text" name="name" class="form-control" required
                 value="<?php echo htmlspecialchars($nm); ?>" placeholder="Supplier name" autofocus>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Phone</label>
        <div class="input-wrap">
          <i class="fi fa-solid fa-phone"></i>
          <input type="text" name="phone" class="form-control"
                 value="<?php echo htmlspecialchars($ph); ?>" placeholder="Phone number">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <div class="input-wrap">
          <i class="fi fa-solid fa-envelope"></i>
          <input type="email" name="email" class="form-control"
                 value="<?php echo htmlspecialchars($em); ?>" placeholder="Email address">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2"
                  placeholder="Address"><?php echo htmlspecialchars($ad); ?></textarea>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"
                  placeholder="Notes"><?php echo htmlspecialchars($nt); ?></textarea>
      </div>
      <div style="margin-top:16px;display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">
          <i class="fa-solid fa-floppy-disk"></i>
          <?php echo $editing ? 'Update Supplier' : 'Save Supplier'; ?>
        </button>
        <?php if($editing): ?>
        <a href="view.php?id=<?php echo $eid; ?>" class="btn btn-info btn-sm" title="View profile">
          <i class="fa-solid fa-eye"></i>
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ── TABLE PANEL ── -->
  <div>

    <!-- Search -->
    <div class="card" style="margin-bottom:14px;padding:14px 18px;">
      <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div class="autocomplete page-autocomplete" data-search-type="supplier"
             style="flex:1;min-width:200px;position:relative;">
          <div class="input-wrap">
            <i class="fi fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" class="form-control"
                   placeholder="Search by name, phone, email…"
                   value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
          </div>
          <div class="autocomplete-list"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-search"></i> Search
        </button>
        <?php if($search !== ''): ?>
        <a href="index.php" class="btn btn-secondary btn-sm">
          <i class="fa-solid fa-xmark"></i> Clear
        </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div class="table-toolbar">
        <div class="table-toolbar-title">
          <i class="fa-solid fa-list"></i> Supplier List
        </div>
        <div class="table-toolbar-actions">
          <span style="font-size:11px;color:var(--text-3);">
            <?php echo mysqli_num_rows($result); ?> record(s)
          </span>
        </div>
      </div>

      <div class="tbl-scroll">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Phone</th>
              <th>Email</th>
              <th>Address</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $found = false; while($row = $result->fetch_assoc()): $found = true; ?>
            <tr>
              <td>
                <a href="view.php?id=<?php echo $row['id']; ?>" class="row-link">
                  <i class="fa-solid fa-building" style="margin-right:5px;font-size:10px;opacity:.5;"></i>
                  <?php echo htmlspecialchars($row['name']); ?>
                </a>
              </td>
              <td><?php echo htmlspecialchars($row['phone']) ?: '<span style="color:var(--text-3);">—</span>'; ?></td>
              <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?php echo htmlspecialchars($row['email']) ?: '<span style="color:var(--text-3);">—</span>'; ?>
              </td>
              <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?php echo htmlspecialchars($row['address']) ?: '<span style="color:var(--text-3);">—</span>'; ?>
              </td>
              <td class="actions-col" style="white-space:nowrap;">
                <a href="view.php?id=<?php echo $row['id']; ?>"
                   class="btn btn-info btn-sm" title="View Profile">
                  <i class="fa-solid fa-eye"></i>
                </a>
                <a href="?action=edit&id=<?php echo $row['id']; ?>&search=<?php echo urlencode($search); ?>"
                   class="btn btn-secondary btn-sm" title="Edit">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <a href="transaction.php?id=<?php echo $row['id']; ?>"
                   class="btn btn-success btn-sm" title="New Stock IN">
                  <i class="fa-solid fa-plus"></i>
                </a>
                <a href="?action=delete&id=<?php echo $row['id']; ?>"
                   class="btn btn-danger btn-sm" title="Delete"
                   onclick="return confirmDelete(this.href,'Delete supplier &quot;<?php echo addslashes(htmlspecialchars($row['name'])); ?>&quot; and all their history?');return false;">
                  <i class="fa-solid fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if(!$found): ?>
            <tr class="empty-row">
              <td colspan="5">
                No suppliers found<?php echo $search ? ' for "'.htmlspecialchars($search).'"' : ''; ?>.
                <?php if(!$search): ?>Use the form on the left to add your first supplier.<?php endif; ?>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

</div>
<style>@media(max-width:900px){.sup-layout{grid-template-columns:1fr!important;}}</style>
<?php require __DIR__.'/../includes/footer.php'; ?>
