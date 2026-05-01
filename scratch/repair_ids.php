<?php
require_once 'includes/db.php';

function repairTableIDs($table) {
    $path = getCSVPath($table);
    if (!file_exists($path)) return;

    $handle = fopen($path, "r");
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return;
    }

    $rows = [];
    $id_map = []; // Old ID -> New ID (not really needed if no foreign keys)
    $next_id = 1;

    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) == count($headers)) {
            $item = array_combine($headers, $row);
            $item['id'] = $next_id++;
            $rows[] = $item;
        }
    }
    fclose($handle);

    // Write back
    $fp = fopen($path, 'w');
    fputcsv($fp, $headers);
    foreach ($rows as $row) {
        fputcsv($fp, array_values($row));
    }
    fclose($fp);
    echo "Repaired $table: Re-indexed " . count($rows) . " rows.\n";
}

repairTableIDs('dealer_transactions');
repairTableIDs('customer_transactions');
?>
