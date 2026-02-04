<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_sale'])) {
    $sale_id = $_POST['sale_id'];
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
                $all_products[$idx]['stock_quantity'] = (float)$all_products[$idx]['stock_quantity'] + (float)$oi['quantity'];
            }
        }

        // Part B: Deduct New Stock
        foreach($cart as $item) {
            if (isset($p_map[$item['id']])) {
                $idx = $p_map[$item['id']];
                $current = (float)$all_products[$idx]['stock_quantity'];
                $needed = (float)$item['qty'];
                
                if ($current < $needed) {
                    $error_items[] = "{$all_products[$idx]['name']} (Available: $current)";
                    // We don't return false yet, we want to collect all errors
                } else {
                    $all_products[$idx]['stock_quantity'] = $current - $needed;
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
        $sale['total_amount'] = $total_amount;
        $sale['paid_amount'] = $paid_amount;
        $sale['discount'] = $discount;
        $sale['payment_method'] = $payment_method;
        $sale['remarks'] = $remarks;
        $sale['due_date'] = $due_date;
        updateCSV('sales', $sale_id, $sale);
    }

    // 5. Update Sale Items (Clear old, Insert new)
    // ... (rest of step 5)
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
            'price_per_unit' => $item['price'],
            'buy_price' => $buy_price,
            'avg_buy_price' => $avg_buy_price,
            'total_price' => $item['total']
        ]);
    }

    // 6. Update Customer Transaction (if exists)
    $all_txns = readCSV('customer_transactions');
    foreach($all_txns as $tx) {
        if (isset($tx['sale_id']) && $tx['sale_id'] == $sale_id) {
            $tx['date'] = $new_date_only;
            $tx['debit'] = $total_amount;
            $tx['credit'] = $paid_amount;
            $tx['description'] = "Sale #$sale_id Updated - $remarks";
            $tx['due_date'] = $due_date;
            updateCSV('customer_transactions', $tx['id'], $tx);
            break; 
        }
    }

    redirect("../pages/sales_history.php?msg=Sale $sale_id updated successfully");
} else {
    redirect("../pages/sales_history.php");
}
