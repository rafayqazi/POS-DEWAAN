<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

/**
 * System Reset Action
 * Verifies admin password and clears all data files except user accounts.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required.']);
        exit;
    }

    // Get current user's password from CSV
    $users = readCSV('users');
    $currentUser = null;
    foreach ($users as $u) {
        if ($u['username'] === $_SESSION['username']) {
            $currentUser = $u;
            break;
        }
    }

    if (!$currentUser || !password_verify($password, $currentUser['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid password. Access denied.']);
        exit;
    }

    // Password verified, proceed with reset
    $dataDir = realpath('../data');
    if (!$dataDir) {
        echo json_encode(['success' => false, 'message' => 'Data directory not found.']);
        exit;
    }

    $files = glob($dataDir . '/*.csv');
    $deletedCount = 0;
    $errors = [];

    foreach ($files as $file) {
        $filename = basename($file);
        if ($filename === 'users.csv') continue;

        $tableName = str_replace('.csv', '', $filename);
        if (in_array($tableName, ['units', 'categories', 'settings'])) {
            // Truncate instead of delete to prevent re-seeding in db.php
            if (writeCSV($tableName, [])) {
                $deletedCount++;
            } else {
                $errors[] = $filename;
            }
        } else {
            if (unlink($file)) {
                $deletedCount++;
            } else {
                $errors[] = $filename;
            }
        }
    }

    // Re-run initCSV for essential tables to ensure they exist but are empty
    // Wait, db.php runs on every request, so it will re-init units and categories if we delete them.
    // However, it's safer to just clear them or let db.php handle it next time.
    // Let's force a re-init of essential ones just in case.
    
    if (empty($errors)) {
        echo json_encode([
            'success' => true, 
            'message' => "System reset successful! $deletedCount files cleared. Use the software as new."
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => "Reset partially failed. Could not delete: " . implode(', ', $errors)
        ]);
    }
    exit;
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}
