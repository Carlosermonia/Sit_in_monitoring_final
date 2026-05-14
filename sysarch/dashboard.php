<?php
session_start();
require 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch latest user data from DB to ensure it's up to date
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch announcements from DB (same table admin posts to)
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 20")->fetchAll();

// ── NOTIFICATIONS ──
if (!isset($_SESSION['last_viewed_notifications'])) {
    $_SESSION['last_viewed_notifications'] = date('Y-m-d H:i:s', strtotime('-30 days'));
}

$stmt = $pdo->prepare("
    SELECT 'sit_in' as type, login_time as timestamp,
           CONCAT('✅ You sat in at Lab ', lab_number, ' for ', purpose) as message
    FROM sit_in_records
    WHERE id_number = ? AND status IN ('active', 'completed')

    UNION ALL

    SELECT 'logout' as type, logout_time as timestamp,
           CONCAT('🚪 You logged out from Lab ', lab_number) as message
    FROM sit_in_records
    WHERE id_number = ? AND status = 'completed' AND logout_time IS NOT NULL

    UNION ALL

    SELECT 'reservation_pending' as type, login_time as timestamp,
           CONCAT('⏳ Reservation submitted — Lab ', lab_number, ' for ', purpose, ' is awaiting admin approval') as message
    FROM sit_in_records
    WHERE id_number = ? AND (status = 'pending' OR status IS NULL OR status = '')

    UNION ALL

    SELECT 'reservation_approved' as type, login_time as timestamp,
           CONCAT('🎉 Reservation approved — Lab ', lab_number, ' for ', purpose) as message
    FROM sit_in_records
    WHERE id_number = ? AND status = 'active'

    UNION ALL

    SELECT 'reservation_cancelled' as type, login_time as timestamp,
           CONCAT('❌ Reservation cancelled — Lab ', lab_number, ' for ', purpose) as message
    FROM sit_in_records
    WHERE id_number = ? AND status = 'cancelled'

    UNION ALL

    SELECT 'announcement' as type, created_at as timestamp,
           CONCAT('📢 New announcement: ', LEFT(content, 60), '...') as message
    FROM announcements

    ORDER BY timestamp DESC LIMIT 15
");
$stmt->execute([$user['id_number'], $user['id_number'], $user['id_number'], $user['id_number'], $user['id_number']]);
$notifications = $stmt->fetchAll();

$unread_count = 0;
foreach ($notifications as $notif) {
    if (strtotime($notif['timestamp']) > strtotime($_SESSION['last_viewed_notifications'])) {
        $unread_count++;
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM sit_in_records WHERE id_number = ? AND status = 'active'");
$stmt->execute([$user['id_number']]);
$is_sitting_in = $stmt->fetchColumn() > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — UC CCS SIT Monitoring</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <?php require_once 'theme.php'; echo theme_styles(); ?>
    <style>
        /* ── Sticky footer layout ── */
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; min-height: 100vh; }
        .dashboard-container { flex: 1; }
        footer {
            margin-top: auto;
            padding: 1.1rem 2rem;
            text-align: center;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            background: var(--footer-bg) !important;
            color: var(--footer-text) !important;
        }

        /* ── Notification icon colors per type ── */
        .notification-icon.reservation_pending svg  { stroke: #d97706; }
        .notification-icon.reservation_approved svg { stroke: #16a34a; }
        .notification-icon.reservation_cancelled svg{ stroke: #dc2626; }
        .notification-icon.sit_in svg               { stroke: #6c3fc7; }
        .notification-icon.logout svg               { stroke: #0369a1; }
        .notification-icon.announcement svg         { stroke: #b45309; }

        /* ══════════════════════════════════════
           DARK MODE — Dashboard Panel Overrides
        ══════════════════════════════════════ */

        /* ── Panel backgrounds & borders ── */
        [data-theme="dark"] .info-panel,
        [data-theme="dark"] .announcement-panel,
        [data-theme="dark"] .rules-panel {
            background: #1c1730 !important;
            border: 1px solid rgba(130,90,230,0.18) !important;
            box-shadow: 0 4px 28px rgba(0,0,0,0.5) !important;
        }

        /* ── Panel headers ── */
        [data-theme="dark"] .panel-header {
            background: #261e3d !important;
            color: #ece8ff !important;
            border-bottom: 1px solid rgba(130,90,230,0.2) !important;
        }

        /* ── Avatar frame ── */
        [data-theme="dark"] .avatar-frame {
            border-color: #4a3f6e !important;
            background: #261e3d !important;
        }

        /* ── Info list rows ── */
        [data-theme="dark"] .info-item {
            border-bottom-color: #2b2245 !important;
        }
        [data-theme="dark"] .info-item .label {
            color: #a89fbe !important;
        }
        [data-theme="dark"] .info-item .val {
            color: #ddd6f8 !important;
        }
        [data-theme="dark"] .session-count {
            color: #9b72f5 !important;
        }

        /* ── Status indicator ── */
        [data-theme="dark"] .status-indicator.inactive {
            background: #1c2433 !important;
            color: #9ca3af !important;
        }
        [data-theme="dark"] .status-indicator.inactive .status-dot {
            background: #4b5563 !important;
        }
        [data-theme="dark"] .status-indicator.active {
            background: #052e16 !important;
            color: #4ade80 !important;
        }
        [data-theme="dark"] .status-indicator.active .status-dot {
            background: #4ade80 !important;
        }

        /* ── Announcement posts ── */
        [data-theme="dark"] .scroll-box {
            scrollbar-color: #4a3f6e #1a1528;
        }
        [data-theme="dark"] .post {
            background: #211a35 !important;
            border: 1px solid #2b2245 !important;
            border-radius: 8px;
            margin-bottom: 0.6rem;
        }
        [data-theme="dark"] .post-meta {
            color: #7a7394 !important;
        }
        [data-theme="dark"] .post p {
            color: #cfc8e8 !important;
        }

        /* ── Rules panel text ── */
        [data-theme="dark"] .rules-content h3,
        [data-theme="dark"] .rules-content h4,
        [data-theme="dark"] .rules-content p,
        [data-theme="dark"] .rules-content strong {
            color: #ddd6f8 !important;
        }
        [data-theme="dark"] .rules-content li {
            color: #bab3d5 !important;
        }
        [data-theme="dark"] .rules-content ol {
            border-left-color: #4a3f6e !important;
        }

        /* ── Announcement left border accent ── */
        [data-theme="dark"] .post {
            border-left: 3px solid #9b72f5 !important;
        }
    </style>
</head>
<body>

<nav>
    <a href="dashboard.php" class="nav-brand">
        <img src="UClogo.png" alt="UC Logo">
        <span class="nav-title">College of Computer Studies<br>SIT-IN Monitoring System</span>
    </a>
    <ul class="nav-links">
        <li><a href="dashboard.php" class="active">Home</a></li>
        <li><a href="edit_profile.php">Edit Profile</a></li>
        <li><a href="sit_in_summary.php">Sit-in Summary</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="user_reservation.php">Reservation</a></li>
           <li><a href="sessions.php" >Session</a></li>
        <li class="notification-item">
            <button class="notification-btn" onclick="toggleNotifications()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5-5V9a4 4 0 00-8 0v3l-5 5h5m0 0v1a3 3 0 006 0v-1m-6 0h6"/>
                </svg>
                <?php if ($unread_count > 0): ?>
                <span class="notification-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </button>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">Notifications</div>
                <div class="notification-list">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notif): ?>
                        <div class="notification-entry">
                            <div class="notification-icon <?= $notif['type'] ?>">
                                <?php if ($notif['type'] === 'sit_in'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                <?php elseif ($notif['type'] === 'logout'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                <?php elseif ($notif['type'] === 'reservation_pending'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                <?php elseif ($notif['type'] === 'reservation_approved'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                <?php elseif ($notif['type'] === 'reservation_cancelled'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                <?php elseif ($notif['type'] === 'announcement'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="notification-content">
                                <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                <div class="notification-time"><?= date('M d, H:i', strtotime($notif['timestamp'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notification-empty">No notifications yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </li>
        <!-- ── THEME TOGGLE ── -->
        <?php echo theme_toggle_button(); ?>
        <li><a href="logout.php" class="btn-nav">Log out</a></li>
    </ul>
</nav>

<div class="dashboard-container">
    
    <aside class="info-panel">
        <div class="panel-header">Student Information</div>
        <div class="profile-card">
            <div class="avatar-frame">
                <?php 
                    $display_pic = (!empty($user['profile_picture']) && file_exists('uploads/' . $user['profile_picture'])) 
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
                    <span class="label">Year:</span>
                    <span class="val"><?= htmlspecialchars($user['course_level']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Email:</span>
                    <span class="val"><?= htmlspecialchars($user['email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Session:</span>
                    <span class="val session-count"><?= htmlspecialchars($user['remaining_sessions'] ?? 30) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Points:</span>
                    <span class="val" style="display:flex;align-items:center;gap:6px;">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#fef9c3;color:#854d0e;padding:3px 10px;border-radius:20px;font-weight:700;font-size:0.85rem;">
                            ⭐ <?= (int)($user['points'] ?? 0) ?> / 3
                        </span>
                        <?php if (($user['points'] ?? 0) >= 2): ?>
                        <span style="font-size:0.72rem;color:#16a34a;font-weight:600;">Almost there!</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Status:</span>
                    <span class="val">
                        <span class="status-indicator <?= $is_sitting_in ? 'active' : 'inactive' ?>">
                            <span class="status-dot"></span>
                            <?= $is_sitting_in ? 'Currently Sitting In' : 'Not Sitting In' ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </aside>

    <main class="announcement-panel">
        <div class="panel-header">Announcements</div>
        <div class="scroll-box">
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $ann): ?>
                <div class="post">
                    <div class="post-meta">CCS Admin | <?= date('Y-M-d', strtotime($ann['created_at'])) ?></div>
                    <p><?= htmlspecialchars($ann['content']) ?></p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="post">
                    <p>No announcements yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <section class="rules-panel">
        <div class="panel-header">Rules and Regulation</div>
        <div class="scroll-box rules-content">
            <h3>University of Cebu</h3>
            <h4>COLLEGE OF INFORMATION &amp; COMPUTER STUDIES</h4>
            <p><strong>LABORATORY RULES AND REGULATIONS</strong></p>
            <ol>
                <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones must be switched off.</li>
                <li>Games are not allowed inside the lab. This includes computer-related games and card games.</li>
                <li>Surfing the Internet is allowed only with the permission of the instructor.</li>
                <li>Downloading and installing software are strictly prohibited.</li>
            </ol>
        </div>
    </section>

</div>

<footer>
    &copy; <?= date('Y') ?> University of Cebu — SIT-IN Monitoring System
</footer>

<?php echo theme_script(); ?>
<script>
// (theme handled by theme.php)
(function () { return; // legacy placeholder
    const html  = document.documentElement;
    const btn   = document.getElementById('themeToggle');
    const icon  = document.getElementById('toggleIcon');
    const label = document.getElementById('toggleLabel');

    // Shared key so both pages sync
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

    // Load saved preference
    const saved = localStorage.getItem(THEME_KEY) || 'light';
    applyTheme(saved);

    btn.addEventListener('click', function() {
        const current  = html.getAttribute('data-theme');
        const newTheme = current === 'light' ? 'dark' : 'light';
        localStorage.setItem(THEME_KEY, newTheme);
        applyTheme(newTheme);
    });

    // Sync across tabs
    window.addEventListener('storage', function(e) {
        if (e.key === THEME_KEY && e.newValue) {
            applyTheme(e.newValue);
        }
    });
})();

// ── NOTIFICATIONS ──
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const isActive = dropdown.classList.toggle('active');
    if (isActive) markNotificationsAsViewed();
}

function markNotificationsAsViewed() {
    fetch('mark_notifications_viewed.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_viewed' })
    }).then(r => r.json()).then(() => {
        const badge = document.querySelector('.notification-badge');
        if (badge) badge.style.display = 'none';
    }).catch(err => console.error('Error:', err));
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.notification-item')) {
        document.getElementById('notificationDropdown').classList.remove('active');
    }
});
</script>
</body>
</html>