<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$alertId = $_POST['alert_id'] ?? '';

if ($alertId) {
    if (!isset($_SESSION['dismissed_alerts'])) {
        $_SESSION['dismissed_alerts'] = [];
    }
    
    if (!in_array($alertId, $_SESSION['dismissed_alerts'])) {
        $_SESSION['dismissed_alerts'][] = $alertId;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid Alert ID']);
exit;
