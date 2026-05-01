<?php
require_once 'includes/db.php';

$txns = readCSV('customer_transactions');
$returns = readCSV('returns');

$updated = 0;
foreach ($txns as &$t) {
    if ($t['type'] === 'Return' && empty($t['return_id'])) {
        foreach ($returns as $r) {
            // Match by sale_id and refund amount
            if ($r['sale_id'] == $t['sale_id'] && (float)$r['total_refund'] == (float)$t['credit']) {
                $t['return_id'] = $r['id'];
                $updated++;
                break;
            }
        }
    }
}

if ($updated > 0) {
    // Write back all transactions
    $path = getCSVPath('customer_transactions');
    $fp = fopen($path, 'w');
    $headers = array_keys($txns[0]);
    fputcsv($fp, $headers);
    foreach ($txns as $t) {
        fputcsv($fp, array_values($t));
    }
    fclose($fp);
    echo "Backfilled $updated return_ids in customer_transactions.\n";
} else {
    echo "No return_ids needed backfilling.\n";
}
?>
