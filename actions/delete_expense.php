<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (isset($_GET['id'])) {
    deleteCSV('expenses', $_GET['id']);
    $_SESSION['success'] = "Expense deleted successfully!";
}

header('Location: ../pages/expenses.php');
exit;
