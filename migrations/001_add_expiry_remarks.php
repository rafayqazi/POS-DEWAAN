<?php
require_once '../includes/db.php';

function modifyCSVHeader($table, $newColumns) {
    echo "Checking table: $table...<br>";
    $path = getCSVPath($table);
    
    if (!file_exists($path)) {
        echo "Table $table currently does not exist (no file). Skipping.<br>";
        return;
    }

    $fp = fopen($path, 'r+');
    if ($fp && flock($fp, LOCK_EX)) {
        $headers = fgetcsv($fp);
        
        $added = [];
        foreach ($newColumns as $col) {
            if (!in_array($col, $headers)) {
                $headers[] = $col;
                $added[] = $col;
            }
        }

        if (empty($added)) {
            echo " - No new columns to add. Headers are up to date.<br>";
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }

        echo " - Adding columns: " . implode(', ', $added) . "<br>";

        // Read all data
        $rows = [];
        while (($row = fgetcsv($fp)) !== FALSE) {
            $rows[] = $row;
        }

        // Rewrite file with new headers
        ftruncate($fp, 0);
        rewind($fp);
        fputcsv($fp, $headers); // New headers

        foreach ($rows as $row) {
            // Pad the row with empty strings for new columns
            // The row is just an indexed array here, matching the OLD headers order.
            // We need to just append empty strings for the new columns since we appended headers.
            foreach ($added as $newCol) {
                $row[] = ""; 
            }
            fputcsv($fp, $row);
        }

        flock($fp, LOCK_UN);
        fclose($fp);
        echo " - Updated successfully.<br>";
    } else {
        echo " - Could not lock file.<br>";
    }
}

echo "<h1>Migration: Add Expiry & Remarks</h1>";

modifyCSVHeader('products', ['expiry_date', 'remarks']);
modifyCSVHeader('restocks', ['expiry_date', 'remarks']);

echo "<h2>Done!</h2>";
?>
