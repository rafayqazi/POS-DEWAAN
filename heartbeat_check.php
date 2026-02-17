<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// This script is called via AJAX to perform the heartbeat in the background
// Run even if not logged in so we can detect UNBLOCK status
sendTrackingHeartbeat();
echo "Check Completed";
?>
