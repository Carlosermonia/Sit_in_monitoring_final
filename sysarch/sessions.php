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

// ── QUICK STATS ──
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'active'    THEN 1 ELSE 0 END) as active,
        SUM(TIMESTAMPDIFF(MINUTE, login_time, logout_time)) as total_mins
    FROM sit_in_records WHERE id_number = ?
");
$stmt->execute([$user['id_number']]);
$qs = $stmt->fetch();

// ── ALL SESSIONS ──
$stmt = $pdo->prepare("
    SELECT
        DATE(login_time)   as session_date,
        TIME(login_time)   as time_in,
        TIME(logout_time)  as time_out,
        TIMESTAMPDIFF(MINUTE, login_time, logout_time) as duration,
        lab_number, purpose, status
    FROM sit_in_records
    WHERE id_number = ?
    ORDER BY login_time DESC
");
$stmt->execute([$user['id_number']]);
$sessions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions — UC CCS SIT Monitoring</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <?php require_once 'theme.php'; echo theme_styles(); ?>
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; min-height: 100vh; }

        /* ── WRAPPER ── */
        .sessions-wrapper {
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 100px 2rem 2rem;
            width: 100%;
            box-sizing: border-box;
        }

        /* ── PAGE HEADING ── */
        .page-heading {
            margin-bottom: 1.8rem;
        }
        .page-heading h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--text-primary);
            margin: 0 0 0.2rem;
        }
        .page-heading p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
        }

        /* ── MINI STAT STRIP ── */
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .strip-card {
            background: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-radius: 12px;
            padding: 1.1rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            box-shadow: var(--shadow-card);
        }
        .strip-icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .strip-info { flex: 1; }
        .strip-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
            color: var(--text-primary);
        }
        .strip-lbl {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-top: 3px;
        }

        /* ── TABLE PANEL ── */
        .table-panel {
            background: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-panel);
        }
        .table-panel-header {
            background: var(--bg-header);
            color: #fff;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-panel-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .record-count {
            background: rgba(255,255,255,0.18);
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        /* ── TABLE ── */
        .sessions-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sessions-table thead th {
            background: var(--bg-table-head) !important;
            color: var(--text-table-head) !important;
            padding: 0.85rem 1.2rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            border-bottom: 2px solid var(--accent-gold);
            text-align: left;
        }
        .sessions-table tbody td {
            padding: 0.9rem 1.2rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
            color: var(--text-table-body);
            vertical-align: middle;
        }
        .sessions-table tbody tr:last-child td { border-bottom: none; }
        .sessions-table tbody tr:hover td { background: var(--bg-table-hover) !important; }
        .sessions-table tbody tr:nth-child(even) td { background: var(--bg-table-alt); }

        /* ── DATE CELL ── */
        .date-cell { font-weight: 600; color: var(--text-primary) !important; }
        .date-sub  { font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; }

        /* ── TIME CELL ── */
        .time-cell { font-family: monospace; font-size: 0.85rem; }

        /* ── DURATION BADGE ── */
        .duration-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--accent-light);
            color: var(--accent);
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .duration-pill.zero { background: var(--bg-hover); color: var(--text-muted); }

        /* ── LAB BADGE ── */
        .lab-pill {
            display: inline-block;
            background: var(--bg-header);
            color: #fff;
            border-radius: 999px;
            padding: 3px 11px;
            font-size: 0.72rem;
            font-weight: 700;
        }

        /* ── PURPOSE CELL ── */
        .purpose-cell {
            font-size: 0.82rem;
            color: var(--text-secondary) !important;
        }

        /* ── STATUS BADGES ── */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-completed { background: #ede9fe; color: #6d28d9; }
        .status-active    { background: #dcfce7; color: #16a34a; }
        .status-pending   { background: #fef3c7; color: #b45309; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }

        /* ── EMPTY STATE ── */
        .empty-row td {
            text-align: center;
            padding: 4rem 2rem !important;
            color: var(--text-muted);
        }
        .empty-icon { font-size: 2.5rem; margin-bottom: 0.6rem; }
        .empty-msg  { font-size: 0.9rem; font-weight: 500; }

        /* ── FOOTER ── */
        footer {
            padding: 1.1rem 2rem;
            text-align: center;
            font-size: 0.82rem;
            margin-top: 0;
            background: var(--footer-bg) !important;
            color: var(--footer-text) !important;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 860px) {
            .stat-strip { grid-template-columns: repeat(2, 1fr); }
            .sessions-wrapper { padding-top: 90px; }
        }
        @media (max-width: 540px) {
            .stat-strip { grid-template-columns: 1fr 1fr; }
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
        <li><a href="sit_in_summary.php">Sit-in Summary</a></li>
        <li><a href="history.php">History</a></li>
        <li><a href="user_reservation.php">Reservation</a></li>
        <li><a href="sessions.php" class="active">Session</a></li>
        <?php if (function_exists('theme_toggle_button')) echo theme_toggle_button(); ?>
        <li><a href="logout.php" class="btn-nav">Log out</a></li>
    </ul>
</nav>

<div class="sessions-wrapper">

    <!-- HEADING -->
    <div class="page-heading">
        <h1>Session History</h1>
        <p>All your laboratory sit-in records, <?= htmlspecialchars($user['first_name']) ?></p>
    </div>

    <!-- MINI STAT STRIP -->
    <div class="stat-strip">
        <div class="strip-card">
            <div class="strip-icon" style="background:#ede9f7;">📋</div>
            <div class="strip-info">
                <div class="strip-val"><?= (int)($qs['total'] ?? 0) ?></div>
                <div class="strip-lbl">Total Sessions</div>
            </div>
        </div>
        <div class="strip-card">
            <div class="strip-icon" style="background:#dcfce7;">✅</div>
            <div class="strip-info">
                <div class="strip-val" style="color:#16a34a;"><?= (int)($qs['completed'] ?? 0) ?></div>
                <div class="strip-lbl">Completed</div>
            </div>
        </div>
        <div class="strip-card">
            <div class="strip-icon" style="background:#fef3c7;">⏳</div>
            <div class="strip-info">
                <div class="strip-val" style="color:#b45309;"><?= (int)($qs['active'] ?? 0) ?></div>
                <div class="strip-lbl">Active Now</div>
            </div>
        </div>
        <div class="strip-card">
            <div class="strip-icon" style="background:#e0f7fa;">⏱️</div>
            <div class="strip-info">
                <div class="strip-val" style="color:#0891b2;"><?= (int)($qs['total_mins'] ?? 0) ?></div>
                <div class="strip-lbl">Total Minutes</div>
            </div>
        </div>
    </div>

    <!-- TABLE PANEL -->
    <div class="table-panel">
        <div class="table-panel-header">
            <span class="table-panel-title">
                🖥️ All Sessions
            </span>
            <span class="record-count"><?= count($sessions) ?> record<?= count($sessions) !== 1 ? 's' : '' ?></span>
        </div>

        <table class="sessions-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Duration</th>
                    <th>Lab</th>
                    <th>Purpose</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($sessions)): ?>
                    <?php foreach ($sessions as $row):
                        $s = strtolower(trim($row['status'] ?? ''));
                        $badge = match($s) {
                            'completed' => 'status-completed',
                            'active'    => 'status-active',
                            'cancelled' => 'status-cancelled',
                            default     => 'status-pending',
                        };
                        $label = match($s) {
                            'completed' => 'Completed',
                            'active'    => 'Active',
                            'cancelled' => 'Cancelled',
                            default     => 'Pending',
                        };
                        $dur = (int)($row['duration'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <div class="date-cell"><?= date('M d, Y', strtotime($row['session_date'])) ?></div>
                            <div class="date-sub"><?= date('l', strtotime($row['session_date'])) ?></div>
                        </td>
                        <td class="time-cell"><?= date('h:i A', strtotime($row['time_in'])) ?></td>
                        <td class="time-cell">
                            <?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '<span style="color:var(--text-muted)">—</span>' ?>
                        </td>
                        <td>
                            <?php if ($dur > 0): ?>
                                <span class="duration-pill">⏱ <?= $dur ?> min</span>
                            <?php else: ?>
                                <span class="duration-pill zero">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="lab-pill">Lab <?= htmlspecialchars($row['lab_number']) ?></span></td>
                        <td class="purpose-cell"><?= htmlspecialchars($row['purpose'] ?? '—') ?></td>
                        <td><span class="status-badge <?= $badge ?>"><?= $label ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="empty-row">
                        <td colspan="7">
                            <div class="empty-icon">🖥️</div>
                            <div class="empty-msg">No sessions found yet.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /.sessions-wrapper -->

<footer>
    &copy; <?= date('Y') ?> University of Cebu — SIT-IN Monitoring System
</footer>

<?php echo theme_script(); ?>
</body>
</html>