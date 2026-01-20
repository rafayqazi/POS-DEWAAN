<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

/**
 * Restore Database Action
 * Unzips an uploaded zip file into the data directory.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    function respond($success, $message, $isAjax) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success, 'message' => $message]);
            exit;
        } else {
            $_SESSION[$success ? 'success' : 'error'] = $message;
            header("Location: ../pages/backup_restore.php");
            exit;
        }
    }

    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(false, "File upload failed with error code: " . $file['error'], $isAjax);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'zip') {
        respond(false, "Invalid file format. Please upload a .zip file.", $isAjax);
    }

    $dataDir = realpath('../data');
    if (!$dataDir) {
        respond(false, "Data directory not found.", $isAjax);
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) === TRUE) {
        $zip->extractTo($dataDir);
        $zip->close();
        respond(true, "Database restored successfully!", $isAjax);
    } else {
        respond(false, "Failed to open ZIP file.", $isAjax);
    }
} else {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
        exit;
    }
    $_SESSION['error'] = "No file uploaded.";
    header("Location: ../pages/backup_restore.php");
    exit;
}
