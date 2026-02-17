<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

/**
 * Backup Database Action
 * Zips the content of the data directory and serves it as a download.
 */

$dataDir = realpath('../data');
if (!$dataDir) {
    $_SESSION['error'] = "Data directory not found.";
    redirect('../pages/backup_restore.php');
}

$zip = new ZipArchive();
$business_name = getSetting('business_name', 'Fashion Shines POS');
$filename = $business_name . " - Backup - " . date("Y-m-d") . ".zip";
$filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dataDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($dataDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();

    // Serve the file
    if (file_exists($filepath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($filepath);
        
        // Delete the temporary file
        unlink($filepath);
        exit;
    } else {
        $_SESSION['error'] = "Failed to create backup file.";
    }
} else {
    $_SESSION['error'] = "Could not open ZipArchive.";
}

redirect('../pages/backup_restore.php');
