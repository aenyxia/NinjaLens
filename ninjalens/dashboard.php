<?php
// dashboard.php — NinjaLens Customer Dashboard
require_once 'config.php';

// ── Auth Guard ──
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$customer_id = (int) $_SESSION['user_id'];
$customer_name = $_SESSION['user_name'];
$error   = '';
$success = '';

// ── Pull and clear receipt from session ──
$receipt = null;
if (isset($_SESSION['last_receipt'])) {
    $receipt = $_SESSION['last_receipt'];
    unset($_SESSION['last_receipt']);
}

// ── Handle: Rent Equipment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rent') {
    $eq_id       = (int) $_POST['equipment_id'];
    $rental_date = mysqli_real_escape_string($conn, $_POST['rental_date']);
    $return_date = mysqli_real_escape_string($conn, $_POST['return_date']);
    $pay_method  = mysqli_real_escape_string($conn, $_POST['payment_method']);

    // Validate dates
    $today   = date('Y-m-d');
    $num_days = (strtotime($return_date) - strtotime($rental_date)) / 86400;

    if (empty($rental_date) || empty($return_date)) {
        $error = 'Please select rental and return dates.';
    } elseif ($rental_date < $today) {
        $error = 'Rental date cannot be in the past.';
    } elseif ($num_days < 1) {
        $error = 'Return date must be at least 1 day after rental date.';
    } else {
        // Check customer penalty
        $pen_res = mysqli_query($conn, "SELECT has_penalty FROM customer WHERE customer_id = $customer_id");
        $pen_row = mysqli_fetch_assoc($pen_res);
        if ($pen_row['has_penalty']) {
            $error = 'You have an unpaid penalty. Please contact admin before renting.';
        } else {
            // Check equipment status + rate
            $eq_res = mysqli_query($conn, "SELECT * FROM equipment WHERE equipment_id = $eq_id AND status = 'Available' LIMIT 1");
            if (!$eq_res || mysqli_num_rows($eq_res) === 0) {
                $error = 'This equipment is no longer available.';
            } else {
                $eq     = mysqli_fetch_assoc($eq_res);
                $rate   = $eq['rental_rate'];
                $total  = $rate * $num_days;

                // Check for overlapping active rental on same equipment
                $overlap = mysqli_query($conn,
                    "SELECT rental_id FROM rental
                     WHERE equipment_id = $eq_id
                       AND status IN ('Pending','Active')
                     LIMIT 1");
                if ($overlap && mysqli_num_rows($overlap) > 0) {
                    $error = 'This equipment is already booked. Please check back later.';
                } else {
                    // Begin transaction
                    mysqli_begin_transaction($conn);
                    try {
                        // Insert rental (Pending until payment confirmed)
                        $ins_rental = "INSERT INTO rental
                            (customer_id, equipment_id, rental_date, return_date, num_days, total_amount, status)
                            VALUES ($customer_id, $eq_id, '$rental_date', '$return_date', $num_days, $total, 'Pending')";
                        mysqli_query($conn, $ins_rental);
                        $rental_id = mysqli_insert_id($conn);

                        // Simulate payment (always succeeds for Cash, demo)
                        $pay_status = 'Paid';
                        $ins_payment = "INSERT INTO payment
                            (rental_id, customer_id, amount_paid, payment_method, payment_status)
                            VALUES ($rental_id, $customer_id, $total, '$pay_method', '$pay_status')";
                        mysqli_query($conn, $ins_payment);

                        // Update rental to Active + equipment to Rented
                        mysqli_query($conn, "UPDATE rental SET status = 'Active' WHERE rental_id = $rental_id");
                        mysqli_query($conn, "UPDATE equipment SET status = 'Rented' WHERE equipment_id = $eq_id");

                        mysqli_commit($conn);

                        // ── Store receipt data in session ──
                        $_SESSION['last_receipt'] = [
                            'rental_id'     => $rental_id,
                            'equip_name'    => $eq['name'],
                            'category'      => $eq['category'],
                            'rental_date'   => $rental_date,
                            'return_date'   => $return_date,
                            'num_days'      => $num_days,
                            'rate'          => $rate,
                            'total'         => $total,
                            'pay_method'    => $pay_method,
                            'pay_status'    => $pay_status,
                            'customer_name' => $customer_name,
                            'issued_at'     => date('F j, Y g:i A'),
                        ];

                        // Redirect to avoid re-POST on refresh, receipt will show from session
                        header('Location: dashboard.php?rental_success=1');
                        exit;

                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = 'Rental failed. Please try again.';
                    }
                }
            }
        }
    }
}

// ── Show success message on redirect ──
if (isset($_GET['rental_success'])) {
    $success = 'Rental confirmed! 🎉 Your receipt is shown below.';
    // Re-pull receipt if set (from redirect)
    if (isset($_SESSION['last_receipt'])) {
        $receipt = $_SESSION['last_receipt'];
        unset($_SESSION['last_receipt']);
    }
}

// ── Load: Available Equipment ──
$avail_sql = "SELECT * FROM equipment WHERE status = 'Available' ORDER BY category, name";
$avail_res = mysqli_query($conn, $avail_sql);

// ── Load: Customer's Rental History ──
$history_sql = "
    SELECT r.*, e.name AS equip_name, e.category, p.payment_method, p.payment_status
    FROM rental r
    JOIN equipment e ON r.equipment_id = e.equipment_id
    LEFT JOIN payment p ON p.rental_id = r.rental_id
    WHERE r.customer_id = $customer_id
    ORDER BY r.created_at DESC
";
$history_res = mysqli_query($conn, $history_sql);

// ── Load: Stats ──
$stats_sql = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) AS active,
    SUM(CASE WHEN status='Returned' THEN 1 ELSE 0 END) AS returned,
    SUM(total_amount) AS total_spent
    FROM rental WHERE customer_id = $customer_id";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_sql));

// ── Emoji category map ──
$cat_icons = [
    'Camera Body'  => '', 'Lens' => '', 'Drone' => '',
    'Stabilizer'   => '','Lighting' => '','Audio' => '️',
    'Tripod'       => '','Monitor/Rec' => '','Accessories' => '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — NinjaLens</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* ── Dashboard Styles ── */
    .dash-nav {
      display: flex;
      gap: 0.5rem;
      border-bottom: 1px solid var(--border);
      margin-bottom: 2rem;
      overflow-x: auto;
    }
    .dash-nav-item {
      padding: 0.7rem 1.25rem;
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--gray-mid);
      cursor: pointer;
      border-bottom: 2px solid transparent;
      white-space: nowrap;
      transition: all var(--transition);
      background: none;
      border-top: none;
      border-left: none;
      border-right: none;
      font-family: var(--font);
    }
    .dash-nav-item:hover { color: var(--white); }
    .dash-nav-item.active {
      color: var(--red);
      border-bottom-color: var(--red);
    }
    .dash-panel { display: none; }
    .dash-panel.active { display: block; }

    .rent-date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    input[type="date"] { color-scheme: dark; }

    .equip-list-row {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr auto;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      transition: background var(--transition);
    }
    .equip-list-row:hover { background: rgba(255,255,255,0.025); }
    .equip-list-row:last-child { border-bottom: none; }
    .equip-list-name { font-weight: 600; font-size: 0.9rem; }
    .equip-list-cat  { font-size: 0.78rem; color: var(--gray-mid); }

    /* ── Receipt Modal ── */
    .receipt-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.75);
      backdrop-filter: blur(6px);
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.25s ease;
    }
    .receipt-modal-overlay.open {
      opacity: 1;
      pointer-events: all;
    }
    .receipt-modal {
      background: #111;
      border: 1px solid rgba(255,59,59,0.25);
      border-radius: 12px;
      width: 100%;
      max-width: 480px;
      box-shadow: 0 0 60px rgba(255,59,59,0.12);
      overflow: hidden;
      transform: translateY(20px);
      transition: transform 0.25s ease;
    }
    .receipt-modal-overlay.open .receipt-modal {
      transform: translateY(0);
    }
    .receipt-header {
      background: linear-gradient(135deg, #1a0000, #2a0808);
      border-bottom: 1px solid rgba(255,59,59,0.2);
      padding: 1.5rem;
      text-align: center;
    }
    .receipt-logo {
      font-size: 1.4rem;
      font-weight: 800;
      letter-spacing: -0.5px;
      margin-bottom: 0.25rem;
    }
    .receipt-logo span { color: #ff3b3b; }
    .receipt-subtitle {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.4);
      letter-spacing: 2px;
      text-transform: uppercase;
    }
    .receipt-body {
      padding: 1.5rem;
    }
    .receipt-confirmed {
      text-align: center;
      margin-bottom: 1.25rem;
    }
    .receipt-confirmed-icon {
      font-size: 2.5rem;
      display: block;
      margin-bottom: 0.4rem;
    }
    .receipt-confirmed-text {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.45);
      letter-spacing: 1.5px;
      text-transform: uppercase;
    }
    .receipt-divider {
      border: none;
      border-top: 1px dashed rgba(255,255,255,0.1);
      margin: 1rem 0;
    }
    .receipt-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      margin-bottom: 0.6rem;
      font-size: 0.82rem;
    }
    .receipt-row-label {
      color: rgba(255,255,255,0.45);
      white-space: nowrap;
    }
    .receipt-row-value {
      color: rgba(255,255,255,0.9);
      font-weight: 500;
      text-align: right;
    }
    .receipt-total-row {
      background: rgba(255,59,59,0.06);
      border: 1px solid rgba(255,59,59,0.2);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 1rem 0;
    }
    .receipt-total-label {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.5);
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .receipt-total-amount {
      font-size: 1.5rem;
      font-weight: 800;
      color: #ff3b3b;
    }
    .receipt-ref {
      text-align: center;
      font-size: 0.7rem;
      color: rgba(255,255,255,0.2);
      letter-spacing: 1px;
      margin-top: 0.5rem;
    }
    .receipt-actions {
      display: flex;
      gap: 0.75rem;
      padding: 0 1.5rem 1.5rem;
    }
    .receipt-actions .btn {
      flex: 1;
    }

    /* ── Print Styles ── */
    @media print {
      /* Hide everything except the printable receipt */
      body > * { display: none !important; }
      #printable-receipt { display: block !important; }

      #printable-receipt {
        width: 80mm;          /* Standard thermal receipt width */
        margin: 0 auto;
        padding: 0;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        color: #000;
        background: #fff;
      }
      .pr-center { text-align: center; }
      .pr-logo   { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
      .pr-tagline { font-size: 9px; letter-spacing: 1px; margin-bottom: 6px; }
      .pr-divider { border-top: 1px dashed #000; margin: 6px 0; }
      .pr-row { display: flex; justify-content: space-between; margin: 3px 0; }
      .pr-label { color: #555; }
      .pr-total-row {
        border-top: 2px solid #000;
        border-bottom: 2px solid #000;
        padding: 4px 0;
        margin: 6px 0;
        font-weight: bold;
        font-size: 13px;
      }
      .pr-footer { font-size: 9px; text-align: center; margin-top: 8px; color: #555; }
      .pr-ref { font-size: 9px; text-align: center; color: #888; margin-top: 4px; }
    }

    @media (max-width: 640px) {
      .equip-list-row { grid-template-columns: 1fr; }
      .rent-date-row  { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body class="dash-layout">

<!-- ────────────────────────────────────────────
     PRINTABLE RECEIPT (hidden on screen, shown on print)
     ──────────────────────────────────────────── -->
<?php if ($receipt): ?>
<div id="printable-receipt" style="display:none;">
  <div class="pr-center">
    <div class="pr-logo">🎥 NinjaLens</div>
    <div class="pr-tagline">EQUIPMENT RENTAL RECEIPT</div>
  </div>
  <div class="pr-divider"></div>

  <div class="pr-row"><span class="pr-label">Receipt #</span><span>NL-<?= str_pad($receipt['rental_id'], 6, '0', STR_PAD_LEFT) ?></span></div>
  <div class="pr-row"><span class="pr-label">Date Issued</span><span><?= htmlspecialchars($receipt['issued_at']) ?></span></div>
  <div class="pr-row"><span class="pr-label">Customer</span><span><?= htmlspecialchars($receipt['customer_name']) ?></span></div>

  <div class="pr-divider"></div>

  <div class="pr-row"><span class="pr-label">Equipment</span><span><?= htmlspecialchars($receipt['equip_name']) ?></span></div>
  <div class="pr-row"><span class="pr-label">Category</span><span><?= htmlspecialchars($receipt['category']) ?></span></div>
  <div class="pr-row"><span class="pr-label">Rental Date</span><span><?= htmlspecialchars($receipt['rental_date']) ?></span></div>
  <div class="pr-row"><span class="pr-label">Return Date</span><span><?= htmlspecialchars($receipt['return_date']) ?></span></div>
  <div class="pr-row"><span class="pr-label">Duration</span><span><?= $receipt['num_days'] ?> day<?= $receipt['num_days'] > 1 ? 's' : '' ?></span></div>
  <div class="pr-row"><span class="pr-label">Rate/Day</span><span>PHP <?= number_format($receipt['rate'], 2) ?></span></div>

  <div class="pr-divider"></div>

  <div class="pr-row pr-total-row">
    <span>TOTAL PAID</span>
    <span>PHP <?= number_format($receipt['total'], 2) ?></span>
  </div>

  <div class="pr-row"><span class="pr-label">Payment Method</span><span><?= htmlspecialchars($receipt['pay_method']) ?></span></div>
  <div class="pr-row"><span class="pr-label">Status</span><span><?= htmlspecialchars($receipt['pay_status']) ?></span></div>

  <div class="pr-divider"></div>
  <div class="pr-footer">
    Thank you for choosing NinjaLens!<br>
    Please return equipment on time to avoid penalties.
  </div>
  <div class="pr-ref">Rental ID: #<?= str_pad($receipt['rental_id'], 6, '0', STR_PAD_LEFT) ?></div>
</div>
<?php endif; ?>

<!-- ── TOPBAR ── -->
<header class="dash-topbar">
  <div class="dash-topbar-inner">
    <a href="index.php" class="logo">
      <img src="images/ninjalens_logo.png" alt="NinjaLens Logo" style="height:50px; width:70px; vertical-align:middle;">
      Ninja<span class="accent">Lens</span>
    </a>
    <div class="dash-user">
      <div class="dash-avatar"><?= strtoupper(substr($customer_name, 0, 1)) ?></div>
      <span style="color:var(--gray-light)"><?= htmlspecialchars($customer_name) ?></span>
      <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
  </div>
</header>

<!-- ── MAIN CONTENT ── -->
<main class="dash-body">
  <div class="container">

    <!-- Alerts -->
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:1rem;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:1rem;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Welcome -->
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-size:1.4rem;">Hey, <?= htmlspecialchars(explode(' ', $customer_name)[0]) ?> 👋</h2>
      <p style="color:var(--gray-mid);font-size:0.875rem;">Ready to shoot? Browse available gear below.</p>
    </div>

    <!-- Stats -->
    <div class="stat-cards">
      <div class="glass-card stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
        <div class="stat-label">Total Rentals</div>
      </div>
      <div class="glass-card stat-card">
        <div class="stat-icon">🔴</div>
        <div class="stat-value"><?= $stats['active'] ?? 0 ?></div>
        <div class="stat-label">Active</div>
      </div>
      <div class="glass-card stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= $stats['returned'] ?? 0 ?></div>
        <div class="stat-label">Returned</div>
      </div>
      <div class="glass-card stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value">₱<?= number_format($stats['total_spent'] ?? 0, 0) ?></div>
        <div class="stat-label">Total Spent</div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="dash-nav">
      <button class="dash-nav-item active" onclick="switchPanel('browse', this)">🔍 Browse Equipment</button>
      <button class="dash-nav-item" onclick="switchPanel('history', this)">📋 Rental History</button>
    </div>

    <!-- ── PANEL: Browse Equipment ── -->
    <div class="dash-panel active" id="panel-browse">
      <div class="glass-card" style="overflow:hidden;">
        <?php if ($avail_res && mysqli_num_rows($avail_res) > 0): ?>
          <?php while ($eq = mysqli_fetch_assoc($avail_res)): ?>
            <?php $icon = $cat_icons[$eq['category']] ?? '📦'; ?>
            <div class="equip-list-row">
              <div>
                <div class="equip-list-name"><?= $icon ?> <?= htmlspecialchars($eq['name']) ?></div>
                <div class="equip-list-cat"><?= htmlspecialchars($eq['category']) ?></div>
                <?php if ($eq['description']): ?>
                  <div style="font-size:0.75rem;color:var(--gray-dark);margin-top:0.2rem;">
                    <?= htmlspecialchars(substr($eq['description'], 0, 60)) ?>...
                  </div>
                <?php endif; ?>
              </div>
              <div>
                <div style="font-weight:700;color:var(--red);font-size:1rem;">
                  ₱<?= number_format($eq['rental_rate'], 2) ?>
                </div>
                <div style="font-size:0.75rem;color:var(--gray-mid);">per day</div>
              </div>
              <div>
                <span class="badge badge-available">Available</span>
              </div>
              <div>
                <button class="btn btn-primary btn-sm"
                  onclick="openRentModal(<?= $eq['equipment_id'] ?>, '<?= addslashes($eq['name']) ?>', <?= $eq['rental_rate'] ?>)">
                  Rent
                </button>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon">📷</div>
            <h3>No equipment available right now</h3>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── PANEL: Rental History ── -->
    <div class="dash-panel" id="panel-history">
      <?php if ($history_res && mysqli_num_rows($history_res) > 0): ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Equipment</th>
                <th>Rental Date</th>
                <th>Return Date</th>
                <th>Days</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php $row_num = 1; while ($h = mysqli_fetch_assoc($history_res)): ?>
                <tr>
                  <td style="color:var(--gray-dark)"><?= $row_num++ ?></td>
                  <td>
                    <div style="font-weight:600;color:var(--white);"><?= htmlspecialchars($h['equip_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--gray-mid)"><?= htmlspecialchars($h['category']) ?></div>
                  </td>
                  <td><?= $h['rental_date'] ?></td>
                  <td><?= $h['return_date'] ?></td>
                  <td><?= $h['num_days'] ?> day<?= $h['num_days'] > 1 ? 's' : '' ?></td>
                  <td style="color:var(--white);font-weight:600;">₱<?= number_format($h['total_amount'], 2) ?></td>
                  <td>
                    <?php if ($h['payment_status']): ?>
                      <span class="badge badge-<?= strtolower($h['payment_status']) ?>">
                        <?= htmlspecialchars($h['payment_status']) ?>
                      </span><br>
                      <span style="font-size:0.7rem;color:var(--gray-mid)"><?= htmlspecialchars($h['payment_method']) ?></span>
                    <?php else: ?>
                      <span class="badge badge-pending">No Payment</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge badge-<?= strtolower($h['status']) ?>">
                      <?= htmlspecialchars($h['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="glass-card empty-state">
          <div class="empty-icon">📋</div>
          <h3>No rental history yet</h3>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<!-- ── RENT MODAL ── -->
<div class="modal-overlay" id="rentModal">
  <div class="modal">
    <div class="modal-header">
      <h2>🎬 Rent Equipment</h2>
      <button class="modal-close" onclick="closeRentModal()">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="rentForm">
        <input type="hidden" name="action" value="rent">
        <input type="hidden" name="equipment_id" id="modal-eq-id">

        <div style="background:rgba(255,59,59,0.06);border:1px solid rgba(255,59,59,0.2);border-radius:var(--radius-sm);padding:1rem;margin-bottom:1.25rem;">
          <div style="font-weight:600;font-size:1rem;" id="modal-eq-name">Equipment Name</div>
          <div style="font-size:0.85rem;color:var(--gray-mid);margin-top:0.25rem;">
            ₱<span id="modal-eq-rate">0</span>/day
          </div>
        </div>

        <div class="rent-date-row">
          <div class="form-group">
            <label for="rental_date">Rental Date</label>
            <input type="date" id="rental_date" name="rental_date" class="form-control"
                   min="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label for="return_date">Return Date</label>
            <input type="date" id="return_date" name="return_date" class="form-control"
                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label>Computed Total</label>
          <div style="font-size:1.5rem;font-weight:800;color:var(--red);padding:0.5rem 0;" id="modal-total">
            ₱0.00
          </div>
          <div style="font-size:0.75rem;color:var(--gray-mid);" id="modal-days-label">Select dates to calculate</div>
        </div>

        <div class="form-group">
          <label for="payment_method">Payment Method</label>
          <select name="payment_method" id="payment_method" class="form-control" required>
            <option value="Cash">💵 Cash</option>
            <option value="Card">💳 Card</option>
            <option value="Online">📱 Online Transfer</option>
          </select>
        </div>

        <div class="alert alert-warning" style="font-size:0.8rem;">
          ⚠️ Payment is processed immediately. Ensure you have sufficient funds before confirming.
        </div>

        <button type="submit" class="btn btn-primary btn-full">Confirm Rental & Pay</button>
      </form>
    </div>
  </div>
</div>

<!-- ────────────────────────────────────────────
     RECEIPT MODAL (shown after successful rental)
     ──────────────────────────────────────────── -->
<?php if ($receipt): ?>
<div class="receipt-modal-overlay" id="receiptModal">
  <div class="receipt-modal">

    <div class="receipt-header">
      <div class="receipt-logo">🎥 Ninja<span>Lens</span></div>
      <div class="receipt-subtitle">Official Receipt</div>
    </div>

    <div class="receipt-body">
      <div class="receipt-confirmed">
        <span class="receipt-confirmed-icon">✅</span>
        <div class="receipt-confirmed-text">Payment Confirmed</div>
      </div>

      <div class="receipt-row">
        <span class="receipt-row-label">Receipt #</span>
        <span class="receipt-row-value">NL-<?= str_pad($receipt['rental_id'], 6, '0', STR_PAD_LEFT) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Date Issued</span>
        <span class="receipt-row-value"><?= htmlspecialchars($receipt['issued_at']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Customer</span>
        <span class="receipt-row-value"><?= htmlspecialchars($receipt['customer_name']) ?></span>
      </div>

      <hr class="receipt-divider">

      <div class="receipt-row">
        <span class="receipt-row-label">Equipment</span>
        <span class="receipt-row-value"><?= htmlspecialchars($receipt['equip_name']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Category</span>
        <span class="receipt-row-value"><?= htmlspecialchars($receipt['category']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Rental Date</span>
        <span class="receipt-row-value"><?= htmlspecialchars($receipt['rental_date']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Return Date</span>
        <span class="receipt-row-value"><?= htmlspecialchars($receipt['return_date']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Duration</span>
        <span class="receipt-row-value"><?= $receipt['num_days'] ?> day<?= $receipt['num_days'] > 1 ? 's' : '' ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Rate / Day</span>
        <span class="receipt-row-value">₱<?= number_format($receipt['rate'], 2) ?></span>
      </div>

      <div class="receipt-total-row">
        <span class="receipt-total-label">Total Paid</span>
        <span class="receipt-total-amount">₱<?= number_format($receipt['total'], 2) ?></span>
      </div>

      <div class="receipt-row">
        <span class="receipt-row-label">Payment Method</span>
        <span class="receipt-row-value"><?= htmlspecialchars($receipt['pay_method']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Payment Status</span>
        <span class="receipt-row-value" style="color:#4ade80;"><?= htmlspecialchars($receipt['pay_status']) ?></span>
      </div>

      <div class="receipt-ref">Rental ID: #<?= str_pad($receipt['rental_id'], 6, '0', STR_PAD_LEFT) ?></div>
    </div>

    <div class="receipt-actions">
      <button class="btn btn-primary" onclick="printReceipt()">🖨️ Print Receipt</button>
      <button class="btn btn-outline" onclick="closeReceiptModal()">Close</button>
    </div>

  </div>
</div>
<?php endif; ?>

<script>
// ── Tab Switcher ──
function switchPanel(id, btn) {
  document.querySelectorAll('.dash-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.dash-nav-item').forEach(b => b.classList.remove('active'));
  document.getElementById('panel-' + id).classList.add('active');
  btn.classList.add('active');
}

// ── Rent Modal ──
let currentRate = 0;
function openRentModal(id, name, rate) {
  currentRate = parseFloat(rate);
  document.getElementById('modal-eq-id').value            = id;
  document.getElementById('modal-eq-name').textContent    = name;
  document.getElementById('modal-eq-rate').textContent    = parseFloat(rate).toFixed(2);
  document.getElementById('modal-total').textContent      = '₱0.00';
  document.getElementById('modal-days-label').textContent = 'Select dates to calculate';
  document.getElementById('rental_date').value            = '';
  document.getElementById('return_date').value            = '';
  document.getElementById('rentModal').classList.add('open');
}
function closeRentModal() {
  document.getElementById('rentModal').classList.remove('open');
}
document.getElementById('rentModal').addEventListener('click', function(e) {
  if (e.target === this) closeRentModal();
});

// ── Live total calculation ──
function calcTotal() {
  const rd  = document.getElementById('rental_date').value;
  const ret = document.getElementById('return_date').value;
  if (!rd || !ret) return;
  const days = Math.round((new Date(ret) - new Date(rd)) / 86400000);
  if (days < 1) {
    document.getElementById('modal-total').textContent      = '—';
    document.getElementById('modal-days-label').textContent = 'Return date must be after rental date';
    return;
  }
  const total = currentRate * days;
  document.getElementById('modal-total').textContent =
    '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
  document.getElementById('modal-days-label').textContent =
    days + ' day' + (days > 1 ? 's' : '') + ' × ₱' + currentRate.toFixed(2);
  // Auto-set min return date
  const minReturn = new Date(rd);
  minReturn.setDate(minReturn.getDate() + 1);
  document.getElementById('return_date').min = minReturn.toISOString().split('T')[0];
}

document.getElementById('rental_date').addEventListener('change', calcTotal);
document.getElementById('return_date').addEventListener('change', calcTotal);

// ── Receipt Modal ──
<?php if ($receipt): ?>
// Auto-open receipt modal after successful rental
window.addEventListener('DOMContentLoaded', function () {
  setTimeout(function () {
    document.getElementById('receiptModal').classList.add('open');
  }, 300); // slight delay so page paint finishes first
});
<?php endif; ?>

function closeReceiptModal() {
  const modal = document.getElementById('receiptModal');
  if (modal) modal.classList.remove('open');
}

function printReceipt() {
  // Make the printable receipt visible, trigger print, then hide it again
  const el = document.getElementById('printable-receipt');
  if (el) {
    el.style.display = 'block';
    window.print();
    // Hide it again after print dialog closes (small delay)
    setTimeout(function () { el.style.display = 'none'; }, 500);
  } else {
    window.print();
  }
}

// Close receipt modal on overlay click
const receiptOverlay = document.getElementById('receiptModal');
if (receiptOverlay) {
  receiptOverlay.addEventListener('click', function(e) {
    if (e.target === this) closeReceiptModal();
  });
}
</script>

</body>
</html>