<?php
/**
 * Dynamic Session Management
 * Generates a unique session name based on the application's root path
 * to prevent conflicts between multiple instances on the same domain (e.g., localhost).
 */

// Generate a unique ID based on the absolute path of this file's parent directory
$app_path = realpath(__DIR__ . '/../');
$unique_id = md5($app_path);
$session_name = 'FASHION_SHINES_' . substr($unique_id, 0, 8);

// Set session lifetime to 24 hours (86400 seconds)
ini_set('session.gc_maxlifetime', 86400);
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
