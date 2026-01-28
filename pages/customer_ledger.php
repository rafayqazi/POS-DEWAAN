<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['id'])) redirect('customers.php');
$cid = $_GET['id'];
$customer = findCSV('customers', $cid);

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
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_dir . $filename)) { // Corrected tmp_id to tmp_name
            $payment_proof = $filename;
        }
    }

    $data = [
        'customer_id' => $cid,
        'type' => $type,
        'debit' => ($type == 'Debt') ? $amount : 0,
        'credit' => ($type == 'Payment') ? $amount : 0,
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

// Create maps for efficient lookups
$sales_map = [];
foreach($all_sales as $s) $sales_map[$s['id']] = $s;

$products_map = [];
foreach($all_products as $p) $products_map[$p['id']] = $p['name'];

$sale_items_grouped = [];
foreach($all_sale_items as $si) {
    $sale_items_grouped[$si['sale_id']][] = $si;
}

$ledger = [];
$total_due = 0;

foreach($all_txns as $t) {
    if($t['customer_id'] == $cid) {
        // No PHP filtering - JS handles it
        // if ($from_date && $t['date'] < $from_date) continue;
        // if ($to_date && $t['date'] > $to_date) continue;
        
        $ledger[] = $t;
        // Calculate total for initial view (JS will overwrite, but good for SEO/No-JS fallback if needed, though completely dependent on JS now)
        $total_due += (float)$t['debit'] - (float)$t['credit'];
    }
}

// Ensure sorting
usort($ledger, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 border-l-4 border-purple-500 glass">
         <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Customer Details</h3>
         <div class="flex items-center gap-3 mb-2">
            <p class="font-black text-gray-800 text-2xl tracking-tight"><?= htmlspecialchars($customer['name']) ?></p>
            <span id="debtClearedBadge" class="hidden inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 border border-yellow-200 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm">
                <i class="fas fa-trophy mr-1.5 text-yellow-500"></i> Debt Cleared!
            </span>
         </div>
         <p class="text-xs font-bold text-gray-500"><?= htmlspecialchars($customer['phone']) ?></p>
         <p class="text-[10px] text-gray-400 font-bold mt-1"><?= htmlspecialchars($customer['address']) ?></p>
    </div>
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 border-l-4 border-red-500 glass flex flex-col justify-center">
         <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Outstanding Balance (Debt)</h3>
         <p id="statTotalDue" class="text-4xl font-black text-red-600 tracking-tighter"><?= formatCurrency($total_due) ?></p>
    </div>
</div>

<div class="mb-6 flex flex-col md:flex-row justify-between items-end gap-4 bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 glass">
    <!-- Filters -->
    <div class="flex flex-wrap items-end gap-3 flex-1">
        <input type="hidden" name="id" value="<?= $cid ?>">
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Quick Range</label>
            <select onchange="applyQuickDate(this.value)" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-purple-500 outline-none w-36 shadow-sm">
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
            <input type="date" id="dateFrom" onchange="renderTable()" value="<?= date('Y-m-01') ?>" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-purple-500 outline-none shadow-sm">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" id="dateTo" onchange="renderTable()" value="<?= date('Y-m-d') ?>" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-purple-500 outline-none shadow-sm">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">&nbsp;</label>
            <button onclick="clearFilters()" class="p-3 bg-gray-100 text-gray-500 rounded-xl text-xs font-bold hover:bg-gray-200 transition shadow-sm h-[42px] flex items-center">
                CLEAR
            </button>
        </div>
    </div>
    
    <div class="flex gap-3">
        <button onclick="printReport()" class="bg-blue-500 text-white px-5 py-3 rounded-xl hover:bg-blue-600 shadow-lg shadow-blue-900/10 font-bold text-xs h-[46px] flex items-center transition active:scale-95">
            <i class="fas fa-print mr-2"></i> Print / Save PDF
        </button>
        <button onclick="openTxnModal('Payment')" class="bg-primary text-white px-6 py-3 rounded-xl shadow-lg shadow-teal-900/10 font-bold text-xs h-[46px] hover:bg-secondary transition active:scale-95">
            <i class="fas fa-hand-holding-usd mr-2"></i> RECEIVE PAYMENT
        </button>
        <button onclick="openTxnModal('Debt')" class="bg-red-500 text-white px-6 py-3 rounded-xl shadow-lg shadow-red-900/10 font-bold text-xs h-[46px] hover:bg-red-600 transition active:scale-95">
            <i class="fas fa-file-invoice-dollar mr-2"></i> OUTSTANDING DEBT
        </button>
    </div>
</div>

<!-- UI Table -->
<div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden glass mb-6">
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
                    <th class="p-6">Reference</th>
                    <th class="p-6">Due Date</th>
                    <th class="p-6 text-right text-purple-600">Balance</th> 
                    <th class="p-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50" id="ledgerBody">
                <!-- JS Rendered -->
            </tbody>
        </table>
    </div>
</div>

</div>

<!-- Transaction Modal -->
<div id="txnModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 transform transition-all">
        <div class="flex justify-between items-center mb-4">
            <h3 id="txnModalTitle" class="text-lg font-bold text-gray-800">Record Transaction</h3>
            <button onclick="closeTxnModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        
        <!-- Outstanding Balance Display -->
        <div id="modalDebtDisplay" class="mb-4 p-3 bg-red-50 border border-red-100 rounded-lg">
            <div class="flex justify-between items-center">
                <span class="text-xs font-bold text-red-600 uppercase tracking-wider">Outstanding Balance</span>
                <span id="modalDebtAmount" class="text-xl font-black text-red-700">Rs. 0</span>
            </div>
        </div>
        
        <form method="POST" class="space-y-4" onsubmit="return validateTransaction()" enctype="multipart/form-data">
            <input type="hidden" name="type" id="modalTxnType">
            <input type="hidden" name="txn_id" id="modalTxnId">
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                <input type="date" name="txn_date" id="modalTxnDate" required class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            </div>
            
            <div>
                <label id="amountLabel" class="block text-xs font-bold text-gray-500 uppercase mb-2">Amount</label>
                
                <!-- Pay in Full Checkbox -->
                <div id="payInFullWrapper" class="flex items-center gap-2 mb-2">
                    <input type="checkbox" id="payInFullCheckbox" onchange="handlePayInFull(this.checked)" class="w-4 h-4 text-teal-600 border-gray-300 rounded focus:ring-2 focus:ring-teal-500">
                    <label for="payInFullCheckbox" class="text-sm font-bold text-teal-600 cursor-pointer">Pay in Full (Clear all debt)</label>
                </div>
                
                <input type="number" name="amount" id="modalTxnAmount" step="0.01" required class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none" placeholder="0.00">
                <p id="amountError" class="hidden mt-1 text-xs text-red-600 font-bold">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Amount cannot exceed outstanding balance!
                </p>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Notes (Optional)</label>
                <textarea name="notes" id="modalTxnNotes" rows="2" class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none resize-none" placeholder="Details..."></textarea>
            </div>

            <div id="dueDateField" class="hidden">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Due Date (Optional)</label>
                <input type="date" name="due_date" id="modalDueDate" class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none">
            </div>
            
            <div id="paymentFields" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Type</label>
                    <select name="payment_type" id="modalPaymentType" class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="Cheque">By Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Proof <span class="text-[9px] lowercase">(Optional)</span></label>
                    <input type="file" name="payment_proof" id="modalPaymentProof" class="w-full p-1 border border-gray-300 rounded-lg text-[10px] focus:ring-2 focus:ring-teal-500 outline-none">
                    <input type="hidden" name="existing_proof" id="modalExistingProof">
                </div>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeTxnModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-bold text-sm hover:bg-gray-300 transition">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-teal-600 text-white rounded-lg font-bold text-sm hover:bg-teal-700 transition">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Printable Area (Hidden from UI, used for PDF) -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #0f766e; margin: 0; font-size: 28px;">Fashion Shines</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Management System</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333;">Customer Ledger Report</h2>
                <p style="color: #888; margin: 5px 0 0 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <div style="display: flex; gap: 40px; margin-bottom: 30px;">
            <div style="flex: 1; background: #f0fdfa; padding: 15px; border-radius: 8px; border-left: 4px solid #0f766e;">
                <h4 style="margin: 0 0 10px 0; color: #0f766e; text-transform: uppercase; font-size: 11px;">Customer Details</h4>
                <p style="margin: 0; font-weight: bold; font-size: 16px;"><?= htmlspecialchars($customer['name']) ?></p>
                <p style="margin: 5px 0; color: #555;"><?= htmlspecialchars($customer['phone']) ?></p>
                <p style="margin: 0; color: #888; font-size: 11px;"><?= htmlspecialchars($customer['address']) ?></p>
            </div>
            <div style="flex: 1; background: #fff1f2; padding: 15px; border-radius: 8px; border-left: 4px solid #e11d48; text-align: right;">
                <h4 style="margin: 0 0 10px 0; color: #e11d48; text-transform: uppercase; font-size: 11px;">Outstanding Balance</h4>
                <p id="printTotalDue" style="margin: 0; font-weight: bold; font-size: 24px; color: #e11d48;"><?= formatCurrency($total_due) ?></p>
                <p id="printDateRange" style="margin: 5px 0 0 0; font-size: 10px; color: #991b1b; display: none;"></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #0f766e; color: #fff;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; width: 40px; font-size: 11px;">Sr #</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Date</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Description</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Debit (Sale)</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Credit (Paid)</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Reference</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Due Date</th>
                </tr>
            </thead>
            <tbody id="printBody" style="vertical-align: top;">
                <!-- JS Populated -->
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="6" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 11px;">Total / Balance Due:</td>
                    <td id="printFooterTotal" style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #e11d48; font-size: 16px;"><?= formatCurrency($total_due) ?></td>
                </tr>
            </tfoot>
        </table>
        <div style="border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; text-align: center; font-size: 10px; color: #888;">
            <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>


<script>
    // Pass PHP data to JS
    const allTxns = <?= json_encode($ledger) ?>;
    const salesMap = <?= json_encode($sales_map) ?>;
    const saleItemsMap = <?= json_encode($sale_items_grouped) ?>;
    const productsMap = <?= json_encode($products_map) ?>;
    const initialBalance = <?= $total_due ?>;

    // Helper for currency formatting
    const formatCurrency = (amount) => {
        return 'Rs.' + new Intl.NumberFormat('en-US').format(amount);
    };

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
            return; // Custom
        }
        
        // Format YYYY-MM-DD
        const fmt = d => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        };
        
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

    function renderTable() {
        const dateFromVal = document.getElementById('dateFrom').value;
        const dateToVal = document.getElementById('dateTo').value;
        
        // Filter Data
        let filteredInfo = filterTransactions(allTxns, dateFromVal, dateToVal);
        const { finalTxns, openingBalance, stats } = filteredInfo; // finalTxns is newest first

        // Update Stats UI
        if(document.getElementById('statTotalDue')) document.getElementById('statTotalDue').innerText = formatCurrency(stats.balance);
        
        const badge = document.getElementById('debtClearedBadge');
        if(badge) {
            if(stats.balance <= 0) badge.classList.remove('hidden');
            else badge.classList.add('hidden');
        }

        // Update Print Header Stats
        if(document.getElementById('printTotalDue')) document.getElementById('printTotalDue').innerText = formatCurrency(stats.balance);
        if(document.getElementById('printFooterTotal')) document.getElementById('printFooterTotal').innerText = formatCurrency(stats.balance);
        
        const dateRangeText = document.getElementById('printDateRange');
        if(dateRangeText) {
            if(dateFromVal || dateToVal) {
                dateRangeText.innerText = `Filtered: ${dateFromVal || 'Start'} to ${dateToVal || 'End'}`;
                dateRangeText.style.display = 'block';
            } else {
                dateRangeText.style.display = 'none';
            }
        }

        // Render UI Table
        const uiHtml = generateTableRows(finalTxns, openingBalance, dateFromVal, false);
        document.getElementById('ledgerBody').innerHTML = uiHtml;

        // Render Print Table
        // For Print, customers usually expect Sr # ascending, or Date Ascending?
        // The original PHP code used `array_reverse($ledger)` which suggests Oldest First if ledger was Newest First.
        // Or if $ledger was sorted Oldest->Newest, array_reverse makes it Newest->Oldest.
        // My filterTransactions returns Descending (Newest First).
        // Let's stick to Newest First for UI, and allow generateTableRows to handle the order if needed.
        // Actually, for print, let's keep the same order as UI for consistency, OR reverse it if that's standard.
        // Let's stick to UI order (Newest First) for now, as that's what Dealer Ledger does.
        const printHtml = generateTableRows(finalTxns, openingBalance, dateFromVal, true);
        document.getElementById('printBody').innerHTML = printHtml;
    }

    /* Strict String-Based Filter */
    function filterTransactions(txns, fromDate, toDate) {
        let opening = 0;
        let validTxns = [];
        
        // Sort Ascending purely for calculation
        let sorted = [...txns].sort((a, b) => {
            return (a.date < b.date) ? -1 : ((a.date > b.date) ? 1 : 0);
        });
        
        let rangeDebit = 0;
        let rangeCredit = 0;

        sorted.forEach(t => {
            // YYYY-MM-DD extraction
            const tDate = t.date.substring(0, 10);
            
            // Strict String Comparison
            if (fromDate && tDate < fromDate) {
                opening += parseFloat(t.debit || 0) - parseFloat(t.credit || 0);
            } else if (toDate && tDate > toDate) {
                // Skip future transactions
            } else {
                validTxns.push(t);
            }
        });

        // Calculate Running Balance for Range
        let running = opening;
        validTxns.forEach(t => {
            running += parseFloat(t.debit || 0);
            running -= parseFloat(t.credit || 0);
            rangeDebit += parseFloat(t.debit || 0);
            rangeCredit += parseFloat(t.credit || 0);
            t.current_running_balance = running; 
        });

        // Return descending for list (UI expects newest first)
        return {
            finalTxns: validTxns.reverse(),
            openingBalance: opening,
            stats: {
                totalDebit: rangeDebit,
                totalCredit: rangeCredit,
                balance: opening + (rangeDebit - rangeCredit)
            }
        };
    }

    function generateTableRows(list, opening, fromDate, isPrint) {
        let html = '';
        
        // Opening Balance Row
        if (opening !== 0) {
            // Check formatted date
            let openingDateStr = fromDate;
            try {
                if(fromDate) {
                     const parts = fromDate.split('-');
                     const d = new Date(parts[0], parts[1]-1, parts[2]);
                     openingDateStr = d.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
                } else {
                    openingDateStr = 'Start';
                }
            } catch(e) {}
            
            const dateLabel = fromDate ? `(Before ${openingDateStr})` : '';
            const bgClass = isPrint ? '' : 'bg-gray-50/50';
            const cellStyle = isPrint ? 'padding: 8px; border: 1px solid #ddd; font-size: 11px;' : 'p-6';
            const balStyle = isPrint ? 'padding: 8px; border: 1px solid #ddd; text-align: right; color: #e11d48; font-weight: bold; font-size: 11px;' : 'p-6 text-right font-black text-red-600';
            
            if(isPrint) {
                 html += `<tr>
                    <td colspan="6" style="${cellStyle} font-style: italic; color: #666;">Opening Balance ${dateLabel}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #e11d48; font-size: 11px; font-weight: bold;">${formatCurrency(opening)}</td>
                </tr>`;
            } else {
                 html += `<tr class="${bgClass}">
                    <td colspan="7" class="${cellStyle} text-xs font-bold text-gray-500 uppercase tracking-widest">Opening Balance ${dateLabel}</td>
                    <td class="${balStyle}">${formatCurrency(opening)}</td>
                    <td class="p-6"></td>
                </tr>`;
            }
        }

        if (list.length === 0 && opening === 0) {
            html += `<tr><td colspan="${isPrint ? 7 : 9}" style="padding: 50px; text-align: center; color: #999;">No transactions found for this period.</td></tr>`;
            return html;
        }

        list.forEach((t, index) => {
            const dateObj = new Date(t.date);
            const displayDate = dateObj.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
            
            // Re-calc Sr # based on reverse index? Or just use Loop Index + 1?
            // Since it's descending, usually Sr# 1 is the latest.
            const sn = index + 1;

            const dueDateDisplay = t.due_date ? new Date(t.due_date).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) : '-';

            if (isPrint) {
                html += `<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: center;">${sn}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${displayDate}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; font-weight: 600;">${t.description}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right; color: #e11d48;">${parseFloat(t.debit) > 0 ? formatCurrency(parseFloat(t.debit)) : '-'}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right; color: #059669;">${parseFloat(t.credit) > 0 ? formatCurrency(parseFloat(t.credit)) : '-'}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${t.description}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${dueDateDisplay}</td>
                </tr>`;
            } else {
                let productsInfo = '';
                let remarks = '-';
                
                if (t.type === 'Sale' && t.sale_id) {
                    const sale = salesMap[t.sale_id];
                    const items = saleItemsMap[t.sale_id] || [];
                    
                    if (sale && sale.remarks) remarks = sale.remarks;
                    
                    if (items.length > 0) {
                        productsInfo = items.map(item => {
                            const pName = productsMap[item.product_id] || 'Unknown Product';
                            return `<div class="flex items-start justify-between gap-2 text-[11px] mb-1.5 border-b border-gray-50 pb-1 last:border-0">
                                        <span class="font-bold text-gray-700 flex-1 leading-tight">${pName}</span>
                                        <span class="text-teal-600 font-black whitespace-nowrap">x ${item.quantity}</span>
                                    </div>`;
                        }).join('');
                    } else {
                        productsInfo = `<span class="text-xs text-gray-400 italic">${t.description}</span>`;
                    }
                } else {
                    productsInfo = `<div class="text-sm font-bold text-purple-600">${t.description}</div>`;
                }

                html += `<tr class="hover:bg-purple-50/30 transition border-b border-gray-50 last:border-0 group">
                    <td class="p-6 text-center text-xs font-mono text-gray-400 italic align-top">${sn}</td>
                    <td class="p-6 align-top">
                        <span class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-1 rounded-md uppercase">${displayDate}</span>
                    </td>
                    <td class="p-6 align-top">
                        <div class="space-y-1 max-w-xs max-h-[220px] overflow-y-auto pr-2 custom-scrollbar">${productsInfo}</div>
                    </td>
                    <td class="p-6 text-right font-black text-gray-700 align-top">
                        ${parseFloat(t.debit) > 0 ? formatCurrency(parseFloat(t.debit)) : '<span class="text-gray-200">-</span>'}
                    </td>
                    <td class="p-6 text-right font-black text-emerald-600 align-top">
                        ${parseFloat(t.credit) > 0 ? formatCurrency(parseFloat(t.credit)) : '<span class="text-gray-200">-</span>'}
                    </td>
                    <td class="p-6 align-top">
                        <div class="text-[10px] text-gray-500 font-bold leading-relaxed line-clamp-2 max-w-[180px]" title="${remarks}">${remarks}</div>
                        ${t.payment_type ? `<div class="mt-1 flex items-center gap-2">
                            <span class="text-[9px] bg-teal-50 text-teal-600 px-1.5 py-0.5 rounded border border-teal-100 uppercase font-black">${t.payment_type}</span>
                            ${t.payment_proof ? `<a href="../uploads/payments/${t.payment_proof}" target="_blank" class="text-blue-500 hover:text-blue-700 text-[10px]"><i class="fas fa-paperclip"></i> Proof</a>` : ''}
                        </div>` : ''}
                    </td>
                    <td class="p-6 align-top">
                        ${t.due_date ? `
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-orange-50 text-orange-700 border border-orange-100 font-bold text-[10px]">
                                <i class="fas fa-calendar-alt text-[10px]"></i>
                                ${dueDateDisplay}
                            </span>
                        ` : '-'}
                    </td>
                    <td class="p-6 text-right font-black text-red-600 bg-red-50/20 align-top">
                        ${formatCurrency(t.current_running_balance)}
                    </td>
                    <td class="p-6 text-center align-top">
                         <div class="flex justify-center items-center gap-1">
                                ${t.type === 'Payment' ? `
                               <button onclick="editTransaction({id:'${t.id}', amount:'${t.credit}', date:'${t.date.substring(0,10)}', description:'${t.description}', type:'Payment', payment_type:'${t.payment_type || 'Cash'}', payment_proof:'${t.payment_proof || ''}'})" class="w-8 h-8 flex items-center justify-center text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Edit Payment">
                                   <i class="fas fa-edit"></i>
                               </button>
                               ` : (t.type === 'Debt' ? `
                               <button onclick="editTransaction({id:'${t.id}', amount:'${t.debit}', date:'${t.date.substring(0,10)}', description:'${t.description}', type:'Debt'})" class="w-8 h-8 flex items-center justify-center text-blue-500 hover:bg-blue-50 rounded-lg transition" title="Edit Debt">
                                   <i class="fas fa-edit"></i>
                               </button>
                               ` : '')}
                               
                               <button onclick="openTxnModal('Payment')" class="w-8 h-8 flex items-center justify-center text-green-500 hover:bg-green-50 rounded-lg transition" title="Receive Payment">
                                   <i class="fas fa-hand-holding-usd"></i>
                               </button>
                               
                               ${t.type === 'Sale' ? `
                               <a href="print_bill.php?id=${t.sale_id}" target="_blank" class="w-8 h-8 flex items-center justify-center text-teal-500 hover:bg-teal-50 rounded-lg transition" title="Print Bill">
                                   <i class="fas fa-print"></i>
                               </a>
                               <button onclick="revertSale('${t.id}', '${t.sale_id}')" class="w-8 h-8 flex items-center justify-center text-orange-500 hover:bg-orange-50 rounded-lg transition" title="Revert Sale (Restore Inventory)">
                                   <i class="fas fa-undo"></i>
                               </button>
                               ` : ''}

                               <button onclick="confirmDelete('customer_ledger.php?id=<?= $cid ?>&delete_txn=${t.id}')" class="w-8 h-8 flex items-center justify-center text-red-400 hover:bg-red-50 rounded-lg transition" title="Delete record ONLY">
                                   <i class="fas fa-trash"></i>
                               </button>
                         </div>
                    </td>
                </tr>`;
            }
        });
        
        return html;
    }

    function editTransaction(data) {
        const currentDebt = calculateCurrentDebt();
        document.getElementById('modalDebtAmount').innerText = formatCurrency(currentDebt);
        
        document.getElementById('modalTxnType').value = data.type;
        document.getElementById('txnModalTitle').innerText = "Edit " + data.type;
        document.getElementById('modalTxnId').value = data.id;
        document.getElementById('modalTxnDate').value = data.date;
        document.getElementById('modalTxnAmount').value = data.amount;
        document.getElementById('modalTxnNotes').value = data.description.replace(data.type === 'Debt' ? "Previous Debt: " : "Payment Received: ", "");
        document.getElementById('modalPaymentType').value = data.payment_type || 'Cash';
        document.getElementById('modalExistingProof').value = data.payment_proof || '';
        document.getElementById('modalDueDate').value = data.due_date || '';
        document.getElementById('payInFullCheckbox').checked = false;
        
        // UI Adjustments
        const dueDateField = document.getElementById('dueDateField');
        if (data.type === 'Payment') {
            document.getElementById('modalDebtDisplay').classList.remove('hidden');
            document.getElementById('payInFullWrapper').classList.remove('hidden');
            document.getElementById('paymentFields').classList.remove('hidden');
            dueDateField.classList.add('hidden');
            document.getElementById('modalDueDate').required = false;
            document.getElementById('amountLabel').innerText = "Amount Received";
        } else {
            document.getElementById('modalDebtDisplay').classList.add('hidden');
            document.getElementById('payInFullWrapper').classList.add('hidden');
            document.getElementById('paymentFields').classList.add('hidden');
            dueDateField.classList.remove('hidden');
            document.getElementById('modalDueDate').required = true;
            document.getElementById('amountLabel').innerText = "Debt Amount";
        }
        
        document.getElementById('txnModal').classList.remove('hidden');
    }

    function openTxnModal(type) {
        const currentDebt = calculateCurrentDebt();
        document.getElementById('modalDebtAmount').innerText = formatCurrency(currentDebt);
        
        document.getElementById('modalTxnType').value = type;
        document.getElementById('txnModalTitle').innerText = (type === 'Debt' ? "Record Outstanding Debt" : "Receive Payment");
        document.getElementById('modalTxnId').value = '';
        document.getElementById('modalTxnDate').value = '<?= date('Y-m-d') ?>';
        document.getElementById('modalTxnAmount').value = '';
        document.getElementById('modalTxnNotes').value = '';
        document.getElementById('modalPaymentType').value = 'Cash';
        document.getElementById('modalExistingProof').value = '';
        document.getElementById('modalPaymentProof').value = '';
        document.getElementById('modalDueDate').value = '';
        document.getElementById('payInFullCheckbox').checked = false;
        
        // Reset readOnly if it was set by Pay in Full
        const amountInput = document.getElementById('modalTxnAmount');
        amountInput.readOnly = false;
        amountInput.classList.remove('bg-gray-100');
        
        // UI Adjustments
        const dueDateField = document.getElementById('dueDateField');
        if (type === 'Payment') {
            document.getElementById('modalDebtDisplay').classList.remove('hidden');
            document.getElementById('payInFullWrapper').classList.remove('hidden');
            document.getElementById('paymentFields').classList.remove('hidden');
            dueDateField.classList.add('hidden');
            document.getElementById('modalDueDate').required = false;
            document.getElementById('amountLabel').innerText = "Amount Received";
        } else {
            document.getElementById('modalDebtDisplay').classList.add('hidden');
            document.getElementById('payInFullWrapper').classList.add('hidden');
            document.getElementById('paymentFields').classList.add('hidden');
            dueDateField.classList.remove('hidden');
            document.getElementById('modalDueDate').required = true;
            document.getElementById('amountLabel').innerText = "Debt Amount";
        }
        
        document.getElementById('txnModal').classList.remove('hidden');
    }
    
    function handlePayInFull(checked) {
        const currentDebt = calculateCurrentDebt();
        const amountInput = document.getElementById('modalTxnAmount');
        
        if (checked) {
            amountInput.value = currentDebt.toFixed(2);
            amountInput.readOnly = true;
            amountInput.classList.add('bg-gray-100');
        } else {
            amountInput.value = '';
            amountInput.readOnly = false;
            amountInput.classList.remove('bg-gray-100');
        }
    }
    
    function calculateCurrentDebt() {
        let debt = 0;
        allTxns.forEach(t => {
            debt += (parseFloat(t.debit) || 0) - (parseFloat(t.credit) || 0);
        });
        return Math.max(0, debt);
    }

    function closeTxnModal() {
        document.getElementById('txnModal').classList.add('hidden');
        document.getElementById('amountError').classList.add('hidden');
    }
    
    function validateTransaction() {
        const type = document.getElementById('modalTxnType').value;
        const dueDate = document.getElementById('modalDueDate').value;
        
        if (type === 'Debt' && !dueDate) {
            showAlert("Expected payment date is mandatory for debt records.", "Error");
            return false;
        }

        if (type === 'Payment') {
            const amount = parseFloat(document.getElementById('modalTxnAmount').value) || 0;
            const currentDebt = calculateCurrentDebt();
            const errorMsg = document.getElementById('amountError');
            
            if (amount > currentDebt + 1) { // 1 unit buffer for floats
                errorMsg.classList.remove('hidden');
                document.getElementById('modalTxnAmount').classList.add('border-red-500');
                showAlert(`Payment amount (Rs. ${amount.toFixed(2)}) cannot exceed outstanding balance (Rs. ${currentDebt.toFixed(2)})!`, 'Overpayment Not Allowed');
                return false;
            }
        }
        
        return true;
    }

    function revertSale(txnId, saleId) {
        showConfirm("REVERT SALE: This will DELETE this sale and RESTORE the product quantities back to your inventory. Are you sure?", function() {
            window.location.href = `../actions/revert_transaction.php?txn_id=${txnId}&sale_id=${saleId}&cid=<?= $cid ?>`;
        });
    }

    function confirmDelete(url) {
        showConfirm("Are you sure you want to delete this entry? This action cannot be undone.", function() {
            window.location.href = url;
        });
    }



    function printReport() {
        const element = document.getElementById('printableArea');
        const content = element.innerHTML;

        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Print Ledger</title><style>body { font-family: sans-serif; }</style></head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    }
    
    // Init Object
    document.addEventListener('DOMContentLoaded', () => {
       renderTable();
    });
</script>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
