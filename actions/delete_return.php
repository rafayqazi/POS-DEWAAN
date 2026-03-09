<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (isset($_GET['id'])) {
    $return_id = $_GET['id'];

    // 1. Fetch Return Data
    $return = findCSV('returns', $return_id);
    if (!$return) {
        redirect("../pages/reports.php?error=" . urlencode("Return record not found."));
    }

    $sale_id = $return['sale_id'];
    $customer_id = $return['customer_id'];
    $total_refund = (float)$return['total_refund'];

    // 2. Fetch Return Items
    $all_return_items = readCSV('return_items');
    $this_return_items = array_filter($all_return_items, function($ri) use ($return_id) {
        return $ri['return_id'] == $return_id;
    });

    // 3. Revert Product Stock
    $transaction_success = processCSVTransaction('products', function($all_products) use ($this_return_items) {
        $p_map = [];
        foreach($all_products as $idx => $p) $p_map[$p['id']] = $idx;

        foreach ($this_return_items as $ri) {
            $pid = $ri['product_id'];
            $qty = (float)$ri['quantity'];
            
            if (isset($p_map[$pid])) {
                $idx = $p_map[$pid];
                // Subtract returned qty from stock (Reverting the return)
                $all_products[$idx]['stock_quantity'] = (float)$all_products[$idx]['stock_quantity'] - $qty;
            }
        }
        return $all_products;
    });

    if ($transaction_success) {
        // 4. Update Original Sale Items (Decrement returned_qty)
        $all_sale_items = readCSV('sale_items');
        foreach ($this_return_items as $ri) {
            foreach ($all_sale_items as &$si) {
                // Return items don't store the exact sale_item_id, but the return_id and product_id.
                // In process_return.php, it looks like it updates by sale_item_id.
                // We need to match by sale_id and product_id to be safe, or just find the one that has returned_qty > 0.
                if ($si['sale_id'] == $sale_id && $si['product_id'] == $ri['product_id']) {
                    $si['returned_qty'] = (float)($si['returned_qty'] ?? 0) - (float)$ri['quantity'];
                    updateCSV('sale_items', $si['id'], $si);
                    break;
                }
            }
        }

        // 5. Restore Sale Total
        $sale = findCSV('sales', $sale_id);
        if ($sale) {
            $sale['total_amount'] = (float)$sale['total_amount'] + $total_refund;
            updateCSV('sales', $sale_id, $sale);
        }

        // 6. Delete Customer Ledger Entry (the Return credit)
        if (!empty($customer_id)) {
            $all_txns = readCSV('customer_transactions');
            foreach ($all_txns as $tx) {
                // Find the transaction that was created for this return
                if (isset($tx['return_id']) && $tx['return_id'] == $return_id) {
                    deleteCSV('customer_transactions', $tx['id']);
                    break;
                }
            }
        }

        // 7. Delete Return Items & Return Record
        foreach ($this_return_items as $ri) {
            deleteCSV('return_items', $ri['id']);
        }
        deleteCSV('returns', $return_id);

        redirect("../pages/reports.php?msg=" . urlencode("Return deleted successfully. Stock adjusted and sale total restored."));
    } else {
        redirect("../pages/reports.php?error=" . urlencode("Failed to process stock adjustment."));
    }
} else {
    redirect("../pages/reports.php");
}
