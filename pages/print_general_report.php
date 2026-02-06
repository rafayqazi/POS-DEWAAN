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
$all_restocks = readCSV('restocks');

$p_map = [];
foreach($all_products as $p) $p_map[$p['id']] = $p;

$c_map = [];
foreach($all_customers as $c) $c_map[$c['id']] = $c['name'];

$all_dealers = readCSV('dealers');
$d_map = [];
foreach($all_dealers as $d) $d_map[$d['id']] = $d['name'];

// 1. Process Sales & Profit
$period_sales = array_filter($all_sales, function($s) use ($start_date, $end_date) {
    $sdate = substr($s['sale_date'], 0, 10);
    return $sdate >= $start_date && $sdate <= $end_date;
});

$revenue = 0;
$cost_of_goods = 0;
$gross_profit = 0;
$paid_at_sale = 0;
$product_qty_sales = [];
$customer_revenue = [];
$total_items_out = 0;

// Calculate Inventory In
$period_restocks = array_filter($all_restocks, function($r) use ($start_date, $end_date) {
    if (!isset($r['date'])) return false;
    $rdate = substr($r['date'], 0, 10);
    return $rdate >= $start_date && $rdate <= $end_date;
});
$total_items_in = 0;
foreach($period_restocks as $r) $total_items_in += (float)$r['quantity'];

foreach($period_sales as $s) {
    $revenue += (float)$s['total_amount'];
    $paid_at_sale += (float)$s['paid_amount'];
    
    $sid = $s['id'];
    $items = array_filter($all_sale_items, function($i) use ($sid) {
        return $i['sale_id'] == $sid;
    });
    
    foreach($items as $item) {
        $qty = (float)$item['quantity'];
        $returned_qty = (float)($item['returned_qty'] ?? 0);
        $net_qty = $qty - $returned_qty;
        
        if ($net_qty <= 0) continue;

        $unit_cost = 0;
        if (isset($item['avg_buy_price']) && (float)$item['avg_buy_price'] > 0) {
            $unit_cost = (float)$item['avg_buy_price'];
        } elseif (isset($item['buy_price']) && (float)$item['buy_price'] > 0) {
            $unit_cost = (float)$item['buy_price'];
        } elseif (isset($p_map[$item['product_id']])) {
            $p = $p_map[$item['product_id']];
            $unit_cost = (isset($p['avg_buy_price']) && (float)$p['avg_buy_price'] > 0) ? (float)$p['avg_buy_price'] : (float)($p['buy_price'] ?? 0);
        }
        
        $price_per_unit = (float)$item['price_per_unit'];
        $item_revenue = $price_per_unit * $net_qty;
        $item_cost = $unit_cost * $net_qty;
        
        $gross_profit += ($item_revenue - $item_cost);
        $total_items_out += $net_qty;
        
        // Product Performance
        $pid = $item['product_id'];
        $product_qty_sales[$pid] = ($product_qty_sales[$pid] ?? 0) + $net_qty;
    }

    // Customer Performance
    $cid = $s['customer_id'] ?: 'Walk-in';
    $customer_revenue[$cid] = ($customer_revenue[$cid] ?? 0) + (float)$s['total_amount'];
}

$cost_of_goods = $revenue - $gross_profit;

// 2. Process Customer Recoveries (Ledger Payments)
$period_ledger_payments = array_filter($all_customer_txns, function($tx) use ($start_date, $end_date) {
    $tdate = substr($tx['date'], 0, 10);
    return $tdate >= $start_date && $tdate <= $end_date && (float)$tx['credit'] > 0;
});
$recovery_amount = 0;
foreach($period_ledger_payments as $pay) $recovery_amount += (float)$pay['credit'];

$total_cash_inflow = $paid_at_sale + $recovery_amount;

// 3. Process Dealer Payments
$period_dealer_payments_raw = array_filter($all_costs, function($tx) use ($start_date, $end_date) {
    $tdate = substr($tx['date'], 0, 10);
    return $tdate >= $start_date && $tdate <= $end_date && (float)$tx['credit'] > 0 && ($tx['type'] == 'Payment' || $tx['type'] == 'Advance');
});
$dealer_paid_amount = 0;
$dealer_payment_details = [];
foreach($period_dealer_payments_raw as $pay) {
    $dealer_paid_amount += (float)$pay['credit'];
    $dealer_payment_details[] = [
        'date' => $pay['date'],
        'dealer' => $d_map[$pay['dealer_id']] ?? 'Unknown',
        'type' => $pay['type'],
        'p_type' => $pay['payment_type'] ?? 'Cash',
        'amount' => (float)$pay['credit']
    ];
}
// Sort by date desc
usort($dealer_payment_details, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

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
    <link rel="icon" type="image/png" href="../<?= getSetting('business_favicon', 'assets/img/favicon.png') ?>">
    <style>
        @page { size: auto; margin: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; margin: 0; padding: 1.5cm; color: #1e293b; }
        .report-header { text-align: center; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 40px; }
        .report-header h1 { margin: 0; color: #0d9488; text-transform: uppercase; letter-spacing: 5px; font-size: 42px; font-weight: 900; line-height: 1; }
        .report-header p { margin: 5px 0 0 0; color: #64748b; font-weight: bold; }
        
        .metric-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .metric-card { padding: 25px; border-radius: 20px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .metric-label { font-size: 14px; font-weight: 800; color: #1e293b; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .metric-label small { font-size: 13px; color: #334155; display: block; margin-top: 2px; text-transform: none; font-weight: 600; }
        .metric-value { font-size: 28px; font-weight: 900; color: #020617; }
        .metric-profit { background: #f0fdf4; border-color: #86efac; color: #14532d !important; }
        .metric-profit .metric-label { color: #14532d; }
        .metric-profit .metric-label small { color: #166534; }
        .metric-loss { background: #fef2f2; border-color: #fca5a5; color: #7f1d1d !important; }
        .metric-loss .metric-label { color: #7f1d1d; }
        .metric-loss .metric-label small { color: #991b1b; }

        .section-title { font-size: 16px; font-weight: 900; color: #0f172a; text-transform: uppercase; border-left: 5px solid #0d9488; padding-left: 15px; margin-bottom: 25px; margin-top: 45px; }
        
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
    <!-- Tailwind for Modal -->
    <script src="../assets/js/tailwind.js"></script>
    <!-- PDF Generation Library -->
    <script src="../assets/vendor/html2pdf/html2pdf.bundle.min.js"></script>
</head>
<body>

    <div class="no-print" style="margin-bottom: 30px; text-align: center;">
        <button onclick="window.print()" style="background: #0d9488; color: white; padding: 12px 25px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer;">Print Report / Save PDF</button>
        <button onclick="emailReport()" style="background: #ef4444; color: white; padding: 12px 25px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; margin-left: 10px;">Email Report</button>
        <button onclick="window.close()" style="background: #64748b; color: white; padding: 12px 25px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; margin-left: 10px;">Close Window</button>
    </div>

    <div class="report-header">
        <h1><?= getSetting('business_name', 'Fashion Shines') ?></h1>
        <p>Comprehensive Business Summary Report</p>
        <span style="font-size: 12px; color: #94a3b8; margin-top: 10px; display: block;">Period: <?= $display_range ?></span>
    </div>

    <div class="section-title">Financial Performance Overview</div>
    <div class="metric-grid">
        <div class="metric-card">
            <span class="metric-label">Total Revenue (Sales)<br><small>(Total Sale)</small></span>
            <div class="metric-value"><?= formatCurrency($revenue) ?></div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Cost of Goods Sold<br><small>(Samaan ki Asli qeemat key hisab se)</small></span>
            <div class="metric-value text-red-500"><?= formatCurrency($cost_of_goods) ?></div>
        </div>
        <div class="metric-card metric-profit">
            <span class="metric-label">Gross Profit<br><small>(Kharchy se phele profit)</small></span>
            <div class="metric-value"><?= formatCurrency($gross_profit) ?></div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Total Expenses<br><small>(Kul Akhrajaat)</small></span>
            <div class="metric-value text-red-500"><?= formatCurrency($expenses_amount) ?></div>
        </div>
        <div class="metric-card <?= $net_profit >= 0 ? 'metric-profit' : 'metric-loss' ?>">
            <span class="metric-label">Net Profit<br><small>(Expense nikal kr profit)</small></span>
            <div class="metric-value"><?= formatCurrency($net_profit) ?></div>
        </div>
        <div class="metric-card" style="background: #fdfaf0; border-color: #fde68a;">
            <span class="metric-label" style="color: #b45309;">Paid to Dealers<br><small>(Total Dealer Payments)</small></span>
            <div class="metric-value" style="color: #92400e;"><?= formatCurrency($dealer_paid_amount) ?></div>
        </div>
        <div class="metric-card" style="background: #f0f9ff; border-color: #bae6fd;">
            <span class="metric-label" style="color: #0369a1;">Inventory Movement<br><small>(IN vs OUT items)</small></span>
            <div class="metric-value" style="color: #0c4a6e; font-size: 22px;">In: <?= number_format($total_items_in) ?> | Out: <?= number_format($total_items_out) ?></div>
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

    <div style="margin-top: 40px;">
        <div>
            <div class="section-title">Product Performance (Sold Qty)</div>
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_qty = 0;
                    foreach($top_products as $pid => $qty): 
                        $total_qty += $qty;
                        $p_data = $p_map[$pid] ?? [];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($p_data['name'] ?? 'Unknown Item') ?></td>
                        <td><?= htmlspecialchars($p_data['category'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p_data['unit'] ?? '-') ?></td>
                        <td class="text-right font-black"><?= $qty ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8fafc;">
                        <td colspan="3" class="font-black" style="color: #0f766e;">TOTAL QUANTITY</td>
                        <td class="text-right font-black" style="color: #0f766e;"><?= $total_qty ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="margin-top: 40px;">
            <div class="section-title">Dealer Payments Detail</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 150px;">Date</th>
                        <th>Dealer Name</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dealer_payment_details)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 30px;">No dealer payments recorded in this period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($dealer_payment_details as $det): ?>
                        <tr>
                            <td><?= date('d M Y, h:i A', strtotime($det['date'])) ?></td>
                            <td class="font-black"><?= htmlspecialchars($det['dealer']) ?></td>
                            <td><span style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: #fef3c7; color: #92400e; font-weight: 800;"><?= $det['type'] ?></span></td>
                            <td><?= $det['p_type'] ?></td>
                            <td class="text-right font-black"><?= formatCurrency($det['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #fffbeb;">
                        <td colspan="4" class="font-black" style="color: #92400e;">TOTAL PAID TO DEALERS</td>
                        <td class="text-right font-black" style="color: #92400e;"><?= formatCurrency($dealer_paid_amount) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="footer">
        <p style="margin: 0; font-weight: bold; color: #0f172a;">REPORT END - Page 1 of 1</p>
        <p style="margin: 8px 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
        <p style="margin: 20px 0 0 0; font-weight: bold; background: #f8fafc; padding: 10px; border-radius: 10px;">Software by Abdul Rafay | WhatsApp: 03000358189</p>
    </div>

    <!-- Email Modal -->
    <div id="emailModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm hidden flex items-center justify-center z-[100] no-print">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md p-8 border border-gray-100 transform transition-all scale-95 opacity-0 animate-[modal-in_0.3s_ease-out_forwards]">
            <div class="flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-teal-50 rounded-2xl flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-black text-gray-800 mb-2">Email Report</h3>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Enter Recipient Details</p>
                
                <div class="w-full space-y-4">
                    <div class="relative group">
                        <label class="absolute -top-2.5 left-4 px-2 bg-white text-[10px] font-black text-teal-600 uppercase tracking-widest transition-all">Email Address</label>
                        <input type="email" id="recipientEmail" placeholder="example@gmail.com" 
                               class="w-full px-5 py-4 bg-gray-50 border-2 border-gray-100 rounded-2xl text-sm font-bold text-gray-700 focus:border-teal-500 focus:bg-white outline-none transition-all shadow-sm">
                    </div>
                    
                    <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl text-left">
                        <p class="text-[9px] font-bold text-blue-600 uppercase flex items-center gap-1.5 mb-1">
                            <i class="fas fa-info-circle"></i> Important Note
                        </p>
                        <p class="text-[10px] text-blue-700 leading-relaxed">
                            The report will download as a PDF. Please <b>manually attach</b> it from your 'Downloads' folder into the Gmail window that opens.
                        </p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button onclick="closeEmailModal()" class="flex-1 py-4 bg-gray-100 text-gray-500 font-black rounded-2xl hover:bg-gray-200 transition-all active:scale-95 text-xs uppercase tracking-widest">Cancel</button>
                        <button onclick="processEmailReport()" id="sendBtn" class="flex-[2] py-4 bg-teal-600 text-white font-black rounded-2xl hover:bg-teal-700 transition-all shadow-lg shadow-teal-900/20 active:scale-95 text-xs uppercase tracking-widest flex items-center justify-center gap-2">
                            <span>Ready to Send</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes modal-in {
            to { transform: scale(1); opacity: 1; }
        }
    </style>

    <script>
    function emailReport() {
        const modal = document.getElementById('emailModal');
        modal.classList.remove('hidden');
        document.getElementById('recipientEmail').focus();
    }

    function closeEmailModal() {
        document.getElementById('emailModal').classList.add('hidden');
    }

    async function processEmailReport() {
        const email = document.getElementById('recipientEmail').value;
        if (!email) {
            alert("Please enter a valid email address.");
            return;
        }

        const btn = document.getElementById('sendBtn');
        const originalContent = btn.innerHTML;
        
        btn.innerHTML = `<svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Generating...</span>`;
        btn.disabled = true;

        const element = document.body;
        const opt = {
            margin:       0.5,
            filename:     'Business_Report_<?= date('Y-m-d') ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

        try {
            // Generate and Download PDF
            await html2pdf().set(opt).from(element).save();

            // Open Gmail
            // Open Gmail
            const subject = encodeURIComponent("Business Summary Report - <?= getSetting('business_name', 'Fashion Shines') ?> (<?= $display_range ?>)");
            const body = encodeURIComponent("Assalam-o-Alaikum,\n\nPlease find attached (or attach) the Business Summary Report for the period: <?= $display_range ?>.\n\n[REMINDER: Please attach the PDF file that was just downloaded to your device].\n\nGenerated by <?= getSetting('business_name', 'Fashion Shines') ?>.");
            const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${email}&su=${subject}&body=${body}`;
            
            setTimeout(() => {
                window.open(gmailUrl, '_blank');
                btn.innerHTML = originalContent;
                btn.disabled = false;
                closeEmailModal();
            }, 1000);

        } catch (error) {
            console.error('PDF Error:', error);
            alert("Error generating PDF. Please try printing to PDF instead.");
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }
    }
    </script>
</body>
</html>
