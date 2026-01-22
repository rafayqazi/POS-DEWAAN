<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = cleanInput($_POST['product_id']);
    $add_quantity = (float)cleanInput($_POST['quantity']);
    $new_buy_price = (float)cleanInput($_POST['new_buy_price']);
    $new_sell_price = (float)cleanInput($_POST['new_sell_price']);
    $dealer_id = cleanInput($_POST['dealer_id']);
    $amount_paid = (float)cleanInput($_POST['amount_paid']);
    $date = cleanInput($_POST['date']) ?: date('Y-m-d');
    $expiry_date = cleanInput($_POST['expiry_date'] ?? '');
    $remarks = cleanInput($_POST['remarks'] ?? '');

    // Validation: Ensure Sell Price >= Buy Price (Prevent Loss)
    if ($new_buy_price > $new_sell_price) {
        $_SESSION['error'] = "Error: Buy Price ($new_buy_price) cannot be greater than Sell Price ($new_sell_price).";
        redirect('../pages/inventory.php');
    }

    // 1. Transactional Update of Product (AVCO & Stock)
    $restock_log_data = []; // To capture data for the log
    
    $transaction_success = processCSVTransaction('products', function($all_products) use ($product_id, $add_quantity, $new_buy_price, $new_sell_price, $expiry_date, $remarks, &$restock_log_data) {
        $found_index = -1;
        foreach ($all_products as $i => $p) {
            if ($p['id'] == $product_id) {
                $found_index = $i;
                break;
            }
        }
        
        if ($found_index === -1) return false; // Product not found
        
        $product = $all_products[$found_index];
        $old_stock = (float)$product['stock_quantity'];
        $old_buy_price = (float)$product['buy_price'];
        $old_sell_price = (float)$product['sell_price'];
        
        // Calculate AVCO
        $current_avco = isset($product['avg_buy_price']) ? (float)$product['avg_buy_price'] : $old_buy_price;
        
        $total_old_value = $old_stock * $current_avco;
        $total_new_value = $add_quantity * $new_buy_price;
        $total_quantity = $old_stock + $add_quantity;
        
        $avg_buy_price = ($total_quantity > 0) ? ($total_old_value + $total_new_value) / $total_quantity : $new_buy_price;
        
        // Update Product
        $all_products[$found_index]['stock_quantity'] = $total_quantity;
        $all_products[$found_index]['buy_price'] = $new_buy_price;
        $all_products[$found_index]['avg_buy_price'] = number_format($avg_buy_price, 2, '.', '');
        $all_products[$found_index]['sell_price'] = $new_sell_price;
        // Update expiry and remarks to the latest one
        if(!empty($expiry_date)) $all_products[$found_index]['expiry_date'] = $expiry_date;
        if(!empty($remarks)) $all_products[$found_index]['remarks'] = $remarks;
        
        // Export data for logging
        $restock_log_data = [
            'product_name' => $product['name'],
            'old_buy_price' => $old_buy_price,
            'old_sell_price' => $old_sell_price
        ];
        
        return $all_products;
    });

    if (!$transaction_success) {
        $_SESSION['error'] = "Restock failed. Product not found or database busy.";
        redirect('../pages/inventory.php');
    }
    
    // Extract captured data
    $product_name = $restock_log_data['product_name'];
    $old_buy_price = $restock_log_data['old_buy_price'];
    $old_sell_price = $restock_log_data['old_sell_price'];

    // 3. Log to restocks.csv
    $dealer_name = "";
    $is_open_market = ($dealer_id === 'OPEN_MARKET');

    if ($is_open_market) {
        $dealer_name = "Open Market";
    } elseif (!empty($dealer_id)) {
        $dealer = findCSV('dealers', $dealer_id);
        $dealer_name = $dealer ? $dealer['name'] : "";
    }

    $restock_data = [
        'product_id' => $product_id,
        'product_name' => $product_name,
        'quantity' => $add_quantity,
        'new_buy_price' => $new_buy_price,
        'old_buy_price' => $old_buy_price,
        'new_sell_price' => $new_sell_price,
        'old_sell_price' => $old_sell_price,
        'dealer_id' => $dealer_id,
        'dealer_name' => $dealer_name,
        'amount_paid' => $amount_paid,
        'amount_paid' => $amount_paid,
        'date' => $date,
        'expiry_date' => $expiry_date,
        'remarks' => $remarks,
        'created_at' => date('Y-m-d H:i:s')
    ];
    // insertCSV returns the generated ID
    $restock_id = insertCSV('restocks', $restock_data);

    // 4. Handle Dealer Transaction if payment/dealer is involved
    if (!empty($dealer_id) && !$is_open_market) {
        $total_cost = $new_buy_price * $add_quantity;
        
        // Log Consolidated Transaction
        $transaction = [
            'dealer_id' => $dealer_id,
            'type' => 'Purchase',
            'debit' => $total_cost,
            'credit' => $amount_paid,
            'description' => "Restock: {$product_name} (Qty: $add_quantity)",
            'date' => $date,
            'created_at' => date('Y-m-d H:i:s'),
            'restock_id' => $restock_id
        ];
        insertCSV('dealer_transactions', $transaction);
    }

    $_SESSION['success'] = "Inventory restocked successfully!";
    redirect('../pages/inventory.php');
} else {
    redirect('../pages/inventory.php');
}
