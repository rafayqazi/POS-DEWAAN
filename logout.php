<?php
session_start();
session_unset();
session_destroy();
header("Location: /POS-DEWAAN/login.php");
exit();
?>
