<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
requirePermission('manage_dealers');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Validations removed as requested by user.
    // Perform Deletion
    $deleted = deleteCSV('dealers', $id);

    if ($deleted) {
        $_SESSION['success'] = "Dealer deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete dealer. Record not found.";
    }
    redirect('../pages/dealers.php');

} else {
    redirect('../pages/dealers.php');
}
