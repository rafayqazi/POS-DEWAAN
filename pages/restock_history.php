<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Restock History";
include '../includes/header.php';

$restocks = readCSV('restocks');

$restocks = readCSV('restocks');

// Sort by ID descending to see newest first
usort($restocks, function($a, $b) {
    return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
});
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Restock Logs</h2>
        <p class="text-sm text-gray-500">Track all inventory replenishment activities and costs.</p>
    </div>
    <a href="inventory.php" class="bg-teal-600 text-white px-6 py-2 rounded-xl hover:bg-teal-700 transition shadow-lg flex items-center w-full md:w-auto justify-center">
        <i class="fas fa-boxes mr-2"></i> Go to Inventory
    </a>
</div>

<!-- Filters -->
<div class="mb-6 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
    <form class="flex flex-wrap items-end gap-3">
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Quick Range</label>
            <select onchange="applyQuickDate(this.value)" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none w-32">
                <option value="">Custom</option>
                <option value="today">Today</option>
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="last_90">Last 90 Days</option>
                <option value="last_year">Last 1 Year</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">From Date</label>
            <input type="date" id="dateFrom" onchange="renderTable()" value="<?= date('Y-m-01') ?>" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" id="dateTo" onchange="renderTable()" value="<?= date('Y-m-d') ?>" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">&nbsp;</label>
            <button type="button" onclick="clearFilters()" class="px-4 py-2 bg-gray-100 text-gray-500 rounded-lg text-sm font-bold hover:bg-gray-200 transition h-[38px] flex items-center border">
                CLEAR
            </button>
        </div>
        
        <div class="flex gap-2">
            <button onclick="printReport()" type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition shadow-md font-bold text-sm h-[38px] flex items-center">
                <i class="fas fa-print mr-2"></i> Print / Save PDF
            </button>
            <a href="restock_history.php" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm h-[38px] flex items-center">Reset</a>
        </div>
    </form>
    <script>
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
        const quickRange = document.querySelector('select[onchange^="applyQuickDate"]');
        if(quickRange) quickRange.value = '';
        renderTable();
    }
    </script>
</div>

<div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-teal-700 text-white text-xs uppercase tracking-widest font-black">
                    <th class="p-6 w-12 text-center">Sno#</th>
                    <th class="p-6">Date</th>
                    <th class="p-6">Product</th>
                    <th class="p-6">Expiry & Remarks</th>
                    <th class="p-6">Qty Added</th>
                    <th class="p-6">Purchase Cost</th>
                    <th class="p-6">Selling Rate</th>
                    <th class="p-6">Dealer / Supplier</th>
                    <th class="p-6 text-right">Paid Amount</th>
                    <th class="p-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 italic-rows" id="restockBody">
                <!-- JS Rendered -->
            </tbody>
        </table>
    </div>
</div>

<style>
    .italic-rows tr td { font-style: normal; }
    .italic-rows .line-through { font-style: italic; }
</style>

<!-- Printable Area -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #0f766e; margin: 0; font-size: 28px;">Fashion Shines</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Inventory Restock History Report</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333;">Summary Report</h2>
                <p style="color: #888; margin: 5px 0 0 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #0f766e; color: #fff;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Date</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Product</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Qty Added</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Purchase Cost</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Dealer</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Paid Amount</th>
                </tr>
            </thead>
            <tbody id="printBody">
                <!-- JS Populated -->
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="5" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 11px;">Total amount paid in this period:</td>
                    <td id="printFooterTotal" style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #0f766e; font-size: 16px;">Rs. 0</td>
                </tr>
            </tfoot>
        </table>

        <div style="border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; text-align: center; font-size: 10px; color: #888;">
            <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php'; 
echo '</main></div>';
?>
<form id="deleteRestockForm" action="../actions/delete_restock.php" method="POST" class="hidden">
    <input type="hidden" name="restock_id" id="deleteRestockId">
</form>

<script>
    const allRestocks = <?= json_encode($restocks) ?>;

    const formatCurrency = (amount) => {
        return 'Rs.' + new Intl.NumberFormat('en-US').format(amount);
    };

    function renderTable() {
        const dateFromVal = document.getElementById('dateFrom').value;
        const dateToVal = document.getElementById('dateTo').value;
        
        let filtered = allRestocks.filter(r => {
            const rDate = (r.date || "").substring(0, 10);
            if (dateFromVal && rDate < dateFromVal) return false;
            if (dateToVal && rDate > dateToVal) return false;
            return true;
        });

        const body = document.getElementById('restockBody');
        const printBody = document.getElementById('printBody');
        let html = '';
        let printHtml = '';
        let totalPaid = 0;

        if (filtered.length === 0) {
            html = '<tr><td colspan="10" class="p-20 text-center text-gray-300"><i class="fas fa-history text-6xl mb-4 opacity-20"></i><p class="font-medium">No restock history found for this period.</p></td></tr>';
            printHtml = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: #999;">No records found.</td></tr>';
        } else {
            filtered.forEach((log, index) => {
                const sn = index + 1;
                const paid = parseFloat(log.amount_paid || 0);
                totalPaid += paid;
                
                const dateDisplay = log.date ? new Date(log.date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'}) : '-';
                const timeDisplay = log.created_at ? new Date(log.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';

                const expiryHtml = log.expiry_date ? 
                    `<div class="text-xs text-red-500 font-bold mb-1"><i class="far fa-calendar-alt mr-1"></i> Exp: ${new Date(log.expiry_date).toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'})}</div>` : 
                    `<div class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1 opacity-60">No Expiry</div>`;
                
                const remarksHtml = log.remarks ? 
                    `<div class="text-xs text-gray-600 bg-gray-50 p-1.5 rounded border border-gray-100 inline-block max-w-[200px] truncate" title="${log.remarks}"><i class="fas fa-sticky-note mr-1 text-teal-500"></i> ${log.remarks}</div>` : 
                    '';

                html += `<tr class="hover:bg-teal-50/30 transition">
                    <td class="p-6 text-center text-xs font-mono text-gray-400 italic">${sn}</td>
                    <td class="p-6 text-sm font-medium text-gray-500 font-mono">${dateDisplay}<br><span class="text-[10px] opacity-50">${timeDisplay}</span></td>
                    <td class="p-6">
                        <span class="font-bold text-gray-800 block">${log.product_name || 'Unknown Product'}</span>
                        <span class="text-[10px] text-gray-400 font-bold uppercase">ID: ${log.product_id}</span>
                    </td>
                    <td class="p-6">${expiryHtml}${remarksHtml}</td>
                    <td class="p-6"><span class="px-3 py-1 bg-green-50 text-green-600 rounded-full font-bold text-sm shadow-sm">+${log.quantity}</span></td>
                    <td class="p-6">
                        <div class="text-gray-800 font-bold text-sm">${formatCurrency(parseFloat(log.new_buy_price || 0))}</div>
                        <div class="text-[10px] text-gray-400 line-through italic">Prev: ${formatCurrency(parseFloat(log.old_buy_price || 0))}</div>
                    </td>
                    <td class="p-6 text-teal-600 font-bold text-sm">${formatCurrency(parseFloat(log.new_sell_price || 0))}</td>
                    <td class="p-6">${log.dealer_name ? `<span class="flex items-center gap-2 text-sm font-semibold text-gray-700"><i class="fas fa-truck text-teal-400"></i>${log.dealer_name}</span>` : '<span class="text-xs text-gray-400 italic">Self Stock</span>'}</td>
                    <td class="p-6 text-right">${log.amount_paid !== '' ? `<span class="font-black text-gray-800">${formatCurrency(paid)}</span>` : '<span class="text-gray-300 font-bold">-</span>'}</td>
                    <td class="p-6 text-center"><button onclick="confirmDeleteRestock(${log.id})" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition"><i class="fas fa-trash-alt"></i></button></td>
                </tr>`;

                printHtml += `<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${dateDisplay}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; font-weight: 600;">${log.product_name}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: center;">${log.quantity}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${formatCurrency(parseFloat(log.new_buy_price))}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${log.dealer_name || 'Self'}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right; font-weight: bold;">${formatCurrency(paid)}</td>
                </tr>`;
            });
        }

        body.innerHTML = html;
        printBody.innerHTML = printHtml;
        document.getElementById('printFooterTotal').innerText = formatCurrency(totalPaid);
    }

    function printReport() {
        const content = document.getElementById('printableArea').innerHTML;
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Restock Report</title><link rel="icon" type="image/png" href="../assets/img/favicon.png"><style>body { font-family: sans-serif; }</style></head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
    }

    function confirmDeleteRestock(id) {
        showConfirm('Are you sure you want to revert this restock? \n\nThis will:\n1. Remove the added quantity from stock.\n2. Revert the Average Buy Price.\n3. Delete any linked Dealer Transactions.', () => {
            document.getElementById('deleteRestockId').value = id;
            document.getElementById('deleteRestockForm').submit();
        }, 'Revert Restock');
    }

    document.addEventListener('DOMContentLoaded', renderTable);
</script>
</body></html>
