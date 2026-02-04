<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$return_id = $_GET['id'] ?? '';
if (empty($return_id)) {
    die("Return ID required.");
}

// Fetch Return Data
$returns = readCSV('returns');
$return_record = null;
foreach ($returns as $r) {
    if ($r['id'] == $return_id) {
        $return_record = $r;
        break;
    }
}

if (!$return_record) {
    die("Return record not found.");
}

$sale_id = $return_record['sale_id'];
$sale = findCSV('sales', $sale_id);

// Fetch Customer
$customers = readCSV('customers');
$customer = null;
if (!empty($return_record['customer_id'])) {
    foreach ($customers as $c) {
        if ($c['id'] == $return_record['customer_id']) {
            $customer = $c;
            break;
        }
    }
}

// Fetch Return Items
$all_return_items = readCSV('return_items');
$items = array_filter($all_return_items, function($item) use ($return_id) {
    return $item['return_id'] == $return_id;
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
    <title>Return Receipt #<?= $return_id ?></title>
    <link rel="icon" type="image/png" href="../<?= getSetting('business_favicon', 'assets/img/favicon.png') ?>">
    <style>
        @page { size: auto; margin: 0; }
        body { font-family: 'Courier New', Courier, monospace; color: #000; margin: 0; padding: 20px; background: #f3f4f6; }
        .receipt-container { background: #fff; width: 350px; margin: 0 auto; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .header { text-align: center; border-bottom: 2px solid #ef4444; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 22px; text-transform: uppercase; color: #ef4444; }
        .header p { margin: 2px 0; font-size: 12px; }
        .header .return-label { font-size: 16px; font-weight: black; color: #ef4444; margin-top: 5px; letter-spacing: 2px; }
        
        .info-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
        .info-label { font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        th { border-bottom: 1px dashed #000; padding: 5px 0; text-align: left; }
        td { padding: 8px 0; vertical-align: top; border-bottom: 1px solid #f9fafb; }
        
        .return-item { color: #ef4444; font-weight: bold; }
        
        .text-right { text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 10px 0; }
        
        .totals-container { margin-top: 10px; padding: 10px; background: #fff5f5; border-radius: 8px; }
        .total-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; }
        .refund-total { font-weight: bold; font-size: 18px; color: #ef4444; border-top: 2px solid #ef4444; padding-top: 8px; margin-top: 8px; }
        
        .footer { text-align: center; margin-top: 25px; font-size: 10px; font-style: italic; border-top: 1px dashed #ccc; padding-top: 10px; }
        
        .no-print { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .btn-print { background: #ef4444; }
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
        <button onclick="window.print()" class="btn btn-print">Print Return Receipt</button>
        <button onclick="window.close()" class="btn btn-close">Close</button>
    </div>

    <div class="receipt-container" id="receiptContent">
        <div class="header">
            <h1><?= $business_name ?></h1>
            <p><?= $business_address ?></p>
            <p>Phone: <?= $business_phone ?></p>
            <div class="return-label">RETURN RECEIPT</div>
        </div>

        <div class="info-row">
            <span class="info-label">Return ID:</span>
            <span style="color:#ef4444; font-weight:bold;">#<?= $return_id ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Original Sale:</span>
            <span>#<?= $sale_id ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date:</span>
            <span><?= date('d-M-Y h:i A', strtotime($return_record['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Customer:</span>
            <span><?= $customer ? htmlspecialchars($customer['name']) : 'Walk-in' ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Returned Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Refund</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $p_name = $p_map[$item['product_id']]['name'] ?? 'Unknown Item';
                ?>
                <tr class="return-item">
                    <td>
                        <?= htmlspecialchars($p_name) ?>
                        <div style="font-size: 8px; font-weight: normal;">[Returned]</div>
                    </td>
                    <td class="text-right"><?= $item['quantity'] ?></td>
                    <td class="text-right"><?= number_format($item['price_per_unit']) ?></td>
                    <td class="text-right"><?= number_format($item['total_price']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($return_record['remarks'])): ?>
        <div style="margin-top: 10px; font-size: 10px; color: #666; font-style: italic;">
            <strong>Reason:</strong> <?= htmlspecialchars($return_record['remarks']) ?>
        </div>
        <?php endif; ?>

        <div class="totals-container">
            <?php 
                $total_refund_qty = 0;
                foreach($items as $item) $total_refund_qty += (float)$item['quantity'];
            ?>
            <div class="total-row" style="color: #ef4444;">
                <span>Returned QTY:</span>
                <span><?= $total_refund_qty ?></span>
            </div>
            <div class="total-row refund-total">
                <span>Total Refund:</span>
                <span>Rs. <?= number_format($return_record['total_refund']) ?></span>
            </div>
        </div>

        <div class="divider"></div>
        <div style="font-size: 10px; text-align: center; color: #666;">
            <p>The above amount has been adjusted in your ledger/bill.</p>
        </div>

        <div class="footer">
            <p>Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189</p>
        </div>
    </div>

</body>
</html>
