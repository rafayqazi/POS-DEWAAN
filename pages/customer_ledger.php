<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['id'])) redirect('customers.php');
$cid = $_GET['id'];

// RBAC Check: Ensure Customer only views their own ledger
if (isRole('Customer') && $cid !== ($_SESSION['related_id'] ?? '')) {
    die("Unauthorized access to this ledger.");
}
$customer = findCSV('customers', $cid);
if (!$customer) die("Customer not found.");

// Handle Date Filtering
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';

// Handle Deletions
if (isset($_GET['delete_txn'])) {
    deleteCSV('customer_transactions', $_GET['delete_txn']);
    redirect("customer_ledger.php?id=$cid&msg=Entry deleted successfully");
}

// Handle Transaction (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    $amount = (float)$_POST['amount'];
    $date = $_POST['txn_date'];
    $notes = cleanInput($_POST['notes']);
    $type = $_POST['type'] ?? 'Payment';
    $payment_type = $_POST['payment_type'] ?? '';
    $payment_proof = $_POST['existing_proof'] ?? '';

    // Handle File Upload
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
        $upload_dir = '../uploads/payments/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $filename = 'cust_' . time() . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_dir . $filename)) { 
            $payment_proof = $filename;
        }
    }

    $data = [
        'customer_id' => $cid,
        'type' => $type,
        'debit' => ($type == 'Debt') ? $amount : 0,
        'credit' => ($type == 'Payment') ? $amount : 0,
        'discount' => isset($_POST['discount']) ? (float)$_POST['discount'] : 0,
        'description' => ($type == 'Debt' ? "Previous Debt: " : "Payment Received: ") . $notes,
        'date' => $date,
        'due_date' => $_POST['due_date'] ?? '',
        'payment_type' => $payment_type,
        'payment_proof' => $payment_proof
    ];

    if (isset($_POST['txn_id']) && !empty($_POST['txn_id'])) {
        updateCSV('customer_transactions', $_POST['txn_id'], $data);
    } else {
        $data['created_at'] = date('Y-m-d H:i:s');
        insertCSV('customer_transactions', $data);
    }
    redirect("customer_ledger.php?id=$cid" . ($from_date ? "&from=$from_date" : "") . ($to_date ? "&to=$to_date" : ""));
}

$pageTitle = "Ledger: " . $customer['name'];
include '../includes/header.php';

// Fetch all transactions for this customer
$all_txns = readCSV('customer_transactions');
$all_sales = readCSV('sales');
$all_sale_items = readCSV('sale_items');
$all_products = readCSV('products');
$all_return_items = readCSV('return_items');

// Create maps for efficient lookups
$sales_map = [];
foreach($all_sales as $s) $sales_map[$s['id']] = $s;

$products_map = [];
foreach($all_products as $p) $products_map[$p['id']] = $p;

$units = readCSV('units');

$sale_items_grouped = [];
foreach($all_sale_items as $si) {
    $sale_items_grouped[$si['sale_id']][] = $si;
}

$return_items_grouped = [];
foreach($all_return_items as $ri) {
    $return_items_grouped[$ri['return_id']][] = $ri;
}

$ledger = [];
$total_due = 0;

foreach($all_txns as $t) {
    if($t['customer_id'] == $cid) {
        if (!isset($t['discount'])) $t['discount'] = 0;
        $ledger[] = $t;
        $total_due += (float)$t['debit'] - (float)$t['credit'] - (float)$t['discount'];
    }
}

// Sorting
usort($ledger, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Linked Dealer Logic
$linked_dealer = null;
$dealer_balance = 0;
$linked_dealer_id = $customer['linked_dealer_id'] ?? '';

if ($linked_dealer_id) {
    $all_dealers = readCSV('dealers');
    foreach ($all_dealers as $d) {
        if ($d['id'] == $linked_dealer_id) {
            $linked_dealer = $d;
            break;
        }
    }

    if ($linked_dealer) {
        $dealer_txns = readCSV('dealer_transactions');
        foreach ($dealer_txns as $dt) {
            if ($dt['dealer_id'] == $linked_dealer_id) {
                $dealer_balance += (float)($dt['debit'] ?? 0) - (float)($dt['credit'] ?? 0);
            }
        }
    }
}
?>

<div class="max-w-7xl mx-auto">
<div class="grid grid-cols-1 md:grid-cols-<?= $linked_dealer ? '3' : '2' ?> gap-6 mb-6">
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 border-l-4 border-purple-500 glass">
         <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2 flex justify-between items-center">
            Customer Details
            <?php if (isRole('Admin')): ?>
            <button onclick="openLinkModal()" class="text-purple-500 hover:text-purple-700 transition" title="Link to Dealer">
                <i class="fas fa-link"></i>
            </button>
            <?php endif; ?>
         </h3>
         <div class="flex items-center gap-3 mb-2">
            <p class="font-black text-gray-800 text-2xl tracking-tight"><?= htmlspecialchars($customer['name']) ?></p>
            <span id="debtClearedBadge" class="hidden inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 border border-yellow-200 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm">
                <i class="fas fa-trophy mr-1.5 text-yellow-500"></i> Debt Cleared!
            </span>
         </div>
         <p class="text-xs font-bold text-gray-500"><?= htmlspecialchars($customer['phone']) ?></p>
         <div class="flex justify-between items-end">
            <p class="text-[10px] text-gray-400 font-bold mt-1"><?= htmlspecialchars($customer['address']) ?></p>
            <?php if ($linked_dealer): ?>
            <div class="bg-purple-50 px-2 py-1 rounded-lg border border-purple-100 flex items-center gap-1.5 mt-2">
                <i class="fas fa-handshake text-purple-500 text-[10px]"></i>
                <span class="text-[9px] font-black text-purple-600 uppercase">Linked: <?= htmlspecialchars($linked_dealer['name']) ?></span>
            </div>
            <?php endif; ?>
         </div>
    </div>
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 border-l-4 border-red-500 glass flex flex-col justify-center">
         <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">
            Outstanding Balance (Debt)
         </h3>
         <p id="statTotalDue" class="text-4xl font-black text-red-600 tracking-tighter"><?= formatCurrency($total_due) ?></p>
    </div>
    <?php if ($linked_dealer): 
        $net_balance = $dealer_balance - $total_due;
    ?>
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 border-l-4 border-orange-500 glass flex flex-col justify-center">
         <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Outstanding Balance (Dealer)</h3>
         <p class="text-4xl font-black text-orange-600 tracking-tighter"><?= formatCurrency($dealer_balance) ?></p>
    </div>
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 border-l-4 border-blue-500 glass flex flex-col justify-center">
         <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Net Outstanding Balance</h3>
         <?php if ($net_balance >= 0): ?>
            <p class="text-4xl font-black text-blue-600 tracking-tighter"><?= formatCurrency($net_balance) ?></p>
            <span class="text-[10px] font-bold text-blue-500 uppercase mt-1">Payable to Dealer</span>
         <?php else: ?>
            <p class="text-4xl font-black text-blue-600 tracking-tighter"><?= formatCurrency(abs($net_balance)) ?></p>
            <span class="text-[10px] font-bold text-blue-500 uppercase mt-1">Receivable from Customer</span>
         <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Filters and Actions Container with high Z-Index -->
<div class="mb-6 bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 glass relative z-50">
    <!-- Row 1: Filters -->
    <div class="flex flex-wrap items-end gap-4 pb-6 border-b border-gray-100 w-full">
        <input type="hidden" name="id" value="<?= $cid ?>">
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Quick Range</label>
            <select onchange="applyQuickDate(this.value)" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-purple-500 outline-none w-40 shadow-sm h-[42px]">
                <option value="">Custom</option>
                <option value="all_time">All Time</option>
                <option value="today">Today</option>
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="last_90">Last 90 Days</option>
                <option value="last_year">Last 1 Year</option>
            </select>
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">From Date</label>
            <input type="date" id="dateFrom" onchange="renderTable()" value="" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-purple-500 outline-none shadow-sm h-[42px]">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" id="dateTo" onchange="renderTable()" value="" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-purple-500 outline-none shadow-sm h-[42px]">
        </div>
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1 opacity-0">Action</label>
            <button onclick="clearFilters()" class="px-6 bg-gray-100 text-gray-500 rounded-xl text-xs font-bold hover:bg-gray-200 transition shadow-sm h-[42px] flex items-center justify-center">CLEAR</button>
        </div>
    </div>
    
    <!-- Row 2: Actions -->
    <div class="flex flex-wrap gap-3 mt-6 justify-end relative">
        <div class="relative inline-block text-left" id="downloadDropdown">
            <button type="button" onclick="toggleDownloadDropdown()" class="bg-blue-500 text-white px-5 py-3 rounded-xl hover:bg-blue-600 shadow-lg shadow-blue-900/10 font-bold text-xs h-[46px] flex items-center transition active:scale-95">
                <i class="fas fa-file-export mr-2"></i> Download Ledger <i class="fas fa-chevron-down ml-2 text-[10px]"></i>
            </button>
            <div id="downloadMenu" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-100 rounded-2xl shadow-2xl z-[100] glass overflow-hidden transform transition-all scale-95 opacity-0 origin-top-right">
                <div class="p-2 space-y-1">
                    <button onclick="printReport(); toggleDownloadDropdown()" class="w-full text-left px-4 py-3 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition flex items-center">
                        <i class="fas fa-file-pdf mr-3 text-red-500"></i> Download as PDF
                    </button>
                    <button onclick="exportToExcel(); toggleDownloadDropdown()" class="w-full text-left px-4 py-3 text-xs font-bold text-gray-700 hover:bg-green-50 hover:text-green-600 rounded-xl transition flex items-center">
                        <i class="fas fa-file-excel mr-3 text-green-600"></i> Download as Excel
                    </button>
                </div>
            </div>
        </div>

        <?php if (isRole('Admin')): ?>
        <button onclick="openTxnModal('Advance')" class="bg-orange-500 text-white px-6 py-3 rounded-xl shadow-lg shadow-orange-900/10 font-bold text-xs h-[46px] hover:bg-orange-600 transition active:scale-95">
            <i class="fas fa-plus-circle mr-2"></i> ADD ADVANCE PAYMENT
        </button>
        <button onclick="openTxnModal('Payment')" class="bg-primary text-white px-6 py-3 rounded-xl shadow-lg shadow-teal-900/10 font-bold text-xs h-[46px] hover:bg-secondary transition active:scale-95">
            <i class="fas fa-hand-holding-usd mr-2"></i> RECEIVE PAYMENT
        </button>
        <button onclick="openTxnModal('Debt')" class="bg-red-500 text-white px-6 py-3 rounded-xl shadow-lg shadow-red-900/10 font-bold text-xs h-[46px] hover:bg-red-600 transition active:scale-95">
            <i class="fas fa-file-invoice-dollar mr-2"></i> OUTSTANDING DEBT
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Table Container with lower Z-Index -->
<div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden glass mb-6 relative z-10">
    <div class="p-6 border-b border-gray-50 bg-gray-50/50">
        <h4 class="font-bold text-gray-800 flex items-center">
            <i class="fas fa-scroll text-purple-500 mr-2"></i> Transaction History
        </h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] border-b border-gray-100">
                <tr>
                    <th class="p-6 w-12 text-center">Sno#</th>
                    <th class="p-6">Date</th>
                    <th class="p-6">Products & QTY</th>
                    <th class="p-6 text-right">Debit (Sale)</th>
                    <th class="p-6 text-right">Credit (Paid)</th>
                    <th class="p-6 text-right">Discount</th>
                    <th class="p-6">Reference</th>
                    <th class="p-6">Due Date</th>
                    <th class="p-6 text-right text-purple-600">Balance</th> 
                    <th class="p-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50" id="ledgerBody"></tbody>
        </table>
    </div>
    <div id="ledgerPagination" class="px-6 py-4 bg-gray-50 border-t border-gray-100"></div>
</div>

<style>
    .italic-normal { font-style: normal !important; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d1d1; border-radius: 10px; }
</style>

</div>

<!-- Modals -->
<?php if (isRole('Admin')): ?>
<div id="linkModal" class="hidden fixed inset-0 bg-black/50 z-[1000] flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl max-w-lg w-full p-8 glass border border-white/20">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black text-gray-800 tracking-tight">Link Customer to Dealer</h3>
            <button onclick="closeLinkModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="mb-6">
            <input type="text" id="dealerSearch" oninput="filterDealers(this.value)" placeholder="Search dealers..." class="w-full p-3 bg-gray-50 border border-gray-100 rounded-2xl text-sm outline-none">
        </div>
        <div class="max-h-[350px] overflow-y-auto custom-scrollbar space-y-3" id="dealerList">
            <?php 
            $all_dealers = readCSV('dealers');
            foreach ($all_dealers as $dealer): 
                $is_linked = ($dealer['id'] == $linked_dealer_id);
            ?>
            <div class="dealer-item flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-transparent hover:border-purple-200 transition" data-name="<?= strtolower($dealer['name']) ?>">
                <div><h4 class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($dealer['name']) ?></h4></div>
                <button onclick="handleLink('<?= $dealer['id'] ?>', <?= $is_linked ? 'true' : 'false' ?>)" class="px-4 py-2 <?= $is_linked ? 'bg-red-50 text-red-600' : 'bg-purple-600 text-white' ?> rounded-xl text-[10px] font-black uppercase"><?= $is_linked ? 'Unlink' : 'Link' ?></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="txnModal" class="hidden fixed inset-0 bg-black/50 z-[1000] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 id="txnModalTitle" class="text-lg font-bold text-gray-800">Record Transaction</h3>
            <button onclick="closeTxnModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div id="modalDebtDisplay" class="mb-4 p-3 bg-red-50 border border-red-100 rounded-lg">
            <div class="flex justify-between items-center"><span class="text-xs font-bold text-red-600 uppercase">Outstanding Balance</span><span id="modalDebtAmount" class="text-xl font-black text-red-700">Rs. 0</span></div>
        </div>
        <form method="POST" class="space-y-4" onsubmit="return validateTransaction()" enctype="multipart/form-data">
            <input type="hidden" name="type" id="modalTxnType">
            <input type="hidden" name="txn_id" id="modalTxnId">
            <input type="hidden" id="modalIsAdvance" value="0">
            <input type="date" name="txn_date" id="modalTxnDate" required class="w-full p-2 border border-gray-300 rounded-lg text-sm outline-none">
            <div id="payInFullWrapper" class="flex items-center gap-2 mb-2">
                <input type="checkbox" id="payInFullCheckbox" onchange="handlePayInFull(this.checked)" class="w-4 h-4 text-teal-600">
                <label for="payInFullCheckbox" class="text-sm font-bold text-teal-600">Pay in Full</label>
            </div>
            <input type="number" name="amount" id="modalTxnAmount" oninput="syncAmountAndDiscount()" step="0.01" required class="w-full p-2 border border-gray-300 rounded-lg text-sm outline-none" placeholder="Amount">
            <input type="number" name="discount" id="modalTxnDiscount" oninput="syncAmountAndDiscount()" step="0.01" class="w-full p-2 border border-gray-300 rounded-lg text-sm outline-none" placeholder="Discount">
            <textarea name="notes" id="modalTxnNotes" rows="2" class="w-full p-2 border border-gray-300 rounded-lg text-sm outline-none resize-none" placeholder="Notes"></textarea>
            <input type="date" name="due_date" id="modalDueDate" class="w-full p-2 border border-gray-300 rounded-lg text-sm outline-none">
            <div class="grid grid-cols-2 gap-3">
                <select name="payment_type" id="modalPaymentType" class="w-full p-2 border border-gray-300 rounded-lg text-sm outline-none"><option value="Cash">Cash</option><option value="Online">Online</option><option value="Cheque">Cheque</option></select>
                <input type="file" name="payment_proof" class="w-full p-1 border border-gray-300 rounded-lg text-[10px]">
                <input type="hidden" name="existing_proof" id="modalExistingProof">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeTxnModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-bold text-sm">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-teal-600 text-white rounded-lg font-bold text-sm">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Printable Area -->
<div id="printableArea" class="hidden">
    <div style="padding: 20px; font-family: sans-serif; font-size: 11px;">
        <style>@page { margin: 10mm; } @media print { body { font-size: 10px; } }</style>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0d9488; padding-bottom: 12px; margin-bottom: 15px;">
            <div style="text-align: left;"><h1 style="color: #0d9488; margin: 0; font-size: 22px;"><?= getSetting('business_name', 'Fashion Shines') ?></h1><p style="color: #888; margin: 3px 0 0 0; font-size: 11px;">Management System</p></div>
            <div style="text-align: right;"><h2 style="margin: 0; color: #333; font-size: 18px;">Customer Ledger Report</h2><p style="color: #888; margin: 3px 0 0 0; font-size: 11px;">Generated on: <?= date('d M Y, h:i A') ?></p></div>
        </div>
        <div style="display: flex; gap: 20px; margin-bottom: 15px; align-items: stretch;">
            <div style="flex: 1; padding: 10px 12px; border-left: 4px solid #0f766e;">
                <p style="margin: 0 0 2px 0; color: #0d9488; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">CUSTOMER DETAILS</p>
                <p style="margin: 0 0 2px 0; font-weight: bold; font-size: 14px;"><?= htmlspecialchars($customer['name']) ?></p>
                <p style="margin: 1px 0; color: #555; font-size: 11px;"><?= htmlspecialchars($customer['phone']) ?></p>
                <p style="margin: 1px 0; color: #777; font-size: 10px;"><?= htmlspecialchars($customer['address']) ?></p>
            </div>
            <div style="flex: 1; padding: 10px 12px; border-left: 4px solid #e11d48; text-align: right; display: flex; flex-direction: column; justify-content: center;">
                <p style="margin: 0 0 2px 0; color: #e11d48; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">OUTSTANDING BALANCE</p>
                <p id="printTotalDue" style="margin: 0; font-weight: bold; font-size: 22px; color: #e11d48;"><?= formatCurrency($total_due) ?></p>
            </div>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <thead><tr style="background: #f8f8f8; color: #555; font-size: 9px; text-transform: uppercase;">
                <th style="padding: 5px; border: 1px solid #ddd; width: 25px;">Sr #</th>
                <th style="padding: 5px; border: 1px solid #ddd; width: 60px;">Date</th>
                <th style="padding: 5px; border: 1px solid #ddd;">Description</th>
                <th style="padding: 5px; border: 1px solid #ddd; text-align: right; width: 65px;">Debit (Sale)</th>
                <th style="padding: 5px; border: 1px solid #ddd; text-align: right; width: 65px;">Credit (Paid)</th>
                <th style="padding: 5px; border: 1px solid #ddd; text-align: right; width: 50px;">Discount</th>
                <th style="padding: 5px; border: 1px solid #ddd; width: 60px;">Reference</th>
                <th style="padding: 5px; border: 1px solid #ddd; width: 70px;">Due Date</th>
            </tr></thead>
            <tbody id="printBody"></tbody>
        </table>
        <!-- Developer Footer -->
        <div style="margin-top:40px; border-top:1px solid #eee; padding-top:15px; text-align:center; font-size:9px; color:#aaa;">
            <p style="margin:0; font-weight:bold; color:#888;">Software Developed by Abdul Rafay</p>
            <p style="margin:4px 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>

<script>
    const allTxns = <?= json_encode($ledger) ?>;
    const salesMap = <?= json_encode($sales_map) ?>;
    const saleItemsMap = <?= json_encode($sale_items_grouped) ?>;
    const productsMap = <?= json_encode($products_map) ?>;
    const initialBalance = <?= $total_due ?>;
    const canEdit = <?= json_encode(isRole('Admin')) ?>;
    const availableUnits = <?= json_encode($units) ?>;
    let currentPage_Ledger = 1;
    const pageSize_Ledger = 200;

    const formatCurrency = (amount) => 'Rs.' + new Intl.NumberFormat('en-US').format(amount);

    function renderTable() {
        const dateFromVal = document.getElementById('dateFrom').value;
        const dateToVal = document.getElementById('dateTo').value;
        let filteredInfo = filterTransactions(allTxns, dateFromVal, dateToVal);
        const { finalTxns, openingBalance, stats } = filteredInfo;

        document.getElementById('statTotalDue').innerText = formatCurrency(stats.balance);
        document.getElementById('ledgerBody').innerHTML = generateTableRows(Pagination.paginate(finalTxns, currentPage_Ledger, pageSize_Ledger), openingBalance, dateFromVal, false);
        
        Pagination.render('ledgerPagination', finalTxns.length, currentPage_Ledger, pageSize_Ledger, (p) => {
            currentPage_Ledger = p;
            renderTable();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        document.getElementById('printBody').innerHTML = generateTableRows(finalTxns, openingBalance, dateFromVal, true, stats);
    }

    function filterTransactions(txns, fromDate, toDate) {
        let opening = 0, validTxns = [], rangeDebit = 0, rangeCredit = 0, rangeDiscount = 0;
        let sorted = [...txns].sort((a, b) => a.date < b.date ? -1 : (a.date > b.date ? 1 : 0));
        sorted.forEach(t => {
            const tDate = t.date.substring(0, 10);
            if (fromDate && tDate < fromDate) opening += parseFloat(t.debit || 0) - parseFloat(t.credit || 0) - parseFloat(t.discount || 0);
            else if (!toDate || tDate <= toDate) validTxns.push(t);
        });
        let running = opening;
        validTxns.forEach(t => {
            running += parseFloat(t.debit || 0) - parseFloat(t.credit || 0) - parseFloat(t.discount || 0);
            rangeDebit += parseFloat(t.debit || 0);
            rangeCredit += parseFloat(t.credit || 0);
            rangeDiscount += parseFloat(t.discount || 0);
            t.current_running_balance = running;
        });
        return { finalTxns: validTxns.reverse(), openingBalance: opening, stats: { totalDebit: rangeDebit, totalCredit: rangeCredit, totalDiscount: rangeDiscount, balance: running } };
    }

    function getProductsText(t) {
        if (t.type === 'Sale' && t.sale_id) {
            const items = saleItemsMap[t.sale_id] || [];
            if (items.length > 0) {
                const itemsText = items.map(item => {
                    const p = productsMap[item.product_id];
                    return `${p ? p.name : 'Product'} (x${item.quantity})`;
                }).join('; ');
                return `${t.description}: ${itemsText}`;
            }
        }
        return t.description;
    }

    function getProductsHtml(t, isPrint) {
        if (t.type === 'Sale' && t.sale_id) {
            const items = saleItemsMap[t.sale_id] || [];
            if (isPrint && items.length > 0) {
                let html = `<table style="width:100%;border-collapse:collapse;font-size:8px;line-height:1.2;table-layout:fixed;">`;
                html += `<tr style="font-weight:bold;text-transform:uppercase;font-size:7px;"><td style="padding:1px 3px;width:140px;">NAME</td><td style="padding:1px 3px;width:50px;">QTY</td><td style="padding:1px 3px;color:#0d9488;width:50px;">PRICE</td><td style="padding:1px 3px;color:#0d9488;width:55px;">TOTAL</td></tr>`;
                items.forEach(item => {
                    const p = productsMap[item.product_id];
                    const pName = p ? p.name : 'Product';
                    const unit = p ? (p.unit || 'Peace') : 'Peace';
                    const price = parseFloat(item.total_price) / parseFloat(item.quantity || 1);
                    html += `<tr><td style="padding:1px 3px;font-weight:bold;white-space:nowrap!important;overflow:hidden;text-overflow:ellipsis;">${pName}</td><td style="padding:1px 3px;white-space:nowrap!important;">x ${item.quantity} ${unit}</td><td style="padding:1px 3px;color:#0d9488;font-weight:bold;">Rs.${Math.round(price).toLocaleString()}</td><td style="padding:1px 3px;color:#0d9488;font-weight:bold;">Rs.${Math.round(item.total_price).toLocaleString()}</td></tr>`;
                });
                html += `</table>`;
                return html;
            }
            return items.map(item => {
                const p = productsMap[item.product_id];
                return `<div class="flex justify-between text-[11px] border-b border-gray-50 py-1 last:border-0"><span>${p ? p.name : 'Product'} x${item.quantity}</span><span>${formatCurrency(item.total_price)}</span></div>`;
            }).join('');
        }
        return isPrint ? `<span style="font-weight:bold;">${t.description}</span>` : t.description;
    }

    function generateTableRows(list, opening, fromDate, isPrint, stats = null) {
        let html = '';
        if (opening !== 0) html += `<tr class="bg-gray-50/50"><td colspan="${isPrint ? 7 : 8}" class="p-4 text-xs font-bold text-gray-500 uppercase" ${isPrint ? 'style="padding:10px;border:1px solid #eee;font-weight:bold;color:#666;"' : ''}>Opening Balance</td><td class="p-4 text-right font-black text-red-600" ${isPrint ? 'style="padding:10px;border:1px solid #eee;text-align:right;font-weight:bold;color:#e11d48;"' : ''}>${formatCurrency(opening)}</td>${isPrint ? '' : '<td class="p-4"></td>'}</tr>`;
        list.forEach((t, i) => {
            const sn = (currentPage_Ledger - 1) * pageSize_Ledger + i + 1;
            if (isPrint) {
                const printDate = new Date(t.date.substring(0,10));
                const dateStr = printDate.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
                const discountVal = parseFloat(t.discount || 0);
                const refText = t.sale_id ? `Sale #${t.sale_id}` : (t.type === 'Payment' ? 'Payment Received:' : (t.description || '-'));
                const dueDate = t.due_date ? new Date(t.due_date).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'}) : '-';
                html += `<tr style="vertical-align:top;border-bottom:1px solid #eee;">`;
                html += `<td style="padding:5px;border:1px solid #eee;text-align:center;color:#999;font-size:9px;">${sn}</td>`;
                html += `<td style="padding:5px;border:1px solid #eee;font-size:9px;white-space:nowrap!important;">${dateStr}</td>`;
                html += `<td style="padding:5px;border:1px solid #eee;">${getProductsHtml(t, true)}</td>`;
                html += `<td style="padding:5px;border:1px solid #eee;text-align:right;color:#0d9488;font-weight:bold;font-size:9px;">${t.debit > 0 ? formatCurrency(t.debit) : '-'}</td>`;
                html += `<td style="padding:5px;border:1px solid #eee;text-align:right;color:#0d9488;font-weight:bold;font-size:9px;">${t.credit > 0 ? formatCurrency(t.credit) : '-'}</td>`;
                html += `<td style="padding:5px;border:1px solid #eee;text-align:right;color:#d97706;font-size:9px;">${discountVal > 0 ? formatCurrency(discountVal) : '-'}</td>`;
                html += `<td style="padding:5px;border:1px solid #eee;font-size:9px;">${refText}</td>`;
                html += `<td style="padding:5px;border:1px solid #eee;font-size:9px;white-space:nowrap!important;">${dueDate}</td>`;
                html += `</tr>`;
            } else {
                html += `<tr class="hover:bg-purple-50/30 transition border-b border-gray-50 last:border-0"><td class="p-6 text-center text-xs font-mono text-gray-400 align-top">${sn}</td><td class="p-6 align-top"><span class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-1 rounded uppercase">${t.date.substring(0,10)}</span></td><td class="p-6 align-top">${getProductsHtml(t, false)}</td><td class="p-6 text-right font-black text-gray-700 align-top">${t.debit > 0 ? formatCurrency(t.debit) : '-'}</td><td class="p-6 text-right font-black text-emerald-600 align-top">${t.credit > 0 ? formatCurrency(t.credit) : '-'}</td><td class="p-6 text-right font-black text-amber-600 align-top">${t.discount > 0 ? formatCurrency(t.discount) : '-'}</td><td class="p-6 align-top text-[10px] font-bold text-gray-500 truncate max-w-[150px]">${t.description}</td><td class="p-6 text-center align-top">${t.due_date || '-'}</td><td class="p-6 text-right font-black text-red-600 bg-red-50/20 align-top">${formatCurrency(t.current_running_balance)}</td><td class="p-6 text-center align-top">${canEdit ? `<button onclick="confirmDelete('customer_ledger.php?id=<?= $cid ?>&delete_txn=${t.id}')" class="text-red-400 hover:text-red-600"><i class="fas fa-trash"></i></button>` : '-'}</td></tr>`;
            }
        });

        if (isPrint && stats) {
            html += `<tr style="border-top:2px solid #ddd; font-weight:bold; background:#fcfcfc;">`;
            html += `<td colspan="3" style="padding:8px; text-align:right; border:1px solid #ddd; text-transform:uppercase; font-size:10px; color:#666;">Total Debit / Credit / Discount:</td>`;
            html += `<td style="padding:8px; text-align:right; border:1px solid #ddd; color:#e11d48; font-size:11px;">${formatCurrency(stats.totalDebit)}</td>`;
            html += `<td style="padding:8px; text-align:right; border:1px solid #ddd; color:#0d9488; font-size:11px;">${formatCurrency(stats.totalCredit)}</td>`;
            html += `<td style="padding:8px; text-align:right; border:1px solid #ddd; color:#d97706; font-size:11px;">${formatCurrency(stats.totalDiscount)}</td>`;
            html += `<td colspan="2" style="padding:8px; border:1px solid #ddd; font-size:9px; color:#aaa;">Overall Summary</td>`;
            html += `</tr>`;
            html += `<tr><td colspan="8" style="padding:0; border:none;"><div style="display:flex; justify-content:flex-end; padding:20px 0;"><div style="border:1px solid #eee; display:flex; align-items:center;"><div style="background:#f8f8f8; padding:10px 20px; font-weight:bold; color:#e11d48; text-transform:uppercase; font-size:12px; border-right:1px solid #eee;">OUTSTANDING BALANCE:</div><div style="padding:10px 30px; font-size:20px; font-weight:bold; color:#e11d48;">${formatCurrency(stats.balance)}</div></div></div></td></tr>`;
        }

        return html || '<tr><td colspan="10" class="p-10 text-center text-gray-400">No transactions found.</td></tr>';
    }

    function toggleDownloadDropdown() {
        const menu = document.getElementById('downloadMenu');
        if (!menu) return;
        const isHidden = menu.classList.contains('hidden');
        if (isHidden) { menu.classList.remove('hidden'); setTimeout(() => { menu.classList.remove('scale-95', 'opacity-0'); menu.classList.add('scale-100', 'opacity-100'); }, 10); }
        else { menu.classList.remove('scale-100', 'opacity-100'); menu.classList.add('scale-95', 'opacity-0'); setTimeout(() => menu.classList.add('hidden'), 200); }
    }

    function exportToExcel() {
        const dateFromVal = document.getElementById('dateFrom').value;
        const dateToVal = document.getElementById('dateTo').value;
        let filteredInfo = filterTransactions(allTxns, dateFromVal, dateToVal);
        const { finalTxns, stats } = filteredInfo;
        
        let csv = "\ufeffSr#,Date,Description,Debit,Credit,Discount,Balance\n";
        const exportData = [...finalTxns].reverse(); 
        
        exportData.forEach((t, i) => {
            const desc = getProductsText(t).replace(/"/g, '""');
            csv += `${i+1},${t.date.substring(0,10)},"${desc}",${t.debit},${t.credit},${t.discount},${t.current_running_balance}\n`;
        });
        
        csv += `\n-,-,TOTALS,${stats.totalDebit},${stats.totalCredit},${stats.totalDiscount},${stats.balance}\n`;
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Ledger_<?= $customer['name'] ?>_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
    }

    function printReport() {
        const win = window.open('', '_blank');
        const fileName = `Ledger_<?= str_replace([" ", "'", "\""], "_", $customer['name']) ?>_<?= date('d_M_Y') ?>`;
        win.document.write('<html><head><title>' + fileName + '</title><style>body{font-family:sans-serif;margin:0;padding:0;}</style></head><body>' + document.getElementById('printableArea').innerHTML + '</body></html>');
        win.document.close(); win.focus(); setTimeout(() => { win.print(); win.close(); }, 500);
    }

    function applyQuickDate(v) {
        const today = new Date(); let s, e = today;
        if (v === 'today') s = today;
        else if (v === 'this_month') s = new Date(today.getFullYear(), today.getMonth(), 1);
        else if (v === 'last_month') { s = new Date(today.getFullYear(), today.getMonth() - 1, 1); e = new Date(today.getFullYear(), today.getMonth(), 0); }
        else if (v === 'all_time') { s = ''; e = ''; }
        document.getElementById('dateFrom').value = s ? s.toISOString().split('T')[0] : '';
        document.getElementById('dateTo').value = e ? e.toISOString().split('T')[0] : '';
        renderTable();
    }

    function clearFilters() { document.getElementById('dateFrom').value = ''; document.getElementById('dateTo').value = ''; renderTable(); }
    function openTxnModal(t) { document.getElementById('modalTxnType').value = (t === 'Advance' ? 'Payment' : t); document.getElementById('modalIsAdvance').value = (t === 'Advance' ? '1' : '0'); document.getElementById('modalDebtAmount').innerText = formatCurrency(allTxns.reduce((acc, curr) => acc + parseFloat(curr.debit||0) - parseFloat(curr.credit||0), 0)); document.getElementById('txnModal').classList.remove('hidden'); }
    function closeTxnModal() { document.getElementById('txnModal').classList.add('hidden'); }
    function validateTransaction() { return true; }
    function confirmDelete(url) { if(confirm("Delete this record?")) window.location.href = url; }
    document.addEventListener('click', (e) => { const d = document.getElementById('downloadDropdown'); const m = document.getElementById('downloadMenu'); if (d && !d.contains(e.target) && m) { m.classList.add('hidden'); } });
    document.addEventListener('DOMContentLoaded', renderTable);
</script>

<?php include '../includes/footer.php'; ?>
