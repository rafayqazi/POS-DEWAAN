<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$action = $_POST['action'] ?? '';

if ($action == 'check') {
    $status = getUpdateStatus();
    header('Content-Type: application/json');
    echo json_encode($status);
    exit;
} elseif ($action == 'apply') {
    $result = runUpdate();
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
