<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Dashboard";
include 'includes/header.php';

$products = readCSV('products');
$sales = readCSV('sales');
$customers = readCSV('customers');

$total_products = count($products);
$low_stock = 0;
foreach($products as $p) {
    if ($p['stock_quantity'] < 10) $low_stock++;
}

$today_sales = 0;
$today_str = date('Y-m-d');
foreach($sales as $s) {
    if (strpos($s['sale_date'], $today_str) === 0) {
        $today_sales += $s['total_amount'];
    }
}
?>

<?php if ($low_stock > 0): ?>
<div class="mb-8 p-6 bg-red-50 border border-red-100 rounded-[2.5rem] flex flex-col md:flex-row items-start md:items-center justify-between group animate-pulse shadow-sm shadow-red-500/5 gap-4">
    <div class="flex items-center gap-6">
        <div class="w-14 h-14 bg-red-600 text-white rounded-2xl flex items-center justify-center shadow-lg transform group-hover:rotate-12 transition-transform shrink-0">
            <i class="fas fa-exclamation-triangle text-xl"></i>
        </div>
        <div>
            <h4 class="text-lg font-bold text-red-900">Critical Stock Warning</h4>
            <p class="text-red-700/80 text-sm font-medium">There are <strong><?= $low_stock ?> items</strong> currently running below the safety threshold (10 units).</p>
        </div>
    </div>
    <a href="pages/inventory.php?filter=low" class="px-8 py-3 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition shadow-lg shadow-red-900/10 active:scale-95 text-sm uppercase tracking-wide w-full md:w-auto text-center">
        Take Action Now
    </a>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-8">
    <!-- Stat Card 1 -->
    <a href="pages/inventory.php" class="bg-white rounded-3xl shadow-sm p-6 border border-gray-100 glass card-hover transition-all duration-300 group">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-teal-500 rounded-2xl text-white shadow-md shadow-teal-500/10 group-hover:bg-teal-600 transition-colors">
                <i class="fas fa-box-open text-lg"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Inventory</span>
        </div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-tight">Total Products</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= $total_products ?></p>
    </a>

    <!-- Stat Card 2 -->
    <a href="pages/inventory.php?filter=low" class="bg-white rounded-3xl shadow-sm p-6 border border-gray-100 glass card-hover transition-all duration-300 group">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-red-500 rounded-2xl text-white shadow-md shadow-red-500/10 group-hover:bg-red-600 transition-colors">
                <i class="fas fa-exclamation-triangle text-lg"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-red-600 bg-red-50 px-3 py-1 rounded-full">Critical</span>
        </div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-tight">Low Stock Alerts</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= $low_stock ?></p>
    </a>

    <!-- Stat Card 3 -->
    <a href="pages/sales_history.php?date=<?= date('Y-m-d') ?>" class="bg-white rounded-3xl shadow-sm p-6 border border-gray-100 glass card-hover transition-all duration-300 group">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-green-500 rounded-2xl text-white shadow-md shadow-green-500/10 group-hover:bg-green-600 transition-colors">
                <i class="fas fa-chart-line text-lg"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-green-600 bg-green-50 px-3 py-1 rounded-full">Real-time</span>
        </div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-tight">Sales Today</p>
        <p class="text-2xl font-bold text-gray-800 mt-1 truncate"><?= formatCurrency($today_sales) ?></p>
    </a>

     <!-- Stat Card 4 -->
     <a href="pages/customers.php" class="bg-white rounded-3xl shadow-sm p-6 border border-gray-100 glass card-hover transition-all duration-300 group">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-purple-500 rounded-2xl text-white shadow-md shadow-purple-500/10 group-hover:bg-purple-600 transition-colors">
                <i class="fas fa-users text-lg"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 bg-purple-50 px-3 py-1 rounded-full">People</span>
        </div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-tight">Total Customers</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= count($customers) ?></p>
    </a>

    <!-- Stat Card 5 -->
    <a href="pages/restock_history.php" class="bg-white rounded-3xl shadow-sm p-6 border border-gray-100 glass card-hover transition-all duration-300 group">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-orange-500 rounded-2xl text-white shadow-md shadow-orange-500/10 group-hover:bg-orange-600 transition-colors">
                <i class="fas fa-history text-lg"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-orange-600 bg-orange-50 px-3 py-1 rounded-full">Logs</span>
        </div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-tight">Restock activities</p>
        <p class="text-3xl font-bold text-gray-800 mt-1"><?= count(readCSV('restocks')) ?></p>
    </a>

    <!-- Stat Card 6 (Quick Restock) -->
    <a href="pages/quick_restock.php" class="bg-white rounded-3xl shadow-sm p-6 border border-gray-100 glass card-hover transition-all duration-300 group ring-2 ring-orange-100">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-orange-600 rounded-2xl text-white shadow-md shadow-orange-600/10 group-hover:bg-orange-700 transition-colors">
                <i class="fas fa-boxes text-lg"></i>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-orange-700 bg-orange-50 px-3 py-1 rounded-full">Action</span>
        </div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-tight">Quick Restock</p>
        <p class="text-xs font-bold text-gray-800 mt-1">Add Stock Now</p>
    </a>
</div>


<!-- Additional Quick Access Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Reports & Analytics Card -->
    <a href="pages/reports.php" class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-3xl shadow-lg p-6 border border-blue-400 hover:scale-105 transition-all duration-300 group text-white">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm">
                <i class="fas fa-chart-pie text-2xl"></i>
            </div>
            <i class="fas fa-arrow-right text-white/50 group-hover:text-white group-hover:translate-x-1 transition-all"></i>
        </div>
        <p class="text-sm font-bold uppercase tracking-wide opacity-90">Reports & Analytics</p>
        <p class="text-xs mt-2 opacity-75">View detailed insights & financial reports</p>
    </a>

    <!-- Categories Management Card -->
    <a href="pages/categories.php" class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-3xl shadow-lg p-6 border border-indigo-400 hover:scale-105 transition-all duration-300 group text-white">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm">
                <i class="fas fa-tags text-2xl"></i>
            </div>
            <i class="fas fa-arrow-right text-white/50 group-hover:text-white group-hover:translate-x-1 transition-all"></i>
        </div>
        <p class="text-sm font-bold uppercase tracking-wide opacity-90">Categories</p>
        <p class="text-xs mt-2 opacity-75">Manage product categories</p>
    </a>

    <!-- Units Management Card -->
    <a href="pages/units.php" class="bg-gradient-to-br from-pink-500 to-pink-600 rounded-3xl shadow-lg p-6 border border-pink-400 hover:scale-105 transition-all duration-300 group text-white">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm">
                <i class="fas fa-balance-scale text-2xl"></i>
            </div>
            <i class="fas fa-arrow-right text-white/50 group-hover:text-white group-hover:translate-x-1 transition-all"></i>
        </div>
        <p class="text-sm font-bold uppercase tracking-wide opacity-90">Units</p>
        <p class="text-xs mt-2 opacity-75">Manage measurement units</p>
    </a>

    <!-- Ledgers Card -->
    <a href="pages/customer_ledger.php" class="bg-gradient-to-br from-violet-500 to-violet-600 rounded-3xl shadow-lg p-6 border border-violet-400 hover:scale-105 transition-all duration-300 group text-white">
        <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm">
                <i class="fas fa-file-invoice-dollar text-2xl"></i>
            </div>
            <i class="fas fa-arrow-right text-white/50 group-hover:text-white group-hover:translate-x-1 transition-all"></i>
        </div>
        <p class="text-sm font-bold uppercase tracking-wide opacity-90">Ledgers</p>
        <p class="text-xs mt-2 opacity-75">Customer & Dealer accounts</p>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Quick Actions -->
    <div class="bg-white rounded-3xl shadow-sm p-8 glass border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-bolt text-yellow-500 mr-3"></i> Quick Actions
        </h3>
        <div class="grid grid-cols-2 gap-4">
            <a href="pages/pos.php" class="flex flex-col items-center justify-center p-6 bg-teal-50 rounded-2xl hover:bg-teal-600 hover:text-white transition-all duration-300 border border-teal-100 group shadow-sm">
                <i class="fas fa-cash-register text-2xl text-teal-600 mb-3 group-hover:text-white transition-colors"></i>
                <span class="font-bold text-sm">New Sale</span>
            </a>
            <a href="pages/inventory.php" class="flex flex-col items-center justify-center p-6 bg-blue-50 rounded-2xl hover:bg-blue-600 hover:text-white transition-all duration-300 border border-blue-100 group shadow-sm">
                <i class="fas fa-plus-circle text-2xl text-blue-600 mb-3 group-hover:text-white transition-colors"></i>
                <span class="font-bold text-sm">Add Product</span>
            </a>
            <a href="pages/dealers.php" class="flex flex-col items-center justify-center p-6 bg-amber-50 rounded-2xl hover:bg-amber-600 hover:text-white transition-all duration-300 border border-amber-100 group shadow-sm">
                <i class="fas fa-truck-loading text-2xl text-amber-600 mb-3 group-hover:text-white transition-colors"></i>
                <span class="font-bold text-sm">Dealer Restock</span>
            </a>
            <a href="pages/customers.php" class="flex flex-col items-center justify-center p-6 bg-purple-50 rounded-2xl hover:bg-purple-600 hover:text-white transition-all duration-300 border border-purple-100 group shadow-sm">
                <i class="fas fa-user-plus text-2xl text-purple-600 mb-3 group-hover:text-white transition-colors"></i>
                <span class="font-bold text-sm">Add Customer</span>
            </a>
            <a href="pages/inventory.php" class="flex flex-col items-center justify-center p-6 bg-orange-50 rounded-2xl hover:bg-orange-600 hover:text-white transition-all duration-300 border border-orange-100 group shadow-sm">
                <i class="fas fa-plus-circle text-2xl text-orange-600 mb-3 group-hover:text-white transition-colors"></i>
                <span class="font-bold text-sm">Restock Inventory</span>
            </a>
            <a href="pages/backup_restore.php" class="flex flex-col items-center justify-center p-6 bg-zinc-50 rounded-2xl hover:bg-zinc-600 hover:text-white transition-all duration-300 border border-zinc-100 group shadow-sm">
                <i class="fas fa-database text-2xl text-zinc-600 mb-3 group-hover:text-white transition-colors"></i>
                <span class="font-bold text-sm">Backup & Restore</span>
            </a>
        </div>
    </div>

    <!-- System Status -->
    <div class="bg-white rounded-3xl shadow-sm p-8 glass border border-gray-100">
         <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-server text-teal-500 mr-3"></i> System Status
         </h3>
         <div class="space-y-4">
            <p class="text-gray-500 text-sm font-medium">Welcome back to <span class="text-teal-600 font-bold">DEWAAN</span>. Your system is running smoothly.</p>
            <div class="bg-gray-50/50 p-4 rounded-2xl border border-gray-100 flex items-center shadow-inner">
                <div class="w-10 h-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-teal-500 mr-4">
                    <i class="fas fa-user-shield text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Active Operator</p>
                    <p class="text-base font-bold text-gray-800"><?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
            </div>
            <div class="bg-gray-50/50 p-4 rounded-2xl border border-gray-100 flex items-center shadow-inner">
                 <div class="w-10 h-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-green-500 mr-4">
                    <i class="fas fa-database text-sm"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Architecture</p>
                    <p class="text-sm font-semibold text-gray-700">Excel / CSV Mode</p>
                </div>
            </div>
         </div>
    </div>
</div>

<?php 
include 'includes/footer.php';
echo '</main></div></body></html>'; 
?>
