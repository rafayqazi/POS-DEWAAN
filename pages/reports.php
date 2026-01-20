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
    $dealer_purchases += (float)($t['debit'] ?? 0);
    $dealer_payments += (float)($t['credit'] ?? 0);
}
$total_debt_dealers = $dealer_purchases - $dealer_payments;

$expenses_data = readCSV('expenses');
$expenses_today = 0;
$expenses_month = 0;
foreach($expenses_data as $e) {
    if(strpos($e['date'], $today_str) === 0) $expenses_today += (float)$e['amount'];
    if(strpos($e['date'], $month_str) === 0) $expenses_month += (float)$e['amount'];
}

$net_profit_today = $profit_today - $expenses_today;
$net_profit_month = $profit_month - $expenses_month;

$total_recovered = $total_paid_at_sale + $total_customer_payments;

$total_recovered = $total_paid_at_sale + $total_customer_payments;

?>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Sales Cards -->
    <a href="sales_history.php?date=<?= date('Y-m-d') ?>" class="bg-white p-6 rounded-2xl shadow-sm border-t-4 border-teal-500 hover:shadow-lg transition transform hover:-translate-y-1">
         <h3 class="text-gray-500 text-xs uppercase font-bold tracking-wider">Sales (Today)</h3>
         <p class="text-3xl font-black text-gray-800 tracking-tight mt-1"><?= formatCurrency($sales_today) ?></p>
    </a>
    <a href="sales_history.php?month=<?= date('Y-m') ?>" class="bg-white p-6 rounded-2xl shadow-sm border-t-4 border-green-500 hover:shadow-lg transition transform hover:-translate-y-1">
        <h3 class="text-gray-500 text-xs uppercase font-bold tracking-wider">Sales (Month)</h3>
        <p class="text-3xl font-black text-gray-800 tracking-tight mt-1"><?= formatCurrency($sales_month) ?></p>
    </a>
    
    <!-- Expense Cards -->
    <a href="expenses.php" class="bg-white p-6 rounded-2xl shadow-sm border-t-4 border-red-500 hover:shadow-lg transition transform hover:-translate-y-1">
        <h3 class="text-red-500 text-xs uppercase font-bold tracking-wider">Expenses (Month)</h3>
        <p class="text-3xl font-black text-gray-800 tracking-tight mt-1"><?= formatCurrency($expenses_month) ?></p>
    </a>

    <!-- Recovery Card -->
    <a href="sales_history.php" class="bg-white p-6 rounded-2xl shadow-sm border-t-4 border-blue-500 hover:shadow-lg transition transform hover:-translate-y-1 block">
        <h3 class="text-gray-500 text-xs uppercase font-bold tracking-wider">Total Recovered</h3>
        <p class="text-3xl font-black text-blue-600 tracking-tight mt-1"><?= formatCurrency($total_recovered) ?></p>
    </a>
</div>

<!-- Detailed Profit & Debt Analysis -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Net Profit Card -->
    <div class="bg-gradient-to-br from-emerald-600 to-teal-700 p-8 rounded-[2rem] shadow-xl text-white">
        <h4 class="text-emerald-100 text-xs font-bold uppercase tracking-widest mb-4">Net Profit (This Month)</h4>
        <p class="text-5xl font-black tracking-tighter mb-2"><?= formatCurrency($net_profit_month) ?></p>
        <p class="text-emerald-200 text-[10px] font-medium uppercase tracking-tight">After deducting all expenses</p>
        
        <div class="mt-8 pt-6 border-t border-white/10 space-y-3">
             <div class="flex justify-between items-center text-sm">
                <span class="text-emerald-200">Gross Profit (Sales-Cost)</span>
                <span class="font-bold text-white"><?= formatCurrency($profit_month) ?></span>
             </div>
             <div class="flex justify-between items-center text-sm">
                <span class="text-emerald-200">Total Monthly Expenses</span>
                <span class="font-bold text-red-300">- <?= formatCurrency($expenses_month) ?></span>
             </div>
        </div>
    </div>

    <!-- Today's Deep Dive -->
    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col justify-between">
        <div>
            <h4 class="text-gray-400 text-xs font-bold uppercase tracking-widest mb-6">Today's Performance</h4>
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-3 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Daily Sales</span>
                    <span class="font-bold text-gray-800"><?= formatCurrency($sales_today) ?></span>
                </div>
                <div class="flex justify-between items-center pb-3 border-b border-gray-50">
                    <span class="text-sm text-gray-600">Daily Expenses</span>
                    <span class="font-bold text-red-500">- <?= formatCurrency($expenses_today) ?></span>
                </div>
                <div class="flex justify-between items-center pt-2">
                    <span class="text-sm font-bold text-gray-800">Net Profit (Today)</span>
                    <span class="text-2xl font-black text-emerald-600"><?= formatCurrency($net_profit_today) ?></span>
                </div>
            </div>
        </div>
        <div class="mt-6">
            <a href="sales_history.php?date=<?= date('Y-m-d') ?>" class="text-[10px] font-bold text-primary uppercase tracking-widest hover:underline flex items-center gap-1">
                View Today's Transactions <i class="fas fa-arrow-right text-[8px]"></i>
            </a>
        </div>
    </div>

    <!-- Debt Summary -->
    <div class="space-y-6">
        <a href="customers.php" class="block bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 group hover:border-red-200 transition-colors">
            <div class="flex items-center gap-4 mb-3">
                <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center group-hover:bg-red-500 group-hover:text-white transition-colors">
                    <i class="fas fa-user-minus"></i>
                </div>
                <h4 class="text-gray-500 text-[10px] font-bold uppercase tracking-widest">Customer Receivables</h4>
            </div>
            <p class="text-3xl font-black text-red-600 tracking-tight"><?= formatCurrency($total_debt_customers) ?></p>
        </a>
        <a href="dealers.php" class="block bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 group hover:border-amber-200 transition-colors">
            <div class="flex items-center gap-4 mb-3">
                <div class="w-10 h-10 bg-amber-50 text-amber-500 rounded-xl flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition-colors">
                    <i class="fas fa-truck-loading"></i>
                </div>
                <h4 class="text-gray-500 text-[10px] font-bold uppercase tracking-widest">Dealer Payables</h4>
            </div>
            <p class="text-3xl font-black text-amber-600 tracking-tight"><?= formatCurrency($total_debt_dealers) ?></p>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
             <i class="fas fa-rocket text-teal-500 mr-3 text-sm"></i> Shortcuts
        </h3>
        <div class="grid grid-cols-3 gap-4">
             <a href="sales_history.php" class="block p-4 bg-gray-50 hover:bg-teal-50 rounded-2xl text-center border border-gray-100 transition-all group">
                <i class="fas fa-receipt text-xl text-teal-500 mb-2 group-hover:scale-110 transition-transform"></i>
                <div class="text-[10px] font-bold text-gray-600 uppercase">Sales</div>
             </a>
             <a href="expenses.php" class="block p-4 bg-gray-50 hover:bg-red-50 rounded-2xl text-center border border-gray-100 transition-all group">
                <i class="fas fa-wallet text-xl text-red-500 mb-2 group-hover:scale-110 transition-transform"></i>
                <div class="text-[10px] font-bold text-gray-600 uppercase">Expenses</div>
             </a>
             <a href="inventory.php" class="block p-4 bg-gray-50 hover:bg-blue-50 rounded-2xl text-center border border-gray-100 transition-all group">
                <i class="fas fa-boxes text-xl text-blue-500 mb-2 group-hover:scale-110 transition-transform"></i>
                <div class="text-[10px] font-bold text-gray-600 uppercase">Stock</div>
             </a>
        </div>
    </div>
    <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 p-8">
        <h3 class="font-bold text-gray-800 flex items-center mb-4">
            <i class="fas fa-calculator text-blue-500 mr-3 text-sm"></i> Profit Calculation Logic
        </h3>
        <p class="text-xs text-gray-500 leading-relaxed">
            We use <span class="font-bold text-gray-700 underline decoration-teal-500">Accrual Accounting</span> principles:
        </p>
        <ul class="mt-3 space-y-2">
            <li class="text-[10px] text-gray-500 flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-teal-500 rounded-full"></span>
                <strong>Gross Profit:</strong> Total Sales Revenue minus Original Product Cost.
            </li>
            <li class="text-[10px] text-gray-500 flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>
                <strong>Net Profit:</strong> Gross Profit minus Daily/Monthly Operating Expenses.
            </li>
        </ul>
    </div>
</div>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
