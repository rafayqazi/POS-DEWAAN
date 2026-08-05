<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_dealer_return'])) {
    $restock_id = cleanInput($_POST['restock_id'] ?? '');
    $return_qty = (float)cleanInput($_POST['return_qty'] ?? 0);
    $remarks = cleanInput($_POST['remarks'] ?? '');
    $total_refund = (float)cleanInput($_POST['total_refund'] ?? 0);

    if (empty($restock_id) || $return_qty <= 0 || $total_refund <= 0) {
        redirect("../pages/return_product_dealer.php?restock_id=$restock_id&error=" . urlencode("Please enter a valid return quantity."));
    }

    // 1. Fetch Restock Data
    $restock = findCSV('restocks', $restock_id);
    if (!$restock) {
        redirect("../pages/return_product_dealer.php?error=" . urlencode("Restock entry not found."));
    }

    $restock_qty = (float)$restock['quantity'];
    $already_returned = (float)($restock['returned_qty'] ?? 0);
    $available_to_return = max(0, $restock_qty - $already_returned);

    if ($return_qty > $available_to_return) {
        redirect("../pages/return_product_dealer.php?restock_id=$restock_id&error=" . urlencode("Return quantity exceeds remaining returnable quantity ($available_to_return)."));
    }

    $product_id = $restock['product_id'];
    $dealer_id = $restock['dealer_id'] ?? '';
    $dealer_name = $restock['dealer_name'] ?? '';
    $unit = $restock['unit'] ?? '';
    $buy_price = (float)$restock['new_buy_price'];

    // 2. Transactional Stock Deduction from Products
    $transaction_success = processCSVTransaction('products', function($all_products) use ($product_id, $return_qty, $unit) {
        $found_idx = -1;
        foreach ($all_products as $idx => $p) {
            if ($p['id'] == $product_id) {
                $found_idx = $idx;
                break;
            }
        }

        if ($found_idx === -1) return false;

        $product = $all_products[$found_idx];
        $restock_unit = !empty($unit) ? $unit : ($product['unit'] ?? '');
        $multiplier = getBaseMultiplier($restock_unit, $product);
        $qty_base_to_deduct = $return_qty * $multiplier;

        $current_stock = (float)$product['stock_quantity'];
        $all_products[$found_idx]['stock_quantity'] = max(0, $current_stock - $qty_base_to_deduct);

        return $all_products;
    });

    if ($transaction_success) {
        // 3. Record Dealer Return Event in dealer_returns
        $dealer_return_id = insertCSV('dealer_returns', [
            'restock_id' => $restock_id,
            'dealer_id' => $dealer_id,
            'dealer_name' => $dealer_name,
            'product_id' => $product_id,
            'product_name' => $restock['product_name'] ?? '',
            'quantity' => $return_qty,
            'unit' => $unit,
            'price_per_unit' => $buy_price,
            'total_refund' => $total_refund,
            'remarks' => $remarks,
            'date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 4. Record Item in dealer_return_items
        insertCSV('dealer_return_items', [
            'dealer_return_id' => $dealer_return_id,
            'product_id' => $product_id,
            'quantity' => $return_qty,
            'price_per_unit' => $buy_price,
            'total_price' => $total_refund
        ]);

        // 5. Update Restock Record returned_qty
        $restock['returned_qty'] = $already_returned + $return_qty;
        updateCSV('restocks', $restock_id, $restock);

        // 6. Update Dealer Ledger if Dealer is set
        if (!empty($dealer_id) && $dealer_id !== 'OPEN_MARKET') {
            insertCSV('dealer_transactions', [
                'dealer_id' => $dealer_id,
                'type' => 'Return',
                'debit' => 0,
                'credit' => $total_refund,
                'description' => "Return to Dealer from Restock #$restock_id ({$restock['product_name']} x $return_qty) - $remarks",
                'date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s'),
                'restock_id' => $restock_id,
                'dealer_return_id' => $dealer_return_id
            ]);
        }

        redirect("../pages/return_product_dealer.php?restock_id=$restock_id&return_id=$dealer_return_id&msg=" . urlencode("Dealer return processed successfully. Stock deducted and dealer ledger adjusted."));
    } else {
        redirect("../pages/return_product_dealer.php?restock_id=$restock_id&error=" . urlencode("Failed to process stock deduction. Product not found or database busy."));
    }
} else {
    redirect("../pages/return_product_dealer.php");
}
