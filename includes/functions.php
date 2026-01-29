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

function getUpdateStatus($force_fetch = false) {
    // 0. Use session cache if available and not forced
    if (!$force_fetch && isset($_SESSION['last_update_check']) && (time() - $_SESSION['last_update_check'] < 3600)) {
        if (isset($_SESSION['cached_update_status'])) {
            return $_SESSION['cached_update_status'];
        }
    }

    $status = ['available' => false, 'count' => 0, 'branch' => '', 'local' => '', 'remote' => '', 'error' => ''];
    
    // 1. Identify current branch
    exec('git rev-parse --abbrev-ref HEAD 2>&1', $out_b, $ret_b);
    if ($ret_b !== 0) {
        $status['error'] = "Could not identify branch: " . implode(" ", $out_b);
        return $status;
    }
    $status['branch'] = trim($out_b[0] ?? 'master');
    
    // 2. Explicit Fetch from origin
    exec('git fetch origin ' . $status['branch'] . ' 2>&1', $out, $ret);
    if ($ret !== 0) {
        $status['error'] = "Fetch failed: " . implode(" ", $out);
        // If fetch fails, we still return the basic status (maybe we are offline)
        // But we won't cache an error status as "success" for long
        return $status;
    }
    
    // 3. Hashes & Count
    exec('git rev-parse HEAD 2>&1', $out_l);
    exec("git rev-parse origin/" . $status['branch'] . " 2>&1", $out_r);
    $status['local'] = substr(trim($out_l[0] ?? ''), 0, 7);
    $status['remote'] = substr(trim($out_r[0] ?? ''), 0, 7);
    
    // 4. Count behind
    exec("git rev-list --count HEAD..origin/" . $status['branch'] . " 2>&1", $out_c);
    $status['count'] = (int)($out_c[0] ?? 0);
    $status['available'] = ($status['count'] > 0 || ($status['local'] !== $status['remote'] && $status['remote'] != ''));
    
    // 5. Track Detection Time for Grace Period
    if ($status['available']) {
        $first_detected = getSetting('update_first_detected', '');
        if (empty($first_detected)) {
            $first_detected = time();
            updateSetting('update_first_detected', $first_detected);
        }
        
        $status['first_detected'] = (int)$first_detected;
        $status['deadline'] = $status['first_detected'] + 86400; // 24 hours later
        $status['overdue'] = (time() > $status['deadline']);
        $status['time_left'] = max(0, $status['deadline'] - time());
    }
    
    // Cache the result
    $_SESSION['last_update_check'] = time();
    $_SESSION['cached_update_status'] = $status;
    
    return $status;
}

function runUpdate() {
    exec('git rev-parse --abbrev-ref HEAD 2>&1', $out_b);
    $branch = trim($out_b[0] ?? 'master');
    
    // Force reset to origin/branch if needed, or just pull
    // Using pull for safety but with explicit origin branch
    exec("git pull origin $branch 2>&1", $output, $return_var);
    
    $message = implode("\n", $output);
    
    if ($return_var !== 0) {
        if (strpos($message, 'local changes to the following files would be overwritten by merge') !== false) {
            $message = "Update failed: Local changes would be overwritten. Please commit or discard changes.\n\nFiles:\n" . $message;
        }
    }
    
    return ['success' => ($return_var === 0), 'message' => $message, 'branch' => $branch];
}
?>
