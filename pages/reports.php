<?php
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
$thirty_days_ago_str = date('Y-m-d', strtotime('-30 days'));
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
    if ($s['sale_date'] >= $thirty_days_ago_str) $sales_month += $amount;
    
    // Calculate Profit
    $current_sale_items = array_filter($sale_items, function($item) use ($s) {
        return $item['sale_id'] == $s['id'];
    });
    
    foreach($current_sale_items as $item) {
        $qty = (float)$item['quantity'];
        $returned_qty = (float)($item['returned_qty'] ?? 0);
        $net_qty = $qty - $returned_qty;
        
        if ($net_qty <= 0) continue;

        // PRIORITY: 
        // 1. avg_buy_price (The accurate AVCO cost at time of sale)
        // 2. buy_price (The cost recorded at sale - fallback)
        // 3. Current Product AVCO (If historic data missing)
        
        $unit_cost = 0;
        if (isset($item['avg_buy_price']) && (float)$item['avg_buy_price'] > 0) {
            $unit_cost = (float)$item['avg_buy_price'];
        } elseif (isset($item['buy_price']) && (float)$item['buy_price'] > 0) {
            $unit_cost = (float)$item['buy_price'];
        } elseif (isset($products_map[$item['product_id']])) {
            $p = $products_map[$item['product_id']];
            $unit_cost = (isset($p['avg_buy_price']) && (float)$p['avg_buy_price'] > 0) ? (float)$p['avg_buy_price'] : (float)($p['buy_price'] ?? 0);
        }
        
        $cost = $unit_cost * $net_qty;
        // Adjust total_price per item for the partial quantity?
        // Actually, item['total_price'] is for the original quantity? 
        // Let's check sale_items.csv structure again. 
        // id,sale_id,product_id,quantity,price_per_unit,total_price,buy_price,avg_buy_price,returned_qty
        // total_price = quantity * price_per_unit.
        // So for net profit, we should use: net_qty * price_per_unit - net_qty * unit_cost.
        
        $price_per_unit = (float)$item['price_per_unit'];
        $item_revenue = $price_per_unit * $net_qty;
        $item_profit = $item_revenue - $cost;

        if ($is_today) $profit_today += $item_profit;
        if ($s['sale_date'] >= $thirty_days_ago_str) $profit_month += $item_profit;
    }
    
    $total_sales_amount += $amount;
    $total_paid_at_sale += (float)$s['paid_amount'];
}

$customer_txns = readCSV('customer_transactions');
$customers_data = readCSV('customers');
$customer_map = [];
foreach($customers_data as $c) $customer_map[$c['id']] = $c['name'];

$total_customer_payments = 0;
$total_debt_customers = 0;
$recovery_details = [];

// 1. Collect payments from Sales (paid at time of sale)
foreach($sales as $s) {
    if ((float)$s['paid_amount'] > 0) {
        $name = 'Walk-in Customer';
        if (!empty($s['customer_id']) && isset($customer_map[$s['customer_id']])) {
            $name = $customer_map[$s['customer_id']];
        }
        
        $recovery_details[] = [
            'date' => $s['sale_date'],
            'name' => $name,
            'amount' => (float)$s['paid_amount'],
            'type' => 'Sale Payment'
        ];
    }
}

// 2. Collect payments from Customer Ledger (credits)
foreach($customer_txns as $tx) {
    if ((float)$tx['credit'] > 0) {
        $total_customer_payments += (float)$tx['credit'];
        $recovery_details[] = [
            'date' => $tx['date'],
            'name' => $customer_map[$tx['customer_id']] ?? 'Unknown Customer',
            'amount' => (float)$tx['credit'],
            'type' => 'Ledger Payment'
        ];
    }
    $total_debt_customers += (float)$tx['debit'] - (float)$tx['credit'];
}

// Sort recovery details by date descending
usort($recovery_details, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

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
    if($e['date'] >= $thirty_days_ago_str) $expenses_month += (float)$e['amount'];
}

$net_profit_today = $profit_today - $expenses_today;
$net_profit_month = $profit_month - $expenses_month;

$total_recovered = $total_paid_at_sale + $total_customer_payments;

$total_recovered = $total_paid_at_sale + $total_customer_payments;

// Report Ranges Configuration
$report_ranges = [
    'today' => 'Today',
    'week' => 'Last 7 Days',
    'month' => 'Last 30 Days',
    'custom' => 'Custom Range'
];

?>

<!-- Header with Print Action -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-black text-gray-800 tracking-tight">Financial Reports</h1>
        <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">Real-time business performance analytics</p>
    </div>
    <div class="flex gap-3">
        <button onclick="openGeneralReportModal()" class="bg-teal-600 hover:bg-teal-700 text-white px-6 py-3 rounded-2xl shadow-lg shadow-teal-900/10 transition flex items-center gap-2 font-bold text-sm">
            <i class="fas fa-print"></i> Print / Save PDF
        </button>
    </div>
</div>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <a href="sales_history.php?date=<?= date('Y-m-d') ?>" class="bg-white p-6 rounded-2xl shadow-sm border-t-4 border-teal-500 hover:shadow-lg transition transform hover:-translate-y-1">
         <h3 class="text-gray-500 text-xs uppercase font-bold tracking-wider">Sales (Today)</h3>
         <p class="text-3xl font-black text-gray-800 tracking-tight mt-1"><?= formatCurrency($sales_today) ?></p>
    </a>
     <a href="sales_history.php?f_type=30days" class="bg-white p-6 rounded-2xl shadow-sm border-t-4 border-green-500 hover:shadow-lg transition transform hover:-translate-y-1">
        <h3 class="text-gray-500 text-xs uppercase font-bold tracking-wider">Sales (Last 30 Days)</h3>
        <p class="text-3xl font-black text-gray-800 tracking-tight mt-1"><?= formatCurrency($sales_month) ?></p>
    </a>
    
    <!-- Expense Cards -->
    <a href="expenses.php" class="bg-white p-6 rounded-2xl shadow-sm border-t-4 border-red-500 hover:shadow-lg transition transform hover:-translate-y-1">
        <h3 class="text-red-500 text-xs uppercase font-bold tracking-wider">Expenses (Last 30 Days)</h3>
        <p class="text-3xl font-black text-gray-800 tracking-tight mt-1"><?= formatCurrency($expenses_month) ?></p>
    </a>

    <!-- Recovery Card -->
    <div onclick="showRecoveryDetails()" class="bg-white p-6 rounded-2xl shadow-sm border-t-4 border-teal-500 hover:shadow-lg transition transform hover:-translate-y-1 block cursor-pointer">
        <h3 class="text-gray-500 text-xs uppercase font-bold tracking-wider">Total Recovered</h3>
        <p class="text-3xl font-black text-teal-600 tracking-tight mt-1"><?= formatCurrency($total_recovered) ?></p>
        <p class="text-[9px] text-gray-400 font-bold uppercase mt-2">Click to view breakdown</p>
    </div>
</div>

<!-- Detailed Profit & Debt Analysis -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Net Profit Card -->
    <div class="bg-gradient-to-br from-emerald-600 to-teal-700 p-8 rounded-[2rem] shadow-xl text-white">
        <h4 class="text-emerald-100 text-xs font-bold uppercase tracking-widest mb-4">Net Profit (Last 30 Days)</h4>
        <p class="text-5xl font-black tracking-tighter mb-2"><?= formatCurrency($net_profit_month) ?></p>
        <p class="text-emerald-200 text-[10px] font-medium uppercase tracking-tight">After deducting all expenses</p>
        
        <div class="mt-8 pt-6 border-t border-white/10 space-y-3">
             <div class="flex justify-between items-center text-sm">
                <span class="text-emerald-200">Gross Profit (Last 30 Days)</span>
                <span class="font-bold text-white"><?= formatCurrency($profit_month) ?></span>
             </div>
             <div class="flex justify-between items-center text-sm">
                <span class="text-emerald-200">Total Expenses (30 Days)</span>
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

<!-- Recovery Details Modal -->
<div id="recoveryModal" class="fixed inset-0 bg-black/60 hidden z-50 flex items-center justify-center backdrop-blur-sm p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-5xl max-h-[95vh] overflow-hidden flex flex-col scale-100 transition-all">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-teal-600 text-white">
            <div>
                <h3 class="text-xl font-bold">Recovered Payments Breakdown</h3>
                <p class="text-xs opacity-80 mt-1">Showing all payments from sales and customer ledgers</p>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="printRecoveryReport()" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2">
                    <i class="fas fa-print"></i> Print / Save PDF
                </button>
                <button onclick="document.getElementById('recoveryModal').classList.add('hidden')" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-white/20 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1 bg-gray-50/30">
            <!-- Filter Bar -->
            <div class="bg-white p-4 rounded-3xl shadow-sm border border-gray-100 mb-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex gap-2">
                        <button onclick="filterRecovery('all')" class="recovery-filter-btn active px-4 py-2 rounded-xl text-xs font-bold transition-all border border-teal-100 bg-teal-50 text-teal-600">All Time</button>
                        <button onclick="filterRecovery('today')" class="recovery-filter-btn px-4 py-2 rounded-xl text-xs font-bold transition-all border border-gray-100 text-gray-500 hover:bg-gray-50">Today</button>
                        <button onclick="filterRecovery('month')" class="recovery-filter-btn px-4 py-2 rounded-xl text-xs font-bold transition-all border border-gray-100 text-gray-500 hover:bg-gray-50">This Month</button>
                        <button onclick="filterRecovery('60days')" class="recovery-filter-btn px-4 py-2 rounded-xl text-xs font-bold transition-all border border-gray-100 text-gray-500 hover:bg-gray-50">Last 60 Days</button>
                    </div>
                    
                    <div class="flex items-center gap-2 bg-gray-50 p-2 rounded-2xl border border-gray-100">
                        <input type="date" id="recoveryFromDate" class="bg-transparent border-none text-[10px] font-bold text-gray-600 outline-none w-28">
                        <span class="text-gray-300 text-xs">to</span>
                        <input type="date" id="recoveryToDate" class="bg-transparent border-none text-[10px] font-bold text-gray-600 outline-none w-28">
                        <button onclick="filterRecovery('custom')" class="bg-teal-600 text-white p-2 rounded-xl hover:bg-teal-700 transition">
                            <i class="fas fa-search text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-teal-50 p-6 rounded-[2rem] mb-6 flex justify-between items-center border border-teal-100">
                <div>
                     <span class="text-xs font-bold text-teal-400 uppercase tracking-widest block mb-1">Recovery Summary</span>
                     <span class="text-sm font-bold text-teal-700" id="recoveryStatsTitle">All Time Recovery</span>
                </div>
                <div class="text-right">
                    <span class="text-xs font-bold text-teal-400 uppercase tracking-widest block mb-1">Total Amount</span>
                    <span class="text-3xl font-black text-teal-800" id="recoveryTotalText"><?= formatCurrency($total_recovered) ?></span>
                </div>
            </div>
            
            <div id="recoveryPrintableContainer" class="bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 bg-gray-50/50">
                            <th class="py-4 pl-6">Sr #</th>
                            <th class="py-4">Date</th>
                            <th class="py-4">Customer / Source</th>
                            <th class="py-4">Type</th>
                            <th class="py-4 text-right pr-6">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="recoveryTableBody" class="divide-y divide-gray-50">
                        <!-- Content via JS -->
                    </tbody>
                </table>
                <div id="noRecoveryMessage" class="hidden p-20 text-center text-gray-400">
                    <i class="fas fa-search-dollar text-5xl mb-4 opacity-20"></i>
                    <p class="font-bold">No transactions found for the selected period.</p>
                </div>
                <div id="recoveryPagination" class="px-6 py-4 bg-gray-50 border-t border-gray-100"></div>
            </div>
        </div>
        
        <div class="p-4 bg-gray-50 border-t border-gray-100 text-center">
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">End of Detailed Report</p>
        </div>
    </div>
</div>

<script>
// Pass initial data to JS
const recoveryData = <?= json_encode($recovery_details) ?>;
let filteredRecoveryData = recoveryData;
let currentPage_Rec = 1;
const pageSize_Rec = 200;

function formatCurrencyJS(amount) {
    return 'Rs. ' + Number(amount).toLocaleString();
}

function renderRecoveryTable(data) {
    const tbody = document.getElementById('recoveryTableBody');
    const noMsg = document.getElementById('noRecoveryMessage');
    const totalText = document.getElementById('recoveryTotalText');
    
    // Save filtered data for pagination
    filteredRecoveryData = data;

    tbody.innerHTML = '';
    let total = 0;
    
    if (data.length === 0) {
        noMsg.classList.remove('hidden');
        totalText.innerText = formatCurrencyJS(0);
        Pagination.render('recoveryPagination', 0, 1, pageSize_Rec, changePage_Rec);
        return;
    }
    
    noMsg.classList.add('hidden');

    const paginated = Pagination.paginate(data, currentPage_Rec, pageSize_Rec);
    
    paginated.forEach((item, index) => {
        const sn = (currentPage_Rec - 1) * pageSize_Rec + index + 1;
        total += Number(item.amount); // This 'total' is tricky if we want total of ALL filtered, not just paginated.
        // Actually, the original code calculates total from 'data' (all filtered).
    });

    // Recalculate total for ALL filtered data
    let fullTotal = data.reduce((acc, item) => acc + Number(item.amount), 0);

    paginated.forEach((item, index) => {
        const sn = (currentPage_Rec - 1) * pageSize_Rec + index + 1;
        const date = new Date(item.date);
        const dateStr = date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' + 
                        date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        const row = `
            <tr class="hover:bg-teal-50/30 transition-colors group border-b border-gray-50">
                <td class="py-4 pl-6 text-[10px] font-bold text-gray-400 font-mono">${sn}</td>
                <td class="py-4 text-xs font-medium text-gray-500">${dateStr}</td>
                <td class="py-4">
                    <div class="text-sm font-bold text-gray-800 group-hover:text-teal-600 transition-colors">${item.name}</div>
                </td>
                <td class="py-4">
                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase border ${item.type === 'Sale Payment' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-teal-50 text-teal-600 border-teal-100'}">
                        ${item.type}
                    </span>
                </td>
                <td class="py-4 text-right pr-6 font-black text-gray-900">
                    ${formatCurrencyJS(item.amount)}
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
    
    totalText.innerText = formatCurrencyJS(fullTotal);
    Pagination.render('recoveryPagination', data.length, currentPage_Rec, pageSize_Rec, changePage_Rec);
}

function changePage_Rec(page) {
    currentPage_Rec = page;
    renderRecoveryTable(filteredRecoveryData);
}

function filterRecovery(range) {
    // UI Update
    document.querySelectorAll('.recovery-filter-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-teal-50', 'text-teal-600', 'border-teal-100');
        btn.classList.add('text-gray-500', 'border-gray-100');
        if (btn.innerText.toLowerCase().includes(range)) {
             btn.classList.add('active', 'bg-teal-50', 'text-teal-600', 'border-teal-100');
             btn.classList.remove('text-gray-500', 'border-gray-100');
        }
    });

    if (range === 'all' && event) {
         event.target.classList.add('active', 'bg-teal-50', 'text-teal-600', 'border-teal-100');
         event.target.classList.remove('text-gray-500', 'border-gray-100');
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const titleText = document.getElementById('recoveryStatsTitle');
    
    currentPage_Rec = 1;
    let filtered = recoveryData;
    
    if (range === 'today') {
        titleText.innerText = "Today's Recovery";
        filtered = recoveryData.filter(d => new Date(d.date) >= today);
    } else if (range === 'month') {
        titleText.innerText = "This Month's Recovery";
        const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        filtered = recoveryData.filter(d => new Date(d.date) >= startOfMonth);
    } else if (range === '60days') {
        titleText.innerText = "Last 60 Days Recovery";
        const sixtyDaysAgo = new Date();
        sixtyDaysAgo.setDate(today.getDate() - 60);
        filtered = recoveryData.filter(d => new Date(d.date) >= sixtyDaysAgo);
    } else if (range === 'custom') {
        const fromDate = document.getElementById('recoveryFromDate').value;
        const toDate = document.getElementById('recoveryToDate').value;
        if (!fromDate || !toDate) {
            alert('Please select both from and to dates');
            return;
        }
        titleText.innerText = `Recovery from ${fromDate} to ${toDate}`;
        const start = new Date(fromDate);
        start.setHours(0,0,0,0);
        const end = new Date(toDate);
        end.setHours(23,59,59,999);
        filtered = recoveryData.filter(d => {
            const date = new Date(d.date);
            return date >= start && date <= end;
        });
    } else {
        titleText.innerText = "All Time Recovery";
    }
    
    renderRecoveryTable(filtered);
}

function printRecoveryReport() {
    const content = document.getElementById('recoveryPrintableContainer').innerHTML;
    const stats = document.getElementById('recoveryStatsTitle').innerText;
    const total = document.getElementById('recoveryTotalText').innerText;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Recovery Report</title>
            <link rel="icon" type="image/png" href="../assets/img/favicon.png">
            <style>
                body { font-family: sans-serif; padding: 40px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { padding: 12px; border: 1px solid #eee; text-align: left; font-size: 12px; }
                th { background: #f8fafc; font-weight: bold; text-transform: uppercase; color: #64748b; }
                .header { display: flex; justify-content: space-between; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px; }
                .total-box { background: #f0fdfa; padding: 20px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
                .text-right { text-align: right; }
                .font-black { font-weight: 900; }
            </style>
        </head>
        <body>
            <div class="header">
                <div>
                     <h1 style="margin: 0; color: #0f766e;"><?= getSetting('business_name', 'Fashion Shines') ?></h1>
                     <p style="margin: 5px 0 0 0; color: #64748b;">Recovered Payments Report</p>
                </div>
                <div class="text-right">
                     <h3 style="margin: 0;">${stats}</h3>
                     <p style="margin: 5px 0 0 0; color: #94a3b8; font-size: 10px;">Generated: ${new Date().toLocaleString()}</p>
                </div>
            </div>
            
            <div class="total-box">
                <span style="font-weight: bold; color: #0f766e;">Total Amount Recovered:</span>
                <span style="font-size: 24px; font-weight: 900; color: #134e4a;">${total}</span>
            </div>
            
            ${content}

            <div style="margin-top: 50px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
                <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

function showRecoveryDetails() {
    document.getElementById('recoveryModal').classList.remove('hidden');
    // Initialize with all data
    document.getElementById('recoveryFromDate').value = '';
    document.getElementById('recoveryToDate').value = '';
    filterRecovery('all');
}
</script>


<!-- General Report Selection Modal -->
<div id="generalReportModal" class="fixed inset-0 bg-black/60 hidden z-[60] flex items-center justify-center backdrop-blur-sm p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in duration-200">
        <div class="p-6 bg-teal-600 text-white flex justify-between items-center">
            <h3 class="font-bold">Select Report Period</h3>
            <button onclick="closeGeneralReportModal()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form action="print_general_report.php" target="_blank" method="GET" class="p-8 space-y-6">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Time Period</label>
                <select name="range" id="reportRangeSelect" onchange="toggleReportCustomDates()" class="w-full bg-gray-50 border border-gray-100 p-3 rounded-xl text-sm font-bold text-gray-700 outline-none focus:ring-2 focus:ring-teal-500/20">
                    <?php foreach($report_ranges as $val => $label): ?>
                    <option value="<?= $val ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="reportCustomRange" class="hidden grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">From</label>
                    <input type="date" name="from" class="w-full bg-gray-50 border border-gray-100 p-2.5 rounded-xl text-xs font-bold text-gray-700">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">To</label>
                    <input type="date" name="to" class="w-full bg-gray-50 border border-gray-100 p-2.5 rounded-xl text-xs font-bold text-gray-700">
                </div>
            </div>

            <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-black py-4 rounded-2xl shadow-lg transition active:scale-95 uppercase tracking-widest text-xs">
                Generate Full Report
            </button>
        </form>
    </div>
</div>

<script>
function openGeneralReportModal() {
    document.getElementById('generalReportModal').classList.remove('hidden');
    toggleReportCustomDates();
}
function closeGeneralReportModal() {
    document.getElementById('generalReportModal').classList.add('hidden');
}
function toggleReportCustomDates() {
    const rangeSelect = document.getElementById('reportRangeSelect');
    const customRangeDiv = document.getElementById('reportCustomRange');
    
    if (rangeSelect.value === 'custom') {
        customRangeDiv.style.display = 'grid';
    } else {
        customRangeDiv.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
