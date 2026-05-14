<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// ── MAIN STATS ──
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_sessions,
        SUM(TIMESTAMPDIFF(MINUTE, login_time, logout_time)) as total_minutes,
        MAX(TIMESTAMPDIFF(MINUTE, login_time, logout_time)) as longest_minutes
    FROM sit_in_records
    WHERE id_number = ? AND status = 'completed'
");
$stmt->execute([$user['id_number']]);
$stats = $stmt->fetch();

$total_sit_in    = (int)($stats['total_sessions'] ?? 0);
$longest_session = (int)($stats['longest_minutes'] ?? 0);
$total_minutes   = (int)($stats['total_minutes'] ?? 0);
$avg_duration    = $total_sit_in > 0 ? round($total_minutes / $total_sit_in) : 0;
$remaining       = (int)($user['remaining_sessions'] ?? 30);
$max_sessions    = 30;
$used_sessions   = max(0, $max_sessions - $remaining);
$session_pct     = round(($remaining / $max_sessions) * 100);

// ── MOST USED LAB ──
$stmt = $pdo->prepare("
    SELECT lab_number, COUNT(*) as cnt
    FROM sit_in_records WHERE id_number = ? AND status = 'completed'
    GROUP BY lab_number ORDER BY cnt DESC LIMIT 1
");
$stmt->execute([$user['id_number']]);
$top_lab = $stmt->fetch();

// ── MOST USED PURPOSE ──
$stmt = $pdo->prepare("
    SELECT purpose, COUNT(*) as cnt
    FROM sit_in_records WHERE id_number = ? AND status = 'completed'
    GROUP BY purpose ORDER BY cnt DESC LIMIT 1
");
$stmt->execute([$user['id_number']]);
$top_purpose = $stmt->fetch();

// ── PURPOSE BREAKDOWN ──
$stmt = $pdo->prepare("
    SELECT purpose, COUNT(*) as cnt
    FROM sit_in_records WHERE id_number = ? AND status = 'completed'
    GROUP BY purpose ORDER BY cnt DESC LIMIT 5
");
$stmt->execute([$user['id_number']]);
$purposes = $stmt->fetchAll();

// ── RECENT 5 SESSIONS ──
$stmt = $pdo->prepare("
    SELECT purpose, lab_number, login_time, logout_time,
           TIMESTAMPDIFF(MINUTE, login_time, logout_time) as duration
    FROM sit_in_records
    WHERE id_number = ? AND status = 'completed'
    ORDER BY login_time DESC LIMIT 5
");
$stmt->execute([$user['id_number']]);
$recent = $stmt->fetchAll();

// ── LAB BREAKDOWN ──
$stmt = $pdo->prepare("
    SELECT lab_number, COUNT(*) as cnt
    FROM sit_in_records WHERE id_number = ? AND status = 'completed'
    GROUP BY lab_number ORDER BY cnt DESC
");
$stmt->execute([$user['id_number']]);
$labs = $stmt->fetchAll();
$max_lab_cnt = !empty($labs) ? $labs[0]['cnt'] : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-in Summary — UC CCS SIT Monitoring</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <?php require_once 'theme.php'; echo theme_styles(); ?>
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; min-height: 100vh; }

        /* ── WRAPPER ── */
        .summary-wrapper {
            flex: 1;
            max-width: 1140px;
            margin: 0 auto;
            padding: 100px 2rem 2rem;
            width: 100%;
            box-sizing: border-box;
        }

        /* ── PAGE HEADING ── */
        .page-heading {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .page-heading h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--text-primary);
            margin: 0 0 0.3rem;
        }
        .page-heading p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0;
        }

        /* ── STAT CARDS ROW ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-radius: 16px;
            padding: 1.6rem 1.2rem 1.4rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: var(--shadow-card);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--card-accent, #6c3fc7);
            border-radius: 16px 16px 0 0;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(74,30,138,0.14);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.9rem;
            font-size: 1.4rem;
            background: var(--card-icon-bg, #ede9f7);
        }
        .stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--card-accent, #6c3fc7);
            line-height: 1;
        }
        .stat-unit {
            font-size: 0.85rem;
            font-weight: 500;
            opacity: 0.7;
            margin-left: 2px;
            font-family: 'DM Sans', sans-serif;
        }
        .stat-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.4rem;
        }

        /* card accent colors */
        .card-purple { --card-accent: #6c3fc7; --card-icon-bg: #ede9f7; }
        .card-gold   { --card-accent: #c9a227; --card-icon-bg: #fef9e7; }
        .card-teal   { --card-accent: #0891b2; --card-icon-bg: #e0f7fa; }
        .card-green  { --card-accent: #16a34a; --card-icon-bg: #dcfce7; }

        /* ── SESSION PROGRESS CARD ── */
        .session-card {
            background: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-radius: 16px;
            padding: 1.6rem 1.8rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
        }
        .session-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .session-card-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
        }
        .session-nums {
            display: flex;
            align-items: baseline;
            gap: 0.4rem;
        }
        .session-remaining {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }
        .session-total {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .progress-bar-wrap {
            background: var(--bg-hover);
            border-radius: 999px;
            height: 12px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #6c3fc7, #c9a227);
            transition: width 1s ease;
        }
        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        /* ── TWO COLUMN BOTTOM ── */
        .bottom-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
        }

        /* ── PANEL SHARED ── */
        .summary-panel {
            background: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }
        .summary-panel-header {
            background: var(--bg-header);
            color: #fff;
            padding: 0.85rem 1.3rem;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-panel-body {
            padding: 1.2rem 1.3rem;
        }

        /* ── PURPOSE BREAKDOWN ── */
        .purpose-row {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.9rem;
        }
        .purpose-row:last-child { margin-bottom: 0; }
        .purpose-name {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
            min-width: 110px;
        }
        .purpose-bar-wrap {
            flex: 1;
            background: var(--bg-hover);
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }
        .purpose-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #6c3fc7, #9b6ef3);
        }
        .purpose-count {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--accent);
            min-width: 24px;
            text-align: right;
        }

        /* ── LAB BREAKDOWN ── */
        .lab-row {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.9rem;
        }
        .lab-row:last-child { margin-bottom: 0; }
        .lab-badge {
            background: var(--bg-header);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            min-width: 52px;
            text-align: center;
        }
        .lab-bar-wrap {
            flex: 1;
            background: var(--bg-hover);
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
        }
        .lab-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #c9a227, #e6b84a);
        }
        .lab-count {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--accent-gold);
            min-width: 24px;
            text-align: right;
        }

        /* ── RECENT SESSIONS ── */
        .recent-item {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .recent-item:last-child { border-bottom: none; padding-bottom: 0; }
        .recent-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--accent-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .recent-info { flex: 1; }
        .recent-purpose {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .recent-meta {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .recent-duration {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--accent);
            white-space: nowrap;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .empty-state .empty-icon { font-size: 2rem; margin-bottom: 0.5rem; }

        /* ── FOOTER ── */
        footer {
            padding: 1.1rem 2rem;
            text-align: center;
            font-size: 0.82rem;
            background: var(--footer-bg) !important;
            color: var(--footer-text) !important;
            margin-top: 0;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .bottom-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 520px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .stat-value { font-size: 1.8rem; }
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
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="edit_profile.php">Edit Profile</a></li>
        <li><a href="sit_in_summary.php" class="active">Sit-in Summary</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="user_reservation.php">Reservation</a></li>
        <li><a href="sessions.php">Session</a></li>
        <?php if (function_exists('theme_toggle_button')) echo theme_toggle_button(); ?>
        <li><a href="logout.php" class="btn-nav">Log out</a></li>
    </ul>
</nav>

<div class="summary-wrapper">

    <!-- PAGE HEADING -->
    <div class="page-heading">
        <h1>Sit-in Analytics</h1>
        <p>Overview of your laboratory sit-in activity, <?= htmlspecialchars($user['first_name']) ?></p>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-row">
        <div class="stat-card card-purple">
            <div class="stat-icon">📋</div>
            <span class="stat-label">Total Sit-ins</span>
            <div class="stat-value"><?= $total_sit_in ?></div>
            <div class="stat-sub">completed sessions</div>
        </div>

        <div class="stat-card card-gold">
            <div class="stat-icon">⏱️</div>
            <span class="stat-label">Average Duration</span>
            <div class="stat-value"><?= $avg_duration ?><span class="stat-unit">min</span></div>
            <div class="stat-sub">per session</div>
        </div>

        <div class="stat-card card-teal">
            <div class="stat-icon">🏆</div>
            <span class="stat-label">Longest Session</span>
            <div class="stat-value"><?= $longest_session ?><span class="stat-unit">min</span></div>
            <div class="stat-sub">personal best</div>
        </div>

        <div class="stat-card card-green">
            <div class="stat-icon">💻</div>
            <span class="stat-label">Favourite Lab</span>
            <div class="stat-value" style="font-size:1.6rem;">
                <?= $top_lab ? 'Lab '.$top_lab['lab_number'] : '—' ?>
            </div>
            <div class="stat-sub">
                <?= $top_lab ? $top_lab['cnt'].' visits' : 'no data yet' ?>
            </div>
        </div>
    </div>

    <!-- SESSION PROGRESS -->
    <div class="session-card">
        <div class="session-card-top">
            <span class="session-card-title">📊 Session Allowance</span>
            <div class="session-nums">
                <span class="session-remaining"><?= $remaining ?></span>
                <span class="session-total">/ <?= $max_sessions ?> remaining</span>
            </div>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width: <?= $session_pct ?>%"></div>
        </div>
        <div class="progress-labels">
            <span><?= $used_sessions ?> used</span>
            <span><?= $session_pct ?>% remaining</span>
        </div>
    </div>

    <!-- BOTTOM ROW: two panels -->
    <div class="bottom-row">

        <!-- LEFT: Purpose Breakdown + Lab Breakdown stacked -->
        <div style="display:flex; flex-direction:column; gap:1.2rem;">

            <!-- PURPOSE BREAKDOWN -->
            <div class="summary-panel">
                <div class="summary-panel-header">
                    🎯 Top Purposes
                </div>
                <div class="summary-panel-body">
                    <?php if (!empty($purposes)):
                        $max_p = $purposes[0]['cnt'];
                        foreach ($purposes as $p):
                            $pct = $max_p > 0 ? round(($p['cnt'] / $max_p) * 100) : 0;
                    ?>
                    <div class="purpose-row">
                        <span class="purpose-name"><?= htmlspecialchars($p['purpose']) ?></span>
                        <div class="purpose-bar-wrap">
                            <div class="purpose-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="purpose-count"><?= $p['cnt'] ?></span>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">🎯</div>
                        No sessions yet
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LAB BREAKDOWN -->
            <div class="summary-panel">
                <div class="summary-panel-header">
                    🏫 Lab Usage
                </div>
                <div class="summary-panel-body">
                    <?php if (!empty($labs)):
                        foreach ($labs as $l):
                            $pct = $max_lab_cnt > 0 ? round(($l['cnt'] / $max_lab_cnt) * 100) : 0;
                    ?>
                    <div class="lab-row">
                        <span class="lab-badge">Lab <?= htmlspecialchars($l['lab_number']) ?></span>
                        <div class="lab-bar-wrap">
                            <div class="lab-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="lab-count"><?= $l['cnt'] ?></span>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">🏫</div>
                        No sessions yet
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RIGHT: Recent Sessions -->
        <div class="summary-panel">
            <div class="summary-panel-header">
                🕐 Recent Sessions
            </div>
            <div class="summary-panel-body">
                <?php if (!empty($recent)):
                    foreach ($recent as $r):
                        $dur = (int)$r['duration'];
                ?>
                <div class="recent-item">
                    <div class="recent-icon">🖥️</div>
                    <div class="recent-info">
                        <div class="recent-purpose"><?= htmlspecialchars($r['purpose']) ?></div>
                        <div class="recent-meta">
                            Lab <?= htmlspecialchars($r['lab_number']) ?>
                            &nbsp;·&nbsp;
                            <?= date('M d, Y', strtotime($r['login_time'])) ?>
                            &nbsp;·&nbsp;
                            <?= date('h:i A', strtotime($r['login_time'])) ?>
                        </div>
                    </div>
                    <div class="recent-duration">
                        <?= $dur > 0 ? $dur.' min' : '—' ?>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="empty-state">
                    <div class="empty-icon">🕐</div>
                    No completed sessions yet
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.bottom-row -->

</div><!-- /.summary-wrapper -->

<footer>
    &copy; <?= date('Y') ?> University of Cebu — SIT-IN Monitoring System
</footer>

<?php echo theme_script(); ?>
</body>
</html>