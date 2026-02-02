<?php
$path = 'c:\xampp\htdocs\POS-DEWAAN\data\users.csv';
echo "Checking file: $path\n";
if (!file_exists($path)) {
    echo "File does not exist.\n";
    exit;
}
$fp = @fopen($path, 'r+');
if ($fp) {
    echo "Success: File opened in r+ mode.\n";
    if (flock($fp, LOCK_EX | LOCK_NB)) {
        echo "Success: Exclusive lock acquired.\n";
        flock($fp, LOCK_UN);
    } else {
        echo "Failed: Could not acquire exclusive lock.\n";
    }
    fclose($fp);
} else {
    $err = error_get_last();
    echo "Failed: " . ($err['message'] ?? 'Unknown error') . "\n";
}
?>
