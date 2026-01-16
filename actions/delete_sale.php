<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (isset($_REQUEST['id'])) {
    $ids = is_array($_REQUEST['id']) ? $_REQUEST['id'] : [$_REQUEST['id']];
    $deleted_count = 0;

    // 1. Load data once
    $all_sales = readCSV('sales');
    $all_sale_items = readCSV('sale_items');
    $products = readCSV('products');

    foreach ($ids as $sale_id) {
        $sale = null;
        foreach($all_sales as $s) {
            if($s['id'] == $sale_id) {
                $sale = $s;
                break;
            }
        }
        
        if (!$sale) continue;

        // 2. Restore Stock
        foreach ($all_sale_items as $item) {
            if ($item['sale_id'] == $sale_id) {
                foreach ($products as &$p) {
                    if ($p['id'] == $item['product_id']) {
                        $p['stock_quantity'] = (int)$p['stock_quantity'] + (int)$item['quantity'];
                        break;
                    }
                }
            }
        }
        
        // 3. Remove from Sales and Sale Items arrays
        $all_sales = array_filter($all_sales, function($s) use ($sale_id) {
            return $s['id'] != $sale_id;
        });
        
        $all_sale_items = array_filter($all_sale_items, function($item) use ($sale_id) {
            return $item['sale_id'] != $sale_id;
        });

        $deleted_count++;
    }
    
    // 4. Save all changes back to CSVs
    writeCSV('products', $products);
    writeCSV('sales', array_values($all_sales), ['id', 'customer_id', 'total_amount', 'paid_amount', 'payment_method', 'sale_date']);
    writeCSV('sale_items', array_values($all_sale_items), ['id', 'sale_id', 'product_id', 'quantity', 'price_per_unit', 'total_price']);
    
    $msg = $deleted_count > 1 ? "$deleted_count sales deleted and stock restored" : "Sale deleted and stock restored";
    $redirect = $_REQUEST['ref'] ?? '../pages/sales_history.php';
    
    // Append msg to redirect URL
    $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
    redirect($redirect . $separator . 'msg=' . urlencode($msg));
} else {
    redirect($_REQUEST['ref'] ?? '../pages/sales_history.php');
}
?>
