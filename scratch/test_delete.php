<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$dealer_id = 1;
$del_ids = ['10']; // Let's try to delete ID 10 if it exists for dealer 1

$all_dealer_txns = readCSV('dealer_transactions');
$count = 0;
$txns_to_keep = [];

foreach ($all_dealer_txns as $txn) {
    if (in_array($txn['id'], $del_ids)) {
        if ($txn['dealer_id'] == $dealer_id) {
            $count++;
            echo "Found txn ID " . $txn['id'] . " for dealer $dealer_id. Skipping.\n";
            continue;
        } else {
            echo "Found txn ID " . $txn['id'] . " but dealer_id is " . $txn['dealer_id'] . " (expected $dealer_id).\n";
        }
    }
    $txns_to_keep[] = $txn;
}

if ($count > 0) {
    echo "Would delete $count transactions.\n";
} else {
    echo "Nothing found to delete.\n";
}
?>
