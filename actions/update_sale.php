<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_sale'])) {
    $sale_id = $_POST['sale_id'];
    $customer_id = $_POST['customer_id'] ?? '';
    $cart = json_decode($_POST['cart_data'], true);
    $paid_amount = (float)$_POST['paid_amount'];
    $total_amount = (float)$_POST['total_amount'];
    $payment_method = $_POST['payment_method'];
    $remarks = cleanInput($_POST['remarks']);
    $discount = (float)($_POST['discount'] ?? 0);

    // 1. Load Old Data
    $all_sale_items = readCSV('sale_items');
    $old_items = array_filter($all_sale_items, function($si) use ($sale_id) {
        return $si['sale_id'] == $sale_id;
    });

    // 2. Consolidated Stock Transaction (Restore Old then Deduct New)
    $error_items = [];
    $final_product_list = [];
    $transaction_success = processCSVTransaction('products', function($all_products) use ($old_items, $cart, &$error_items, &$final_product_list) {
        $p_map = [];
        foreach($all_products as $idx => $p) $p_map[$p['id']] = $idx;

        // Part A: Restore Old Stock
        foreach($old_items as $oi) {
            if (isset($p_map[$oi['product_id']])) {
                $idx = $p_map[$oi['product_id']];
                $product = $all_products[$idx];
                $multiplier = getBaseMultiplier($oi['unit'] ?? $product['unit'], $product);
                $all_products[$idx]['stock_quantity'] = (float)$all_products[$idx]['stock_quantity'] + ((float)$oi['quantity'] * $multiplier);
            }
        }

        // Part B: Deduct New Stock
        foreach($cart as $item) {
            if (isset($p_map[$item['id']])) {
                $idx = $p_map[$item['id']];
                $product = $all_products[$idx];
                $current = (float)$all_products[$idx]['stock_quantity'];
                
                $multiplier = getBaseMultiplier($item['unit'] ?? $product['unit'], $product);
                $needed_base = (float)$item['qty'] * $multiplier;
                
                if ($current < $needed_base) {
                    $error_items[] = "{$product['name']} (Available: $current)";
                } else {
                    $all_products[$idx]['stock_quantity'] = $current - $needed_base;
                }
            }
        }

        if (!empty($error_items)) return false; // Fail transaction if any item exceeds stock
        $final_product_list = $all_products;
        return $all_products;
    });

    if (!$transaction_success) {
        redirect("../pages/edit_sale.php?id=$sale_id&error=" . urlencode("Stock limit exceeded for: " . implode(', ', $error_items)));
        exit;
    }

    $due_date = $_POST['due_date'] ?? '';
    $new_date_only = $_POST['sale_date'];

    // 4. Update Sale Record
    $sale = findCSV('sales', $sale_id);
    if ($sale) {
        $old_time = date('H:i:s', strtotime($sale['sale_date']));
        $sale['sale_date'] = $new_date_only . ' ' . $old_time;
        $sale['customer_id'] = $customer_id; // Added customer_id update
        $sale['total_amount'] = $total_amount;
        $sale['paid_amount'] = $paid_amount;
        $sale['discount'] = $discount;
        $sale['payment_method'] = $payment_method;
        $sale['remarks'] = $remarks;
        $sale['due_date'] = $due_date;
        updateCSV('sales', $sale_id, $sale);
    }

    // 5. Update Sale Items (Clear old, Insert new)
    $new_sale_items_list = array_filter($all_sale_items, function($si) use ($sale_id) {
        return $si['sale_id'] != $sale_id;
    });
    writeCSV('sale_items', $new_sale_items_list);

    foreach($cart as $item) {
        $buy_price = 0; $avg_buy_price = 0;
        foreach($final_product_list as $fp) {
            if ($fp['id'] == $item['id']) {
                $buy_price = $fp['buy_price'];
                $avg_buy_price = isset($fp['avg_buy_price']) ? $fp['avg_buy_price'] : $fp['buy_price'];
                break;
            }
        }
        insertCSV('sale_items', [
            'sale_id' => $sale_id,
            'product_id' => $item['id'],
            'quantity' => $item['qty'],
            'unit' => $item['unit'], // Store the unit used in this sale
            'price_per_unit' => $item['price'],
            'buy_price' => $buy_price,
            'avg_buy_price' => $avg_buy_price,
            'total_price' => $item['total']
        ]);
    }

    // 6. Update/Sync Customer Transaction
    $all_txns = readCSV('customer_transactions');
    $txn_found = false;
    foreach($all_txns as $tx) {
        if (isset($tx['sale_id']) && $tx['sale_id'] == $sale_id) {
            $txn_found = true;
            if (empty($customer_id)) {
                // Now Walk-in: Delete Transaction
                deleteCSV('customer_transactions', $tx['id']);
            } else {
                // Update existing transaction
                $tx['customer_id'] = $customer_id;
                $tx['date'] = $new_date_only;
                $tx['debit'] = $total_amount;
                $tx['credit'] = $paid_amount;
                $tx['description'] = "Sale #$sale_id Updated - $remarks";
                $tx['due_date'] = $due_date;
                updateCSV('customer_transactions', $tx['id'], $tx);
            }
            break; 
        }
    }

    // If transaction wasn't found but a customer is now assigned, create it
    if (!$txn_found && !empty($customer_id)) {
        insertCSV('customer_transactions', [
            'customer_id' => $customer_id,
            'type' => 'Sale',
            'debit' => $total_amount,
            'credit' => $paid_amount,
            'description' => "Sale #$sale_id Updated (Assigned Customer) - $remarks",
            'date' => $new_date_only,
            'created_at' => date('Y-m-d H:i:s'),
            'sale_id' => $sale_id,
            'due_date' => $due_date
        ]);
    }

    redirect("../pages/sales_history.php?msg=Sale $sale_id updated successfully");
} else {
    redirect("../pages/sales_history.php");
}
