<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requirePermission('manage_customers');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$customer_id = $_POST['customer_id'] ?? null;
$dealer_id = $_POST['dealer_id'] ?? ''; // Empty means unlink

if (!$customer_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit;
}

$success = updateCSV('customers', $customer_id, ['linked_dealer_id' => $dealer_id]);

header('Content-Type: application/json');
if ($success) {
    echo json_encode(['success' => true, 'message' => $dealer_id ? 'Dealer linked successfully' : 'Dealer unlinked successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update customer link']);
}
