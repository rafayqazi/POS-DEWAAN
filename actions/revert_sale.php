<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (isset($_REQUEST['id'])) {
    $ids = is_array($_REQUEST['id']) ? $_REQUEST['id'] : [$_REQUEST['id']];
    $reverted_count = 0;

    // Load data
    $all_sales = readCSV('sales');
    $all_sale_items = readCSV('sale_items');
    $products = readCSV('products');
    $all_customer_txns = readCSV('customer_transactions');

    foreach ($ids as $sale_id) {
        $sale = null;
        foreach($all_sales as $s) {
            if($s['id'] == $sale_id) {
                $sale = $s;
                break;
            }
        }
        
        if (!$sale) continue;

        // 1. Restore Stock
        foreach ($all_sale_items as $item) {
            if ($item['sale_id'] == $sale_id) {
                foreach ($products as &$p) {
                    if ($p['id'] == $item['product_id']) {
                        $multiplier = getBaseMultiplier($item['unit'] ?? $p['unit'], $p);
                        $p['stock_quantity'] = (float)$p['stock_quantity'] + ((float)$item['quantity'] * $multiplier);
                        break;
                    }
                }
            }
        }
        
        // 2. Remove from Customer Transactions (Connectivity)
        $all_customer_txns = array_filter($all_customer_txns, function($t) use ($sale_id) {
            return !(isset($t['sale_id']) && $t['sale_id'] == $sale_id);
        });

        // 3. Remove from Sales and Sale Items arrays
        $all_sales = array_filter($all_sales, function($s) use ($sale_id) {
            return $s['id'] != $sale_id;
        });
        
        $all_sale_items = array_filter($all_sale_items, function($item) use ($sale_id) {
            return $item['sale_id'] != $sale_id;
        });

        $reverted_count++;
    }
    
    // Save all changes
    writeCSV('products', $products);
    writeCSV('customer_transactions', array_values($all_customer_txns));
    writeCSV('sales', array_values($all_sales));
    writeCSV('sale_items', array_values($all_sale_items));
    
    $msg = $reverted_count > 1 ? "$reverted_count sales reverted and stock restored" : "Sale reverted and stock restored";
    $redirect = $_REQUEST['ref'] ?? '../pages/sales_history.php';
    
    $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
    redirect($redirect . $separator . 'msg=' . urlencode($msg));
} else {
    redirect($_REQUEST['ref'] ?? '../pages/sales_history.php');
}
?>
