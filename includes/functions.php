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

function getUpdateStatus() {
    $status = ['available' => false, 'count' => 0, 'branch' => '', 'local' => '', 'remote' => '', 'error' => ''];
    
    // 1. Fetch
    exec('git fetch --all 2>&1', $out, $ret);
    if ($ret !== 0) {
        $status['error'] = "Fetch failed: " . implode(" ", $out);
        return $status;
    }
    
    // 2. Branch
    exec('git rev-parse --abbrev-ref HEAD 2>&1', $out_b, $ret_b);
    $status['branch'] = $out_b[0] ?? 'master';
    
    // 3. Count behind
    exec("git rev-list --count HEAD..origin/" . $status['branch'] . " 2>&1", $out_c, $ret_c);
    $status['count'] = (int)($out_c[0] ?? 0);
    $status['available'] = ($status['count'] > 0);
    
    // 4. Hashes
    exec('git rev-parse HEAD 2>&1', $out_l);
    exec("git rev-parse origin/" . $status['branch'] . " 2>&1", $out_r);
    $status['local'] = substr($out_l[0] ?? '', 0, 7);
    $status['remote'] = substr($out_r[0] ?? '', 0, 7);
    
    return $status;
}

function runUpdate() {
    exec('git rev-parse --abbrev-ref HEAD 2>&1', $out_b);
    $branch = $out_b[0] ?? 'master';
    
    // Attempt pull
    exec("git pull origin $branch 2>&1", $output, $return_var);
    
    $message = implode("\n", $output);
    
    // If pull failed due to local changes, suggest a solution or be more descriptive
    if ($return_var !== 0) {
        if (strpos($message, 'local changes to the following files would be overwritten by merge') !== false) {
            $message = "Update failed: Local changes in the following files would be overwritten:\n" . $message . "\n\nPlease commit or discard your changes before updating.";
        }
    }
    
    return ['success' => ($return_var === 0), 'message' => $message, 'branch' => $branch];
}
?>
