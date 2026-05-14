<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location:login.php');
    exit;
}

// ── GET STUDENT ──
$student_id = (int)($_GET['id'] ?? 0);
if ($student_id === 0) {
    header('Location: admin_dashboard.php?tab=students');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: admin_dashboard.php?tab=students');
    exit;
}

// ── UPDATE STUDENT ──
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $course_level = trim($_POST['course_level'] ?? '');
    $remaining_sessions = (int)($_POST['remaining_sessions'] ?? 30);

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        $update_stmt = $pdo->prepare("
            UPDATE users SET 
                first_name = ?,
                last_name = ?,
                email = ?,
                course = ?,
                course_level = ?,
                remaining_sessions = ?
            WHERE id = ?
        ");
        
        if ($update_stmt->execute([$first_name, $last_name, $email, $course, $course_level, $remaining_sessions, $student_id])) {
            $message = 'Student information updated successfully!';
            $message_type = 'success';
            
            // Refresh student data
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();
        } else {
            $message = 'Error updating student information.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Student — UC CCS SIT Monitoring</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="adminDashboard.css"/>
  <style>
    /* ── THEME VARIABLES ── */
    :root {
      --bg-primary: #ffffff;
      --bg-secondary: #f9f8fb;
      --bg-tertiary: #f0eef8;
      --text-primary: #1e1b2e;
      --text-secondary: #4a4560;
      --text-tertiary: #8b84a0;
      --border-color: #e8e6f0;
      --panel-shadow: rgba(74, 30, 138, 0.08);
    }
    
    [data-theme="dark"] {
      --bg-primary: #1a1625;
      --bg-secondary: #2d2441;
      --bg-tertiary: #3d3553;
      --text-primary: #f5f5f7;
      --text-secondary: #d4d0e0;
      --text-tertiary: #a89fb5;
      --border-color: #4a3f5c;
      --panel-shadow: rgba(0, 0, 0, 0.3);
    }

    body {
      background: var(--bg-secondary);
      color: var(--text-primary);
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    .edit-container {
      background: var(--bg-primary);
      box-shadow: 0 4px 20px var(--panel-shadow);
    }
    
    .edit-header {
      border-bottom: 2px solid var(--border-color);
    }
    
    .edit-header h1 {
      color: var(--text-primary);
    }
    
    .edit-header p {
      color: var(--text-tertiary);
    }
    
    .student-info-box {
      background: var(--bg-secondary);
      border: 2px solid var(--border-color);
      color: var(--text-primary);
    }
    
    .form-group label {
      color: var(--text-primary);
    }
    
    .form-group input,
    .form-group select {
      background: var(--bg-secondary);
      color: var(--text-primary);
      border: 2px solid var(--border-color);
    }
    
    .form-group input:focus,
    .form-group select:focus {
      border-color: #4a1e8a;
      box-shadow: 0 0 0 3px rgba(74, 30, 138, 0.1);
    }

    .alert-message {
      transition: all 0.3s ease;
    }

    .form-actions {
      border-top: 2px solid var(--border-color);
    }

    .btn-cancel {
      background: var(--bg-secondary);
      color: #4a1e8a;
      border: 2px solid var(--border-color);
    }

    .btn-cancel:hover {
      background: var(--bg-tertiary);
      border-color: #4a1e8a;
    }

    /* ── THEME TOGGLE ── */
    .theme-toggle-btn {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.5rem;
      padding: 0.5rem;
      margin-left: 1rem;
      transition: transform 0.3s ease;
      color: #fff;
    }
    .theme-toggle-btn:hover {
      transform: scale(1.1);
    }
    .edit-container {
      max-width: 700px;
      margin: 2rem auto;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 4px 20px rgba(74, 30, 138, 0.1);
      padding: 2rem;
    }
    .edit-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 2px solid #f0eef8;
    }
    .edit-header-icon {
      width: 48px;
      height: 48px;
      background: linear-gradient(135deg, #4a1e8a, #8b5cf6);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 1.5rem;
    }
    .edit-header h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem;
      color: #1e1b2e;
      margin: 0;
    }
    .edit-header p {
      font-size: 0.85rem;
      color: #8b84a0;
      margin: 0;
    }
    .alert-message {
      padding: 1rem 1.2rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }
    .alert-success {
      background: #dcfce7;
      color: #166534;
      border: 1px solid #86efac;
    }
    .alert-error {
      background: #fee2e2;
      color: #dc2626;
      border: 1px solid #fca5a5;
    }
    .form-group {
      margin-bottom: 1.5rem;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      color: #1e1b2e;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
    }
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 0.8rem 1rem;
      border: 2px solid #e8e6f0;
      border-radius: 8px;
      font-size: 0.95rem;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.2s;
    }
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #4a1e8a;
      box-shadow: 0 0 0 3px rgba(74, 30, 138, 0.1);
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .form-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 2px solid #f0eef8;
    }
    .btn-save {
      flex: 1;
      padding: 0.9rem 1.5rem;
      background: linear-gradient(135deg, #4a1e8a, #8b5cf6);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-save:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(74, 30, 138, 0.3);
    }
    .btn-cancel {
      flex: 1;
      padding: 0.9rem 1.5rem;
      background: #f0eef8;
      color: #4a1e8a;
      border: 2px solid #e8e6f0;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    .btn-cancel:hover {
      background: #e8e6f0;
      border-color: #4a1e8a;
    }
    .student-info-box {
      background: #f9f8fb;
      border: 2px solid #e8e6f0;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .info-item {
      font-size: 0.85rem;
      color: #8b84a0;
    }
    .info-item strong {
      color: #1e1b2e;
      display: block;
      margin-bottom: 0.2rem;
    }
    @media (max-width: 600px) {
      .edit-container {
        padding: 1.5rem;
      }
      .form-row {
        grid-template-columns: 1fr;
      }
      .student-info-box {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
  <a href="admin_dashboard.php" class="nav-brand">
    <img src="UClogo.png" alt="UC Logo">
    <span class="nav-title">CCS Admin Panel<br>SIT-IN Monitoring System</span>
  </a>
  <ul class="nav-links">
    <li><a href="admin_dashboard.php?tab=home">Home</a></li>
    <li><a href="admin_dashboard.php?tab=search">Search</a></li>
    <li><a href="admin_dashboard.php?tab=students" class="active">Students</a></li>
    <li><button class="theme-toggle-btn" id="themeToggle" title="Toggle Dark Mode">🌙</button></li>
    <li><a href="admin_logout.php" class="btn-nav">Log out</a></li>
  </ul>
</nav>

<div class="admin-layout">
  <div class="tab-content">
    <div class="edit-container">
      <!-- Header -->
      <div class="edit-header">
        <div class="edit-header-icon">✏️</div>
        <div>
          <h1>Edit Student</h1>
          <p>Update student information</p>
        </div>
      </div>

      <!-- Messages -->
      <?php if ($message): ?>
      <div class="alert-message alert-<?= $message_type ?>">
        <?= $message_type === 'success' ? '✓' : '✕' ?>
        <?= htmlspecialchars($message) ?>
      </div>
      <?php endif; ?>

      <!-- Student Info Summary -->
      <div class="student-info-box">
        <div class="info-item">
          <strong>ID Number</strong>
          <?= htmlspecialchars($student['id_number']) ?>
        </div>
        <div class="info-item">
          <strong>Created</strong>
          <?= date('M d, Y', strtotime($student['created_at'] ?? date('Y-m-d'))) ?>
        </div>
      </div>

      <!-- Edit Form -->
      <form method="POST" action="">
        <div class="form-row">
          <div class="form-group">
            <label for="first_name">First Name *</label>
            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($student['first_name']) ?>" required>
          </div>
          <div class="form-group">
            <label for="last_name">Last Name *</label>
            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($student['last_name']) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="email">Email Address *</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="course">Course</label>
            <input type="text" id="course" name="course" value="<?= htmlspecialchars($student['course']) ?>">
          </div>
          <div class="form-group">
            <label for="course_level">Year Level</label>
            <select id="course_level" name="course_level">
              <option value="">Select year</option>
              <option value="1" <?= $student['course_level'] === '1' ? 'selected' : '' ?>>1st Year</option>
              <option value="2" <?= $student['course_level'] === '2' ? 'selected' : '' ?>>2nd Year</option>
              <option value="3" <?= $student['course_level'] === '3' ? 'selected' : '' ?>>3rd Year</option>
              <option value="4" <?= $student['course_level'] === '4' ? 'selected' : '' ?>>4th Year</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="remaining_sessions">Remaining Sessions</label>
          <input type="number" id="remaining_sessions" name="remaining_sessions" value="<?= $student['remaining_sessions'] ?? 30 ?>" min="0" max="999">
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <a href="admin_dashboard.php?tab=students" class="btn-cancel">Cancel</a>
          <button type="submit" class="btn-save">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<footer>
  &copy; <?= date('Y') ?> University of Cebu — SIT-IN Monitoring System | Admin Panel
</footer>

<script>
// ── THEME TOGGLE ──
document.addEventListener('DOMContentLoaded', function() {
  const themeToggle = document.getElementById('themeToggle');
  const htmlElement = document.documentElement;
  
  // Get saved theme from localStorage, default to 'light'
  const savedTheme = localStorage.getItem('theme') || 'light';
  htmlElement.setAttribute('data-theme', savedTheme);
  updateThemeButton(savedTheme);
  
  // Toggle theme on button click
  themeToggle.addEventListener('click', function() {
    const currentTheme = htmlElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    htmlElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeButton(newTheme);
  });
  
  function updateThemeButton(theme) {
    const button = document.getElementById('themeToggle');
    button.textContent = theme === 'light' ? '🌙' : '☀️';
    button.title = theme === 'light' ? 'Switch to Dark Mode' : 'Switch to Light Mode';
  }
});
</script>

</body>
</html>
