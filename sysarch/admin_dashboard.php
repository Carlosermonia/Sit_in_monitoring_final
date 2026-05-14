<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location:login.php');
    exit;
}

$admin_user = $_SESSION['admin_user'];

// ── STATS ──
$total_students    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$currently_sitin   = $pdo->query("SELECT COUNT(*) FROM sit_in_records WHERE status = 'active'")->fetchColumn();
$total_sitin       = $pdo->query("SELECT COUNT(*) FROM sit_in_records")->fetchColumn();

// ── PURPOSE DISTRIBUTION ──
$purpose_data = $pdo->query("SELECT purpose, COUNT(*) as count FROM sit_in_records WHERE purpose IS NOT NULL AND purpose != '' GROUP BY purpose ORDER BY count DESC")->fetchAll();
$chart_labels = [];
$chart_data = [];
foreach ($purpose_data as $purpose) {
    $chart_labels[] = $purpose['purpose'];
    $chart_data[] = $purpose['count'];
}

// ── FEEDBACK REPORTS ──
$feedbacks = $pdo->query("
    SELECT f.*, u.first_name, u.last_name, u.id_number, u.course,
           s.purpose, s.lab_number
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    JOIN sit_in_records s ON f.sit_in_id = s.id
    ORDER BY f.created_at DESC
")->fetchAll();

$avg_rating    = count($feedbacks) ? round(array_sum(array_column($feedbacks, 'rating')) / count($feedbacks), 1) : 0;
$total_feedback = count($feedbacks);
$rating_counts  = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
foreach ($feedbacks as $fb) $rating_counts[(int)$fb['rating']]++;

// ── ANNOUNCEMENTS ──
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10")->fetchAll();

// ── POST ANNOUNCEMENT ──
$ann_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement'])) {
    $text = trim($_POST['announcement']);
    if (!empty($text)) {
        $stmt = $pdo->prepare("INSERT INTO announcements (content, created_at) VALUES (?, NOW())");
        $stmt->execute([$text]);
        $ann_msg = 'success';
        header('Location: admin_dashboard.php?tab=home&ann=1');
        exit;
    }
}

// ── DELETE ANNOUNCEMENT ──
if (isset($_GET['delete_ann_id'])) {
    $ann_id = (int)$_GET['delete_ann_id'];
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$ann_id]);
    header('Location: admin_dashboard.php?tab=home&ann_deleted=1');
    exit;
}

// ── SEARCH STUDENTS ──
$search_query   = trim($_GET['q'] ?? '');
$search_results = [];
if ($search_query !== '') {
    $like = "%$search_query%";
    // We use CONCAT to join first and last name so you can search "First Last" or "Last First"
    $stmt = $pdo->prepare("SELECT * FROM users 
                           WHERE id_number LIKE ? 
                           OR first_name LIKE ? 
                           OR last_name LIKE ? 
                           OR email LIKE ? 
                           OR course LIKE ? 
                           OR CONCAT(first_name, ' ', last_name) LIKE ?
                           OR CONCAT(last_name, ' ', first_name) LIKE ?
                           ORDER BY last_name ASC");
    
    // Pass the $like variable for every '?' placeholder (there are 7 now)
    $stmt->execute([$like, $like, $like, $like, $like, $like, $like]);
    $search_results = $stmt->fetchAll();
}

// ── ALL STUDENTS ──
$students = $pdo->query("SELECT * FROM users ORDER BY last_name ASC")->fetchAll();

// ── SIT-IN RECORDS ──
// Exclude pending reservation requests from the sit-in records list.
$sit_records = $pdo->query("
    SELECT s.*, u.first_name, u.last_name, u.id_number, u.course
    FROM sit_in_records s
    JOIN users u ON s.id_number = u.id_number 
    WHERE TRIM(COALESCE(s.status, '')) NOT IN ('pending', '')
    ORDER BY s.login_time DESC LIMIT 50
")->fetchAll();

// ── PENDING RESERVATIONS ──
$pending_reservations = $pdo->query("
    SELECT s.*, u.first_name, u.last_name, u.id_number, u.course, u.email
    FROM sit_in_records s
    JOIN users u ON s.id_number = u.id_number 
    WHERE TRIM(COALESCE(s.status, '')) IN ('pending', '')
    ORDER BY s.login_time ASC
")->fetchAll();

// ── ENSURE REWARD COLUMNS EXIST ──
try {
    $pdo->exec("ALTER TABLE sit_in_records ADD COLUMN IF NOT EXISTS reward_points INT DEFAULT 0");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS points INT DEFAULT 0");
} catch (Exception $e) { /* columns may already exist */ }

// ── TIMEOUT WITH REWARD POINTS (POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timeout_id'])) {
    $tid    = (int)$_POST['timeout_id'];
    $reward = (int)$_POST['reward_points']; // 0 or 1

    // 1. Fetch the sit-in record
    $rec = $pdo->prepare("SELECT id_number FROM sit_in_records WHERE id = ? AND status = 'active'");
    $rec->execute([$tid]);
    $sit = $rec->fetch();

    // 2. Mark sit-in as completed and save reward points
    $pdo->prepare("UPDATE sit_in_records SET status='completed', logout_time=NOW(), reward_points=? WHERE id=?")
        ->execute([$reward, $tid]);

    if ($sit) {
        $id_number = $sit['id_number'];

        // 3. Add reward point to user's points total
        $pdo->prepare("UPDATE users SET points = points + ? WHERE id_number = ?")
            ->execute([$reward, $id_number]);

        // 4. Check if total points reached 3 → give +1 session and subtract 3 points
        $userRow = $pdo->prepare("SELECT points FROM users WHERE id_number = ?");
        $userRow->execute([$id_number]);
        $currentPoints = (int)$userRow->fetchColumn();

        if ($currentPoints >= 3) {
            $pdo->prepare("
                UPDATE users
                SET remaining_sessions = remaining_sessions + 1,
                    points = points - 3
                WHERE id_number = ?
            ")->execute([$id_number]);
        }

        // 5. Decrement remaining_sessions by 1 (minimum 0)
        $pdo->prepare("
            UPDATE users
            SET remaining_sessions = GREATEST(remaining_sessions - 1, 0)
            WHERE id_number = ?
        ")->execute([$id_number]);
    }

    header('Location: admin_dashboard.php?tab=sitin&timed_out=1');
    exit;
}

// ── DELETE STUDENT ──
if (isset($_GET['delete_id'])) {
    $did = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$did]);
    header('Location: admin_dashboard.php?tab=students');
    exit;
}

// ── RESET SESSIONS ──
if (isset($_GET['reset_sessions'])) {
    $pdo->exec("UPDATE users SET remaining_sessions = 30");
    header('Location: admin_dashboard.php?tab=students&reset=1');
    exit;
}

// ── RESERVATIONS MANAGEMENT ──
if (isset($_GET['approve_reservation'])) {
    $res_id = (int)$_GET['approve_reservation'];
    $pdo->prepare("UPDATE sit_in_records SET status='active' WHERE id=?")->execute([$res_id]);
    
    // Decrement student's sessions
    $stmt = $pdo->prepare("SELECT id_number FROM sit_in_records WHERE id = ?");
    $stmt->execute([$res_id]);
    $res = $stmt->fetch();
    if ($res) {
        $pdo->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE id_number = ?")
            ->execute([$res['id_number']]);
    }
    
    header('Location: admin_dashboard.php?tab=reservations&approved=1');
    exit;
}

if (isset($_GET['reject_reservation'])) {
    $res_id = (int)$_GET['reject_reservation'];
    $pdo->prepare("DELETE FROM sit_in_records WHERE id=?")->execute([$res_id]);
    header('Location: admin_dashboard.php?tab=reservations&rejected=1');
    exit;
}

// ── SOFTWARE MANAGEMENT ──
// Ensure the table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS software_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_number VARCHAR(10) NOT NULL,
    software_name VARCHAR(100) NOT NULL,
    version VARCHAR(50),
    category VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lab_software (lab_number, software_name)
)");

// Add software
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_software'])) {
    $sw_lab  = trim($_POST['sw_lab']  ?? '');
    $sw_name = trim($_POST['sw_name'] ?? '');
    $sw_ver  = trim($_POST['sw_version']  ?? '');
    $sw_cat  = trim($_POST['sw_category'] ?? '');
    $sw_desc = trim($_POST['sw_description'] ?? '');
    if ($sw_lab && $sw_name) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO software_availability (lab_number, software_name, version, category, description) VALUES (?,?,?,?,?)");
        $stmt->execute([$sw_lab, $sw_name, $sw_ver, $sw_cat, $sw_desc]);
    }
    header('Location: admin_dashboard.php?tab=reservations&sw_added=1');
    exit;
}

// Delete software
if (isset($_GET['delete_sw'])) {
    $sw_id = (int)$_GET['delete_sw'];
    $pdo->prepare("DELETE FROM software_availability WHERE id=?")->execute([$sw_id]);
    header('Location: admin_dashboard.php?tab=reservations&sw_deleted=1');
    exit;
}

// Fetch all software grouped by lab
$all_labs_for_sw = ['524','526','528','530','542'];
$software_by_lab = [];
foreach ($all_labs_for_sw as $l) {
    $s = $pdo->prepare("SELECT * FROM software_availability WHERE lab_number=? ORDER BY category, software_name");
    $s->execute([$l]);
    $software_by_lab[$l] = $s->fetchAll();
}
// ── LEADERBOARD DATA ──
$leaderboard_query = $pdo->query("
    SELECT 
        u.first_name, 
        u.last_name, 
        u.id_number, 
        u.course,
        u.course_level,
        u.profile_picture,
        COUNT(s.id) AS total_sessions,
        ROUND(SUM(TIMESTAMPDIFF(MINUTE, s.login_time, IFNULL(s.logout_time, NOW()))) / 60.0, 1) AS total_hours,
        COUNT(DISTINCT DATE(s.login_time)) AS total_points,
        ROUND(
            (COUNT(DISTINCT DATE(s.login_time)) * 0.50) +
            (SUM(TIMESTAMPDIFF(MINUTE, s.login_time, IFNULL(s.logout_time, NOW()))) / 60.0 * 0.30) +
            (COUNT(s.id) * 0.20),
        2) AS score
    FROM users u
    JOIN sit_in_records s ON u.id_number = s.id_number
    WHERE s.status = 'completed'
    GROUP BY u.id
    ORDER BY score DESC
    LIMIT 5
")->fetchAll();

// ── ACTIVE TAB ──
$tab = $_GET['tab'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — UC CCS SIT Monitoring</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="adminDashboard.css"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    /* ══════════════════════════════════════
       THEME CSS VARIABLES
    ══════════════════════════════════════ */
    :root {
      --bg-body:          #f0eef8;
      --bg-panel:         #ffffff;
      --bg-nav:           #2d1b4e;
      --bg-nav-active:    rgba(255,255,255,0.15);
      --bg-header:        #3d2b5e;
      --bg-card:          #ffffff;
      --bg-input:         #ffffff;
      --bg-table-head:    #f0eef8;
      --bg-table-row:     #ffffff;
      --bg-table-alt:     #f9f8fb;
      --bg-table-hover:   #f5f3fc;
      --bg-tag:           #ede9f7;
      --bg-modal:         #ffffff;
      --bg-stat-card:     #ffffff;

      --text-primary:     #1e1b2e;
      --text-secondary:   #4a4560;
      --text-muted:       #8b84a0;
      --text-nav:         rgba(255,255,255,0.80);
      --text-nav-active:  #ffffff;
      --text-label:       #6b6480;
      --text-val:         #1e1b2e;
      --text-table:       #2d2444;
      --text-heading:     #1e1b2e;
      --text-subheading:  #6b6480;

      --border:           #e8e6f0;
      --border-panel:     rgba(74,30,138,0.08);
      --shadow-panel:     0 4px 24px rgba(74,30,138,0.08);
      --shadow-nav:       0 2px 12px rgba(45,27,78,0.15);
      --shadow-card:      0 2px 12px rgba(74,30,138,0.07);
      --shadow-modal:     0 20px 60px rgba(74,30,138,0.18);

      --accent:           #4a1e8a;
      --accent-mid:       #8b5cf6;
      --accent-light:     #f3f0ff;
      --accent-gold:      #c9a227;
      --accent-gold-btn:  #c9a227;

      --alert-success-bg: #f0fdf4;
      --alert-success-text:#166534;
      --alert-error-bg:   #fef2f2;
      --alert-error-text: #dc2626;

      --id-badge-bg:      #ede9f7;
      --id-badge-text:    #4a1e8a;
      --session-badge-bg: #dcfce7;
      --session-badge-text:#166534;
      --session-low-bg:   #fef2f2;
      --session-low-text: #dc2626;
      --status-active-bg: #dcfce7;
      --status-active-text:#166534;
      --status-done-bg:   #f3f4f6;
      --status-done-text: #374151;

      --footer-bg:        #2d1b4e;
      --footer-text:      rgba(255,255,255,0.5);

      --scrollbar-track:  #f0eef8;
      --scrollbar-thumb:  #c4b8e0;

      /* Feedback-tab specific */
      --fb-card-bg:       #ffffff;
      --fb-summary-bg:    #ffffff;
      --fb-rating-bg:     #ffffff;
      --fb-num-color:     #1e1b2e;
      --fb-lbl-color:     #8b84a0;
      --fb-bar-track:     #f0eef8;
      --fb-pill-bg:       #f3f0ff;
      --fb-pill-text:     #4a1e8a;
      --fb-msg-color:     #4a4560;
      --fb-divider:       #f0eef8;
      --fb-count-pill-bg: #f3f0ff;
      --fb-count-pill-text:#4a1e8a;
      --fb-heading-color: #1e1b2e;
    }

    [data-theme="dark"] {
      --bg-body:          #110e1c;
      --bg-panel:         #1e1830;
      --bg-nav:           #0d0a18;
      --bg-nav-active:    rgba(255,255,255,0.12);
      --bg-header:        #2a2040;
      --bg-card:          #1e1830;
      --bg-input:         #2a2040;
      --bg-table-head:    #2a2040;
      --bg-table-row:     #1e1830;
      --bg-table-alt:     #221c36;
      --bg-table-hover:   #2e2550;
      --bg-tag:           #2a2040;
      --bg-modal:         #1e1830;
      --bg-stat-card:     #1e1830;

      --text-primary:     #f0ecff;
      --text-secondary:   #c9c1e0;
      --text-muted:       #8b84a0;
      --text-nav:         rgba(220,210,255,0.75);
      --text-nav-active:  #f0ecff;
      --text-label:       #a89fbe;
      --text-val:         #e8e0ff;
      --text-table:       #d4cce8;
      --text-heading:     #f0ecff;
      --text-subheading:  #9b93b8;

      --border:           #2e2545;
      --border-panel:     rgba(120,80,220,0.15);
      --shadow-panel:     0 4px 24px rgba(0,0,0,0.4);
      --shadow-nav:       0 2px 16px rgba(0,0,0,0.5);
      --shadow-card:      0 2px 12px rgba(0,0,0,0.3);
      --shadow-modal:     0 20px 60px rgba(0,0,0,0.6);

      --accent:           #9b6ef3;
      --accent-mid:       #b08af8;
      --accent-light:     #2a2040;
      --accent-gold:      #d4a940;
      --accent-gold-btn:  #c9a227;

      --alert-success-bg: #052e16;
      --alert-success-text:#4ade80;
      --alert-error-bg:   #1f0505;
      --alert-error-text: #f87171;

      --id-badge-bg:      #2a2040;
      --id-badge-text:    #b08af8;
      --session-badge-bg: #052e16;
      --session-badge-text:#4ade80;
      --session-low-bg:   #1f0505;
      --session-low-text: #f87171;
      --status-active-bg: #052e16;
      --status-active-text:#4ade80;
      --status-done-bg:   #1f2937;
      --status-done-text: #9ca3af;

      --footer-bg:        #0d0a18;
      --footer-text:      rgba(200,190,240,0.45);

      --scrollbar-track:  #1a1528;
      --scrollbar-thumb:  #4a3f6e;

      /* Feedback-tab specific */
      --fb-card-bg:       #1e1830;
      --fb-summary-bg:    #1e1830;
      --fb-rating-bg:     #1e1830;
      --fb-num-color:     #f0ecff;
      --fb-lbl-color:     #8b84a0;
      --fb-bar-track:     #2a2040;
      --fb-pill-bg:       #2a2040;
      --fb-pill-text:     #b08af8;
      --fb-msg-color:     #c9c1e0;
      --fb-divider:       #2e2545;
      --fb-count-pill-bg: #2a2040;
      --fb-count-pill-text:#b08af8;
      --fb-heading-color: #f0ecff;
    }

    /* ── SMOOTH TRANSITIONS ── */
    *, *::before, *::after {
      transition: background-color 0.25s ease, color 0.25s ease,
                  border-color 0.25s ease, box-shadow 0.25s ease;
    }

    body {
      background: var(--bg-body) !important;
      color: var(--text-primary) !important;
    }

    /* ── ERROR MODAL ── */
    .error-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 9999;
      align-items: center;
      justify-content: center;
    }
    .error-modal-overlay.show {
      display: flex;
    }
    .error-modal-box {
      background: var(--bg-primary);
      border-radius: 14px;
      padding: 2rem 2rem;
      text-align: center;
      max-width: 380px;
      width: 90%;
      box-shadow: 0 20px 60px var(--panel-shadow);
      animation: errorPop 0.3s ease-out;
    }
    .error-modal-box h2 {
      color: var(--text-primary);
    }
    .error-modal-box .error-msg {
      color: var(--text-tertiary);
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
    @keyframes errorPop {
      from {
        transform: scale(0.9);
        opacity: 0;
      }
      to {
        transform: scale(1);
        opacity: 1;
      }
    }
    .error-modal-icon {
      width: 70px;
      height: 70px;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.2rem;
      box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }
    .error-modal-icon svg {
      width: 36px;
      height: 36px;
      stroke: #fff;
      stroke-width: 2.5;
      fill: none;
    }
    .error-modal-box h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.4rem;
      color: #1e1b2e;
      margin: 0 0 0.5rem;
    }
    .error-modal-box .error-msg {
      color: #64748b;
      font-size: 0.9rem;
      margin-bottom: 1.8rem;
      line-height: 1.5;
    }
    .error-modal-actions {
      display: flex;
      gap: 10px;
      justify-content: center;
    }
    .error-modal-actions button {
      padding: 0.6rem 1.4rem;
      border: none;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .error-modal-ok {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: #fff;
    }
    .error-modal-ok:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 16px rgba(239, 68, 68, 0.3);
    }
    /* ── ANNOUNCEMENT DELETE BUTTON ── */
    .ann-item {
      position: relative;
    }
    .ann-delete-btn {
      display: inline-block;
      margin-top: 0.8rem;
      font-size: 0.75rem;
      padding: 0.4rem 0.8rem;
      background: #fee2e2;
      color: #dc2626;
      border-radius: 6px;
      text-decoration: none;
      transition: all 0.2s;
      font-weight: 600;
      cursor: pointer;
    }
    .ann-delete-btn:hover {
      background: #fecaca;
      color: #991b1b;
      transform: translateY(-1px);
    }
    /* ── RESERVATION BUTTONS ── */
    .btn-approve {
      background: #dcfce7 !important;
      color: #166534 !important;
      border: 1px solid #86efac !important;
    }
    .btn-approve:hover {
      background: #86efac !important;
      color: #065f46 !important;
    }
    .btn-reject {
      background: #fee2e2 !important;
      color: #dc2626 !important;
      border: 1px solid #fca5a5 !important;
    }
    .btn-reject:hover {
      background: #fecaca !important;
      color: #991b1b !important;
    }
    /* ── PIE CHART STYLES ── */
    .chart-container {
      position: relative;
      width: 100%;
      height: 300px;
      margin-top: 1rem;
    }
    .chart-legend {
      display: flex;
      justify-content: center;
      gap: 2rem;
      margin-top: 1.5rem;
      flex-wrap: wrap;
    }
    .chart-legend-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.85rem;
      color: var(--text-muted);
    }
    .chart-legend-dot {
      width: 12px;
      height: 12px;
      border-radius: 3px;
    }

    /* ══════════════════════════════════════
       GLOBAL DARK MODE OVERRIDES
    ══════════════════════════════════════ */

    /* NAV */
    nav { background: var(--bg-nav) !important; box-shadow: var(--shadow-nav) !important; }
    .nav-title { color: #fff !important; }
    .nav-links > li > a { color: var(--text-nav) !important; }
    .nav-links > li > a:hover,
    .nav-links > li > a.active { color: var(--text-nav-active) !important; background: var(--bg-nav-active) !important; }
    .btn-nav { background: var(--accent-gold-btn) !important; color: #fff !important; }

    /* PANELS / CARDS */
    .panel, .stat-card, .search-hero, .page-title,
    .result-card, .modal-box, .fb-summary-card, .fb-rating-panel, .fb-card {
      background: var(--bg-panel) !important;
      box-shadow: var(--shadow-card) !important;
    }
    .panel-header { background: var(--bg-header) !important; color: #fff !important; }
    .panel-body { color: var(--text-primary) !important; }

    /* STATS */
    .stat-num { color: var(--text-heading) !important; }
    .stat-label { color: var(--text-muted) !important; }

    /* PAGE TITLES */
    .page-title h2 { color: var(--text-heading) !important; }
    .page-title p { color: var(--text-subheading) !important; }

    /* TABLES */
    .data-table thead th { background: var(--bg-table-head) !important; color: var(--text-label) !important; border-bottom: 1px solid var(--border) !important; }
    .data-table tbody tr { background: var(--bg-table-row) !important; }
    .data-table tbody tr:nth-child(even) { background: var(--bg-table-alt) !important; }
    .data-table tbody tr:hover { background: var(--bg-table-hover) !important; }
    .data-table td { color: var(--text-table) !important; border-bottom: 1px solid var(--border) !important; }
    .empty-cell { color: var(--text-muted) !important; }

    /* BADGES */
    .id-badge { background: var(--id-badge-bg) !important; color: var(--id-badge-text) !important; }
    .session-badge { background: var(--session-badge-bg) !important; color: var(--session-badge-text) !important; }
    .session-badge.low { background: var(--session-low-bg) !important; color: var(--session-low-text) !important; }
    .status-badge.active { background: var(--status-active-bg) !important; color: var(--status-active-text) !important; }
    .status-badge.completed,
    .status-badge.done { background: var(--status-done-bg) !important; color: var(--status-done-text) !important; }

    /* ALERTS */
    .alert-success-sm { background: var(--alert-success-bg) !important; color: var(--alert-success-text) !important; }
    .alert-error-sm { background: var(--alert-error-bg) !important; color: var(--alert-error-text) !important; }

    /* INPUTS / SELECTS / TEXTAREAS */
    input, select, textarea {
      background: var(--bg-input) !important;
      color: var(--text-primary) !important;
      border-color: var(--border) !important;
    }
    input::placeholder, textarea::placeholder { color: var(--text-muted) !important; }

    /* TABLE TOOLBAR */
    .table-search { background: var(--bg-panel) !important; border-color: var(--border) !important; }
    .table-search svg { stroke: var(--text-muted) !important; }
    .table-search input { background: transparent !important; color: var(--text-primary) !important; }

    /* SEARCH */
    .search-hero-bar { background: var(--bg-panel) !important; border-color: var(--border) !important; box-shadow: var(--shadow-panel) !important; }
    .search-hero-bar svg { stroke: var(--text-muted) !important; }
    .hint-pill { background: var(--bg-table-head) !important; color: var(--text-muted) !important; }
    .result-name { color: var(--text-heading) !important; }
    .result-email { color: var(--text-muted) !important; }
    .sessions-lbl { color: var(--text-muted) !important; }
    .sessions-num { color: var(--accent) !important; }
    .meta-badge.id { background: var(--id-badge-bg) !important; color: var(--id-badge-text) !important; }
    .meta-badge.course, .meta-badge.year { background: var(--bg-table-head) !important; color: var(--text-secondary) !important; }

    /* ANNOUNCEMENTS */
    .ann-form textarea { background: var(--bg-input) !important; color: var(--text-primary) !important; border-color: var(--border) !important; }
    .ann-divider { color: var(--text-muted) !important; border-color: var(--border) !important; }
    .ann-item { border-color: var(--border) !important; }
    .ann-meta { color: var(--text-muted) !important; }
    .ann-item p { color: var(--text-secondary) !important; }
    .ann-delete-btn { background: var(--session-low-bg) !important; color: var(--session-low-text) !important; }

    /* MODAL */
    .modal-box { background: var(--bg-modal) !important; }
    .modal-header h3 { color: var(--text-heading) !important; }
    .modal-header { border-color: var(--border) !important; }
    .modal-field label { color: var(--text-label) !important; }
    .modal-field input,
    .modal-field select { background: var(--bg-input) !important; color: var(--text-primary) !important; border-color: var(--border) !important; }
    .error-modal-box { background: var(--bg-modal) !important; }
    .error-modal-box h2 { color: var(--text-heading) !important; }
    .error-modal-box .error-msg { color: var(--text-muted) !important; }

    /* EMPTY STATES */
    .empty-state, .search-empty-state { color: var(--text-muted) !important; }
    .empty-state p, .search-empty-state p { color: var(--text-muted) !important; }
    .empty-state svg, .search-empty-state svg { stroke: var(--text-muted) !important; }

    /* STATISTICS INLINE TEXT */
    [style*="color: #1e1b2e"] { color: var(--text-heading) !important; }

    /* FEEDBACK TAB */
    .fb-summary-card { background: var(--fb-summary-bg) !important; }
    .fb-summary-info .num { color: var(--fb-num-color) !important; }
    .fb-summary-info .lbl { color: var(--fb-lbl-color) !important; }
    .fb-rating-panel { background: var(--fb-rating-bg) !important; }
    .fb-rating-panel h3 { color: var(--fb-heading-color) !important; }
    .rating-bar-track { background: var(--fb-bar-track) !important; }
    .rating-bar-label, .rating-bar-count { color: var(--text-muted) !important; }
    .fb-cards-header h3 { color: var(--fb-heading-color) !important; }
    .fb-count-pill { background: var(--fb-count-pill-bg) !important; color: var(--fb-count-pill-text) !important; }
    .fb-card { background: var(--fb-card-bg) !important; }
    .fb-card-name { color: var(--text-heading) !important; }
    .fb-card-meta { color: var(--text-muted) !important; }
    .fb-pill { background: var(--fb-pill-bg) !important; color: var(--fb-pill-text) !important; }
    .fb-card-message { color: var(--fb-msg-color) !important; border-color: var(--fb-divider) !important; }
    .fb-empty h3 { color: var(--text-secondary) !important; }
    .fb-empty p { color: var(--text-muted) !important; }

    /* FOOTER */
    footer { background: var(--footer-bg) !important; color: var(--footer-text) !important; }

    /* ── THEME TOGGLE BUTTON ── */
    .theme-toggle-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 20px;
      padding: 5px 12px;
      cursor: pointer;
      font-size: 0.82rem;
      font-weight: 600;
      color: #fff !important;
      letter-spacing: 0.3px;
      transition: background 0.2s, transform 0.2s !important;
      white-space: nowrap;
    }
    .theme-toggle-btn:hover {
      background: rgba(255,255,255,0.22);
      transform: translateY(-1px);
    }
    .theme-toggle-btn .toggle-icon { font-size: 1rem; line-height: 1; }

    /* ── SOFTWARE MODAL ── */
    .sw-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.52);
      z-index: 9999;
      align-items: flex-start;
      justify-content: center;
      padding-top: 3vh;
      overflow-y: auto;
    }
    .sw-modal-overlay.show { display: flex; }
    .sw-modal-box {
      background: var(--bg-modal);
      border-radius: 16px;
      width: 95%;
      max-width: 760px;
      box-shadow: var(--shadow-modal);
      animation: swPop 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
      margin-bottom: 2rem;
    }
    @keyframes swPop {
      from { transform: scale(0.88) translateY(-20px); opacity: 0; }
      to   { transform: scale(1)    translateY(0);     opacity: 1; }
    }
    .sw-modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.1rem 1.5rem;
      border-bottom: 1px solid var(--border);
      background: var(--bg-header);
      border-radius: 16px 16px 0 0;
    }
    .sw-modal-header h3 {
      margin: 0;
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem;
      color: #fff;
    }
    .sw-modal-close {
      background: none;
      border: none;
      color: rgba(255,255,255,0.75);
      font-size: 1.3rem;
      cursor: pointer;
      line-height: 1;
      padding: 0;
    }
    .sw-modal-close:hover { color: #fff; }
    .sw-modal-body { padding: 1.4rem 1.5rem; }

    /* add-software form inside modal */
    .sw-add-form {
      background: var(--accent-light);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 1.1rem 1.2rem;
      margin-bottom: 1.3rem;
    }
    .sw-add-form h4 {
      margin: 0 0 0.9rem;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text-heading);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .sw-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 0.7rem;
    }
    .sw-form-grid .full { grid-column: 1/-1; }
    .sw-form-field label {
      display: block;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--text-label);
      margin-bottom: 0.25rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .sw-form-field input,
    .sw-form-field select,
    .sw-form-field textarea {
      width: 100%;
      box-sizing: border-box;
      padding: 0.5rem 0.75rem;
      border: 1.5px solid var(--border);
      border-radius: 7px;
      font-size: 0.85rem;
      font-family: 'DM Sans', sans-serif;
      background: var(--bg-input);
      color: var(--text-primary);
      outline: none;
    }
    .sw-form-field textarea { resize: vertical; min-height: 56px; }
    .sw-form-field input:focus,
    .sw-form-field select:focus,
    .sw-form-field textarea:focus { border-color: var(--accent-mid); }
    .btn-sw-add {
      margin-top: 0.8rem;
      padding: 0.55rem 1.4rem;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: background 0.2s, transform 0.1s;
    }
    .btn-sw-add:hover { background: var(--accent-mid); transform: translateY(-1px); }

    /* lab tabs inside modal */
    .sw-lab-tabs { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .sw-lab-tab-btn {
      padding: 0.35rem 1rem;
      border-radius: 20px;
      border: 1.5px solid var(--accent);
      background: transparent;
      color: var(--accent);
      font-size: 0.75rem;
      font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      transition: all 0.18s;
    }
    .sw-lab-tab-btn:hover,
    .sw-lab-tab-btn.active { background: var(--accent); color: #fff; }

    /* software list inside modal */
    .sw-list-panel { max-height: 320px; overflow-y: auto; }
    .sw-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.82rem;
    }
    .sw-table thead th {
      background: var(--bg-table-head);
      color: var(--text-label);
      padding: 0.6rem 0.8rem;
      text-align: left;
      font-weight: 700;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
    }
    .sw-table tbody td {
      padding: 0.6rem 0.8rem;
      border-bottom: 1px solid var(--border);
      color: var(--text-table);
    }
    .sw-table tbody tr:hover td { background: var(--bg-table-hover); }
    .sw-empty-row td {
      text-align: center;
      color: var(--text-muted);
      padding: 1.5rem;
      font-style: italic;
    }
    .btn-sw-delete {
      background: #fee2e2;
      color: #dc2626;
      border: 1px solid #fca5a5;
      padding: 3px 10px;
      border-radius: 6px;
      font-size: 0.72rem;
      font-weight: 600;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.18s;
    }
    .btn-sw-delete:hover { background: #fecaca; color: #991b1b; }

    /* manage software button in reservations tab */
    .res-title-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1.5rem;
    }
    .btn-manage-sw {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 0.55rem 1.2rem;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 9px;
      font-size: 0.84rem;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s, box-shadow 0.15s;
      white-space: nowrap;
      box-shadow: 0 3px 10px rgba(74,30,138,0.2);
    }
    .btn-manage-sw:hover {
      background: var(--accent-mid);
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(74,30,138,0.3);
    }
    .btn-manage-sw svg { flex-shrink: 0; }

    /* ── 3-COLUMN HOME GRID ── */
    .home-grid-3col {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 1.2rem;
      align-items: start;
    }

    /* ── LEADERBOARD PANEL (right column) ── */
    .lb-panel {
      background: var(--bg-panel);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: var(--shadow-panel);
      border: 1px solid var(--border-panel);
    }
    .lb-panel-header {
      background: var(--bg-header);
      color: #fff;
      padding: 0.9rem 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 700;
      font-size: 0.82rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    /* Thin column-header row inside leaderboard */
    .lb-col-headers-v {
      display: grid;
      grid-template-columns: 28px 36px 1fr 32px 42px 32px 52px;
      gap: 4px;
      padding: 0.45rem 0.9rem;
      background: var(--bg-table-head);
      border-bottom: 1px solid var(--border);
    }
    .lb-col-headers-v span {
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: var(--text-muted);
    }
    .lbh-stat, .lbh-score { text-align: right; }
    /* Each row */
    .lb-row-v {
      display: grid;
      grid-template-columns: 28px 36px 1fr 32px 42px 32px 52px;
      gap: 4px;
      align-items: center;
      padding: 0.6rem 0.9rem;
      border-bottom: 1px solid var(--border);
      background: var(--bg-table-row);
      transition: background 0.18s, transform 0.15s;
    }
    .lb-row-v:last-child { border-bottom: none; }
    .lb-row-v:hover { background: var(--bg-table-hover); transform: translateX(2px); }
    .lb-row-v.top-1 { border-left: 3px solid #FFD700; }
    .lb-row-v.top-2 { border-left: 3px solid #C0C0C0; }
    .lb-row-v.top-3 { border-left: 3px solid #CD7F32; }
    .lb-medal-v { font-size: 1.1rem; text-align: center; }
    /* Shared avatar style */
    .lb-avatar-wrap {
      width: 32px; height: 32px;
      border-radius: 50%;
      overflow: hidden;
      border: 2px solid var(--border);
      flex-shrink: 0;
      background: var(--accent-light);
      display: flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: 0.68rem;
      color: var(--accent);
    }
    .lb-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
    .lb-info-v { min-width: 0; }
    .lb-name-v {
      font-weight: 700; font-size: 0.82rem;
      color: var(--text-heading);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .lb-sub-v {
      font-size: 0.68rem; color: var(--text-muted);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .lb-num-v {
      text-align: right;
      font-weight: 600; font-size: 0.8rem;
      color: var(--text-secondary);
    }
    .lb-num-v.accent { color: var(--accent); }
    .lb-score-v { text-align: right; }
    .lb-score-v span {
      display: inline-block;
      background: var(--accent);
      color: #fff;
      font-weight: 800; font-size: 0.75rem;
      padding: 3px 9px;
      border-radius: 20px;
    }
    .lb-empty {
      text-align: center; padding: 2rem;
      color: var(--text-muted); font-size: 0.88rem;
    }
  </style>
</head>
<body>

<!-- ════ ERROR MODAL ════ -->
<div id="errorModal" class="error-modal-overlay">
  <div class="error-modal-box">
    <div class="error-modal-icon">
      <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 8v4M12 16h.01"/>
      </svg>
    </div>
    <h2>Oops!</h2>
    <p class="error-msg">Please enter an announcement before posting. The textarea cannot be empty.</p>
    <div class="error-modal-actions">
      <button class="error-modal-ok" onclick="closeErrorModal()">Okay</button>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<nav>
  <a href="admin_dashboard.php" class="nav-brand">
    <img src="UClogo.png" alt="UC Logo">
    <span class="nav-title">CCS Admin Panel<br>SIT-IN Monitoring System</span>
  </a>
  <ul class="nav-links">
    <li><a href="admin_dashboard.php?tab=home"     class="<?= $tab==='home'     ? 'active':'' ?>">Home</a></li>
    <li><a href="admin_dashboard.php?tab=search"   class="<?= $tab==='search'   ? 'active':'' ?>">Search</a></li>
    <li><a href="admin_dashboard.php?tab=students" class="<?= $tab==='students' ? 'active':'' ?>">Students</a></li>
    <li><a href="admin_dashboard.php?tab=reservations" class="<?= $tab==='reservations' ? 'active':'' ?>">Reservations <?php if(count($pending_reservations) > 0): ?><span style="background:#ef4444;color:#fff;border-radius:50%;width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;margin-left:4px;"><?= count($pending_reservations) ?></span><?php endif; ?></a></li>
    <li><a href="admin_dashboard.php?tab=sitin"    class="<?= $tab==='sitin'    ? 'active':'' ?>">Sit-in</a></li>
    <li><a href="admin_dashboard.php?tab=records"  class="<?= $tab==='records'  ? 'active':'' ?>">View Sit-in Records</a></li>
    <li><a href="sitin_reports.php">Sit-in Reports</a></li>
    <li><a href="admin_dashboard.php?tab=feedback" class="<?= $tab==='feedback' ? 'active':'' ?>">Feedback Reports</a></li>
    <li><button class="theme-toggle-btn" id="themeToggle" title="Toggle Dark/Light Mode"><span class="toggle-icon" id="toggleIcon">🌙</span><span id="toggleLabel">Dark</span></button></li>
    <li><a href="admin_logout.php" class="btn-nav">Log out</a></li>
  </ul>
</nav>

<div class="admin-layout">

<!-- ════════════════════════════════
     HOME TAB
════════════════════════════════ -->
<?php if ($tab === 'home'): ?>
<div class="tab-content">

  <!-- Stats Row -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon purple">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-num"><?= $total_students ?></div>
        <div class="stat-label">Students Registered</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon gold">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-num"><?= $currently_sitin ?></div>
        <div class="stat-label">Currently Sit-in</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-num"><?= $total_sitin ?></div>
        <div class="stat-label">Total Sit-in Sessions</div>
      </div>
    </div>
  </div>
  <!-- 3-column: Chart (Left) | Announcements (Center) | Leaderboard (Right) -->
  <div class="home-grid-3col">

    <!-- ── LEFT: Statistics Chart ── -->
    <div class="panel">
      <div class="panel-header">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Statistics
      </div>
      <div class="panel-body">
        <div style="margin-bottom:1.2rem;">
          <p style="font-weight:600;color:var(--text-heading);margin:0 0 0.3rem;font-size:0.93rem;">Students Registered: <strong><?= $total_students ?></strong></p>
          <p style="font-weight:600;color:var(--text-heading);margin:0 0 0.3rem;font-size:0.93rem;">Currently Sit-in: <strong><?= $currently_sitin ?></strong></p>
          <p style="font-weight:600;color:var(--text-heading);margin:0;font-size:0.93rem;">Total Sit-in: <strong><?= $total_sitin ?></strong></p>
        </div>
        <div class="chart-container">
          <canvas id="sitin-chart"></canvas>
        </div>
      </div>
    </div>

    <!-- ── CENTER: Announcements ── -->
    <div class="panel">
      <div class="panel-header">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
        Announcements
      </div>
      <div class="panel-body">
        <?php if (isset($_GET['ann'])): ?>
        <div class="alert-success-sm">✓ Announcement posted successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['ann_deleted'])): ?>
        <div class="alert-success-sm">✓ Announcement deleted successfully.</div>
        <?php endif; ?>
        <form method="POST" action="admin_dashboard.php?tab=home" class="ann-form" onsubmit="return validateAnnouncement(event)">
          <textarea name="announcement" id="ann-textarea" placeholder="Write a new announcement..." rows="3"></textarea>
          <button type="submit" class="btn-gold">Post Announcement</button>
        </form>
        <div class="ann-divider">Posted Announcements</div>
        <div class="ann-list">
          <?php foreach ($announcements as $ann): ?>
          <div class="ann-item">
            <div class="ann-meta">CCS Admin | <?= date('Y-M-d', strtotime($ann['created_at'])) ?></div>
            <p><?= htmlspecialchars($ann['content']) ?></p>
            <a href="admin_dashboard.php?tab=home&delete_ann_id=<?= $ann['id'] ?>" class="ann-delete-btn" onclick="return confirm('Delete this announcement?')">Delete</a>
          </div>
          <?php endforeach; ?>
          <?php if (empty($announcements)): ?>
          <div class="empty-state">No announcements yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── RIGHT: Leaderboard ── -->
    <div class="lb-panel">
      <div class="lb-panel-header">
        🏆 &nbsp;Leaderboards
      </div>

      <?php if (empty($leaderboard_query)): ?>
        <div class="lb-empty">No completed sit-in records yet.</div>
      <?php else: ?>
        <?php
          $medals   = ['🥇','🥈','🥉'];
          $topClass = ['top-1','top-2','top-3'];
        ?>

        <!-- Column headers -->
        <div class="lb-col-headers-v">
  <span></span>
  <span></span>
  <span>Student</span>
  <span class="lbh-stat">Pts</span>
  <span class="lbh-stat">Hrs</span>
  <span class="lbh-stat">Ses</span>
  <span class="lbh-score">Score</span>
</div>

        <?php foreach ($leaderboard_query as $i => $row):
  $medal      = $medals[$i] ?? ($i + 1);
  $tClass     = $topClass[$i] ?? '';

  $profilePic = (!empty($row['profile_picture']) && file_exists('uploads/' . $row['profile_picture']))
      ? 'uploads/' . $row['profile_picture']
      : 'Studentlogo.png';

  $courseYear = htmlspecialchars(
      trim(($row['course'] ?? '') . ' ' . ($row['course_level'] ?? ''))
  );
?>

<div class="lb-row-v <?= $tClass ?>">

  <!-- Medal -->
  <div class="lb-medal-v"><?= $medal ?></div>

  <!-- Avatar -->
  <div class="lb-avatar-wrap">
    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Student">
  </div>

  <!-- Student Info -->
  <div class="lb-info-v">
    <div class="lb-name-v">
      <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
    </div>

    <div class="lb-sub-v">
      <?= htmlspecialchars($row['id_number']) ?>
      <?= $courseYear ? ' · ' . $courseYear : '' ?>
    </div>
  </div>

  <!-- Points -->
  <div class="lb-num-v">
    <?= (int)$row['total_points'] ?>
  </div>

  <!-- Hours -->
  <div class="lb-num-v accent">
    <?= $row['total_hours'] ?>h
  </div>

  <!-- Sessions -->
  <div class="lb-num-v">
    <?= (int)$row['total_sessions'] ?>
  </div>

  <!-- Score -->
  <div class="lb-score-v">
    <span><?= $row['score'] ?></span>
  </div>

</div>
<?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div><!-- /.home-grid-3col -->
</div>

<!-- ════════════════════════════════
     SEARCH TAB
════════════════════════════════ -->
<?php elseif ($tab === 'search'): ?>
<div class="tab-content">
  <div class="page-title">
    <h2>Search Students</h2>
    <p>Find registered students by name, ID number, email, or course</p>
  </div>

  <?php if (isset($_GET['error']) && $_GET['error'] === 'no_sessions'): ?>
  <div class="alert-error-sm">✕ This student has no remaining sessions and cannot be sat in.</div>
  <?php endif; ?>

  <!-- PROMINENT SEARCH BAR -->
  <div class="search-hero">
    <form action="admin_dashboard.php" method="GET" class="search-hero-form">
      <input type="hidden" name="tab" value="search">
      <div class="search-hero-bar">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="q" id="search-input" placeholder="Search by name, ID number, email, or course..." value="<?= htmlspecialchars($search_query) ?>" autofocus>
        <button type="submit" class="search-btn">Search</button>
        <?php if ($search_query): ?>
        <a href="admin_dashboard.php?tab=search" class="search-clear">✕ Clear</a>
        <?php endif; ?>
      </div>
    </form>
    <div class="search-hints">
      <span class="hint-pill">By ID Number</span>
      <span class="hint-pill">By Full Name</span>
      <span class="hint-pill">By Last Name</span>
      <span class="hint-pill">By First Name</span>
      <span class="hint-pill">By Email</span>
      <span class="hint-pill">By Course</span>
    </div>
  </div>

  <!-- RESULTS -->
  <?php if ($search_query !== ''): ?>
  <div class="panel">
    <div class="panel-header">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Search Results for "<?= htmlspecialchars($search_query) ?>" —
      <strong><?= count($search_results) ?> student<?= count($search_results) !== 1 ? 's' : '' ?> found</strong>
    </div>
    <div class="panel-body">
      <?php if (empty($search_results)): ?>
      <div class="empty-state large">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <p>No students found matching "<strong><?= htmlspecialchars($search_query) ?></strong>"</p>
        <span>Try a different name, ID, or course</span>
      </div>
      <?php else: ?>
      <div class="result-cards">
        <?php foreach ($search_results as $s): ?>
        <div class="result-card" onclick="openSitInModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['id_number']) ?>', '<?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?>', <?= $s['remaining_sessions'] ?? 30 ?>)">
          <div class="result-avatar">
            <?php
              $pic = (!empty($s['profile_picture']) && file_exists('uploads/'.$s['profile_picture']))
                     ? 'uploads/'.$s['profile_picture'] : 'Studentlogo.png';
            ?>
            <img src="<?= htmlspecialchars($pic) ?>" alt="Avatar">
          </div>
          <div class="result-info">
            <div class="result-name"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
            <div class="result-meta">
              <span class="meta-badge id"><?= htmlspecialchars($s['id_number']) ?></span>
              <span class="meta-badge course"><?= htmlspecialchars($s['course']) ?></span>
              <span class="meta-badge year">Year <?= htmlspecialchars($s['course_level']) ?></span>
            </div>
            <div class="result-email"><?= htmlspecialchars($s['email']) ?></div>
          </div>
          <div class="result-sessions">
            <div class="sessions-num"><?= $s['remaining_sessions'] ?? 30 ?></div>
            <div class="sessions-lbl">sessions left</div>
          </div>
          <div class="result-actions">
            <button class="btn-sitin" onclick="event.stopPropagation(); openSitInModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['id_number']) ?>', '<?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?>', <?= $s['remaining_sessions'] ?? 30 ?>)">Sit In</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="search-empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <h3>Search for a Student</h3>
    <p>Type a student's name, ID number, email address, or course in the search bar above to get started.</p>
  </div>
  <?php endif; ?>
</div>

<!-- ════════════════════════════════
     STUDENTS TAB
════════════════════════════════ -->
<?php elseif ($tab === 'students'): ?>
<div class="tab-content">
  <div class="page-title-row">
    <div class="page-title">
      <h2>Students Information</h2>
      <p>All registered students in the SIT-IN system</p>
    </div>
    <div class="title-actions">
      <a href="admin_dashboard.php?tab=students&reset_sessions=1" class="btn-outline" onclick="return confirm('Reset ALL student sessions to 30?')">Reset All Sessions</a>
    </div>
  </div>
  <?php if (isset($_GET['reset'])): ?>
  <div class="alert-success-sm">✓ All sessions have been reset to 30.</div>
  <?php endif; ?>

  <!-- Inline search -->
  <div class="table-toolbar">
    <div class="table-search">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <input type="text" id="table-filter" placeholder="Filter students..." oninput="filterTable()">
    </div>
  </div>

  <div class="panel">
    <div class="table-wrap">
      <table class="data-table" id="students-table">
        <thead>
          <tr>
            <th>ID Number</th>
            <th>Name</th>
            <th>Course</th>
            <th>Year</th>
            <th>Email</th>
            <th>Sessions Left</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $s): ?>
          <tr>
            <td><span class="id-badge"><?= htmlspecialchars($s['id_number']) ?></span></td>
            <td class="name-cell">
              <?php
                $pic = (!empty($s['profile_picture']) && file_exists('uploads/'.$s['profile_picture']))
                       ? 'uploads/'.$s['profile_picture'] : 'Studentlogo.png';
              ?>
              <img src="<?= htmlspecialchars($pic) ?>" class="table-avatar" alt="">
              <?= htmlspecialchars($s['last_name'].', '.$s['first_name']) ?>
            </td>
            <td><?= htmlspecialchars($s['course']) ?></td>
            <td><?= htmlspecialchars($s['course_level']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td>
              <span class="session-badge <?= ($s['remaining_sessions'] ?? 30) <= 5 ? 'low' : '' ?>">
                <?= $s['remaining_sessions'] ?? 30 ?>
              </span>
            </td>
            <td class="actions-cell">
              <button class="btn-sm btn-sitin-sm" onclick="openSitInModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['id_number']) ?>', '<?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?>', <?= $s['remaining_sessions'] ?? 30 ?>)">Sit In</button>
              <a href="admin_edit_student.php?id=<?= $s['id'] ?>" class="btn-sm btn-edit">Edit</a>
              <a href="admin_dashboard.php?tab=students&delete_id=<?= $s['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this student?')">Delete</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ════════════════════════════════
     SIT-IN TAB
════════════════════════════════ -->
<?php elseif ($tab === 'sitin'): ?>
<div class="tab-content">
  <div class="page-title">
    <h2>Current Sit-in</h2>
    <p>Students currently checked in to the laboratory</p>
  </div>
  <?php if (isset($_GET['timed_out'])): ?>
  <div class="alert-success-sm">✓ Student has been timed out successfully.</div>
  <?php endif; ?>
  <div class="panel">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Sit ID</th>
            <th>ID Number</th>
            <th>Name</th>
            <th>Purpose</th>
            <th>Lab</th>
            <th>Time In</th>
            <th>Session Used</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $active = array_filter($sit_records, fn($r) => $r['status'] === 'active');
            foreach ($active as $r):
          ?>
          <tr>
            <td><span class="id-badge"><?= $r['id'] ?></span></td>
            <td><?= htmlspecialchars($r['id_number']) ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><?= htmlspecialchars($r['lab_number']) ?></td>
            <td><?= date('h:i A', strtotime($r['login_time'])) ?></td>
            <td><?= htmlspecialchars($r['session_used'] ?? 1) ?></td>
            <td><span class="status-badge active">Active</span></td>
            <td>
              <button class="btn-sm btn-timeout" onclick="openRewardModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?>')">Time Out</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($active)): ?>
          <tr><td colspan="9" class="empty-cell">No students currently sitting in.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ════════════════════════════════
     RECORDS TAB
════════════════════════════════ -->
<?php elseif ($tab === 'records'): ?>
<div class="tab-content">
  <div class="page-title">
    <h2>Sit-in Records</h2>
    <p>Complete history of all sit-in sessions</p>
  </div>
  <?php if (isset($_GET['sitin'])): ?>
  <div class="alert-success-sm">✓ Student has been successfully sat in. Session recorded.</div>
  <?php endif; ?>
  <div class="panel">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Sit ID</th>
            <th>ID Number</th>
            <th>Name</th>
            <th>Course</th>
            <th>Purpose</th>
            <th>Lab</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sit_records as $r): ?>
          <tr>
            <td><span class="id-badge"><?= $r['id'] ?></span></td>
            <td><?= htmlspecialchars($r['id_number']) ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= htmlspecialchars($r['course']) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><?= htmlspecialchars($r['lab_number']) ?></td>
            <td><?= date('M d Y h:i A', strtotime($r['login_time'])) ?></td>
            <td><?= $r['logout_time'] ? date('M d Y h:i A', strtotime($r['logout_time'])) : '—' ?></td>
            <td><span class="status-badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($sit_records)): ?>
          <tr><td colspan="9" class="empty-cell">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ════════════════════════════════
     RESERVATIONS TAB
════════════════════════════════ -->
<?php elseif ($tab === 'reservations'): ?>
<div class="tab-content">
  <div class="res-title-row">
    <div class="page-title" style="margin-bottom:0;">
      <h2>Reservation Requests</h2>
      <p>Students requesting lab computer reservations pending your approval</p>
    </div>
    <button class="btn-manage-sw" onclick="openSwModal()">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
      Manage Lab Software
    </button>
  </div>

  <?php if (isset($_GET['approved'])): ?>
  <div class="alert-success-sm">✓ Reservation approved successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['rejected'])): ?>
  <div class="alert-success-sm">✓ Reservation rejected successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['sw_added'])): ?>
  <div class="alert-success-sm">✓ Software added successfully to the lab.</div>
  <?php endif; ?>

  <?php if (isset($_GET['sw_deleted'])): ?>
  <div class="alert-success-sm">✓ Software removed successfully.</div>
  <?php endif; ?>

  <div class="panel">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID Number</th>
            <th>Student Name</th>
            <th>Email</th>
            <th>Purpose</th>
            <th>Lab</th>
            <th>Requested Date</th>
            <th>Time</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pending_reservations)): ?>
          <tr><td colspan="8" class="empty-cell">No pending reservation requests.</td></tr>
          <?php else: ?>
            <?php foreach ($pending_reservations as $r): ?>
            <tr>
              <td><span class="id-badge"><?= htmlspecialchars($r['id_number']) ?></span></td>
              <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
              <td><?= htmlspecialchars($r['email']) ?></td>
              <td><?= htmlspecialchars($r['purpose']) ?></td>
              <td>Lab <?= htmlspecialchars($r['lab_number']) ?></td>
              <td><?= date('M d, Y', strtotime($r['login_time'])) ?></td>
              <td><?= date('h:i A', strtotime($r['login_time'])) ?></td>
              <td>
                <a href="admin_dashboard.php?tab=reservations&approve_reservation=<?= $r['id'] ?>" class="btn-sm btn-approve" onclick="return confirm('Approve this reservation?')">Approve</a>
                <a href="admin_dashboard.php?tab=reservations&reject_reservation=<?= $r['id'] ?>" class="btn-sm btn-reject" onclick="return confirm('Reject this reservation?')">Reject</a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ════════════════════════════════
     FEEDBACK REPORTS TAB
════════════════════════════════ -->
<?php elseif ($tab === 'feedback'): ?>
<div class="tab-content">
  <div class="page-title">
    <h2>Feedback Reports</h2>
    <p>Student experience ratings and comments from completed sit-in sessions</p>
  </div>

  <style>
    .fb-summary-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin-bottom: 1.8rem;
    }
    .fb-summary-card {
      background: #fff;
      border-radius: 14px;
      padding: 1.4rem 1.5rem;
      box-shadow: 0 2px 12px rgba(74,30,138,0.08);
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .fb-summary-icon {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 1.5rem;
    }
    .fb-summary-icon.gold   { background: #fef9ec; }
    .fb-summary-icon.purple { background: #f3f0ff; }
    .fb-summary-icon.teal   { background: #ecfdf5; }
    .fb-summary-info .num { font-size: 1.7rem; font-weight: 700; color: #1e1b2e; line-height: 1; }
    .fb-summary-info .lbl { font-size: 0.78rem; color: #8b84a0; margin-top: 3px; }
    .fb-rating-panel {
      background: #fff;
      border-radius: 14px;
      padding: 1.5rem;
      box-shadow: 0 2px 12px rgba(74,30,138,0.08);
      margin-bottom: 1.8rem;
    }
    .fb-rating-panel h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      color: #1e1b2e;
      margin: 0 0 1.2rem;
    }
    .rating-bar-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }
    .rating-bar-label { font-size: 0.82rem; color: #8b84a0; width: 40px; text-align: right; flex-shrink: 0; }
    .rating-bar-track { flex: 1; height: 10px; background: #f0eef8; border-radius: 999px; overflow: hidden; }
    .rating-bar-fill  { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #4a1e8a, #8b5cf6); }
    .rating-bar-count { font-size: 0.78rem; color: #8b84a0; width: 28px; flex-shrink: 0; }
    .fb-cards-header  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    .fb-cards-header h3 { font-family: 'Playfair Display', serif; font-size: 1rem; color: #1e1b2e; margin: 0; }
    .fb-count-pill { background: #f3f0ff; color: #4a1e8a; border-radius: 999px; padding: 3px 12px; font-size: 0.78rem; font-weight: 700; }
    .fb-cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }
    .fb-card {
      background: #fff;
      border-radius: 14px;
      padding: 1.3rem 1.4rem;
      box-shadow: 0 2px 12px rgba(74,30,138,0.07);
      border-left: 4px solid #4a1e8a;
      transition: transform 0.15s, box-shadow 0.15s;
    }
    .fb-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(74,30,138,0.13); }
    .fb-card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.7rem; }
    .fb-card-name { font-weight: 700; font-size: 0.9rem; color: #1e1b2e; }
    .fb-card-meta { font-size: 0.75rem; color: #8b84a0; margin-top: 2px; }
    .fb-stars { color: #f5c842; font-size: 1rem; letter-spacing: 1px; flex-shrink: 0; }
    .fb-card-session { display: flex; gap: 6px; margin-bottom: 0.75rem; flex-wrap: wrap; }
    .fb-pill { background: #f3f0ff; color: #4a1e8a; border-radius: 999px; padding: 2px 10px; font-size: 0.72rem; font-weight: 600; }
    .fb-card-message { font-size: 0.85rem; color: #4a4560; line-height: 1.6; border-top: 1px solid #f0eef8; padding-top: 0.75rem; font-style: italic; }
    .fb-empty { text-align: center; padding: 4rem 2rem; color: #8b84a0; }
    .fb-empty svg { margin: 0 auto 1rem; display: block; opacity: 0.3; }
    .fb-empty h3 { font-size: 1.1rem; color: #4a4560; margin: 0 0 0.4rem; }
    .fb-empty p  { font-size: 0.85rem; margin: 0; }
  </style>

  <?php if ($total_feedback === 0): ?>
  <div class="fb-empty">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="64" height="64">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
    </svg>
    <h3>No feedback yet</h3>
    <p>Student feedback will appear here once they submit their session reviews.</p>
  </div>

  <?php else: ?>

  <div class="fb-summary-grid">
    <div class="fb-summary-card">
      <div class="fb-summary-icon gold">⭐</div>
      <div class="fb-summary-info">
        <div class="num"><?= $avg_rating ?><span style="font-size:1rem;color:#8b84a0;font-weight:400;"> / 5</span></div>
        <div class="lbl">Average Rating</div>
      </div>
    </div>
    <div class="fb-summary-card">
      <div class="fb-summary-icon purple">💬</div>
      <div class="fb-summary-info">
        <div class="num"><?= $total_feedback ?></div>
        <div class="lbl">Total Responses</div>
      </div>
    </div>
    <div class="fb-summary-card">
      <div class="fb-summary-icon teal">🏆</div>
      <div class="fb-summary-info">
        <div class="num"><?= $rating_counts[5] + $rating_counts[4] ?></div>
        <div class="lbl">Positive Reviews (4–5 ★)</div>
      </div>
    </div>
  </div>

  <div class="fb-rating-panel">
    <h3>Rating Distribution</h3>
    <?php for ($i = 5; $i >= 1; $i--): ?>
    <?php $pct = $total_feedback ? round($rating_counts[$i] / $total_feedback * 100) : 0; ?>
    <div class="rating-bar-row">
      <div class="rating-bar-label"><?= $i ?> ★</div>
      <div class="rating-bar-track">
        <div class="rating-bar-fill" style="width:<?= $pct ?>%"></div>
      </div>
      <div class="rating-bar-count"><?= $rating_counts[$i] ?></div>
    </div>
    <?php endfor; ?>
  </div>

  <div class="fb-cards-header">
    <h3>All Feedback</h3>
    <span class="fb-count-pill"><?= $total_feedback ?> review<?= $total_feedback !== 1 ? 's' : '' ?></span>
  </div>
  <div class="fb-cards-grid">
    <?php foreach ($feedbacks as $fb): ?>
    <div class="fb-card">
      <div class="fb-card-top">
        <div>
          <div class="fb-card-name"><?= htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']) ?></div>
          <div class="fb-card-meta"><?= htmlspecialchars($fb['id_number']) ?> · <?= htmlspecialchars($fb['course']) ?></div>
          <div class="fb-card-meta"><?= date('M d, Y h:i A', strtotime($fb['created_at'])) ?></div>
        </div>
        <div class="fb-stars">
          <?= str_repeat('★', (int)$fb['rating']) ?><?= str_repeat('☆', 5 - (int)$fb['rating']) ?>
        </div>
      </div>
      <div class="fb-card-session">
        <span class="fb-pill">📚 <?= htmlspecialchars($fb['purpose']) ?></span>
        <span class="fb-pill">🏛 Lab <?= htmlspecialchars($fb['lab_number']) ?></span>
      </div>
      <div class="fb-card-message">"<?= htmlspecialchars($fb['message']) ?>"</div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- end admin-layout -->

<!-- ════ SIT-IN MODAL ════ -->
<div id="sitin-modal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Sit In Form</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <form method="POST" action="admin_process_sitin.php">
      <input type="hidden" name="user_id" id="modal-user-id">
      <div class="modal-field">
        <label>ID Number</label>
        <input type="text" id="modal-id" readonly>
      </div>
      <div class="modal-field">
        <label>Student Name</label>
        <input type="text" id="modal-name" readonly>
      </div>
      <div class="modal-field">
        <label>Purpose</label>
        <select name="purpose" required>
          <option value="">Select purpose</option>
          <option value="C Programming">C Programming</option>
          <option value="Java">Java</option>
          <option value="ASP.NET">ASP.NET</option>
          <option value="PHP">PHP</option>
          <option value="Research">Research</option>
          <option value="Database">Database</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="modal-field">
        <label>Lab</label>
        <select name="lab" required>
          <option value="">Select lab</option>
          <option value="524">Lab 524</option>
          <option value="526">Lab 526</option>
          <option value="528">Lab 528</option>
          <option value="530">Lab 530</option>
          <option value="542">Lab 542</option>
        </select>
      </div>
      <div class="modal-field">
        <label>Remaining Sessions</label>
        <input type="text" id="modal-sessions" readonly>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-modal-close" onclick="closeModal()">Close</button>
        <button type="submit" class="btn-modal-sitin">Sit In</button>
      </div>
    </form>
  </div>
</div>

<!-- ════ SOFTWARE MANAGEMENT MODAL ════ -->
<div id="swModal" class="sw-modal-overlay">
  <div class="sw-modal-box">
    <div class="sw-modal-header">
      <h3>🛠️ Manage Lab Software</h3>
      <button class="sw-modal-close" onclick="closeSwModal()">✕</button>
    </div>
    <div class="sw-modal-body">

      <!-- ADD FORM -->
      <div class="sw-add-form">
        <h4>➕ Add New Software</h4>
        <form method="POST" action="admin_dashboard.php?tab=reservations">
          <input type="hidden" name="add_software" value="1">
          <div class="sw-form-grid">
            <div class="sw-form-field">
              <label>Lab *</label>
              <select name="sw_lab" required>
                <option value="">Select lab</option>
                <?php foreach ($all_labs_for_sw as $l): ?>
                <option value="<?= $l ?>">Lab <?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sw-form-field">
              <label>Software Name *</label>
              <input type="text" name="sw_name" placeholder="e.g. VS Code" required>
            </div>
            <div class="sw-form-field">
              <label>Version</label>
              <input type="text" name="sw_version" placeholder="e.g. 1.85.1">
            </div>
            <div class="sw-form-field">
              <label>Category</label>
              <input type="text" name="sw_category" placeholder="e.g. Code Editor">
            </div>
            <div class="sw-form-field full">
              <label>Description</label>
              <textarea name="sw_description" placeholder="Short description of the software..."></textarea>
            </div>
          </div>
          <button type="submit" class="btn-sw-add">Add Software</button>
        </form>
      </div>

      <!-- CURRENT SOFTWARE PER LAB -->
      <div>
        <div class="sw-lab-tabs" id="swLabTabs">
          <?php foreach ($all_labs_for_sw as $idx => $l): ?>
          <button class="sw-lab-tab-btn <?= $idx===0?'active':'' ?>" onclick="showSwLab('<?= $l ?>', this)">Lab <?= $l ?></button>
          <?php endforeach; ?>
        </div>
        <div class="sw-list-panel">
          <?php foreach ($all_labs_for_sw as $idx => $l): ?>
          <div id="swList_<?= $l ?>" style="<?= $idx===0?'':'display:none' ?>">
            <table class="sw-table">
              <thead>
                <tr>
                  <th>Software</th>
                  <th>Version</th>
                  <th>Category</th>
                  <th>Description</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($software_by_lab[$l])): ?>
                <tr class="sw-empty-row"><td colspan="5">No software listed for Lab <?= $l ?>.</td></tr>
                <?php else: ?>
                  <?php foreach ($software_by_lab[$l] as $sw): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($sw['software_name']) ?></strong></td>
                    <td><?= htmlspecialchars($sw['version'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($sw['category'] ?? '—') ?></td>
                    <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($sw['description'] ?? '—') ?></td>
                    <td>
                      <a href="admin_dashboard.php?tab=reservations&delete_sw=<?= $sw['id'] ?>"
                         class="btn-sw-delete"
                         onclick="return confirm('Remove <?= htmlspecialchars(addslashes($sw['software_name'])) ?> from Lab <?= $l ?>?')">
                        Delete
                      </a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /.sw-modal-body -->
  </div>
</div>

<footer>
  &copy; <?= date('Y') ?> University of Cebu — SIT-IN Monitoring System | Admin Panel
</footer>

<script>
// Table filter
function filterTable() {
  const q = document.getElementById('table-filter').value.toLowerCase();
  const rows = document.querySelectorAll('#students-table tbody tr');
  rows.forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// Sit-in Modal
// Build set of user IDs currently sitting in (active status)
<?php
  $activeUserIds = $pdo->query("
    SELECT u.id FROM users u
    JOIN sit_in_records s ON u.id_number = s.id_number
    WHERE s.status = 'active'
  ")->fetchAll(PDO::FETCH_COLUMN);
?>
const activeSitInUserIds = new Set(<?= json_encode(array_map('intval', $activeUserIds)) ?>);

function openSitInModal(userId, idNum, name, sessions) {
  if (activeSitInUserIds.has(userId)) {
    document.getElementById('alreadySitinName').textContent = name;
    document.getElementById('alreadySitinModal').classList.add('show');
    return;
  }
  document.getElementById('modal-user-id').value = userId;
  document.getElementById('modal-id').value = idNum;
  document.getElementById('modal-name').value = name;
  document.getElementById('modal-sessions').value = sessions;
  document.getElementById('sitin-modal').style.display = 'flex';
}

function closeAlreadySitinModal() {
  document.getElementById('alreadySitinModal').classList.remove('show');
}

function closeModal() {
  document.getElementById('sitin-modal').style.display = 'none';
}

document.getElementById('sitin-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── ERROR MODAL FUNCTIONS ──
function validateAnnouncement(e) {
  const textarea = document.getElementById('ann-textarea');
  const text = textarea.value.trim();
  
  if (text === '') {
    e.preventDefault();
    openErrorModal();
    return false;
  }
  return true;
}

function openErrorModal() {
  document.getElementById('errorModal').classList.add('show');
}

function closeErrorModal() {
  document.getElementById('errorModal').classList.remove('show');
  document.getElementById('ann-textarea').focus();
}

// ── PIE CHART ──
window.addEventListener('load', function() {
  const ctx = document.getElementById('sitin-chart');
  if (ctx) {
    const chartLabels = <?= json_encode($chart_labels) ?>;
    const chartData = <?= json_encode($chart_data) ?>;
    
    const colors = [
      '#3b82f6',
      '#f87171',
      '#fb923c',
      '#facc15',
      '#34d399',
      '#06b6d4'
    ];
    
    const bgColors = chartLabels.map((_, i) => colors[i % colors.length]);
    
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: chartLabels,
        datasets: [{
          data: chartData,
          backgroundColor: bgColors,
          borderColor: '#fff',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: {
              font: { size: 11, weight: '500' },
              color: '#4a4560',
              padding: 12,
              usePointStyle: true,
              boxPadding: 6
            }
          },
          tooltip: {
            backgroundColor: 'rgba(30, 27, 46, 0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: '#4a1e8a',
            borderWidth: 1,
            padding: 10,
            titleFont: { size: 13, weight: 'bold' },
            bodyFont: { size: 12 }
          }
        }
      }
    });
  }
});

// ── SOFTWARE MODAL ──
function openSwModal() {
  document.getElementById('swModal').classList.add('show');
}
function closeSwModal() {
  document.getElementById('swModal').classList.remove('show');
}
function showSwLab(lab, btn) {
  document.querySelectorAll('[id^="swList_"]').forEach(el => el.style.display = 'none');
  document.getElementById('swList_' + lab).style.display = '';
  document.querySelectorAll('.sw-lab-tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
// Close sw modal on overlay click
document.getElementById('swModal').addEventListener('click', function(e) {
  if (e.target === this) closeSwModal();
});

// ── THEME TOGGLE ──
(function() {
  const html  = document.documentElement;
  const btn   = document.getElementById('themeToggle');
  const icon  = document.getElementById('toggleIcon');
  const label = document.getElementById('toggleLabel');
  const THEME_KEY = 'uc_ccs_theme';

  function applyTheme(theme) {
    html.setAttribute('data-theme', theme);
    if (theme === 'dark') {
      icon.textContent  = '☀️';
      label.textContent = 'Light';
      btn.title = 'Switch to Light Mode';
    } else {
      icon.textContent  = '🌙';
      label.textContent = 'Dark';
      btn.title = 'Switch to Dark Mode';
    }
  }

  const saved = localStorage.getItem(THEME_KEY) || 'light';
  applyTheme(saved);

  btn.addEventListener('click', function() {
    const current  = html.getAttribute('data-theme');
    const newTheme = current === 'light' ? 'dark' : 'light';
    localStorage.setItem(THEME_KEY, newTheme);
    applyTheme(newTheme);
  });

  // Sync across tabs/windows
  window.addEventListener('storage', function(e) {
    if (e.key === THEME_KEY && e.newValue) applyTheme(e.newValue);
  });
})();
</script>

<!-- ════ REWARD POINTS MODAL ════ -->
<div id="rewardModal" class="reward-modal-overlay" onclick="if(event.target===this)closeRewardModal()">
  <div class="reward-modal-box">
    <div class="reward-modal-header">
      <div class="reward-star-icon">⭐</div>
      <h2>Reward Points</h2>
      <p>Give 1 reward point for good performance?</p>
    </div>
    <form method="POST" action="admin_dashboard.php?tab=sitin" id="rewardForm">
      <input type="hidden" name="timeout_id" id="rewardTimeoutId">
      <input type="hidden" name="reward_points" id="rewardPointsValue" value="0">
      <div class="reward-options">
        <div class="reward-option" id="optionOne" onclick="selectReward(1)">
          <span class="reward-option-icon">⭐</span>
          <span class="reward-option-num">1</span>
          <span class="reward-option-label">Point</span>
        </div>
        <div class="reward-option selected" id="optionZero" onclick="selectReward(0)">
          <span class="reward-option-icon">✱</span>
          <span class="reward-option-num">0</span>
          <span class="reward-option-label">No points</span>
        </div>
      </div>
      <div class="reward-modal-actions">
        <button type="button" class="reward-btn-cancel" onclick="closeRewardModal()">Cancel</button>
        <button type="submit" class="reward-btn-confirm">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Confirm &amp; Time Out
        </button>
      </div>
    </form>
  </div>
</div>

<style>
/* ── REWARD MODAL ── */
.reward-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.65);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(3px);
}
.reward-modal-overlay.show { display: flex; }
.reward-modal-box {
  background: #1e1b2e;
  border-radius: 20px;
  width: 340px;
  overflow: hidden;
  box-shadow: 0 24px 60px rgba(0,0,0,0.6);
  animation: rewardPop 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
  border: 1px solid rgba(255,255,255,0.07);
}
@keyframes rewardPop {
  from { transform: scale(0.85) translateY(20px); opacity: 0; }
  to   { transform: scale(1)    translateY(0);    opacity: 1; }
}
.reward-modal-header {
  background: #2a2040;
  padding: 1.8rem 1.5rem 1.4rem;
  text-align: center;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}
.reward-star-icon {
  font-size: 2.6rem;
  line-height: 1;
  margin-bottom: 0.6rem;
  filter: drop-shadow(0 4px 12px rgba(255,200,0,0.4));
}
.reward-modal-header h2 {
  font-family: 'Playfair Display', serif;
  font-size: 1.4rem;
  color: #ffffff;
  margin: 0 0 0.35rem;
}
.reward-modal-header p {
  color: rgba(255,255,255,0.55);
  font-size: 0.85rem;
  margin: 0;
}
.reward-options {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 1.2rem 1.2rem 0.5rem;
}
.reward-option {
  background: #2a2040;
  border: 2px solid transparent;
  border-radius: 14px;
  padding: 1.1rem 0.5rem;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.18s, background 0.18s, transform 0.15s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.3rem;
}
.reward-option:hover { transform: translateY(-2px); background: #342a55; }
.reward-option.selected {
  border-color: #c9a227;
  background: #342a10;
}
.reward-option-icon { font-size: 1.6rem; line-height: 1; }
.reward-option-num {
  font-size: 1.4rem;
  font-weight: 800;
  color: #ffffff;
  line-height: 1;
}
.reward-option-label {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.5);
  font-weight: 500;
}
.reward-modal-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 1rem 1.2rem 1.4rem;
}
.reward-btn-cancel {
  background: #2a2040;
  color: rgba(255,255,255,0.7);
  border: none;
  border-radius: 12px;
  padding: 0.75rem;
  font-size: 0.9rem;
  font-weight: 600;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  transition: background 0.18s;
}
.reward-btn-cancel:hover { background: #342a55; }
.reward-btn-confirm {
  background: #c9a227;
  color: #1a1200;
  border: none;
  border-radius: 12px;
  padding: 0.75rem;
  font-size: 0.875rem;
  font-weight: 700;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  transition: background 0.18s, transform 0.15s, box-shadow 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}
.reward-btn-confirm:hover {
  background: #e0b830;
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(201,162,39,0.35);
}
</style>

<script>
function openRewardModal(sitId, studentName) {
  document.getElementById('rewardTimeoutId').value = sitId;
  document.getElementById('rewardPointsValue').value = '0';
  // Reset selection to "No points"
  document.getElementById('optionZero').classList.add('selected');
  document.getElementById('optionOne').classList.remove('selected');
  document.getElementById('rewardModal').classList.add('show');
}
function closeRewardModal() {
  document.getElementById('rewardModal').classList.remove('show');
}
function selectReward(val) {
  document.getElementById('rewardPointsValue').value = val;
  if (val === 1) {
    document.getElementById('optionOne').classList.add('selected');
    document.getElementById('optionZero').classList.remove('selected');
  } else {
    document.getElementById('optionZero').classList.add('selected');
    document.getElementById('optionOne').classList.remove('selected');
  }
}
</script>

<!-- ════ ALREADY SITTING IN MODAL ════ -->
<div id="alreadySitinModal" class="error-modal-overlay" onclick="if(event.target===this)closeAlreadySitinModal()">
  <div class="error-modal-box">
    <div class="error-modal-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
      <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 8v4M12 16h.01"/>
      </svg>
    </div>
    <h2>Already Sitting In</h2>
    <p class="error-msg">
      <strong id="alreadySitinName"></strong> is currently active in a sit-in session.
      Please time them out first before starting a new session.
    </p>
    <div class="error-modal-actions">
      <button class="error-modal-ok" style="background:linear-gradient(135deg,#f59e0b,#d97706);" onclick="closeAlreadySitinModal()">OK, Got It</button>
    </div>
  </div>
</div>

</body>
</html>