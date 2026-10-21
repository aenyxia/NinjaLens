<?php
// admin.php — NinjaLens Administrator Dashboard
require_once 'config.php';

// ── Auth Guard ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['user_name'];
$admin_id   = (int) $_SESSION['user_id'];
$page       = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$error      = '';
$success    = '';

// ============================================================
// EQUIPMENT ACTIONS
// ============================================================

// ── Add Equipment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_equipment') {
    $name     = mysqli_real_escape_string($conn, trim($_POST['eq_name']));
    $category = mysqli_real_escape_string($conn, trim($_POST['eq_category']));
    $desc     = mysqli_real_escape_string($conn, trim($_POST['eq_desc']));
    $rate     = (float) $_POST['eq_rate'];
    $status   = mysqli_real_escape_string($conn, $_POST['eq_status']);

    if (empty($name) || empty($category) || $rate <= 0) {
        $error = 'Please fill in all required fields with valid values.';
    } else {
        $sql = "INSERT INTO equipment (name, category, description, rental_rate, status)
                VALUES ('$name','$category','$desc',$rate,'$status')";
        if (mysqli_query($conn, $sql)) {
            $success = "Equipment '$name' added successfully.";
        } else {
            $error = 'Failed to add equipment: ' . mysqli_error($conn);
        }
    }
    $page = 'equipment';
}

// ── Edit Equipment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_equipment') {
    $eq_id    = (int) $_POST['eq_id'];
    $name     = mysqli_real_escape_string($conn, trim($_POST['eq_name']));
    $category = mysqli_real_escape_string($conn, trim($_POST['eq_category']));
    $desc     = mysqli_real_escape_string($conn, trim($_POST['eq_desc']));
    $rate     = (float) $_POST['eq_rate'];
    $status   = mysqli_real_escape_string($conn, $_POST['eq_status']);

    $sql = "UPDATE equipment SET name='$name', category='$category', description='$desc',
            rental_rate=$rate, status='$status' WHERE equipment_id=$eq_id";
    if (mysqli_query($conn, $sql)) {
        $success = "Equipment updated successfully.";
    } else {
        $error = 'Update failed: ' . mysqli_error($conn);
    }
    $page = 'equipment';
}

// ── Delete Equipment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_equipment') {
    $eq_id = (int) $_POST['eq_id'];
    // Only delete if not currently rented
    $check = mysqli_query($conn, "SELECT status FROM equipment WHERE equipment_id=$eq_id");
    $eq    = mysqli_fetch_assoc($check);
    if ($eq && $eq['status'] === 'Rented') {
        $error = 'Cannot delete equipment that is currently rented.';
    } else {
        mysqli_query($conn, "DELETE FROM equipment WHERE equipment_id=$eq_id");
        $success = 'Equipment deleted.';
    }
    $page = 'equipment';
}

// ── Return Equipment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_equipment') {
    $rental_id = (int) $_POST['rental_id'];
    $eq_id     = (int) $_POST['equipment_id'];
    mysqli_query($conn, "UPDATE rental SET status='Returned' WHERE rental_id=$rental_id");
    mysqli_query($conn, "UPDATE equipment SET status='Available' WHERE equipment_id=$eq_id");
    $success = 'Equipment marked as returned and status updated to Available.';
    $page = 'rentals';
}

// ── Toggle Customer Penalty ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_penalty') {
    $cust_id     = (int) $_POST['cust_id'];
    $has_penalty = (int) $_POST['current_penalty'] ? 0 : 1;
    mysqli_query($conn, "UPDATE customer SET has_penalty=$has_penalty WHERE customer_id=$cust_id");
    $success = 'Customer penalty status updated.';
    $page = 'customers';
}

// ============================================================
// DATA QUERIES
// ============================================================

// Dashboard stats
$eq_count     = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM equipment"))[0];
$cust_count   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM customer"))[0];
$active_rent  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM rental WHERE status='Active'"))[0];
$total_rev    = mysqli_fetch_row(mysqli_query($conn, "SELECT COALESCE(SUM(amount_paid),0) FROM payment WHERE payment_status='Paid'"))[0];

// Recent rentals for dashboard
$recent_sql = "SELECT r.rental_id, c.full_name, e.name AS equip_name, r.rental_date, r.return_date, r.total_amount, r.status
               FROM rental r
               JOIN customer c ON c.customer_id = r.customer_id
               JOIN equipment e ON e.equipment_id = r.equipment_id
               ORDER BY r.created_at DESC LIMIT 8";
$recent_res = mysqli_query($conn, $recent_sql);

// Equipment list
$eq_sql = "SELECT * FROM equipment ORDER BY status, category, name";
$eq_res = mysqli_query($conn, $eq_sql);

// Customers list
$cust_sql = "SELECT c.*, COUNT(r.rental_id) AS total_rentals
             FROM customer c
             LEFT JOIN rental r ON r.customer_id = c.customer_id
             GROUP BY c.customer_id
             ORDER BY c.created_at DESC";
$cust_res = mysqli_query($conn, $cust_sql);

// All rentals
$all_rent_sql = "SELECT r.*, c.full_name, e.name AS equip_name, p.payment_method, p.payment_status, p.amount_paid
                 FROM rental r
                 JOIN customer c ON c.customer_id = r.customer_id
                 JOIN equipment e ON e.equipment_id = r.equipment_id
                 LEFT JOIN payment p ON p.rental_id = r.rental_id
                 ORDER BY r.created_at DESC";
$all_rent_res = mysqli_query($conn, $all_rent_sql);

// Payment report
$pay_sql = "SELECT p.*, c.full_name, e.name AS equip_name, r.rental_date, r.return_date, r.num_days
            FROM payment p
            JOIN customer c ON c.customer_id = p.customer_id
            JOIN rental r   ON r.rental_id   = p.rental_id
            JOIN equipment e ON e.equipment_id = r.equipment_id
            ORDER BY p.paid_at DESC";
$pay_res = mysqli_query($conn, $pay_sql);

// Category options
$categories = ['Camera Body','Lens','Drone','Stabilizer','Lighting','Audio','Tripod','Monitor/Rec','Accessories'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — NinjaLens</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .sidebar-toggle { display: none; }
    .admin-overlay  {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 99;
    }
    .admin-overlay.open { display: block; }

    .stat-grid-admin {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .chart-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .chart-placeholder {
      height: 200px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--gray-dark);
      font-size: 0.875rem;
      flex-direction: column;
      gap: 0.5rem;
    }

    /* Simple bar chart */
    .mini-bar-chart {
      display: flex;
      align-items: flex-end;
      gap: 0.4rem;
      height: 120px;
      padding: 1rem 0 0;
    }
    .bar-col {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.4rem;
    }
    .bar {
      width: 100%;
      background: rgba(255,59,59,0.5);
      border-radius: 4px 4px 0 0;
      min-height: 4px;
      transition: height 0.8s ease;
    }
    .bar-label { font-size: 0.6rem; color: var(--gray-dark); }

    @media (max-width: 900px) {
      .sidebar-toggle { display: flex !important; }
      .chart-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
      .stat-grid-admin { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<div class="admin-layout">

  <!-- ── SIDEBAR ── -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-logo">
      <img src="images/ninjalens_logo.png" alt="NinjaLens Logo" style="height:50px; width:70px; vertical-align:middle;">
      Ninja<span class="accent">Lens</span>
    </a>
    </div>

    <nav class="sidebar-nav">
      <p class="sidebar-section">Main</p>
      <a href="?page=dashboard"  class="sidebar-link <?= $page === 'dashboard'  ? 'active' : '' ?>">
        <span class="icon">📊</span> Dashboard
      </a>
      <a href="?page=equipment"  class="sidebar-link <?= $page === 'equipment'  ? 'active' : '' ?>">
        <span class="icon">📷</span> Equipment
      </a>
      <a href="?page=rentals"    class="sidebar-link <?= $page === 'rentals'    ? 'active' : '' ?>">
        <span class="icon">🎬</span> Rentals
      </a>

      <p class="sidebar-section">Management</p>
      <a href="?page=customers"  class="sidebar-link <?= $page === 'customers'  ? 'active' : '' ?>">
        <span class="icon">👥</span> Customers
      </a>
      <a href="?page=payments"   class="sidebar-link <?= $page === 'payments'   ? 'active' : '' ?>">
        <span class="icon">💳</span> Payments
      </a>

      <p class="sidebar-section">System</p>
      <a href="index.php"  class="sidebar-link">
        <span class="icon">🌐</span> View Site
      </a>
      <a href="logout.php" class="sidebar-link" style="color:#ff6b6b;">
        <span class="icon">🚪</span> Logout
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="admin-info">
        <div class="admin-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
        <div>
          <div class="admin-name"><?= htmlspecialchars($admin_name) ?></div>
          <div class="admin-role">Administrator</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- Mobile overlay -->
  <div class="admin-overlay" id="adminOverlay" onclick="toggleSidebar()"></div>

  <!-- ── MAIN ── -->
  <main class="admin-main">

    <!-- Topbar -->
    <div class="admin-topbar">
      <div style="display:flex;align-items:center;gap:1rem;">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
          <span style="font-size:1.1rem;">☰</span>
        </button>
        <h1>
          <?= match($page) {
            'dashboard' => 'Dashboard',
            'equipment' => 'Equipment',
            'rentals'   => 'Rentals',
            'customers' => 'Customers',
            'payments'  => 'Payments',
            default     => 'Dashboard'
          } ?>
        </h1>
      </div>
      <div style="font-size:0.8rem;color:var(--gray-mid);">
        <?= date('l, F j, Y') ?>
      </div>
    </div>

    <!-- Content -->
    <div class="admin-content">

      <!-- Alerts -->
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- ====================================================
           PAGE: DASHBOARD
           ==================================================== -->
      <?php if ($page === 'dashboard'): ?>

        <!-- Stats -->
        <div class="stat-grid-admin">
          <div class="glass-card stat-card">
            <div class="stat-icon">📷</div>
            <div class="stat-value"><?= $eq_count ?></div>
            <div class="stat-label">Total Equipment</div>
          </div>
          <div class="glass-card stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= $cust_count ?></div>
            <div class="stat-label">Customers</div>
          </div>
          <div class="glass-card stat-card">
            <div class="stat-icon">🎬</div>
            <div class="stat-value"><?= $active_rent ?></div>
            <div class="stat-label">Active Rentals</div>
          </div>
          <div class="glass-card stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₱<?= number_format($total_rev, 0) ?></div>
            <div class="stat-label">Total Revenue</div>
          </div>
        </div>

        <!-- Charts row -->
        <div class="chart-row">
          <div class="glass-card" style="padding:1.5rem;">
            <h3 style="font-size:0.9rem;margin-bottom:1rem;color:var(--gray-mid);">Equipment by Status</h3>
            <?php
              $status_counts = [];
              $sc_res = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM equipment GROUP BY status");
              while ($sc = mysqli_fetch_assoc($sc_res)) $status_counts[$sc['status']] = $sc['cnt'];
              $all_statuses = ['Available','Rented','Maintenance','Damaged'];
              $max_cnt = max(array_values($status_counts) ?: [1]);
            ?>
            <div class="mini-bar-chart">
              <?php foreach ($all_statuses as $st): ?>
                <?php $cnt = $status_counts[$st] ?? 0; $h = $max_cnt > 0 ? ($cnt / $max_cnt * 100) : 0; ?>
                <div class="bar-col">
                  <div style="font-size:0.7rem;color:var(--gray-mid);margin-bottom:0.25rem;"><?= $cnt ?></div>
                  <div class="bar" style="height:<?= $h ?>px;
                    background:<?= $st==='Available'?'#22c55e':($st==='Rented'?'var(--red)':($st==='Maintenance'?'#fbbf24':'#6b7280')) ?>;"></div>
                  <div class="bar-label"><?= $st ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="glass-card" style="padding:1.5rem;">
            <h3 style="font-size:0.9rem;margin-bottom:1rem;color:var(--gray-mid);">Rentals by Status</h3>
            <?php
              $rent_counts = [];
              $rc_res = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM rental GROUP BY status");
              while ($rc = mysqli_fetch_assoc($rc_res)) $rent_counts[$rc['status']] = $rc['cnt'];
              $rent_statuses = ['Pending','Active','Returned','Overdue'];
              $max_rc = max(array_values($rent_counts) ?: [1]);
            ?>
            <div class="mini-bar-chart">
              <?php foreach ($rent_statuses as $st): ?>
                <?php $cnt = $rent_counts[$st] ?? 0; $h = $max_rc > 0 ? ($cnt / $max_rc * 100) : 0; ?>
                <div class="bar-col">
                  <div style="font-size:0.7rem;color:var(--gray-mid);margin-bottom:0.25rem;"><?= $cnt ?></div>
                  <div class="bar" style="height:<?= max(4,$h) ?>px;
                    background:<?= $st==='Pending'?'#fbbf24':($st==='Active'?'#60a5fa':($st==='Returned'?'#22c55e':'var(--red)')) ?>;"></div>
                  <div class="bar-label"><?= $st ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Recent Rentals -->
        <div class="glass-card" style="overflow:hidden;">
          <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:0.95rem;">Recent Rentals</h3>
            <a href="?page=rentals" class="btn btn-outline btn-sm">View All</a>
          </div>
          <div class="table-wrapper" style="border:none;border-radius:0;">
            <table>
              <thead>
                <tr>
                  <th>ID</th><th>Customer</th><th>Equipment</th><th>Dates</th><th>Amount</th><th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($recent_res && mysqli_num_rows($recent_res) > 0): ?>
                  <?php while ($r = mysqli_fetch_assoc($recent_res)): ?>
                    <tr>
                      <td style="color:var(--gray-dark)">#<?= $r['rental_id'] ?></td>
                      <td><?= htmlspecialchars($r['full_name']) ?></td>
                      <td><?= htmlspecialchars($r['equip_name']) ?></td>
                      <td style="font-size:0.78rem;"><?= $r['rental_date'] ?> → <?= $r['return_date'] ?></td>
                      <td style="font-weight:600;color:var(--white);">₱<?= number_format($r['total_amount'],2) ?></td>
                      <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="6" style="text-align:center;color:var(--gray-mid);">No rentals yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <!-- ====================================================
           PAGE: EQUIPMENT
           ==================================================== -->
      <?php elseif ($page === 'equipment'): ?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
          <div>
            <h2 style="font-size:1.1rem;">Equipment Management</h2>
            <p style="font-size:0.8rem;color:var(--gray-mid);">Add, edit, and manage all rental equipment.</p>
          </div>
          <button class="btn btn-primary" onclick="openModal('modal-add-eq')">+ Add Equipment</button>
        </div>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Name</th><th>Category</th><th>Rate/Day</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($eq_res && mysqli_num_rows($eq_res) > 0): mysqli_data_seek($eq_res, 0); ?>
                <?php while ($eq = mysqli_fetch_assoc($eq_res)): ?>
                  <tr>
                    <td style="color:var(--gray-dark)"><?= $eq['equipment_id'] ?></td>
                    <td>
                      <div style="font-weight:600;color:var(--white);"><?= htmlspecialchars($eq['name']) ?></div>
                      <?php if ($eq['description']): ?>
                        <div style="font-size:0.72rem;color:var(--gray-mid);"><?= htmlspecialchars(substr($eq['description'],0,50)) ?>...</div>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($eq['category']) ?></td>
                    <td style="color:var(--red);font-weight:600;">₱<?= number_format($eq['rental_rate'],2) ?></td>
                    <td>
                      <span class="badge badge-<?= strtolower(str_replace(' ','-',$eq['status'])) ?>">
                        <?= $eq['status'] ?>
                      </span>
                    </td>
                    <td>
                      <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                        <button class="btn btn-outline btn-sm"
                          onclick="openEditModal(<?= $eq['equipment_id'] ?>, '<?= addslashes($eq['name']) ?>', '<?= addslashes($eq['category']) ?>', <?= $eq['rental_rate'] ?>, '<?= $eq['status'] ?>', '<?= addslashes($eq['description']) ?>')">
                          Edit
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this equipment?')">
                          <input type="hidden" name="action" value="delete_equipment">
                          <input type="hidden" name="eq_id" value="<?= $eq['equipment_id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="6" style="text-align:center;color:var(--gray-mid);">No equipment found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ====================================================
           PAGE: RENTALS
           ==================================================== -->
      <?php elseif ($page === 'rentals'): ?>

        <div style="margin-bottom:1.5rem;">
          <h2 style="font-size:1.1rem;">All Rentals</h2>
          <p style="font-size:0.8rem;color:var(--gray-mid);">View and manage all rental transactions. Mark returned items.</p>
        </div>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Customer</th><th>Equipment</th><th>From</th><th>To</th><th>Days</th><th>Total</th><th>Payment</th><th>Status</th><th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($all_rent_res && mysqli_num_rows($all_rent_res) > 0): ?>
                <?php while ($r = mysqli_fetch_assoc($all_rent_res)): ?>
                  <tr>
                    <td style="color:var(--gray-dark)">#<?= $r['rental_id'] ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td>
                      <div style="font-weight:500;color:var(--white);font-size:0.85rem;"><?= htmlspecialchars($r['equip_name']) ?></div>
                    </td>
                    <td style="font-size:0.8rem;"><?= $r['rental_date'] ?></td>
                    <td style="font-size:0.8rem;"><?= $r['return_date'] ?></td>
                    <td><?= $r['num_days'] ?>d</td>
                    <td style="color:var(--white);font-weight:600;">₱<?= number_format($r['total_amount'],2) ?></td>
                    <td>
                      <?php if ($r['payment_status']): ?>
                        <span class="badge badge-<?= strtolower($r['payment_status']) ?>"><?= $r['payment_status'] ?></span>
                      <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                      <span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span>
                    </td>
                    <td>
                      <?php if ($r['status'] === 'Active'): ?>
                        <form method="POST" style="display:inline;">
                          <input type="hidden" name="action"       value="return_equipment">
                          <input type="hidden" name="rental_id"    value="<?= $r['rental_id'] ?>">
                          <input type="hidden" name="equipment_id" value="<?= $r['equipment_id'] ?>">
                          <button type="submit" class="btn btn-success btn-sm"
                            onclick="return confirm('Mark this rental as returned?')">
                            Return
                          </button>
                        </form>
                      <?php else: ?>
                        <span style="font-size:0.75rem;color:var(--gray-dark);">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="10" style="text-align:center;color:var(--gray-mid);">No rentals found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ====================================================
           PAGE: CUSTOMERS
           ==================================================== -->
      <?php elseif ($page === 'customers'): ?>

        <div style="margin-bottom:1.5rem;">
          <h2 style="font-size:1.1rem;">Customer Management</h2>
          <p style="font-size:0.8rem;color:var(--gray-mid);">View customer accounts and manage penalty flags.</p>
        </div>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Rentals</th><th>Penalty</th><th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($cust_res && mysqli_num_rows($cust_res) > 0): ?>
                <?php while ($c = mysqli_fetch_assoc($cust_res)): ?>
                  <tr>
                    <td style="color:var(--gray-dark)">#<?= $c['customer_id'] ?></td>
                    <td style="font-weight:500;color:var(--white);"><?= htmlspecialchars($c['full_name']) ?></td>
                    <td style="font-size:0.8rem;"><?= htmlspecialchars($c['email']) ?></td>
                    <td style="font-size:0.8rem;"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                    <td><?= $c['total_rentals'] ?></td>
                    <td>
                      <?php if ($c['has_penalty']): ?>
                        <span class="badge badge-rented">⚠️ Has Penalty</span>
                      <?php else: ?>
                        <span class="badge badge-available">✅ Clear</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="action"          value="toggle_penalty">
                        <input type="hidden" name="cust_id"         value="<?= $c['customer_id'] ?>">
                        <input type="hidden" name="current_penalty" value="<?= $c['has_penalty'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm">
                          <?= $c['has_penalty'] ? 'Clear Penalty' : 'Flag Penalty' ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" style="text-align:center;color:var(--gray-mid);">No customers found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <!-- ====================================================
           PAGE: PAYMENTS
           ==================================================== -->
      <?php elseif ($page === 'payments'): ?>

        <?php
          $total_paid   = mysqli_fetch_row(mysqli_query($conn,"SELECT COALESCE(SUM(amount_paid),0) FROM payment WHERE payment_status='Paid'"))[0];
          $total_count  = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM payment WHERE payment_status='Paid'"))[0];
        ?>

        <div style="margin-bottom:1.5rem;">
          <h2 style="font-size:1.1rem;">Payment Records</h2>
          <p style="font-size:0.8rem;color:var(--gray-mid);">
            Total Revenue: <strong style="color:var(--red)">₱<?= number_format($total_paid,2) ?></strong>
            from <?= $total_count ?> paid transactions.
          </p>
        </div>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Customer</th><th>Equipment</th><th>Dates</th><th>Days</th><th>Amount</th><th>Method</th><th>Status</th><th>Paid At</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($pay_res && mysqli_num_rows($pay_res) > 0): ?>
                <?php while ($p = mysqli_fetch_assoc($pay_res)): ?>
                  <tr>
                    <td style="color:var(--gray-dark)">#<?= $p['payment_id'] ?></td>
                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                    <td style="font-size:0.85rem;"><?= htmlspecialchars($p['equip_name']) ?></td>
                    <td style="font-size:0.78rem;"><?= $p['rental_date'] ?> → <?= $p['return_date'] ?></td>
                    <td><?= $p['num_days'] ?>d</td>
                    <td style="font-weight:700;color:var(--white);">₱<?= number_format($p['amount_paid'],2) ?></td>
                    <td>
                      <span style="font-size:0.8rem;">
                        <?= $p['payment_method'] === 'Cash' ? '💵' : ($p['payment_method'] === 'Card' ? '💳' : '📱') ?>
                        <?= htmlspecialchars($p['payment_method']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge badge-<?= strtolower($p['payment_status']) ?>">
                        <?= $p['payment_status'] ?>
                      </span>
                    </td>
                    <td style="font-size:0.78rem;color:var(--gray-mid);">
                      <?= date('M j, Y g:i A', strtotime($p['paid_at'])) ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="9" style="text-align:center;color:var(--gray-mid);">No payment records yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <?php endif; ?>
    </div><!-- /admin-content -->
  </main>
</div>

<!-- ====================================================
     MODALS
     ==================================================== -->

<!-- Add Equipment Modal -->
<div class="modal-overlay" id="modal-add-eq">
  <div class="modal">
    <div class="modal-header">
      <h2>➕ Add Equipment</h2>
      <button class="modal-close" onclick="closeModal('modal-add-eq')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add_equipment">
        <div class="form-group">
          <label>Equipment Name <span style="color:var(--red)">*</span></label>
          <input type="text" name="eq_name" class="form-control" placeholder="e.g. Sony A7 IV" required>
        </div>
        <div class="form-group">
          <label>Category <span style="color:var(--red)">*</span></label>
          <select name="eq_category" class="form-control" required>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>"><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="eq_desc" class="form-control" rows="3" placeholder="Brief description..."></textarea>
        </div>
        <div class="form-group">
          <label>Rental Rate (₱/day) <span style="color:var(--red)">*</span></label>
          <input type="number" name="eq_rate" class="form-control" placeholder="0.00" min="1" step="0.01" required>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="eq_status" class="form-control">
            <option value="Available">Available</option>
            <option value="Maintenance">Maintenance</option>
            <option value="Damaged">Damaged</option>
          </select>
        </div>
        <div class="modal-footer" style="padding:0;border:none;margin-top:1rem;">
          <button type="button" class="btn btn-outline" onclick="closeModal('modal-add-eq')">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Equipment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal-overlay" id="modal-edit-eq">
  <div class="modal">
    <div class="modal-header">
      <h2>✏️ Edit Equipment</h2>
      <button class="modal-close" onclick="closeModal('modal-edit-eq')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="edit_equipment">
        <input type="hidden" name="eq_id"  id="edit-eq-id">
        <div class="form-group">
          <label>Equipment Name</label>
          <input type="text" name="eq_name" id="edit-eq-name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="eq_category" id="edit-eq-category" class="form-control">
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat ?>"><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="eq_desc" id="edit-eq-desc" class="form-control" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label>Rental Rate (₱/day)</label>
          <input type="number" name="eq_rate" id="edit-eq-rate" class="form-control" min="1" step="0.01" required>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="eq_status" id="edit-eq-status" class="form-control">
            <option value="Available">Available</option>
            <option value="Rented">Rented</option>
            <option value="Maintenance">Maintenance</option>
            <option value="Damaged">Damaged</option>
          </select>
        </div>
        <div class="modal-footer" style="padding:0;border:none;margin-top:1rem;">
          <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-eq')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── Modal Control ──
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Open Edit Modal with pre-filled data ──
function openEditModal(id, name, category, rate, status, desc) {
  document.getElementById('edit-eq-id').value   = id;
  document.getElementById('edit-eq-name').value  = name;
  document.getElementById('edit-eq-rate').value  = rate;
  document.getElementById('edit-eq-status').value= status;
  document.getElementById('edit-eq-desc').value  = desc;
  // Set select category
  const catSelect = document.getElementById('edit-eq-category');
  for (let opt of catSelect.options) {
    opt.selected = (opt.value === category);
  }
  openModal('modal-edit-eq');
}

// ── Mobile Sidebar Toggle ──
function toggleSidebar() {
  document.getElementById('adminSidebar').classList.toggle('open');
  document.getElementById('adminOverlay').classList.toggle('open');
}

// ── Auto-open modals if page action failed with error ──
<?php if ($error && $page === 'equipment'): ?>
// openModal('modal-add-eq'); // Uncomment to re-open on error
<?php endif; ?>
</script>

</body>
</html>
