<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

// Filters from URL
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$cat = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$expiryFilter = $_GET['expiry'] ?? 'all';

$display_range = date('d M Y', strtotime($from)) . " to " . date('d M Y', strtotime($to));

// Load Data
$products = readCSV('products');
$categories = readCSV('categories');
$restocks = readCSV('restocks');
$sales = readCSV('sales');
$sale_items = readCSV('sale_items');

// Map sales to dates
$sales_date_map = [];
foreach ($sales as $s) {
    if (isset($s['id'])) {
        $sales_date_map[$s['id']] = substr($s['sale_date'], 0, 10);
    }
}

$today_str = date('Y-m-d');
$next_30_str = date('Y-m-d', strtotime('+30 days'));

// Stats
$total_in = 0;
$total_out = 0;
$total_value = 0;
$alerts = 0;

$report_data = [];

foreach ($products as $p) {
    if ($cat && $p['category'] !== $cat) continue;
    if ($search && stripos($p['name'], $search) === false) continue;

    // Expiry
    $isNear = false;
    $isExp = false;
    if ($p['expiry_date']) {
        if ($p['expiry_date'] < $today_str) $isExp = true;
        elseif ($p['expiry_date'] <= $next_30_str) $isNear = true;
    }

    if ($expiryFilter === 'near' && !$isNear) continue;
    if ($expiryFilter === 'expired' && !$isExp) continue;

    // Movement Calculation
    $current = (float)$p['stock_quantity'];
    $inPeriod = 0;
    $inAfter = 0;
    foreach ($restocks as $r) {
        if ($r['product_id'] != $p['id']) continue;
        $rd = substr($r['date'], 0, 10);
        if ($rd >= $from && $rd <= $to) $inPeriod += (float)$r['quantity'];
        if ($rd > $to) $inAfter += (float)$r['quantity'];
    }

    $outPeriod = 0;
    $outAfter = 0;
    foreach ($sale_items as $si) {
        if ($si['product_id'] != $p['id']) continue;
        $sd = $sales_date_map[$si['sale_id']] ?? '';
        if ($sd >= $from && $sd <= $to) $outPeriod += (float)$si['quantity'];
        if ($sd > $to) $outAfter += (float)$si['quantity'];
    }

    $finalAt = $current - $inAfter + $outAfter;
    $startAt = $finalAt - $inPeriod + $outPeriod;

    $total_in += $inPeriod;
    $total_out += $outPeriod;
    $total_value += ($current * (float)$p['buy_price']);
    if ($isNear || $isExp) $alerts++;

    $report_data[] = [
        'name' => $p['name'],
        'category' => $p['category'],
        'unit' => $p['unit'],
        'start' => $startAt,
        'in' => $inPeriod,
        'out' => $outPeriod,
        'final' => $finalAt,
        'expiry' => $p['expiry_date'],
        'isNear' => $isNear,
        'isExp' => $isExp
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Movement Report - <?= $display_range ?></title>
    <style>
        @page { size: auto; margin: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; margin: 0; padding: 1.5cm; color: #1e293b; }
        .report-header { text-align: center; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px; }
        .report-header h1 { margin: 0; color: #0f766e; text-transform: uppercase; letter-spacing: 2px; font-size: 28px; }
        .report-header p { margin: 5px 0 0 0; color: #64748b; font-weight: bold; font-size: 14px; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .summary-card { padding: 15px; border-radius: 12px; border: 1px solid #f1f5f9; background: #f8fafc; text-align: center; }
        .summary-label { font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .summary-value { font-size: 18px; font-weight: 900; color: #0f172a; }
        
        .section-title { font-size: 12px; font-weight: 900; color: #0f172a; text-transform: uppercase; border-left: 4px solid #0d9488; padding-left: 12px; margin-bottom: 15px; margin-top: 30px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; font-weight: bold; color: #64748b; text-transform: uppercase; font-size: 10px; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .font-black { font-weight: 900; }
        
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge-red { background: #fee2e2; color: #b91c1c; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .badge-teal { background: #f0fdfa; color: #0f766e; }

        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        
        @media print {
            body { padding: 1cm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 30px; text-align: center;">
        <button onclick="window.print()" style="background: #0d9488; color: white; padding: 12px 25px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer;">Print Report / Save PDF</button>
        <button onclick="window.close()" style="background: #64748b; color: white; padding: 12px 25px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; margin-left: 10px;">Close Window</button>
    </div>

    <div class="report-header">
        <h1>Fashion Shines</h1>
        <p>Inventory Movement & Stock Position Report</p>
        <span style="font-size: 11px; color: #94a3b8; margin-top: 8px; display: block;">Report Period: <b><?= $display_range ?></b></span>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <span class="summary-label">Total Stock IN</span>
            <div class="summary-value" style="color:#2563eb"><?= number_format($total_in) ?></div>
        </div>
        <div class="summary-card">
            <span class="summary-label">Total Stock OUT</span>
            <div class="summary-value" style="color:#d97706"><?= number_format($total_out) ?></div>
        </div>
        <div class="summary-card">
            <span class="summary-label">Inventory Value</span>
            <div class="summary-value" style="color:#0d9488"><?= formatCurrency($total_value) ?></div>
        </div>
        <div class="summary-card">
            <span class="summary-label">Expiry Alerts</span>
            <div class="summary-value" style="color:#dc2626"><?= $alerts ?></div>
        </div>
    </div>

    <div class="section-title">Product Movement Details</div>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th class="text-center">Start</th>
                <th class="text-center" style="color:#2563eb">IN (+)</th>
                <th class="text-center" style="color:#d97706">OUT (-)</th>
                <th class="text-center">Final Stock</th>
                <th class="text-center">Status/Expiry</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data as $row): ?>
            <tr>
                <td>
                    <div class="font-bold text-gray-800"><?= htmlspecialchars($row['name']) ?></div>
                    <div style="font-size: 9px; color: #94a3b8;"><?= htmlspecialchars($row['category']) ?> • <?= $row['unit'] ?></div>
                </td>
                <td class="text-center font-bold" style="color:#64748b"><?= number_format($row['start']) ?></td>
                <td class="text-center font-bold" style="color:#2563eb"><?= $row['in'] > 0 ? '+' . number_format($row['in']) : '-' ?></td>
                <td class="text-center font-bold" style="color:#d97706"><?= $row['out'] > 0 ? '-' . number_format($row['out']) : '-' ?></td>
                <td class="text-center font-black" style="color:#0f172a; background: #f8fafc;"><?= number_format($row['final']) ?></td>
                <td class="text-center">
                    <?php if ($row['isExp']): ?>
                        <span class="badge badge-red">Expired</span>
                    <?php elseif ($row['isNear']): ?>
                        <span class="badge badge-orange">Near Expiry</span>
                    <?php elseif ($row['expiry']): ?>
                        <span class="badge badge-teal"><?= $row['expiry'] ?></span>
                    <?php else: ?>
                        <span style="color:#cbd5e1; font-style: italic;">No Expiry</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <p style="margin: 0; font-weight: bold; color: #0f172a;">REPORT END</p>
        <p style="margin: 5px 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
        <p style="margin: 15px 0 0 0; font-weight: bold; background: #f8fafc; padding: 10px; border-radius: 10px;">Software by Abdul Rafay | WhatsApp: 03000358189</p>
    </div>

</body>
</html>
