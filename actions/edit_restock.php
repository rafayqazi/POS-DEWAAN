<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_id'])) {
    $restock_id = $_POST['restock_id'];
    $new_qty = (float)cleanInput($_POST['quantity']);
    $new_buy_price = (float)cleanInput($_POST['new_buy_price']);
    $new_sell_price = (float)cleanInput($_POST['new_sell_price']);
    $dealer_id = cleanInput($_POST['dealer_id']);
    $amount_paid = (float)cleanInput($_POST['amount_paid']);
    $date = cleanInput($_POST['date']) ?: date('Y-m-d');
    $expiry_date = cleanInput($_POST['expiry_date'] ?? '');
    $remarks = cleanInput($_POST['remarks'] ?? '');

    // 1. Fetch the Old Restock Log
    $old_restock = findCSV('restocks', $restock_id);
    if (!$old_restock) {
        $_SESSION['error'] = "Restock record not found.";
        redirect('../pages/restock_history.php');
    }

    $product_id = $old_restock['product_id'];
    $old_qty = (float)$old_restock['quantity'];
    $old_batch_price = (float)$old_restock['new_buy_price'];

    // 2. Atomic Transaction for Product Update
    $transaction_success = processCSVTransaction('products', function($all_products) use ($product_id, $old_qty, $old_batch_price, $new_qty, $new_buy_price, $new_sell_price, $expiry_date, $remarks) {
        $found_index = -1;
        foreach ($all_products as $i => $p) {
            if ($p['id'] == $product_id) {
                $found_index = $i;
                break;
            }
        }
        if ($found_index === -1) return false;

        $product = $all_products[$found_index];
        $current_qty = (float)$product['stock_quantity'];
        $current_avco = isset($product['avg_buy_price']) ? (float)$product['avg_buy_price'] : (float)$product['buy_price'];

        // --- PART A: REVERT OLD VALUES ---
        // Current Total Value = current_qty * current_avco
        $current_total_value = $current_qty * $current_avco;
        // Value of the old batch = old_qty * old_batch_price
        $old_batch_value = $old_qty * $old_batch_price;
        // Reverted totals
        $reverted_value = $current_total_value - $old_batch_value;
        $reverted_qty = $current_qty - $old_qty;

        // --- PART B: APPLY NEW VALUES ---
        $new_batch_value = $new_qty * $new_buy_price;
        $final_qty = $reverted_qty + $new_qty;
        $final_value = $reverted_value + $new_batch_value;

        if ($final_qty > 0) {
            $final_avco = max(0, $final_value / $final_qty);
        } else {
            $final_avco = $new_buy_price;
        }

        // Update Product
        $all_products[$found_index]['stock_quantity'] = $final_qty;
        $all_products[$found_index]['buy_price'] = $new_buy_price;
        $all_products[$found_index]['avg_buy_price'] = number_format($final_avco, 2, '.', '');
        $all_products[$found_index]['sell_price'] = $new_sell_price;
        
        if(!empty($expiry_date)) $all_products[$found_index]['expiry_date'] = $expiry_date;
        if(!empty($remarks)) $all_products[$found_index]['remarks'] = $remarks;

        return $all_products;
    });

    if (!$transaction_success) {
        $_SESSION['error'] = "Update failed. Product context lost.";
        redirect('../pages/restock_history.php');
    }

    // 3. Update Restock Log
    $dealer_name = "Open Market";
    $is_open_market = ($dealer_id === 'OPEN_MARKET');
    if (!$is_open_market && !empty($dealer_id)) {
        $dealer = findCSV('dealers', $dealer_id);
        $dealer_name = $dealer ? $dealer['name'] : "Unknown Dealer";
    }

    $updated_restock = [
        'quantity' => $new_qty,
        'new_buy_price' => $new_buy_price,
        'new_sell_price' => $new_sell_price,
        'dealer_id' => $dealer_id,
        'dealer_name' => $dealer_name,
        'amount_paid' => $amount_paid,
        'date' => $date,
        'expiry_date' => $expiry_date,
        'remarks' => $remarks
    ];
    updateCSV('restocks', $restock_id, $updated_restock);

    // 4. Update Financial Transactions
    // Delete old dealer transaction linked to this restock
    $transactions = readCSV('dealer_transactions');
    $filtered_transactions = [];
    foreach ($transactions as $t) {
        if (isset($t['restock_id']) && $t['restock_id'] == $restock_id) {
            continue; 
        }
        $filtered_transactions[] = $t;
    }
    
    // Add new dealer transaction if applicable
    if (!empty($dealer_id) && !$is_open_market) {
        $total_cost = $new_buy_price * $new_qty;
        $filtered_transactions[] = [
            'id' => uniqid(), // Generate a safe ID for financial log if insertCSV isn't used here
            'dealer_id' => $dealer_id,
            'type' => 'Purchase (Edited)',
            'debit' => $total_cost,
            'credit' => $amount_paid,
            'description' => "Edited Restock: {$old_restock['product_name']} (Qty: $new_qty)",
            'date' => $date,
            'created_at' => date('Y-m-d H:i:s'),
            'restock_id' => $restock_id
        ];
    }
    writeCSV('dealer_transactions', $filtered_transactions);

    $_SESSION['success'] = "Restock entry updated successfully. Stock and AVCO recalculated.";
    redirect('../pages/restock_history.php');

} else {
    redirect('../pages/restock_history.php');
}
