<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$pageTitle = "Reports & Analytics";
include '../includes/header.php';

$sales = readCSV('sales');
$dealer_txns = readCSV('dealer_transactions');

$sales_today = 0;
$sales_month = 0;
$profit_today = 0;
$profit_month = 0;
$total_sales_amount = 0;
$total_paid_at_sale = 0;

$today_str = date('Y-m-d');
$month_str = date('Y-m');

$sale_items = readCSV('sale_items');
$products_map = [];
$prods = readCSV('products');
foreach($prods as $p) $products_map[$p['id']] = $p;

foreach($sales as $s) {
    $amount = (float)$s['total_amount'];
    $is_today = (strpos($s['sale_date'], $today_str) === 0);
    $is_month = (strpos($s['sale_date'], $month_str) === 0);
    
    if ($is_today) $sales_today += $amount;
    if ($is_month) $sales_month += $amount;
    
    // Calculate Profit
    $current_sale_items = array_filter($sale_items, function($item) use ($s) {
        return $item['sale_id'] == $s['id'];
    });
    
    foreach($current_sale_items as $item) {
        // PRIORITY: 
        // 1. avg_buy_price (The accurate AVCO cost at time of sale)
        // 2. buy_price (The cost recorded at sale - fallback)
        // 3. Current Product AVCO (If historic data missing)
        
        $unit_cost = 0;
        if (isset($item['avg_buy_price']) && $item['avg_buy_price'] !== '') {
            $unit_cost = (float)$item['avg_buy_price'];
        } elseif (isset($item['buy_price']) && $item['buy_price'] !== '') {
            $unit_cost = (float)$item['buy_price'];
        } elseif (isset($products_map[$item['product_id']])) {
            // Fallback for very old data
            $p = $products_map[$item['product_id']];
            $unit_cost = isset($p['avg_buy_price']) ? (float)$p['avg_buy_price'] : (float)$p['buy_price'];
        }
        $cost = $unit_cost * (float)$item['quantity'];
        $profit = (float)$item['total_price'] - $cost;
        if ($is_today) $profit_today += $profit;
        if ($is_month) $profit_month += $profit;
    }
    
    $total_sales_amount += $amount;
    $total_paid_at_sale += (float)$s['paid_amount'];
}

$all_payments = readCSV('customer_payments');
$total_customer_payments = 0;
foreach($all_payments as $pm) {
    $total_customer_payments += (float)$pm['amount'];
}
$total_debt_customers = $total_sales_amount - $total_paid_at_sale - $total_customer_payments;

$dealer_purchases = 0;
$dealer_payments = 0;
foreach($dealer_txns as $t) {
    if($t['type'] == 'Purchase') $dealer_purchases += (float)$t['amount'];
    else $dealer_payments += (float)$t['amount'];
}
$total_debt_dealers = $dealer_purchases - $dealer_payments;

$total_recovered = $total_paid_at_sale + $total_customer_payments;

?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <a href="sales_history.php?month=<?= date('Y-m') ?>" class="bg-white p-6 rounded-xl shadow border-t-4 border-green-500 hover:shadow-lg transition transform hover:-translate-y-1">
        <h3 class="text-gray-500 text-sm uppercase">Sales (This Month)</h3>
        <p class="text-3xl font-bold text-gray-800"><?= formatCurrency($sales_month) ?></p>
    </a>
    <a href="sales_history.php?date=<?= date('Y-m-d') ?>" class="bg-white p-6 rounded-xl shadow border-t-4 border-teal-500 hover:shadow-lg transition transform hover:-translate-y-1">
         <h3 class="text-gray-500 text-sm uppercase">Sales (Today)</h3>
         <p class="text-3xl font-bold text-gray-800"><?= formatCurrency($sales_today) ?></p>
    </a>
    <a href="sales_history.php" class="bg-white p-6 rounded-xl shadow border-t-4 border-blue-500 hover:shadow-lg transition transform hover:-translate-y-1 block">
        <h3 class="text-gray-500 text-sm uppercase">Total Recovered</h3>
        <p class="text-3xl font-bold text-green-600"><?= formatCurrency($total_recovered) ?></p>
    </a>
    <a href="sales_history.php?date=<?= date('Y-m-d') ?>" class="bg-white p-6 rounded-xl shadow border-t-4 border-emerald-500 hover:shadow-lg transition transform hover:-translate-y-1 block">
        <h3 class="text-gray-500 text-sm uppercase font-bold text-emerald-600">Profit (Today)</h3>
        <p class="text-3xl font-bold text-gray-800"><?= formatCurrency($profit_today) ?></p>
    </a>
    <a href="sales_history.php?month=<?= date('Y-m') ?>" class="bg-white p-6 rounded-xl shadow border-t-4 border-emerald-600 hover:shadow-lg transition transform hover:-translate-y-1 block">
        <h3 class="text-gray-500 text-sm uppercase font-bold text-emerald-700">Profit (This Month)</h3>
        <p class="text-3xl font-bold text-gray-800"><?= formatCurrency($profit_month) ?></p>
    </a>
    <a href="customers.php" class="bg-white p-6 rounded-xl shadow border-t-4 border-red-500 hover:shadow-lg transition transform hover:-translate-y-1 block">
        <h3 class="text-gray-500 text-sm uppercase">Customer Debt</h3>
        <p class="text-3xl font-bold text-red-600"><?= formatCurrency($total_debt_customers) ?></p>
    </a>
    <a href="dealers.php" class="bg-white p-6 rounded-xl shadow border-t-4 border-amber-500 hover:shadow-lg transition transform hover:-translate-y-1 block">
        <h3 class="text-gray-500 text-sm uppercase">Dealer Payable</h3>
        <p class="text-3xl font-bold text-amber-600"><?= formatCurrency($total_debt_dealers) ?></p>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
             <i class="fas fa-chart-pie text-teal-500 mr-3"></i> Monthly Performance
        </h3>
        <div class="grid grid-cols-2 gap-4">
             <a href="sales_history.php" class="block p-4 bg-gray-50 hover:bg-gray-100 rounded text-center border">
                <i class="fas fa-receipt text-2xl text-gray-500 mb-2"></i>
                <div class="font-medium">All Sales History</div>
             </a>
             <a href="print_sales.php" target="_blank" class="block p-4 bg-gray-50 hover:bg-gray-100 rounded text-center border">
                <i class="fas fa-print text-2xl text-gray-500 mb-2"></i>
                <div class="font-medium">Print Report</div>
             </a>
        </div>
    </div>
     <div class="bg-white rounded-xl shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-gray-800">Database Info</h3>
        </div>
        <p class="text-sm text-gray-600 mb-2">All data is stored in CSV files in the <code class="bg-gray-200 px-1">/data</code> folder.</p>
        <p class="text-sm text-gray-600">You can open these files with Microsoft Excel to view or edit raw data.</p>
        <div class="mt-4 p-4 bg-blue-50 text-blue-800 rounded text-sm">
             <i class="fas fa-info-circle mr-1"></i> Tip: Make backups of the 'data' folder regularly.
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
