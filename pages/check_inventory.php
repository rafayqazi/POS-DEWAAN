<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Check Inventory Movements";
include '../includes/header.php';

// Prepare Data
$products = readCSV('products');
$categories = readCSV('categories');
$restocks = readCSV('restocks');
$sales = readCSV('sales');
$sale_items = readCSV('sale_items');

// Map sales to dates for easier lookup
$sales_date_map = [];
foreach ($sales as $s) {
    if (isset($s['id'])) {
        $sales_date_map[$s['id']] = substr($s['sale_date'], 0, 10);
    }
}

// Map products for easy access
$product_map = [];
foreach ($products as $p) {
    $product_map[$p['id']] = $p;
}

// Stats variables
$total_in = 0;
$total_out = 0;
$near_expiry_count = 0;
$total_stock_value = 0;

$today = date('Y-m-d');
$next_30_days = date('Y-m-d', strtotime('+30 days'));

// Current date for default filters
$default_from = date('Y-m-01');
$default_to = date('Y-m-d');
?>

<div class="mb-6 bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col md:flex-row justify-between items-end gap-4 glass no-print">
    <div class="flex flex-wrap items-end gap-3 flex-1">
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Quick Range</label>
            <select id="invQuickRange" onchange="setQuickRange()" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none w-40 shadow-sm">
                <option value="custom">Custom Range</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="week">Last 7 Days</option>
                <option value="month" selected>This Month</option>
                <option value="last_month">Last Month</option>
                <option value="year">This Year</option>
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Date From</label>
            <input type="date" id="invDateFrom" value="<?= $default_from ?>" onchange="document.getElementById('invQuickRange').value = 'custom'; renderInventory()" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none w-40 shadow-sm">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Date To</label>
            <input type="date" id="invDateTo" value="<?= $default_to ?>" onchange="document.getElementById('invQuickRange').value = 'custom'; renderInventory()" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none w-40 shadow-sm">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Category</label>
            <select id="invCategory" onchange="renderInventory()" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none w-40 shadow-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Expiry Status</label>
            <select id="invExpiry" onchange="renderInventory()" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none w-40 shadow-sm">
                <option value="all">All Items</option>
                <option value="near">Near Expiry (30 Days)</option>
                <option value="expired">Already Expired</option>
            </select>
        </div>
        <div class="flex flex-col flex-1 min-w-[200px]">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Search Product</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" id="invSearch" oninput="renderInventory()" placeholder="Search by name..." class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none shadow-sm">
            </div>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">&nbsp;</label>
            <button onclick="resetFilters()" class="p-3 bg-gray-100 text-gray-500 rounded-xl text-xs font-bold hover:bg-gray-200 transition shadow-sm h-[44px]">
                RESET
            </button>
        </div>
    </div>
    
    <div class="flex gap-2">
        <button onclick="printInventoryReport()" class="bg-gray-800 text-white px-6 py-3 rounded-xl hover:bg-black shadow-lg font-bold text-xs h-[46px] flex items-center transition active:scale-95">
            <i class="fas fa-print mr-2"></i> Print / Save PDF
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-blue-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Stock IN (ITEMS)</h3>
        <p id="statIn" class="text-3xl font-black text-blue-600 tracking-tighter mt-1">0</p>
        <div class="mt-4 text-[9px] text-gray-400 font-bold uppercase tracking-wider">Total Units Added</div>
    </div>
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-orange-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Stock OUT (ITEMS)</h3>
        <p id="statOut" class="text-3xl font-black text-orange-600 tracking-tighter mt-1">0</p>
        <div class="mt-4 text-[9px] text-gray-400 font-bold uppercase tracking-wider">Total Units Sold</div>
    </div>
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-teal-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Inventory Value</h3>
        <p id="statValue" class="text-3xl font-black text-teal-600 tracking-tighter mt-1">Rs. 0</p>
        <div class="mt-4 text-[9px] text-gray-400 font-bold uppercase tracking-wider">Based on Current Stock</div>
    </div>
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-red-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Expiry Alerts</h3>
        <p id="statExpiry" class="text-3xl font-black text-red-600 tracking-tighter mt-1">0</p>
        <div class="mt-4 text-[9px] text-gray-400 font-bold uppercase tracking-wider">Items Near or Expired</div>
    </div>
</div>

<!-- Main Inventory Table -->
<div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden glass mb-8">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] border-b border-gray-100">
                <tr>
                    <th class="p-6">Product Details</th>
                    <th class="p-6 text-center">Start Stock</th>
                    <th class="p-6 text-center text-blue-600">IN (+)</th>
                    <th class="p-6 text-center text-orange-600">OUT (-)</th>
                    <th class="p-6 text-center font-black text-teal-600">Final Stock</th>
                    <th class="p-6 text-center">Expiry</th>
                </tr>
            </thead>
            <tbody id="inventoryBody" class="divide-y divide-gray-50 text-sm">
                <!-- JS Populated -->
            </tbody>
        </table>
    </div>
</div>

<script>
const products = <?= json_encode($products) ?>;
const restocks = <?= json_encode($restocks) ?>;
const sales = <?= json_encode($sales_date_map) ?>;
const saleItems = <?= json_encode($sale_items) ?>;

function renderInventory() {
    const from = document.getElementById('invDateFrom').value;
    const to = document.getElementById('invDateTo').value;
    const cat = document.getElementById('invCategory').value;
    const search = document.getElementById('invSearch').value.toLowerCase();
    const expiryFilter = document.getElementById('invExpiry').value;

    let html = '';
    let totalInUnits = 0;
    let totalOutUnits = 0;
    let totalStockValueAmount = 0;
    let totalExpiryAlerts = 0;

    const today = new Date();
    today.setHours(0,0,0,0);
    const next30 = new Date();
    next30.setDate(today.getDate() + 30);

    products.forEach(p => {
        // Basic Filters
        if (cat && p.category !== cat) return;
        if (search && !p.name.toLowerCase().includes(search)) return;

        // Expiry Status Calculation
        let isNearExpiry = false;
        let isExpired = false;
        if (p.expiry_date) {
            const exp = new Date(p.expiry_date);
            if (exp < today) isExpired = true;
            else if (exp <= next30) isNearExpiry = true;
        }

        if (expiryFilter === 'near' && !isNearExpiry) return;
        if (expiryFilter === 'expired' && !isExpired) return;
        if (isExpired || isNearExpiry) totalExpiryAlerts++;

        // Stock Calculation
        // Step 1: Get current stock as baseline
        const currentStock = parseFloat(p.stock_quantity) || 0;

        // Step 2: Calculate Restocks
        let stockInPeriod = 0;
        let restocksAfterEnd = 0;
        restocks.forEach(r => {
            if (r.product_id != p.id) return;
            const rDate = r.date.substring(0, 10);
            const qty = parseFloat(r.quantity) || 0;

            if (from && to && rDate >= from && rDate <= to) {
                stockInPeriod += qty;
            }
            if (to && rDate > to) {
                restocksAfterEnd += qty;
            }
        });

        // Step 3: Calculate Sales
        let stockOutPeriod = 0;
        let salesAfterEnd = 0;
        saleItems.forEach(si => {
            if (si.product_id != p.id) return;
            const sDate = sales[si.sale_id];
            if (!sDate) return;
            const qty = parseFloat(si.quantity) || 0;

            if (from && to && sDate >= from && sDate <= to) {
                stockOutPeriod += qty;
            }
            if (to && sDate > to) {
                salesAfterEnd += qty;
            }
        });

        // Step 4: Backtrack Stock
        // Final Stock at the end of period: Current - (Restocks after period) + (Sales after period)
        const finalStockAtPeriod = currentStock - restocksAfterEnd + salesAfterEnd;
        // Start Stock at begin of period: Final - (Restocks within period) + (Sales within period)
        const startStockAtPeriod = finalStockAtPeriod - stockInPeriod + stockOutPeriod;

        totalInUnits += stockInPeriod;
        totalOutUnits += stockOutPeriod;
        totalStockValueAmount += currentStock * (parseFloat(p.buy_price) || 0);

        // Expiry Badge
        let expiryBadge = '<span class="text-gray-400 italic text-[10px]">No Expiry</span>';
        if (isExpired) expiryBadge = '<span class="bg-red-100 text-red-600 px-2 py-1 rounded text-[10px] font-bold">EXPIRED</span>';
        else if (isNearExpiry) expiryBadge = '<span class="bg-orange-100 text-orange-600 px-2 py-1 rounded text-[10px] font-bold">NEAR EXPIRY</span>';
        else if (p.expiry_date) expiryBadge = `<span class="bg-teal-50 text-teal-600 px-2 py-1 rounded text-[10px] font-bold">${p.expiry_date}</span>`;

        html += `
            <tr class="hover:bg-gray-50 transition border-b border-gray-50 last:border-0 group">
                <td class="p-6">
                    <div class="font-bold text-gray-800">${p.name}</div>
                    <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">${p.category} • ${p.unit}</div>
                </td>
                <td class="p-6 text-center font-semibold text-gray-500">${startStockAtPeriod.toLocaleString()}</td>
                <td class="p-6 text-center font-bold text-blue-600">${stockInPeriod > 0 ? '+' + stockInPeriod.toLocaleString() : '-'}</td>
                <td class="p-6 text-center font-bold text-orange-600">${stockOutPeriod > 0 ? '-' + stockOutPeriod.toLocaleString() : '-'}</td>
                <td class="p-6 text-center font-black text-teal-700 bg-teal-50/30">${finalStockAtPeriod.toLocaleString()}</td>
                <td class="p-6 text-center">
                    <div class="flex flex-col items-center gap-1">
                        ${expiryBadge}
                        ${p.expiry_date && !isExpired && !isNearExpiry ? `<div class="text-[9px] text-gray-400">${p.expiry_date}</div>` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    document.getElementById('inventoryBody').innerHTML = html || '<tr><td colspan="6" class="p-12 text-center text-gray-400 font-medium italic">No products matched your filters.</td></tr>';
    
    // Update Stats
    document.getElementById('statIn').innerText = totalInUnits.toLocaleString();
    document.getElementById('statOut').innerText = totalOutUnits.toLocaleString();
    document.getElementById('statValue').innerText = 'Rs. ' + totalStockValueAmount.toLocaleString();
    document.getElementById('statExpiry').innerText = totalExpiryAlerts;
}

function setQuickRange() {
    const range = document.getElementById('invQuickRange').value;
    const fromEl = document.getElementById('invDateFrom');
    const toEl = document.getElementById('invDateTo');
    
    let fromDate = new Date();
    let toDate = new Date();
    
    if (range === 'today') {
        // Today
    } else if (range === 'yesterday') {
        fromDate.setDate(fromDate.getDate() - 1);
        toDate.setDate(toDate.getDate() - 1);
    } else if (range === 'week') {
        fromDate.setDate(fromDate.getDate() - 7);
    } else if (range === 'month') {
        fromDate.setDate(1);
    } else if (range === 'last_month') {
        fromDate.setMonth(fromDate.getMonth() - 1);
        fromDate.setDate(1);
        toDate = new Date(fromDate.getFullYear(), fromDate.getMonth() + 1, 0);
    } else if (range === 'year') {
        fromDate.setMonth(0);
        fromDate.setDate(1);
    } else {
        return; // Custom logic handled by renderInventory
    }
    
    fromEl.value = fromDate.toISOString().split('T')[0];
    toEl.value = toDate.toISOString().split('T')[0];
    renderInventory();
}

function resetFilters() {
    document.getElementById('invQuickRange').value = 'month';
    document.getElementById('invDateFrom').value = "<?= $default_from ?>";
    document.getElementById('invDateTo').value = "<?= $default_to ?>";
    document.getElementById('invCategory').value = '';
    document.getElementById('invSearch').value = '';
    document.getElementById('invExpiry').value = 'all';
    renderInventory();
}

function printInventoryReport() {
    const from = document.getElementById('invDateFrom').value;
    const to = document.getElementById('invDateTo').value;
    const cat = document.getElementById('invCategory').value;
    const search = document.getElementById('invSearch').value;
    const expiry = document.getElementById('invExpiry').value;
    
    const url = `print_inventory_report.php?from=${from}&to=${to}&category=${encodeURIComponent(cat)}&search=${encodeURIComponent(search)}&expiry=${expiry}`;
    window.open(url, '_blank');
}

window.onload = renderInventory;
</script>

<style>
@media print {
    body { background: #fff !important; }
    .glass { box-shadow: none !important; border: 1px solid #eee !important; }
    .no-print { display: none !important; }
    nav, .sidebar, header { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
