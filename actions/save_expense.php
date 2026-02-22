<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $date = $_POST['date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? 'Miscellaneous';
    $title = $_POST['title'] ?? 'Expense';
    $amount = (float)($_POST['amount'] ?? 0);
    $description = $_POST['description'] ?? '';

    $expense_data = [
        'date'        => $date,
        'category'    => $category,
        'title'       => $title,
        'amount'      => $amount,
        'description' => $description,
        'created_at'  => date('Y-m-d H:i:s')
    ];

    if ($id) {
        updateCSV('expenses', $id, $expense_data);
        $expense_data['id'] = $id;
        $msg = "Expense updated successfully!";
    } else {
        $newId = insertCSV('expenses', $expense_data);
        $expense_data['id'] = $newId;
        $msg = "Expense added successfully!";
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => $msg, 'expense' => $expense_data, 'is_edit' => (bool)$id]);
        exit;
    }

    $_SESSION['success'] = $msg;
}

header('Location: ../pages/expenses.php');
exit;
