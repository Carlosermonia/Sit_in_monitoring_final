<?php
session_start();
require 'db_connect.php';

$error = '';
$success_name = '';
$admin_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identity = trim($_POST['id_number'] ?? ''); 
    $password = $_POST['password'] ?? '';

    if (empty($login_identity) || empty($password)) {
        $error = 'Please enter your credentials.';
    } else {
        try {
            // 1. Check Admin
            $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmtAdmin->execute([$login_identity]);
            $admin = $stmtAdmin->fetch();

            if ($admin && $password === $admin['password']) {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_user'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $admin_success = $admin['full_name'];
            } else {
                // 2. Check Students (only if admin login failed)
                $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
                $stmtUser->execute([$login_identity]);
                $user = $stmtUser->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['id_number']  = $user['id_number'];
                    $_SESSION['first_name'] = $user['first_name'];
                    // Don't redirect yet — show popup first
                    $success_name = $user['first_name'] . ' ' . $user['last_name'];
                } else {
                    $error = 'Invalid credentials. Please try again.';
                }
            }
            
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — UC CCS SIT Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="login.css"/>
  <style>
    /* ── Success Popup ── */
    .popup-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 9999;
      align-items: center;
      justify-content: center;
    }
    .popup-overlay.show {
      display: flex;
    }
    .popup-box {
      background: #fff;
      border-radius: 24px;
      padding: 48px 40px 40px;
      text-align: center;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 32px 80px rgba(0,0,0,0.2);
      animation: popIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    @keyframes popIn {
      from { transform: scale(0.75); opacity: 0; }
      to   { transform: scale(1);    opacity: 1; }
    }
    .popup-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #22c55e, #16a34a);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 22px;
      box-shadow: 0 8px 24px rgba(34,197,94,0.35);
    }
    .popup-icon svg {
      width: 40px;
      height: 40px;
      stroke: #fff;
      stroke-width: 2.5;
      fill: none;
    }
    .popup-box h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem;
      color: #1e293b;
      margin-bottom: 8px;
    }
    .popup-box .popup-sub {
      color: #64748b;
      font-size: 0.9rem;
      margin-bottom: 6px;
    }
    .popup-box .popup-name {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--purple);
      margin-bottom: 28px;
      display: block;
    }
    .popup-progress {
      height: 5px;
      background: #e2e8f0;
      border-radius: 99px;
      overflow: hidden;
      margin-bottom: 14px;
    }
    .popup-progress-bar {
      height: 100%;
      background: linear-gradient(90deg, #22c55e, #16a34a);
      border-radius: 99px;
      animation: drain 2.6s linear forwards;
      transform-origin: left;
    }
    @keyframes drain {
      from { transform: scaleX(1); }
      to   { transform: scaleX(0); }
    }
    .popup-redirect-note {
      font-size: 0.78rem;
      color: #94a3b8;
    }
  </style>
</head>
<body>

<!-- ── ADMIN SUCCESS POPUP ── -->
<?php if ($admin_success): ?>
<div class="popup-overlay show" id="adminSuccessPopup">
  <div class="popup-box">
    <div class="popup-icon">
      <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 13l4 4L19 7"/>
      </svg>
    </div>
    <h2>Welcome Admin!</h2>
    <p class="popup-sub">Successfully signed in as</p>
    <span class="popup-name"><?= htmlspecialchars($admin_success) ?></span>
    <div class="popup-progress">
      <div class="popup-progress-bar"></div>
    </div>
    <p class="popup-redirect-note">Redirecting to admin dashboard...</p>
  </div>
</div>
<script>
  // Auto-redirect after 2.6s (matches the progress bar animation)
  setTimeout(function() {
    window.location.href = 'admin_dashboard.php';
  }, 2600);
</script>
<?php endif; ?>

<!-- ── SUCCESS POPUP ── -->
<?php if ($success_name): ?>
<div class="popup-overlay show" id="successPopup">
  <div class="popup-box">
    <div class="popup-icon">
      <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 13l4 4L19 7"/>
      </svg>
    </div>
    <h2>Welcome back!</h2>
    <p class="popup-sub">Successfully signed in as</p>
    <span class="popup-name"><?= htmlspecialchars($success_name) ?></span>
    <div class="popup-progress">
      <div class="popup-progress-bar"></div>
    </div>
    <p class="popup-redirect-note">Redirecting to your dashboard...</p>
  </div>
</div>
<script>
  // Auto-redirect after 2.6s (matches the progress bar animation)
  setTimeout(function() {
    window.location.href = 'dashboard.php';
  }, 2600);
</script>
<?php endif; ?>

<!-- NAVBAR -->
<nav>
  <a href="index.php" class="nav-brand">
    <img src="UClogo.png" alt="UC Logo">
    <span class="nav-title">College of Computer Studies<br>SIT-IN Monitoring System</span>
  </a>
  <ul class="nav-links">
    <li><a href="index.php">Home</a></li>
    <li><a href="community.php">Community</a></li>
    <li><a href="login.php" class="active">Login</a></li>
    <li><a href="Register.php" class="btn-nav">Register</a></li>
  </ul>
</nav>

<!-- MAIN LAYOUT -->
<div class="page">

  <!-- LEFT PANEL -->
  <div class="left-panel">
    <div class="hex-grid"></div>
    <div class="shield-wrapper">
      <img src="Css_logo-removebg-preview.png" alt="CCS Shield">
    </div>
    <div class="left-copy">
      <h2>College of Computer Studies</h2>
      <div class="tagline-pills">
        <span class="pill">SIT Program</span>
        <span class="pill">UC Cebu</span>
        <span class="pill">Est. 1983</span>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">
    <div class="card">

      <div class="card-logo">
        <img src="UClogo.png" alt="UC Logo">
        <h1>Welcome</h1>
        <p>Sign in to your SIT-IN Monitoring account</p>
      </div>

      <div class="divider"></div>

      <?php if ($error): ?>
      <div class="alert alert-error">
        <span class="alert-icon">⚠</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="login.php" autocomplete="off">

        <div class="field">
          <label for="id_number">Student / Staff ID</label>
          <div class="input-wrap">
            <svg class="input-icon-left" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 9a3 3 0 11-6 0 3 3 0 016 0zm6 9a9 9 0 10-18 0"/>
            </svg>
            <input
              type="text"
              id="id_number"
              name="id_number"
              placeholder="e.g. 2024-00001"
              value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
              required
              autocomplete="username"
            >
          </div>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <svg class="input-icon-left" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-5a2 2 0 00-2-2H6a2 2 0 00-2 2v5a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Enter your password"
              required
              autocomplete="current-password"
            >
            <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Toggle password">
              <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                <path id="eye-slash" class="hidden" stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
              </svg>
            </button>
          </div>
        </div>

        <div class="extras">
          <label class="remember">
            <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
            Remember me
          </label>
          <a href="forgot-password.php" class="forgot">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login">Sign In</button>

      </form>

      <div class="register-row">
        Don't have an account? <a href="register.php">Create one &rarr;</a>
      </div>

    </div>
  </div>

  <footer>
    &copy; <?= date('Y') ?> University of Cebu — College of Computer Studies &nbsp;|&nbsp; SIT-IN Monitoring System
  </footer>

</div>

<script>
  function togglePassword() {
    const passwordInput = document.getElementById('password');
    const slash = document.getElementById('eye-slash');
    const btn = document.querySelector('.toggle-pw');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        slash.classList.remove('hidden');
        btn.classList.add('is-visible');
    } else {
        passwordInput.type = 'password';
        slash.classList.add('hidden');
        btn.classList.remove('is-visible');
    }
  }
</script>
</body>
</html>