<?php
session_start();
require 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get student info from session/database
$student_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: login.php');
    exit;
}

// Fetch all labs from database
function getLabs($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT lab_number FROM sit_in_records ORDER BY lab_number ASC");
    $labs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return !empty($labs) ? $labs : ['524', '526', '528', '530', '542'];
}

// Get lab computers and their status
function getLabStatus($pdo, $lab) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as occupied,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM sit_in_records 
        WHERE lab_number = ? AND login_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute([$lab]);
    return $stmt->fetch();
}

// ── SOFTWARE AVAILABILITY TABLE ──
// Table is managed by the admin via admin_dashboard.php
// Do NOT auto-seed here — it would re-insert software the admin deleted

// Get all computer states for a lab (only from actual database reservations)
function getLabComputerStates($pdo, $lab) {
    // Get all active and recent sit-ins for this lab
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM sit_in_records 
        WHERE lab_number = ? AND login_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        GROUP BY status
    ");
    $stmt->execute([$lab]);
    $data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $occupied = (int)($data['active'] ?? 0);
    $total = 50;
    
    // Create array with only actual occupancy
    $states = array_fill(0, $total, 'available');
    
    // Mark occupied computers from actual data
    for ($i = 0; $i < $occupied && $i < $total; $i++) {
        $states[$i] = 'occupied';
    }
    
    shuffle($states);
    return $states;
}

// Get student's reservation history
function getStudentReservations($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM sit_in_records 
        WHERE id_number = (SELECT id_number FROM users WHERE id = ?)
        ORDER BY login_time DESC 
        LIMIT 10
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll();
}

// Check if student can reserve (has sessions left)
function canReserve($student_sessions) {
    return (int)$student_sessions > 0;
}

// Save reservation to database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve') {
    $lab = trim($_POST['lab'] ?? '');
    $pc = trim($_POST['pc'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');

    $errors = [];
    if (empty($purpose)) $errors[] = 'Purpose is required';
    if (empty($lab)) $errors[] = 'Lab selection is required';
    if (empty($pc)) $errors[] = 'Computer selection is required';
    if (empty($date)) $errors[] = 'Date is required';
    if (empty($time)) $errors[] = 'Time is required';

    if (!canReserve($student['remaining_sessions'])) {
        $errors[] = 'You have no remaining sessions';
    }

    if (empty($errors)) {
        try {
            $login_datetime = $date . ' ' . $time . ':00';
            $stmt = $pdo->prepare("
                INSERT INTO sit_in_records 
                (id_number, purpose, lab_number, pc_number, login_time, status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student['id_number'], $purpose, $lab, $pc, $login_datetime, 'pending']);

            header('Location: user_reservation.php?pending=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$labs = getLabs($pdo);
$reservations = getStudentReservations($pdo, $student_id);
$success      = isset($_GET['success']);
$pending      = isset($_GET['pending']);
$cancelled    = isset($_GET['cancelled']);
$cancel_error = isset($_GET['cancel_error']);
$error_msg    = $errors[0] ?? '';

// Get software availability for each lab
function getLabSoftware($pdo, $lab) {
    $stmt = $pdo->prepare("SELECT software_name, version, category, description FROM software_availability WHERE lab_number = ? ORDER BY category, software_name");
    $stmt->execute([$lab]);
    return $stmt->fetchAll();
}

// --- HANDLE CANCELLATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reservation_id'])) {
    $res_id = (int) $_POST['cancel_reservation_id'];
    $id_num = $student['id_number'];

    // Delete the record entirely — removes it from both student view and admin table
    $stmt = $pdo->prepare("DELETE FROM sit_in_records WHERE id = ? AND id_number = ? AND status NOT IN ('active', 'completed')");
    $stmt->execute([$res_id, $id_num]);

    if ($stmt->rowCount() > 0) {
        header('Location: user_reservation.php?cancelled=1');
    } else {
        // Fallback: try delete without status restriction (for empty/null status rows)
        $stmt2 = $pdo->prepare("DELETE FROM sit_in_records WHERE id = ? AND id_number = ?");
        $stmt2->execute([$res_id, $id_num]);
        if ($stmt2->rowCount() > 0) {
            header('Location: user_reservation.php?cancelled=1');
        } else {
            header('Location: user_reservation.php?cancel_error=1');
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Lab Reservation — UC CCS SIT Monitoring</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="dashboard.css"/>
  <?php require_once 'theme.php'; echo theme_styles(); ?>
  <style>
    /* ── PAGE LAYOUT ── */
    .reservation-container {
      max-width: 1300px;
      margin: 0 auto;
      padding: 100px 2.5rem 48px;
    }

    .page-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.7rem;
      font-weight: 700;
      color: var(--purple-dk);
      text-align: center;
      margin-bottom: 2rem;
      letter-spacing: 0.02em;
    }

    .two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      align-items: start;
    }

    /* ── ALERTS ── */
    .alert {
      padding: 1rem 1.2rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      font-size: 0.9rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from { transform: translateY(-20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .alert-success {
      background: #ecfdf5;
      color: #065f46;
      border: 1.5px solid #86efac;
    }

    .alert-error {
      background: #fef2f2;
      color: #991b1b;
      border: 1.5px solid #fca5a5;
    }

    /* ── SHARED PANEL CARD ── */
    .res-panel {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(0,0,0,0.13);
      animation: slideUp 0.5s ease both;
    }

    @keyframes slideUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .res-panel-header {
      background: var(--purple);
      color: #fff;
      padding: 0.85rem 1.2rem;
      font-weight: 600;
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .res-panel-body {
      padding: 1.5rem;
    }

    /* ── FORM STYLES ── */
    .form-row {
      margin-bottom: 1rem;
    }

    .form-row label {
      display: block;
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--purple-dk);
      margin-bottom: 0.3rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .form-row input,
    .form-row select {
      width: 100%;
      padding: 0.6rem 0.9rem;
      border: 1.5px solid #DDD8F0;
      border-radius: 7px;
      font-size: 0.88rem;
      font-family: 'DM Sans', sans-serif;
      color: var(--text);
      background: var(--offwhite);
      outline: none;
      transition: border-color 0.2s;
    }

    .form-row input:focus,
    .form-row select:focus {
      border-color: var(--purple);
    }

    .form-row input[readonly] {
      background: #EEEAF8;
      color: var(--muted);
      cursor: not-allowed;
    }

    .form-row-inline {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.8rem;
    }

    .form-divider {
      border: none;
      border-top: 1.5px solid #EEEAF8;
      margin: 1rem 0;
    }

    /* ── SESSION BADGE ── */
    .session-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
      padding: 1rem;
      background: #f3f0ff;
      border-radius: 10px;
      border: 1.5px solid var(--gold);
    }

    .session-label {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--purple-dk);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .session-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--offwhite);
      border: 1.5px solid var(--gold);
      color: var(--purple-dk);
      font-weight: 700;
      font-size: 0.95rem;
      padding: 0.35rem 0.9rem;
      border-radius: 8px;
    }

    .session-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--gold);
      display: inline-block;
    }

    .session-warning {
      background: #fef3c7;
      color: #92400e;
    }

    /* ── BUTTONS ── */
    .btn-reserve {
      width: 100%;
      padding: 0.75rem;
      background: var(--gold);
      color: var(--purple-dk);
      border: none;
      border-radius: 8px;
      font-size: 0.92rem;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      letter-spacing: 0.03em;
      transition: background 0.2s, transform 0.1s;
    }

    .btn-reserve:hover { background: var(--gold-lt); }
    .btn-reserve:active { transform: scale(0.98); }
    .btn-reserve:disabled {
      background: #d1d5db;
      cursor: not-allowed;
      color: #6b7280;
    }

    /* ── LAB TABS ── */
    .lab-tabs {
      display: flex;
      gap: 0.4rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }

    .tab-btn {
      padding: 0.38rem 1rem;
      border-radius: 20px;
      border: 1.5px solid var(--purple);
      background: transparent;
      color: var(--purple);
      font-size: 0.78rem;
      font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      transition: all 0.18s;
    }

    .tab-btn:hover,
    .tab-btn.active {
      background: var(--purple);
      color: #fff;
    }

    /* ── LEGEND ── */
    .legend {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.72rem;
      color: var(--muted);
      font-weight: 500;
    }

    .legend-dot {
      width: 13px;
      height: 13px;
      border-radius: 4px;
      border: 1.5px solid transparent;
      flex-shrink: 0;
    }

    /* ── COMPUTER GRID ── */
    .row-group {
      margin-bottom: 10px;
    }

    .row-label {
      font-size: 0.68rem;
      font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-bottom: 4px;
    }

    .computer-grid {
      display: grid;
      grid-template-columns: repeat(10, 1fr);
      gap: 5px;
    }

    .pc {
      aspect-ratio: 1 / 1;
      border-radius: 5px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.58rem;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      border: 1.5px solid transparent;
      transition: transform 0.12s, box-shadow 0.12s;
      user-select: none;
      position: relative;
      flex-direction: column;
      gap: 4px;
    }

    .pc::before {
      content: '🖥';
      font-size: 1.5rem;
    }

    .pc:hover { transform: scale(1.13); box-shadow: 0 3px 10px rgba(74,30,138,0.2); }

    .pc.available { background: #E9F9EE; border-color: #4CAF82; color: #2D6E4E; }
    .pc.available::before { content: '✓ 🖥'; }
    
    .pc.occupied  { background: #FDEAEA; border-color: #E05757; color: #8B1A1A; cursor: not-allowed; }
    .pc.occupied::before { content: '⊗ 🖥'; }
    
    .pc.reserved  { background: #FFF4DC; border-color: #C9952A; color: #7A4E00; cursor: not-allowed; }
    .pc.reserved::before { content: '⏳ 🖥'; }
    
    .pc.selected  { background: var(--purple); border-color: var(--purple-dk); color: #fff; }
    .pc.selected::before { content: '★ 🖥'; }

    .occupied:hover, .reserved:hover { transform: none; box-shadow: none; }

    .avail-count {
      font-size: 0.72rem;
      color: var(--muted);
      text-align: right;
      margin-top: 10px;
    }

    /* ── HISTORY SECTION ── */
    .history-panel {
      grid-column: 1 / -1;
      margin-top: 1.5rem;
    }

    .history-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
    }

    .history-table th {
      background: #f3f0ff;
      padding: 0.8rem;
      text-align: left;
      font-weight: 700;
      color: var(--purple-dk);
      border-bottom: 1.5px solid var(--gold);
    }

    .history-table td {
      padding: 0.8rem;
      border-bottom: 1px solid #EEEAF8;
    }

    .history-table tbody tr:hover {
      background: #f9f8ff;
    }

    .empty-history {
      text-align: center;
      padding: 2rem;
      color: var(--muted);
    }

    /* ── SOFTWARE AVAILABILITY ── */
    .software-panel {
      margin-top: 1.5rem;
      padding: 1rem;
      background: #f8f9ff;
      border-radius: 8px;
      border: 1.5px solid #e0e7ff;
    }

    .software-header {
      margin-bottom: 1rem;
    }

    .software-header h4 {
      margin: 0;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--purple-dk);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .software-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 0.75rem;
    }

    .software-item {
      background: #fff;
      padding: 0.75rem;
      border-radius: 6px;
      border: 1px solid #e0e7ff;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      transition: transform 0.15s, box-shadow 0.15s;
    }

    .software-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .software-name {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--purple-dk);
      margin-bottom: 0.25rem;
    }

    .software-version {
      font-size: 0.7rem;
      color: var(--gold);
      font-weight: 600;
      margin-bottom: 0.25rem;
    }

    .software-category {
      display: inline-block;
      font-size: 0.65rem;
      color: var(--muted);
      background: #f0f0ff;
      padding: 0.2rem 0.5rem;
      border-radius: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      margin-bottom: 0.25rem;
    }

    .software-description {
      font-size: 0.7rem;
      color: var(--text);
      line-height: 1.3;
    }

    .btn-cancel-action {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background-color: #fee2e2;
        color: #dc2626;
        border: 1px solid #fecaca;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: 'DM Sans', sans-serif;
    }
    .btn-cancel-action:hover {
        background-color: #dc2626;
        color: #ffffff;
        border-color: #dc2626;
    }

    /* ── STATUS BADGES ── */
    .status-badge {
        display: inline-block;
        padding: 3px 11px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .status-active    { background: #dcfce7; color: #16a34a; }
    .status-completed { background: #ede9fe; color: #6d28d9; }
    .status-pending   { background: #fef3c7; color: #b45309; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }

    /* ── CANCEL CONFIRM MODAL ── */
    .cancel-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.45);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .cancel-modal-overlay.show { display: flex; }
    .cancel-modal-box {
        background: #fff;
        border-radius: 16px;
        width: 90%;
        max-width: 400px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        animation: popIn 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
    }
    @keyframes popIn {
        from { transform: scale(0.85); opacity: 0; }
        to   { transform: scale(1);   opacity: 1; }
    }
    .cancel-modal-header {
        background: #dc2626;
        color: #fff;
        padding: 1rem 1.4rem;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        font-size: 0.95rem;
    }
    .cancel-modal-body {
        padding: 1.5rem 1.4rem 1rem;
    }
    .cancel-modal-body p {
        margin: 0 0 0.4rem;
        font-size: 0.9rem;
        color: #374151;
        line-height: 1.5;
    }
    .cancel-modal-body .cancel-detail {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.7rem 1rem;
        margin-top: 0.8rem;
        font-size: 0.82rem;
        color: #6b7280;
    }
    .cancel-modal-body .cancel-detail strong {
        color: #374151;
    }
    .cancel-modal-footer {
        padding: 0.8rem 1.4rem 1.3rem;
        display: flex;
        gap: 0.6rem;
        justify-content: flex-end;
    }
    .btn-modal-keep {
        background: #f3f4f6;
        color: #374151;
        border: none;
        padding: 0.55rem 1.1rem;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }
    .btn-modal-keep:hover { background: #e5e7eb; }
    .btn-modal-confirm {
        background: #dc2626;
        color: #fff;
        border: none;
        padding: 0.55rem 1.2rem;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }
    .btn-modal-confirm:hover { background: #b91c1c; }

    /* hidden cancel form */
    #cancelForm { display: none; }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .two-col { grid-template-columns: 1fr; }
      .reservation-container { padding: 90px 1.5rem 32px; }
      .history-panel { grid-column: auto; }
      .software-list { grid-template-columns: 1fr; }
    }

    /* ══════════════════════════════════════
       DARK MODE — Reservation Page Overrides
    ══════════════════════════════════════ */

    /* ── Page title ── */
    [data-theme="dark"] .page-title { color: #ece8ff !important; }

    /* ── Alert banners ── */
    [data-theme="dark"] .alert-success {
        background: #052e16 !important;
        color: #4ade80 !important;
        border-color: #14532d !important;
    }
    [data-theme="dark"] .alert-error {
        background: #2d0a0a !important;
        color: #f87171 !important;
        border-color: #7f1d1d !important;
    }

    /* ── Panels ── */
    [data-theme="dark"] .res-panel {
        background: #1c1730 !important;
        box-shadow: 0 8px 32px rgba(0,0,0,0.5) !important;
    }
    [data-theme="dark"] .res-panel-header {
        background: #261e3d !important;
        border-bottom: 1px solid rgba(130,90,230,0.2) !important;
    }
    [data-theme="dark"] .res-panel-body {
        background: #1c1730 !important;
    }

    /* ── Form labels ── */
    [data-theme="dark"] .form-row label {
        color: #a89fbe !important;
    }

    /* ── Form inputs & selects ── */
    [data-theme="dark"] .form-row input,
    [data-theme="dark"] .form-row select {
        background: #261e3d !important;
        color: #ece8ff !important;
        border-color: #3b3060 !important;
    }
    [data-theme="dark"] .form-row input::placeholder {
        color: #6b6488 !important;
    }
    [data-theme="dark"] .form-row input:focus,
    [data-theme="dark"] .form-row select:focus {
        border-color: #9b72f5 !important;
        box-shadow: 0 0 0 3px rgba(155,114,245,0.15) !important;
    }

    /* ── Read-only inputs ── */
    [data-theme="dark"] .form-row input[readonly] {
        background: #1a1528 !important;
        color: #6b6488 !important;
        border-color: #2b2245 !important;
    }

    /* ── Form divider ── */
    [data-theme="dark"] .form-divider {
        border-top-color: #2b2245 !important;
    }

    /* ── Session row ── */
    [data-theme="dark"] .session-row {
        background: #211a35 !important;
        border-color: #e0a832 !important;
    }
    [data-theme="dark"] .session-label { color: #c4b5fd !important; }
    [data-theme="dark"] .session-badge {
        background: #261e3d !important;
        color: #e0a832 !important;
        border-color: #e0a832 !important;
    }

    /* ── Reserve button ── */
    [data-theme="dark"] .btn-reserve {
        background: #e0a832 !important;
        color: #1a1528 !important;
    }
    [data-theme="dark"] .btn-reserve:hover { background: #f0c050 !important; }
    [data-theme="dark"] .btn-reserve:disabled {
        background: #2b2245 !important;
        color: #6b6488 !important;
    }

    /* ── Lab tab buttons ── */
    [data-theme="dark"] .tab-btn {
        border-color: #9b72f5 !important;
        color: #9b72f5 !important;
        background: transparent !important;
    }
    [data-theme="dark"] .tab-btn:hover,
    [data-theme="dark"] .tab-btn.active {
        background: #6c3fc7 !important;
        color: #fff !important;
        border-color: #6c3fc7 !important;
    }

    /* ── Legend text ── */
    [data-theme="dark"] .legend-item { color: #7a7394 !important; }

    /* ── Row labels in PC grid ── */
    [data-theme="dark"] .row-label { color: #7a7394 !important; }
    [data-theme="dark"] .avail-count { color: #7a7394 !important; }

    /* ── PC states dark ── */
    [data-theme="dark"] .pc.available {
        background: #0d2b1a !important;
        border-color: #4CAF82 !important;
        color: #4ade80 !important;
    }
    [data-theme="dark"] .pc.occupied {
        background: #2d0a0a !important;
        border-color: #E05757 !important;
        color: #f87171 !important;
    }
    [data-theme="dark"] .pc.reserved {
        background: #2d1f00 !important;
        border-color: #C9952A !important;
        color: #fbbf24 !important;
    }
    [data-theme="dark"] .pc.selected {
        background: #6c3fc7 !important;
        border-color: #9b72f5 !important;
        color: #fff !important;
    }

    /* ── History table ── */
    [data-theme="dark"] .history-table th {
        background: #261e3d !important;
        color: #ece8ff !important;
        border-bottom-color: #e0a832 !important;
    }
    [data-theme="dark"] .history-table td {
        color: #cfc8e8 !important;
        border-bottom-color: #2b2245 !important;
    }
    [data-theme="dark"] .history-table tbody tr:hover td {
        background: #261e3d !important;
    }
    [data-theme="dark"] .history-table tbody tr:nth-child(even) td {
        background: #1a1528 !important;
    }

    /* ── Status badges dark ── */
    [data-theme="dark"] .status-active    { background: #052e16 !important; color: #4ade80 !important; }
    [data-theme="dark"] .status-completed { background: #2e1a4e !important; color: #c4b5fd !important; }
    [data-theme="dark"] .status-pending   { background: #2d2010 !important; color: #fbbf24 !important; }
    [data-theme="dark"] .status-cancelled { background: #2d0a0a !important; color: #f87171 !important; }

    /* ── Cancel action button dark ── */
    [data-theme="dark"] .btn-cancel-action {
        background-color: #2d0a0a !important;
        color: #f87171 !important;
        border-color: #7f1d1d !important;
    }
    [data-theme="dark"] .btn-cancel-action:hover {
        background-color: #dc2626 !important;
        color: #fff !important;
    }

    /* ── Software panel ── */
    [data-theme="dark"] .software-panel {
        background: #211a35 !important;
        border-color: #2b2245 !important;
    }
    [data-theme="dark"] .software-header h4 { color: #c4b5fd !important; }
    [data-theme="dark"] .software-item {
        background: #261e3d !important;
        border-color: #2b2245 !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
    }
    [data-theme="dark"] .software-item:hover {
        box-shadow: 0 4px 14px rgba(0,0,0,0.4) !important;
    }
    [data-theme="dark"] .software-name    { color: #ddd6f8 !important; }
    [data-theme="dark"] .software-version { color: #e0a832 !important; }
    [data-theme="dark"] .software-category {
        background: #1a1528 !important;
        color: #a89fbe !important;
    }
    [data-theme="dark"] .software-description { color: #9b93b8 !important; }

    /* ── Cancel confirm modal dark ── */
    [data-theme="dark"] .cancel-modal-box {
        background: #1c1730 !important;
        box-shadow: 0 20px 60px rgba(0,0,0,0.7) !important;
    }
    [data-theme="dark"] .cancel-modal-body p { color: #cfc8e8 !important; }
    [data-theme="dark"] .cancel-modal-body .cancel-detail {
        background: #211a35 !important;
        border-color: #2b2245 !important;
        color: #a89fbe !important;
    }
    [data-theme="dark"] .cancel-modal-body .cancel-detail strong {
        color: #ddd6f8 !important;
    }
    [data-theme="dark"] .cancel-modal-footer { border-top: 1px solid #2b2245; }
    [data-theme="dark"] .btn-modal-keep {
        background: #2b2245 !important;
        color: #a89fbe !important;
    }
    [data-theme="dark"] .btn-modal-keep:hover { background: #3b3060 !important; }

    /* ── Footer ── */
    [data-theme="dark"] footer {
        background: var(--footer-bg) !important;
        color: var(--footer-text) !important;
    }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav>
    <a href="dashboard.php" class="nav-brand">
      <img src="UClogo.png" alt="UC Logo">
      <span class="nav-title">College of Computer Studies<br>Lab Reservation</span>
    </a>
    <ul class="nav-links">
      <li><a href="dashboard.php">Home</a></li>
      <li><a href="edit_profile.php">Edit Profile</a></li>
      <li><a href="sit_in_summary.php">Sit-in Summary</a></li>
      <li><a href="history.php">History</a></li>
      <li><a href="user_reservation.php" class="active">Reservation</a></li>
      <li><a href="sessions.php">Session</a></li>
      <?php echo theme_toggle_button(); ?>
      <li><a href="logout.php" class="btn-nav">Log out</a></li>
    </ul>
  </nav>

  <!-- MAIN CONTENT -->
  <div class="reservation-container">
    <h1 class="page-title">Lab Computer Reservation</h1>

    <?php if ($pending): ?>
    <div class="alert alert-success">
      <span>⏳</span> Reservation request submitted! Awaiting admin approval. You'll be notified once it's confirmed.
    </div>
    <?php endif; ?>

    <?php if ($cancelled): ?>
    <div class="alert alert-success">
      <span>✓</span> Your reservation has been cancelled successfully.
    </div>
    <?php endif; ?>

    <?php if ($cancel_error): ?>
    <div class="alert alert-error">
      <span>✕</span> Could not cancel the reservation. It may have already been processed.
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
      <span>✓</span> Reservation successfully approved! Check your history for details.
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-error">
      <span>✕</span> <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>

    <div class="two-col">

      <!-- LEFT: FORM -->
      <div class="res-panel">
        <div class="res-panel-header">Student Reservation Form</div>
        <div class="res-panel-body">
          <form method="POST" onsubmit="return validateForm()">
            <input type="hidden" name="action" value="reserve">

            <div class="form-row">
              <label>ID Number</label>
              <input type="text" value="<?= htmlspecialchars($student['id_number']) ?>" readonly/>
            </div>

            <div class="form-row">
              <label>Student Name</label>
              <input type="text" value="<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>" readonly/>
            </div>

            <div class="form-row">
              <label>Course & Year</label>
              <input type="text" value="<?= htmlspecialchars($student['course'] . ' — Year ' . $student['course_level']) ?>" readonly/>
            </div>

            <div class="form-row">
              <label>Purpose *</label>
              <select name="purpose" id="purpose" required>
                <option value="">Select purpose</option>
                <option value="C Programming">C Programming</option>
                <option value="Java">Java</option>
                <option value="ASP.NET">ASP.NET</option>
                <option value="PHP">PHP</option>
                <option value="Database">Database</option>
                <option value="Research">Research</option>
                <option value="Project">Project</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="form-row">
              <label>Lab *</label>
              <select name="lab" id="labSelect" onchange="switchLab(this.value, null)" required>
                <?php foreach ($labs as $lab): ?>
                <option value="<?= htmlspecialchars($lab) ?>" <?= $lab === '524' ? 'selected' : '' ?>>Lab <?= htmlspecialchars($lab) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row">
              <label>Selected Computer *</label>
              <input type="text" id="selectedPcInput" name="pc" placeholder="Click a computer on the grid" readonly style="border-color: var(--gold);"/>
            </div>

            <hr class="form-divider"/>

            <div class="form-row-inline">
              <div class="form-row" style="margin-bottom:0">
                <label>Date *</label>
                <input type="date" name="date" id="dateIn" required/>
              </div>
              <div class="form-row" style="margin-bottom:0">
                <label>Time In *</label>
                <input type="time" name="time" id="timeIn" required/>
              </div>
            </div>

            <hr class="form-divider"/>

            <div class="session-row <?= !canReserve($student['remaining_sessions']) ? 'session-warning' : '' ?>">
              <span class="session-label">Remaining Sessions</span>
              <span class="session-badge">
                <span class="session-dot"></span>
                <span id="sessCount"><?= (int)$student['remaining_sessions'] ?? 30 ?></span>
              </span>
            </div>

            <button type="submit" class="btn-reserve" id="reserveBtn" <?= !canReserve($student['remaining_sessions']) ? 'disabled' : '' ?>>
              <?= canReserve($student['remaining_sessions']) ? 'Reserve Computer' : 'No Sessions Available' ?>
            </button>
          </form>
        </div>
      </div>

      <!-- RIGHT: LAB MAP -->
      <div class="res-panel">
        <div class="res-panel-header">Computer Availability — <span id="labLabel">Lab 524</span></div>
        <div class="res-panel-body">

          <div class="legend">
            <div class="legend-item">
              <div class="legend-dot" style="background:#E9F9EE; border-color:#4CAF82;"></div>Available
            </div>
            <div class="legend-item">
              <div class="legend-dot" style="background:#FDEAEA; border-color:#E05757;"></div>Occupied
            </div>
            <div class="legend-item">
              <div class="legend-dot" style="background:#FFF4DC; border-color:#C9952A;"></div>Reserved
            </div>
            <div class="legend-item">
              <div class="legend-dot" style="background:#4A1E8A; border-color:#2D0F5E;"></div>Your Pick
            </div>
          </div>

          <div class="lab-tabs" id="labTabs">
            <?php foreach ($labs as $lab): ?>
            <button class="tab-btn <?= $lab === '524' ? 'active' : '' ?>" onclick="switchLab('<?= htmlspecialchars($lab) ?>', this)">Lab <?= htmlspecialchars($lab) ?></button>
            <?php endforeach; ?>
          </div>

          <div id="computerGrid"></div>
          <div class="avail-count" id="availCount"></div>

          <!-- SOFTWARE AVAILABILITY PANEL -->
          <div class="software-panel">
            <div class="software-header">
              <h4>🛠️ Software Available in Lab <span id="softwareLabLabel">524</span></h4>
            </div>
            <div id="softwareList" class="software-list">
              <!-- Software items will be populated by JavaScript -->
            </div>
          </div>

        </div>
      </div>

    </div>


    <div class="res-panel history-panel">
      <div class="res-panel-header">Your Recent Sit-In</div>
      <div class="res-panel-body">
        <?php if (empty($reservations)): ?>
        <div class="empty-history">
          <p>No reservations yet. Make your first reservation above!</p>
        </div>
        <?php else: ?>
        <table class="history-table">
  <thead>
    <tr>
      <th>Date</th>
      <th>Time</th>
      <th>Lab</th>
      <th>Purpose</th>
      <th>Status</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($reservations as $res): ?>
    <tr>
      <td><?= date('M d, Y', strtotime($res['login_time'])) ?></td>
      <td><?= date('h:i A', strtotime($res['login_time'])) ?></td>
      <td>Lab <?= htmlspecialchars($res['lab_number']) ?></td>
      <td><?= htmlspecialchars($res['purpose']) ?></td>
      <td>
        <?php
          $s = strtolower(trim($res['status'] ?? ''));
          // Empty or unrecognised = treat as pending
          if ($s === '' || !in_array($s, ['active','completed','cancelled','pending'])) $s = 'pending';
          $badge_class = match($s) {
              'active'    => 'status-active',
              'completed' => 'status-completed',
              'cancelled' => 'status-cancelled',
              default     => 'status-pending',
          };
          $badge_label = match($s) {
              'active'    => 'Active',
              'completed' => 'Completed',
              'cancelled' => 'Cancelled',
              default     => 'Pending',
          };
        ?>
        <span class="status-badge <?= $badge_class ?>"><?= $badge_label ?></span>
      </td>
      <td>
        <?php $show_cancel = ($s !== 'completed' && $s !== 'cancelled'); ?>
        <?php if ($show_cancel): ?>
          <button type="button" class="btn-cancel-action"
            onclick="openCancelModal(
              <?= (int)$res['id'] ?>,
              '<?= htmlspecialchars(addslashes($res['purpose'])) ?>',
              'Lab <?= htmlspecialchars($res['lab_number']) ?>',
              '<?= date('M d, Y', strtotime($res['login_time'])) ?>'
            )">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Cancel
          </button>
        <?php else: ?>
          <span style="color: #9ca3af; font-size: 0.72rem;">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
        <?php endif; ?>
      </div><!-- /.res-panel-body -->
    </div><!-- /.res-panel history-panel -->

  </div><!-- /.reservation-container -->

  <!-- HIDDEN CANCEL FORM (submitted by modal) -->
  <form method="POST" id="cancelForm">
    <input type="hidden" name="cancel_reservation_id" id="cancelResId">
  </form>

  <!-- CANCEL CONFIRMATION MODAL -->
  <div class="cancel-modal-overlay" id="cancelModal">
    <div class="cancel-modal-box">
      <div class="cancel-modal-header">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        Cancel Reservation
      </div>
      <div class="cancel-modal-body">
        <p>Are you sure you want to cancel this reservation? This action cannot be undone.</p>
        <div class="cancel-detail">
          <div><strong>Purpose:</strong> <span id="cancelDetailPurpose"></span></div>
          <div><strong>Lab:</strong> <span id="cancelDetailLab"></span></div>
          <div><strong>Date:</strong> <span id="cancelDetailDate"></span></div>
        </div>
      </div>
      <div class="cancel-modal-footer">
        <button type="button" class="btn-modal-keep" onclick="closeCancelModal()">Keep Reservation</button>
        <button type="button" class="btn-modal-confirm" onclick="confirmCancel()">Yes, Cancel It</button>
      </div>
    </div>
  </div>

  <!-- TOAST NOTIFICATION -->
  <div class="toast" id="toast"></div>

  <script>
    // ── GENERATE LAB DATA FROM DATABASE ──
    const labsData = {};
    const labsSoftware = {};
    <?php foreach ($labs as $lab): ?>
    labsData['<?= $lab ?>'] = <?php
      $states = getLabComputerStates($pdo, $lab);
      echo json_encode($states);
    ?>;
    labsSoftware['<?= $lab ?>'] = <?php
      $software = getLabSoftware($pdo, $lab);
      echo json_encode($software);
    ?>;
    <?php endforeach; ?>

    let currentLab = '524';
    let selectedPc = null;

    // ── RENDER COMPUTER GRID ──
    function renderGrid(lab) {
      const states = labsData[lab];
      const grid = document.getElementById('computerGrid');
      grid.innerHTML = '';

      const rows = [
        { label: 'Row A — PCs 1–10',  from: 0,  to: 10 },
        { label: 'Row B — PCs 11–20', from: 10, to: 20 },
        { label: 'Row C — PCs 21–30', from: 20, to: 30 },
        { label: 'Row D — PCs 31–40', from: 30, to: 40 },
        { label: 'Row E — PCs 41–50', from: 40, to: 50 },
      ];

      rows.forEach(row => {
        const group = document.createElement('div');
        group.className = 'row-group';

        const lbl = document.createElement('div');
        lbl.className = 'row-label';
        lbl.textContent = row.label;
        group.appendChild(lbl);

        const cg = document.createElement('div');
        cg.className = 'computer-grid';

        for (let i = row.from; i < row.to; i++) {
          const num = i + 1;
          const state = states[i];
          const isSelected = selectedPc === num && currentLab === lab;

          const pc = document.createElement('div');
          pc.className = 'pc ' + (isSelected ? 'selected' : state);
          pc.textContent = num;
          pc.title = 'PC ' + num + ' — ' + (isSelected ? 'Selected' : state.charAt(0).toUpperCase() + state.slice(1));

          if (state === 'available' || isSelected) {
            pc.addEventListener('click', () => selectPc(num));
          }

          cg.appendChild(pc);
        }

        group.appendChild(cg);
        grid.appendChild(group);
      });

      const avail = states.filter(s => s === 'available').length;
      document.getElementById('availCount').textContent = avail + ' of 50 computers available';
    }

    // ── SELECT A COMPUTER ──
    function selectPc(num) {
      selectedPc = (selectedPc === num) ? null : num;
      document.getElementById('selectedPcInput').value =
        selectedPc ? 'Lab ' + currentLab + ' — PC ' + selectedPc : '';
      renderGrid(currentLab);
    }

    // ── RENDER SOFTWARE LIST ──
    function renderSoftware(lab) {
      const software = labsSoftware[lab];
      const container = document.getElementById('softwareList');
      container.innerHTML = '';

      if (!software || software.length === 0) {
        container.innerHTML = '<div class="software-item"><div class="software-name">No software information available</div></div>';
        return;
      }

      software.forEach(item => {
        const softwareDiv = document.createElement('div');
        softwareDiv.className = 'software-item';

        softwareDiv.innerHTML = `
          <div class="software-name">${item.software_name}</div>
          <div class="software-version">v${item.version}</div>
          <div class="software-category">${item.category}</div>
          <div class="software-description">${item.description}</div>
        `;

        container.appendChild(softwareDiv);
      });
    }

    // ── SWITCH LAB ──
    function switchLab(lab, btn) {
      currentLab = lab;
      document.getElementById('labSelect').value = lab;
      document.getElementById('labLabel').textContent = 'Lab ' + lab;
      document.getElementById('softwareLabLabel').textContent = lab;

      if(btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      }

      selectedPc = null;
      document.getElementById('selectedPcInput').value = '';
      renderGrid(lab);
      renderSoftware(lab);
    }

    // ── TOAST HELPER ──
    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 3200);
    }

    // ── VALIDATE FORM ──
    function validateForm() {
      const purpose = document.getElementById('purpose').value.trim();
      const date = document.getElementById('dateIn').value;
      const time = document.getElementById('timeIn').value;

      if (!purpose) { showToast('Please select a purpose.'); return false; }
      if (!selectedPc) { showToast('Please select a computer from the grid.'); return false; }
      if (!date) { showToast('Please select a date.'); return false; }
      if (!time) { showToast('Please enter a time.'); return false; }

      return true;
    }

    // ── INIT ──
    renderGrid('524');
    renderSoftware('524');
    const now = new Date();
    document.getElementById('dateIn').value = now.toISOString().split('T')[0];
    document.getElementById('timeIn').value =
      now.getHours().toString().padStart(2, '0') + ':' +
      now.getMinutes().toString().padStart(2, '0');
    // ── CANCEL MODAL ──
    function openCancelModal(id, purpose, lab, date) {
      document.getElementById('cancelResId').value   = id;
      document.getElementById('cancelDetailPurpose').textContent = purpose;
      document.getElementById('cancelDetailLab').textContent     = lab;
      document.getElementById('cancelDetailDate').textContent    = date;
      document.getElementById('cancelModal').classList.add('show');
    }
    function closeCancelModal() {
      document.getElementById('cancelModal').classList.remove('show');
    }
    function confirmCancel() {
      document.getElementById('cancelForm').submit();
    }
    // Close modal on overlay click
    document.getElementById('cancelModal').addEventListener('click', function(e) {
      if (e.target === this) closeCancelModal();
    });

  </script>
<?php echo theme_script(); ?>
</body>
</html>