<?php
session_start();
require 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// 1. Fetch current user data to populate the form
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and trim inputs
    $last_name    = trim($_POST['last_name'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $course       = trim($_POST['course'] ?? '');
    $course_level = trim($_POST['course_level'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');

    // Image Upload Logic
    $profile_pic = $user['profile_picture'] ?? 'Studentlogo.png'; 
    
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            // Create a unique name to avoid overwriting files with the same name
            $new_name = "profile_" . $_SESSION['user_id'] . "_" . time() . "." . $ext;
            $upload_path = 'uploads/' . $new_name;

            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                $profile_pic = $new_name;
            } else {
                $error = 'Failed to move uploaded file.';
            }
        } else {
            $error = 'Invalid image format. Please use JPG or PNG.';
        }
    }

    // Database Update Logic
    if (empty($error)) {
        if (empty($last_name) || empty($first_name) || empty($email)) {
            $error = 'Please fill in all required fields (marked with *).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Update the database (including the profile_picture column)
                $sql = "UPDATE users SET 
                        last_name = ?, first_name = ?, middle_name = ?, 
                        course = ?, course_level = ?, email = ?, address = ?, profile_picture = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $last_name, $first_name, $middle_name, 
                    $course, $course_level, $email, $address, 
                    $profile_pic, $_SESSION['user_id']
                ]);

                // Refresh the user variable to show updated data immediately
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                // Update session name for the navbar
                $_SESSION['first_name'] = $user['first_name'];

                $success = 'Profile updated successfully!';
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — UC CCS SIT Monitoring</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css"> 
    <link rel="stylesheet" href="edit_profile.css"> 
    <?php require_once 'theme.php'; echo theme_styles(); ?>
    <style>
        /* ── Body & footer layout ── */
        html, body { height: 100%; margin: 0; }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: var(--bg-body) !important;
        }
        /* Full-width bg wrapper eliminates the pink side gaps */
        .page-bg-wrapper {
            flex: 1;
            width: 100%;
            background: var(--bg-body) !important;
        }
        footer {
            flex-shrink: 0;
            padding: 1.1rem 2rem;
            text-align: center;
            font-size: 0.82rem;
            background: var(--footer-bg) !important;
            color: var(--footer-text) !important;
        }

        /* ══════════════════════════════════════
           DARK MODE — Edit Profile Overrides
        ══════════════════════════════════════ */
        [data-theme="dark"] .edit-page-container .info-panel,
        [data-theme="dark"] .sticky-panel {
            background: linear-gradient(160deg, #1a0d38 0%, #261e3d 55%, #3b2560 100%) !important;
            border: 1px solid rgba(130,90,230,0.25) !important;
            box-shadow: 0 4px 28px rgba(0,0,0,0.5) !important;
        }
        [data-theme="dark"] .edit-page-container .info-panel .panel-header {
            color: rgba(200,180,255,0.5) !important;
        }
        [data-theme="dark"] .edit-page-container .avatar-frame {
            background: linear-gradient(135deg, #e0a832, #f0c050, #e0a832) !important;
        }
        [data-theme="dark"] .edit-page-container .avatar-frame img {
            border-color: #1a0d38 !important;
        }
        [data-theme="dark"] .edit-page-container .info-item {
            border-bottom-color: rgba(255,255,255,0.06) !important;
        }
        [data-theme="dark"] .edit-page-container .info-item .label { color: #e0a832 !important; }
        [data-theme="dark"] .edit-page-container .info-item .val   { color: rgba(255,255,255,0.88) !important; }

        [data-theme="dark"] .main-form-panel {
            background: #1c1730 !important;
            border-color: rgba(130,90,230,0.18) !important;
            box-shadow: 0 12px 40px rgba(0,0,0,0.5) !important;
        }
        [data-theme="dark"] .main-form-panel .panel-header {
            background: linear-gradient(90deg, #211a35, #1c1730) !important;
            color: #ddd6f8 !important;
            border-bottom-color: #2b2245 !important;
        }
        [data-theme="dark"] .main-form-panel .panel-header::before {
            background: linear-gradient(180deg, #e0a832, #f0c050) !important;
        }
        [data-theme="dark"] .main-form-panel .form-content {
            background: #1c1730 !important;
        }
        [data-theme="dark"] .form-section { border-bottom-color: #2b2245 !important; }
        [data-theme="dark"] .form-section-title { color: #e0a832 !important; }
        [data-theme="dark"] .form-section-title::after {
            background: linear-gradient(90deg, #3b3060, transparent) !important;
        }

        [data-theme="dark"] .field label { color: #a89fbe !important; }

        [data-theme="dark"] .field input,
        [data-theme="dark"] .field select,
        [data-theme="dark"] .presentable-form input {
            background: #261e3d !important;
            color: #ece8ff !important;
            border-color: #3b3060 !important;
        }
        [data-theme="dark"] .field input:hover,
        [data-theme="dark"] .field select:hover  { border-color: #6b5aaa !important; }
        [data-theme="dark"] .field input:focus,
        [data-theme="dark"] .field select:focus,
        [data-theme="dark"] .presentable-form input:focus {
            border-color: #9b72f5 !important;
            background: #2d2350 !important;
            box-shadow: 0 0 0 3px rgba(155,114,245,0.15) !important;
        }
        [data-theme="dark"] .field input::placeholder { color: #6b6488 !important; }

        [data-theme="dark"] input[type="file"] {
            background: #211a35 !important;
            border-color: #4a3f6e !important;
            color: #c4b5fd !important;
        }
        [data-theme="dark"] input[type="file"]:hover {
            background: #261e3d !important;
            border-color: #9b72f5 !important;
        }
        [data-theme="dark"] .field-locked input {
            background: #1a1528 !important;
            color: #6b6488 !important;
            border-color: #2b2245 !important;
        }
        [data-theme="dark"] .field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239b72f5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E") !important;
        }
        [data-theme="dark"] select optgroup { color: #c4b5fd !important; background: #261e3d !important; }
        [data-theme="dark"] select option   { color: #ece8ff !important; background: #261e3d !important; }

        [data-theme="dark"] .alert-error   { background: #2d0a0a !important; border-color: #7f1d1d !important; color: #f87171 !important; }
        [data-theme="dark"] .alert-success { background: #052e16 !important; border-color: #14532d !important; color: #4ade80 !important; }

        [data-theme="dark"] .btn-primary {
            background: linear-gradient(135deg, #6c3fc7, #9b72f5) !important;
            box-shadow: 0 4px 16px rgba(108,63,199,0.4) !important;
        }
        [data-theme="dark"] .btn-primary:hover {
            box-shadow: 0 8px 24px rgba(108,63,199,0.5) !important;
        }
        [data-theme="dark"] .btn-row { border-top-color: #2b2245 !important; }
    </style>
<body>

<nav>
    <a href="dashboard.php" class="nav-brand">
        <img src="UClogo.png" alt="UC Logo">
        <span class="nav-title">College of Computer Studies<br>SIT-IN Monitoring System</span>
    </a>
    <ul class="nav-links">
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="edit_profile.php" class="active">Edit Profile</a></li>
        <li><a href="sit_in_summary.php">Sit-in Summary</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="user_reservation.php">Reservation</a></li>
        <li><a href="sessions.php" >Session</a></li>
        <?php echo theme_toggle_button(); ?>
        <li><a href="logout.php" class="btn-nav">Log out</a></li>
    </ul>
</nav>

<div class="page-bg-wrapper">
<div class="dashboard-container edit-page-container">
    
    <aside class="info-panel sticky-panel">
        <div class="panel-header">Profile Summary</div>
        <div class="profile-card">
            <div class="avatar-frame">
                <?php 
                    // Use the uploaded pic if it exists, otherwise use default
                    $display_pic = (!empty($user['profile_picture']) && $user['profile_picture'] !== 'Studentlogo.png') 
                                   ? 'uploads/' . $user['profile_picture'] 
                                   : 'Studentlogo.png';
                ?>
                <img src="<?= htmlspecialchars($display_pic) ?>" alt="User Avatar">
            </div>
            <div class="info-list">
                <div class="info-item">
                    <span class="label">ID Number:</span>
                    <span class="val"><?= htmlspecialchars($user['id_number']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Name:</span>
                    <span class="val"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Course:</span>
                    <span class="val"><?= htmlspecialchars($user['course']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Email:</span>
                    <span class="val"><?= htmlspecialchars($user['email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Address:</span>
                    <span class="val"><?= htmlspecialchars($user['address']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Year:</span>
                    <span class="val"><?= htmlspecialchars($user['course_level']) ?></span>
                </div>
            </div>
        </div>
    </aside>

    <main class="form-panel main-form-panel">
        <div class="panel-header">Update Profile Details</div>
        
        <div class="form-content">

            <?php if ($error): ?>
                <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
            <?php elseif ($success): ?>
                <div class="alert alert-success">✓ <?= $success ?></div>
            <?php endif; ?>

            <form method="POST" action="edit_profile.php" class="presentable-form" enctype="multipart/form-data">

                <div class="form-section">
                    <div class="form-section-title">Profile Information</div>

                <div class="field">
                    <label for="profile_pic">Change Profile Picture</label>
                    <div class="input-wrap">
                        <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="padding: 0.5rem;">
                    </div>
                </div>

                <div class="field field-locked">
                    <label for="id_number">ID Number (Read-Only)</label>
                    <div class="input-wrap">
                        <input type="text" id="id_number" value="<?= htmlspecialchars($user['id_number']) ?>" disabled>
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-5a2 2 0 00-2-2H6a2 2 0 00-2 2v5a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                    </div>
                    <div class="field">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-grid split-3">
                    <div class="field">
                        <label for="course">Course *</label>
                        <div class="input-wrap">
                            <select id="course" name="course" required>
                                <option value="" disabled <?= empty($user['course']) ? 'selected' : '' ?>>Select course</option>
                                <optgroup label="College of Computer Studies (CCS)">
                                    <option value="BSIT" <?= ($user['course'] ?? '') === 'BSIT' ? 'selected' : '' ?>>Information Technology</option>
                                    <option value="BSCS" <?= ($user['course'] ?? '') === 'BSCS' ? 'selected' : '' ?>>Computer Science</option>
                                    <option value="BSIS" <?= ($user['course'] ?? '') === 'BSIS' ? 'selected' : '' ?>>Information Systems</option>
                                    <option value="ACT"  <?= ($user['course'] ?? '') === 'ACT'  ? 'selected' : '' ?>>ACT</option>
                                </optgroup>
                                <optgroup label="College of Engineering">
                                    <option value="BSCpE" <?= ($user['course'] ?? '') === 'BSCpE' ? 'selected' : '' ?>>Computer Engineering</option>
                                    <option value="BSCE"  <?= ($user['course'] ?? '') === 'BSCE'  ? 'selected' : '' ?>>Civil Engineering</option>
                                    <option value="BSME"  <?= ($user['course'] ?? '') === 'BSME'  ? 'selected' : '' ?>>Mechanical Engineering</option>
                                    <option value="BSEE"  <?= ($user['course'] ?? '') === 'BSEE'  ? 'selected' : '' ?>>Electrical Engineering</option>
                                    <option value="BSIE"  <?= ($user['course'] ?? '') === 'BSIE'  ? 'selected' : '' ?>>Industrial Engineering</option>
                                    <option value="BSNAME" <?= ($user['course'] ?? '') === 'BSNAME' ? 'selected' : '' ?>>Naval Architecture and Marine Engineering</option>
                                </optgroup>
                                <optgroup label="College of Education">
                                    <option value="BEEd" <?= ($user['course'] ?? '') === 'BEEd' ? 'selected' : '' ?>>Elementary Education (BEEd)</option>
                                    <option value="BSEd" <?= ($user['course'] ?? '') === 'BSEd' ? 'selected' : '' ?>>Secondary Education (BSEd)</option>
                                </optgroup>
                                <optgroup label="Criminal Justice & Arts">
                                    <option value="BS Crim" <?= ($user['course'] ?? '') === 'BS Crim' ? 'selected' : '' ?>>Criminology</option>
                                    <option value="IndPsych" <?= ($user['course'] ?? '') === 'IndPsych' ? 'selected' : '' ?>>Industrial Psychology</option>
                                    <option value="AB PolSci" <?= ($user['course'] ?? '') === 'AB PolSci' ? 'selected' : '' ?>>AB Political Science</option>
                                    <option value="AB English" <?= ($user['course'] ?? '') === 'AB English' ? 'selected' : '' ?>>AB English</option>
                                </optgroup>
                                <optgroup label="Business & Management">
                                    <option value="BS Commerce" <?= ($user['course'] ?? '') === 'BS Commerce' ? 'selected' : '' ?>>Commerce</option>
                                    <option value="BS Accountancy" <?= ($user['course'] ?? '') === 'BS Accountancy' ? 'selected' : '' ?>>Accountancy</option>
                                    <option value="BSHRM" <?= ($user['course'] ?? '') === 'BSHRM' ? 'selected' : '' ?>>Hotel and Restaurant Management</option>
                                    <option value="BSCA" <?= ($user['course'] ?? '') === 'BSCA' ? 'selected' : '' ?>>Customs Administration</option>
                                    <option value="CompSec" <?= ($user['course'] ?? '') === 'CompSec' ? 'selected' : '' ?>>Computer Secretarial</option>
                                </optgroup>
                                <optgroup label="Special Programs & Short Courses">
                                    <option value="CISCO" <?= ($user['course'] ?? '') === 'CISCO' ? 'selected' : '' ?>>CISCO Networking Academy (Module 1 - 4)</option>
                                    <option value="EngComm" <?= ($user['course'] ?? '') === 'EngComm' ? 'selected' : '' ?>>English Communication Skills</option>
                                    <option value="Korean" <?= ($user['course'] ?? '') === 'Korean' ? 'selected' : '' ?>>Conversational Korean</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <div class="field">
    <label for="course_level">Year Level *</label>
                    <div class="input-wrap">
                        <select id="course_level" name="course_level" required>
                            <option value="" disabled <?= empty($user['course_level']) ? 'selected' : '' ?>>Select Year</option>
                            
                            <?php
                            // Your custom order: 4, 2, 1, 3, 5
                            $custom_order = [4, 2, 1, 3, 5];
                            
                            foreach ($custom_order as $level): 
                                // Logic to determine the suffix (st, nd, rd, th)
                                $suffix = 'th';
                                if ($level == 1) $suffix = 'st';
                                elseif ($level == 2) $suffix = 'nd';
                                elseif ($level == 3) $suffix = 'rd';
                                
                                $isSelected = ((int)($user['course_level'] ?? 0) === $level) ? 'selected' : '';
                            ?>
                                <option value="<?= $level ?>" <?= $isSelected ?>>
                                    <?= $level . $suffix ?> Year
                                </option>
                            <?php endforeach; ?>
                        </select>
    </div>
</div>
                </div>
                </div><!-- end profile info section -->

                <!-- Contact Info -->
                <div class="form-section">
                    <div class="form-section-title">Contact Information</div>
                    <div class="field">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="address">Address *</label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($user['address']) ?>" required>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </main>
</div><!-- /.dashboard-container -->
</div><!-- /.page-bg-wrapper -->

<footer>
    &copy; <?= date('Y') ?> University of Cebu — CCS SIT Monitoring System
</footer>
<?php echo theme_script(); ?>
</body>
</html>