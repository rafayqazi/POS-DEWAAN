<?php
function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) return false;

    // Auto-logout after 24 hours from login
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > 86400) {
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    return true;
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

/**
 * Software Tracking System
 */
function getMachineMAC() {
    $os = strtoupper(substr(PHP_OS, 0, 3));
    $output = "";
    
    // Check if shell_exec is even available
    if (!function_exists('shell_exec')) return "No shell_exec";

    try {
        if ($os === 'WIN') {
            // Method 1: getmac
            $output = @shell_exec('getmac');
            if (preg_match('/([0-9a-fA-F]{2}-){5}[0-9a-fA-F]{2}/', $output, $matches)) {
                return $matches[0];
            }
            // Method 2: ipconfig /all (Fallback)
            $output = @shell_exec('ipconfig /all');
            if (preg_match('/Physical Address.*?([0-9a-fA-F]{2}-){5}[0-9a-fA-F]{2}/i', $output, $matches)) {
                $mac = explode(': ', $matches[0]);
                return end($mac);
            }
        } else {
            $output = @shell_exec('/sbin/ifconfig || /sbin/ip link show');
            if (preg_match('/([0-9a-fA-F]{2}:){5}[0-9a-fA-F]{2}/', $output, $matches)) {
                return $matches[0];
            }
        }
    } catch (Exception $e) { }
    
    return "Not Found";
}

function getSystemTrackingData() {
    $ip = @file_get_contents('https://api.ipify.org') ?: '0.0.0.0';
    return [
        'machine_name'     => gethostname(),
        'mac_address'      => getMachineMAC(),
        'os_info'          => php_uname(),
        'public_ip'        => $ip,
        'local_ip'         => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
        'software_url'     => ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'),
        'username'         => $_SESSION['username'] ?? 'Unknown',
        'business_name'    => getSetting('business_name', 'N/A'),
        'business_address' => getSetting('business_address', 'N/A'),
        'business_phone'   => getSetting('business_phone', 'N/A'),
        'timestamp'        => date('Y-m-d H:i:s')
    ];
}

function sendTrackingHeartbeat() {
    $url = "https://script.google.com/macros/s/AKfycbzTtbwCMDERuHgjWPk9SyQLK2BDnzM35bzLRuNxOJg-IXVH8sE3tR1dOvdKMeF1MGcrFQ/exec"; 
    $lockFile = dirname(__FILE__) . '/.security_dat.php';

    $data = getSystemTrackingData();

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle Remote Blocking
    $cleanResponse = trim($response);
    if ($cleanResponse === 'BLOCKED') {
        $_SESSION['system_blocked'] = true;
        @file_put_contents($lockFile, '<?php /* Security Lock Active */ return true; ?>');
    } else if ($httpCode == 200 && ($cleanResponse === 'Logged' || strpos($cleanResponse, 'Logged') !== false)) {
        // Only unblock if we successfully talked to the server and it didn't say BLOCKED
        unset($_SESSION['system_blocked']);
        if (file_exists($lockFile)) @unlink($lockFile);
    }

    $_SESSION['tracked_today'] = true;
}

/**
 * Check if the system is remotely blocked
 */
function checkSystemBlock() {
    $lockFile = dirname(__FILE__) . '/.security_dat.php';
    $isBlocked = (isset($_SESSION['system_blocked']) && $_SESSION['system_blocked'] === true) || file_exists($lockFile);

    if ($isBlocked) {
        // Agar blocked hai toh UI dikhayein
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Restricted</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap");
                body { font-family: "Plus Jakarta Sans", sans-serif; }
            </style>
        </head>
        <body class="bg-gray-950 flex items-center justify-center min-h-screen p-6 overflow-hidden">
            <div class="absolute inset-0 opacity-20 pointer-events-none">
                <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-red-600 rounded-full blur-[120px]"></div>
                <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-600 rounded-full blur-[120px]"></div>
            </div>
            
            <div class="max-w-md w-full bg-white/10 backdrop-blur-xl p-10 rounded-[2.5rem] border border-white/10 shadow-2xl text-center relative z-10 transition-all transform hover:scale-[1.02]">
                <div class="w-24 h-24 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-8 border border-red-500/30 group">
                    <i class="fas fa-shield-alt text-4xl text-red-500 animate-pulse"></i>
                </div>
                
                <h1 class="text-4xl font-extrabold text-white mb-4 tracking-tight">Access Restricted</h1>
                <p class="text-gray-400 mb-8 leading-relaxed text-lg">
                    You are either selling or using pirated software. You are not allowed to use this software for free.
                </p>
                
                <div class="space-y-4 mb-10">
                    <div class="bg-black/20 p-6 rounded-2xl border border-white/5 group transition-all hover:bg-black/40">
                        <span class="block text-[10px] text-gray-500 uppercase tracking-[0.2em] mb-2 font-bold text-red-500">Notice</span>
                        <span class="text-white font-semibold text-sm">Please contact the developer to authorize your license.</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <button onclick="document.getElementById(\'contactModal\').classList.remove(\'hidden\')" class="inline-flex items-center justify-center w-full px-8 py-4 bg-white text-black font-bold rounded-2xl hover:bg-gray-200 transition shadow-lg shadow-white/10 group">
                        <i class="fas fa-id-card mr-3 text-sm group-hover:rotate-12 transition"></i> Contact Developer
                    </button>
                </div>
                
                <p class="mt-8 text-xs text-gray-500 uppercase tracking-widest font-medium opacity-50">Authorized Personnel Only</p>
            </div>

            <!-- Contact Modal -->
            <div id="contactModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md transition-all">
                <div class="max-w-sm w-full bg-gray-900 border border-white/10 rounded-[2rem] p-8 shadow-2xl relative animate-in fade-in zoom-in duration-300">
                    <button onclick="document.getElementById(\'contactModal\').classList.add(\'hidden\')" class="absolute top-6 right-6 text-gray-500 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                    
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-blue-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-blue-500/20">
                            <i class="fas fa-address-book text-2xl text-blue-500"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-white">Developer Contact</h2>
                        <p class="text-gray-400 text-sm mt-1">Get in touch for license activation</p>
                    </div>

                    <div class="space-y-3">
                        <div class="grid grid-cols-1 gap-3">
                            <a href="https://wa.me/923000358189" target="_blank" class="flex items-center p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-green-600/10 hover:border-green-600/30 transition group">
                                <i class="fab fa-whatsapp w-10 text-xl text-green-500 group-hover:scale-110 transition"></i>
                                <div class="text-left ml-2">
                                    <span class="block text-[10px] text-gray-500 uppercase font-bold tracking-tight">WhatsApp 1</span>
                                    <span class="text-white text-sm font-semibold">0300-0358189</span>
                                </div>
                            </a>
                            <a href="https://wa.me/923710273699" target="_blank" class="flex items-center p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-green-600/10 hover:border-green-600/30 transition group">
                                <i class="fab fa-whatsapp w-10 text-xl text-green-500 group-hover:scale-110 transition"></i>
                                <div class="text-left ml-2">
                                    <span class="block text-[10px] text-gray-500 uppercase font-bold tracking-tight">WhatsApp 2</span>
                                    <span class="text-white text-sm font-semibold">0371-0273699</span>
                                </div>
                            </a>
                        </div>
                        
                        <a href="https://www.linkedin.com/in/abdulrafayqazi/" target="_blank" class="flex items-center p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-blue-600/10 hover:border-blue-600/30 transition group">
                            <i class="fab fa-linkedin w-10 text-xl text-blue-400 group-hover:scale-110 transition"></i>
                            <div class="text-left ml-2">
                                <span class="block text-[10px] text-gray-500 uppercase font-bold tracking-tight">LinkedIn</span>
                                <span class="text-white text-sm font-semibold">@abdulrafayqazi</span>
                            </div>
                        </a>

                        <a href="https://web.facebook.com/rafeH.QAZI" target="_blank" class="flex items-center p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-blue-700/10 hover:border-blue-700/30 transition group">
                            <i class="fab fa-facebook w-10 text-xl text-blue-600 group-hover:scale-110 transition"></i>
                            <div class="text-left ml-2">
                                <span class="block text-[10px] text-gray-500 uppercase font-bold tracking-tight">Facebook</span>
                                <span class="text-white text-sm font-semibold">@rafeH.QAZI</span>
                            </div>
                        </a>

                        <a href="https://www.instagram.com/abdulrafayqazi/" target="_blank" class="flex items-center p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-pink-600/10 hover:border-pink-600/30 transition group">
                            <i class="fab fa-instagram w-10 text-xl text-pink-500 group-hover:scale-110 transition"></i>
                            <div class="text-left ml-2">
                                <span class="block text-[10px] text-gray-500 uppercase font-bold tracking-tight">Instagram</span>
                                <span class="text-white text-sm font-semibold">@abdulrafayqazi</span>
                            </div>
                        </a>

                        <a href="https://github.com/rafayqazi" target="_blank" class="flex items-center p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-gray-700/10 hover:border-gray-700/30 transition group">
                            <i class="fab fa-github w-10 text-xl text-white group-hover:scale-110 transition"></i>
                            <div class="text-left ml-2">
                                <span class="block text-[10px] text-gray-500 uppercase font-bold tracking-tight">GitHub</span>
                                <span class="text-white text-sm font-semibold">@rafayqazi</span>
                            </div>
                        </a>
                    </div>

                    <p class="mt-8 text-[10px] text-gray-600 text-center uppercase tracking-widest font-bold">Checking License Status...</p>
                </div>
            </div>

            <script>
                // Background mein check karein ke admin ne unblock toh nahi kiya
                setInterval(() => {
                    fetch(\'heartbeat_check.php\')
                        .then(() => {
                            // Page reload karein taake agar unblock ho gaya ho toh software khul jaye
                            location.reload();
                        });
                }, 5000); // Har 5 seconds baad check karein
            </script>
        </body>
        </html>';
        exit;
    }
}

// Global block check - Skip for heartbeat script to allow unblocking
if (basename($_SERVER['PHP_SELF']) !== 'heartbeat_check.php') {
    checkSystemBlock();
}

/**
 * --- Hierarchical Unit Helpers ---
 */

/**
 * Recursively calculates the multiplier to get the smallest (base) unit for a SPECIFIC PRODUCT.
 */
function getBaseMultiplier($unit_id_or_name, $product = null) {
    if (!$unit_id_or_name || !$product) return 1;
    
    $full_hierarchy = getUnitHierarchy($product['unit'] ?? '');
    
    $target_idx = -1;
    foreach ($full_hierarchy as $i => $u) {
        if ($u['id'] == $unit_id_or_name || strtolower($u['name']) == strtolower($unit_id_or_name)) {
            $target_idx = $i;
            break;
        }
    }
    
    if ($target_idx === -1) return 1;
    
    $multiplier = 1;
    $f2 = (float)($product['factor_level2'] ?? 1) ?: 1;
    $f3 = (float)($product['factor_level3'] ?? 1) ?: 1;

    // Chain logic: 
    // Top (0): m = f2 * f3
    // Mid (1): m = f3
    // Base (2): m = 1
    if ($target_idx == 0) {
        if (count($full_hierarchy) > 2) $multiplier = $f2 * $f3;
        elseif (count($full_hierarchy) > 1) $multiplier = $f2;
    } elseif ($target_idx == 1) {
        if (count($full_hierarchy) > 2) $multiplier = $f3;
    }
    
    return $multiplier;
}

/**
 * Returns the full chain of units from top to bottom.
 */
function getUnitHierarchy($unit_id_or_name) {
    if (!$unit_id_or_name) return [];
    $units = readCSV('units');
    $chain = [];
    
    // Find the starting unit
    $current = null;
    foreach ($units as $u) {
        if ($u['id'] == $unit_id_or_name || strtolower($u['name']) == strtolower($unit_id_or_name)) {
            $current = $u;
            break;
        }
    }
    
    if (!$current) return [];
    
    // Build chain downwards
    // Since it's a linear chain in units.csv: parent_id links
    $buildChain = function($u) use ($units, &$buildChain, &$chain) {
        $chain[] = $u;
        foreach ($units as $child) {
            if ((int)$child['parent_id'] === (int)$u['id']) {
                $buildChain($child);
                break; 
            }
        }
    };
    
    $buildChain($current);
    return $chain;
}

/**
 * Formats qty (in base units) into a human readable hierarchy for a product.
 * e.g. 145 items -> "1 Ctn, 0 Box, 1 Piece"
 */
function formatStockHierarchy($qty, $product) {
    if (is_string($product)) {
        // Fallback for old calls or simple units
        return "<b>" . (float)$qty . "</b> <span class='text-[10px] uppercase opacity-70'>$product</span>";
    }
    
    $qty = (float)$qty;
    $unit_name = $product['unit'] ?? 'Units';
    if ($qty <= 0) return "0 " . $unit_name;

    $full_hierarchy = getUnitHierarchy($unit_name);
    if (count($full_hierarchy) <= 1) {
         return "<b>" . $qty . "</b> <span class='text-[10px] uppercase opacity-70'>$unit_name</span>";
    }

    $remaining = $qty;
    $parts = [];
    $factors = [];
    
    $f2 = (float)($product['factor_level2'] ?? 1) ?: 1;
    $f3 = (float)($product['factor_level3'] ?? 1) ?: 1;
    
    foreach ($full_hierarchy as $i => $u) {
        $multiplier = getBaseMultiplier($u['id'], $product);
        $count = floor($remaining / $multiplier);
        if ($count > 0) {
            $parts[] = "<b>$count</b> <span class='text-[10px] uppercase opacity-70'>{$u['name']}</span>";
            $remaining = fmod($remaining, $multiplier);
        }

        // Build factor description list
        if ($i == 0 && count($full_hierarchy) > 1) {
             $child_unit = $full_hierarchy[1]['name'];
             $factors[] = "1 " . $u['name'] . " = $f2 $child_unit";
        }
        if ($i == 1 && count($full_hierarchy) > 2) {
             $child_unit = $full_hierarchy[2]['name'];
             $factors[] = "1 " . $u['name'] . " = $f3 $child_unit";
        }
    }
    
    if (empty($parts) && $remaining >= 0) {
        $display = "<b>" . round($remaining, 2) . "</b> <span class='text-[10px] uppercase opacity-70'>$unit_name</span>";
    } else {
        $display = empty($parts) ? "0 $unit_name" : implode(", ", $parts);
    }

    // Always show the absolute base total (requested by user)
    $base_unit = end($full_hierarchy)['name'];
    $display .= " <span class='text-[10px] text-teal-600 font-bold ml-1 italic'>[Total: " . (round($qty, 2) == round($qty, 0) ? (int)$qty : round($qty, 2)) . " $base_unit]</span>";

    // Show conversion factors for clarity
    if (!empty($factors)) {
        $display .= " <div class='text-[9px] text-gray-400 font-medium leading-none mt-0.5'>Factors: " . implode(" | ", $factors) . "</div>";
    }
    
    return $display;
}

/**
 * Builds a nested tree structure from a flat units array.
 */
function buildUnitTree($units, $parentId = 0) {
    $branch = [];
    foreach ($units as $unit) {
        if (($unit['parent_id'] ?? 0) == $parentId) {
            $children = buildUnitTree($units, $unit['id']);
            if ($children) {
                $unit['children'] = $children;
            } else {
                $unit['children'] = [];
            }
            $branch[] = $unit;
        }
    }
    return $branch;
}

/**
 * Renders unit options for a select menu with indentation.
 */
function renderUnitOptions($tree, $level = 0, $selectedVal = null, $useNameAsValue = false) {
    $html = '';
    $prefix = str_repeat('&nbsp;&nbsp;&mdash;&nbsp;', $level);
    foreach ($tree as $unit) {
        $val = $useNameAsValue ? $unit['name'] : $unit['id'];
        $selected = (string)$val === (string)$selectedVal ? 'selected' : '';
        $html .= "<option value='".htmlspecialchars($val)."' $selected>{$prefix} " . htmlspecialchars($unit['name']) . "</option>";
        if (!empty($unit['children'])) {
            $html .= renderUnitOptions($unit['children'], $level + 1, $selectedVal, $useNameAsValue);
        }
    }
    return $html;
}


// --- Unit Hierarchy & Receipt Helper Functions ---

function getUnitHierarchyFromList($unitName, $allUnits) {
    if (!$unitName) return [];
    
    // Find start node
    $startNode = null;
    foreach ($allUnits as $u) {
        if (strcasecmp($u['name'], $unitName) === 0) {
            $startNode = $u;
            break;
        }
    }
    if (!$startNode) return [];

    $root = $startNode;
    // Walk up to root
    while ($root['parent_id'] != 0) {
        $parent = null;
        foreach ($allUnits as $u) {
            if ($u['id'] == $root['parent_id']) {
                $parent = $u;
                break;
            }
        }
        if (!$parent) break;
        $root = $parent;
    }

    // Now walk down from root to build chain (Replicating JS logic: picking first child each step)
    $chain = [];
    $current = $root;
    
    while ($current) {
        $chain[] = $current;
        $next = null;
        foreach ($allUnits as $u) {
            // JS: find(u => parent_id === current.id) returns the FIRST match.
            if ($u['parent_id'] == $current['id']) {
                $next = $u;
                break; 
            }
        }
        if (!$next) break;
        $current = $next;
    }
    
    return $chain;
}

function getBaseMultiplierFromList($unitName, $product, $allUnits) {
    $primaryUnit = $product['unit'] ?? 'Units';
    $chain = getUnitHierarchyFromList($primaryUnit, $allUnits);
    
    $targetIdx = -1;
    foreach ($chain as $idx => $u) {
        if (strcasecmp($u['name'], $unitName) === 0) {
            $targetIdx = $idx;
            break;
        }
    }
    
    if ($targetIdx === -1) return 1;

    $f2 = (float)($product['factor_level2'] ?? $product['f2'] ?? 1);
    $f3 = (float)($product['factor_level3'] ?? $product['f3'] ?? 1);
    if ($f2 <= 0) $f2 = 1;
    if ($f3 <= 0) $f3 = 1;

    if ($targetIdx === 0) {
        if (count($chain) > 2) return $f2 * $f3;
        if (count($chain) > 1) return $f2;
    } elseif ($targetIdx === 1) {
        if (count($chain) > 2) return $f3;
    }
    
    return 1;
}

function detectBestUnit($item, $product, $allUnits) {
    if (isset($item['unit']) && $item['unit'] !== 'Units' && $item['unit'] !== '') {
        return $item['unit'];
    }
    
    if (!$product) return 'Units';
    
    $savedPrice = (float)$item['price_per_unit'];
    $primaryPrice = (float)$product['sell_price'];
    
    $chain = getUnitHierarchyFromList($product['unit'], $allUnits);
    
    // 1. Simple Case
    if (count($chain) <= 1) return $product['unit']; // Default to primary if no hierarchy
    
    // 2. Check Match with Primary
    if (abs($savedPrice - $primaryPrice) < ($primaryPrice * 0.2)) {
        return $product['unit'];
    }
    
    // 3. Check Secondary
    if (count($chain) > 1) {
        $u2 = $chain[1];
        $f2 = (float)($product['factor_level2'] ?? 1);
        if ($f2 > 0) {
            $price2 = $primaryPrice / $f2;
            if (abs($savedPrice - $price2) < ($price2 * 0.2)) {
                return $u2['name'];
            }
        }
    }
    
    // 4. Check Tertiary
    if (count($chain) > 2) {
        $u3 = $chain[2];
        $f2 = (float)($product['factor_level2'] ?? 1);
        $f3 = (float)($product['factor_level3'] ?? 1);
        if ($f2 > 0 && $f3 > 0) {
            $price3 = $primaryPrice / ($f2 * $f3);
            if (abs($savedPrice - $price3) < ($price3 * 0.2)) {
                return $u3['name'];
            }
        }
    }
    
    // 5. Fallback: If price is significantly lower than primary, assume base unit (last one)
    $lastUnit = $chain[count($chain)-1];
    
    // Re-verify logic: if price is closer to base unit price?
    $baseMult = getBaseMultiplierFromList($lastUnit['name'], $product, $allUnits);
    $basePrice = $primaryPrice / $baseMult;
    
    if ($savedPrice < ($basePrice * 1.5)) { // Close to base price
         return $lastUnit['name'];
    }

    return $product['unit'];
}
?>
