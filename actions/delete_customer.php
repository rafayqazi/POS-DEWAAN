<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // 1. Check for Dependencies
    // 1. Check for Dependencies (Unified Ledger)
    $txns = readCSV('customer_transactions');
    foreach ($txns as $t) {
        if ($t['customer_id'] == $id) {
            $_SESSION['error'] = "Cannot delete customer: This customer has ledger transactions. Please delete their transactions/sales first.";
            redirect('../pages/customers.php');
        }
    }

    // 2. Perform Deletion
    // We need a specific delete function or use the generic one if it supports locking
    // Since our deleteCSV now supports locking, we can use it directly
    deleteCSV('customers', $id);

    $_SESSION['success'] = "Customer deleted successfully.";
    redirect('../pages/customers.php');
} else {
    redirect('../pages/customers.php');
}
