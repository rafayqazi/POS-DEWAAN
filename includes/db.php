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
function readCSV($table) {
    $path = getCSVPath($table);
    if (!file_exists($path)) return [];

    $data = [];
    if (($handle = fopen($path, "r")) !== FALSE) {
        $headers = fgetcsv($handle); // Read header row
        if (!$headers) return []; // Empty file

        while (($row = fgetcsv($handle)) !== FALSE) {
            // Combine header with row data to create associative array
            // Handle cases where row length might mismatch header (though unlikely if managed by app)
            if (count($headers) == count($row)) {
                $data[] = array_combine($headers, $row);
            }
        }
        fclose($handle);
    }
    return $data;
}

/**
 * Insert a new row into the CSV
 * Auto-increments ID if 'id' is in headers but not in data
 */
function insertCSV($table, $row_data) {
    $path = getCSVPath($table);
    $all_data = readCSV($table);
    
    // Get headers
    $headers = [];
    if (file_exists($path)) {
        $f = fopen($path, 'r');
        $headers = fgetcsv($f);
        fclose($f);
    }

    if (!$headers) return false;

    // Generate ID?
    if (in_array('id', $headers) && !isset($row_data['id'])) {
        $last_id = 0;
        foreach ($all_data as $row) {
            if (isset($row['id']) && $row['id'] > $last_id) {
                $last_id = (int)$row['id'];
            }
        }
        $row_data['id'] = $last_id + 1;
    }

    // Prepare row in correct order of headers
    $csv_row = [];
    foreach ($headers as $col) {
        $csv_row[] = isset($row_data[$col]) ? $row_data[$col] : ''; // Default to empty string
    }

    // Append to file
    $fp = fopen($path, 'a');
    fputcsv($fp, $csv_row);
    fclose($fp);

    return isset($row_data['id']) ? $row_data['id'] : true;
}

/**
 * Update a row by ID
 */
function updateCSV($table, $id, $new_data) {
    $rows = readCSV($table);
    $headers = array_keys(reset($rows) ?: []);
    if (!$headers) {
        // Try to get headers from file if rows are empty but file exists
        if (($h = fopen(getCSVPath($table), 'r')) !== FALSE) {
            $headers = fgetcsv($h);
            fclose($h);
        }
    }

    $fp = fopen(getCSVPath($table), 'w');
    fputcsv($fp, $headers); // Write headers

    foreach ($rows as $row) {
        if ($row['id'] == $id) {
            // Merge existing row with new data
            foreach ($new_data as $k => $v) {
                if (array_key_exists($k, $row)) {
                    $row[$k] = $v;
                }
            }
        }
        fputcsv($fp, $row); // Write row
    }
    fclose($fp);
}

/**
 * Delete a row by ID
 */
function deleteCSV($table, $id) {
    $rows = readCSV($table);
    $path = getCSVPath($table);
    
    // Get headers from file before clearing
    $headers = [];
    if (file_exists($path)) {
        $f = fopen($path, 'r');
        $headers = fgetcsv($f);
        fclose($f);
    }
    
    if (!$headers) return false;

    // Re-open and overwrite
    $fp = fopen($path, 'w');
    fputcsv($fp, $headers); // Write headers

    foreach ($rows as $row) {
        if ($row['id'] != $id) {
            fputcsv($fp, $row);
        }
    }
    fclose($fp);
}

/**
 * Write a full array of data to CSV (replaces entire file)
 */
function writeCSV($table, $data, $custom_headers = null) {
    $path = getCSVPath($table);
    
    // Determine headers
    $headers = $custom_headers;
    if (!$headers && !empty($data)) {
        $headers = array_keys(reset($data));
    }
    
    // If we still don't have headers and file exists, try to preserve them
    if (!$headers && file_exists($path)) {
        $f = fopen($path, 'r');
        $headers = fgetcsv($f);
        fclose($f);
    }

    if (!$headers) return false;
    
    $fp = fopen($path, 'w');
    fputcsv($fp, $headers);
    foreach ($data as $row) {
        // Ensure values follow header order
        $csv_row = [];
        foreach ($headers as $h) {
            $csv_row[] = isset($row[$h]) ? $row[$h] : '';
        }
        fputcsv($fp, $csv_row);
    }
    fclose($fp);
    return true;
}

/**
 * Find a single row by ID
 */
function findCSV($table, $id) {
    $rows = readCSV($table);
    foreach ($rows as $row) {
        if ($row['id'] == $id) return $row;
    }
    return null;
}

// Initialize tables if they don't exist
initCSV('units', ['id', 'name']);
initCSV('categories', ['id', 'name']);

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
