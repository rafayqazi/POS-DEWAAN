<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['txn_id']) || !isset($_GET['cid'])) {
    redirect('../pages/customers.php');
}

$txn_id = $_GET['txn_id'];
$sale_id = $_GET['sale_id'] ?? '';
$cid = $_GET['cid'];

// 1. If it's a sale, restore stock
if (!empty($sale_id)) {
    $sale_items = readCSV('sale_items');
    $items_to_revert = array_filter($sale_items, function($i) use ($sale_id) {
        return $i['sale_id'] == $sale_id;
    });

    if (!empty($items_to_revert)) {
        processCSVTransaction('products', function($all_products) use ($items_to_revert) {
            $product_map = [];
            foreach($all_products as $idx => $p) $product_map[$p['id']] = $idx;

            foreach($items_to_revert as $item) {
                $pid = $item['product_id'];
                if (isset($product_map[$pid])) {
                    $idx = $product_map[$pid];
                    $all_products[$idx]['stock_quantity'] += (float)$item['quantity'];
                }
            }
            return $all_products;
        });
    }

    // 2. Delete Sale Items
    $all_sale_items = readCSV('sale_items');
    $remaining_sale_items = array_filter($all_sale_items, function($i) use ($sale_id) {
        return $i['sale_id'] != $sale_id;
    });
    writeCSV('sale_items', $remaining_sale_items);

    // 3. Delete Sale Header
    deleteCSV('sales', $sale_id);
}

// 4. Delete Customer Transaction
deleteCSV('customer_transactions', $txn_id);

redirect("../pages/customer_ledger.php?id=$cid&msg=Transaction reverted and inventory restored successfully");
