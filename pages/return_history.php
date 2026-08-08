<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Return Products History";
include '../includes/header.php';

// 1. Fetch Customer Returns
$returns = readCSV('returns');
$return_items = readCSV('return_items');
$customers = readCSV('customers');
$products = readCSV('products');

$c_map = [];
foreach ($customers as $c) $c_map[$c['id']] = $c['name'];

$p_map = [];
foreach ($products as $p) $p_map[$p['id']] = $p['name'];

$cust_return_items_grouped = [];
foreach ($return_items as $ri) {
    $rid = $ri['return_id'] ?? '';
    if ($rid) {
        $cust_return_items_grouped[$rid][] = [
            'p_name' => $p_map[$ri['product_id'] ?? ''] ?? 'Unknown',
            'qty' => (float)($ri['quantity'] ?? 0),
            'price' => (float)($ri['price_per_unit'] ?? 0),
            'total' => (float)($ri['total_price'] ?? 0)
        ];
    }
}

$js_cust_returns = [];
foreach ($returns as $r) {
    $rid = $r['id'];
    $items = $cust_return_items_grouped[$rid] ?? [];
    $items_summary = [];
    foreach ($items as $it) {
        $items_summary[] = $it['p_name'] . " (x" . $it['qty'] . ")";
    }
    $summary_text = !empty($items_summary) ? implode(', ', $items_summary) : 'Item Returned';

    $js_cust_returns[] = [
        'id' => $r['id'],
        'sale_id' => $r['sale_id'] ?? '',
        'customer_id' => $r['customer_id'] ?? '',
        'customer_name' => $c_map[$r['customer_id'] ?? ''] ?? 'Walk-in Customer',
        'total_refund' => (float)($r['total_refund'] ?? 0),
        'remarks' => $r['remarks'] ?? '',
        'date' => $r['date'] ?? date('Y-m-d', strtotime($r['created_at'] ?? 'now')),
        'created_at' => $r['created_at'] ?? '',
        'items_summary' => $summary_text,
        'items' => $items
    ];
}

// Sort Customer Returns by Date DESC, ID DESC
usort($js_cust_returns, function($a, $b) {
    $dateA = $a['date'];
    $dateB = $b['date'];
    if ($dateA != $dateB) {
        return strcmp($dateB, $dateA);
    }
    return (int)$b['id'] - (int)$a['id'];
});

// 2. Fetch Dealer Returns
$dealer_returns = readCSV('dealer_returns');
$dealer_return_items = readCSV('dealer_return_items');
$dealers = readCSV('dealers');

$d_map = [];
foreach ($dealers as $d) $d_map[$d['id']] = $d['name'];

$dealer_return_items_grouped = [];
foreach ($dealer_return_items as $dri) {
    $drid = $dri['dealer_return_id'] ?? '';
    if ($drid) {
        $dealer_return_items_grouped[$drid][] = [
            'p_name' => $p_map[$dri['product_id'] ?? ''] ?? 'Item',
            'qty' => (float)($dri['quantity'] ?? 0),
            'price' => (float)($dri['price_per_unit'] ?? 0),
            'total' => (float)($dri['total_price'] ?? 0)
        ];
    }
}

$js_dealer_returns = [];
foreach ($dealer_returns as $dr) {
    $drid = $dr['id'];
    $items = $dealer_return_items_grouped[$drid] ?? [];
    $items_summary = [];
    if (!empty($items)) {
        foreach ($items as $it) {
            $items_summary[] = $it['p_name'] . " (x" . $it['qty'] . ")";
        }
        $summary_text = implode(', ', $items_summary);
    } else {
        $pname = $dr['product_name'] ?? ($p_map[$dr['product_id'] ?? ''] ?? 'Item');
        $summary_text = $pname . " (x" . ($dr['quantity'] ?? 0) . " " . ($dr['unit'] ?? '') . ")";
    }

    $dealer_name = $dr['dealer_name'] ?? ($d_map[$dr['dealer_id'] ?? ''] ?? 'Open Market');
    if (empty($dealer_name)) $dealer_name = 'Open Market';

    $js_dealer_returns[] = [
        'id' => $dr['id'],
        'restock_id' => $dr['restock_id'] ?? '',
        'dealer_id' => $dr['dealer_id'] ?? '',
        'dealer_name' => $dealer_name,
        'product_name' => $dr['product_name'] ?? '',
        'quantity' => (float)($dr['quantity'] ?? 0),
        'unit' => $dr['unit'] ?? '',
        'total_refund' => (float)($dr['total_refund'] ?? 0),
        'remarks' => $dr['remarks'] ?? '',
        'date' => $dr['date'] ?? date('Y-m-d', strtotime($dr['created_at'] ?? 'now')),
        'created_at' => $dr['created_at'] ?? '',
        'items_summary' => $summary_text,
        'items' => $items
    ];
}

// Sort Dealer Returns by Date DESC, ID DESC
usort($js_dealer_returns, function($a, $b) {
    $dateA = $a['date'];
    $dateB = $b['date'];
    if ($dateA != $dateB) {
        return strcmp($dateB, $dateA);
    }
    return (int)$b['id'] - (int)$a['id'];
});
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Return Products History</h2>
        <p class="text-sm text-gray-500">Track all customer refunds and dealer product return logs.</p>
    </div>
    <div class="flex gap-2">
        <a href="return_product.php" class="bg-teal-600 text-white px-5 py-2 rounded-xl hover:bg-teal-700 transition shadow-lg text-xs font-bold flex items-center">
            <i class="fas fa-undo mr-2"></i> Customer Return
        </a>
        <a href="return_product_dealer.php" class="bg-amber-600 text-white px-5 py-2 rounded-xl hover:bg-amber-700 transition shadow-lg text-xs font-bold flex items-center">
            <i class="fas fa-truck-loading mr-2"></i> Dealer Return
        </a>
    </div>
</div>

<!-- Tabs Selection -->
<div class="flex border-b border-gray-200 mb-6 bg-white rounded-2xl p-2 shadow-sm">
    <button onclick="switchTab('customer')" id="tabBtnCustomer" class="flex-1 py-3 px-6 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 bg-teal-600 text-white shadow-md">
        <i class="fas fa-user-undo"></i> Customer Returns
        <span class="bg-white/20 text-white text-[10px] px-2 py-0.5 rounded-full font-black"><?= count($js_cust_returns) ?></span>
    </button>
    <button onclick="switchTab('dealer')" id="tabBtnDealer" class="flex-1 py-3 px-6 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 text-gray-500 hover:text-teal-600 hover:bg-teal-50">
        <i class="fas fa-truck-loading"></i> Dealer Returns
        <span class="bg-gray-100 text-gray-600 text-[10px] px-2 py-0.5 rounded-full font-black"><?= count($js_dealer_returns) ?></span>
    </button>
</div>

<!-- Filter Bar -->
<div class="mb-6 bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
    <form class="flex flex-wrap items-end gap-3" onsubmit="return false;">
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Quick Range</label>
            <select id="quickRange" onchange="applyQuickDate(this.value)" class="p-2 border rounded-xl text-sm focus:ring-2 focus:ring-teal-500 outline-none w-32 font-medium">
                <option value="">Custom</option>
                <option value="today">Today</option>
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="last_90">Last 90 Days</option>
                <option value="last_year">Last 1 Year</option>
            </select>
        </div>
        <div class="flex flex-col flex-1 min-w-[200px]">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Search Returns</label>
            <input type="text" id="searchInput" onkeyup="renderTable()" placeholder="Search by name, ID, or items..." class="p-2 border rounded-xl text-sm focus:ring-2 focus:ring-teal-500 outline-none">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">From Date</label>
            <input type="date" id="dateFrom" onchange="renderTable()" class="p-2 border rounded-xl text-sm focus:ring-2 focus:ring-teal-500 outline-none">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" id="dateTo" onchange="renderTable()" class="p-2 border rounded-xl text-sm focus:ring-2 focus:ring-teal-500 outline-none">
        </div>
        <div>
            <button type="button" onclick="clearFilters()" class="px-4 py-2 bg-gray-100 text-gray-500 rounded-xl text-sm font-bold hover:bg-gray-200 transition h-[38px] flex items-center border">
                CLEAR
            </button>
        </div>
        <div class="flex gap-2">
            <button onclick="printReport()" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 transition shadow-md font-bold text-sm h-[38px] flex items-center">
                <i class="fas fa-print mr-2"></i> Print / Save PDF
            </button>
        </div>
    </form>
</div>

<!-- History Table Card -->
<div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-teal-700 text-white text-xs uppercase tracking-widest font-black" id="tableHeaderRow">
                    <!-- Dynamic Header -->
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm" id="returnHistoryBody">
                <!-- JS Rendered -->
            </tbody>
        </table>
    </div>
    <div id="paginationContainer" class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center text-xs font-bold text-gray-500">
        <!-- JS Pagination -->
    </div>
</div>

<!-- Hidden Printable Area for Report / PDF -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #0f766e; margin: 0; font-size: 28px;"><?= getSetting('business_name', 'Fashion Shines') ?></h1>
                <p style="color: #666; margin: 5px 0 0 0;" id="printReportSubtitle">Product Returns Report</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333;" id="printReportTitle">Returns Report Summary</h2>
                <p style="color: #888; margin: 5px 0 0 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #0f766e; color: #fff;" id="printTableHeader">
                    <!-- Dynamic Print Header -->
                </tr>
            </thead>
            <tbody id="printTableBody">
                <!-- JS Populated -->
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="4" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 11px;">Total Return Amount in Period:</td>
                    <td id="printFooterTotal" style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #0f766e; font-size: 16px;">Rs. 0</td>
                </tr>
            </tfoot>
        </table>

        <!-- Mandatory Developer Footer -->
        <div style="margin-top:40px; border-top:1px solid #eee; padding-top:15px; text-align:center; font-size:9px; color:#aaa;">
            <p style="margin:0; font-weight:bold; color:#888;">Software Developed by Abdul Rafay</p>
            <p style="margin:4px 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>

<script>
var custReturns = <?= json_encode($js_cust_returns) ?>;
var dealerReturns = <?= json_encode($js_dealer_returns) ?>;

var activeTab = 'customer'; // 'customer' or 'dealer'
var currentPage = 1;
var pageSize = 50;

function switchTab(tab) {
    activeTab = tab;
    currentPage = 1;
    
    const btnCust = document.getElementById('tabBtnCustomer');
    const btnDealer = document.getElementById('tabBtnDealer');
    
    if (tab === 'customer') {
        btnCust.className = "flex-1 py-3 px-6 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 bg-teal-600 text-white shadow-md";
        btnDealer.className = "flex-1 py-3 px-6 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 text-gray-500 hover:text-teal-600 hover:bg-teal-50";
    } else {
        btnDealer.className = "flex-1 py-3 px-6 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 bg-teal-600 text-white shadow-md";
        btnCust.className = "flex-1 py-3 px-6 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 text-gray-500 hover:text-teal-600 hover:bg-teal-50";
    }
    
    renderTable();
}

function getFilteredData() {
    const data = (activeTab === 'customer') ? custReturns : dealerReturns;
    const search = document.getElementById('searchInput').value.toLowerCase().trim();
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;

    return data.filter(item => {
        // Date Filter
        if (dateFrom && item.date < dateFrom) return false;
        if (dateTo && item.date > dateTo) return false;

        // Search Filter
        if (search) {
            const idMatch = (item.id || '').toString().toLowerCase().includes(search);
            const refMatch = (activeTab === 'customer' ? (item.sale_id || '') : (item.restock_id || '')).toString().toLowerCase().includes(search);
            const nameMatch = (activeTab === 'customer' ? (item.customer_name || '') : (item.dealer_name || '')).toLowerCase().includes(search);
            const itemMatch = (item.items_summary || '').toLowerCase().includes(search);
            const remarksMatch = (item.remarks || '').toLowerCase().includes(search);

            if (!idMatch && !refMatch && !nameMatch && !itemMatch && !remarksMatch) return false;
        }

        return true;
    });
}

function renderTable() {
    const headerRow = document.getElementById('tableHeaderRow');
    const tbody = document.getElementById('returnHistoryBody');
    const filtered = getFilteredData();

    if (activeTab === 'customer') {
        headerRow.innerHTML = `
            <th class="p-4 w-12 text-center">Sno#</th>
            <th class="p-4">Date & Time</th>
            <th class="p-4">Return ID</th>
            <th class="p-4">Sale Ref</th>
            <th class="p-4">Customer Name</th>
            <th class="p-4">Items Returned</th>
            <th class="p-4 text-right">Refund Amount</th>
            <th class="p-4">Remarks</th>
            <th class="p-4 text-center">Receipt</th>
        `;
    } else {
        headerRow.innerHTML = `
            <th class="p-4 w-12 text-center">Sno#</th>
            <th class="p-4">Date & Time</th>
            <th class="p-4">Return ID</th>
            <th class="p-4">Restock Ref</th>
            <th class="p-4">Dealer / Supplier</th>
            <th class="p-4">Product Returned</th>
            <th class="p-4 text-right">Return Credit</th>
            <th class="p-4">Remarks</th>
            <th class="p-4 text-center">Receipt</th>
        `;
    }

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="p-8 text-center text-gray-400 italic">
                    <i class="fas fa-history text-3xl mb-2 block opacity-30"></i>
                    No return records found matching your filters.
                </td>
            </tr>
        `;
        document.getElementById('paginationContainer').innerHTML = `Showing 0 records`;
        return;
    }

    const totalPages = Math.ceil(filtered.length / pageSize);
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * pageSize;
    const pageItems = filtered.slice(startIdx, startIdx + pageSize);

    let html = '';
    pageItems.forEach((item, idx) => {
        const sno = startIdx + idx + 1;
        const formattedDate = item.created_at ? new Date(item.created_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }) : item.date;
        const printUrl = (activeTab === 'customer') ? `print_return.php?id=${item.id}` : `print_return_dealer.php?id=${item.id}`;

        if (activeTab === 'customer') {
            html += `
                <tr class="hover:bg-gray-50/80 transition-colors">
                    <td class="p-4 text-center font-bold text-gray-400">${sno}</td>
                    <td class="p-4 font-bold text-gray-700 text-xs">${formattedDate}</td>
                    <td class="p-4 font-black text-teal-700">#${item.id}</td>
                    <td class="p-4 font-bold text-gray-600">Sale #${item.sale_id}</td>
                    <td class="p-4 font-bold text-gray-800">${escapeHtml(item.customer_name)}</td>
                    <td class="p-4 text-xs font-medium text-gray-600 max-w-xs truncate" title="${escapeHtml(item.items_summary)}">${escapeHtml(item.items_summary)}</td>
                    <td class="p-4 text-right font-black text-red-600">Rs. ${Math.round(item.total_refund).toLocaleString()}</td>
                    <td class="p-4 text-xs italic text-gray-500">${escapeHtml(item.remarks || '-')}</td>
                    <td class="p-4 text-center">
                        <a href="${printUrl}" target="_blank" class="px-3 py-1.5 bg-teal-50 text-teal-700 hover:bg-teal-600 hover:text-white rounded-lg text-xs font-bold transition flex items-center justify-center gap-1 w-fit mx-auto border border-teal-100 shadow-sm">
                            <i class="fas fa-print"></i> Receipt
                        </a>
                    </td>
                </tr>
            `;
        } else {
            html += `
                <tr class="hover:bg-gray-50/80 transition-colors">
                    <td class="p-4 text-center font-bold text-gray-400">${sno}</td>
                    <td class="p-4 font-bold text-gray-700 text-xs">${formattedDate}</td>
                    <td class="p-4 font-black text-amber-700">#${item.id}</td>
                    <td class="p-4 font-bold text-gray-600">Restock #${item.restock_id}</td>
                    <td class="p-4 font-bold text-gray-800">${escapeHtml(item.dealer_name)}</td>
                    <td class="p-4 text-xs font-medium text-gray-600 max-w-xs truncate" title="${escapeHtml(item.items_summary)}">${escapeHtml(item.items_summary)}</td>
                    <td class="p-4 text-right font-black text-teal-700">Rs. ${Math.round(item.total_refund).toLocaleString()}</td>
                    <td class="p-4 text-xs italic text-gray-500">${escapeHtml(item.remarks || '-')}</td>
                    <td class="p-4 text-center">
                        <a href="${printUrl}" target="_blank" class="px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white rounded-lg text-xs font-bold transition flex items-center justify-center gap-1 w-fit mx-auto border border-amber-100 shadow-sm">
                            <i class="fas fa-print"></i> Receipt
                        </a>
                    </td>
                </tr>
            `;
        }
    });

    tbody.innerHTML = html;

    // Pagination
    let pagHtml = `Showing ${startIdx + 1} to ${Math.min(startIdx + pageSize, filtered.length)} of ${filtered.length} entries`;
    if (totalPages > 1) {
        pagHtml += `<div class="flex gap-1">`;
        pagHtml += `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1 rounded bg-gray-200 text-gray-700 disabled:opacity-40">Prev</button>`;
        pagHtml += `<span class="px-3 py-1 text-gray-700">Page ${currentPage} of ${totalPages}</span>`;
        pagHtml += `<button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} class="px-3 py-1 rounded bg-gray-200 text-gray-700 disabled:opacity-40">Next</button>`;
        pagHtml += `</div>`;
    }
    document.getElementById('paginationContainer').innerHTML = pagHtml;
}

function changePage(p) {
    currentPage = p;
    renderTable();
}

function applyQuickDate(type) {
    const today = new Date();
    let start, end;
    
    if (type === 'today') {
        start = new Date();
        end = new Date();
    } else if (type === 'this_month') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
        end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    } else if (type === 'last_month') {
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
    } else if (type === 'last_90') {
        end = new Date();
        start = new Date();
        start.setDate(today.getDate() - 90);
    } else if (type === 'last_year') {
        end = new Date();
        start = new Date();
        start.setFullYear(today.getFullYear() - 1);
    } else {
        return;
    }
    
    const fmt = d => d.toISOString().split('T')[0];
    document.getElementById('dateFrom').value = fmt(start);
    document.getElementById('dateTo').value = fmt(end);
    renderTable();
}

function clearFilters() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    document.getElementById('searchInput').value = '';
    document.getElementById('quickRange').value = '';
    renderTable();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function printReport() {
    const filtered = getFilteredData();
    const isCust = (activeTab === 'customer');

    document.getElementById('printReportTitle').innerText = isCust ? "Customer Returns Report" : "Dealer Returns Report";
    document.getElementById('printReportSubtitle').innerText = isCust ? "Summary of customer returns and refunds" : "Summary of dealer product returns and credits";

    const printHeader = document.getElementById('printTableHeader');
    if (isCust) {
        printHeader.innerHTML = `
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Date</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Return ID</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Customer</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Items Summary</th>
            <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Refund Amount</th>
        `;
    } else {
        printHeader.innerHTML = `
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Date</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Return ID</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Dealer</th>
            <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Product Returned</th>
            <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Return Credit</th>
        `;
    }

    const printBody = document.getElementById('printTableBody');
    let rowsHtml = '';
    let grandTotal = 0;

    filtered.forEach(item => {
        grandTotal += parseFloat(item.total_refund) || 0;
        const name = isCust ? escapeHtml(item.customer_name) : escapeHtml(item.dealer_name);

        rowsHtml += `
            <tr>
                <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${item.date}</td>
                <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; font-weight: bold;">#${item.id}</td>
                <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${name}</td>
                <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${escapeHtml(item.items_summary)}</td>
                <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right; font-weight: bold;">Rs. ${Math.round(item.total_refund).toLocaleString()}</td>
            </tr>
        `;
    });

    if (filtered.length === 0) {
        rowsHtml = `<tr><td colspan="5" style="padding: 15px; text-align: center; color: #888;">No return records found.</td></tr>`;
    }

    printBody.innerHTML = rowsHtml;
    document.getElementById('printFooterTotal').innerText = 'Rs. ' + Math.round(grandTotal).toLocaleString();

    // Trigger Print Window
    const content = document.getElementById('printableArea').innerHTML;
    const printWindow = window.open('', '', 'height=700,width=900');
    printWindow.document.write('<html><head><title>Print Report</title></head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Initial Render
document.addEventListener('DOMContentLoaded', () => {
    renderTable();
});
</script>

<?php include '../includes/footer.php'; ?>
