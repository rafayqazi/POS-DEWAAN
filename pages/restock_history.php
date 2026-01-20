<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Restock History";
include '../includes/header.php';

$restocks = readCSV('restocks');

// Filter Logic
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to = $_GET['to'];
    $restocks = array_filter($restocks, function($r) use ($from, $to) {
        if (empty($r['date'])) return false;
        return $r['date'] >= $from && $r['date'] <= $to;
    });
}

// Sort by ID descending to see newest first
usort($restocks, function($a, $b) {
    return (int)$b['id'] - (int)$a['id'];
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
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="last_90">Last 90 Days</option>
                <option value="last_year">Last 1 Year</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">From Date</label>
            <input type="date" name="from" id="dateFrom" value="<?= $_GET['from'] ?? '' ?>" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" name="to" id="dateTo" value="<?= $_GET['to'] ?? '' ?>" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none">
        </div>
        <button type="submit" class="bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 transition shadow-md font-bold text-sm h-[38px]">
            <i class="fas fa-filter mr-1"></i> Filter
        </button>
        <?php if(isset($_GET['from']) || isset($_GET['to'])): ?>
            <a href="restock_history.php" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm h-[38px] flex items-center">Reset</a>
        <?php endif; ?>
    </form>
    <script>
    function applyQuickDate(type) {
        const today = new Date();
        let start, end;
        
        if (type === 'this_month') {
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
    }
    </script>
</div>

<div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-orange-600 text-white text-xs uppercase tracking-widest font-black">
                    <th class="p-6">Date</th>
                    <th class="p-6">Product</th>
                    <th class="p-6">Qty Added</th>
                    <th class="p-6">Purchase Cost</th>
                    <th class="p-6">Selling Rate</th>
                    <th class="p-6">Dealer / Supplier</th>
                    <th class="p-6 text-right">Paid Amount</th>
                    <th class="p-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 italic-rows">
                <?php if (count($restocks) > 0): ?>
                    <?php foreach ($restocks as $log): ?>
                        <tr class="hover:bg-orange-50/30 transition">
                            <td class="p-6 text-sm font-medium text-gray-500 font-mono">
                                <?php if (!empty($log['date'])): ?>
                                    <?= date('d M, Y', strtotime($log['date'])) ?>
                                    <br><span class="text-[10px] opacity-50"><?= !empty($log['created_at']) ? date('H:i', strtotime($log['created_at'])) : '' ?></span>
                                <?php else: ?>
                                    <span class="text-gray-300">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-6">
                                <span class="font-bold text-gray-800 block"><?= htmlspecialchars($log['product_name'] ?? 'Unknown Product') ?></span>
                                <span class="text-[10px] text-gray-400 font-bold uppercase">ID: <?= $log['product_id'] ?></span>
                            </td>
                            <td class="p-6">
                                <span class="px-3 py-1 bg-green-50 text-green-600 rounded-full font-bold text-sm shadow-sm">
                                    +<?= $log['quantity'] ?>
                                </span>
                            </td>
                            <td class="p-6">
                                <div class="text-gray-800 font-bold text-sm"><?= formatCurrency((float)$log['new_buy_price']) ?></div>
                                <div class="text-[10px] text-gray-400 line-through italic">Prev: <?= formatCurrency((float)$log['old_buy_price']) ?></div>
                            </td>
                            <td class="p-6">
                                <div class="text-teal-600 font-bold text-sm"><?= formatCurrency((float)$log['new_sell_price']) ?></div>
                                <div class="text-[10px] text-gray-400 line-through italic">Prev: <?= formatCurrency((float)$log['old_sell_price']) ?></div>
                            </td>
                            <td class="p-6">
                                <?php if (!empty($log['dealer_name'])): ?>
                                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                        <i class="fas fa-truck text-orange-400"></i>
                                        <?= htmlspecialchars($log['dealer_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 italic">Self Stock / Unknown</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-6 text-right">
                                <?php if (isset($log['amount_paid']) && $log['amount_paid'] !== ''): ?>
                                    <span class="font-black text-gray-800"><?= formatCurrency((float)$log['amount_paid']) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-300 font-bold">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-6 text-center">
                                <button onclick="confirmDeleteRestock(<?= $log['id'] ?>)" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition" title="Revert this Restock">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="p-20 text-center text-gray-300">
                            <i class="fas fa-history text-6xl mb-4 opacity-20"></i>
                            <p class="font-medium">No restock history found yet.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .italic-rows tr td { font-style: normal; }
    .italic-rows .line-through { font-style: italic; }
</style>

<?php 
include '../includes/footer.php'; 
echo '</main></div>';

// Add the delete form at the bottom
?>
<form id="deleteRestockForm" action="../actions/delete_restock.php" method="POST" class="hidden">
    <input type="hidden" name="restock_id" id="deleteRestockId">
</form>

<script>
    function confirmDeleteRestock(id) {
        showConfirm('Are you sure you want to revert this restock? \n\nThis will:\n1. Remove the added quantity from stock.\n2. Revert the Average Buy Price.\n3. Delete any linked Dealer Transactions.', () => {
            document.getElementById('deleteRestockId').value = id;
            document.getElementById('deleteRestockForm').submit();
        }, 'Revert Restock');
    }
</script>
</body></html>
