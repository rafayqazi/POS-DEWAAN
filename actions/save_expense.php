<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $date = $_POST['date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? 'Miscellaneous';
    $title = $_POST['title'] ?? 'Expense';
    $amount = (float)($_POST['amount'] ?? 0);
    $description = $_POST['description'] ?? '';

    $expense_data = [
        'date' => $date,
        'category' => $category,
        'title' => $title,
        'amount' => $amount,
        'description' => $description,
        'created_at' => date('Y-m-d H:i:s')
    ];

    if ($id) {
        updateCSV('expenses', $id, $expense_data);
        $_SESSION['success'] = "Expense updated successfully!";
    } else {
        insertCSV('expenses', $expense_data);
        $_SESSION['success'] = "Expense added successfully!";
    }
}

header('Location: ../pages/expenses.php');
exit;
