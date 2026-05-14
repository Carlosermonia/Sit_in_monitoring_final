<?php
session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

if ($data && $data['action'] === 'mark_viewed') {
    // Update the last viewed notifications time to current time
    $_SESSION['last_viewed_notifications'] = date('Y-m-d H:i:s');
    
    echo json_encode([
        'success' => true,
        'message' => 'Notifications marked as viewed',
        'timestamp' => $_SESSION['last_viewed_notifications']
    ]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}
?>
