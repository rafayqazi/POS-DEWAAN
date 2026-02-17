<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

requireLogin();
sendTrackingHeartbeat();

$pageTitle = "Dashboard";
include 'includes/header.php';

$products = filterDataByRole('products', readCSV('products'));
$sales = filterDataByRole('sales', readCSV('sales'));
$customers = filterDataByRole('customers', readCSV('customers'));

// Customer-Specific Aggregation
$customer_total_balance = 0;
$customer_orders_count = 0;
if (isRole('Customer')) {
    $related_id = getUserRelatedId();
    $customer_orders_count = count($sales); // Already filtered by role
    
    $cust_txns = filterDataByRole('customer_transactions', readCSV('customer_transactions'));
    foreach ($cust_txns as $tx) {
        $customer_total_balance += (float)$tx['debit'] - (float)$tx['credit'];
    }
}

// 1. Core Data Aggregation
$low_stock = 0;
foreach($products as $p) {
    if ($p['stock_quantity'] < 10) $low_stock++;
}

// Debt & Recovery Initialization
$all_txns = filterDataByRole('customer_transactions', readCSV('customer_transactions'));
$cust_map = [];
foreach($customers as $c) $cust_map[$c['id']] = $c['name'];

$balances = [];
$customer_due_dates = []; 
foreach ($all_txns as $tx) {
    if(!isset($balances[$tx['customer_id']])) $balances[$tx['customer_id']] = 0;
    $balances[$tx['customer_id']] += (float)$tx['debit'] - (float)$tx['credit'];
    
    if (!empty($tx['due_date'])) {
        if (!isset($customer_due_dates[$tx['customer_id']]) || $tx['due_date'] < $customer_due_dates[$tx['customer_id']]) {
            $customer_due_dates[$tx['customer_id']] = $tx['due_date'];
        }
    }
}

$rec_notify_days = (int)getSetting('recovery_notify_days', '7');
$rec_threshold = date('Y-m-d', strtotime("+$rec_notify_days days"));
$due_customers_alert = []; 
$all_debtors = [];        
foreach ($balances as $cid => $bal) {
    if ($bal > 1) {
        $name = $cust_map[$cid] ?? 'Unknown';
        $due_date = $customer_due_dates[$cid] ?? '';
        $debtor_data = ['id' => $cid, 'name' => $name, 'balance' => $bal, 'due_date' => $due_date];
        $all_debtors[] = $debtor_data;
        if (!empty($due_date) && $due_date <= $rec_threshold) {
            $due_customers_alert[] = $debtor_data;
        }
    }
}

// Sort Debtors
usort($all_debtors, function($a, $b) {
    if (empty($a['due_date']) && empty($b['due_date'])) return 0;
    if (empty($a['due_date'])) return 1;
    if (empty($b['due_date'])) return -1;
    return strtotime($a['due_date']) - strtotime($b['due_date']);
});

// 2. Calculations for high-impact metrics
$today_str = date('Y-m-d');
$thirty_days_ago_str = date('Y-m-d', strtotime('-30 days'));
$today_sales = 0;
$today_profit = 0;
$monthly_sales = 0;
$sale_items = readCSV('sale_items');

foreach($sales as $s) {
    if (strpos($s['sale_date'], $today_str) === 0) {
        $today_sales += (float)$s['total_amount'];
        // Calculate profit for this sale
        foreach($sale_items as $si) {
            if ($si['sale_id'] == $s['id']) {
                $qty = (float)$si['quantity'] - (float)($si['returned_qty'] ?? 0);
                if ($qty > 0) {
                    $buy_p = (float)($si['avg_buy_price'] ?: $si['buy_price']);
                    $sell_p = (float)$si['price_per_unit'];
                    $today_profit += ($sell_p - $buy_p) * $qty;
                }
            }
        }
    }
    if ($s['sale_date'] >= $thirty_days_ago_str) {
        $monthly_sales += (float)$s['total_amount'];
    }
}

// Total Debtors Calculation
$total_customer_debt = 0;
foreach ($balances as $bal) {
    if ($bal > 0) $total_customer_debt += $bal;
}

// Dealer Debt Calculation
$dealer_txns = readCSV('dealer_transactions');
$dealer_balances = [];
foreach ($dealer_txns as $tx) {
    if(!isset($dealer_balances[$tx['dealer_id']])) $dealer_balances[$tx['dealer_id']] = 0;
    $dealer_balances[$tx['dealer_id']] += (float)$tx['debit'] - (float)$tx['credit'];
}
$total_dealer_debt = 0;
foreach ($dealer_balances as $bal) {
    if ($bal > 0) $total_dealer_debt += $bal;
}

// 2. Automatic Update Check (once per session login)
if (isset($_SESSION['check_updates']) && $_SESSION['check_updates']) {
    $update_status = getUpdateStatus();
    $_SESSION['update_available'] = $update_status['available'];
    $_SESSION['check_updates'] = false;
}

// 3. Expiry Notifications Logic
$notify_days = (int)getSetting('expiry_notify_days', '7');
$expiry_threshold = date('Y-m-d', strtotime("+$notify_days days"));
$expiring_products = [];
foreach ($products as $p) {
    if (!empty($p['expiry_date']) && $p['expiry_date'] <= $expiry_threshold && $p['expiry_date'] >= date('Y-m-d')) {
        $expiring_products[] = $p;
    }
}
$expiring_count = count($expiring_products);

// 4. Recovery Notifications Logic
$recovery_alert_count = count($due_customers_alert);
$dismissed = $_SESSION['dismissed_alerts'] ?? [];

// Final Update Status check for lockdown & countdown
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $update_status = ['available' => false, 'overdue' => false, 'time_left' => 0];
} else {
    $update_status = getUpdateStatus();
}
$_SESSION['update_overdue'] = $update_status['available'] && $update_status['overdue'];
?>

<script>
async function dismissAlert(alertId, element) {
    try {
        const response = await fetch('actions/dismiss_alert.php', {
            method: 'POST',
            body: new URLSearchParams({ 'alert_id': alertId })
        });
        if (response.ok) {
            element.closest('.alert-card').remove();
        }
    } catch (e) {
        console.error("Failed to dismiss alert", e);
    }
}
</script>

<?php if (hasPermission('view_business_alerts')): ?>
<?php if ($low_stock > 0 && !in_array('low_stock', $dismissed)): ?>
<div class="alert-card mb-6 p-6 bg-red-50 border border-red-100 rounded-[2.5rem] flex flex-col md:flex-row items-start md:items-center justify-between group animate-pulse shadow-sm shadow-red-500/5 gap-4 relative">
    <button onclick="dismissAlert('low_stock', this)" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center bg-white rounded-full text-red-400 hover:text-white hover:bg-red-500 shadow-sm transition-all text-lg cursor-pointer z-10">
        <i class="fas fa-times"></i>
    </button>
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

<?php if ($expiring_count > 0 && !in_array('expiry', $dismissed)): ?>
<div class="alert-card mb-6 p-6 bg-amber-50 border border-amber-100 rounded-[2.5rem] flex flex-col md:flex-row items-start md:items-center justify-between group shadow-sm shadow-amber-500/5 gap-4 relative">
    <button onclick="dismissAlert('expiry', this)" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center bg-white rounded-full text-amber-400 hover:text-white hover:bg-amber-500 shadow-sm transition-all text-lg cursor-pointer z-10">
        <i class="fas fa-times"></i>
    </button>
    <div class="flex items-center gap-6">

        <div class="w-14 h-14 bg-amber-500 text-white rounded-2xl flex items-center justify-center shadow-lg transform group-hover:-rotate-12 transition-transform shrink-0">
            <i class="fas fa-calendar-times text-xl"></i>
        </div>
        <div>
            <h4 class="text-lg font-bold text-amber-900">Expiry Alert</h4>
            <p class="text-amber-700/80 text-sm font-medium"><strong><?= $expiring_count ?> products</strong> are expiring within the next <?= $notify_days ?> days.</p>
        </div>
    </div>
    <a href="pages/inventory.php?filter=expiring" class="px-8 py-3 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition shadow-lg shadow-amber-900/10 active:scale-95 text-sm uppercase tracking-wide w-full md:w-auto text-center">
        View Expiring List
    </a>
</div>
<?php endif; ?>

<?php if ($recovery_alert_count > 0 && !in_array('recovery', $dismissed)): ?>
<div class="alert-card mb-6 p-6 bg-orange-50 border border-orange-100 rounded-[2.5rem] flex flex-col md:flex-row items-start md:items-center justify-between group shadow-sm shadow-orange-500/5 gap-4 relative">
    <button onclick="dismissAlert('recovery', this)" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center bg-white rounded-full text-orange-400 hover:text-white hover:bg-orange-500 shadow-sm transition-all text-lg cursor-pointer z-10">
        <i class="fas fa-times"></i>
    </button>
    <div class="flex items-center gap-6">

        <div class="w-14 h-14 bg-orange-600 text-white rounded-2xl flex items-center justify-center shadow-lg transform group-hover:rotate-12 transition-transform shrink-0">
            <i class="fas fa-hand-holding-usd text-xl"></i>
        </div>
        <div>
            <h4 class="text-lg font-bold text-orange-900">Payment Recovery Alert</h4>
            <p class="text-orange-700/80 text-sm font-medium">
                <?php if ($recovery_alert_count <= 3): ?>
                    <?php 
                    $msgs = [];
                    foreach($due_customers_alert as $dc) {
                        $diff = strtotime($dc['due_date']) - strtotime(date('Y-m-d'));
                        $days = round($diff / (60 * 60 * 24));
                        $day_text = ($days == 0) ? "today" : (($days < 0) ? abs($days) . " days overdue" : $days . " days left");
                        $msgs[] = "<strong>" . htmlspecialchars($dc['name']) . "</strong> ($day_text)";
                    }
                    echo implode(", ", $msgs);
                    ?>
                <?php else: ?>
                    <strong><?= $recovery_alert_count ?> customers</strong> have payments due soon or overdue.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="flex gap-2 w-full md:w-auto">
        <button onclick="openDebtorsModal()" class="px-8 py-3 bg-orange-600 text-white rounded-xl font-bold hover:bg-orange-700 transition shadow-lg shadow-orange-900/10 active:scale-95 text-sm uppercase tracking-wide w-full md:w-auto text-center">
            View Debtors
        </button>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>


<?php if (isset($update_status['available']) && $update_status['available']): ?>
<div class="mb-8 p-6 bg-teal-50 border border-teal-100 rounded-[2.5rem] flex flex-col md:flex-row items-start md:items-center justify-between group shadow-sm shadow-teal-500/5 gap-4 border-l-8 border-l-teal-500">
    <div class="flex items-center gap-6">
        <div class="w-14 h-14 bg-teal-600 text-white rounded-2xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform shrink-0">
            <i class="fas fa-cloud-download-alt text-xl"></i>
        </div>
        <div>
            <h4 class="text-lg font-bold text-teal-900">Software Update Available</h4>
            <p class="text-teal-700/80 text-sm font-medium">A new version of Fashion Shines POS is ready. Update within the next <strong id="updateCountdown" class="text-teal-900 font-black">--:--:--</strong> to avoid system lockout.</p>
        </div>
    </div>
    <div class="flex gap-2 w-full md:w-auto">
        <button onclick="startSeamlessUpdate()" class="px-8 py-3 bg-teal-600 text-white rounded-xl font-bold hover:bg-teal-700 transition shadow-lg shadow-teal-900/10 active:scale-95 text-sm uppercase tracking-wide w-full md:w-auto text-center flex items-center justify-center gap-2">
            <i class="fas fa-cloud-download-alt"></i> Update Now
        </button>
    </div>
</div>

<script>
function startUpdateCountdown(timeLeft) {
    const timer = document.getElementById('updateCountdown');
    if (!timer) return;

    function update() {
        // Only trigger reload if explicitly overdue AND we are sure valid timeLeft was passed
        if (timeLeft <= 0) {
            timer.innerText = "OVERDUE";
            // Check if we are already in a refresh loop before reloading
            // Simple prevention: only reload if page was loaded > 5 sec ago?
            // Better: trust the PHP logic. If PHP rendered this, it means it thinks it's NOT overdue.
            // So if JS thinks it IS overdue, it just means time passed.
            // Reloading is correct, but let's add a small delay to prevent rapid-fire loops if timestamps are skewed.
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        const hours = Math.floor(timeLeft / 3600);
        const mins = Math.floor((timeLeft % 3600) / 60);
        const secs = timeLeft % 60;
        
        timer.innerText = `${hours}h ${mins}m ${secs}s`;
        timeLeft--;
        setTimeout(update, 1000);
    }
    update();
}
document.addEventListener('DOMContentLoaded', () => {
    // Ensure we pass a number, default to a high number if missing to prevent instant loop
    // But logically, if available is true, time_left must be set.
    startUpdateCountdown(<?= isset($update_status['time_left']) ? (int)$update_status['time_left'] : 0 ?>);
});
</script>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-12">
    <?php if (hasPermission('view_sensitive_stats')): ?>
    <!-- Today's Sales Card -->
    <div class="bg-gradient-to-br from-teal-600 to-teal-800 rounded-[2rem] shadow-xl p-6 border border-white/10 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <i class="fas fa-chart-line text-6xl text-white"></i>
        </div>
        <div class="relative z-10">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-white/90 mb-2">Today's Revenue <br> <span class="text-[9px] lowercase opacity-80">(Aaj ki Kul Sale)</span></p>
            <h3 class="text-3xl font-black text-white mb-1"><?= formatCurrency($today_sales) ?></h3>
            <div class="flex items-center gap-2 mt-4">
                <span class="px-3 py-1 bg-white/20 rounded-full text-[10px] font-bold text-white backdrop-blur-md">
                    <i class="fas fa-calendar-day mr-1"></i> Today
                </span>
            </div>
        </div>
    </div>

    <!-- Monthly Sales Card -->
    <a href="pages/sales_history.php?f_type=30days" class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-[2rem] shadow-xl p-6 border border-white/10 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <i class="fas fa-calendar-alt text-6xl text-white"></i>
        </div>
        <div class="relative z-10">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-white/90 mb-2">30-Day Revenue <br> <span class="text-[9px] lowercase opacity-80">(Pichle 30 Din ki Sale)</span></p>
            <h3 class="text-3xl font-black text-white mb-1"><?= formatCurrency($monthly_sales) ?></h3>
            <div class="flex items-center gap-2 mt-4 text-white text-[10px] font-bold">
                <i class="fas fa-chart-bar"></i> Total last 30 days
            </div>
        </div>
    </a>

    <!-- Today's Profit Card -->
    <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-[2rem] shadow-xl p-6 border border-white/10 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <i class="fas fa-wallet text-6xl text-white"></i>
        </div>
        <div class="relative z-10">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-white/90 mb-2">Net Profit Today <br> <span class="text-[9px] lowercase opacity-80">(Aaj ka Net Munafa)</span></p>
            <h3 class="text-3xl font-black text-white mb-1"><?= formatCurrency($today_profit) ?></h3>
            <div class="flex items-center gap-2 mt-4 text-white text-[10px] font-bold">
                <i class="fas fa-info-circle"></i> Based on COGS
            </div>
        </div>
    </div>

    <!-- Customer Debt Card -->
    <a href="pages/customers.php" class="bg-gradient-to-br from-orange-500 to-orange-700 rounded-[2rem] shadow-xl p-6 border border-white/10 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <i class="fas fa-users text-6xl text-white"></i>
        </div>
        <div class="relative z-10">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-white/90 mb-2">Total Customer Debt <br> <span class="text-[9px] lowercase opacity-80">(Customer ka Kul Udhaar)</span></p>
            <h3 class="text-3xl font-black text-white mb-1"><?= formatCurrency($total_customer_debt) ?></h3>
            <p class="text-[10px] mt-4 text-white font-bold underline underline-offset-4 group-hover:text-amber-200 transition-colors">View All Debtors <i class="fas fa-arrow-right ml-1"></i></p>
        </div>
    </a>

    <!-- Dealer Debt Card -->
    <a href="pages/dealers.php" class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-[2rem] shadow-xl p-6 border border-white/10 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <i class="fas fa-truck text-6xl text-white"></i>
        </div>
        <div class="relative z-10">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-white/90 mb-2">Total Dealer Payable <br> <span class="text-[9px] lowercase opacity-80">(Dealer ko Kul Deni Rakam)</span></p>
            <h3 class="text-3xl font-black text-white mb-1"><?= formatCurrency($total_dealer_debt) ?></h3>
            <p class="text-[10px] mt-4 text-white font-bold underline underline-offset-4 group-hover:text-indigo-200 transition-colors">View Dealer Ledgers <i class="fas fa-arrow-right ml-1"></i></p>
        </div>
    </a>
    <?php endif; ?>

    <?php if (isRole('Customer')): ?>
    <!-- Customer Dedicated Stats -->
    <div class="md:col-span-3 bg-gradient-to-br from-red-600 to-red-800 rounded-[2rem] shadow-xl p-8 border border-white/10 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-10 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <i class="fas fa-hand-holding-usd text-8xl text-white"></i>
        </div>
        <div class="relative z-10">
            <p class="text-xs font-black uppercase tracking-[0.3em] text-white/80 mb-3">Total Outstanding Balance</p>
            <h3 class="text-5xl font-black text-white mb-2"><?= formatCurrency($customer_total_balance) ?></h3>
            <div class="flex items-center gap-3 mt-6">
                <span class="px-4 py-2 bg-white/20 rounded-full text-[10px] font-black text-white backdrop-blur-md uppercase tracking-widest">
                    <i class="fas fa-info-circle mr-2"></i> Current Ledger Balance
                </span>
                <a href="pages/customer_ledger.php?id=<?= getUserRelatedId() ?>" class="px-4 py-2 bg-white text-red-600 rounded-full text-[10px] font-black uppercase tracking-widest hover:bg-gray-100 transition-colors shadow-lg">
                    View Full Ledger <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="md:col-span-2 bg-gradient-to-br from-purple-600 to-purple-800 rounded-[2rem] shadow-xl p-8 border border-white/10 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-10 opacity-10 group-hover:scale-110 transition-transform duration-500">
            <i class="fas fa-shopping-bag text-8xl text-white"></i>
        </div>
        <div class="relative z-10">
            <p class="text-xs font-black uppercase tracking-[0.3em] text-white/80 mb-3">Total Orders Placed</p>
            <h3 class="text-5xl font-black text-white mb-2"><?= $customer_orders_count ?></h3>
            <div class="flex items-center gap-2 mt-6 text-white/90 text-[10px] font-black uppercase tracking-widest">
                <i class="fas fa-history mr-2"></i> Lifetime History
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Secondary Stats Row -->
<?php if (hasPermission('view_business_alerts')): ?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
    <a href="pages/inventory.php" class="bg-white rounded-[2rem] p-6 shadow-sm border border-gray-100 flex items-center gap-6 group hover:border-teal-200 transition-all">
        <div class="w-16 h-16 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-2xl group-hover:bg-teal-600 group-hover:text-white transition-all shrink-0">
            <i class="fas fa-box"></i>
        </div>
        <div>
            <p class="text-xs font-black text-gray-500 uppercase tracking-widest">Total Products <br> <span class="text-[9px] lowercase opacity-60">(Kul Ashyaa)</span></p>
            <h4 class="text-2xl font-black text-gray-800"><?= count($products) ?></h4>
        </div>
    </a>
    <a href="pages/inventory.php?filter=low" class="bg-white rounded-[2rem] p-6 shadow-sm border border-gray-100 flex items-center gap-6 group hover:border-red-200 transition-all">
        <div class="w-16 h-16 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center text-2xl group-hover:bg-red-600 group-hover:text-white transition-all shrink-0">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div>
            <p class="text-xs font-black text-gray-500 uppercase tracking-widest">Low Stock Items <br> <span class="text-[9px] lowercase opacity-60">(Kam Stock Wali Ashyaa)</span></p>
            <h4 class="text-2xl font-black text-gray-800"><?= $low_stock ?></h4>
        </div>
    </a>
    <a href="pages/quick_restock.php" class="bg-white rounded-[2rem] p-6 shadow-sm border border-gray-100 flex items-center gap-6 group hover:border-amber-200 transition-all">
        <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-2xl group-hover:bg-amber-600 group-hover:text-white transition-all shrink-0">
            <i class="fas fa-plus-square"></i>
        </div>
        <div>
            <p class="text-xs font-black text-gray-500 uppercase tracking-widest">Action <br> <span class="text-[9px] lowercase opacity-60">(Jaldi Stock Mangwayein)</span></p>
            <h4 class="text-xl font-black text-gray-800">Quick Restock</h4>
        </div>
    </a>
</div>
<?php endif; ?>

<?php if (hasPermission('manage_business')): ?>
<!-- Quick Access Grid -->
<h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.3em] mb-6 px-2">Core Operations</h3>
<div class="grid grid-cols-3 md:grid-cols-6 gap-4 mb-12">
    <a href="pages/pos.php" class="flex flex-col items-center justify-center p-6 bg-white rounded-[2rem] shadow-sm border border-gray-100 hover:shadow-xl hover:shadow-teal-900/5 hover:-translate-y-1 transition-all duration-300 group">
        <div class="w-12 h-12 bg-teal-500 text-white rounded-2xl flex items-center justify-center text-xl mb-3 shadow-lg shadow-teal-500/20 group-hover:rotate-6 transition-transform">
            <i class="fas fa-cash-register"></i>
        </div>
        <span class="text-[10px] font-black text-gray-800 uppercase tracking-widest">New Sale</span>
    </a>

    <a href="pages/sales_history.php" class="flex flex-col items-center justify-center p-6 bg-white rounded-[2rem] shadow-sm border border-gray-100 hover:shadow-xl hover:shadow-emerald-900/5 hover:-translate-y-1 transition-all duration-300 group">
        <div class="w-12 h-12 bg-emerald-500 text-white rounded-2xl flex items-center justify-center text-xl mb-3 shadow-lg shadow-emerald-500/20 group-hover:rotate-6 transition-transform">
            <i class="fas fa-history"></i>
        </div>
        <span class="text-[10px] font-black text-gray-800 uppercase tracking-widest">View Sales</span>
    </a>

    <a href="pages/inventory.php" class="flex flex-col items-center justify-center p-6 bg-white rounded-[2rem] shadow-sm border border-gray-100 hover:shadow-xl hover:shadow-blue-900/5 hover:-translate-y-1 transition-all duration-300 group">
        <div class="w-12 h-12 bg-blue-500 text-white rounded-2xl flex items-center justify-center text-xl mb-3 shadow-lg shadow-blue-500/20 group-hover:-rotate-6 transition-transform">
            <i class="fas fa-plus-circle"></i>
        </div>
        <span class="text-[9px] font-black text-gray-800 uppercase tracking-widest text-center">Add Product</span>
    </a>

    <a href="pages/reports.php" class="flex flex-col items-center justify-center p-6 bg-white rounded-[2rem] shadow-sm border border-gray-100 hover:shadow-xl hover:shadow-indigo-900/5 hover:-translate-y-1 transition-all duration-300 group">
        <div class="w-12 h-12 bg-indigo-500 text-white rounded-2xl flex items-center justify-center text-xl mb-3 shadow-lg shadow-indigo-500/20 group-hover:scale-110 transition-transform">
            <i class="fas fa-chart-pie"></i>
        </div>
        <span class="text-[9px] font-black text-gray-800 uppercase tracking-widest">Reports</span>
    </a>

    <a href="pages/customer_ledger.php" class="flex flex-col items-center justify-center p-6 bg-white rounded-[2rem] shadow-sm border border-gray-100 hover:shadow-xl hover:shadow-orange-900/5 hover:-translate-y-1 transition-all duration-300 group">
        <div class="w-12 h-12 bg-orange-500 text-white rounded-2xl flex items-center justify-center text-xl mb-3 shadow-lg shadow-orange-500/20 group-hover:rotate-12 transition-transform">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <span class="text-[9px] font-black text-gray-800 uppercase tracking-widest">Ledgers</span>
    </a>

    <a href="pages/expenses.php" class="flex flex-col items-center justify-center p-6 bg-white rounded-[2rem] shadow-sm border border-gray-100 hover:shadow-xl hover:shadow-red-900/5 hover:-translate-y-1 transition-all duration-300 group">
        <div class="w-12 h-12 bg-red-500 text-white rounded-2xl flex items-center justify-center text-xl mb-3 shadow-lg shadow-red-500/20 group-hover:-rotate-12 transition-transform">
            <i class="fas fa-wallet"></i>
        </div>
        <span class="text-[9px] font-black text-gray-800 uppercase tracking-widest">Expenses</span>
    </a>

    <a href="pages/backup_restore.php" class="flex flex-col items-center justify-center p-6 bg-white rounded-[2rem] shadow-sm border border-gray-100 hover:shadow-xl hover:shadow-zinc-900/5 hover:-translate-y-1 transition-all duration-300 group">
        <div class="w-12 h-12 bg-zinc-600 text-white rounded-2xl flex items-center justify-center text-xl mb-3 shadow-lg shadow-zinc-600/20 group-hover:scale-90 transition-transform">
            <i class="fas fa-database"></i>
        </div>
        <span class="text-[9px] font-black text-gray-800 uppercase tracking-widest text-center">Security</span>
    </a>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- System Status -->
    <div class="bg-white rounded-[2.5rem] shadow-sm p-10 border border-gray-100 relative overflow-hidden group">
        <div class="absolute -top-12 -right-12 w-48 h-48 bg-teal-50 rounded-full blur-3xl group-hover:bg-teal-100 transition-colors"></div>
        <div class="relative z-10">
            <h3 class="text-lg font-black text-gray-800 mb-8 flex items-center">
                <i class="fas fa-server text-teal-500 mr-4"></i> System Intelligence
            </h3>
            <div class="space-y-6">
                <div class="bg-gray-50/80 p-6 rounded-[2rem] border border-gray-100 flex items-center shadow-inner hover:bg-white transition-colors">
                    <div class="w-12 h-12 bg-white rounded-2xl shadow-sm flex items-center justify-center text-teal-500 mr-5">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Active Identity</p>
                        <p class="text-lg font-black text-gray-800"><?= htmlspecialchars($_SESSION['username']) ?></p>
                    </div>
                </div>
                <div class="bg-gray-50/80 p-6 rounded-[2rem] border border-gray-100 flex items-center shadow-inner hover:bg-white transition-colors">
                    <div class="w-12 h-12 bg-white rounded-2xl shadow-sm flex items-center justify-center text-indigo-500 mr-5">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Environment</p>
                        <p class="text-sm font-black text-gray-700 tracking-tight">Enterprise POS v2.1 (Stability Mode)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Insights -->
    <div class="bg-white rounded-[2.5rem] shadow-sm p-10 border border-gray-100">
        <h3 class="text-lg font-black text-gray-800 mb-8 flex items-center">
            <i class="fas fa-lightbulb text-amber-500 mr-4"></i> Business Insights
        </h3>
        <div class="space-y-6">
            <p class="text-sm font-medium text-gray-500 leading-relaxed">
                You have <span class="text-teal-600 font-black"><?= $low_stock ?> items</span> that need restocking soon. Addressing these now will prevent loss of potential sales.
            </p>
            <div class="h-px bg-gray-100"></div>
            <p class="text-sm font-medium text-gray-500 leading-relaxed">
                Profitability is currently <span class="text-green-600 font-black"><?= $today_sales > 0 ? round(($today_profit / $today_sales) * 100, 1) : 0 ?>%</span> today. Focus on high-margin products to increase overall net profit.
            </p>
            <div class="mt-8">
                <a href="pages/reports.php" class="text-xs font-black text-teal-600 uppercase tracking-widest hover:text-teal-700 flex items-center gap-2">
                    View Detailed Analytics <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Hidden iframe for backup trigger -->
<iframe id="backupFrame" style="display:none;"></iframe>

<!-- Update Loading Overlay -->
<div id="updateOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-md hidden z-[9999] flex-col items-center justify-center text-center p-6">
    <div class="bg-white rounded-[3rem] p-10 max-w-sm w-full shadow-2xl scale-in transform transition-all">
        <div class="w-20 h-20 bg-teal-100 text-teal-600 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-sm border border-teal-50">
            <i class="fas fa-cloud-download-alt text-3xl animate-bounce"></i>
        </div>
        <h3 class="text-2xl font-black text-gray-800 mb-2">System Updating</h3>
        <p class="text-gray-500 text-sm mb-6 leading-relaxed">
            Please wait while we backup your database and install the latest updates. <br>
            <strong>Do not close this page.</strong>
        </p>
        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden mb-3">
            <div id="updateProgressBar" class="h-full bg-teal-500 w-0 transition-all duration-1000"></div>
        </div>
        <p id="updateStatusText" class="text-[10px] font-black uppercase tracking-widest text-teal-600">Initializing Update...</p>
    </div>
</div>

<?php if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome']): ?>
    <?php unset($_SESSION['show_welcome']); ?>
    <!-- Welcome Screen Modal -->
    <div id="welcomeModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[10000] flex items-center justify-center p-6 animate-in fade-in duration-500">
        <div class="bg-white rounded-[2.5rem] p-10 max-w-md w-full shadow-2xl border border-teal-50 transform scale-in transition-all duration-700 relative overflow-hidden group">
            <!-- Animated Background Circles -->
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-teal-500/5 rounded-full blur-3xl group-hover:bg-teal-500/10 transition-colors"></div>
            <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-purple-500/5 rounded-full blur-3xl group-hover:bg-purple-500/10 transition-colors"></div>

            <div class="relative z-10 text-center">
                <div class="w-24 h-24 bg-gradient-to-br from-teal-500 to-teal-600 text-white rounded-[2rem] flex items-center justify-center mx-auto mb-8 shadow-xl shadow-teal-500/20 transform hover:rotate-6 transition-transform">
                    <i class="fas fa-rocket text-4xl animate-pulse"></i>
                </div>
                
                <h2 class="text-[10px] font-black text-teal-600 uppercase tracking-[0.3em] mb-3">Login Successful</h2>
                <h3 class="text-3xl font-black text-gray-800 mb-4 tracking-tight leading-tight">
                    Welcome to <span class="text-teal-600"><?= getSetting('business_name', 'Fashion Shines') ?></span>
                </h3>
                
                <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed px-4">
                    The Point of Sale & Inventory Management System is ready for your operations.
                </p>

                <div class="h-px w-16 bg-gradient-to-r from-transparent via-gray-200 to-transparent mx-auto mb-8"></div>

                <div class="flex flex-col items-center">
                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-2">Architect & Developer</p>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-teal-50 flex items-center justify-center border border-teal-100">
                            <i class="fas fa-code text-teal-600 text-[10px]"></i>
                        </div>
                        <p class="text-sm font-black text-gray-800">Abdul Rafay</p>
                    </div>
                </div>

                <div class="mt-10 flex justify-center">
                    <div class="flex gap-1.5 items-center">
                        <div class="w-1.5 h-1.5 bg-teal-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-1.5 h-1.5 bg-teal-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        <div class="w-1.5 h-1.5 bg-teal-500 rounded-full animate-bounce" style="animation-delay: 0.3s"></div>
                    </div>
                </div>
            </div>
            
            <!-- Auto-close timer display (subtle) -->
            <div class="absolute bottom-0 left-0 h-1 bg-teal-500/20 w-full">
                <div id="welcomeProgress" class="h-full bg-teal-500 w-full transition-all duration-[4000ms] ease-linear"></div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('welcomeModal');
            const progress = document.getElementById('welcomeProgress');
            
            // Trigger progress bar shrink
            setTimeout(() => {
                if(progress) progress.style.width = '0%';
            }, 50);

            // Auto-close modal
            setTimeout(() => {
                if(modal) {
                    modal.classList.add('opacity-0', 'scale-95');
                    setTimeout(() => modal.remove(), 500);
                }
            }, 4000);

            // Click to close immediately
            modal.addEventListener('click', function() {
                modal.classList.add('opacity-0', 'scale-95');
                setTimeout(() => modal.remove(), 500);
            });
        });
    </script>
<?php endif; ?>

<script>
async function startSeamlessUpdate() {
    const overlay = document.getElementById('updateOverlay');
    const bar = document.getElementById('updateProgressBar');
    const status = document.getElementById('updateStatusText');
    const backupFrame = document.getElementById('backupFrame');
    
    // 1. Show Overlay
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    
    try {
        // 2. Trigger Backup Download
        status.innerText = "Backing up Database...";
        bar.style.width = "30%";
        backupFrame.src = 'actions/backup_process.php';
        
        // Give a second for backup header to fire
        await new Promise(r => setTimeout(r, 2000));
        
        // 3. Perform Update
        status.innerText = "Installing Latest Updates...";
        bar.style.width = "70%";
        
        const response = await fetch('pages/settings.php', {
            method: 'POST',
            body: new URLSearchParams({ 'action': 'do_update' })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            bar.style.width = "100%";
            status.innerText = "SUCCESS! Signing out for security...";
            setTimeout(() => window.location.href = 'logout.php', 1500);
        } else {
            throw new Error(data.message);
        }
        
    } catch (error) {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        alert("Update Failed: " + error.message);
    }
}

function openDebtorsModal() {
    const modal = document.getElementById('debtorsModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        modal.querySelector('.modal-content').classList.remove('scale-95', 'opacity-0');
        modal.querySelector('.modal-content').classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeDebtorsModal() {
    const modal = document.getElementById('debtorsModal');
    modal.querySelector('.modal-content').classList.remove('scale-100', 'opacity-100');
    modal.querySelector('.modal-content').classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}
</script>

<!-- Debtors Modal -->
<div id="debtorsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-[1000] items-center justify-center p-4">
    <div class="modal-content bg-white w-full max-w-4xl rounded-[2.5rem] shadow-2xl transform transition-all scale-95 opacity-0 overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="p-8 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-orange-50 to-white">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-orange-600 text-white rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-hand-holding-usd text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-gray-800 tracking-tight">Active Debtors</h3>
                    <p class="text-[10px] font-black uppercase tracking-widest text-orange-600">Total: <?= count($all_debtors) ?> Customers</p>
                </div>
            </div>
            <button onclick="closeDebtorsModal()" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl transition-all">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-8 bg-gray-50/30">
            <div class="overflow-x-auto bg-white rounded-3xl border border-gray-100 shadow-sm">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                            <th class="px-6 py-4">Customer Name</th>
                            <th class="px-6 py-4 text-center">Remaining Balance</th>
                            <th class="px-6 py-4 text-center">Earliest Due Date</th>
                            <th class="px-6 py-4 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach($all_debtors as $d): ?>
                            <?php 
                                $days = null;
                                $status_class = "bg-gray-100 text-gray-500";
                                $status_text = "No Due Date";
                                
                                if (!empty($d['due_date'])) {
                                    $diff = strtotime($d['due_date']) - strtotime(date('Y-m-d'));
                                    $days = round($diff / (60 * 60 * 24));
                                    
                                    if ($days < 0) {
                                        $status_class = "bg-red-100 text-red-600";
                                        $status_text = abs($days) . " days overdue";
                                    } elseif ($days == 0) {
                                        $status_class = "bg-orange-100 text-orange-600";
                                        $status_text = "Due Today";
                                    } else {
                                        $status_class = "bg-teal-100 text-teal-600";
                                        $status_text = $days . " days left";
                                    }
                                }
                            ?>
                            <tr onclick="window.location.href='pages/customer_ledger.php?id=<?= $d['id'] ?>'" class="hover:bg-teal-50/50 cursor-pointer transition-colors group">
                                <td class="px-6 py-5">
                                    <div class="font-bold text-gray-800"><?= htmlspecialchars($d['name']) ?></div>
                                    <div class="text-[9px] text-gray-400 font-bold uppercase tracking-wide">ID: <?= $d['id'] ?></div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="text-sm font-black text-gray-800"><?= formatCurrency($d['balance']) ?></div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="text-xs font-bold text-gray-600">
                                        <?= !empty($d['due_date']) ? date('M d, Y', strtotime($d['due_date'])) : '<span class="text-gray-300 italic">Not Set</span>' ?>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <span class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider <?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($all_debtors)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-16 h-16 bg-teal-50 text-teal-600 rounded-full flex items-center justify-center mb-4">
                                            <i class="fas fa-check-circle text-2xl"></i>
                                        </div>
                                        <p class="text-gray-500 font-medium">No outstanding debts found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-8 border-t border-gray-100 flex justify-between items-center bg-gray-50/50">
            <p class="text-[10px] font-bold text-gray-400 italic">Only transactions with a balance >= 1 are listed here.</p>
            <a href="pages/customer_ledger.php" class="px-6 py-2 bg-gray-800 text-white rounded-xl text-xs font-bold hover:bg-gray-900 transition active:scale-95">Open Ledgers</a>
        </div>
    </div>
</div>


<?php 
include 'includes/footer.php';
echo '<script>
fetch("heartbeat_check.php")
    .then(r => r.text())
    .then(t => { 
        if(t.trim().includes("BLOCK")) location.reload(); 
    });
</script>';
echo '</main></div></body></html>'; 
?>
