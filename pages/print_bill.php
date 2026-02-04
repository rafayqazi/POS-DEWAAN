<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$sale_id = $_GET['id'] ?? '';
if (empty($sale_id)) {
    die("Sale ID required.");
}

// Fetch Sale Data
$sales = readCSV('sales');
$sale = null;
foreach ($sales as $s) {
    if ($s['id'] == $sale_id) {
        $sale = $s;
        break;
    }
}

if (!$sale) {
    die("Sale not found.");
}

// Fetch Customer
$customers = readCSV('customers');
$customer = null;
if (!empty($sale['customer_id'])) {
    foreach ($customers as $c) {
        if ($c['id'] == $sale['customer_id']) {
            $customer = $c;
            break;
        }
    }
}

// Fetch Sale Items
$sale_items = readCSV('sale_items');
$items = array_filter($sale_items, function($item) use ($sale_id) {
    return $item['sale_id'] == $sale_id;
});

// Fetch Products for names
$products = readCSV('products');
$p_map = [];
foreach ($products as $p) $p_map[$p['id']] = $p;

$business_name = getSetting('business_name', 'Fashion Shines');
$business_address = getSetting('business_address', 'Faisalabad, Pakistan');
$business_phone = getSetting('business_phone', '0300-0000000');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $sale_id ?></title>
    <link rel="icon" type="image/png" href="../<?= getSetting('business_favicon', 'assets/img/favicon.png') ?>">
    <style>
        @page { size: auto; margin: 0; }
        body { font-family: 'Courier New', Courier, monospace; color: #000; margin: 0; padding: 20px; background: #f3f4f6; }
        .receipt-container { background: #fff; width: 350px; margin: 0 auto; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 22px; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 12px; }
        
        .info-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
        .info-label { font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        th { border-bottom: 1px dashed #000; padding: 5px 0; text-align: left; }
        td { padding: 5px 0; vertical-align: top; }
        
        .text-right { text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 10px 0; }
        
        .totals-container { margin-top: 10px; }
        .total-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 2px; }
        .grand-total { font-weight: bold; font-size: 16px; border-top: 1px solid #000; padding-top: 5px; margin-top: 5px; }
        
        .footer { text-align: center; margin-top: 25px; font-size: 10px; font-style: italic; }
        
        .no-print { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; color: #fff; text-decoration: none; }
        .btn-print { background: #0d9488; }
        .btn-pdf { background: #ef4444; }
        .btn-close { background: #6b7280; }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt-container { box-shadow: none; width: 100%; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print">Print Receipt</button>
        <button onclick="downloadPDF()" class="btn btn-pdf">Download PDF</button>
        <button onclick="window.close()" class="btn btn-close">Close</button>
    </div>

    <div class="receipt-container" id="receiptContent">
        <div class="header">
            <h1><?= $business_name ?></h1>
            <p><?= $business_address ?></p>
            <p>Phone: <?= $business_phone ?></p>
        </div>

        <div class="info-row">
            <span class="info-label">Receipt #:</span>
            <span><?= $sale_id ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date:</span>
            <span><?= date('d-M-Y h:i A', strtotime($sale['sale_date'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Customer:</span>
            <span><?= $customer ? htmlspecialchars($customer['name']) : 'Walk-in' ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $p_name = $p_map[$item['product_id']]['name'] ?? 'Unknown Item';
                    $returned_qty = (float)($item['returned_qty'] ?? 0);
                    $net_qty = (float)$item['quantity'] - $returned_qty;
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($p_name) ?>
                        <?php if ($returned_qty > 0): ?>
                            <div style="color: #ef4444; font-size: 11px; font-weight: 800; margin-top: 2px;">
                                [Returned: <?= $returned_qty ?>]
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?= $item['quantity'] ?></td>
                    <td class="text-right"><?= number_format($item['price_per_unit']) ?></td>
                    <td class="text-right">
                        <?= number_format($item['total_price']) ?>
                        <?php if ($returned_qty > 0): ?>
                            <div style="color: #ef4444; font-size: 11px; font-weight: 800; margin-top: 1px;">
                                -<?= number_format($returned_qty * $item['price_per_unit']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="totals-container">
            <?php 
                $total_items = count($items);
                $total_qty = 0;
                foreach($items as $item) $total_qty += (float)$item['quantity'];
            ?>
            <div class="total-row">
                <span>Total Items:</span>
                <span><?= $total_items ?></span>
            </div>
            <div class="total-row">
                <span>Total QTY:</span>
                <span><?= $total_qty ?></span>
            </div>
            <?php 
                $subtotal = (float)$sale['total_amount'] + (float)($sale['discount'] ?? 0);
            ?>
            <div class="total-row">
                <span>Sub-Total:</span>
                <span>Rs. <?= number_format($subtotal) ?></span>
            </div>
            <?php if (!empty($sale['discount']) && $sale['discount'] > 0): ?>
            <div class="total-row" style="color: #ef4444;">
                <span>Discount:</span>
                <span>- Rs. <?= number_format($sale['discount']) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row grand-total">
                <span>Net Total:</span>
                <span>Rs. <?= number_format($sale['total_amount']) ?></span>
            </div>
            <div class="total-row">
                <span>Paid Amount:</span>
                <span>Rs. <?= number_format($sale['paid_amount']) ?></span>
            </div>
            <?php $balance = (float)$sale['total_amount'] - (float)$sale['paid_amount']; ?>
            <div class="total-row">
                <span>Balance:</span>
                <span>Rs. <?= number_format($balance) ?></span>
            </div>
            <?php if ($balance > 0.01 && !empty($sale['due_date'])): ?>
            <div class="total-row" style="margin-top: 5px; color: #ef4444; font-weight: bold;">
                <span>Recovery Date:</span>
                <span><?= date('d-M-Y', strtotime($sale['due_date'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>


</body>
</html>
