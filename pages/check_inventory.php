<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Check Inventory Movements";
include '../includes/header.php';

// Prepare Data
$products = readCSV('products');
usort($products, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

$categories = readCSV('categories');
usort($categories, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

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
$default_from = date('Y-m-d', strtotime('-30 days'));
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
                <option value="30_days" selected>Last 30 Days</option>
                <option value="month">This Month</option>
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
var products = <?= json_encode($products) ?>;
var restocks = <?= json_encode($restocks) ?>;
var sales = <?= json_encode($sales_date_map) ?>;
<?php
$_customers_list = readCSV('customers');
$_cmap = [];
$_cFullMap = [];
foreach($_customers_list as $_c) {
    $_cmap[$_c['id']] = $_c['name'];
    $_cFullMap[$_c['id']] = $_c;
}
$_rawSales = readCSV('sales');
$_salesFull = [];
foreach($_rawSales as $_s) {
    $_cid = $_s['customer_id'] ?? '';
    $_s['customer_name'] = !empty($_cid) ? ($_cmap[$_cid] ?? 'Walk-In') : 'Walk-In';
    $_s['customer_phone'] = !empty($_cid) ? ($_cFullMap[$_cid]['phone'] ?? '') : '';
    $_s['customer_address'] = !empty($_cid) ? ($_cFullMap[$_cid]['address'] ?? '') : '';
    $_salesFull[$_s['id']] = $_s;
}

$_returns_list = readCSV('returns');
$_return_items_list = readCSV('return_items');
$_returns_map = [];
foreach ($_returns_list as $_r) {
    $_returns_map[$_r['id']] = $_r;
}
$_returns_by_sale_product = [];
foreach ($_return_items_list as $_ri) {
    $_rid = $_ri['return_id'] ?? '';
    $_r = $_returns_map[$_rid] ?? null;
    if ($_r) {
        $_cid = $_r['customer_id'] ?? '';
        $_cinfo = $_cFullMap[$_cid] ?? null;
        $_cname = $_cinfo['name'] ?? ($_cmap[$_cid] ?? 'Walk-In Customer');
        $_cphone = $_cinfo['phone'] ?? '';
        $_caddress = $_cinfo['address'] ?? '';

        $_itemReturn = [
            'return_id' => $_r['id'],
            'sale_id' => $_r['sale_id'] ?? '',
            'product_id' => $_ri['product_id'] ?? '',
            'quantity' => (float)($_ri['quantity'] ?? 0),
            'price_per_unit' => (float)($_ri['price_per_unit'] ?? 0),
            'total_price' => (float)($_ri['total_price'] ?? 0),
            'date' => $_r['date'] ?? '',
            'created_at' => $_r['created_at'] ?? '',
            'remarks' => $_r['remarks'] ?? '',
            'customer_id' => $_cid,
            'customer_name' => $_cname,
            'customer_phone' => $_cphone,
            'customer_address' => $_caddress
        ];

        $_key = ($_r['sale_id'] ?? '') . '_' . ($_ri['product_id'] ?? '');
        $_returns_by_sale_product[$_key][] = $_itemReturn;
    }
}
?>
var salesFull = <?= json_encode($_salesFull) ?>;
var saleItems = <?= json_encode($sale_items) ?>;
var returnsLookup = <?= json_encode($_returns_by_sale_product) ?>;
var availableUnits = <?= json_encode($units) ?>;

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

        // Step 3: Calculate Sales (Net of Returns)
        let stockOutPeriod = 0;
        let salesAfterEnd = 0;
        saleItems.forEach(si => {
            if (si.product_id != p.id) return;
            const sDate = sales[si.sale_id];
            if (!sDate) return;
            const qty = parseFloat(si.quantity) || 0;
            const retQty = parseFloat(si.returned_qty) || 0;
            const netQty = Math.max(0, qty - retQty);

            if (from && to && sDate >= from && sDate <= to) {
                stockOutPeriod += netQty;
            }
            if (to && sDate > to) {
                salesAfterEnd += netQty;
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
                    <div class="flex items-center justify-center gap-2">
                        <button onclick="showRestockLogs('${p.id}')" class="px-3 py-1.5 bg-teal-50 text-teal-600 rounded-lg text-xs font-bold hover:bg-teal-600 hover:text-white transition shadow-sm border border-teal-100 flex items-center gap-1.5 whitespace-nowrap">
                            <i class="fas fa-history"></i> Stock Logs
                        </button>
                        <button onclick="showIssuingHistory('${p.id}')" class="px-3 py-1.5 bg-orange-50 text-orange-600 rounded-lg text-xs font-bold hover:bg-orange-600 hover:text-white transition shadow-sm border border-orange-100 flex items-center gap-1.5 whitespace-nowrap">
                            <i class="fas fa-shopping-bag"></i> Sales History
                        </button>
                    </div>
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
    } else if (range === '30_days') {
        fromDate.setDate(fromDate.getDate() - 30);
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
    document.getElementById('invQuickRange').value = '30_days';
    document.getElementById('invDateFrom').value = "<?= date('Y-m-d', strtotime('-30 days')) ?>";
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

<!-- Modals rendered below, footer.php is included at end of file -->

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
                <!-- Summary Badges -->
                <div class="hidden md:flex gap-3" id="restockSummaryBadges">
                    <div class="px-4 py-2 bg-blue-50 border border-blue-100 rounded-2xl text-center">
                        <div class="text-[9px] font-black text-blue-400 uppercase tracking-widest">Total In</div>
                        <div class="text-lg font-black text-blue-600" id="restockTotalQty">0</div>
                    </div>
                    <div class="px-4 py-2 bg-teal-50 border border-teal-100 rounded-2xl text-center">
                        <div class="text-[9px] font-black text-teal-400 uppercase tracking-widest">Total Entries</div>
                        <div class="text-lg font-black text-teal-600" id="restockTotalCount">0</div>
                    </div>
                    <div class="px-4 py-2 bg-emerald-50 border border-emerald-100 rounded-2xl text-center">
                        <div class="text-[9px] font-black text-emerald-400 uppercase tracking-widest">Total Cost</div>
                        <div class="text-lg font-black text-emerald-600" id="restockTotalCost">Rs. 0</div>
                    </div>
                </div>
                <button onclick="printRestockLog()" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-teal-50 text-teal-600 hover:bg-teal-600 hover:text-white transition-all shadow-sm border border-teal-100" title="Print Restock Log">
                    <i class="fas fa-print"></i>
                </button>
                <button onclick="closeRestockModal()" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all shadow-sm border border-gray-100" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="overflow-y-auto p-8" id="logModalBody" style="max-height: calc(90vh - 120px);">
            <div id="logTableContainer" class="rounded-3xl border border-gray-100 overflow-hidden shadow-sm bg-white">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-[10px] uppercase font-black tracking-widest text-gray-400 border-b border-gray-100">
                            <th class="p-5">#</th>
                            <th class="p-5">Date</th>
                            <th class="p-5">Type</th>
                            <th class="p-5 text-center">Quantity</th>
                            <th class="p-5 text-right">Buy Price</th>
                            <th class="p-5 text-right">Dealer / Supplier</th>
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

        <div style="margin-top:40px; border-top:1px solid #eee; padding-top:15px; text-align:center; font-size:9px; color:#aaa;">
            <p style="margin:0; font-weight:bold; color:#888;">Software Developed by Abdul Rafay</p>
            <p style="margin:4px 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>

<!-- Print Only Container for Issuing -->
<div id="printIssuingContainer" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #f97316; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #f97316; margin: 0; font-size: 32px; font-weight: 900;"><?= getSetting('business_name', 'Fashion Shines') ?></h1>
                <p style="color: #666; margin: 5px 0 0 0; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; font-size: 12px;">Product Issuing / Sales History Report</p>
            </div>
            <div style="text-align: right;">
                <h2 id="printIssuingProductName" style="margin: 0; color: #333; font-size: 20px;">Product Name</h2>
                <p id="printIssuingGeneratedOn" style="color: #888; margin: 5px 0 0 0; font-size: 11px;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background: #f97316; color: #fff;">
                    <th style="padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; font-weight: 900;">#</th>
                    <th style="padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; font-weight: 900;">Date</th>
                    <th style="padding: 15px; text-align: left; font-size: 11px; text-transform: uppercase; font-weight: 900;">Customer</th>
                    <th style="padding: 15px; text-align: center; font-size: 11px; text-transform: uppercase; font-weight: 900;">Qty Sold</th>
                    <th style="padding: 15px; text-align: right; font-size: 11px; text-transform: uppercase; font-weight: 900;">Sell Price</th>
                    <th style="padding: 15px; text-align: right; font-size: 11px; text-transform: uppercase; font-weight: 900;">Total</th>
                </tr>
            </thead>
            <tbody id="printIssuingBody"></tbody>
        </table>

        <div style="margin-top:40px; border-top:1px solid #eee; padding-top:15px; text-align:center; font-size:9px; color:#aaa;">
            <p style="margin:0; font-weight:bold; color:#888;">Software Developed by Abdul Rafay</p>
            <p style="margin:4px 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>

<script>
    var currentModalProductId = null;

    function showRestockLogs(productId) {
        currentModalProductId = productId;
        const p = products.find(x => x.id == productId);
        if (!p) return;

        document.getElementById('logModalTitle').innerText = p.name;
        document.getElementById('logModalSubtitle').innerText = (p.category ? p.category + ' • ' : '') + (p.unit || 'Units') + ' (ID: #' + productId + ')';
        
        renderRestockModalTable(productId);
        
        document.getElementById('restockLogModal').classList.remove('hidden');
        document.getElementById('restockLogModal').classList.add('flex');
    }

    function renderRestockModalTable(productId) {
        const p = products.find(x => x.id == productId);
        if (!p) return;

        const productRestocks = restocks.filter(r => 
            r.product_id == productId || (r.product_name && r.product_name.trim().toLowerCase() === (p.name || '').trim().toLowerCase())
        ).sort((a, b) => (parseInt(b.id) || 0) - (parseInt(a.id) || 0));

        const body = document.getElementById('logTableBody');
        const emptyState = document.getElementById('logEmptyState');
        const tableContainer = document.getElementById('logTableContainer');

        if (productRestocks.length === 0) {
            tableContainer.classList.add('hidden');
            emptyState.classList.remove('hidden');
            document.getElementById('restockTotalQty').innerText = '0';
            document.getElementById('restockTotalCount').innerText = '0 Entries';
            document.getElementById('restockTotalCost').innerText = 'Rs. 0';
            return;
        }

        tableContainer.classList.remove('hidden');
        emptyState.classList.add('hidden');

        let totalQty = 0;
        let totalCost = 0;
        let html = '';
        let rowNum = 1;

        productRestocks.forEach(r => {
            const qty = parseFloat(r.quantity) || 0;
            const price = parseFloat(r.new_buy_price) || 0;
            const rowTotal = qty * price;
            totalQty += qty;
            totalCost += rowTotal;

            const dateStr = r.date ? new Date(r.date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : '-';
            const isInitial = (r.remarks || '').toLowerCase().includes('initial');
            const typeLabel = isInitial ? 
                '<span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded text-[9px] font-black uppercase tracking-tighter border border-amber-100">Initial</span>' : 
                '<span class="px-2 py-0.5 bg-teal-50 text-teal-600 rounded text-[9px] font-black uppercase tracking-tighter border border-teal-100">Restock</span>';

            const dealerHtml = r.dealer_id && r.dealer_id !== 'OPEN_MARKET' ? 
                `<a href="dealer_ledger.php?id=${r.dealer_id}" class="text-blue-600 hover:text-blue-800 transition underline-offset-2 hover:underline">${r.dealer_name}</a>` : 
                (r.dealer_name || 'Self Stock');

            const rowBg = rowNum % 2 === 0 ? 'background:#f9fafb;' : '';

            html += `
                <tr class="hover:bg-teal-50/40 transition" style="${rowBg}">
                    <td class="p-5 text-xs text-gray-400 font-mono">#${r.id || rowNum}</td>
                    <td class="p-5 text-sm font-bold text-gray-500 font-mono">${dateStr}</td>
                    <td class="p-5">${typeLabel}</td>
                    <td class="p-5 text-center">
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full font-black text-xs shadow-sm border border-blue-100">+${qty % 1 === 0 ? qty.toLocaleString() : qty.toFixed(2)} ${p.unit || ''}</span>
                    </td>
                    <td class="p-5 text-right text-sm font-black text-gray-800">Rs. ${price.toLocaleString()}</td>
                    <td class="p-5 text-right font-bold text-gray-400 text-xs italic">${dealerHtml}</td>
                </tr>
            `;
            rowNum++;
        });

        body.innerHTML = html;
        document.getElementById('restockTotalQty').innerText = (totalQty % 1 === 0 ? totalQty.toLocaleString() : totalQty.toFixed(2)) + ' ' + (p.unit || 'Units');
        document.getElementById('restockTotalCount').innerText = productRestocks.length + ' Entries';
        document.getElementById('restockTotalCost').innerText = 'Rs. ' + Math.round(totalCost).toLocaleString();
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
            r.product_id == productId || (r.product_name && r.product_name.trim().toLowerCase() === (p.name || '').trim().toLowerCase())
        ).sort((a, b) => (parseInt(b.id) || 0) - (parseInt(a.id) || 0));

        const printBody = document.getElementById('printRestockBody');
        document.getElementById('printProductName').innerText = p.name + ' (' + (p.category || 'Product') + ' • ' + (p.unit || 'Units') + ')';
        
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

    document.getElementById('restockLogModal').addEventListener('click', function(e) {
        if (e.target === this) closeRestockModal();
    });
</script>

<!-- Issuing / Sales History Modal -->
<div id="issuingHistoryModal" class="fixed inset-0 bg-black/60 backdrop-blur-md hidden z-[100] items-center justify-center p-4 no-print">
    <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <!-- Header -->
        <div class="sticky top-0 bg-white p-8 border-b border-gray-100 flex items-center justify-between z-10">
            <div>
                <h3 class="text-2xl font-black text-gray-800 tracking-tight" id="issuingModalTitle">Issuing History</h3>
                <div class="flex items-center mt-1">
                    <span class="h-2 w-2 rounded-full bg-orange-500 mr-2"></span>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-[0.2em]" id="issuingModalSubtitle">Customer Sales Records</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <!-- Summary Badges -->
                <div class="hidden md:flex gap-2" id="issuingSummaryBadges">
                    <div class="px-3 py-2 bg-orange-50 border border-orange-100 rounded-2xl text-center">
                        <div class="text-[8px] font-black text-orange-400 uppercase tracking-widest">Total Sold</div>
                        <div class="text-sm font-black text-orange-600" id="issuingTotalQty">0</div>
                    </div>
                    <div class="px-3 py-2 bg-red-50 border border-red-100 rounded-2xl text-center" id="issuingReturnedBadgeBox">
                        <div class="text-[8px] font-black text-red-400 uppercase tracking-widest">Returned</div>
                        <div class="text-sm font-black text-red-600" id="issuingTotalReturned">0</div>
                    </div>
                    <div class="px-3 py-2 bg-purple-50 border border-purple-100 rounded-2xl text-center">
                        <div class="text-[8px] font-black text-purple-400 uppercase tracking-widest">Net Out</div>
                        <div class="text-sm font-black text-purple-600" id="issuingNetQty">0</div>
                    </div>
                    <div class="px-3 py-2 bg-teal-50 border border-teal-100 rounded-2xl text-center">
                        <div class="text-[8px] font-black text-teal-400 uppercase tracking-widest">Net Revenue</div>
                        <div class="text-sm font-black text-teal-600" id="issuingTotalRev">Rs. 0</div>
                    </div>
                </div>
                <button onclick="printIssuingHistory()" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-orange-50 text-orange-600 hover:bg-orange-600 hover:text-white transition-all shadow-sm border border-orange-100">
                    <i class="fas fa-print"></i>
                </button>
                <button onclick="closeIssuingModal()" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-all shadow-sm border border-gray-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="overflow-y-auto p-8" style="max-height: calc(90vh - 120px);">
            <div id="issuingTableContainer" class="rounded-3xl border border-gray-100 overflow-hidden shadow-sm bg-white">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 text-[10px] uppercase font-black tracking-widest text-gray-400 border-b border-gray-100">
                            <th class="p-5">#</th>
                            <th class="p-5">Date</th>
                            <th class="p-5">Customer</th>
                            <th class="p-5 text-center">Qty Sold</th>
                            <th class="p-5 text-right">Sell Price</th>
                            <th class="p-5 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody id="issuingTableBody" class="divide-y divide-gray-50"></tbody>
                </table>
            </div>
            <div id="issuingEmptyState" class="hidden py-20 text-center">
                <div class="w-20 h-20 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-orange-100">
                    <i class="fas fa-shopping-bag text-3xl text-orange-200"></i>
                </div>
                <p class="text-gray-400 font-bold uppercase text-xs tracking-widest">No sales found for this product</p>
            </div>
        </div>
    </div>
</div>

<script>
    function showIssuingHistory(productId) {
        const p = products.find(x => x.id == productId);
        if (!p) return;

        document.getElementById('issuingModalTitle').innerText = p.name;
        document.getElementById('issuingModalSubtitle').innerText = p.category + ' • ' + p.unit;

        // Gather all sale_items for this product
        const items = saleItems.filter(si => si.product_id == productId);

        const tableBody = document.getElementById('issuingTableBody');
        const tableContainer = document.getElementById('issuingTableContainer');
        const emptyState = document.getElementById('issuingEmptyState');

        if (items.length === 0) {
            tableContainer.classList.add('hidden');
            emptyState.classList.remove('hidden');
            document.getElementById('issuingTotalQty').innerText = '0';
            document.getElementById('issuingTotalReturned').innerText = '0';
            document.getElementById('issuingNetQty').innerText = '0';
            document.getElementById('issuingTotalRev').innerText = 'Rs. 0';
        } else {
            tableContainer.classList.remove('hidden');
            emptyState.classList.add('hidden');

            // Sort by sale_id descending (newest first)
            items.sort((a, b) => (parseInt(b.sale_id) || 0) - (parseInt(a.sale_id) || 0));

            let totalQty = 0;
            let totalReturned = 0;
            let totalRev = 0;
            let rowNum = 1;
            let html = '';

            items.forEach(si => {
                const sale = salesFull[si.sale_id];
                const qty = parseFloat(si.quantity) || 0;
                const retQty = parseFloat(si.returned_qty) || 0;
                const netQty = Math.max(0, qty - retQty);
                const price = parseFloat(si.price_per_unit || 0);
                const total = qty * price;
                const netTotal = netQty * price;
                const customer = sale ? (sale.customer_name || sale.customer || 'Walk-In') : 'Walk-In';
                const dateStr = sale && sale.sale_date ? new Date(sale.sale_date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : '-';
                const saleId = si.sale_id;

                totalQty += qty;
                totalReturned += retQty;
                totalRev += netTotal;

                const rowBg = rowNum % 2 === 0 ? 'background:#f9fafb;' : '';

                let qtyDisplay = `<span class="px-3 py-1 bg-orange-50 text-orange-600 rounded-full font-black text-xs shadow-sm border border-orange-100">${qty % 1 === 0 ? qty : qty.toFixed(2)} ${p.unit}</span>`;
                if (retQty > 0) {
                    qtyDisplay += `
                        <div class="mt-1 flex items-center justify-center gap-1">
                            <button type="button" onclick="showReturnDetailsModal('${saleId}', '${si.product_id}'); event.stopPropagation();" 
                                class="px-2 py-0.5 bg-red-50 hover:bg-red-500 text-red-600 hover:text-white border border-red-200 hover:border-red-500 rounded text-[9px] font-black uppercase tracking-tight transition-all active:scale-95 flex items-center gap-1 shadow-sm cursor-pointer group" 
                                title="Click to view Customer Return Details">
                                <i class="fas fa-undo-alt text-[8px]"></i> -${retQty % 1 === 0 ? retQty : retQty.toFixed(2)} Returned
                                <i class="fas fa-external-link-alt text-[7px] opacity-70 group-hover:opacity-100"></i>
                            </button>
                            <span class="text-[10px] text-gray-500 font-bold">(Net: ${netQty % 1 === 0 ? netQty : netQty.toFixed(2)})</span>
                        </div>
                    `;
                }

                html += `
                    <tr class="hover:bg-orange-50/40 transition" style="${rowBg}">
                        <td class="p-5 text-xs text-gray-400 font-mono">#${saleId}</td>
                        <td class="p-5 text-sm font-bold text-gray-500 font-mono">${dateStr}</td>
                        <td class="p-5">
                            <div class="font-bold text-gray-800 text-sm">${customer}</div>
                            ${sale && sale.customer_phone ? `<div class="text-[10px] text-gray-400">${sale.customer_phone}</div>` : ''}
                        </td>
                        <td class="p-5 text-center">
                            ${qtyDisplay}
                        </td>
                        <td class="p-5 text-right font-bold text-gray-700 text-sm">Rs. ${price.toLocaleString()}</td>
                        <td class="p-5 text-right">
                            <div class="font-black ${retQty > 0 ? 'text-gray-400 line-through text-xs' : 'text-teal-700'}">Rs. ${total.toLocaleString()}</div>
                            ${retQty > 0 ? `<div class="font-black text-teal-700 text-sm">Rs. ${Math.round(netTotal).toLocaleString()}</div>` : ''}
                        </td>
                    </tr>
                `;
                rowNum++;
            });

            const netQtyTotal = totalQty - totalReturned;
            tableBody.innerHTML = html;
            document.getElementById('issuingTotalQty').innerText = (totalQty % 1 === 0 ? totalQty : totalQty.toFixed(2)) + ' ' + p.unit;
            document.getElementById('issuingTotalReturned').innerText = (totalReturned % 1 === 0 ? totalReturned : totalReturned.toFixed(2)) + ' ' + p.unit;
            document.getElementById('issuingNetQty').innerText = (netQtyTotal % 1 === 0 ? netQtyTotal : netQtyTotal.toFixed(2)) + ' ' + p.unit;
            document.getElementById('issuingTotalRev').innerText = 'Rs. ' + Math.round(totalRev).toLocaleString();
        }

        document.getElementById('issuingHistoryModal').classList.remove('hidden');
        document.getElementById('issuingHistoryModal').classList.add('flex');
    }

    function closeIssuingModal() {
        document.getElementById('issuingHistoryModal').classList.add('hidden');
        document.getElementById('issuingHistoryModal').classList.remove('flex');
    }

    function printIssuingHistory() {
        const modalTitle = document.getElementById('issuingModalTitle').innerText;
        const subtitle = document.getElementById('issuingModalSubtitle').innerText;
        const items = document.getElementById('issuingTableBody').innerHTML;

        document.getElementById('printIssuingProductName').innerText = modalTitle + " (" + subtitle + ")";
        document.getElementById('printIssuingBody').innerHTML = items;

        const printContent = document.getElementById('printIssuingContainer').innerHTML;
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Issuing History - ' + modalTitle + '</title>');
        printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContent);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    }

    document.getElementById('issuingHistoryModal').addEventListener('click', function(e) {
        if (e.target === this) closeIssuingModal();
    });

    function showReturnDetailsModal(saleId, productId) {
        const p = products.find(x => x.id == productId);
        const sale = salesFull[saleId];
        const key = saleId + '_' + productId;
        let records = (returnsLookup && returnsLookup[key]) ? [...returnsLookup[key]] : [];

        // Fallback search across all returns
        if (records.length === 0 && returnsLookup) {
            Object.values(returnsLookup).forEach(list => {
                list.forEach(r => {
                    if (r.sale_id == saleId && r.product_id == productId) {
                        records.push(r);
                    }
                });
            });
        }

        const modal = document.getElementById('returnDetailsModal');
        const modalBody = document.getElementById('returnDetailsModalBody');
        const modalFooter = document.getElementById('returnDetailsModalFooter');
        const productTitle = document.getElementById('retModalProductTitle');

        productTitle.innerText = (p ? p.name : 'Product') + ' • Sale #' + saleId;

        let custId = sale ? (sale.customer_id || '') : '';
        let custName = sale ? (sale.customer_name || 'Walk-In Customer') : 'Walk-In Customer';
        let custPhone = sale ? (sale.customer_phone || '') : '';
        let custAddress = sale ? (sale.customer_address || '') : '';

        let html = '';
        let primaryCustomerId = custId;

        if (records.length > 0) {
            records.forEach(r => {
                if (r.customer_id) primaryCustomerId = r.customer_id;
                const cName = r.customer_name || custName;
                const cPhone = r.customer_phone || custPhone;
                const cAddress = r.customer_address || custAddress;
                const dateStr = r.date ? new Date(r.date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : (r.created_at ? r.created_at.substring(0, 10) : 'Recorded');

                html += `
                    <div class="p-5 rounded-3xl bg-gray-50/70 border border-red-100 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-200/60 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 bg-red-500 text-white rounded-lg text-[10px] font-black uppercase tracking-wider shadow-sm">
                                    <i class="fas fa-undo-alt mr-1"></i> Return #${r.return_id}
                                </span>
                                <span class="text-xs font-mono font-bold text-gray-500">Invoice: #${r.sale_id}</span>
                            </div>
                            <div class="text-xs font-bold text-gray-500 font-mono flex items-center gap-1">
                                <i class="far fa-calendar-alt text-teal-600"></i> ${dateStr}
                            </div>
                        </div>

                        <!-- Customer Card -->
                        <div class="p-4 bg-white rounded-2xl border border-gray-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div class="text-[9px] font-bold uppercase text-gray-400 tracking-wider">Customer Details</div>
                                <div class="text-sm font-black text-gray-800 flex items-center gap-1.5 mt-0.5">
                                    <i class="fas fa-user-circle text-teal-600 text-base"></i> ${cName}
                                </div>
                                ${cPhone ? `<div class="text-[11px] text-gray-500 font-mono mt-1"><i class="fas fa-phone-alt text-[9px] text-teal-500 mr-1"></i>${cPhone}</div>` : ''}
                                ${cAddress ? `<div class="text-[10px] text-gray-400 italic mt-0.5"><i class="fas fa-map-marker-alt text-[9px] text-orange-400 mr-1"></i>${cAddress}</div>` : ''}
                            </div>
                            ${r.customer_id ? `
                                <a href="customer_ledger.php?id=${r.customer_id}" class="px-3.5 py-2 bg-teal-50 hover:bg-teal-600 text-teal-700 hover:text-white rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-sm border border-teal-200 whitespace-nowrap self-start sm:self-auto">
                                    <i class="fas fa-file-invoice-dollar"></i> View Ledger <i class="fas fa-arrow-right text-[9px]"></i>
                                </a>
                            ` : ''}
                        </div>

                        <!-- Quantities & Refund -->
                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="p-3 bg-red-100/50 rounded-2xl border border-red-200">
                                <div class="text-[9px] font-bold uppercase text-red-500 tracking-wider">Quantity Returned</div>
                                <div class="text-lg font-black text-red-600 mt-0.5">${r.quantity} ${p ? p.unit : 'Units'}</div>
                            </div>
                            <div class="p-3 bg-teal-100/50 rounded-2xl border border-teal-200">
                                <div class="text-[9px] font-bold uppercase text-teal-600 tracking-wider">Total Refunded</div>
                                <div class="text-lg font-black text-teal-700 mt-0.5">Rs. ${Math.round(r.total_price).toLocaleString()}</div>
                                <div class="text-[9px] text-gray-400 mt-0.5">@ Rs. ${r.price_per_unit.toLocaleString()} / unit</div>
                            </div>
                        </div>

                        ${r.remarks ? `
                            <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200 text-xs text-amber-900">
                                <span class="font-bold"><i class="fas fa-comment-dots text-amber-500 mr-1"></i>Remarks:</span> ${r.remarks}
                            </div>
                        ` : ''}
                    </div>
                `;
            });
        } else {
            // Fallback if return was logged via sale_item returned_qty
            const si = saleItems.find(x => x.sale_id == saleId && x.product_id == productId);
            const retQty = si ? (parseFloat(si.returned_qty) || 0) : 0;
            const unitPrice = si ? (parseFloat(si.price_per_unit) || 0) : 0;
            const refundAmt = retQty * unitPrice;
            const saleDate = sale && sale.sale_date ? new Date(sale.sale_date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : 'Recorded';

            html = `
                <div class="p-5 rounded-3xl bg-gray-50/70 border border-red-100 shadow-sm space-y-4">
                    <div class="p-4 bg-white rounded-2xl border border-gray-100 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="text-[9px] font-bold uppercase text-gray-400 tracking-wider">Customer Details</div>
                            <div class="text-sm font-black text-gray-800 flex items-center gap-1.5 mt-0.5">
                                <i class="fas fa-user-circle text-teal-600 text-base"></i> ${custName}
                            </div>
                            ${custPhone ? `<div class="text-[11px] text-gray-500 font-mono mt-1"><i class="fas fa-phone-alt text-[9px] text-teal-500 mr-1"></i>${custPhone}</div>` : ''}
                        </div>
                        ${primaryCustomerId ? `
                            <a href="customer_ledger.php?id=${primaryCustomerId}" class="px-3.5 py-2 bg-teal-50 hover:bg-teal-600 text-teal-700 hover:text-white rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-sm border border-teal-200 whitespace-nowrap self-start sm:self-auto">
                                <i class="fas fa-file-invoice-dollar"></i> View Ledger <i class="fas fa-arrow-right text-[9px]"></i>
                            </a>
                        ` : ''}
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="p-3 bg-red-100/50 rounded-2xl border border-red-200">
                            <div class="text-[9px] font-bold uppercase text-red-500 tracking-wider">Quantity Returned</div>
                            <div class="text-lg font-black text-red-600 mt-0.5">${retQty} ${p ? p.unit : 'Units'}</div>
                        </div>
                        <div class="p-3 bg-teal-100/50 rounded-2xl border border-teal-200">
                            <div class="text-[9px] font-bold uppercase text-teal-600 tracking-wider">Refund Amount</div>
                            <div class="text-lg font-black text-teal-700 mt-0.5">Rs. ${Math.round(refundAmt).toLocaleString()}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        modalBody.innerHTML = html;

        let footerHtml = `
            <button onclick="closeReturnDetailsModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                Close
            </button>
        `;
        if (primaryCustomerId) {
            footerHtml = `
                <a href="customer_ledger.php?id=${primaryCustomerId}" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fas fa-user"></i> Open Customer Ledger
                </a>
            ` + footerHtml;
        }
        footerHtml = `
            <a href="return_history.php" class="px-5 py-2.5 bg-orange-50 hover:bg-orange-500 text-orange-600 hover:text-white border border-orange-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                <i class="fas fa-history"></i> Return History
            </a>
        ` + footerHtml;

        modalFooter.innerHTML = footerHtml;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeReturnDetailsModal() {
        const modal = document.getElementById('returnDetailsModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
</script>

<!-- Return Details Modal -->
<div id="returnDetailsModal" class="fixed inset-0 bg-black/60 backdrop-blur-md hidden z-[110] items-center justify-center p-4 no-print">
    <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-lg w-full overflow-hidden transform transition-all animate-in fade-in zoom-in duration-200 border border-gray-100">
        <!-- Header -->
        <div class="p-6 bg-gradient-to-r from-red-50 to-orange-50 border-b border-red-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-red-500 text-white flex items-center justify-center shadow-md shadow-red-200">
                    <i class="fas fa-undo-alt text-base"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-gray-800 tracking-tight">Return Transaction Details</h3>
                    <p class="text-[10px] text-red-600 font-bold uppercase tracking-wider" id="retModalProductTitle">Product Return</p>
                </div>
            </div>
            <button onclick="closeReturnDetailsModal()" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/80 text-gray-400 hover:text-red-600 hover:bg-white transition shadow-sm" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto" id="returnDetailsModalBody">
            <!-- Dynamic Content -->
        </div>

        <!-- Footer Actions -->
        <div class="p-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2" id="returnDetailsModalFooter">
            <!-- Dynamic Buttons -->
        </div>
    </div>
</div>

<script>
    document.getElementById('returnDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) closeReturnDetailsModal();
    });
</script>

<?php include '../includes/footer.php'; ?>
