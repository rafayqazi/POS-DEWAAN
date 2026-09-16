<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? ($method === 'POST' ? 'sync' : 'preview');

// Preload data
$products = readCSV('products');
$restocks = readCSV('restocks');
$sale_items = readCSV('sale_items');
$dealer_returns = readCSV('dealer_return_items');

// Build products map
$p_map = [];
foreach ($products as $idx => $p) {
    $p_map[$p['id']] = [
        'idx' => $idx,
        'p' => $p,
        'in' => 0,
        'out' => 0,
        'dealer_return' => 0,
        'current' => (float)($p['stock_quantity'] ?? 0)
    ];
}

// Calculate Restocks IN
foreach ($restocks as $r) {
    $pid = $r['product_id'];
    if (isset($p_map[$pid])) {
        $p = $p_map[$pid]['p'];
        $u = !empty($r['unit']) ? $r['unit'] : ($p['unit'] ?? '');
        $q = (float)$r['quantity'] * getBaseMultiplier($u, $p);
        $p_map[$pid]['in'] += $q;
    }
}

// Calculate Sales OUT
foreach ($sale_items as $si) {
    $pid = $si['product_id'];
    if (isset($p_map[$pid])) {
        $p = $p_map[$pid]['p'];
        $u = !empty($si['unit']) ? $si['unit'] : ($p['unit'] ?? '');
        $q = (float)$si['quantity'] * getBaseMultiplier($u, $p);
        $ret = (float)($si['returned_qty'] ?? 0) * getBaseMultiplier($u, $p);
        $p_map[$pid]['out'] += max(0, $q - $ret);
    }
}

// Calculate Dealer Returns OUT
foreach ($dealer_returns as $dr) {
    $pid = $dr['product_id'];
    if (isset($p_map[$pid])) {
        $p = $p_map[$pid]['p'];
        $q = (float)($dr['quantity'] ?? 0);
        $p_map[$pid]['dealer_return'] += $q;
    }
}

$discrepancies = [];
foreach ($p_map as $pid => $data) {
    $expected = $data['in'] - $data['out'] - $data['dealer_return'];
    $diff = $data['current'] - $expected;
    if (abs($diff) > 0.001) {
        $discrepancies[] = [
            'id' => $pid,
            'name' => $data['p']['name'],
            'unit' => $data['p']['unit'],
            'total_in' => $data['in'],
            'total_out' => $data['out'],
            'dealer_return' => $data['dealer_return'],
            'expected' => $expected,
            'current' => $data['current'],
            'diff' => $diff
        ];
    }
}

if ($action === 'preview') {
    echo json_encode([
        'status' => 'success',
        'total_products' => count($products),
        'discrepancy_count' => count($discrepancies),
        'discrepancies' => $discrepancies
    ]);
    exit;
}

if ($action === 'sync') {
    $target_id = $_POST['product_id'] ?? 'all';
    $updated_count = 0;
    
    $success = processCSVTransaction('products', function($all_products) use ($p_map, $target_id, &$updated_count) {
        foreach ($all_products as &$p) {
            $pid = $p['id'];
            if (!isset($p_map[$pid])) continue;
            if ($target_id !== 'all' && $pid != $target_id) continue;
            
            $expected = $p_map[$pid]['in'] - $p_map[$pid]['out'] - $p_map[$pid]['dealer_return'];
            $current = (float)($p['stock_quantity'] ?? 0);
            if (abs($current - $expected) > 0.001) {
                $p['stock_quantity'] = (string)$expected;
                $updated_count++;
            }
        }
        return $all_products;
    });

    if ($success) {
        echo json_encode([
            'status' => 'success',
            'message' => "Successfully synchronized {$updated_count} product(s).",
            'updated_count' => $updated_count
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update stock in database.'
        ]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
