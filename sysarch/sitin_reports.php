<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// ── FILTER PARAMS ──
$date_from  = $_GET['date_from']  ?? date('Y-01-01');
$date_to    = $_GET['date_to']    ?? date('Y-12-31');
$purpose    = $_GET['purpose']    ?? '';
$lab        = $_GET['lab']        ?? '';
$status     = $_GET['status']     ?? '';
$generated  = isset($_GET['generate']);

// ── FETCH DISTINCT PURPOSES & LABS FOR DROPDOWNS ──
$all_purposes = $pdo->query("SELECT DISTINCT purpose FROM sit_in_records WHERE purpose IS NOT NULL AND purpose != '' ORDER BY purpose")->fetchAll(PDO::FETCH_COLUMN);
$all_labs     = $pdo->query("SELECT DISTINCT lab_number FROM sit_in_records WHERE lab_number IS NOT NULL ORDER BY lab_number")->fetchAll(PDO::FETCH_COLUMN);

// ── FETCH REPORT DATA ──
$records = [];
if ($generated) {
    $sql = "
        SELECT 
            s.id,
            u.id_number,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            u.course,
            u.course_level AS year,
            s.purpose,
            s.lab_number,
            DATE(s.login_time) AS sit_date,
            s.login_time,
            s.logout_time,
            CASE
                WHEN s.logout_time IS NOT NULL
                THEN CONCAT(FLOOR(TIMESTAMPDIFF(MINUTE, s.login_time, s.logout_time)/60), 'h ',
                            MOD(TIMESTAMPDIFF(MINUTE, s.login_time, s.logout_time), 60), 'm')
                ELSE '—'
            END AS duration,
            u.remaining_sessions AS points,
            s.status
        FROM sit_in_records s
        JOIN users u ON s.id_number = u.id_number
        WHERE DATE(s.login_time) BETWEEN ? AND ?
    ";
    $params = [$date_from, $date_to];

    if ($purpose !== '') { $sql .= " AND s.purpose = ?";    $params[] = $purpose; }
    if ($lab     !== '') { $sql .= " AND s.lab_number = ?"; $params[] = $lab; }
    if ($status  !== '') { $sql .= " AND s.status = ?";     $params[] = $status; }

    $sql .= " ORDER BY s.login_time DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
}

// ── EXPORT HANDLERS ──
$export = $_GET['export'] ?? '';
if (in_array($export, ['csv', 'excel', 'pdf']) && $generated) {

    if ($export === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sitin_report_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','ID Number','Name','Course','Year','Purpose','Lab','Date','Time In','Time Out','Duration','Points','Status']);
        foreach ($records as $r) {
            fputcsv($out, [
                $r['id'], $r['id_number'], $r['full_name'], $r['course'], $r['year'],
                $r['purpose'], $r['lab_number'], $r['sit_date'],
                date('h:i A', strtotime($r['login_time'])),
                $r['logout_time'] ? date('h:i A', strtotime($r['logout_time'])) : '—',
                $r['duration'], $r['points'], ucfirst($r['status'])
            ]);
        }
        fclose($out);
        exit;
    }

    if ($export === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="sitin_report_' . date('Ymd') . '.xls"');
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<style>body{font-family:Arial,sans-serif;font-size:11pt;}
              table{border-collapse:collapse;width:100%;}
              th{background:#3d2b5e;color:#fff;padding:8px 10px;text-align:left;font-size:10pt;}
              td{padding:7px 10px;border-bottom:1px solid #e8e6f0;font-size:10pt;}
              tr:nth-child(even) td{background:#f9f8fb;}
              .title{font-size:16pt;font-weight:bold;color:#2d1b4e;margin-bottom:6px;}
              .subtitle{font-size:10pt;color:#6b6480;margin-bottom:14px;}
        </style>';
        echo '<p class="title">UC CCS — Sit-in Reports</p>';
        echo '<p class="subtitle">Period: ' . htmlspecialchars($date_from) . ' to ' . htmlspecialchars($date_to) . ' &nbsp;|&nbsp; Generated: ' . date('F j, Y') . '</p>';
        echo '<table>';
        echo '<tr><th>ID</th><th>ID Number</th><th>Name</th><th>Course</th><th>Year</th><th>Purpose</th><th>Lab</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Duration</th><th>Points</th><th>Status</th></tr>';
        foreach ($records as $r) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($r['id']) . '</td>';
            echo '<td>' . htmlspecialchars($r['id_number']) . '</td>';
            echo '<td>' . htmlspecialchars($r['full_name']) . '</td>';
            echo '<td>' . htmlspecialchars($r['course']) . '</td>';
            echo '<td>' . htmlspecialchars($r['year']) . '</td>';
            echo '<td>' . htmlspecialchars($r['purpose']) . '</td>';
            echo '<td>' . htmlspecialchars($r['lab_number']) . '</td>';
            echo '<td>' . htmlspecialchars($r['sit_date']) . '</td>';
            echo '<td>' . date('h:i A', strtotime($r['login_time'])) . '</td>';
            echo '<td>' . ($r['logout_time'] ? date('h:i A', strtotime($r['logout_time'])) : '—') . '</td>';
            echo '<td>' . htmlspecialchars($r['duration']) . '</td>';
            echo '<td>' . htmlspecialchars($r['points']) . '</td>';
            echo '<td>' . ucfirst(htmlspecialchars($r['status'])) . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    if ($export === 'pdf') {
        // Build a print-ready HTML page for PDF (landscape via CSS)
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Sit-in Report</title>
        <style>
          @page { size: A4 landscape; margin: 18mm 14mm; }
          @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
          body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #1e1b2e; margin: 0; padding: 0; }
          .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2.5px solid #4a1e8a; padding-bottom: 10px; margin-bottom: 14px; }
          .header-title { font-size: 17pt; font-weight: bold; color: #2d1b4e; }
          .header-sub { font-size: 9pt; color: #6b6480; margin-top: 3px; }
          .header-meta { text-align: right; font-size: 8.5pt; color: #6b6480; }
          table { width: 100%; border-collapse: collapse; margin-top: 0; font-size: 8.5pt; }
          th { background: #3d2b5e; color: #fff; padding: 7px 7px; text-align: left; font-size: 8pt; letter-spacing: .03em; }
          td { padding: 6px 7px; border-bottom: 1px solid #e8e6f0; }
          tr:nth-child(even) td { background: #f9f8fb; }
          .badge-active { background: #dcfce7; color: #166534; padding: 2px 7px; border-radius: 20px; font-size: 7.5pt; font-weight: 700; }
          .badge-completed { background: #f3f4f6; color: #374151; padding: 2px 7px; border-radius: 20px; font-size: 7.5pt; font-weight: 700; }
          .badge-pending { background: #fef9c3; color: #854d0e; padding: 2px 7px; border-radius: 20px; font-size: 7.5pt; font-weight: 700; }
          .footer { margin-top: 18px; font-size: 7.5pt; color: #8b84a0; text-align: center; border-top: 1px solid #e8e6f0; padding-top: 8px; }
          .no-data { text-align: center; padding: 2.5rem; color: #8b84a0; font-size: 10pt; }
        </style>
        </head><body onload="window.print()">
        <div class="header">
          <div>
            <div class="header-title">&#x1F4CA; UC CCS — Sit-in Reports</div>
            <div class="header-sub">University of Cebu — College of Computer Studies | SIT-IN Monitoring System</div>
          </div>
          <div class="header-meta">
            <strong>Period:</strong> ' . htmlspecialchars($date_from) . ' to ' . htmlspecialchars($date_to) . '<br>
            <strong>Generated:</strong> ' . date('F j, Y \a\t h:i A') . '<br>
            <strong>Total Records:</strong> ' . count($records) . '
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>#</th><th>ID Number</th><th>Name</th><th>Course</th><th>Yr</th>
              <th>Purpose</th><th>Lab</th><th>Date</th><th>Time In</th><th>Time Out</th>
              <th>Duration</th><th>Points</th><th>Status</th>
            </tr>
          </thead>
          <tbody>';
        if (empty($records)) {
            echo '<tr><td colspan="13" class="no-data">No records found for the selected filters.</td></tr>';
        } else {
            foreach ($records as $i => $r) {
                $statusBadge = match(strtolower($r['status'])) {
                    'active'    => '<span class="badge-active">Active</span>',
                    'completed' => '<span class="badge-completed">Completed</span>',
                    default     => '<span class="badge-pending">' . ucfirst(htmlspecialchars($r['status'])) . '</span>'
                };
                echo '<tr>
                    <td>' . ($i+1) . '</td>
                    <td>' . htmlspecialchars($r['id_number']) . '</td>
                    <td>' . htmlspecialchars($r['full_name']) . '</td>
                    <td>' . htmlspecialchars($r['course']) . '</td>
                    <td>' . htmlspecialchars($r['year']) . '</td>
                    <td>' . htmlspecialchars($r['purpose']) . '</td>
                    <td>' . htmlspecialchars($r['lab_number']) . '</td>
                    <td>' . htmlspecialchars($r['sit_date']) . '</td>
                    <td>' . date('h:i A', strtotime($r['login_time'])) . '</td>
                    <td>' . ($r['logout_time'] ? date('h:i A', strtotime($r['logout_time'])) : '—') . '</td>
                    <td>' . htmlspecialchars($r['duration']) . '</td>
                    <td>' . htmlspecialchars($r['points']) . '</td>
                    <td>' . $statusBadge . '</td>
                </tr>';
            }
        }
        echo '</tbody></table>
        <div class="footer">&copy; ' . date('Y') . ' University of Cebu — SIT-IN Monitoring System | Admin Panel</div>
        </body></html>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sit-in Reports — UC CCS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="adminDashboard.css"/>
  <style>
    /* ══════════════════════════════════════
       THEME CSS VARIABLES (mirrored from admin_dashboard.php)
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
      --bg-modal:         #ffffff;
      --text-primary:     #1e1b2e;
      --text-secondary:   #4a4560;
      --text-muted:       #8b84a0;
      --text-nav:         rgba(255,255,255,0.80);
      --text-nav-active:  #ffffff;
      --text-label:       #6b6480;
      --text-val:         #1e1b2e;
      --text-table:       #2d2444;
      --text-heading:     #1e1b2e;
      --border:           #e8e6f0;
      --border-panel:     rgba(74,30,138,0.08);
      --shadow-panel:     0 4px 24px rgba(74,30,138,0.08);
      --shadow-nav:       0 2px 12px rgba(45,27,78,0.15);
      --shadow-card:      0 2px 12px rgba(74,30,138,0.07);
      --accent:           #4a1e8a;
      --accent-mid:       #8b5cf6;
      --accent-light:     #f3f0ff;
      --accent-gold:      #c9a227;
      --footer-bg:        #2d1b4e;
      --footer-text:      rgba(255,255,255,0.5);
      --status-active-bg: #dcfce7;
      --status-active-text:#166534;
      --status-done-bg:   #f3f4f6;
      --status-done-text: #374151;
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
      --bg-modal:         #1e1830;
      --text-primary:     #f0ecff;
      --text-secondary:   #c9c1e0;
      --text-muted:       #8b84a0;
      --text-nav:         rgba(220,210,255,0.75);
      --text-nav-active:  #f0ecff;
      --text-label:       #a89fbe;
      --text-val:         #e8e0ff;
      --text-table:       #d4cce8;
      --text-heading:     #f0ecff;
      --border:           #2e2545;
      --border-panel:     rgba(120,80,220,0.15);
      --shadow-panel:     0 4px 24px rgba(0,0,0,0.4);
      --shadow-nav:       0 2px 16px rgba(0,0,0,0.5);
      --shadow-card:      0 2px 12px rgba(0,0,0,0.3);
      --accent:           #9b6ef3;
      --accent-mid:       #b08af8;
      --accent-light:     #2a2040;
      --accent-gold:      #d4a940;
      --footer-bg:        #0d0a18;
      --footer-text:      rgba(200,190,240,0.45);
      --status-active-bg: #052e16;
      --status-active-text:#4ade80;
      --status-done-bg:   #1f2937;
      --status-done-text: #9ca3af;
    }

    *, *::before, *::after {
      transition: background-color 0.25s ease, color 0.25s ease, border-color 0.25s ease;
    }

    html, body {
      height: 100%;
      margin: 0;
    }
    body {
      background: var(--bg-body);
      color: var(--text-primary);
      font-family: 'DM Sans', sans-serif;
      margin: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .reports-page {
      flex: 1;
    }

    /* ── THEME TOGGLE ── */
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
      white-space: nowrap;
    }
    .theme-toggle-btn:hover { background: rgba(255,255,255,0.22); transform: translateY(-1px); }
    .theme-toggle-btn .toggle-icon { font-size: 1rem; }

    /* ── PAGE WRAPPER ── */
    .reports-page {
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem 2rem 4rem;
      flex: 1;
    }

    /* ── PAGE TITLE ── */
    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
    }
    .page-title-group {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .page-title-icon {
      width: 40px;
      height: 40px;
      color: var(--accent);
      flex-shrink: 0;
    }
    .page-title-group h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--text-heading);
      margin: 0;
    }
    .page-date {
      background: var(--bg-panel);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 0.5rem 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-secondary);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: var(--shadow-card);
    }

    /* ── FILTER PANEL ── */
    .filter-panel {
      background: var(--bg-panel);
      border: 1px solid var(--border-panel);
      border-radius: 16px;
      box-shadow: var(--shadow-panel);
      padding: 1.5rem 1.75rem 1.75rem;
      margin-bottom: 1.5rem;
    }
    .filter-panel-title {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--text-label);
      margin-bottom: 1.1rem;
    }
    .filter-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
      gap: 1rem;
      align-items: end;
    }
    .filter-field label {
      display: block;
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-label);
      margin-bottom: 0.35rem;
    }
    .filter-field input,
    .filter-field select {
      width: 100%;
      box-sizing: border-box;
      padding: 0.55rem 0.85rem;
      border: 1.5px solid var(--border);
      border-radius: 9px;
      font-size: 0.875rem;
      font-family: 'DM Sans', sans-serif;
      background: var(--bg-input);
      color: var(--text-primary);
      outline: none;
      appearance: none;
      -webkit-appearance: none;
    }
    .filter-field input:focus,
    .filter-field select:focus {
      border-color: var(--accent-mid);
      box-shadow: 0 0 0 3px rgba(139,92,246,0.1);
    }
    .filter-field select {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238b84a0'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1rem;
      padding-right: 2.4rem;
    }
    .filter-actions {
      display: flex;
      gap: 0.6rem;
      align-items: flex-end;
    }
    .btn-generate {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      padding: 0.6rem 1.4rem;
      background: linear-gradient(135deg, var(--accent), var(--accent-mid));
      color: #fff;
      border: none;
      border-radius: 9px;
      font-size: 0.875rem;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      white-space: nowrap;
      box-shadow: 0 3px 12px rgba(74,30,138,0.25);
      transition: transform 0.15s, box-shadow 0.15s;
    }
    .btn-generate:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(74,30,138,0.35); }
    .btn-reset {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      padding: 0.6rem 1.1rem;
      background: var(--bg-input);
      color: var(--text-secondary);
      border: 1.5px solid var(--border);
      border-radius: 9px;
      font-size: 0.875rem;
      font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      white-space: nowrap;
      text-decoration: none;
      transition: background 0.15s, border-color 0.15s;
    }
    .btn-reset:hover { background: var(--bg-table-hover); border-color: var(--accent-mid); }

    /* ── EXPORT + TABLE PANEL ── */
    .results-panel {
      background: var(--bg-panel);
      border: 1px solid var(--border-panel);
      border-radius: 16px;
      box-shadow: var(--shadow-panel);
      overflow: hidden;
    }
    .results-toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.6rem;
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--border);
    }
    .results-count {
      margin-right: auto;
      font-size: 0.82rem;
      color: var(--text-muted);
      font-weight: 500;
    }
    .results-count strong { color: var(--text-heading); }
    .btn-export {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 1.1rem;
      border: none;
      border-radius: 8px;
      font-size: 0.8rem;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      text-decoration: none;
      transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
    }
    .btn-export:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .btn-export-csv   { background: #16a34a; color: #fff; }
    .btn-export-excel { background: #15803d; color: #fff; }
    .btn-export-pdf   { background: #dc2626; color: #fff; }
    .btn-export.disabled { opacity: 0.45; pointer-events: none; cursor: not-allowed; }

    /* ── TABLE ── */
    .report-table-wrap {
      overflow-x: auto;
    }
    .report-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 900px;
    }
    .report-table thead th {
      background: var(--bg-table-head);
      color: var(--text-label);
      padding: 0.7rem 1rem;
      text-align: left;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 2px solid var(--border);
      white-space: nowrap;
    }
    .report-table tbody tr td {
      padding: 0.7rem 1rem;
      font-size: 0.85rem;
      color: var(--text-table);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    .report-table tbody tr:nth-child(even) td { background: var(--bg-table-alt); }
    .report-table tbody tr:hover td { background: var(--bg-table-hover); }
    .report-table tbody tr:last-child td { border-bottom: none; }

    /* Status badges */
    .badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 700;
    }
    .badge-active    { background: var(--status-active-bg); color: var(--status-active-text); }
    .badge-completed { background: var(--status-done-bg);   color: var(--status-done-text); }
    .badge-pending   { background: #fef9c3; color: #854d0e; }

    /* Empty state */
    .empty-state-row td {
      text-align: center;
      padding: 4rem 1rem;
    }
    .empty-icon {
      width: 52px;
      height: 52px;
      color: var(--text-muted);
      margin: 0 auto 1rem;
      opacity: 0.5;
    }
    .empty-state-row h3 { color: var(--text-heading); margin: 0 0 0.4rem; font-size: 1rem; }
    .empty-state-row p  { color: var(--text-muted); margin: 0; font-size: 0.85rem; }

    /* ── NAVBAR FORCE OVERRIDE ── */
    nav {
      background: #2d1b4e !important;
      box-shadow: 0 2px 12px rgba(45,27,78,0.15) !important;
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      padding: 0 2rem !important;
      position: sticky !important;
      top: 0 !important;
      z-index: 1000 !important;
      min-height: 60px !important;
    }
    [data-theme="dark"] nav {
      background: #0d0a18 !important;
    }
    nav .nav-brand {
      display: flex !important;
      align-items: center !important;
      gap: 0.75rem !important;
      text-decoration: none !important;
    }
    nav .nav-brand img {
      height: 38px !important;
      width: auto !important;
    }
    nav .nav-title {
      font-family: 'DM Sans', sans-serif !important;
      font-size: 0.78rem !important;
      font-weight: 700 !important;
      color: #ffffff !important;
      line-height: 1.3 !important;
    }
    nav .nav-links {
      display: flex !important;
      align-items: center !important;
      gap: 0.25rem !important;
      list-style: none !important;
      margin: 0 !important;
      padding: 0 !important;
    }
    nav .nav-links li a {
      color: rgba(255,255,255,0.80) !important;
      text-decoration: none !important;
      font-size: 0.82rem !important;
      font-weight: 500 !important;
      padding: 0.45rem 0.75rem !important;
      border-radius: 8px !important;
      transition: background 0.18s, color 0.18s !important;
      white-space: nowrap !important;
    }
    nav .nav-links li a:hover {
      background: rgba(255,255,255,0.1) !important;
      color: #ffffff !important;
    }
    nav .nav-links li a.active {
      background: rgba(255,255,255,0.15) !important;
      color: #ffffff !important;
      font-weight: 700 !important;
    }
    nav .nav-links li a.btn-nav {
      background: #ef4444 !important;
      color: #fff !important;
      border-radius: 8px !important;
      font-weight: 700 !important;
      padding: 0.45rem 1rem !important;
    }
    nav .nav-links li a.btn-nav:hover {
      background: #dc2626 !important;
    }

    /* ── FOOTER FORCE OVERRIDE ── */
    footer {
      background: #2d1b4e !important;
      color: rgba(255,255,255,0.5) !important;
      text-align: center !important;
      padding: 1.1rem !important;
      font-size: 0.8rem !important;
      margin-top: 0 !important;
    }
    [data-theme="dark"] footer {
      background: #0d0a18 !important;
      color: rgba(200,190,240,0.45) !important;
    }

    /* Responsive */
    @media (max-width: 1100px) {
      .filter-grid { grid-template-columns: 1fr 1fr 1fr; }
    }
    @media (max-width: 768px) {
      .filter-grid { grid-template-columns: 1fr 1fr; }
      .reports-page { padding: 1rem 1rem 3rem; }
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
    <li><a href="admin_dashboard.php?tab=students">Students</a></li>
    <li><a href="admin_dashboard.php?tab=reservations">Reservations</a></li>
    <li><a href="admin_dashboard.php?tab=sitin">Sit-in</a></li>
    <li><a href="admin_dashboard.php?tab=records">View Sit-in Records</a></li>
    <li><a href="sitin_reports.php" class="active">Sit-in Reports</a></li>
    <li><a href="admin_dashboard.php?tab=feedback">Feedback Reports</a></li>
    
    <li>
      <button class="theme-toggle-btn" id="themeToggle" title="Toggle Dark/Light Mode">
        <span class="toggle-icon" id="toggleIcon">🌙</span>
        <span id="toggleLabel">Dark</span>
      </button>
    </li>
    <li><a href="admin_logout.php" class="btn-nav">Log out</a></li>
  </ul>
</nav>

<div class="reports-page">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-title-group">
      <svg class="page-title-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
      </svg>
      <h1>Sit-in Reports</h1>
    </div>
    <div class="page-date">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <?= date('M d, Y') ?>
    </div>
  </div>

  <!-- Filter Panel -->
  <div class="filter-panel">
    <div class="filter-panel-title">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:14px;height:14px;">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
      </svg>
      Filter Reports
    </div>
    <form method="GET" action="sitin_reports.php">
      <input type="hidden" name="generate" value="1">
      <div class="filter-grid">
        <div class="filter-field">
          <label>Date From</label>
          <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="filter-field">
          <label>Date To</label>
          <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="filter-field">
          <label>Purpose</label>
          <select name="purpose">
            <option value="">All Purposes</option>
            <?php foreach ($all_purposes as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>" <?= $purpose === $p ? 'selected' : '' ?>>
              <?= htmlspecialchars($p) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-field">
          <label>Laboratory</label>
          <select name="lab">
            <option value="">All Labs</option>
            <?php foreach ($all_labs as $l): ?>
            <option value="<?= htmlspecialchars($l) ?>" <?= $lab === $l ? 'selected' : '' ?>>
              Lab <?= htmlspecialchars($l) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-field">
          <label>Status</label>
          <select name="status">
            <option value="">All Status</option>
            <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pending</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:0.6rem;margin-top:1.1rem;justify-content:center;">
        <a href="sitin_reports.php" class="btn-reset">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:14px;height:14px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
          Reset
        </a>
        <button type="submit" class="btn-generate">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:15px;height:15px;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          Generate
        </button>
      </div>
    </form>
  </div>

  <!-- Results Panel -->
  <div class="results-panel">
    <div class="results-toolbar">
      <?php if ($generated): ?>
      <div class="results-count">
        Showing <strong><?= count($records) ?></strong> record<?= count($records) !== 1 ? 's' : '' ?>
      </div>
      <?php else: ?>
      <div class="results-count">Set filters above and click <strong>Generate</strong> to view records.</div>
      <?php endif; ?>

      <?php
        // Build export query string preserving filters
        $exportBase = http_build_query([
          'date_from' => $date_from, 'date_to' => $date_to,
          'purpose'   => $purpose,   'lab'      => $lab,
          'status'    => $status,    'generate' => '1'
        ]);
        $canExport = $generated && count($records) > 0;
      ?>

      <a href="sitin_reports.php?<?= $exportBase ?>&export=csv"
         class="btn-export btn-export-csv <?= $canExport ? '' : 'disabled' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:14px;height:14px;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        </svg>
        Export CSV
      </a>
      <a href="sitin_reports.php?<?= $exportBase ?>&export=excel"
         class="btn-export btn-export-excel <?= $canExport ? '' : 'disabled' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:14px;height:14px;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
        </svg>
        Export Excel
      </a>
      <a href="sitin_reports.php?<?= $exportBase ?>&export=pdf"
         class="btn-export btn-export-pdf <?= $canExport ? '' : 'disabled' ?>" target="_blank">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:14px;height:14px;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
        </svg>
        Export PDF
      </a>
    </div>

    <!-- Table -->
    <div class="report-table-wrap">
      <table class="report-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ID Number</th>
            <th>Name</th>
            <th>Course</th>
            <th>Year</th>
            <th>Purpose</th>
            <th>Lab</th>
            <th>Date</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Duration</th>
            <th>Points</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$generated): ?>
          <tr class="empty-state-row">
            <td colspan="13">
              <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
              </svg>
              <h3>No report generated</h3>
              <p>Adjust your filters and click <strong>Generate</strong> to load records.</p>
            </td>
          </tr>
          <?php elseif (empty($records)): ?>
          <tr class="empty-state-row">
            <td colspan="13">
              <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
              </svg>
              <h3>No data available</h3>
              <p>Try adjusting your filters or date range.</p>
            </td>
          </tr>
          <?php else: ?>
            <?php foreach ($records as $i => $r):
              $statusClass = match(strtolower($r['status'])) {
                'active'    => 'badge-active',
                'completed' => 'badge-completed',
                default     => 'badge-pending'
              };
            ?>
            <tr>
              <td><?= htmlspecialchars($r['id']) ?></td>
              <td><?= htmlspecialchars($r['id_number']) ?></td>
              <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
              <td><?= htmlspecialchars($r['course']) ?></td>
              <td><?= htmlspecialchars($r['year']) ?></td>
              <td><?= htmlspecialchars($r['purpose']) ?></td>
              <td><?= htmlspecialchars($r['lab_number']) ?></td>
              <td><?= htmlspecialchars($r['sit_date']) ?></td>
              <td><?= date('h:i A', strtotime($r['login_time'])) ?></td>
              <td><?= $r['logout_time'] ? date('h:i A', strtotime($r['logout_time'])) : '—' ?></td>
              <td><?= htmlspecialchars($r['duration']) ?></td>
              <td><?= htmlspecialchars($r['points']) ?></td>
              <td><span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($r['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.reports-page -->

<footer style="background:#2d1b4e !important;color:rgba(255,255,255,0.5) !important;text-align:center;padding:1.1rem;font-size:0.8rem;font-family:'DM Sans',sans-serif;">
  &copy; <?= date('Y') ?> University of Cebu — SIT-IN Monitoring System | Admin Panel
</footer>

<script>
// ── THEME TOGGLE ── (mirrors admin_dashboard.php)
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

  window.addEventListener('storage', function(e) {
    if (e.key === THEME_KEY && e.newValue) applyTheme(e.newValue);
  });
})();
</script>

</body>
</html>