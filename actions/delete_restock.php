<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restock_id'])) {
    $restock_id = $_POST['restock_id'];
    
    // 1. Fetch the Restock Log
    $restock = findCSV('restocks', $restock_id);
    if (!$restock) {
        $_SESSION['error'] = "Restock record not found.";
        redirect('../pages/restock_history.php');
    }

    $product_id = $restock['product_id'];
    $restock_qty = (float)$restock['quantity'];
    $restock_unit = $restock['unit'] ?? ''; // Might be empty for old records
    $batch_price = (float)$restock['new_buy_price'];

    // 2. Fetch Product Data
    $product = findCSV('products', $product_id);
    if ($product) {
        $current_qty = (float)$product['stock_quantity'];
        
        // Use multiplier for hierarchical units
        $multiplier = getBaseMultiplier($restock_unit ?: $product['unit'], $product);
        $qty_to_remove_base = $restock_qty * $multiplier;
        $price_per_base = $batch_price / $multiplier;

        // 3. Calculate Reversal (Reverting Weighted Average)
        $current_avco = isset($product['avg_buy_price']) ? (float)$product['avg_buy_price'] : (float)$product['buy_price'];
        $current_total_value = $current_qty * $current_avco;
        
        // Value of the batch being removed = Qty (Base) * Price (Base)
        $batch_value = $qty_to_remove_base * $price_per_base;
        
        // Remaining Value
        $remaining_value = $current_total_value - $batch_value;
        $remaining_qty = $current_qty - $qty_to_remove_base;
        
        if ($remaining_qty > 0) {
            $new_rectified_avco = max(0, $remaining_value / $remaining_qty);
        } else {
            // Fallback to old buy price from log if stock hits 0
            $new_rectified_avco = (float)$restock['old_buy_price'];
        }

        // Update Product
        // We revert 'buy_price' to what it was before this restock (old_buy_price from log) to keep "Latest Price" consistent
        // We update 'avg_buy_price' to the rectified AVCO
        $updated_product = [
            'stock_quantity' => $remaining_qty,
            'buy_price' => $restock['old_buy_price'], 
            'avg_buy_price' => number_format($new_rectified_avco, 2, '.', '')
        ];
        updateCSV('products', $product_id, $updated_product);
    }

    // 4. Remove Financial Transactions
    // We need to delete from dealer_transactions where restock_id matches
    $transactions = readCSV('dealer_transactions');
    $new_transactions = [];
    foreach ($transactions as $t) {
        if (isset($t['restock_id']) && $t['restock_id'] == $restock_id) {
            continue; // Skip (Delete) this transaction
        }
        $new_transactions[] = $t;
    }
    writeCSV('dealer_transactions', $new_transactions);

    // 5. Delete Restock Log
    deleteCSV('restocks', $restock_id);

    $_SESSION['success'] = "Restock entry reverted successfully. Stock and Ledger updated.";
    redirect('../pages/restock_history.php');

} else {
    redirect('../pages/restock_history.php');
}
