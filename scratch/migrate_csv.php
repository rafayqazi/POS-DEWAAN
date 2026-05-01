<?php
require_once 'includes/db.php';

function migrateTable($table, $newColumn) {
    $path = getCSVPath($table);
    if (!file_exists($path)) return;

    $handle = fopen($path, "r");
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return;
    }

    if (in_array($newColumn, $headers)) {
        fclose($handle);
        echo "Table $table already has $newColumn column.\n";
        return;
    }

    $newHeaders = $headers;
    $newHeaders[] = $newColumn;

    $rows = [];
    while (($row = fgetcsv($handle)) !== FALSE) {
        // Pad the row to match new headers count
        while (count($row) < count($newHeaders)) {
            $row[] = '';
        }
        $rows[] = $row;
    }
    fclose($handle);

    // Write back
    $fp = fopen($path, 'w');
    fputcsv($fp, $newHeaders);
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    echo "Migrated $table: Added $newColumn column and padded " . count($rows) . " rows.\n";
}

migrateTable('customer_transactions', 'return_id');
migrateTable('dealer_transactions', 'return_id');
migrateTable('customer_transactions', 'payment_id'); // Just in case
migrateTable('dealer_transactions', 'payment_id'); // Just in case
?>
