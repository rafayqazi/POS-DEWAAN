<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Calculate Balance
    $txns = readCSV('customer_transactions');
    $balance = 0;
    foreach ($txns as $t) {
        if ($t['customer_id'] == $id) {
            $balance += ((float)$t['debit'] - (float)$t['credit']);
        }
    }

    if ($balance > 1) {
        $msg = "Cannot delete customer: Outstanding debt of " . formatCurrency($balance) . ". Please clear the debt first.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit;
        }
        $_SESSION['error'] = $msg;
        redirect('../pages/customers.php');
    }

    // Delete transactions first
    $remaining_txns = array_filter($txns, function($t) use ($id) {
        return $t['customer_id'] != $id;
    });
    writeCSV('customer_transactions', array_values($remaining_txns), ['id', 'customer_id', 'date', 'type', 'debit', 'credit', 'description', 'due_date', 'created_at', 'sale_id', 'restock_id', 'payment_id']);

    // Delete customer
    deleteCSV('customers', $id);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Customer deleted successfully.']);
        exit;
    }

    $_SESSION['success'] = "Customer and their transaction history deleted successfully.";
    redirect('../pages/customers.php');
} else {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'No ID provided.']);
        exit;
    }
    redirect('../pages/customers.php');
}
