<?php
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
        // 1. Remove from Sales and Sale Items arrays
        $all_sales = array_filter($all_sales, function($s) use ($sale_id) {
            return $s['id'] != $sale_id;
        });
        
        $all_sale_items = array_filter($all_sale_items, function($item) use ($sale_id) {
            return $item['sale_id'] != $sale_id;
        });

        $deleted_count++;
    }
    
    // 2. Save all changes back to CSVs
    writeCSV('sales', array_values($all_sales), ['id', 'customer_id', 'total_amount', 'paid_amount', 'payment_method', 'sale_date', 'remarks']);
    writeCSV('sale_items', array_values($all_sale_items), ['id', 'sale_id', 'product_id', 'quantity', 'price_per_unit', 'total_price', 'buy_price', 'avg_buy_price']);
    
    $msg = $deleted_count > 1 ? "$deleted_count sales deleted (No Restocking)" : "Sale deleted (No Restocking)";
    $redirect = $_REQUEST['ref'] ?? '../pages/sales_history.php';
    
    // Append msg to redirect URL
    $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
    redirect($redirect . $separator . 'msg=' . urlencode($msg));
} else {
    redirect($_REQUEST['ref'] ?? '../pages/sales_history.php');
}
?>
