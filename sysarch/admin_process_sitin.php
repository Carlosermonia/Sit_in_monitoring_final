<?php
session_start();
require 'db_connect.php';

// Only admins may access this
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int) $_POST['user_id'];
    $purpose = trim($_POST['purpose']);
    $lab     = trim($_POST['lab']);

    if ($user_id && $purpose && $lab) {

        // 1. Fetch the student to get their id_number and remaining sessions
        $stmt = $pdo->prepare("SELECT id_number, remaining_sessions FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch();

        if ($student && $student['remaining_sessions'] > 0) {

            // 2. Insert sit-in record (using actual column names: lab_number, login_time)
           $ins = $pdo->prepare("
    INSERT INTO sit_in_records (id_number, purpose, lab_number, status, login_time)
    VALUES (?, ?, ?, 'active', NOW())
");
            $ins->execute([$student['id_number'], $purpose, $lab]);

            // 3. Redirect to the Sit-in Records tab (NOT the sit-in active tab)
            header('Location: admin_dashboard.php?tab=records&sitin=1');
            exit;

        } else {
            // No sessions left – redirect back with an error
            header('Location: admin_dashboard.php?tab=search&error=no_sessions');
            exit;
        }
    }
}

// Fallback redirect
header('Location: admin_dashboard.php');
exit;