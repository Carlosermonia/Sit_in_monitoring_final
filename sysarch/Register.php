<?php
session_start();
require 'db_connect.php';
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number       = trim($_POST['id_number']       ?? '');
    $last_name       = trim($_POST['last_name']        ?? '');
    $first_name      = trim($_POST['first_name']       ?? '');
    $middle_name     = trim($_POST['middle_name']      ?? '');
    $course          = trim($_POST['course']           ?? '');
    $course_level    = trim($_POST['course_level']     ?? '');
    $email           = trim($_POST['email']            ?? '');
    $address         = trim($_POST['address']          ?? '');
    $password        = $_POST['password']              ?? '';
    $repeat_password = $_POST['repeat_password']       ?? '';

    // Basic validation
    if (empty($error)) {
        try {
            // Check if ID Number or Email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ? OR email = ?");
            $stmt->execute([$id_number, $email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "ID Number or Email is already registered.";
            } else {
                // Hash the password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $sql = "INSERT INTO users (id_number, last_name, first_name, middle_name, course, course_level, email, address, password) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_number, $last_name, $first_name, $middle_name, $course, $course_level, $email, $address, $hashed_password]);

                $success = 'Account created successfully! You can now <a href="login.php">sign in</a>.';
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register — UC CCS SIT Monitoring System</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="register.css"/>
</head>
<body>

<!-- NAVBAR -->
<nav>
  <a href="index.php" class="nav-brand">
    <img src="Uclogo.png" alt="UC Logo">
    <span class="nav-title">College of Computer Studies<br>SIT-IN Monitoring System</span>
  </a>
  <ul class="nav-links">
    <li><a href="index.php">Home</a></li>
    <li><a href="community.php">Community</a></li>
    <li><a href="login.php">Login</a></li>
    <li><a href="register.php" class="active btn-nav">Register</a></li>
  </ul>
</nav>

<!-- MAIN LAYOUT -->
<div class="page">

  <!-- LEFT PANEL -->
  <div class="left-panel">
    <div class="hex-grid"></div>

    <div class="logo-wrapper">
      <img src="Css_logo-removebg-preview.png" alt="University of Cebu Logo">
    </div>

    <div class="left-copy">
      <h2>Join the SIT-IN Program</h2>
      <p>Create your account and start tracking your student internship journey with UC CCS.</p>
    </div>

    <div class="step-list">
      <div class="step-item">
        <span class="step-num">1</span>
        Fill in your personal details
      </div>
      <div class="step-item">
        <span class="step-num">2</span>
        Set a secure password
      </div>
      <div class="step-item">
        <span class="step-num">3</span>
        Submit and verify your account
      </div>
      <div class="step-item">
        <span class="step-num">4</span>
        Log in and begin monitoring
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">
    <div class="card">
      <div class="card-header">
        <h1>Create an Account</h1>
        <p>All fields marked with <strong>*</strong> are required</p>
      </div>

      <div class="divider"></div>

      <!-- Alert box (shown on error or success) -->
      <?php if ($error): ?>
      <div class="alert alert-error" id="form-alert" style="display:flex;">
        <span class="alert-icon">⚠</span>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php elseif ($success): ?>
      <div class="alert alert-success" id="form-alert" style="display:flex;">
        <span class="alert-icon">✓</span>
        <span><?= $success ?></span>
      </div>
      <?php else: ?>
      <div class="alert" id="form-alert" style="display:none;"></div>
      <?php endif; ?>

      <form method="POST" action="register.php" id="register-form" autocomplete="off">

        <!-- ── ACCOUNT INFO ── -->
        <div class="section-label">Account Info</div>
        <div class="form-grid">

          <div class="field field-full">
            <label for="id_number">ID Number *</label>
            <div class="input-wrap">
              <input
                type="text"
                id="id_number"
                name="id_number"
                placeholder="e.g. 2024-00001"
                value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
                required
              >
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
              </svg>
            </div>
          </div>

          <div class="field">
            <label for="password">Password *</label>
            <div class="input-wrap">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Min. 8 characters"
                required
                oninput="checkStrength(this.value)"
              >
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-5a2 2 0 00-2-2H6a2 2 0 00-2 2v5a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              <button type="button" class="toggle-pw" onclick="togglePassword('password','eye-pw')" aria-label="Toggle password">
                <svg id="eye-pw" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="16" height="16">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
            <div class="strength-bar-wrap">
              <div class="strength-seg"></div>
              <div class="strength-seg"></div>
              <div class="strength-seg"></div>
              <div class="strength-seg"></div>
            </div>
            <div class="strength-label" id="strength-label"></div>
          </div>

          <div class="field">
            <label for="repeat_password">Repeat Password *</label>
            <div class="input-wrap">
              <input
                type="password"
                id="repeat_password"
                name="repeat_password"
                placeholder="Confirm password"
                required
              >
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
              <button type="button" class="toggle-pw" onclick="togglePassword('repeat_password','eye-pw2')" aria-label="Toggle confirm password">
                <svg id="eye-pw2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="16" height="16">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
          </div>

        </div>

        <!-- ── PERSONAL INFO ── -->
        <div class="section-label">Personal Information</div>
        <div class="form-grid">

          <div class="field">
            <label for="last_name">Last Name *</label>
            <div class="input-wrap">
              <input
                type="text"
                id="last_name"
                name="last_name"
                placeholder="e.g. Dela Cruz"
                value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                required
              >
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
          </div>

          <div class="field">
            <label for="first_name">First Name *</label>
            <div class="input-wrap">
              <input
                type="text"
                id="first_name"
                name="first_name"
                placeholder="e.g. Juan"
                value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                required
              >
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
          </div>

          <div class="field field-full">
            <label for="middle_name">Middle Name</label>
            <div class="input-wrap">
              <input
                type="text"
                id="middle_name"
                name="middle_name"
                placeholder="e.g. Santos (optional)"
                value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>"
              >
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
          </div>

          <div class="field field-full">
            <label for="email">Email Address *</label>
            <div class="input-wrap">
              <input
                type="email"
                id="email"
                name="email"
                placeholder="e.g. juan@uc.edu.ph"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
              >
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </div>
          </div>

          <div class="field field-full">
            <label for="address">Address *</label>
            <div class="input-wrap">
              <input
                type="text"
                id="address"
                name="address"
                placeholder="e.g. Mandaue City, Cebu"
                value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                required
              >
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
          </div>

        </div>

        <!-- ── ACADEMIC INFO ── -->
        <div class="section-label">Academic Details</div>
        <div class="form-grid">

          <div class="field">
  <label for="course">Course *</label>
  <div class="input-wrap">
    <select id="course" name="course" required>
      <option value="" disabled <?= empty($_POST['course']) ? 'selected' : '' ?>>Select course</option>

      <optgroup label="College of Computer Studies (CCS)">
        <option value="BSIT" <?= ($_POST['course'] ?? '') === 'BSIT' ? 'selected' : '' ?>>Information Technology</option>
        <option value="BSCS" <?= ($_POST['course'] ?? '') === 'BSCS' ? 'selected' : '' ?>>Computer Science</option>
        <option value="BSIS" <?= ($_POST['course'] ?? '') === 'BSIS' ? 'selected' : '' ?>>Information Systems</option>
        <option value="ACT"  <?= ($_POST['course'] ?? '') === 'ACT'  ? 'selected' : '' ?>>ACT</option>
      </optgroup>

      <optgroup label="College of Engineering">
        <option value="BSCpE" <?= ($_POST['course'] ?? '') === 'BSCpE' ? 'selected' : '' ?>>Computer Engineering</option>
        <option value="BSCE"  <?= ($_POST['course'] ?? '') === 'BSCE'  ? 'selected' : '' ?>>Civil Engineering</option>
        <option value="BSME"  <?= ($_POST['course'] ?? '') === 'BSME'  ? 'selected' : '' ?>>Mechanical Engineering</option>
        <option value="BSEE"  <?= ($_POST['course'] ?? '') === 'BSEE'  ? 'selected' : '' ?>>Electrical Engineering</option>
        <option value="BSIE"  <?= ($_POST['course'] ?? '') === 'BSIE'  ? 'selected' : '' ?>>Industrial Engineering</option>
        <option value="BSNAME" <?= ($_POST['course'] ?? '') === 'BSNAME' ? 'selected' : '' ?>>Naval Architecture and Marine Engineering</option>
      </optgroup>

      <optgroup label="College of Education">
        <option value="BEEd" <?= ($_POST['course'] ?? '') === 'BEEd' ? 'selected' : '' ?>>Elementary Education (BEEd)</option>
        <option value="BSEd" <?= ($_POST['course'] ?? '') === 'BSEd' ? 'selected' : '' ?>>Secondary Education (BSEd)</option>
      </optgroup>

      <optgroup label="Criminal Justice & Arts">
        <option value="BS Crim" <?= ($_POST['course'] ?? '') === 'BS Crim' ? 'selected' : '' ?>>Criminology</option>
        <option value="IndPsych" <?= ($_POST['course'] ?? '') === 'IndPsych' ? 'selected' : '' ?>>Industrial Psychology</option>
        <option value="AB PolSci" <?= ($_POST['course'] ?? '') === 'AB PolSci' ? 'selected' : '' ?>>AB Political Science</option>
        <option value="AB English" <?= ($_POST['course'] ?? '') === 'AB English' ? 'selected' : '' ?>>AB English</option>
      </optgroup>

      <optgroup label="Business & Management">
        <option value="BS Commerce" <?= ($_POST['course'] ?? '') === 'BS Commerce' ? 'selected' : '' ?>>Commerce</option>
        <option value="BS Accountancy" <?= ($_POST['course'] ?? '') === 'BS Accountancy' ? 'selected' : '' ?>>Accountancy</option>
        <option value="BSHRM" <?= ($_POST['course'] ?? '') === 'BSHRM' ? 'selected' : '' ?>>Hotel and Restaurant Management</option>
        <option value="BSCA" <?= ($_POST['course'] ?? '') === 'BSCA' ? 'selected' : '' ?>>Customs Administration</option>
        <option value="CompSec" <?= ($_POST['course'] ?? '') === 'CompSec' ? 'selected' : '' ?>>Computer Secretarial</option>
      </optgroup>

      <optgroup label="Special Programs & Short Courses">
        <option value="CISCO" <?= ($_POST['course'] ?? '') === 'CISCO' ? 'selected' : '' ?>>CISCO Networking Academy (Module 1 - 4)</option>
        <option value="EngComm" <?= ($_POST['course'] ?? '') === 'EngComm' ? 'selected' : '' ?>>English Communication Skills</option>
        <option value="Korean" <?= ($_POST['course'] ?? '') === 'Korean' ? 'selected' : '' ?>>Conversational Korean</option>
      </optgroup>
    </select>

    <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
    </svg>
  </div>
</div>

          <div class="field">
            <label for="course_level">Year Level *</label>
            <div class="input-wrap">
              <select id="course_level" name="course_level" required>
                <option value="" disabled <?= empty($_POST['course_level']) ? 'selected' : '' ?>>Select year</option>
                <option value="1" <?= ($_POST['course_level'] ?? '') === '1' ? 'selected' : '' ?>>1st Year</option>
                <option value="2" <?= ($_POST['course_level'] ?? '') === '2' ? 'selected' : '' ?>>2nd Year</option>
                <option value="3" <?= ($_POST['course_level'] ?? '') === '3' ? 'selected' : '' ?>>3rd Year</option>
                <option value="4" <?= ($_POST['course_level'] ?? '') === '4' ? 'selected' : '' ?>>4th Year</option>
              </select>
              <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
            </div>
          </div>

        </div>

        <button type="submit" class="btn-register">Create Account</button>

      </form>

      <div class="login-row">
        Already have an account? <a href="login.php">Sign in &rarr;</a>
      </div>

    </div>
  </div>

</div>

<script src="register.js"></script>
</body>
</html>