<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$pageTitle = "Sales History";
include '../includes/header.php';

// Manual Join
$sales = readCSV('sales');
$sale_items = readCSV('sale_items');
$products = readCSV('products');
$customers = readCSV('customers');

$c_map = [];
foreach($customers as $c) $c_map[$c['id']] = $c['name'];

// NEW: FIFO Payment Allocation Logic
$transactions = readCSV('customer_transactions');
$customer_credits = []; // total credits per customer
foreach($transactions as $t) {
    $cid = $t['customer_id'];
    $customer_credits[$cid] = ($customer_credits[$cid] ?? 0) + (float)$t['credit'];
}

// Sort all sales by date ASC for FIFO allocation
$all_sales_fifo = readCSV('sales');
usort($all_sales_fifo, function($a, $b) {
    return strtotime($a['sale_date']) - strtotime($b['sale_date']);
});

$sale_paid_adj = []; // sale_id => adjusted_paid_amount
foreach($all_sales_fifo as $as) {
    if (empty($as['customer_id'])) {
        $sale_paid_adj[$as['id']] = (float)$as['paid_amount'];
        continue;
    }
    $cid = $as['customer_id'];
    $total_sale = (float)$as['total_amount'];
    $allocated = min($total_sale, ($customer_credits[$cid] ?? 0));
    $sale_paid_adj[$as['id']] = $allocated;
    $customer_credits[$cid] = ($customer_credits[$cid] ?? 0) - $allocated;
}

$p_cost_map = [];
foreach($products as $p) $p_cost_map[$p['id']] = (float)$p['buy_price'];

// Filtering Logic
$f_type = $_GET['f_type'] ?? 'month';
$current_year = date('Y');
$current_month = date('Y-m');

if ($f_type == 'range' && !empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to = $_GET['to'];
    $sales = array_filter($sales, function($s) use ($from, $to) {
        $date = date('Y-m-d', strtotime($s['sale_date']));
        return $date >= $from && $date <= $to;
    });
} elseif ($f_type == 'year' && !empty($_GET['year'])) {
    $year = $_GET['year'];
    $sales = array_filter($sales, function($s) use ($year) {
        return strpos($s['sale_date'], $year) === 0;
    });
} elseif ($f_type == 'month' && !empty($_GET['month'])) {
    $month = $_GET['month'];
    $sales = array_filter($sales, function($s) use ($month) {
        return strpos($s['sale_date'], $month) === 0;
    });
} else {
    // Default to current month if no filter applied
    $sales = array_filter($sales, function($s) use ($current_month) {
        return strpos($s['sale_date'], $current_month) === 0;
    });
}

// Sort Descending
usort($sales, function($a, $b) {
    return strtotime($b['sale_date']) - strtotime($a['sale_date']);
});

// Calculate Analytics
$stats = [
    'revenue' => 0,
    'profit' => 0,
    'recovered' => 0,
    'debt' => 0,
    'labels' => [],
    'revenue_data' => [],
    'profit_data' => []
];

$chart_data = [];

foreach($sales as $s) {
    $sale_id = $s['id'];
    $revenue = (float)$s['total_amount'];
    $paid = $sale_paid_adj[$s['id']] ?? (float)$s['paid_amount'];
    $stats['revenue'] += $revenue;
    $stats['recovered'] += $paid;
    $stats['debt'] += ($revenue - $paid);
    
    // Profit Calculation
    $sale_profit = 0;
    foreach($sale_items as $item) {
        if ($item['sale_id'] == $sale_id) {
            $unit_cost = (isset($item['buy_price']) && $item['buy_price'] !== '') ? (float)$item['buy_price'] : ($p_cost_map[$item['product_id']] ?? 0);
            $cost = $unit_cost * (float)$item['quantity'];
            $sale_profit += ((float)$item['total_price'] - $cost);
        }
    }
    $stats['profit'] += $sale_profit;
    
    // Chart grouping (by date)
    $date = date('d M', strtotime($s['sale_date']));
    if (!isset($chart_data[$date])) {
        $chart_data[$date] = ['r' => 0, 'p' => 0];
    }
    $chart_data[$date]['r'] += $revenue;
    $chart_data[$date]['p'] += $sale_profit;
}

// Prepare Chart Arrays (Reverse to show chronological)
$chart_data = array_reverse($chart_data);
$stats['labels'] = array_keys($chart_data);
$stats['revenue_data'] = array_column($chart_data, 'r');
$stats['profit_data'] = array_column($chart_data, 'p');
?>

<!-- PDF Download Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function downloadPDF() {
        const element = document.getElementById('salesTableContainer');
        const opt = {
            margin:       0.5,
            filename:     'sales_report_<?= date('Y-m-d') ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
        };
        html2pdf().set(opt).from(element).save();
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.sale-checkbox');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        toggleBulkBtn();
    }

    function toggleBulkBtn() {
        const checkboxes = document.querySelectorAll('.sale-checkbox:checked');
        const btn = document.getElementById('bulkDeleteBtn');
        if (checkboxes.length > 0) {
            btn.classList.remove('hidden');
        } else {
            btn.classList.add('hidden');
        }
    }

    function confirmDelete(url) {
        showConfirm('Are you sure you want to delete this sale? This will restore the product stock.', () => {
            window.location.href = url;
        }, 'Delete Sale?');
        return false;
    }

    function confirmBulkDelete() {
        const count = document.querySelectorAll('.sale-checkbox:checked').length;
        showConfirm(`Are you sure you want to delete ${count} selected sales? This will restore stock for all items.`, () => {
            document.getElementById('bulkDeleteForm').submit();
        }, 'Bulk Delete?');
    }
</script>

<!-- Advanced Filters & Analytics Dashboard -->
<div class="space-y-6 mb-8">
    <!-- Analysis Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-teal-500 glass animate-in fade-in slide-in-from-top-4 duration-300">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Total Revenue</h3>
            <p class="text-2xl font-black text-gray-800"><?= formatCurrency($stats['revenue']) ?></p>
            <div class="mt-2 text-[10px] text-teal-600 font-bold bg-teal-50 px-2 py-1 rounded-lg inline-block">Total Sales Value</div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-amber-500 glass animate-in fade-in slide-in-from-top-4 duration-500">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Net Profit</h3>
            <p class="text-2xl font-black text-amber-600"><?= formatCurrency($stats['profit']) ?></p>
            <div class="mt-2 text-[10px] text-amber-600 font-bold bg-amber-50 px-2 py-1 rounded-lg inline-block">Revenue - Cost</div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-green-500 glass animate-in fade-in slide-in-from-top-4 duration-700">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Amount Recovered</h3>
            <p class="text-2xl font-black text-green-600"><?= formatCurrency($stats['recovered']) ?></p>
            <div class="mt-2 text-[10px] text-green-600 font-bold bg-green-50 px-2 py-1 rounded-lg inline-block">Total Cash Received</div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-red-500 glass animate-in fade-in slide-in-from-top-4 duration-1000">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Payment Debt</h3>
            <p class="text-2xl font-black text-red-600"><?= formatCurrency($stats['debt']) ?></p>
            <div class="mt-2 text-[10px] text-red-600 font-bold bg-red-50 px-2 py-1 rounded-lg inline-block">Pending Balance</div>
        </div>
    </div>

    <!-- Chart & Advanced Filter -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Chart Container -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 shadow-sm border border-gray-100 glass">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="font-bold text-gray-800 text-lg">Performance Chart</h3>
                    <p class="text-xs text-gray-400">Revenue vs Profit Analysis</p>
                </div>
                <div class="flex gap-4 text-[10px] font-bold uppercase tracking-widest">
                    <span class="flex items-center"><span class="w-3 h-3 bg-teal-500 rounded-full mr-1.5"></span> Revenue</span>
                    <span class="flex items-center"><span class="w-3 h-3 bg-amber-400 rounded-full mr-1.5"></span> Profit</span>
                </div>
            </div>
            <div class="h-64 relative">
                <canvas id="analyticsChart"></canvas>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 glass flex flex-col justify-between">
            <form method="GET" id="filterForm">
                <h3 class="font-bold text-gray-800 text-lg mb-4 flex items-center">
                    <i class="fas fa-sliders-h mr-2 text-teal-600"></i> Smart Filters
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">Filter Type</label>
                        <select name="f_type" id="filterType" onchange="toggleFilterInputs()" class="w-full bg-gray-50 border-gray-100 rounded-xl text-sm font-semibold text-gray-700 focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 outline-none transition-all cursor-pointer">
                            <option value="month" <?= $f_type == 'month' ? 'selected' : '' ?>>Monthly Analysis</option>
                            <option value="year" <?= $f_type == 'year' ? 'selected' : '' ?>>Yearly Report</option>
                            <option value="range" <?= $f_type == 'range' ? 'selected' : '' ?>>Custom Date Range</option>
                        </select>
                    </div>

                    <div id="monthInput" class="<?= $f_type != 'month' ? 'hidden' : '' ?>">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">Select Month</label>
                        <input type="month" name="month" value="<?= $_GET['month'] ?? date('Y-m') ?>" class="w-full bg-gray-50 border-gray-100 rounded-xl text-sm font-semibold text-gray-700">
                    </div>

                    <div id="yearInput" class="<?= $f_type != 'year' ? 'hidden' : '' ?>">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">Select Year</label>
                        <select name="year" class="w-full bg-gray-50 border-gray-100 rounded-xl text-sm font-semibold text-gray-700">
                            <?php for($y=date('Y'); $y>=2024; $y--): ?>
                                <option value="<?= $y ?>" <?= ($_GET['year'] ?? date('Y')) == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div id="rangeInput" class="<?= $f_type != 'range' ? 'hidden' : '' ?> grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">From</label>
                            <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>" class="w-full bg-gray-50 border-gray-100 rounded-xl text-[11px] font-semibold text-gray-700">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">To</label>
                            <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>" class="w-full bg-gray-50 border-gray-100 rounded-xl text-[11px] font-semibold text-gray-700">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-6">
                    <a href="sales_history.php" class="bg-gray-100 text-gray-500 font-bold py-3 rounded-2xl text-xs text-center hover:bg-gray-200 transition active:scale-95">Reset</a>
                    <button type="submit" class="bg-teal-600 text-white font-bold py-3 rounded-2xl text-xs hover:bg-teal-700 transition shadow-lg shadow-teal-900/10 active:scale-95">Apply Filter</button>
                </div>
            </form>
            
            <div class="mt-6 flex flex-col gap-2">
                <button onclick="downloadPDF()" class="w-full bg-teal-50 text-teal-700 font-bold py-3 rounded-2xl text-xs flex items-center justify-center hover:bg-teal-100 transition border border-teal-100">
                    <i class="fas fa-file-pdf mr-2"></i> Save Report as PDF
                </button>
                <a href="print_sales.php?<?= http_build_query($_GET) ?>" target="_blank" class="w-full bg-gray-800 text-white font-bold py-3 rounded-2xl text-xs flex items-center justify-center hover:bg-black transition border border-gray-700">
                    <i class="fas fa-print mr-2"></i> Printer Friendly View
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleFilterInputs() {
        const type = document.getElementById('filterType').value;
        const monthInput = document.getElementById('monthInput');
        const yearInput = document.getElementById('yearInput');
        const rangeInput = document.getElementById('rangeInput');

        monthInput.classList.add('hidden');
        yearInput.classList.add('hidden');
        rangeInput.classList.add('hidden');

        if (type === 'month') monthInput.classList.remove('hidden');
        else if (type === 'year') yearInput.classList.remove('hidden');
        else if (type === 'range') rangeInput.classList.remove('hidden');
    }

    // Chart.js initialization
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('analyticsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($stats['labels']) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($stats['revenue_data']) ?>,
                    borderColor: '#0d9488', // teal-600
                    backgroundColor: 'rgba(13, 148, 136, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#0d9488',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 6
                }, {
                    label: 'Profit',
                    data: <?= json_encode($stats['profit_data']) ?>,
                    borderColor: '#f59e0b', // amber-500
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { display: false },
                        ticks: {
                            callback: function(value) { return 'Rs ' + value.toLocaleString(); },
                            font: { size: 9, weight: '600' }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9, weight: '600' } }
                    }
                }
            }
        });
    });
</script>

<div class="flex items-center justify-between mb-4">
    <h3 class="font-bold text-gray-800 text-lg flex items-center">
        <i class="fas fa-list-ul mr-2 text-teal-500"></i> Detail Transaction Log
    </h3>
    <button id="bulkDeleteBtn" onclick="confirmBulkDelete()" class="hidden bg-red-100 text-red-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-red-600 hover:text-white transition flex items-center border border-red-200 shadow-sm animate-in fade-in zoom-in duration-300">
        <i class="fas fa-trash-alt mr-2"></i> Restore Stock & Delete Selected
    </button>
</div>

<form id="bulkDeleteForm" action="../actions/delete_sale.php" method="POST">
<div id="salesTableContainer" class="bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-teal-700 text-white text-sm uppercase tracking-wider">
                    <th class="p-3 pl-6 w-10">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded border-teal-500 text-accent focus:ring-accent">
                    </th>
                    <th class="p-3">Sr #</th>
                    <th class="p-4">Date & Time</th>
                    <th class="p-4">Customer</th>
                    <th class="p-4 text-right">Total Amount</th>
                    <th class="p-4 text-right">Paid</th>
                    <th class="p-4">Payment Method</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (count($sales) > 0): $sn = 1; ?>
                    <?php foreach ($sales as $s): ?>
                        <tr class="hover:bg-teal-50 transition border-b border-gray-50 last:border-0 text-sm">
                            <td class="p-4 pl-6 text-center">
                                <input type="checkbox" name="id[]" value="<?= $s['id'] ?>" onchange="toggleBulkBtn()" class="sale-checkbox rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                            </td>
                            <td class="p-4 font-mono text-gray-500"><?= $sn++ ?></td>
                            <td class="p-4 text-gray-700 font-medium"><?= date('d M Y, h:i A', strtotime($s['sale_date'])) ?></td>
                            <td class="p-4">
                                <div class="font-bold text-gray-800">
                                    <?= isset($c_map[$s['customer_id']]) ? htmlspecialchars($c_map[$s['customer_id']]) : '<span class="text-gray-400 font-normal italic">Walk-in Customer</span>' ?>
                                </div>
                            </td>
                            <td class="p-4 font-bold text-gray-900 text-right"><?= formatCurrency((float)$s['total_amount']) ?></td>
                            <?php $adj_paid = $sale_paid_adj[$s['id']] ?? (float)$s['paid_amount']; ?>
                            <td class="p-4 text-green-600 font-semibold text-right"><?= formatCurrency($adj_paid) ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-xs font-medium border border-gray-200 uppercase">
                                    <?= $s['payment_method'] ?>
                                </span>
                            </td>
                             <td class="p-4 text-center">
                                <?php 
                                $total_amt = (float)$s['total_amount'];
                                $paid_amt = $sale_paid_adj[$s['id']] ?? (float)$s['paid_amount'];
                                if($total_amt > $paid_amt): ?>
                                    <?php if(!empty($s['customer_id'])): ?>
                                        <a href="customer_ledger.php?id=<?= $s['customer_id'] ?>" class="bg-red-100 text-red-700 text-[10px] uppercase font-bold px-2 py-1 rounded-full border border-red-200 hover:bg-red-200 transition">
                                            <i class="fas fa-exclamation-circle mr-1"></i> Due
                                        </a>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-700 text-[10px] uppercase font-bold px-2 py-1 rounded-full border border-red-200">
                                            <i class="fas fa-exclamation-circle mr-1"></i> Due
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if(!empty($s['customer_id'])): ?>
                                        <a href="customer_ledger.php?id=<?= $s['customer_id'] ?>" class="bg-green-100 text-green-700 text-[10px] uppercase font-bold px-2 py-1 rounded-full border border-green-200 hover:bg-green-200 transition">
                                            <i class="fas fa-check mr-1"></i> Paid
                                        </a>
                                    <?php else: ?>
                                        <span class="bg-green-100 text-green-700 text-[10px] uppercase font-bold px-2 py-1 rounded-full border border-green-200">
                                            <i class="fas fa-check mr-1"></i> Paid
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <button type="button" onclick="confirmDelete('../actions/delete_sale.php?id=<?= $s['id'] ?>')" 
                                   class="text-red-400 hover:text-red-600 transition p-2" title="Delete Sale">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="p-12 text-center text-gray-400">
                            <i class="fas fa-receipt text-4xl mb-3 text-gray-200"></i><br>
                            No sales records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</form>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
