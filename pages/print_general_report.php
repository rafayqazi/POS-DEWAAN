<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$range = $_GET['range'] ?? 'today';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$start_date = '';
$end_date = '';

if ($range === 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
    $display_range = "Today (" . date('d M Y') . ")";
} elseif ($range === 'week') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
    $display_range = "Last 7 Days (" . date('d M Y', strtotime($start_date)) . " to " . date('d M Y') . ")";
} elseif ($range === 'month') {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
    $display_range = "Last 30 Days (" . date('d M Y', strtotime($start_date)) . " to " . date('d M Y') . ")";
} elseif ($range === 'custom' && !empty($from) && !empty($to)) {
    $start_date = $from;
    $end_date = $to;
    $display_range = "Custom Range (" . date('d M Y', strtotime($from)) . " to " . date('d M Y', strtotime($to)) . ")";
} else {
    die("Invalid date range selected.");
}

// Load Data
$all_sales = readCSV('sales');
$all_sale_items = readCSV('sale_items');
$all_costs = readCSV('dealer_transactions');
$all_expenses = readCSV('expenses');
$all_customer_txns = readCSV('customer_transactions');
$all_products = readCSV('products');
$all_customers = readCSV('customers');

$p_map = [];
foreach($all_products as $p) $p_map[$p['id']] = $p;

$c_map = [];
foreach($all_customers as $c) $c_map[$c['id']] = $c['name'];

// 1. Process Sales & Profit
$period_sales = array_filter($all_sales, function($s) use ($start_date, $end_date) {
    $sdate = substr($s['sale_date'], 0, 10);
    return $sdate >= $start_date && $sdate <= $end_date;
});

$revenue = 0;
$cost_of_goods = 0;
$paid_at_sale = 0;
$product_qty_sales = [];
$customer_revenue = [];

foreach($period_sales as $s) {
    $revenue += (float)$s['total_amount'];
    $paid_at_sale += (float)$s['paid_amount'];
    
    $sid = $s['id'];
    $items = array_filter($all_sale_items, function($i) use ($sid) {
        return $i['sale_id'] == $sid;
    });
    
    foreach($items as $item) {
        // Calculate Cost (following AVCO logic from reports.php)
        $unit_cost = 0;
        if (isset($item['avg_buy_price']) && $item['avg_buy_price'] !== '') {
            $unit_cost = (float)$item['avg_buy_price'];
        } elseif (isset($item['buy_price']) && $item['buy_price'] !== '') {
            $unit_cost = (float)$item['buy_price'];
        } elseif (isset($p_map[$item['product_id']])) {
            $p = $p_map[$item['product_id']];
            $unit_cost = isset($p['avg_buy_price']) ? (float)$p['avg_buy_price'] : (float)$p['buy_price'];
        }
        $cost_of_goods += $unit_cost * (float)$item['quantity'];
        
        // Product Performance
        $pid = $item['product_id'];
        $product_qty_sales[$pid] = ($product_qty_sales[$pid] ?? 0) + (float)$item['quantity'];
    }

    // Customer Performance
    $cid = $s['customer_id'] ?: 'Walk-in';
    $customer_revenue[$cid] = ($customer_revenue[$cid] ?? 0) + (float)$s['total_amount'];
}

$gross_profit = $revenue - $cost_of_goods;

// 2. Process Customer Recoveries (Ledger Payments)
$period_ledger_payments = array_filter($all_customer_txns, function($tx) use ($start_date, $end_date) {
    $tdate = substr($tx['date'], 0, 10);
    return $tdate >= $start_date && $tdate <= $end_date && (float)$tx['credit'] > 0;
});
$recovery_amount = 0;
foreach($period_ledger_payments as $pay) $recovery_amount += (float)$pay['credit'];

$total_cash_inflow = $paid_at_sale + $recovery_amount;

// 3. Process Dealer Payments
$period_dealer_payments = array_filter($all_costs, function($tx) use ($start_date, $end_date) {
    $tdate = substr($tx['date'], 0, 10);
    return $tdate >= $start_date && $tdate <= $end_date && (float)$tx['credit'] > 0;
});
$dealer_paid_amount = 0;
foreach($period_dealer_payments as $pay) $dealer_paid_amount += (float)$pay['credit'];

// 4. Process Expenses
$period_expenses = array_filter($all_expenses, function($e) use ($start_date, $end_date) {
    $edate = substr($e['date'], 0, 10);
    return $edate >= $start_date && $edate <= $end_date;
});
$expenses_amount = 0;
foreach($period_expenses as $exp) $expenses_amount += (float)$exp['amount'];

$net_profit = $gross_profit - $expenses_amount;
$total_cash_outflow = $dealer_paid_amount + $expenses_amount;

// Full List of Performers
arsort($product_qty_sales);
$top_products = $product_qty_sales;

arsort($customer_revenue);
$top_customers = $customer_revenue;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Overall Financial Report - <?= $display_range ?></title>
    <style>
        @page { size: auto; margin: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; margin: 0; padding: 1.5cm; color: #1e293b; }
        .report-header { text-align: center; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 40px; }
        .report-header h1 { margin: 0; color: #0f766e; text-transform: uppercase; letter-spacing: 2px; }
        .report-header p { margin: 5px 0 0 0; color: #64748b; font-weight: bold; }
        
        .metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .metric-card { padding: 25px; border-radius: 20px; border: 1px solid #f1f5f9; background: #f8fafc; }
        .metric-label { font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; display: block; }
        .metric-value { font-size: 24px; font-weight: 900; color: #0f172a; }
        .metric-profit { background: #f0fdf4; border-color: #bbf7d0; color: #15803d !important; }
        .metric-loss { background: #fef2f2; border-color: #fecaca; color: #b91c1c !important; }

        .section-title { font-size: 14px; font-weight: 900; color: #0f172a; text-transform: uppercase; border-left: 4px solid #0d9488; padding-left: 15px; margin-bottom: 20px; margin-top: 40px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; font-weight: bold; color: #64748b; text-transform: uppercase; font-size: 11px; }
        
        .text-right { text-align: right; }
        .font-black { font-weight: 900; }
        
        .cash-flow { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        
        .footer { margin-top: 60px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 30px; }
        
        @media print {
            body { padding: 1.5cm; }
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
        <p>Comprehensive Business Summary Report</p>
        <span style="font-size: 12px; color: #94a3b8; margin-top: 10px; display: block;">Period: <?= $display_range ?></span>
    </div>

    <div class="section-title">Financial Performance Overview</div>
    <div class="metric-grid">
        <div class="metric-card">
            <span class="metric-label">Total Revenue (Sales)</span>
            <div class="metric-value"><?= formatCurrency($revenue) ?></div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Cost of Goods Sold</span>
            <div class="metric-value text-red-500"><?= formatCurrency($cost_of_goods) ?></div>
        </div>
        <div class="metric-card metric-profit">
            <span class="metric-label" style="color:rgba(21, 128, 61, 0.6)">Gross Profit</span>
            <div class="metric-value" style="color:#15803d"><?= formatCurrency($gross_profit) ?></div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Total Expenses</span>
            <div class="metric-value text-red-500"><?= formatCurrency($expenses_amount) ?></div>
        </div>
        <div class="metric-card <?= $net_profit >= 0 ? 'metric-profit' : 'metric-loss' ?>">
            <span class="metric-label" style="opacity: 0.6">Net Profit</span>
            <div class="metric-value"><?= formatCurrency($net_profit) ?></div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Total Recovery</span>
            <div class="metric-value text-teal-600"><?= formatCurrency($total_cash_inflow) ?></div>
        </div>
    </div>

    <div class="cash-flow">
        <div>
            <div class="section-title">Cash Flow: Money In</div>
            <table>
                <tr>
                    <td>Cash from Direct Sales</td>
                    <td class="text-right font-black"><?= formatCurrency($paid_at_sale) ?></td>
                </tr>
                <tr>
                    <td>Cash from Ledger Recovery</td>
                    <td class="text-right font-black"><?= formatCurrency($recovery_amount) ?></td>
                </tr>
                <tr style="background: #f0fdfa;">
                    <td class="font-black text-teal-700">Total Inflow</td>
                    <td class="text-right font-black text-teal-700"><?= formatCurrency($total_cash_inflow) ?></td>
                </tr>
            </table>
        </div>
        <div>
            <div class="section-title">Cash Flow: Money Out</div>
            <table>
                <tr>
                    <td>Paid to Dealers/Suppliers</td>
                    <td class="text-right font-black"><?= formatCurrency($dealer_paid_amount) ?></td>
                </tr>
                <tr>
                    <td>Operating Expenses</td>
                    <td class="text-right font-black"><?= formatCurrency($expenses_amount) ?></td>
                </tr>
                <tr style="background: #fef2f2;">
                    <td class="font-black text-red-700">Total Outflow</td>
                    <td class="text-right font-black text-red-700"><?= formatCurrency($total_cash_outflow) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px;">
        <div>
            <div class="section-title">Product Performance (Sold Qty)</div>
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th class="text-right">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($top_products as $pid => $qty): ?>
                    <tr>
                        <td><?= htmlspecialchars($p_map[$pid]['name'] ?? 'Unknown Item') ?></td>
                        <td class="text-right font-black"><?= $qty ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div>
            <div class="section-title">Customer Performance (Revenue)</div>
            <table>
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($top_customers as $cid => $amt): ?>
                    <tr>
                        <td><?= $cid === 'Walk-in' ? 'Walk-in Customer' : htmlspecialchars($c_map[$cid] ?? 'Unknown') ?></td>
                        <td class="text-right font-black"><?= formatCurrency($amt) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">
        <p style="margin: 0; font-weight: bold; color: #0f172a;">REPORT END - Page 1 of 1</p>
        <p style="margin: 8px 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
        <p style="margin: 20px 0 0 0; font-weight: bold; background: #f8fafc; padding: 10px; border-radius: 10px;">Software by Abdul Rafay | WhatsApp: 03000358189</p>
    </div>

</body>
</html>
