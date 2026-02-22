<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$pageTitle = "Sales History";
include '../includes/header.php';

// Load Data
$all_sales = readCSV('sales');
$sale_items = readCSV('sale_items');
$products = readCSV('products');
$customers = readCSV('customers');

// RBAC: Filter Sales for Customer Role
if (isRole('Customer')) {
    $cid = $_SESSION['related_id'] ?? '';
    $all_sales = array_filter($all_sales, function($s) use ($cid) {
        return ($s['customer_id'] ?? '') == $cid;
    });
}

// Maps for easy lookup
$c_map = [];
foreach($customers as $c) $c_map[$c['id']] = $c['name'];

$p_name_map = [];
foreach($products as $p) $p_name_map[$p['id']] = $p['name'];

// NEW: FIFO Payment Allocation Logic
$transactions = readCSV('customer_transactions');
$customer_credits = []; // total credits per customer
foreach($transactions as $t) {
    $cid = $t['customer_id'];
    $customer_credits[$cid] = ($customer_credits[$cid] ?? 0) + (float)$t['credit'];
}

// Sort all sales by date ASC for FIFO allocation
$all_sales_fifo = $all_sales;
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

$grouped_items = [];
foreach($sale_items as $si) {
    $grouped_items[$si['sale_id']][] = $si;
}

// Prepare JS Data
$js_sales = [];
foreach($all_sales as $s) {
    $sale_id = $s['id'];
    $items = $grouped_items[$sale_id] ?? [];
    $formatted_items = [];
    foreach($items as $i) {
        $formatted_items[] = [
            'p_name' => $p_name_map[$i['product_id']] ?? 'Unknown',
            'qty' => (float)$i['quantity'],
            'unit' => $i['unit'] ?? '',
            'returned_qty' => (float)($i['returned_qty'] ?? 0)
        ];
    }
    
    $js_sales[] = [
        'id' => $s['id'],
        'customer_id' => $s['customer_id'] ?? '',
        'customer_name' => $c_map[$s['customer_id']] ?? 'Walk-in Customer',
        'date' => $s['sale_date'],
        'total' => (float)$s['total_amount'],
        'paid' => $sale_paid_adj[$sale_id] ?? 0,
        'method' => $s['payment_method'],
        'remarks' => $s['remarks'] ?? '',
        'items' => $formatted_items
    ];
}
?>

<script>
    const allSales = <?= json_encode($js_sales) ?>;
    const isRoleAdmin = <?= json_encode(isRole('Admin')) ?>;
    const isRoleCustomer = <?= json_encode(isRole('Customer')) ?>;

    let currentPage_Sales = 1;
    const pageSize_Sales = 200;

    function formatCurrency(amount) {
        return 'Rs.' + new Intl.NumberFormat('en-US').format(amount);
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.sale-checkbox');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        toggleBulkBtn();
    }

    function toggleBulkBtn() {
        if(!isRoleAdmin) return;
        const checkboxes = document.querySelectorAll('.sale-checkbox:checked');
        const btn = document.getElementById('bulkDeleteBtn');
        if (checkboxes.length > 0) {
            btn.classList.remove('hidden');
        } else {
            btn.classList.add('hidden');
        }
    }

    function confirmDelete(id) {
        showConfirm('DELETE RECORD ONLY: This will remove the sale record from history but will NOT restore stock or clear customer debt. Continue?', () => {
            window.location.href = `../actions/delete_sale.php?id=${id}`;
        }, 'Delete Record Only?');
        return false;
    }

    function confirmRevert(id) {
        showConfirm('FULL REVERT: This will DELETE the sale, RESTORE product stock, and REVERSE the customer ledger entry. Continue?', () => {
            window.location.href = `../actions/revert_sale.php?id=${id}`;
        }, 'Reverse Sale?');
        return false;
    }

    function confirmBulkDelete() {
        const count = document.querySelectorAll('.sale-checkbox:checked').length;
        showConfirm(`Are you sure you want to delete ${count} selected records? (No restocking or ledger changes)`, () => {
            document.getElementById('bulkDeleteForm').submit();
        }, 'Bulk Delete?');
    }

    function changePage_Sales(page) {
        currentPage_Sales = page;
        renderSalesTable();
        // Scroll to top of table
        document.getElementById('salesTableContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderSalesTable() {
        const term = document.getElementById('salesSearch').value.toLowerCase();
        const fType = document.getElementById('f_type_select').value;
        const fromDate = document.getElementById('dateFrom').value;
        const toDate = document.getElementById('dateTo').value;

        // Date constraints
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);
        const sixtyDaysAgo = new Date();
        sixtyDaysAgo.setDate(today.getDate() - 60);

        let filtered = allSales.filter(s => {
            const saleDate = new Date(s.date);
            const saleDateStr = s.date.substring(0, 10);
            const matchesSearch = s.customer_name.toLowerCase().includes(term);
            
            let matchesDate = true;
            if (fType === 'month') {
                const currentMonth = today.toISOString().substring(0, 7);
                matchesDate = s.date.startsWith(currentMonth);
            } else if (fType === 'year') {
                const currentYear = today.getFullYear().toString();
                matchesDate = s.date.startsWith(currentYear);
            } else if (fType === '30days') {
                matchesDate = saleDate >= thirtyDaysAgo;
            } else if (fType === '60days') {
                matchesDate = saleDate >= sixtyDaysAgo;
            } else if (fType === 'range') {
                if (fromDate) matchesDate = matchesDate && (saleDateStr >= fromDate);
                if (toDate) matchesDate = matchesDate && (saleDateStr <= toDate);
            }
            
            return matchesSearch && matchesDate;
        });

        // Sort descending
        filtered.sort((a,b) => new Date(b.date) - new Date(a.date));

        // Pagination
        const totalItems = filtered.length;
        const paginated = Pagination.paginate(filtered, currentPage_Sales, pageSize_Sales);

        const tbody = document.getElementById('salesTableBody');
        if (totalItems === 0) {
            tbody.innerHTML = `<tr><td colspan="11" class="p-12 text-center text-gray-400"><i class="fas fa-receipt text-4xl mb-3 text-gray-200"></i><br>No sales records found.</td></tr>`;
            Pagination.render('salesPagination', 0, 1, pageSize_Sales, changePage_Sales);
            return;
        }

        let html = '';
        paginated.forEach((s, index) => {
            const displayIndex = (currentPage_Sales - 1) * pageSize_Sales + index + 1;
            const dateStr = new Date(s.date).toLocaleString('en-GB', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
            const isDue = s.total > s.paid;
            const statusClass = isDue ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
            const statusLabel = isDue ? 'Due' : 'Paid';
            const statusIcon = isDue ? 'fa-exclamation-circle' : 'fa-check';

            let itemsHtml = s.items.map(i => `
                <div class="flex flex-col border-b border-gray-50 pb-1 last:border-0 mb-1">
                    <div class="flex justify-between gap-4 text-[11px]">
                        <span class="font-bold text-gray-600 truncate max-w-[150px]">${i.p_name}</span>
                        <span class="text-teal-600 font-black whitespace-nowrap">x ${i.qty} ${i.unit}</span>
                    </div>
                    ${i.returned_qty > 0 ? `
                    <div class="flex justify-between text-[10px] text-red-500 font-bold -mt-0.5">
                        <span>[Returned]</span>
                        <span>- ${i.returned_qty}</span>
                    </div>
                    ` : ''}
                </div>
            `).join('');

            html += `
                <tr class="hover:bg-teal-50 transition border-b border-gray-50 last:border-0 text-sm">
                    <td class="p-4 pl-6 text-center">
                        ${isRoleAdmin ? `<input type="checkbox" name="id[]" value="${s.id}" onchange="toggleBulkBtn()" class="sale-checkbox rounded border-gray-300 text-teal-600 focus:ring-teal-500">` : displayIndex}
                    </td>
                    <td class="p-4 font-mono text-gray-500">${displayIndex}</td>
                    <td class="p-4 text-gray-700 font-medium">${dateStr}</td>
                    ${!isRoleCustomer ? `
                    <td class="p-4">
                        <div class="font-bold text-gray-800">
                            ${s.customer_id ? `<a href="customer_ledger.php?id=${s.customer_id}" class="text-teal-600 hover:underline transition-all">${s.customer_name}</a>` : `<span class="text-gray-400 font-normal italic">Walk-in Customer</span>`}
                        </div>
                    </td>` : ''}
                    <td class="p-4 align-top">
                        <div class="space-y-1 max-h-[160px] overflow-y-auto pr-2 custom-scrollbar">${itemsHtml}</div>
                    </td>
                    <td class="p-4 font-bold text-gray-900 text-right">${formatCurrency(s.total)}</td>
                    <td class="p-4 text-green-600 font-semibold text-right">${formatCurrency(s.paid)}</td>
                    <td class="p-4">
                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-xs font-medium border border-gray-200 uppercase">${s.method}</span>
                    </td>
                    <td class="p-4">
                        <div class="text-[10px] text-gray-500 font-bold leading-relaxed line-clamp-2 max-w-[150px]" title="${s.remarks}">${s.remarks}</div>
                    </td>
                    <td class="p-4 text-center">
                        ${s.customer_id ? `
                            <a href="customer_ledger.php?id=${s.customer_id}" class="${statusClass} text-[10px] uppercase font-bold px-2 py-1 rounded-full border border-opacity-20 hover:bg-opacity-80 transition cursor-pointer flex items-center justify-center gap-1 mx-auto w-fit">
                                <i class="fas ${statusIcon}"></i> ${statusLabel}
                            </a>
                        ` : `
                            <span class="${statusClass} text-[10px] uppercase font-bold px-2 py-1 rounded-full border border-opacity-20 hover:opacity-80 transition cursor-default flex items-center justify-center gap-1 mx-auto w-fit">
                                <i class="fas ${statusIcon}"></i> ${statusLabel}
                            </span>
                        `}
                    </td>
                    <td class="p-4 text-center">
                        <div class="flex items-center justify-center gap-3">
                            ${isRoleAdmin ? `
                            <a href="edit_sale.php?id=${s.id}" class="text-blue-500 hover:text-blue-700 transition" title="Edit Sale"><i class="fas fa-edit"></i></a>
                            <a href="#" onclick="return confirmRevert('${s.id}')" class="text-orange-500 hover:text-orange-700 transition" title="Revert Sale (UNDO All Effects)"><i class="fas fa-undo"></i></a>
                            ` : ''}
                            <a href="print_bill.php?id=${s.id}" target="_blank" class="text-teal-600 hover:text-teal-800 transition" title="Print Bill"><i class="fas fa-print"></i></a>
                            ${isRoleAdmin ? `
                            <a href="#" onclick="return confirmDelete('${s.id}')" class="text-red-500 hover:text-red-700 transition" title="Delete record ONLY"><i class="fas fa-trash-alt"></i></a>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        Pagination.render('salesPagination', totalItems, currentPage_Sales, pageSize_Sales, changePage_Sales);
        updatePrintLink();
    }

    function toggleCustomRange() {
        const fType = document.getElementById('f_type_select').value;
        const rangeForm = document.getElementById('customRangeInputs');
        if (fType === 'range') {
            rangeForm.classList.remove('hidden');
        } else {
            rangeForm.classList.add('hidden');
            renderSalesTable();
        }
    }

    function updatePrintLink() {
        const term = document.getElementById('salesSearch').value;
        const fType = document.getElementById('f_type_select').value;
        const fromDate = document.getElementById('dateFrom').value;
        const toDate = document.getElementById('dateTo').value;
        
        let query = `?f_type=${fType}&search=${encodeURIComponent(term)}`;
        if (fromDate) query += `&from=${fromDate}`;
        if (toDate) query += `&to=${toDate}`;
        
        document.getElementById('printBtn').href = `print_sales.php${query}`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('salesSearch').addEventListener('input', () => {
            currentPage_Sales = 1;
            renderSalesTable();
        });
        document.getElementById('f_type_select').addEventListener('change', () => {
            currentPage_Sales = 1;
            toggleCustomRange();
        });
        document.getElementById('dateFrom').addEventListener('change', () => {
            currentPage_Sales = 1;
            renderSalesTable();
        });
        document.getElementById('dateTo').addEventListener('change', () => {
            currentPage_Sales = 1;
            renderSalesTable();
        });
        renderSalesTable();
    });
</script>

<!-- Simplified Filter Bar -->
<div class="mb-6 flex flex-col lg:flex-row justify-between items-center gap-4 bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
    <div class="flex flex-col md:flex-row items-center gap-3 flex-1 w-full">
        <div class="relative w-full max-w-sm">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <i class="fas fa-search text-xs"></i>
            </span>
            <input type="text" id="salesSearch" autofocus placeholder="Search by customer name..." class="w-full pl-9 pr-4 py-2.5 rounded-xl border-gray-200 border focus:ring-2 focus:ring-teal-500 focus:outline-none transition text-sm">
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto">
            <select id="f_type_select" class="pl-3 pr-8 py-2.5 rounded-xl border-gray-200 border bg-teal-50 text-teal-700 font-bold text-sm focus:ring-2 focus:ring-teal-500 appearance-none bg-no-repeat bg-[right_0.5rem_center] bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%230d9488%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E');">
                <option value="30days" selected>Last 30 Days</option>
                <option value="month">Monthly Analysis</option>
                <option value="year">Yearly Report</option>
                <option value="60days">Last 60 Days</option>
                <option value="all">All Time</option>
                <option value="range">Custom Range</option>
            </select>
            
            <div id="customRangeInputs" class="flex items-center gap-2 hidden">
                <input type="date" id="dateFrom" class="p-2.5 rounded-xl border-gray-200 border text-xs font-semibold">
                <input type="date" id="dateTo" class="p-2.5 rounded-xl border-gray-200 border text-xs font-semibold">
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 w-full lg:w-auto border-l border-gray-100 pl-4">
        <a id="printBtn" href="print_sales.php" target="_blank" class="px-5 py-2.5 bg-gray-800 text-white rounded-xl hover:bg-black transition shadow-sm border border-gray-700 flex items-center gap-2 font-bold text-sm">
            <i class="fas fa-print"></i> Print
        </a>
    </div>
</div>

<div class="flex items-center justify-between mb-4">
    <h3 class="font-bold text-gray-800 text-lg flex items-center">
        <i class="fas fa-list-ul mr-2 text-teal-500"></i> Detail Transaction Log
    </h3>
    <?php if (isRole('Admin')): ?>
    <button id="bulkDeleteBtn" onclick="confirmBulkDelete()" class="hidden bg-red-100 text-red-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-red-600 hover:text-white transition flex items-center border border-red-200 shadow-sm">
        <i class="fas fa-trash-alt mr-2"></i> Restore Stock & Delete Selected
    </button>
    <?php endif; ?>
</div>

<form id="bulkDeleteForm" action="../actions/delete_sale.php" method="POST">
<div id="salesTableContainer" class="bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-teal-700 text-white text-sm uppercase tracking-wider">
                    <th class="p-4 text-center w-10">
                        <?php if (isRole('Admin')): ?>
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded border-teal-500 text-accent focus:ring-accent">
                        <?php else: ?>
                        #
                        <?php endif; ?>
                    </th>
                    <th class="p-3">Sr #</th>
                    <th class="p-4">Date & Time</th>
                    <?php if (!isRole('Customer')): ?>
                    <th class="p-4">Customer</th>
                    <?php endif; ?>
                    <th class="p-4">Products & QTY</th>
                    <th class="p-4 text-right">Total Amount</th>
                    <th class="p-4 text-right">Paid</th>
                    <th class="p-4">Payment Method</th>
                    <th class="p-4">Remarks</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="salesTableBody" class="divide-y divide-gray-100">
                <!-- Data rendered by JavaScript -->
            </tbody>
        </table>
    </div>
    <div id="salesPagination" class="px-6 py-4 bg-gray-50 border-t border-gray-100"></div>
    <div style="border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; text-align: center; font-size: 10px; color: #888;">
        <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
        <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
    </div>
</div>
</form>

<?php include '../includes/footer.php'; ?>
