<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // 1. Calculate Balance
    $txns = readCSV('customer_transactions');
    $balance = 0;
    foreach ($txns as $t) {
        if ($t['customer_id'] == $id) {
            $balance += ((float)$t['debit'] - (float)$t['credit']);
        }
    }

    if ($balance > 1) {
        $_SESSION['error'] = "Cannot delete customer: This customer has an outstanding debt of " . formatCurrency($balance) . ". Please clear the debt first.";
        redirect('../pages/customers.php');
    }

    // 2. Perform Deletion
    // Delete transactions first
    $remaining_txns = array_filter($txns, function($t) use ($id) {
        return $t['customer_id'] != $id;
    });
    writeCSV('customer_transactions', array_values($remaining_txns), ['id', 'customer_id', 'date', 'type', 'debit', 'credit', 'description', 'due_date', 'created_at', 'sale_id', 'restock_id', 'payment_id']);

    // Delete customer
    deleteCSV('customers', $id);

    $_SESSION['success'] = "Customer and their transaction history deleted successfully.";
    redirect('../pages/customers.php');
} else {
    redirect('../pages/customers.php');
}
