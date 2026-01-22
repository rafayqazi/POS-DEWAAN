<?php
require_once 'includes/db.php';
// session.php (via db.php) already called session_start() with the correct name
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
