<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_return'])) {
    $sale_id = $_POST['sale_id'];
    $return_qtys = $_POST['return_qty']; // Array: sale_item_id => quantity to return
    $remarks = cleanInput($_POST['remarks'] ?? '');
    $total_refund = (float)$_POST['total_refund'];

    if ($total_refund <= 0) {
        redirect("../pages/return_product.php?sale_id=$sale_id&error=" . urlencode("No items selected for return."));
    }

    // 1. Fetch Sale Data
    $sale = findCSV('sales', $sale_id);
    if (!$sale) {
        redirect("../pages/return_product.php?error=" . urlencode("Sale not found."));
    }

    $customer_id = $sale['customer_id'];

    // 2. Process Stock Recovery & Update Sale Items
    $all_sale_items = readCSV('sale_items');
    $items_to_update = [];
    
    // Use transaction for products to ensure stock consistency
    $transaction_success = processCSVTransaction('products', function($all_products) use ($return_qtys, $all_sale_items, &$items_to_update) {
        $p_map = [];
        foreach($all_products as $idx => $p) $p_map[$p['id']] = $idx;

        foreach ($return_qtys as $item_id => $qty) {
            $qty = (float)$qty;
            if ($qty <= 0) continue;

            // Find the sale item record
            foreach ($all_sale_items as &$si) {
                if ($si['id'] == $item_id) {
                    $pid = $si['product_id'];
                    
                    // Increment Product Stock
                    if (isset($p_map[$pid])) {
                        $idx = $p_map[$pid];
                        $all_products[$idx]['stock_quantity'] = (float)$all_products[$idx]['stock_quantity'] + $qty;
                    }
                    
                    // Prepare updated sale item (we'll save this after transaction)
                    $si['returned_qty'] = (float)($si['returned_qty'] ?? 0) + $qty;
                    // Adjust total price for this item row (optional, but keep it consistent)
                    // We don't change original total_price here to keep historical records, 
                    // but we will adjust the SALE total.
                    $items_to_update[] = $si;
                    break;
                }
            }
        }
        return $all_products;
    });

    if ($transaction_success) {
        // 3. Record Return Event
        $return_id = insertCSV('returns', [
            'sale_id' => $sale_id,
            'customer_id' => $customer_id,
            'total_refund' => $total_refund,
            'remarks' => $remarks,
            'date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 4. Update Sale Items in CSV & Record Return Items
        foreach ($items_to_update as $updated_si) {
            updateCSV('sale_items', $updated_si['id'], $updated_si);
            
            // Record this item in return_items (only if it was part of THIS return)
            // The $return_qtys array has item_id => qty
            $this_return_qty = (float)($return_qtys[$updated_si['id']] ?? 0);
            if ($this_return_qty > 0) {
                insertCSV('return_items', [
                    'return_id' => $return_id,
                    'product_id' => $updated_si['product_id'],
                    'quantity' => $this_return_qty,
                    'price_per_unit' => $updated_si['price_per_unit'],
                    'total_price' => $this_return_qty * (float)$updated_si['price_per_unit']
                ]);
            }
        }

        // 5. Update Original Sale Total
        $sale['total_amount'] = (float)$sale['total_amount'] - $total_refund;
        updateCSV('sales', $sale_id, $sale);

        // 6. Update Customer Ledger
        if (!empty($customer_id)) {
            insertCSV('customer_transactions', [
                'customer_id' => $customer_id,
                'type' => 'Return',
                'debit' => 0,
                'credit' => $total_refund,
                'description' => "Return from Sale #$sale_id - $remarks",
                'date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s'),
                'sale_id' => $sale_id,
                'return_id' => $return_id
            ]);
        }

        redirect("../pages/return_product.php?sale_id=$sale_id&return_id=$return_id&msg=" . urlencode("Return processed successfully. Stock updated and ledger adjusted."));
    } else {
        redirect("../pages/return_product.php?sale_id=$sale_id&error=" . urlencode("Failed to process transaction."));
    }
} else {
    redirect("../pages/return_product.php");
}
