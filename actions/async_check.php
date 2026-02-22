<?php
/**
 * async_check.php
 * 
 * Called via fire-and-forget fetch() from the browser AFTER index.php has already rendered.
 * Runs the tracking heartbeat and update check in the background so they don't delay page load.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Must be logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

header('Content-Type: application/json');

$result = ['heartbeat' => 'skipped', 'update' => null];

// 1. Heartbeat — only run once per session to save bandwidth
if (empty($_SESSION['tracked_today'])) {
    sendTrackingHeartbeat(); // This sets $_SESSION['tracked_today'] = true
    $result['heartbeat'] = 'sent';
}

// 2. Update check — only run if not cached in session (1 hour cache)
$current_time = time() + ($_SESSION['time_offset'] ?? 0);
$last_check = $_SESSION['last_update_check'] ?? 0;
$cache_expired = ($current_time - $last_check) >= 3600;

if ($cache_expired || !isset($_SESSION['cached_update_status'])) {
    $update_status = getUpdateStatus();
    $result['update'] = $update_status;
} else {
    // Return cached status so JS can still show the banner if needed
    $result['update'] = $_SESSION['cached_update_status'] ?? ['available' => false];
    $result['update']['from_cache'] = true;
}

echo json_encode($result);
