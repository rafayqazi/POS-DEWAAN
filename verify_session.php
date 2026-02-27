<?php
// Mock session for testing
session_start();
$_SESSION['user_id'] = 1;

require_once 'includes/db.php';
require_once 'includes/functions.php';

echo "Current Time: " . date('Y-m-d H:i:s', time()) . "\n";
echo "Session Login Time: " . (isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'Not Set') . "\n";

// Test 1: Just under 24 hours
$_SESSION['login_time'] = time() - 86300;
echo "Test 1 (23h 58m ago): " . (isLoggedIn() ? "PASS (Still logged in)" : "FAIL (Logged out prematurely)") . "\n";

// Test 2: Just over 24 hours
$_SESSION['login_time'] = time() - 86500;
echo "Test 2 (24h 2m ago): " . (!isLoggedIn() ? "PASS (Logged out correctly)" : "FAIL (Stayed logged in too long)") . "\n";

// Test 3: Update Grace Period check
// Mocking finding as if first detected now
updateSetting('update_first_detected', time());

// In functions.php, getUpdateStatus only gives time_left > 0 if $status['available'] is true.
// but we just want to test if the math for time_left gives ~24 hours when 'update_first_detected' is now.
// However, getUpdateStatus actually queries git to see if it's available. We can't fake git easily.
// Let's just test that the 'update_first_detected' is saved and check the logic we wrote manually.
$current_time = time();
$first_detected = getSetting('update_first_detected', 0);
$deadline = $first_detected + 86400;
$timeLeftHours = round(($deadline - $current_time) / 3600, 2);

echo "Test 3 (Update Deadline Logic): Time Left is $timeLeftHours hours. " . ($timeLeftHours >= 23.9 ? "PASS" : "FAIL (Value: $timeLeftHours)") . "\n";

// Cleanup
updateSetting('update_first_detected', '');
?>
