<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$return_id = $_GET['id'] ?? '';
if (empty($return_id)) {
    die("Dealer Return ID required.");
}

// Fetch Dealer Return Data
$returns = readCSV('dealer_returns');
$return_record = null;
foreach ($returns as $r) {
    if ($r['id'] == $return_id) {
        $return_record = $r;
        break;
    }
}

if (!$return_record) {
    die("Dealer return record not found.");
}

$restock_id = $return_record['restock_id'];
$restock = findCSV('restocks', $restock_id);

// Fetch Dealer
$dealers = readCSV('dealers');
$dealer = null;
if (!empty($return_record['dealer_id']) && $return_record['dealer_id'] !== 'OPEN_MARKET') {
    foreach ($dealers as $d) {
        if ($d['id'] == $return_record['dealer_id']) {
            $dealer = $d;
            break;
        }
    }
}

// Fetch Return Items
$all_return_items = readCSV('dealer_return_items');
$items = array_filter($all_return_items, function($item) use ($return_id) {
    return ($item['dealer_return_id'] ?? '') == $return_id;
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
    <title>Dealer Return Receipt #<?= $return_id ?></title>
    <link rel="icon" type="image/png" href="../<?= getSetting('business_favicon', 'assets/img/favicon.png') ?>">
    <style>
        @page { size: auto; margin: 0; }
        body { font-family: 'Courier New', Courier, monospace; color: #000; margin: 0; padding: 20px; background: #f3f4f6; }
        .receipt-container { background: #fff; width: 350px; margin: 0 auto; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        .header { text-align: center; border-bottom: 2px solid #0d9488; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; color: #0d9488; }
        .header p { margin: 2px 0; font-size: 12px; }
        .header .return-label { font-size: 15px; font-weight: bold; color: #0d9488; margin-top: 5px; letter-spacing: 1px; }
        
        .info-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
        .info-label { font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
        th { border-bottom: 1px dashed #000; padding: 5px 0; text-align: left; }
        td { padding: 8px 0; vertical-align: top; border-bottom: 1px solid #f9fafb; }
        
        .return-item { color: #0f766e; font-weight: bold; }
        
        .text-right { text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 10px 0; }
        
        .totals-container { margin-top: 10px; padding: 10px; background: #f0fdf4; border-radius: 8px; }
        .total-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; }
        .refund-total { font-weight: bold; font-size: 18px; color: #0d9488; border-top: 2px solid #0d9488; padding-top: 8px; margin-top: 8px; }
        
        .no-print { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .btn-print { background: #0d9488; }
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
        <button onclick="window.print()" class="btn btn-print">Print Dealer Return Receipt</button>
        <button onclick="window.close()" class="btn btn-close">Close</button>
    </div>

    <div class="receipt-container" id="receiptContent">
        <div class="header">
            <h1><?= $business_name ?></h1>
            <p><?= $business_address ?></p>
            <p>Phone: <?= $business_phone ?></p>
            <div class="return-label">DEALER RETURN RECEIPT</div>
        </div>

        <div class="info-row">
            <span class="info-label">Return ID:</span>
            <span style="color:#0d9488; font-weight:bold;">#<?= $return_id ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Restock Ref:</span>
            <span>#<?= $restock_id ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date:</span>
            <span><?= date('d-M-Y h:i A', strtotime($return_record['created_at'] ?? $return_record['date'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Dealer:</span>
            <span><?= $dealer ? htmlspecialchars($dealer['name']) : (($return_record['dealer_name'] ?? '') ?: 'Open Market') ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Returned Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): 
                        $p_name = $p_map[$item['product_id']]['name'] ?? ($return_record['product_name'] ?? 'Unknown Item');
                    ?>
                    <tr class="return-item">
                        <td>
                            <?= htmlspecialchars($p_name) ?>
                            <div style="font-size: 8px; font-weight: normal; color: #666;">[Returned to Dealer]</div>
                        </td>
                        <td class="text-right"><?= $item['quantity'] ?></td>
                        <td class="text-right"><?= number_format($item['price_per_unit']) ?></td>
                        <td class="text-right"><?= number_format($item['total_price']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="return-item">
                        <td>
                            <?= htmlspecialchars($return_record['product_name'] ?? 'Item') ?>
                            <div style="font-size: 8px; font-weight: normal; color: #666;">[Returned to Dealer]</div>
                        </td>
                        <td class="text-right"><?= $return_record['quantity'] ?></td>
                        <td class="text-right"><?= number_format($return_record['price_per_unit']) ?></td>
                        <td class="text-right"><?= number_format($return_record['total_refund']) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($return_record['remarks'])): ?>
        <div style="margin-top: 10px; font-size: 10px; color: #666; font-style: italic;">
            <strong>Reason:</strong> <?= htmlspecialchars($return_record['remarks']) ?>
        </div>
        <?php endif; ?>

        <div class="totals-container">
            <?php 
                $total_refund_qty = (float)($return_record['quantity'] ?? 0);
                if (!empty($items)) {
                    $total_refund_qty = 0;
                    foreach($items as $item) $total_refund_qty += (float)$item['quantity'];
                }
            ?>
            <div class="total-row" style="color: #0d9488;">
                <span>Returned QTY:</span>
                <span><?= $total_refund_qty ?></span>
            </div>
            <div class="total-row refund-total">
                <span>Total Return Credit:</span>
                <span>Rs. <?= number_format($return_record['total_refund']) ?></span>
            </div>
        </div>

        <div class="divider"></div>
        <div style="font-size: 10px; text-align: center; color: #666;">
            <p>The above return amount has been credited to dealer ledger.</p>
        </div>

        <!-- Mandatory Developer Footer -->
        <div style="margin-top:40px; border-top:1px solid #eee; padding-top:15px; text-align:center; font-size:9px; color:#aaa;">
            <p style="margin:0; font-weight:bold; color:#888;">Software Developed by Abdul Rafay</p>
            <p style="margin:4px 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>

</body>
</html>
