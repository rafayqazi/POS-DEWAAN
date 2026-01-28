<?php
require_once 'includes/db.php';

// 1. Ensure sales.csv has correct headers and padding
$salesPath = DATA_DIR . 'sales.csv';
if (file_exists($salesPath)) {
    $fp = fopen($salesPath, 'r');
    $headers = fgetcsv($fp);
    fclose($fp);

    if (!in_array('due_date', $headers)) {
        $headers[] = 'due_date';
    }

    $raw_rows = [];
    $fp = fopen($salesPath, 'r');
    fgetcsv($fp); // skip original header
    while (($row = fgetcsv($fp)) !== FALSE) {
        // Pad row to match new header count
        while (count($row) < count($headers)) {
            $row[] = '';
        }
        $raw_rows[] = array_combine($headers, $row);
    }
    fclose($fp);
    
    // 2. Recovery: Fetch missing due_dates from customer_transactions
    $txns = readCSV('customer_transactions');
    foreach ($raw_rows as &$sale) {
        if (empty($sale['due_date'])) {
            foreach ($txns as $tx) {
                if (isset($tx['sale_id']) && $tx['sale_id'] == $sale['id'] && !empty($tx['due_date'])) {
                    $sale['due_date'] = $tx['due_date'];
                    echo "Recovered due_date {$tx['due_date']} for Sale #{$sale['id']}\n";
                    break;
                }
            }
        }
    }

    writeCSV('sales', $raw_rows, $headers);
    echo "sales.csv updated successfully.\n";
} else {
    echo "sales.csv not found.\n";
}
?>
