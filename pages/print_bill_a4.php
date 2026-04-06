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
$pdf_filename = "Invoice_#{$sale_id}_{$cust_name}_{$sale_date}.pdf";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $sale_id ?></title>
    <link rel="icon" type="image/png" href="../<?= getSetting('business_favicon', 'assets/img/favicon.png') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @page { 
            size: A4; 
            margin: 1.5cm; 
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: #333; 
            line-height: 1.5;
            margin: 0;
            padding: 20px;
            background-color: #f3f4f6;
        }
        .invoice-box {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            border-bottom: 2px solid #0d9488;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .business-info h1 {
            margin: 0;
            color: #0d9488;
            font-size: 28px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .business-info p {
            margin: 5px 0;
            font-size: 13px;
            color: #666;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h2 {
            margin: 0;
            color: #1f2937;
            font-size: 24px;
            text-transform: uppercase;
        }
        .invoice-title p {
            margin: 2px 0;
            font-size: 14px;
            font-weight: bold;
            color: #0d9488;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }
        .detail-item h4 {
            margin: 0 0 10px 0;
            color: #374151;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .detail-content p {
            margin: 3px 0;
            font-size: 14px;
        }
        .detail-content span {
            font-weight: bold;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #f9fafb;
            color: #4b5563;
            font-size: 12px;
            text-transform: uppercase;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
            color: #1f2937;
        }
        .col-qty, .col-price, .col-total {
            text-align: right;
        }
        .summary-container {
            display: flex;
            justify-content: flex-end;
        }
        .summary-box {
            width: 250px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        .summary-row.total {
            border-top: 2px solid #0d9488;
            margin-top: 10px;
            padding-top: 15px;
            font-weight: bold;
            font-size: 18px;
            color: #0d9488;
        }
        .summary-row.paid {
            color: #059669;
            font-weight: bold;
        }
        .summary-row.balance {
            color: #ef4444;
            font-weight: bold;
            padding-top: 10px;
            border-top: 1px dashed #e5e7eb;
            margin-top: 5px;
        }
        .footer {
            margin-top: 60px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            color: #9ca3af;
            font-size: 12px;
        }
        .no-print {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-print { background: #0d9488; color: white; }
        .btn-print:hover { background: #0f766e; }
        .btn-pdf { background: #ef4444; color: white; }
        .btn-pdf:hover { background: #dc2626; }
        .btn-close { background: #6b7280; color: white; }

        @media print {
            body { background: #fff; padding: 0; }
            .invoice-box { box-shadow: none; padding: 0; width: 100%; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> Print Invoice</button>
        <button onclick="downloadPDF()" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> Save as PDF</button>
        <button onclick="window.close()" class="btn btn-close">Close</button>
    </div>

    <div class="invoice-box" id="invoiceContent">
        <div class="header">
            <div class="business-info">
                <h1><?= $business_name ?></h1>
                <p><i class="fas fa-map-marker-alt"></i> <?= $business_address ?></p>
                <p><i class="fas fa-phone"></i> <?= $business_phone ?></p>
            </div>
            <div class="invoice-title">
                <h2>INVOICE</h2>
                <p>#INV-<?= str_pad($sale_id, 4, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-item">
                <h4>Bill To</h4>
                <div class="detail-content">
                    <p><span>Name:</span> <?= htmlspecialchars($cust_name) ?></p>
                    <?php if ($customer): ?>
                        <p><span>Phone:</span> <?= htmlspecialchars($customer['phone'] ?? '-') ?></p>
                        <p><span>Address:</span> <?= htmlspecialchars($customer['address'] ?? '-') ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="detail-item">
                <h4>Invoice Info</h4>
                <div class="detail-content">
                    <p><span>Date:</span> <?= date('d M Y', strtotime($sale['sale_date'])) ?></p>
                    <p><span>Time:</span> <?= date('h:i A', strtotime($sale['sale_date'])) ?></p>
                    <p><span>Payment:</span> <?= strtoupper($sale['payment_method']) ?></p>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="40">#</th>
                    <th>Item Description</th>
                    <th class="col-qty" width="100">Qty</th>
                    <th class="col-price" width="120">Price</th>
                    <th class="col-total" width="140">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($items as $item): 
                    $product = $p_map[$item['product_id']] ?? null;
                    $p_name = $product['name'] ?? 'Unknown Item';
                    $returned_qty = (float)($item['returned_qty'] ?? 0);
                    $net_qty = (float)$item['quantity'] - $returned_qty;
                    $unitName = detectBestUnit($item, $product, $all_units);
                ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td>
                        <strong><?= htmlspecialchars($p_name) ?></strong>
                        <div style="font-size: 11px; color: #666; margin-top: 2px;">Unit: <?= htmlspecialchars($unitName) ?></div>
                        <?php if ($returned_qty > 0): ?>
                            <div style="color: #ef4444; font-size: 10px; font-weight: bold; margin-top: 4px;">
                                <i class="fas fa-undo"></i> RETURNED: <?= $returned_qty ?> <?= htmlspecialchars($unitName) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="col-qty"><?= $net_qty ?></td>
                    <td class="col-price"><?= number_format($item['price_per_unit']) ?></td>
                    <td class="col-total">
                        <?= number_format($item['total_price']) ?>
                        <?php if ($returned_qty > 0): ?>
                            <div style="color: #ef4444; font-size: 10px;">-<?= number_format($returned_qty * $item['price_per_unit']) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-container">
            <div class="summary-box">
                <?php $subtotal = (float)$sale['total_amount'] + (float)($sale['discount'] ?? 0); ?>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>Rs. <?= number_format($subtotal) ?></span>
                </div>
                <?php if (!empty($sale['discount']) && $sale['discount'] > 0): ?>
                <div class="summary-row" style="color: #ef4444;">
                    <span>Discount</span>
                    <span>- <?= number_format($sale['discount']) ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-row total">
                    <span>Net Total</span>
                    <span>Rs. <?= number_format($sale['total_amount']) ?></span>
                </div>
                <div class="summary-row paid">
                    <span>Paid Amount</span>
                    <span>Rs. <?= number_format($sale['paid_amount']) ?></span>
                </div>
                <?php $balance = (float)$sale['total_amount'] - (float)$sale['paid_amount']; ?>
                <div class="summary-row balance">
                    <span>Due Balance</span>
                    <span>Rs. <?= number_format($balance) ?></span>
                </div>
                <?php if ($balance > 0.01 && !empty($sale['due_date'])): ?>
                <div class="summary-row" style="margin-top: 10px; font-size: 12px; font-style: italic; color: #666;">
                    <span>Recovery Date:</span>
                    <span><?= date('d M Y', strtotime($sale['due_date'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <p><strong>Remarks:</strong> <?= htmlspecialchars($sale['remarks'] ?: 'Thank you for your purchase!') ?></p>
            <div style="margin-top: 40px;">
                <p style="margin-bottom: 5px; font-weight: bold;">Software by Abdul Rafay</p>
                <p>WhatsApp: 03000358189 / 03710273699</p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function downloadPDF() {
        const element = document.getElementById('invoiceContent');
        const opt = {
            margin:       10,
            filename:     '<?= addslashes($pdf_filename) ?>',
            image:        { type: 'jpeg', quality: 1 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
    </script>
</body>
</html>
