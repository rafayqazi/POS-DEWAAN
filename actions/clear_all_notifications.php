<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_SESSION['dismissed_alerts'])) {
    $_SESSION['dismissed_alerts'] = [];
}

// Add all business notification types to dismissed list
$types = ['stock', 'expiry', 'debt'];
foreach ($types as $type) {
    if (!in_array($type, $_SESSION['dismissed_alerts'])) {
        $_SESSION['dismissed_alerts'][] = $type;
    }
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
exit;
