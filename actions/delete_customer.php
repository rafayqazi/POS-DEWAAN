<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // 1. Check for Dependencies
    $sales = readCSV('sales');
    foreach ($sales as $s) {
        if ($s['customer_id'] == $id) {
            $_SESSION['error'] = "Cannot delete customer: This customer has sales records. Please delete their sales first.";
            redirect('../pages/customers.php');
        }
    }

    $payments = readCSV('customer_payments');
    foreach ($payments as $p) {
        if ($p['customer_id'] == $id) {
            $_SESSION['error'] = "Cannot delete customer: This customer has payment records.";
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
