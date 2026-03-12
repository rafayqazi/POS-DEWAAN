<?php
date_default_timezone_set('Asia/Karachi');
/**
 * Dynamic Session Management
 * Generates a unique session name based on the application's root path
 * to prevent conflicts between multiple instances on the same domain (e.g., localhost).
 */

// Generate a unique ID based on the absolute path of this file's parent directory
$app_path = realpath(__DIR__ . '/../');
$unique_id = md5($app_path);
$session_name = 'POS_DEWAAN_' . substr($unique_id, 0, 8); // Changed prefix for stability

// Set private session save path to avoid conflicts and XAMPP cleanup
$session_save_path = $app_path . '/data/sessions';
if (is_dir($session_save_path)) {
    ini_set('session.save_path', $session_save_path);
}

// Set session lifetime to 24 hours (86400 seconds)
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Set the session name before starting
session_name($session_name);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
