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
$ref     = $_GET['ref'] ?? ''; // 'view' = came from view page, redirect back

/* ──────────────── LOAD FOR EDIT ──────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $eid = (int)$_GET['id'];
    $st  = $db->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');
    $st->bind_param('i',$eid); $st->execute();
    $c   = $st->get_result()->fetch_assoc(); $st->close();
    if ($c) {
        $editing = true;
        $nm = $c['name']; $ph = $c['phone']; $em = $c['email'];
        $ad = $c['address']; $nt = $c['notes'];
    }
}

/* ──────────────── DELETE ──────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $cid = (int)$_GET['id'];
    $st  = $db->prepare('SELECT name FROM customers WHERE id=? LIMIT 1');
    $st->bind_param('i',$cid); $st->execute();
    $c   = $st->get_result()->fetch_assoc(); $st->close();
    if ($c) {
        $st = $db->prepare('DELETE FROM customers WHERE id=?');
        $st->bind_param('i',$cid); $st->execute(); $st->close();
        log_activity('customer_delete','Deleted customer: '.$c['name']);
        $_SESSION['success'] = 'Customer "'.htmlspecialchars($c['name']).'" deleted.';
    } else {
        $_SESSION['error'] = 'Customer not found.';
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
        $errors[] = 'Customer name is required.';
    } else {
        if ($eid > 0) {
            $ds = $db->prepare('SELECT COUNT(*) c FROM customers WHERE LOWER(name)=LOWER(?) AND id!=?');
            $ds->bind_param('si',$nm,$eid);
        } else {
            $ds = $db->prepare('SELECT COUNT(*) c FROM customers WHERE LOWER(name)=LOWER(?)');
            $ds->bind_param('s',$nm);
        }
        $ds->execute();
        if ($ds->get_result()->fetch_assoc()['c'] > 0) $errors[] = 'A customer with that name already exists.';
        $ds->close();
    }

    if (empty($errors)) {
        if ($eid > 0) {
            $st = $db->prepare('UPDATE customers SET name=?,phone=?,email=?,address=?,notes=? WHERE id=?');
            $st->bind_param('sssssi',$nm,$ph,$em,$ad,$nt,$eid);
            $st->execute(); $st->close();
            log_activity('customer_edit','Updated customer: '.$nm);
            $_SESSION['success'] = 'Customer updated successfully.';
            $db->close();
            // Redirect back to view page if came from there
            if ($ref === 'view') {
                header('Location: view.php?id='.$eid); exit;
            }
            header('Location: index.php'); exit;
        } else {
            $st = $db->prepare('INSERT INTO customers (name,phone,email,address,notes) VALUES (?,?,?,?,?)');
            $st->bind_param('sssss',$nm,$ph,$em,$ad,$nt);
            $st->execute();
            $new_id = $db->insert_id;
            $st->close();
            log_activity('customer_add','Added customer: '.$nm);
            $_SESSION['success'] = 'Customer added successfully.';
            $db->close();
            header('Location: view.php?id='.$new_id); exit;
        }
    }
}

/* ──────────────── LIST / SEARCH ──────────────── */
if ($search !== '') {
    $sp = '%'.$search.'%';
    $st = $db->prepare('SELECT * FROM customers WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ? OR notes LIKE ? ORDER BY name ASC');
    $st->bind_param('sssss',$sp,$sp,$sp,$sp,$sp);
    $st->execute(); $result = $st->get_result(); $st->close();
} else {
    $result = $db->query('SELECT * FROM customers ORDER BY name ASC');
}

require __DIR__.'/../includes/header.php';
?>
<div class="main-content">

<!-- Page Header -->
<div class="page-header">
  <div class="page-header-left">
    <h1><i class="fa-solid fa-users"></i> Customers</h1>
    <div class="breadcrumb">
      <a href="<?php echo APP_URL; ?>/dashboard.php">Dashboard</a>
      <span class="sep">/</span><span>Customers</span>
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

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;" class="cust-layout">

  <!-- ── ADD / EDIT FORM ── -->
  <div class="card" style="margin-bottom:0;position:sticky;top:76px;">
    <div class="card-header">
      <div class="card-title">
        <i class="fa-solid fa-<?php echo $editing ? 'pen' : 'user-plus'; ?>"></i>
        <?php echo $editing ? 'Edit Customer' : 'Add New Customer'; ?>
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
          <i class="fi fa-solid fa-user"></i>
          <input type="text" name="name" class="form-control" required
                 value="<?php echo htmlspecialchars($nm); ?>" placeholder="Customer name" autofocus>
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
          <?php echo $editing ? 'Update Customer' : 'Save Customer'; ?>
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
        <div class="autocomplete page-autocomplete" data-search-type="customer"
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
          <i class="fa-solid fa-list"></i> Customer List
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
                  <i class="fa-solid fa-user" style="margin-right:5px;font-size:10px;opacity:.5;"></i>
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
                <!-- View -->
                <a href="view.php?id=<?php echo $row['id']; ?>"
                   class="btn btn-info btn-sm" title="View Profile">
                  <i class="fa-solid fa-eye"></i>
                </a>
                <!-- Edit -->
                <a href="?action=edit&id=<?php echo $row['id']; ?>&search=<?php echo urlencode($search); ?>"
                   class="btn btn-secondary btn-sm" title="Edit">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <!-- Transaction -->
                <a href="transaction.php?id=<?php echo $row['id']; ?>"
                   class="btn btn-primary btn-sm" title="New Transaction">
                  <i class="fa-solid fa-plus"></i>
                </a>
                <!-- Delete -->
                <a href="?action=delete&id=<?php echo $row['id']; ?>"
                   class="btn btn-danger btn-sm" title="Delete"
                   onclick="return confirmDelete(this.href,'Delete customer &quot;<?php echo addslashes(htmlspecialchars($row['name'])); ?>&quot; and all their transaction history?');return false;">
                  <i class="fa-solid fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if(!$found): ?>
            <tr class="empty-row">
              <td colspan="5">
                No customers found<?php echo $search ? ' for "'.htmlspecialchars($search).'"' : ''; ?>.
                <?php if(!$search): ?>
                  Use the form on the left to add your first customer.
                <?php endif; ?>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div><!-- /tbl-scroll -->
    </div><!-- /table-card -->

  </div><!-- /table panel -->
</div><!-- /grid -->

</div><!-- /main-content -->
<style>@media(max-width:900px){.cust-layout{grid-template-columns:1fr!important;}}</style>
<?php require __DIR__.'/../includes/footer.php'; ?>
