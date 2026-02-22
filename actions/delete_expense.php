<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$id = $_GET['id'] ?? ($_POST['id'] ?? null);

if ($id) {
    deleteCSV('expenses', $id);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    $_SESSION['success'] = "Expense deleted successfully!";
}

header('Location: ../pages/expenses.php');
exit;
