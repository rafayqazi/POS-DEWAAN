<?php
function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $base = (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? '../' : '';
        redirect($base . 'login.php');
    }
}

function formatCurrency($amount) {
    return 'Rs. ' . number_format($amount, 0); // No decimals for simplicity unless needed
}

// function getSetting() and updateSetting() removed as they are defined in includes/db.php


function getNetworkTime() {
    // Try to get time from google.com (fast and reliable)
    $context = stream_context_create([
        'http' => ['method' => 'HEAD', 'timeout' => 2.0] // 2s timeout
    ]);
    
    // Suppress errors, we will fallback if it fails
    $headers = @get_headers("http://www.google.com", 1, $context);
    
    if ($headers && isset($headers['Date'])) {
        $network_time = strtotime($headers['Date']);
        if ($network_time > 0) return $network_time;
    }
    
    return false;
}

function getReliableTime() {
    // Check if we have a cached offset
    if (!isset($_SESSION['time_offset'])) {
        $net_time = getNetworkTime();
        if ($net_time) {
            // Calculate offset: Network - System
            $_SESSION['time_offset'] = $net_time - time();
        } else {
            $_SESSION['time_offset'] = 0; // Fallback to system time
        }
    }
    
    return time() + $_SESSION['time_offset'];
}

function getUpdateStatus($force_fetch = false) {
    $status = null;
    $current_time = getReliableTime();

    // 0. Use session cache if available and not forced
    if (!$force_fetch && isset($_SESSION['last_update_check']) && ($current_time - $_SESSION['last_update_check'] < 3600)) {
        if (isset($_SESSION['cached_update_status'])) {
            $status = $_SESSION['cached_update_status'];
        }
    }

    if ($status === null) {
        $status = ['available' => false, 'count' => 0, 'branch' => '', 'local' => '', 'remote' => '', 'error' => ''];
        
        // 1. Identify current branch
        exec('git rev-parse --abbrev-ref HEAD 2>&1', $out_b, $ret_b);
        if ($ret_b !== 0) {
            $status['error'] = "Could not identify branch: " . implode(" ", $out_b);
            // Return defaults on error
        } else {
            $status['branch'] = trim($out_b[0] ?? 'master');
            
            // 2. Explicit Fetch from origin
            exec('git fetch origin ' . $status['branch'] . ' 2>&1', $out, $ret);
            // Note: If fetch fails, we continue with local info, but likely won't see an update available.
            
            // 3. Hashes & Count
            exec('git rev-parse HEAD 2>&1', $out_l);
            exec("git rev-parse origin/" . $status['branch'] . " 2>&1", $out_r);
            $status['local'] = substr(trim($out_l[0] ?? ''), 0, 7);
            $status['remote'] = substr(trim($out_r[0] ?? ''), 0, 7);
            
            // 4. Count behind
            exec("git rev-list --count HEAD..origin/" . $status['branch'] . " 2>&1", $out_c);
            $status['count'] = (int)($out_c[0] ?? 0);
            $status['available'] = ($status['count'] > 0 || ($status['local'] !== $status['remote'] && $status['remote'] != ''));
            
            // Cache the GIT part of the result
            $_SESSION['last_update_check'] = $current_time;
            $_SESSION['cached_update_status'] = $status;
        }
    }
    
    // 5. Track Detection Time for Grace Period (ALWAYS RUN THIS)
    if ($status['available']) {
        // Read directly from DB to avoid static cache issues
        $all_settings = readCSV('settings');
        $first_detected = null;
        
        foreach($all_settings as $s) { 
            if($s['key'] == 'update_first_detected') {
                $first_detected = $s['value'];
                break;
            }
        }
        
        // If not set, or invalid, set it now.
        if (empty($first_detected) || $first_detected <= 0) {
            $first_detected = $current_time;
            
            // Critical: Write immediately
            if (findSettingId('update_first_detected')) {
                updateSetting('update_first_detected', $first_detected);
            } else {
                insertCSV('settings', ['key' => 'update_first_detected', 'value' => $first_detected]);
            }
        }
        
        $status['first_detected'] = (int)$first_detected;
        $status['deadline'] = $status['first_detected'] + 60; // 1 minute later
        $status['overdue'] = ($current_time > $status['deadline']);
        $status['time_left'] = max(0, $status['deadline'] - $current_time);
    } else {
        $status['first_detected'] = 0;
        $status['deadline'] = 0;
        $status['overdue'] = false;
        $status['time_left'] = 0;
        
        // Optional: Reset detection if update is gone? 
        // No, keep it for history or manual reset.
    }
    
    return $status;
}

function runUpdate() {
    exec('git rev-parse --abbrev-ref HEAD 2>&1', $out_b);
    $branch = trim($out_b[0] ?? 'master');
    
    // Step 1: Check for unmerged files or ongoing merge
    exec("git ls-files -u 2>&1", $unmerged_files);
    if (!empty($unmerged_files)) {
        // There are unmerged files, abort the merge
        exec("git merge --abort 2>&1");
    }
    
    // Step 2: Reset to a clean state (hard reset to current HEAD)
    // This will discard any uncommitted changes in tracked files
    exec("git reset --hard HEAD 2>&1");
    
    // Step 3: Clean untracked files and directories (but preserve data/*.csv files)
    // -f forces clean, -d removes directories
    exec("git clean -fd 2>&1");
    
    // Step 4: Stash any remaining local changes (especially in data files)
    exec("git stash 2>&1");
    
    // Step 5: Fetch the latest changes from remote
    exec("git fetch origin $branch 2>&1");
    
    // Step 6: Force reset to remote branch (this ensures we're in sync)
    exec("git reset --hard origin/$branch 2>&1", $reset_output, $reset_var);
    
    if ($reset_var === 0) {
        // Success: Try to restore local data changes (CSV files)
        exec("git stash pop 2>&1", $stash_output, $stash_var);
        
        // Clear the detection flag
        updateSetting('update_first_detected', '');
        
        $message = "Update successful! Your software has been updated to the latest version.";
        return ['success' => true, 'message' => $message, 'branch' => $branch];
    } else {
        // Failure: If reset failed
        $message = "Update failed: " . implode("\n", $reset_output);
        return ['success' => false, 'message' => $message, 'branch' => $branch];
    }
}

// --- RBAC Helpers ---

function isRole($role) {
    if (!isset($_SESSION['user_role'])) return false;
    if (is_array($role)) return in_array($_SESSION['user_role'], $role);
    return $_SESSION['user_role'] === $role;
}

function hasPermission($action) {
    $role = $_SESSION['user_role'] ?? '';
    
    if ($role === 'Admin') return true;
    
    switch ($action) {
        case 'view_dashboard':
        case 'view_inventory':
        case 'view_ledger':
        case 'view_reports':
            return in_array($role, ['Admin', 'Viewer', 'Customer', 'Dealer']);
        
        case 'add_sale':
        case 'edit_sale':
        case 'delete_sale':
        case 'add_product':
        case 'edit_product':
        case 'delete_product':
        case 'add_restock':
        case 'manage_users':
        case 'update_settings':
        case 'manage_customers':
        case 'manage_dealers':
        case 'manage_business':
            return $role === 'Admin';
            
        case 'view_sensitive_stats':
        case 'view_business_alerts':
            return in_array($role, ['Admin', 'Viewer']);
            
        case 'download_records':
            return in_array($role, ['Admin', 'Viewer']);
            
        default:
            return false;
    }
}

function requirePermission($action) {
    requireLogin();
    if (!hasPermission($action)) {
        die("Unauthorized access. You do not have permission to perform this action.");
    }
}

function getUserRelatedId() {
    return $_SESSION['related_id'] ?? null;
}

/**
 * Filter data based on user role
 */
function filterDataByRole($table, $data) {
    $role = $_SESSION['user_role'] ?? 'Admin';
    $related_id = getUserRelatedId();
    
    if ($role === 'Admin' || $role === 'Viewer') return $data;
    
    if ($role === 'Customer' && $related_id) {
        if ($table === 'sales' || $table === 'customer_transactions') {
            return array_filter($data, function($item) use ($related_id) {
                return isset($item['customer_id']) && $item['customer_id'] == $related_id;
            });
        }
        if ($table === 'customers') {
            return array_filter($data, function($item) use ($related_id) {
                return isset($item['id']) && $item['id'] == $related_id;
            });
        }
    }
    
    if ($role === 'Dealer' && $related_id) {
        if ($table === 'restocks' || $table === 'dealer_transactions') {
            return array_filter($data, function($item) use ($related_id) {
                return isset($item['dealer_id']) && $item['dealer_id'] == $related_id;
            });
        }
        if ($table === 'dealers') {
            return array_filter($data, function($item) use ($related_id) {
                return isset($item['id']) && $item['id'] == $related_id;
            });
        }
    }
    
    // For other tables or if no related_id, return empty if not Admin/Viewer
    if (in_array($table, ['sales', 'customer_transactions', 'restocks', 'dealer_transactions', 'expenses', 'users', 'settings'])) {
         return [];
    }
    
    return $data;
}


/**
 * Consolidated Notification Helper
 */
function getGlobalNotifications() {
    $notifications = [];
    
    // Use the unified permission helper
    if (hasPermission('view_business_alerts')) {
        $all_products = readCSV('products');
        
        // 1. Low Stock Check
        $dismissed = $_SESSION['dismissed_alerts'] ?? [];
        $low_stock_count = 0;
        foreach ($all_products as $p) {
            if (isset($p['stock_quantity']) && $p['stock_quantity'] < 10) {
                $low_stock_count++;
            }
        }
        if ($low_stock_count > 0 && !in_array('stock', $dismissed)) {
            $notifications[] = [
                'type' => 'stock',
                'title' => 'Critical Stock Warning',
                'message' => "{$low_stock_count} items are running low.",
                'icon' => 'fas fa-exclamation-triangle',
                'color' => 'bg-red-500',
                'link' => 'pages/inventory.php?filter=low'
            ];
        }

        // 2. Expiry Check
        $notify_days = (int)getSetting('expiry_notify_days', '7');
        $expiry_threshold = date('Y-m-d', strtotime("+$notify_days days"));
        $expiring_count = 0;
        foreach ($all_products as $p) {
            if (!empty($p['expiry_date']) && $p['expiry_date'] <= $expiry_threshold && $p['expiry_date'] >= date('Y-m-d')) {
                $expiring_count++;
            }
        }
        if ($expiring_count > 0 && !in_array('expiry', $dismissed)) {
            $notifications[] = [
                'type' => 'expiry',
                'title' => 'Expiry Alert',
                'message' => "{$expiring_count} items expiring soon.",
                'icon' => 'fas fa-calendar-times',
                'color' => 'bg-amber-500',
                'link' => 'pages/inventory.php'
            ];
        }

        // 3. Debt Recovery Logic
        $all_txns = readCSV('customer_transactions');
        $debt_map = [];
        foreach($all_txns as $t) {
            $cid = $t['customer_id'];
            $debt_map[$cid] = ($debt_map[$cid] ?? 0) + ((float)$t['debit'] - (float)$t['credit']);
        }

        $rec_notify_days = (int)getSetting('recovery_notify_days', '7');
        $rec_threshold = date('Y-m-d', strtotime("+$rec_notify_days days"));
        $due_count = 0;
        foreach ($all_txns as $tx) {
            if (!empty($tx['due_date']) && ($debt_map[$tx['customer_id']] ?? 0) > 1) {
                if ($tx['due_date'] <= $rec_threshold) {
                    $due_count++;
                }
            }
        }
        if ($due_count > 0 && !in_array('debt', $dismissed)) {
            $notifications[] = [
                'type' => 'debt',
                'title' => 'Debt Recovery Due',
                'message' => "{$due_count} payments are pending.",
                'icon' => 'fas fa-file-invoice-dollar',
                'color' => 'bg-blue-500',
                'link' => 'pages/customers.php'
            ];
        }
    }

    return $notifications;
}
?>
