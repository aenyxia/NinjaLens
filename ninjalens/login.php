<?php
// login.php — NinjaLens Authentication Page
require_once 'config.php';

$error   = '';
$success = '';
$tab     = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';

// ── Handle LOGIN ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email    = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
        $tab   = 'login';
    } else {
        // Check admin first
        $admin_sql = "SELECT * FROM administrator WHERE email = '$email' LIMIT 1";
        $admin_res = mysqli_query($conn, $admin_sql);

        if ($admin_res && mysqli_num_rows($admin_res) > 0) {
            $admin = mysqli_fetch_assoc($admin_res);
            if (password_verify($password, $admin['password'])) {
                $_SESSION['user_id']   = $admin['admin_id'];
                $_SESSION['user_name'] = $admin['full_name'];
                $_SESSION['user_email']= $admin['email'];
                $_SESSION['role']      = 'admin';
                header('Location: admin.php');
                exit;
            }
        }

        // Check customer
        $cust_sql = "SELECT * FROM customer WHERE email = '$email' LIMIT 1";
        $cust_res = mysqli_query($conn, $cust_sql);

        if ($cust_res && mysqli_num_rows($cust_res) > 0) {
            $customer = mysqli_fetch_assoc($cust_res);
            if (password_verify($password, $customer['password'])) {
                $_SESSION['user_id']   = $customer['customer_id'];
                $_SESSION['user_name'] = $customer['full_name'];
                $_SESSION['user_email']= $customer['email'];
                $_SESSION['role']      = 'customer';
                header('Location: dashboard.php');
                exit;
            }
        }

        $error = 'Invalid email or password. Please try again.';
        $tab   = 'login';
    }
}

// ── Handle REGISTER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $tab      = 'register';
    $fullname = trim(mysqli_real_escape_string($conn, $_POST['fullname']));
    $email    = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $phone    = trim(mysqli_real_escape_string($conn, $_POST['phone']));
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (empty($fullname) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $check = "SELECT customer_id FROM customer WHERE email = '$email' LIMIT 1";
        $check_res = mysqli_query($conn, $check);

        if ($check_res && mysqli_num_rows($check_res) > 0) {
            $error = 'An account with that email already exists.';
        } else {
            $hashed   = password_hash($password, PASSWORD_BCRYPT);
            $insert   = "INSERT INTO customer (full_name, email, phone, password)
                         VALUES ('$fullname', '$email', '$phone', '$hashed')";
            if (mysqli_query($conn, $insert)) {
                $success = 'Account created! You can now log in.';
                $tab     = 'login';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

// Redirect if already logged in
if (isset($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — NinjaLens</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .auth-wrapper::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse at 20% 50%, rgba(255,59,59,0.1) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 20%, rgba(255,59,59,0.06) 0%, transparent 50%),
        linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
      background-size: 100%, 100%, 60px 60px, 60px 60px;
      pointer-events: none;
    }
    .auth-form { display: none; }
    .auth-form.active { display: block; }

    .divider {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin: 1.25rem 0;
      color: var(--gray-dark);
      font-size: 0.75rem;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

  
  </style>
</head>
<body>

<div class="auth-wrapper">
  <div class="glass-card auth-card">
    <!-- Logo -->
    <a href="index.php" class="auth-logo" style="color:var(--white);font-weight:800;">
      <img src="images/ninjalens_logo.png" alt="Ninja Lens Logo" style="height:50px; width:70px; vertical-align:middle;"  />
      Ninja<span style="color:var(--red)">Lens</span>
    </a>

    <h1 style="margin:0.4rem 0 0.2rem;">
      <?= $tab === 'register' ? 'Create Account' : 'Welcome Back' ?>
    </h1>
    <p style="color:var(--gray-mid);font-size:0.875rem;margin-bottom:1.5rem;">
      <?= $tab === 'register' ? 'Start renting professional gear today.' : 'Sign in to access your rental dashboard.' ?>
    </p>

    <!-- Tabs -->
    <div class="auth-tabs">
      <button class="auth-tab <?= $tab === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">Login</button>
      <button class="auth-tab <?= $tab === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">Register</button>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- ── LOGIN FORM ── -->
    <form method="POST" class="auth-form <?= $tab === 'login' ? 'active' : '' ?>" id="form-login">
      <input type="hidden" name="action" value="login">

    
      <div class="form-group">
        <label for="login-email">Email Address</label>
        <input type="email" id="login-email" name="email" class="form-control"
               placeholder="you@example.com" required
               value="<?= isset($_POST['email']) && $_POST['action'] === 'login' ? htmlspecialchars($_POST['email']) : '' ?>">
      </div>

      <div class="form-group">
        <label for="login-password">Password</label>
        <input type="password" id="login-password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">
        Sign In →
      </button>
    </form>

    <!-- ── REGISTER FORM ── -->
    <form method="POST" class="auth-form <?= $tab === 'register' ? 'active' : '' ?>" id="form-register">
      <input type="hidden" name="action" value="register">

      <div class="form-group">
        <label for="reg-name">Full Name <span style="color:var(--red)">*</span></label>
        <input type="text" id="reg-name" name="fullname" class="form-control"
               placeholder="Juan dela Cruz" required
               value="<?= isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '' ?>">
      </div>

      <div class="form-group">
        <label for="reg-email">Email Address <span style="color:var(--red)">*</span></label>
        <input type="email" id="reg-email" name="email" class="form-control"
               placeholder="you@example.com" required
               value="<?= isset($_POST['email']) && $_POST['action'] === 'register' ? htmlspecialchars($_POST['email']) : '' ?>">
      </div>

      <div class="form-group">
        <label for="reg-phone">Phone Number</label>
        <input type="text" id="reg-phone" name="phone" class="form-control"
               placeholder="09XX XXX XXXX"
               value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
      </div>

      <div class="form-group">
        <label for="reg-pass">Password <span style="color:var(--red)">*</span></label>
        <input type="password" id="reg-pass" name="password" class="form-control"
               placeholder="Min. 6 characters" required>
      </div>

      <div class="form-group">
        <label for="reg-confirm">Confirm Password <span style="color:var(--red)">*</span></label>
        <input type="password" id="reg-confirm" name="confirm_password" class="form-control"
               placeholder="Repeat password" required>
      </div>

      <button type="submit" class="btn btn-primary btn-full" style="margin-top:0.5rem;">
        Create Account →
      </button>
    </form>

    <div class="auth-footer">
      <a href="index.php">← Back to homepage</a>
    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.auth-tab').forEach((t, i) => {
    t.classList.toggle('active', (i === 0 && tab === 'login') || (i === 1 && tab === 'register'));
  });
  document.getElementById('form-login').classList.toggle('active', tab === 'login');
  document.getElementById('form-register').classList.toggle('active', tab === 'register');
}
</script>
</body>
</html>
