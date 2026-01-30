<?php
require_once __DIR__ . '/session.php';
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
        
        if (!$headers) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

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
        
        if (!$headers) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

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
/**
 * Get a setting value by key
 */
/**
 * Get a setting value by key with static caching
 */
function getSetting($key, $default = '') {
    static $settings_cache = null;
    
    if ($settings_cache === null) {
        $settings_cache = readCSV('settings');
    }
    
    foreach ($settings_cache as $s) {
        if ($s['key'] == $key) return $s['value'];
    }
    return $default;
}

/**
 * Update or insert a setting and invalidate cache
 */
function updateSetting($key, $value) {
    if (findSettingId($key)) {
        updateCSV('settings', findSettingId($key), ['value' => $value]);
    } else {
        insertCSV('settings', ['key' => $key, 'value' => $value]);
    }
}

function findSettingId($key) {
    $settings = readCSV('settings');
    foreach ($settings as $s) {
        if ($s['key'] == $key) return $s['id'];
    }
    return null;
}

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
initCSV('settings', ['id', 'key', 'value']);
initCSV('dealer_transactions', ['id', 'dealer_id', 'type', 'debit', 'credit', 'description', 'date', 'created_at', 'restock_id', 'payment_type', 'payment_proof']);
initCSV('customer_transactions', ['id', 'customer_id', 'type', 'debit', 'credit', 'description', 'date', 'created_at', 'sale_id', 'payment_type', 'payment_proof']);
initCSV('restocks', ['id', 'product_id', 'product_name', 'quantity', 'new_buy_price', 'old_buy_price', 'new_sell_price', 'old_sell_price', 'dealer_id', 'dealer_name', 'amount_paid', 'date', 'expiry_date', 'remarks', 'created_at']);
initCSV('expenses', ['id', 'date', 'category', 'title', 'amount', 'description', 'created_at']);

// Seed defaults only if files do not exist (fresh install)
if (!file_exists(getCSVPath('settings'))) {
    updateSetting('expiry_notify_days', '7');
    updateSetting('recovery_notify_days', '7');
}

if (!file_exists(getCSVPath('units'))) {
    insertCSV('units', ['name' => 'Katta']);
    insertCSV('units', ['name' => 'Ctn']);
    insertCSV('units', ['name' => 'KG']);
}
if (!file_exists(getCSVPath('categories'))) {
    insertCSV('categories', ['name' => 'Fertilizer']);
    insertCSV('categories', ['name' => 'Pesticide']);
    insertCSV('categories', ['name' => 'Other']);
}

/**
 * Execute all DB migrations only if needed
 */
function runMigrations() {
    $current_version = (int)getSetting('db_schema_version', '0');
    $latest_version = 3;

    if ($current_version >= $latest_version) {
        return; // Already up to date
    }

    // ----------------------------------------------------
    // MIGRATION 1: All legacy header fixes
    // ----------------------------------------------------
    
    // Fix missing headers in restocks.csv
    $restockPath = getCSVPath('restocks');
    if (file_exists($restockPath)) {
        $fp_mig = fopen($restockPath, 'r');
        if ($fp_mig) {
            $headers = fgetcsv($fp_mig);
            fclose($fp_mig);
            if ($headers && !in_array('expiry_date', $headers)) {
                $data = readCSV('restocks');
                $newHeaders = array_merge($headers, ['expiry_date', 'remarks']);
                writeCSV('restocks', $data, array_unique($newHeaders));
            }
        }
    }

    // Fix headers in dealers.csv
    $dealerPath = getCSVPath('dealers');
    if (file_exists($dealerPath)) {
        $fp_mig = fopen($dealerPath, 'r');
        if ($fp_mig) {
            $headers = fgetcsv($fp_mig);
            fclose($fp_mig);
            if ($headers && !in_array('phone', $headers)) {
                $data = readCSV('dealers');
                foreach ($data as &$d) {
                    if (isset($d['contact'])) {
                        $d['phone'] = $d['contact'];
                        unset($d['contact']);
                    }
                    if (!isset($d['address'])) {
                        $d['address'] = '';
                    }
                }
                writeCSV('dealers', $data, ['id', 'name', 'phone', 'address', 'created_at']);
            }
        }
    }

    // Fix headers in sales.csv
    $salesPath = getCSVPath('sales');
    if (file_exists($salesPath)) {
        $fp_mig = fopen($salesPath, 'r');
        if ($fp_mig) {
            $headers = fgetcsv($fp_mig);
            fclose($fp_mig);
            if ($headers && (!in_array('due_date', $headers) || !in_array('discount', $headers))) {
                $data = readCSV('sales');
                foreach ($data as &$s) {
                    if (!isset($s['remarks'])) $s['remarks'] = '';
                    if (!isset($s['due_date'])) $s['due_date'] = '';
                    if (!isset($s['discount'])) $s['discount'] = '0';
                }
                $newHeaders = array_merge($headers, ['remarks', 'due_date', 'discount']);
                writeCSV('sales', $data, array_unique($newHeaders));
            }
        }
    }

    // Add due_date to customer_transactions.csv
    $custTxnPath = getCSVPath('customer_transactions');
    if (file_exists($custTxnPath)) {
        $fp_mig = fopen($custTxnPath, 'r');
        if ($fp_mig) {
            $headers = fgetcsv($fp_mig);
            fclose($fp_mig);
            if ($headers && !in_array('due_date', $headers)) {
                $data = readCSV('customer_transactions');
                $newHeaders = array_merge($headers, ['due_date']);
                writeCSV('customer_transactions', $data, array_unique($newHeaders));
            }
        }
    }

    // Payment Fields for Transactions
    foreach (['customer_transactions', 'dealer_transactions'] as $tbl) {
        $p = getCSVPath($tbl);
        if (file_exists($p)) {
            $fp_mig = fopen($p, 'r');
            if ($fp_mig) {
                $headers = fgetcsv($fp_mig);
                fclose($fp_mig);
                if ($headers && !in_array('payment_type', $headers)) {
                    $data = readCSV($tbl);
                    foreach ($data as &$row) {
                        $row['payment_type'] = (isset($row['type']) && $row['type'] == 'Payment') ? 'Cash' : '';
                        $row['payment_proof'] = '';
                    }
                    $newHeaders = array_merge($headers, ['payment_type', 'payment_proof']);
                    writeCSV($tbl, $data, array_unique($newHeaders));
                }
            }
        }
    }

    // Add returned_qty to sale_items.csv
    $saleItemsPath = getCSVPath('sale_items');
    if (file_exists($saleItemsPath)) {
        $fp_mig = fopen($saleItemsPath, 'r');
        if ($fp_mig) {
            $headers = fgetcsv($fp_mig);
            fclose($fp_mig);
            if ($headers && !in_array('returned_qty', $headers)) {
                $data = readCSV('sale_items');
                $newHeaders = array_merge($headers, ['returned_qty']);
                writeCSV('sale_items', $data, array_unique($newHeaders));
            }
        }
    }

    // ----------------------------------------------------
    // MIGRATION 2: User Roles and Related IDs
    // ----------------------------------------------------
    $userPath = getCSVPath('users');
    if (file_exists($userPath)) {
        $fp_mig = fopen($userPath, 'r');
        if ($fp_mig) {
            $headers = fgetcsv($fp_mig);
            fclose($fp_mig);
            if ($headers && !in_array('role', $headers)) {
                $data = readCSV('users');
                foreach ($data as &$u) {
                    if (!isset($u['role'])) $u['role'] = 'Admin';
                    if (!isset($u['related_id'])) $u['related_id'] = '';
                }
                writeCSV('users', $data, ['id', 'username', 'password', 'role', 'related_id', 'created_at']);
            }
        }
    }

    // ----------------------------------------------------
    // MIGRATION 3: Plain Password for Admin visibility
    // ----------------------------------------------------
    $userPath = getCSVPath('users');
    if (file_exists($userPath)) {
        $fp_mig = fopen($userPath, 'r');
        if ($fp_mig) {
            $headers = fgetcsv($fp_mig);
            fclose($fp_mig);
            if ($headers && !in_array('plain_password', $headers)) {
                $data = readCSV('users');
                foreach ($data as &$u) {
                    if (!isset($u['plain_password'])) $u['plain_password'] = ''; 
                }
                writeCSV('users', $data, ['id', 'username', 'password', 'role', 'related_id', 'created_at', 'plain_password']);
            }
        }
    }

    // Mark migration as done
    updateSetting('db_schema_version', '3');
}

// Run Migrations (only runs if version < latest)
runMigrations();

?>
