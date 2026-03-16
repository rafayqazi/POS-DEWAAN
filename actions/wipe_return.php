<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (isset($_GET['id'])) {
    $return_id = $_GET['id'];

    // 1. Fetch Return Data
    $return = findCSV('returns', $return_id);
    if (!$return) {
        redirect("../pages/reports.php?error=" . urlencode("Return record not found."));
    }

    // 2. Fetch Return Items
    $all_return_items = readCSV('return_items');
    $this_return_items = array_filter($all_return_items, function($ri) use ($return_id) {
        return $ri['return_id'] == $return_id;
    });

    // 3. Delete Return Items & Return Record (Simple Wipeout)
    foreach ($this_return_items as $ri) {
        deleteCSV('return_items', $ri['id']);
    }
    deleteCSV('returns', $return_id);

    redirect("../pages/reports.php?msg=" . urlencode("Return record wiped out successfully. No stock or financial changes were made."));
} else {
    redirect("../pages/reports.php");
}
