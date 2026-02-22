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
$units = readCSV('units');

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
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-8">
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
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-purple-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Stock items</h3>
        <p id="statTotalStock" class="text-3xl font-black text-purple-600 tracking-tighter mt-1">0</p>
        <div class="mt-4 text-[9px] text-gray-400 font-bold uppercase tracking-wider">Total Units in Hand</div>
    </div>
</div>

<!-- Main Inventory Table -->
<div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden glass mb-8">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] border-b border-gray-100">
                <tr>
                    <th class="p-6">Product Details</th>
                    <th class="p-6 text-center">Date</th>
                    <th class="p-6 text-center">Buy Price</th>
                    <th class="p-6 text-center">Start Stock</th>
                    <th class="p-6 text-center text-blue-600">IN (+)</th>
                    <th class="p-6 text-center text-orange-600">OUT (-)</th>
                    <th class="p-6 text-center font-black text-teal-600">Final Stock</th>
                    <th class="p-6 text-center">Expiry</th>
                    <th class="p-6 text-center">Actions</th>
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
const availableUnits = <?= json_encode($units) ?>;

function getUnitHierarchyJS(unitName) {
    if (!unitName) return [];
    let startNode = availableUnits.find(u => u.name.toLowerCase() === unitName.toLowerCase());
    if (!startNode) return [];
    let root = startNode;
    while(root.parent_id != 0) {
        let parent = availableUnits.find(u => u.id == root.parent_id);
        if(!parent) break;
        root = parent;
    }
    let chain = [];
    let current = root;
    while(current) {
        chain.push(current);
        let next = availableUnits.find(u => parseInt(u.parent_id) === parseInt(current.id));
        if(!next) break;
        current = next;
    }
    return chain;
}

function getBaseMultiplierForProductJS(unitName, p) {
    const chain = getUnitHierarchyJS(p.unit);
    let targetIdx = chain.findIndex(u => u.name.toLowerCase() === unitName.toLowerCase());
    if (targetIdx === -1) return 1;
    const f2 = parseFloat(p.factor_level2 || 1) || 1;
    const f3 = parseFloat(p.factor_level3 || 1) || 1;
    if (targetIdx === 0) {
        if (chain.length > 2) return f2 * f3;
        if (chain.length > 1) return f2;
    } else if (targetIdx === 1) {
        if (chain.length > 2) return f3;
    }
    return 1;
}

function formatStockHierarchyJS(qty, p) {
    qty = parseFloat(qty);
    const unitName = p.unit || 'Units';
    if (qty <= 0) return `0 ${unitName}`;

    const chain = getUnitHierarchyJS(unitName);
    if (chain.length <= 1) return `<b>${qty.toFixed(0)}</b> <span class="text-[9px] uppercase opacity-70">${unitName}</span>`;

    let remaining = qty;
    let parts = [];
    let factors = [];
    
    chain.forEach((u, i) => {
        let mult = getBaseMultiplierForProductJS(u.name, p);
        
        // 1. Hierarchical breakdown
        let count = Math.floor(remaining / mult);
        if (count > 0) {
            parts.push(`<b>${count}</b> <span class="text-[9px] uppercase opacity-70">${u.name}</span>`);
            remaining = remaining % mult;
        }

        // 2. Build factors for clarity (requested by user)
        if (i === 0 && chain.length > 1) {
            const f2 = parseFloat(p.factor_level2 || 1) || 1;
            factors.push(`1 ${u.name} = ${f2} ${chain[1].name}`);
        }
        if (i === 1 && chain.length > 2) {
            const f3 = parseFloat(p.factor_level3 || 1) || 1;
            factors.push(`1 ${u.name} = ${f3} ${chain[2].name}`);
        }
    });

    let display = parts.length === 0 ? `0 ${unitName}` : parts.join(', ');
    
    // Absolute total in base unit
    const baseUnit = chain[chain.length - 1].name;
    display += ` <span class="text-[9px] text-teal-600 font-bold ml-1 tracking-tight italic">[Total: ${qty % 1 === 0 ? qty : qty.toFixed(2)} ${baseUnit}]</span>`;
    
    // Factor descriptions
    if (factors.length > 0) {
        display += ` <div class="text-[7px] text-gray-400 font-medium leading-none mt-0.5 opacity-80">Factors: ${factors.join(' | ')}</div>`;
    }
    
    return display;
}

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
    let totalCurrentStockTotal = 0;

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
        totalCurrentStockTotal += currentStock;

        // Expiry Badge
        let expiryBadge = '<span class="text-gray-400 italic text-[10px]">No Expiry</span>';
        if (isExpired) expiryBadge = '<span class="bg-red-100 text-red-600 px-2 py-1 rounded text-[10px] font-bold">EXPIRED</span>';
        else if (isNearExpiry) expiryBadge = '<span class="bg-orange-100 text-orange-600 px-2 py-1 rounded text-[10px] font-bold">NEAR EXPIRY</span>';
        else if (p.expiry_date) expiryBadge = `<span class="bg-teal-50 text-teal-600 px-2 py-1 rounded text-[10px] font-bold">${p.expiry_date}</span>`;

        // Logic for Latest Date and Price
        let latestDate = p.created_at ? p.created_at.substring(0, 10) : '-';
        let latestPrice = parseFloat(p.buy_price) || 0;
        
        // Find latest restock for this product
        const productRestocks = restocks.filter(r => r.product_id == p.id);
        if (productRestocks.length > 0) {
            // Sort by ID or date to get the latest. ID is more reliable for "last entered".
            const latestRestock = productRestocks.sort((a, b) => (parseInt(b.id) || 0) - (parseInt(a.id) || 0))[0];
            latestDate = latestRestock.date.substring(0, 10);
            latestPrice = parseFloat(latestRestock.new_buy_price) || latestPrice;
        }

        html += `
            <tr class="hover:bg-gray-50 transition border-b border-gray-50 last:border-0 group">
                <td class="p-6">
                    <div class="font-bold text-gray-800">${p.name}</div>
                    <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">${p.category} • ${p.unit}</div>
                </td>
                <td class="p-6 text-center font-mono text-[11px] text-gray-500">${latestDate}</td>
                <td class="p-6 text-center font-bold text-gray-700">Rs. ${latestPrice.toLocaleString()}</td>
                <td class="p-6 text-center font-semibold text-gray-500">${formatStockHierarchyJS(startStockAtPeriod, p)}</td>
                <td class="p-6 text-center font-bold text-blue-600">${stockInPeriod > 0 ? '+' + formatStockHierarchyJS(stockInPeriod, p) : '-'}</td>
                <td class="p-6 text-center font-bold text-orange-600">${stockOutPeriod > 0 ? '-' + formatStockHierarchyJS(stockOutPeriod, p) : '-'}</td>
                <td class="p-6 text-center font-black text-teal-700 bg-teal-50/30">${formatStockHierarchyJS(finalStockAtPeriod, p)}</td>
                <td class="p-6 text-center">
                    <div class="flex flex-col items-center gap-1">
                        ${expiryBadge}
                        ${p.expiry_date && !isExpired && !isNearExpiry ? `<div class="text-[9px] text-gray-400">${p.expiry_date}</div>` : ''}
                    </div>
                </td>
                <td class="p-6 text-center">
                    <button onclick="showRestockLogs('${p.id}')" class="px-3 py-1.5 bg-teal-50 text-teal-600 rounded-lg text-xs font-bold hover:bg-teal-600 hover:text-white transition shadow-sm border border-teal-100 flex items-center gap-2 mx-auto">
                        <i class="fas fa-history"></i> Logs
                    </button>
                </td>
            </tr>
        `;
    });

    document.getElementById('inventoryBody').innerHTML = html || '<tr><td colspan="8" class="p-12 text-center text-gray-400 font-medium italic">No products matched your filters.</td></tr>';
    
    // Update Stats
    document.getElementById('statIn').innerText = totalInUnits.toLocaleString();
    document.getElementById('statOut').innerText = totalOutUnits.toLocaleString();
    document.getElementById('statValue').innerText = 'Rs. ' + totalStockValueAmount.toLocaleString();
    document.getElementById('statExpiry').innerText = totalExpiryAlerts;
    document.getElementById('statTotalStock').innerText = totalCurrentStockTotal.toLocaleString();
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
    document.getElementById('invDateFrom').value = "<?= date('Y-m-01') ?>";
    document.getElementById('invDateTo').value = "<?= date('Y-m-d') ?>";
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

<!-- Restock Logs Modal -->
<div id="restockLogModal" class="fixed inset-0 bg-black/60 backdrop-blur-md hidden z-[100] items-center justify-center p-4 no-print">
    <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden transform transition-all animate-in fade-in zoom-in duration-300">
        <!-- Sticky Header -->
        <div class="sticky top-0 bg-white p-8 border-b border-gray-100 flex items-center justify-between z-10">
            <div>
                <h3 class="text-2xl font-black text-gray-800 tracking-tight" id="logModalTitle">Restock History</h3>
                <div class="flex items-center mt-1">
                    <span class="h-2 w-2 rounded-full bg-teal-500 mr-2"></span>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-[0.2em]" id="logModalSubtitle">Transaction Records</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="printRestockLog()" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition-all shadow-sm border border-blue-100">
                    <i class="fas fa-print"></i>
                </button>
                <button onclick="closeRestockModal()" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all shadow-sm border border-gray-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="overflow-y-auto p-8" id="logModalBody">
            <div id="logTableContainer" class="rounded-3xl border border-gray-100 overflow-hidden shadow-sm bg-white">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-[10px] uppercase font-black tracking-widest text-gray-400 border-b border-gray-100">
                            <th class="p-6">Date</th>
                            <th class="p-6">Type</th>
                            <th class="p-6 text-center">Quantity</th>
                            <th class="p-6">Buy Price</th>
                            <th class="p-6 text-right">Dealer / Supplier</th>
                        </tr>
                    </thead>
                    <tbody id="logTableBody" class="divide-y divide-gray-50">
                        <!-- JS Rendered -->
                    </tbody>
                </table>
            </div>
            
            <div id="logEmptyState" class="hidden py-20 text-center">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
                    <i class="fas fa-folder-open text-3xl text-gray-200"></i>
                </div>
                <p class="text-gray-400 font-bold uppercase text-xs tracking-widest">No restock records found</p>
            </div>

            <div class="mt-8 flex justify-center" id="showAllContainer">
                <button onclick="renderRestockModalTable(currentModalProductId, 9999)" id="showAllLogsBtn" class="px-8 py-3 bg-white border border-gray-200 text-gray-500 rounded-2xl text-xs font-black uppercase tracking-widest hover:border-teal-500 hover:text-teal-600 transition shadow-sm active:scale-95">
                    View Full History
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Only Container -->
<div id="printRestockContainer" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0f766e; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #0f766e; margin: 0; font-size: 32px; font-weight: 900;"><?= getSetting('business_name', 'Fashion Shines') ?></h1>
                <p style="color: #666; margin: 5px 0 0 0; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; font-size: 12px;">Product Restock History Report</p>
            </div>
            <div style="text-align: right;">
                <h2 id="printProductName" style="margin: 0; color: #333; font-size: 20px;">Product Name</h2>
                <p id="printGeneratedOn" style="color: #888; margin: 5px 0 0 0; font-size: 11px;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background: #0f766e; color: #fff;">
                    <th style="padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; font-weight: 900;">Date</th>
                    <th style="padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; font-weight: 900;">Type</th>
                    <th style="padding: 15px; text-align: center; font-size: 11px; text-transform: uppercase; font-weight: 900;">Qty Added</th>
                    <th style="padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; font-weight: 900;">Purchase Price</th>
                    <th style="padding: 15px; text-align: right; font-size: 11px; text-transform: uppercase; font-weight: 900;">Dealer</th>
                </tr>
            </thead>
            <tbody id="printRestockBody"></tbody>
        </table>

        <div style="border-top: 1px solid #eee; margin-top: 40px; padding-top: 20px; text-align: center; font-size: 10px; color: #aaa;">
            <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>

<script>
    let currentModalProductId = null;

    function showRestockLogs(productId) {
        currentModalProductId = productId;
        const p = products.find(x => x.id == productId);
        if (!p) return;

        document.getElementById('logModalTitle').innerText = p.name;
        document.getElementById('logModalSubtitle').innerText = 'Restock History for ID: #' + productId;
        
        renderRestockModalTable(productId, 10);
        
        document.getElementById('restockLogModal').classList.remove('hidden');
        document.getElementById('restockLogModal').classList.add('flex');
    }

    function renderRestockModalTable(productId, limit) {
        const p = products.find(x => x.id == productId);
        if (!p) return;

        const productRestocks = restocks.filter(r => 
            r.product_id == productId || (r.product_name && r.product_name === p.name)
        ).sort((a, b) => (parseInt(b.id) || 0) - (parseInt(a.id) || 0));

        const body = document.getElementById('logTableBody');
        const showAllContainer = document.getElementById('showAllContainer');
        const emptyState = document.getElementById('logEmptyState');
        const tableContainer = document.getElementById('logTableContainer');

        if (productRestocks.length === 0) {
            tableContainer.classList.add('hidden');
            emptyState.classList.remove('hidden');
            showAllContainer.classList.add('hidden');
            return;
        }

        tableContainer.classList.remove('hidden');
        emptyState.classList.add('hidden');

        const displayItems = productRestocks.slice(0, limit);
        let html = '';
        displayItems.forEach(r => {
            const dateStr = r.date ? new Date(r.date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : '-';
            const isInitial = (r.remarks || '').toLowerCase().includes('initial');
            const typeLabel = isInitial ? 
                '<span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded text-[9px] font-black uppercase tracking-tighter border border-amber-100">Initial</span>' : 
                '<span class="px-2 py-0.5 bg-teal-50 text-teal-600 rounded text-[9px] font-black uppercase tracking-tighter border border-teal-100">Restock</span>';

            const dealerHtml = r.dealer_id && r.dealer_id !== 'OPEN_MARKET' ? 
                `<a href="dealer_ledger.php?id=${r.dealer_id}" class="text-blue-600 hover:text-blue-800 transition underline-offset-2 hover:underline">${r.dealer_name}</a>` : 
                (r.dealer_name || 'Self Stock');

            html += `
                <tr class="hover:bg-gray-50/50 transition">
                    <td class="p-6 text-sm font-bold text-gray-500 font-mono">${dateStr}</td>
                    <td class="p-6">${typeLabel}</td>
                    <td class="p-6 text-center">
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full font-black text-xs shadow-sm">+${r.quantity}</span>
                    </td>
                    <td class="p-6 text-sm font-black text-gray-800">Rs. ${parseFloat(r.new_buy_price).toLocaleString()}</td>
                    <td class="p-6 text-right font-bold text-gray-400 text-xs italic">${dealerHtml}</td>
                </tr>
            `;
        });

        body.innerHTML = html;
        showAllContainer.classList.toggle('hidden', productRestocks.length <= limit);
    }

    function closeRestockModal() {
        document.getElementById('restockLogModal').classList.add('hidden');
        document.getElementById('restockLogModal').classList.remove('flex');
    }

    function printRestockLog() {
        const productId = currentModalProductId;
        const p = products.find(x => x.id == productId);
        if (!p) return;

        const productRestocks = restocks.filter(r => 
            r.product_id == productId || (r.product_name && r.product_name === p.name)
        ).sort((a, b) => (parseInt(b.id) || 0) - (parseInt(a.id) || 0));

        const printBody = document.getElementById('printRestockBody');
        document.getElementById('printProductName').innerText = p.name;
        
        let html = '';
        productRestocks.forEach(r => {
            const dateStr = r.date ? new Date(r.date).toLocaleDateString('en-GB') : '-';
            const isInitial = (r.remarks || '').toLowerCase().includes('initial');
            const typeText = isInitial ? 'INITIAL' : 'RESTOCK';

            html += `
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px 15px; font-size: 11px; font-family: monospace;">${dateStr}</td>
                    <td style="padding: 12px 15px; font-size: 9px; font-weight: 900; color: ${isInitial ? '#92400e' : '#0f766e'};">${typeText}</td>
                    <td style="padding: 12px 15px; font-size: 11px; text-align: center; font-weight: bold; color: #0f766e;">+${r.quantity}</td>
                    <td style="padding: 12px 15px; font-size: 11px; font-weight: bold;">Rs. ${parseFloat(r.new_buy_price).toLocaleString()}</td>
                    <td style="padding: 12px 15px; font-size: 10px; text-align: right; color: #666; font-style: italic;">${r.dealer_name || 'Self'}</td>
                </tr>
            `;
        });
        printBody.innerHTML = html;

        const content = document.getElementById('printRestockContainer').innerHTML;
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Restock Log - ' + p.name + '</title></head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    }
</script>
<?php include '../includes/footer.php'; ?>
