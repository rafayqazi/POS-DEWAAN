<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
requirePermission('manage_dealers');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $deleted = deleteCSV('dealers', $id);

    if ($isAjax) {
        header('Content-Type: application/json');
        if ($deleted) {
            echo json_encode(['status' => 'success', 'message' => 'Dealer deleted successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete dealer. Record not found.']);
        }
        exit;
    }

    if ($deleted) {
        $_SESSION['success'] = "Dealer deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete dealer. Record not found.";
    }
    redirect('../pages/dealers.php');

} else {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'No ID provided.']);
        exit;
    }
    redirect('../pages/dealers.php');
}
