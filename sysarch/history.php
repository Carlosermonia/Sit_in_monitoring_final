<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Auto-create feedback table if it doesn't exist yet
$pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sit_in_id  INT NOT NULL,
    user_id    INT NOT NULL,
    rating     TINYINT NOT NULL,
    message    TEXT NOT NULL,
    created_at DATETIME NOT NULL
)");

// Handle feedback submission
$feedback_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback'])) {
    $sit_id   = (int) $_POST['sit_id'];
    $rating   = (int) $_POST['rating'];
    $feedback = trim($_POST['feedback']);

    if ($sit_id && $rating && !empty($feedback)) {
        $ins = $pdo->prepare("
            INSERT INTO feedback (sit_in_id, user_id, rating, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $ins->execute([$sit_id, $_SESSION['user_id'], $rating, $feedback]);
        $feedback_msg = 'success';
    }
}

// Fetch this student's sit-in history
$records = $pdo->prepare("
    SELECT s.id, s.purpose, s.lab_number, s.login_time, s.logout_time, s.status
    FROM sit_in_records s
    WHERE s.id_number = ?
    ORDER BY s.login_time DESC
");
$records->execute([$user['id_number']]);
$history = $records->fetchAll();

// Get sit IDs that already have feedback
$fb_check = $pdo->prepare("SELECT sit_in_id FROM feedback WHERE user_id = ?");
$fb_check->execute([$_SESSION['user_id']]);
$has_feedback = array_column($fb_check->fetchAll(), 'sit_in_id');

// Sessions eligible for feedback (done + no feedback yet)
$eligible = array_filter($history, function($r) use ($has_feedback) {
    return strtolower($r['status']) === 'completed' && !in_array($r['id'], $has_feedback);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History — UC CCS SIT Monitoring</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <?php require_once 'theme.php'; echo theme_styles(); ?>
    <style>
        /* ── Page Wrapper ── */
        .history-wrapper {
            padding: 100px 2.5rem 60px;
            max-width: 1300px;
            margin: 0 auto;
        }

        .page-heading {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--white);
            text-align: center;
            margin-bottom: 1.8rem;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        /* ── Layout: side panel + table ── */
        .layout-flex {
            display: flex;
            align-items: stretch;
            gap: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            animation: slideUp 0.5s ease both;
        }

        @keyframes slideUp {
            from { transform: translateY(24px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }

        /* ── Side Panel ── */
        .side-panel {
            width: 210px;
            min-width: 210px;
            background: var(--purple);
            padding: 1.8rem 1.1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .side-panel-title {
            color: rgba(255,255,255,0.5);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-align: center;
            margin-bottom: 2px;
        }

        .side-divider {
            width: 100%;
            height: 1px;
            background: rgba(255,255,255,0.12);
            margin: 2px 0 6px;
        }

        .btn-side {
            width: 100%;
            padding: 10px 13px;
            border-radius: 10px;
            border: none;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 9px;
            transition: background 0.15s, transform 0.12s, box-shadow 0.15s;
            font-family: 'DM Sans', sans-serif;
            text-align: left;
        }

        .btn-side svg { flex-shrink: 0; }

        /* Gold feedback button */
        .btn-give-feedback {
            background: linear-gradient(135deg, var(--gold), #e0a820);
            color: var(--purple-dk, #2d1260);
            box-shadow: 0 3px 12px rgba(201,149,42,0.35);
        }
        .btn-give-feedback:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(201,149,42,0.5);
        }
        .btn-give-feedback:active { transform: translateY(0); }

        /* Ghost buttons */
        .btn-ghost {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.82);
        }
        .btn-ghost:hover {
            background: rgba(255,255,255,0.16);
            transform: translateY(-1px);
        }

        /* Session counter at bottom */
        .session-counter {
            margin-top: auto;
            background: rgba(255,255,255,0.07);
            border-radius: 12px;
            padding: 12px 10px;
            width: 100%;
            text-align: center;
        }
        .session-counter .count-num {
            color: #fff;
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }
        .session-counter .count-lbl {
            color: rgba(255,255,255,0.45);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-top: 4px;
        }

        /* Eligible badge on feedback button */
        .eligible-badge {
            margin-left: auto;
            background: rgba(255,255,255,0.3);
            color: var(--purple-dk, #2d1260);
            border-radius: 999px;
            padding: 1px 7px;
            font-size: 0.7rem;
            font-weight: 700;
        }

        /* ── Table Card ── */
        .table-card {
            flex: 1;
            background: #fff;
            display: flex;
            flex-direction: column;
        }

        .table-card-header {
            background: var(--purple);
            color: #fff;
            padding: 1rem 1.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .table-card-body {
            padding: 1.5rem;
            flex: 1;
        }

        /* ── DataTable Overrides ── */
        #historyTable_wrapper .dataTables_length select,
        #historyTable_wrapper .dataTables_filter input {
            border: 1.5px solid #DDD8EE;
            border-radius: 8px;
            padding: 0.4rem 0.7rem;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            color: var(--text);
            outline: none;
        }

        #historyTable_wrapper .dataTables_filter input:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(74,30,138,0.1);
        }

        #historyTable_wrapper .dataTables_length label,
        #historyTable_wrapper .dataTables_filter label {
            font-size: 0.85rem;
            color: var(--muted);
            font-family: 'DM Sans', sans-serif;
        }

        table#historyTable {
            width: 100% !important;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        table#historyTable thead th {
            background: var(--purple);
            color: #fff;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.85rem 1rem;
            border: none;
        }

        table#historyTable thead th.sorting::after,
        table#historyTable thead th.sorting_asc::after,
        table#historyTable thead th.sorting_desc::after {
            color: var(--gold-lt);
        }

        table#historyTable tbody tr {
            transition: background 0.15s;
        }

        table#historyTable tbody tr:hover {
            background: #F7F5FF;
        }

        table#historyTable tbody td {
            padding: 0.85rem 1rem;
            font-size: 0.85rem;
            color: var(--text);
            border-bottom: 1px solid #F0EEF8;
            vertical-align: middle;
        }

        #historyTable_wrapper .dataTables_info {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 0.8rem;
        }

        /* Pagination */
        #historyTable_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            font-size: 0.82rem;
            color: var(--purple) !important;
            padding: 4px 10px !important;
        }

        #historyTable_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--purple) !important;
            color: #fff !important;
            border: none !important;
        }

        #historyTable_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
            background: rgba(74,30,138,0.08) !important;
            color: var(--purple) !important;
            border: none !important;
        }

        /* ── Status Badges ── */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-active { background: #dcfce7; color: #16a34a; }
        .badge-done   { background: #ede9fe; color: #6d28d9; }

        /* ── Feedback Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }

        .modal-box {
            background: #fff;
            border-radius: 20px;
            width: 90%;
            max-width: 480px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            animation: popIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes popIn {
            from { transform: scale(0.85); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }

        .modal-header {
            background: var(--purple);
            color: #fff;
            padding: 1.1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            margin: 0;
        }
        .modal-close {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.75);
            font-size: 1.3rem;
            cursor: pointer;
            line-height: 1;
            transition: color 0.2s;
        }
        .modal-close:hover { color: #fff; }

        .modal-body { padding: 1.8rem 1.5rem 1rem; }

        .modal-body label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin-bottom: 0.4rem;
        }

        /* Session selector */
        .session-select {
            width: 100%;
            border: 1.5px solid #DDD8EE;
            border-radius: 10px;
            padding: 0.65rem 1rem;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            color: var(--text);
            outline: none;
            margin-bottom: 1.2rem;
            background: #faf9ff;
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%234a1e8a' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }
        .session-select:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(74,30,138,0.1);
        }

        .modal-body textarea {
            width: 100%;
            border: 1.5px solid #DDD8EE;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            color: var(--text);
            resize: vertical;
            min-height: 100px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .modal-body textarea:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(74,30,138,0.1);
        }

        /* No eligible sessions notice */
        .no-eligible {
            background: #faf9ff;
            border: 1.5px dashed #DDD8EE;
            border-radius: 10px;
            padding: 1.2rem 1rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        /* Star Rating */
        .star-group {
            display: flex;
            gap: 6px;
            margin-bottom: 1.2rem;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .star-group input { display: none; }
        .star-group label {
            font-size: 2rem;
            color: #d1d5db;
            cursor: pointer;
            transition: color 0.15s;
            text-transform: none;
            letter-spacing: 0;
            font-weight: 400;
            margin-bottom: 0;
        }
        .star-group input:checked ~ label,
        .star-group label:hover,
        .star-group label:hover ~ label {
            color: var(--gold);
        }

        .modal-footer {
            padding: 1rem 1.5rem 1.5rem;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: var(--muted);
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--purple), #6B32B8);
            color: #fff;
            border: none;
            padding: 0.6rem 1.4rem;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(74,30,138,0.3);
            transition: transform 0.15s, box-shadow 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(74,30,138,0.4);
        }

        /* ── Sticky footer layout ── */
        html, body { height: 100%; }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .history-wrapper { flex: 1; }
        footer {
            margin-top: auto;
            padding: 1.1rem 2rem;
            text-align: center;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            background: var(--footer-bg) !important;
            color: var(--footer-text) !important;
        }

        /* ── Success Toast ── */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #fff;
            border-left: 5px solid #22c55e;
            border-radius: 10px;
            padding: 1rem 1.4rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 99999;
            animation: slideInRight 0.4s ease both;
            font-size: 0.88rem;
            color: var(--text);
            font-weight: 500;
        }
        .toast-icon { font-size: 1.3rem; }
        @keyframes slideInRight {
            from { transform: translateX(120%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }
        .toast.hide { animation: slideOutRight 0.4s ease both; }
        @keyframes slideOutRight {
            from { transform: translateX(0);    opacity: 1; }
            to   { transform: translateX(120%); opacity: 0; }
        }
        /* ══════════════════════════════════════
           DARK MODE — History Page Overrides
        ══════════════════════════════════════ */

        /* ── Main layout card ── */
        [data-theme="dark"] .layout-flex {
            box-shadow: 0 8px 40px rgba(0,0,0,0.6) !important;
        }

        /* ── Table card (white box) ── */
        [data-theme="dark"] .table-card {
            background: #1c1730 !important;
        }
        [data-theme="dark"] .table-card-header {
            background: #261e3d !important;
            border-bottom: 1px solid rgba(130,90,230,0.2) !important;
        }

        /* ── DataTable controls ── */
        [data-theme="dark"] #historyTable_wrapper .dataTables_length select,
        [data-theme="dark"] #historyTable_wrapper .dataTables_filter input {
            background: #261e3d !important;
            color: #ece8ff !important;
            border-color: #3b3060 !important;
        }
        [data-theme="dark"] #historyTable_wrapper .dataTables_length label,
        [data-theme="dark"] #historyTable_wrapper .dataTables_filter label {
            color: #7a7394 !important;
        }
        [data-theme="dark"] #historyTable_wrapper .dataTables_info {
            color: #7a7394 !important;
        }

        /* ── Table rows ── */
        [data-theme="dark"] table#historyTable tbody tr:hover {
            background: #261e3d !important;
        }
        [data-theme="dark"] table#historyTable tbody td {
            color: #cfc8e8 !important;
            border-bottom-color: #2b2245 !important;
        }
        [data-theme="dark"] table#historyTable tbody tr:nth-child(even) td {
            background: #1a1528 !important;
        }

        /* ── Pagination ── */
        [data-theme="dark"] #historyTable_wrapper .dataTables_paginate .paginate_button {
            color: #9b72f5 !important;
        }
        [data-theme="dark"] #historyTable_wrapper .dataTables_paginate .paginate_button.current {
            background: #6c3fc7 !important;
            color: #fff !important;
        }
        [data-theme="dark"] #historyTable_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
            background: rgba(155,114,245,0.15) !important;
            color: #9b72f5 !important;
        }

        /* ── Status badges dark ── */
        [data-theme="dark"] .badge-active {
            background: #052e16 !important;
            color: #4ade80 !important;
        }
        [data-theme="dark"] .badge-done {
            background: #2e1a4e !important;
            color: #c4b5fd !important;
        }

        /* ── Feedback Modal dark ── */
        [data-theme="dark"] .modal-box {
            background: #1c1730 !important;
            box-shadow: 0 24px 60px rgba(0,0,0,0.6) !important;
        }
        [data-theme="dark"] .modal-body {
            background: #1c1730 !important;
        }
        [data-theme="dark"] .modal-body label {
            color: #a89fbe !important;
        }
        [data-theme="dark"] .modal-body p {
            color: #cfc8e8 !important;
        }
        [data-theme="dark"] .session-select {
            background: #261e3d !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239b72f5' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            color: #ece8ff !important;
            border-color: #3b3060 !important;
        }
        [data-theme="dark"] .modal-body textarea {
            background: #261e3d !important;
            color: #ece8ff !important;
            border-color: #3b3060 !important;
        }
        [data-theme="dark"] .modal-body textarea::placeholder {
            color: #6b6488 !important;
        }
        [data-theme="dark"] .no-eligible {
            background: #211a35 !important;
            border-color: #3b3060 !important;
            color: #7a7394 !important;
        }
        [data-theme="dark"] .modal-footer {
            background: #1c1730 !important;
            border-top: 1px solid #2b2245 !important;
        }
        [data-theme="dark"] .btn-cancel {
            background: #2b2245 !important;
            color: #a89fbe !important;
        }
        [data-theme="dark"] .btn-cancel:hover {
            background: #3b3060 !important;
        }

        /* ── Star rating dark ── */
        [data-theme="dark"] .star-group label {
            color: #3b3060 !important;
        }
        [data-theme="dark"] .star-group input:checked ~ label,
        [data-theme="dark"] .star-group label:hover,
        [data-theme="dark"] .star-group label:hover ~ label {
            color: #e0a832 !important;
        }

        /* ── Toast dark ── */
        [data-theme="dark"] .toast {
            background: #1c1730 !important;
            color: #ece8ff !important;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5) !important;
        }

        /* ── Page heading dark ── */
        [data-theme="dark"] .page-heading {
            color: #ece8ff !important;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <a href="dashboard.php" class="nav-brand">
        <img src="UClogo.png" alt="UC Logo">
        <span class="nav-title">College of Computer Studies<br>SIT-IN Monitoring System</span>
    </a>
    <ul class="nav-links">
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="edit_profile.php">Edit Profile</a></li>
        <li><a href="sit_in_summary.php">Sit-in Summary</a></li>
        <li><a href="history.php" class="active">History</a></li>
        <li><a href="user_reservation.php">Reservation</a></li>
        <li><a href="sessions.php" >Session</a></li>
        <?php echo theme_toggle_button(); ?>
        <li><a href="logout.php" class="btn-nav">Log out</a></li>
    </ul>
</nav>

<!-- MAIN CONTENT -->
<div class="history-wrapper">
    <h1 class="page-heading">History Information</h1>

    <div class="layout-flex">

        <!-- ── LEFT SIDE PANEL ── -->
        <div class="side-panel">
            <p class="side-panel-title">Quick Actions</p>
            <div class="side-divider"></div>

            <!-- Give Feedback -->
            <button class="btn-side btn-give-feedback" onclick="openFeedback()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                Give Feedback
                <?php if (count($eligible) > 0): ?>
                    <span class="eligible-badge"><?= count($eligible) ?></span>
                <?php endif; ?>
            </button>

            <!-- View Sessions (scrolls to table) -->

            <!-- Back to Dashboard -->
            <button class="btn-side btn-ghost" onclick="window.location.href='dashboard.php'">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </button>

            <!-- Session Counter -->
            <div class="session-counter">
                <div class="count-num"><?= count($history) ?></div>
                <div class="count-lbl">Total Sessions</div>
            </div>
        </div>

        <!-- ── TABLE CARD ── -->
        <div class="table-card">
            <div class="table-card-header">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                My Sit-in History
            </div>
            <div class="table-card-body">
                <table id="historyTable" class="display" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Sit Purpose</th>
                            <th>Laboratory</th>
                            <th>Login</th>
                            <th>Logout</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id_number']) ?></td>
                            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                            <td><?= htmlspecialchars($r['purpose']) ?></td>
                            <td>Lab <?= htmlspecialchars($r['lab_number']) ?></td>
                            <td><?= $r['login_time']  ? date('h:i A', strtotime($r['login_time']))  : '—' ?></td>
                            <td><?= $r['logout_time'] ? date('h:i A', strtotime($r['logout_time'])) : '—' ?></td>
                            <td><?= $r['login_time']  ? date('M d, Y', strtotime($r['login_time']))  : '—' ?></td>
                            <td>
                                <?php if (strtolower($r['status']) === 'active'): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-done">Done</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.layout-flex -->
</div><!-- /.history-wrapper -->

<!-- ── FEEDBACK MODAL ── -->
<div class="modal-overlay" id="feedbackModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Session Feedback</h3>
            <button class="modal-close" onclick="closeFeedback()">✕</button>
        </div>

        <?php if (count($eligible) > 0): ?>
        <form method="POST" action="history.php" id="feedbackForm">
            <input type="hidden" name="sit_id" id="modal-sit-id">
            <div class="modal-body">

                <!-- Session selector -->
                <label>Select Session</label>
                <select class="session-select" id="session-select" onchange="document.getElementById('modal-sit-id').value = this.value">
                    <option value="">— Choose a session —</option>
                    <?php foreach ($eligible as $r): ?>
                    <option value="<?= $r['id'] ?>">
                        <?= htmlspecialchars($r['purpose']) ?> — Lab <?= htmlspecialchars($r['lab_number']) ?>
                        (<?= date('M d, Y', strtotime($r['login_time'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>

                <label>How was your experience?</label>
                <div class="star-group">
                    <input type="radio" name="rating" id="s5" value="5"><label for="s5">★</label>
                    <input type="radio" name="rating" id="s4" value="4"><label for="s4">★</label>
                    <input type="radio" name="rating" id="s3" value="3"><label for="s3">★</label>
                    <input type="radio" name="rating" id="s2" value="2"><label for="s2">★</label>
                    <input type="radio" name="rating" id="s1" value="1"><label for="s1">★</label>
                </div>

                <label style="margin-top:0.4rem;">Your Feedback</label>
                <textarea name="feedback" placeholder="Tell us about your sit-in session — the lab, the equipment, the experience..." required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeFeedback()">Cancel</button>
                <button type="submit" class="btn-submit">Submit Feedback</button>
            </div>
        </form>
        <?php else: ?>
        <div class="modal-body">
            <div class="no-eligible">
                <p style="margin:0; font-size:1.5rem;">✅</p>
                <p style="margin:0.5rem 0 0; font-weight:600; color:var(--text);">All caught up!</p>
                <p style="margin:0.3rem 0 0;">You have no pending sessions to give feedback on.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeFeedback()">Close</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── SUCCESS TOAST ── -->
<?php if ($feedback_msg === 'success'): ?>
<div class="toast" id="toast">
    <span class="toast-icon">✅</span>
    <span>Thank you! Your feedback has been submitted.</span>
</div>
<script>
    setTimeout(() => {
        const t = document.getElementById('toast');
        t.classList.add('hide');
        setTimeout(() => t.remove(), 400);
    }, 3500);
</script>
<?php endif; ?>

<footer>
    &copy; <?= date('Y') ?> University of Cebu — SIT-IN Monitoring System
</footer>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function () {
        $('#historyTable').DataTable({
            order: [[6, 'desc']],
            pageLength: 10,
            language: {
                emptyTable: "No sit-in history found.",
                zeroRecords: "No matching records found."
            }
        });
    });

    function openFeedback() {
        document.getElementById('feedbackModal').classList.add('show');
    }

    function closeFeedback() {
        document.getElementById('feedbackModal').classList.remove('show');
        const form = document.getElementById('feedbackForm');
        if (form) form.reset();
    }

    // Validate before submit
    document.getElementById('feedbackForm')?.addEventListener('submit', function(e) {
        const sitId  = document.getElementById('session-select').value;
        const rating = document.querySelector('input[name="rating"]:checked');
        const msg    = document.querySelector('textarea[name="feedback"]').value.trim();

        if (!sitId) {
            e.preventDefault();
            showFormError('Please select a session first.');
            return;
        }
        if (!rating) {
            e.preventDefault();
            showFormError('Please choose a star rating.');
            return;
        }
        if (!msg) {
            e.preventDefault();
            showFormError('Please write your feedback before submitting.');
            return;
        }
    });

    function showFormError(msg) {
        let err = document.getElementById('form-error');
        if (!err) {
            err = document.createElement('div');
            err.id = 'form-error';
            err.style.cssText = 'background:#fff0f0;border-left:4px solid #ef4444;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#b91c1c;margin-bottom:1rem;font-weight:600;';
            document.querySelector('.modal-body').prepend(err);
        }
        err.textContent = '⚠ ' + msg;
        setTimeout(() => err && err.remove(), 3500);
    }

    // Close modal on overlay click
    document.getElementById('feedbackModal').addEventListener('click', function(e) {
        if (e.target === this) closeFeedback();
    });
</script>
<?php echo theme_script(); ?>
</body>
</html>