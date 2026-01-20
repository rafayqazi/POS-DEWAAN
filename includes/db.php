<?php
// Core System Configuration
define('DATA_DIR', __DIR__ . '/../data/');

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0777, true);
}

// --- CSV Helper Functions ---

/**
 * Get full path for a table's CSV file
 */
function getCSVPath($table) {
    return DATA_DIR . $table . '.csv';
}

/**
 * Initialize a CSV file with headers if it doesn't exist
 */
function initCSV($table, $headers) {
    $path = getCSVPath($table);
    if (!file_exists($path)) {
        $fp = fopen($path, 'w');
        fputcsv($fp, $headers);
        fclose($fp);
    }
}

/**
 * Read all data from a CSV file
 * Returns an array of associative arrays
 */
/**
 * Read all data from a CSV file with shared lock
 * Returns an array of associative arrays
 */
function readCSV($table) {
    // START TRANSACTION (READ)
    $path = getCSVPath($table);
    if (!file_exists($path)) return [];

    $data = [];
    $handle = fopen($path, "r");
    if ($handle !== FALSE) {
        // Acquire Shared Lock
        if (flock($handle, LOCK_SH)) {
            $headers = fgetcsv($handle);
            if ($headers) {
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($headers) == count($row)) {
                        $data[] = array_combine($headers, $row);
                    }
                }
            }
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
    return $data;
}

/**
 * Insert a new row into the CSV with exclusive lock
 */
function insertCSV($table, $row_data) {
    // START TRANSACTION (WRITE)
    $path = getCSVPath($table);
    
    // Ensure file exists and has headers (safely)
    if (!file_exists($path)) {
        $headers = array_keys($row_data);
        if (!in_array('id', $headers)) array_unshift($headers, 'id');
        if (!in_array('created_at', $headers)) $headers[] = 'created_at';
        writeCSV($table, [], $headers); // Initialize
    }

    $retId = false;
    
    $fp = fopen($path, 'r+'); // Open for reading and writing
    if ($fp && flock($fp, LOCK_EX)) {
        // Read headers
        $headers = fgetcsv($fp);
        
        // Read all existing data to find max ID
        $entries = [];
        $last_id = 0;
        while (($row = fgetcsv($fp)) !== FALSE) {
            $entries[] = $row;
            if ($headers && count($row) == count($headers)) {
                $item = array_combine($headers, $row);
                if (isset($item['id']) && $item['id'] > $last_id) {
                    $last_id = (int)$item['id'];
                }
            }
        }

        // Generate ID / Defaults
        if (in_array('id', $headers) && !isset($row_data['id'])) {
            $row_data['id'] = $last_id + 1;
        }
        $retId = $row_data['id'];

        // Prepare new row
        $csv_row = [];
        foreach ($headers as $col) {
            $csv_row[] = isset($row_data[$col]) ? $row_data[$col] : ''; 
        }

        // Move pointer to end to append
        fseek($fp, 0, SEEK_END);
        fputcsv($fp, $csv_row);

        flock($fp, LOCK_UN);
        fclose($fp);
    } elseif ($fp) {
        fclose($fp);
    }

    return $retId;
}

/**
 * Update a row by ID with exclusive lock
 */
function updateCSV($table, $id, $new_data) {
    $path = getCSVPath($table);
    if (!file_exists($path)) return false;

    $fp = fopen($path, 'r+');
    if ($fp && flock($fp, LOCK_EX)) {
        $headers = fgetcsv($fp);
        $rows = [];
        
        while (($r = fgetcsv($fp)) !== FALSE) {
             if (count($r) == count($headers)) {
                 $rows[] = array_combine($headers, $r);
             }
        }

        // Rewrite file from scratch (truncate)
        ftruncate($fp, 0);
        rewind($fp);
        fputcsv($fp, $headers);

        foreach ($rows as $row) {
            if ($row['id'] == $id) {
                // Merge data
                foreach ($new_data as $k => $v) {
                    if (array_key_exists($k, $row)) {
                        $row[$k] = $v;
                    }
                }
            }
            // Write row ensuring order
            $csv_row = [];
            foreach ($headers as $h) {
                $csv_row[] = $row[$h];
            }
            fputcsv($fp, $csv_row);
        }

        flock($fp, LOCK_UN);
        fclose($fp);
    } elseif ($fp) {
        fclose($fp);
    }
}

/**
 * Delete a row by ID with exclusive lock
 */
function deleteCSV($table, $id) {
    $path = getCSVPath($table);
    if (!file_exists($path)) return false;

    $fp = fopen($path, 'r+');
    if ($fp && flock($fp, LOCK_EX)) {
        $headers = fgetcsv($fp);
        $rows = [];
        while (($r = fgetcsv($fp)) !== FALSE) {
             $rows[] = $r;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fputcsv($fp, $headers);

        foreach ($rows as $r) {
            if (count($r) == count($headers)) {
                $row = array_combine($headers, $r);
                if ($row['id'] != $id) {
                    fputcsv($fp, $r);
                }
            }
        }

        flock($fp, LOCK_UN);
        fclose($fp);
    } elseif ($fp) {
        fclose($fp);
    }
}

/**
 * Write a full array of data to CSV (replaces entire file)
 * USE WITH CAUTION: Overwrites everything.
 */
function writeCSV($table, $data, $custom_headers = null) {
    $path = getCSVPath($table);
    
    // Determine headers
    $headers = $custom_headers;
    if (!$headers && !empty($data)) {
        $headers = array_keys(reset($data));
    }
    
    // Preserve existing headers if not provided
    if (!$headers && file_exists($path)) {
        $f = fopen($path, 'r');
        $headers = fgetcsv($f);
        fclose($f);
    }

    if (!$headers) return false;
    
    $fp = fopen($path, 'w'); // 'w' truncates, but we lock immediately
    if ($fp && flock($fp, LOCK_EX)) {
        fputcsv($fp, $headers);
        foreach ($data as $row) {
            $csv_row = [];
            foreach ($headers as $h) {
                $csv_row[] = isset($row[$h]) ? $row[$h] : '';
            }
            fputcsv($fp, $csv_row);
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    } elseif ($fp) {
        fclose($fp);
    }
    return true;
}

/**
 * Find a single row by ID
 */
function findCSV($table, $id) {
    // readCSV already handles locking
    $rows = readCSV($table);
    foreach ($rows as $row) {
        if ($row['id'] == $id) return $row;
    }
    return null;
}

/**
 * Transaction Helper
 * Reads a table securely, allows modification via callback, and writes back.
 * Ensures no other process changes the file in between read and write.
 */
function processCSVTransaction($table, $callback) {
    $path = getCSVPath($table);
    if (!file_exists($path)) return false;

    $fp = fopen($path, 'r+');
    if (!$fp) return false;

    if (flock($fp, LOCK_EX)) { // Exclusive Lock
        // 1. Read Data
        $headers = fgetcsv($fp);
        $data = [];
        if ($headers) {
            while (($row = fgetcsv($fp)) !== FALSE) {
                if (count($row) == count($headers)) {
                    $data[] = array_combine($headers, $row);
                }
            }
        }

        // 2. Execute Callback (Modify Data)
        // Callback signature: function($data) { return $modified_data; }
        // Pass $data by reference is also possible, but return is safer for logic
        $newData = $callback($data);

        if ($newData !== false) { // distinct from empty array
            // 3. Write Back
            ftruncate($fp, 0);
            rewind($fp);
            fputcsv($fp, $headers);
            foreach ($newData as $row) {
                 $csv_row = [];
                 foreach ($headers as $h) {
                     $csv_row[] = isset($row[$h]) ? $row[$h] : '';
                 }
                 fputcsv($fp, $csv_row);
            }
        }

        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    } 
    
    fclose($fp);
    return false;
}

// Initialize tables if they don't exist
initCSV('units', ['id', 'name']);
initCSV('categories', ['id', 'name']);
initCSV('dealer_transactions', ['id', 'dealer_id', 'type', 'amount', 'description', 'date', 'created_at', 'restock_id']);
initCSV('restocks', ['id', 'product_id', 'product_name', 'quantity', 'new_buy_price', 'old_buy_price', 'new_sell_price', 'old_sell_price', 'dealer_id', 'dealer_name', 'amount_paid', 'date', 'created_at']);

// Seed defaults if empty
if (count(readCSV('units')) == 0) {
    insertCSV('units', ['name' => 'Katta']);
    insertCSV('units', ['name' => 'Ctn']);
    insertCSV('units', ['name' => 'KG']);
}
if (count(readCSV('categories')) == 0) {
    insertCSV('categories', ['name' => 'Fertilizer']);
    insertCSV('categories', ['name' => 'Pesticide']);
    insertCSV('categories', ['name' => 'Other']);
}
?>
