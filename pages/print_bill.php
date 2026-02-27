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

// Fetch Units for hierarchy
$all_units = readCSV('units');

$business_name = getSetting('business_name', 'Fashion Shines');
$business_address = getSetting('business_address', 'Faisalabad, Pakistan');
$business_phone = getSetting('business_phone', '0300-0000000');

$cust_name = $customer ? $customer['name'] : 'Walk-in';
$sale_date = date('d-M-Y', strtotime($sale['sale_date']));
$pdf_filename = "{$cust_name} , Receipt_#{$sale_id} , {$sale_date}.pdf";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $sale_id ?></title>
    <link rel="icon" type="image/png" href="../<?= getSetting('business_favicon', 'assets/img/favicon.png') ?>">
    <style>
        @page { 
            size: 80mm auto; 
            margin: 0; 
        }
        html, body {
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body { 
            font-family: 'Courier New', Courier, monospace; 
            color: #000; 
            padding: 20px; 
            background: #f3f4f6; 
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        .receipt-container { 
            background: #fff; 
            width: 80mm; 
            max-width: 80mm;
            margin: 0 auto; 
            padding: 10mm 5mm; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            border-radius: 8px; 
            box-sizing: border-box;
        }
        .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; font-weight: bold; }
        .header p { margin: 2px 0; font-size: 11px; }
        
        .info-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; line-height: 1.2; }
        .info-label { font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; table-layout: fixed; }
        th { border-bottom: 1px dashed #000; padding: 5px 2px; text-align: left; font-weight: bold; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        th.col-qty   { width: 22%; text-align: right; }
        th.col-price { width: 24%; text-align: right; }
        th.col-total { width: 24%; text-align: right; }
        th.col-item  { width: 30%; }
        td { padding: 4px 2px; vertical-align: top; word-break: break-word; }
        td.col-qty, td.col-price, td.col-total { text-align: right; white-space: nowrap; }
        .unit-badge { display: block; font-size: 8px; font-weight: bold; text-transform: uppercase; color: #555; margin-top: 1px; }
        .text-right { text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        
        .totals-container { margin-top: 5px; }
        .total-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 2px; line-height: 1.4; }
        .grand-total { font-weight: bold; font-size: 15px; border-top: 1px solid #000; padding-top: 5px; margin-top: 5px; }
        
        .footer { text-align: center; margin-top: 20px; font-size: 10px; padding-bottom: 10px; }
        .footer p { margin: 2px 0; }
        
        .no-print { display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; width: 350px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; color: #fff; text-decoration: none; font-family: sans-serif; font-size: 13px; }
        .btn-print { background: #0d9488; }
        .btn-pdf { background: #ef4444; }
        .btn-close { background: #6b7280; }

        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            html, body {
                width: 80mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .receipt-container { 
                box-shadow: none !important; 
                width: 80mm !important; 
                margin: 0 !important; 
                padding: 5mm !important;
                border-radius: 0 !important;
                border: none !important;
            }
            .no-print { display: none !important; }
            * { transition: none !important; }
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
            <span class="info-label">Print Time:</span>
            <span><?= date('d-M-Y h:i A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Customer:</span>
            <span><?= $customer ? htmlspecialchars($customer['name']) : 'Walk-in' ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-item">Item</th>
                    <th class="col-qty">QTY</th>
                    <th class="col-price">Price</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $product = $p_map[$item['product_id']] ?? null;
                    $p_name = $product['name'] ?? 'Unknown Item';
                    $returned_qty = (float)($item['returned_qty'] ?? 0);
                    $net_qty = (float)$item['quantity'] - $returned_qty;
                    
                    // Detect Unit
                    $unitName = detectBestUnit($item, $product, $all_units);
                ?>
                <tr>
                    <td class="col-item">
                        <?= htmlspecialchars($p_name) ?>
                        <span class="unit-badge"><?= htmlspecialchars($unitName) ?></span>
                        <?php if ($returned_qty > 0): ?>
                            <div style="color: #ef4444; font-size: 10px; font-weight: 800; margin-top: 2px;">
                                [Ret: <?= $returned_qty ?>]
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="col-qty"><?= $net_qty ?></td>
                    <td class="col-price"><?= number_format($item['price_per_unit']) ?></td>
                    <td class="col-total">
                        <?= number_format($item['total_price']) ?>
                        <?php if ($returned_qty > 0): ?>
                            <div style="color: #ef4444; font-size: 10px; font-weight: 800; margin-top: 1px;">
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
            <div style="margin-top: 30px; font-size: 8px; color: #4b5563; line-height: 1.4;">
                <p style="margin: 0; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Software by Abdul Rafay</p>
                <p style="margin: 2px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
            </div>
        </div>
    </div>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function downloadPDF() {
        const element = document.getElementById('receiptContent');
        
        // Calculate height in mm
        // 80mm is our width. We need to find the height that preserves the aspect ratio or just measure it.
        // html2pdf uses a default DPI of 96. 1mm = 3.78px approx.
        const heightPx = element.offsetHeight;
        const heightMm = Math.ceil(heightPx / 3.78) + 10; // Add 10mm buffer

        const opt = {
            margin:       0,
            filename:     '<?= addslashes($pdf_filename) ?>',
            image:        { type: 'jpeg', quality: 1 },
            html2canvas:  { scale: 3, useCORS: true, logging: false, letterRendering: true },
            jsPDF:        { unit: 'mm', format: [80, heightMm], orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }
    </script>
</body>
</html>
