<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['id'])) {
    redirect('dealers.php');
}

$dealer_id = $_GET['id'];
$dealer = findCSV('dealers', $dealer_id);

if (!$dealer) die("Dealer not found");

// Handle Date Filtering
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'];
    $amount = (float)$_POST['amount'];
    
    $data = [
        'dealer_id' => $dealer_id,
        'type' => $type,
        'debit' => ($type == 'Purchase' || $type == 'Debt') ? $amount : 0,
        'credit' => ($type == 'Payment') ? $amount : 0,
        'description' => cleanInput($_POST['description']),
        'date' => $_POST['date']
    ];
    
    if (isset($_POST['txn_id']) && !empty($_POST['txn_id'])) {
        updateCSV('dealer_transactions', $_POST['txn_id'], $data);
    } else {
        $data['created_at'] = date('Y-m-d H:i:s');
        insertCSV('dealer_transactions', $data);
    }
    
    redirect("dealer_ledger.php?id=$dealer_id" . ($from_date ? "&from=$from_date" : "") . ($to_date ? "&to=$to_date" : ""));
}

// Handle Transaction Deletion
if (isset($_GET['delete_txn'])) {
    $del_id = $_GET['delete_txn'];
    $txn = findCSV('dealer_transactions', $del_id);
    
    if ($txn && $txn['dealer_id'] == $dealer_id) {
        // Check for linked Restock/Initial ID
        if (!empty($txn['restock_id'])) {
            $restock = findCSV('restocks', $txn['restock_id']);
            
            if ($restock) {
                // Revert Product Stock
                $product_id = $restock['product_id'];
                $qty_to_remove = (float)$restock['quantity'];
                
                // Read products to find current stock
                $all_products = readCSV('products');
                $p_index = -1;
                foreach ($all_products as $idx => $p) {
                    if ($p['id'] == $product_id) {
                        $p_index = $idx;
                        break;
                    }
                }
                
                if ($p_index > -1) {
                    $current_stock = (float)$all_products[$p_index]['stock_quantity'];
                    $all_products[$p_index]['stock_quantity'] = max(0, $current_stock - $qty_to_remove); // Prevent negative stock
                    
                    // Save Product Update using helper
                    updateCSV('products', $product_id, ['stock_quantity' => $all_products[$p_index]['stock_quantity']]);
                }
                
                // Delete Restock Record
                deleteCSV('restocks', $restock['id']);
            }
        }
        
        // Delete Transaction
        deleteCSV('dealer_transactions', $del_id);
        redirect("dealer_ledger.php?id=$dealer_id&msg=Transaction deleted and stock reverted");
    }
}

$pageTitle = "Ledger: " . $dealer['name'];
include '../includes/header.php';

// Fetch Transactions
$all_txns = readCSV('dealer_transactions');
$list = [];

// Get all transactions for this dealer, no PHP filtering
foreach($all_txns as $t) {
    if($t['dealer_id'] == $dealer_id) {
        $list[] = $t;
    }
}

// Stats calculation (Initial total - for before JS kicks in, or just basic)
$total_debit = 0;
$total_credit = 0;
foreach ($list as $t) {
    $total_debit += (float)($t['debit'] ?? 0);
    $total_credit += (float)($t['credit'] ?? 0);
}
$current_balance = $total_debit - $total_credit;
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-amber-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Goods Value</h3>
        <p id="statTotalDebit" class="text-3xl font-black text-gray-800 tracking-tighter mt-1"><?= formatCurrency($total_debit) ?></p>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-emerald-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Paid</h3>
        <p id="statTotalCredit" class="text-3xl font-black text-gray-800 tracking-tighter mt-1"><?= formatCurrency($total_credit) ?></p>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-red-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Outstanding Debt</h3>
        <p id="statBalance" class="text-3xl font-black text-red-600 tracking-tighter mt-1"><?= formatCurrency($current_balance) ?></p>
    </div>
</div>

<div class="mb-6 flex flex-col md:flex-row justify-between items-end gap-4 bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 glass">
<!-- Top Filter Blocks -->
    <div class="flex flex-wrap items-end gap-3 flex-1">
        <input type="hidden" name="id" value="<?= $dealer_id ?>">
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Quick Range</label>
            <select onchange="applyQuickDate(this.value)" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none w-36 shadow-sm">
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
            <input type="date" id="dateFrom" onchange="renderTable()" value="<?= date('Y-m-01') ?>" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none shadow-sm">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" id="dateTo" onchange="renderTable()" value="<?= date('Y-m-d') ?>" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none shadow-sm">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">&nbsp;</label>
            <button onclick="clearFilters()" class="p-3 bg-gray-100 text-gray-500 rounded-xl text-xs font-bold hover:bg-gray-200 transition shadow-sm h-[42px] flex items-center">
                CLEAR
            </button>
        </div>
        <!-- No Filter Button - Realtime -->
        <button onclick="renderTable()" class="hidden"></button>
    </div>
    
    <div class="flex gap-3">
        <!-- ... Buttons ... -->
        <button onclick="downloadPDF()" class="bg-red-500 text-white px-5 py-3 rounded-xl hover:bg-red-600 shadow-lg shadow-red-900/10 font-bold text-xs h-[46px] flex items-center transition active:scale-95">
            <i class="fas fa-file-pdf mr-2"></i> PDF
        </button>
        <button onclick="printReport()" class="bg-blue-500 text-white px-5 py-3 rounded-xl hover:bg-blue-600 shadow-lg shadow-blue-900/10 font-bold text-xs h-[46px] flex items-center transition active:scale-95">
            <i class="fas fa-print mr-2"></i> PRINT
        </button>
        <button onclick="openModal('Payment')" class="bg-primary text-white px-6 py-3 rounded-xl shadow-lg shadow-teal-900/10 font-bold text-xs h-[46px] hover:bg-secondary transition active:scale-95">
            <i class="fas fa-plus mr-1"></i> PAYMENT
        </button>
        <button onclick="openModal('Debt')" class="bg-red-500 text-white px-6 py-3 rounded-xl shadow-lg shadow-red-900/10 font-bold text-xs h-[46px] hover:bg-red-600 transition active:scale-95">
            <i class="fas fa-file-invoice-dollar mr-1"></i> OUTSTANDING DEBT
        </button>
    </div>
</div>

<!-- Readable UI Table -->
<div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden glass">
    <div class="p-6 border-b border-gray-50 bg-gray-50/50">
        <h4 class="font-bold text-gray-800 flex items-center">
            <i class="fas fa-scroll text-amber-500 mr-2"></i> Transaction History
        </h4>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] border-b border-gray-100">
                <tr>
                    <th class="p-6 w-12 text-center">Sno#</th>
                    <th class="p-6">Date</th>
                    <th class="p-6">Description</th>
                    <th class="p-6 text-center">Type</th>
                    <th class="p-6 text-right">Debit (Goods)</th>
                    <th class="p-6 text-right">Credit (Paid)</th>
                    <th class="p-6 text-right text-amber-600">Balance (Debt)</th>
                    <th class="p-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50" id="ledgerBody">
                <!-- JS Rendered -->
            </tbody>
        </table>
    </div>
</div>

<!-- Transaction Modal -->
<div id="txnModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 transform transition-all scale-100 zoom-in-95">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-xl font-black text-gray-800">Record Transaction</h3>
            <button type="button" onclick="document.getElementById('txnModal').classList.add('hidden'); document.getElementById('txnModal').classList.remove('flex')" class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Debt Display -->
        <div id="modalDebtDisplay" class="hidden mb-6 bg-red-50 p-3 rounded-xl border border-red-100 flex justify-between items-center">
            <span class="text-xs font-bold text-red-800 uppercase tracking-wider">Current Outstanding Debt</span>
            <span id="modalDebtAmount" class="text-lg font-black text-red-600">Rs. 0</span>
        </div>
        
        <form method="POST" onsubmit="return validateTransaction()">
            <input type="hidden" name="id" value="<?= $dealer_id ?>">
            <input type="hidden" name="type" id="txnType">
            <input type="hidden" name="txn_id" id="txn_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Date</label>
                    <input type="date" name="date" id="txnDate" required class="w-full p-4 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 outline-none focus:ring-2 focus:ring-amber-500 transition-all text-sm">
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Amount</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Rs.</span>
                        <input type="number" step="any" name="amount" id="txnAmount" required class="w-full pl-12 p-4 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-800 text-lg outline-none focus:ring-2 focus:ring-amber-500 transition-all">
                    </div>
                    <div class="mt-2 flex items-center" id="payTotalWrapper">
                        <input type="checkbox" id="payTotalCheck" onchange="togglePayTotal()" class="w-4 h-4 text-amber-500 rounded border-gray-300 focus:ring-amber-500">
                        <label for="payTotalCheck" class="ml-2 text-xs font-bold text-gray-500 cursor-pointer select-none">Pay Full Outstanding Balance</label>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Description</label>
                    <textarea name="description" id="txnDesc" rows="3" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-xl font-bold text-gray-700 outline-none focus:ring-2 focus:ring-amber-500 transition-all resize-none text-sm"></textarea>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-bold py-4 rounded-xl mt-6 shadow-lg shadow-amber-500/30 transition-all active:scale-95 text-sm tracking-wide">
                SAVE TRANSACTION
            </button>
        </form>
    </div>
</div>

<!-- Print/PDF Hidden Area -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ea580c; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #ea580c; margin: 0; font-size: 28px;">DEWAAN</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Fertilizers & Pesticides Management System</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333;">Dealer Ledger Report</h2>
                <p style="color: #888; margin: 5px 0 0 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <div style="display: flex; gap: 40px; margin-bottom: 30px;">
            <div style="flex: 1; background: #fff7ed; padding: 15px; border-radius: 8px; border-left: 4px solid #ea580c;">
                <h4 style="margin: 0 0 10px 0; color: #ea580c; text-transform: uppercase; font-size: 11px;">Dealer Details</h4>
                <p style="margin: 0; font-weight: bold; font-size: 16px;"><?= htmlspecialchars($dealer['name']) ?></p>
                <p style="margin: 5px 0; color: #555;"><?= htmlspecialchars($dealer['phone'] ?? '') ?></p>
                <p style="margin: 0; color: #888; font-size: 11px;"><?= htmlspecialchars($dealer['address'] ?? '') ?></p>
            </div>
            <div style="flex: 1; background: #fef2f2; padding: 15px; border-radius: 8px; border-left: 4px solid #dc2626; text-align: right;">
                <h4 style="margin: 0 0 10px 0; color: #dc2626; text-transform: uppercase; font-size: 11px;">Outstanding Balance</h4>
                <p id="printTotalDue" style="margin: 0; font-weight: bold; font-size: 24px; color: #dc2626;"><?= formatCurrency($current_balance) ?></p>
                <p id="printDateRange" style="margin: 5px 0 0 0; font-size: 10px; color: #991b1b; display: none;"></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #ea580c; color: #fff;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; width: 40px; font-size: 11px;">Sr #</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Date</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Description</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Debit (Goods)</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Credit (Paid)</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Balance</th>
                </tr>
            </thead>
            <tbody id="printBody">
                <!-- JS Populated -->
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="4" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 11px;">Total / Balance Due:</td>
                    <td id="printFooterTotal" style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #dc2626; font-size: 16px;"><?= formatCurrency($current_balance) ?></td>
                </tr>
            </tfoot>
        </table>
        <div style="border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; text-align: center; font-size: 10px; color: #888;">
            <p style="margin: 0; font-weight: bold;">POS System Developed by Abdul Rafay - Contact: 0300-0358189</p>
            <p style="margin: 3px 0 0 0; font-style: italic;">Disclaimer: Unauthorized use of this software without developer consent is illegal.</p>
        </div>
    </div>
</div>

<script src="../assets/js/html2pdf.bundle.min.js"></script>
<script>
    // Pass PHP data to JS
    const allTxns = <?= json_encode($list) ?>;
    const initialBalance = <?= $current_balance ?>;
    let currentDebtValue = 0; // Store globally for validation

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
        
        // Format YYYY-MM-DD using local time
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
        const { finalTxns, openingBalance, stats } = filteredInfo;
        
        // Update Global Debt Value (Uses stats.balance which is current debt)
        currentDebtValue = stats.balance;

        // Update Stats Cards
        if(document.getElementById('statTotalDebit')) document.getElementById('statTotalDebit').innerText = formatCurrency(stats.totalDebit);
        if(document.getElementById('statTotalCredit')) document.getElementById('statTotalCredit').innerText = formatCurrency(stats.totalCredit);
        if(document.getElementById('statBalance')) document.getElementById('statBalance').innerText = formatCurrency(stats.balance);

        // Update Print Stats
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
        const printHtml = generateTableRows(finalTxns, openingBalance, dateFromVal, true);
        document.getElementById('printBody').innerHTML = printHtml;
    }

    /* Strict String-Based Filter */
    function filterTransactions(txns, fromDate, toDate) {
        let opening = 0;
        let validTxns = [];
        
        // Sort Ascending purely for calculation (optional but safer)
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
                // If dealer logic is Debit increase debt, Credit reduce debt
                // Balance = Total Debit - Total Credit.
                // Assuming Opening Balance is already "Net".
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
                     // parts[0] = YYYY, parts[1] = MM, parts[2] = DD
                     // Create date object (months are 0-indexed)
                     const d = new Date(parts[0], parts[1]-1, parts[2]);
                     openingDateStr = d.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
                } else {
                    openingDateStr = 'Start';
                }
            } catch(e) {}
            
            const dateLabel = fromDate ? `(Before ${openingDateStr})` : '';
            const bgClass = isPrint ? '' : 'bg-amber-50/50';
            const textClass = isPrint ? 'font-style: italic; color: #666; font-size: 11px;' : 'text-xs font-bold text-gray-500 uppercase tracking-widest';
            const cellStyle = isPrint ? 'padding: 8px; border: 1px solid #ddd; font-size: 11px;' : 'p-6';
            const balStyle = isPrint ? 'padding: 8px; border: 1px solid #ddd; text-align: right; color: #dc2626; font-weight: bold; font-size: 11px;' : 'p-6 text-right font-black text-red-600';
            
            html += `<tr class="${bgClass}">
                <td colspan="${isPrint ? 4 : 5}" style="${cellStyle}" class="${textClass}">Opening Balance ${dateLabel}</td>
                <td style="${balStyle}" class="${isPrint ? '' : 'p-6 text-right font-black text-red-600'}">${formatCurrency(opening)}</td>
                ${isPrint ? '' : '<td class="p-6"></td>'}
            </tr>`;
        }

        if (list.length === 0 && opening === 0) {
            html += `<tr><td colspan="${isPrint ? 5 : 7}" style="padding: 50px; text-align: center; color: #999;">No transactions found for this period.</td></tr>`;
            return html;
        }

        list.forEach((t, index) => {
            const dateObj = new Date(t.date);
            const sn = index + 1;
            const displayDate = dateObj.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
            
            let displayTime = '';
            // Attempt to parse time from timestamp if available
            if(t.date.includes(' ')) {
                displayTime = t.date.split(' ')[1]; // "HH:MM:SS" or similar
                 // Let's use created_at if available in JSON
                 if(t.created_at) {
                     const createdDate = new Date(t.created_at);
                     displayTime = createdDate.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                 }
            }
            
            if (isPrint) {
                html += `<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: center;">${sn}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${displayDate}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; font-weight: 600;">${t.description}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #ea580c; font-size: 11px;">${parseFloat(t.debit) > 0 ? formatCurrency(parseFloat(t.debit)) : '-'}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #059669; font-size: 11px;">${parseFloat(t.credit) > 0 ? formatCurrency(parseFloat(t.credit)) : '-'}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: #dc2626; font-weight: bold; font-size: 11px;">${formatCurrency(t.current_running_balance)}</td>
                </tr>`;
            } else {
                html += `<tr class="hover:bg-amber-50/30 transition border-b border-gray-50 last:border-0 group">
                    <td class="p-6 text-center text-xs font-mono text-gray-400 italic">${sn}</td>
                    <td class="p-6">
                        <span class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-1 rounded-md uppercase">${displayDate}</span>
                    </td>
                    <td class="p-6">
                        <div class="text-sm font-bold text-gray-800">${t.description}</div>
                        <div class="text-[9px] text-gray-400 font-semibold tracking-wider mt-0.5">${displayTime}</div>
                    </td>
                    <td class="p-6 text-center">
                        <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border ${t.type === 'Purchase' ? 'bg-orange-50 text-orange-600 border-orange-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100'}">
                            ${t.type}
                        </span>
                    </td>
                    <td class="p-6 text-right font-black text-gray-700">
                        ${parseFloat(t.debit) > 0 ? formatCurrency(parseFloat(t.debit)) : '<span class="text-gray-200">-</span>'}
                    </td>
                    <td class="p-6 text-right font-black text-emerald-600">
                        ${parseFloat(t.credit) > 0 ? formatCurrency(parseFloat(t.credit)) : '<span class="text-gray-200">-</span>'}
                    </td>
                    <td class="p-6 text-right font-black text-red-600 bg-red-50/20">
                        ${formatCurrency(t.current_running_balance)}
                    </td>
                    <td class="p-6 text-center">
                         <div class="flex justify-center space-x-2 transition-opacity">
                               <button onclick="prepareEdit('${t.id}')" class="text-blue-500 hover:text-blue-700 p-1" title="Edit">
                                   <i class="fas fa-edit"></i>
                               </button>
                               <button onclick="confirmDelete('dealer_ledger.php?id=<?= $dealer_id ?>&delete_txn=${t.id}')" class="text-red-500 hover:text-red-700 p-1" title="Delete">
                                   <i class="fas fa-trash"></i>
                               </button>
                         </div>
                    </td>
                </tr>`;
            }
        });
        
        return html;
    }

    function openModal(type) {
        document.getElementById('txnType').value = type;
        document.getElementById('txn_id').value = '';
        document.getElementById('txnDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('txnAmount').value = '';
        document.getElementById('txnDesc').value = '';
        document.getElementById('payTotalCheck').checked = false;
        
        let title = "Record Transaction";
        if(type == 'Purchase') title = "Record Goods";
        else if(type == 'Payment') title = "Record Payment";
        else if(type == 'Debt') title = "Record Outstanding Debt";
        
        document.getElementById('modalTitle').innerText = title;
        
        // Handle Debt Display for Payment
        const debtDisplay = document.getElementById('modalDebtDisplay');
        const payTotalWrapper = document.getElementById('payTotalWrapper');
        
        if (type == 'Payment') {
            debtDisplay.classList.remove('hidden');
            document.getElementById('modalDebtAmount').innerText = formatCurrency(currentDebtValue);
            if(payTotalWrapper) payTotalWrapper.classList.remove('hidden');
        } else {
            debtDisplay.classList.add('hidden');
            if(payTotalWrapper) payTotalWrapper.classList.add('hidden');
        }
        
        document.getElementById('txnModal').classList.remove('hidden');
        document.getElementById('txnModal').classList.add('flex');
    }
    
    function validateTransaction() {
        const type = document.getElementById('txnType').value;
        if (type === 'Payment') {
            const enteredAmount = parseFloat(document.getElementById('txnAmount').value) || 0;
            // Allow small buffer? No, strict.
            // If currentDebtValue < 0, it means dealer owes us? No, Debt is normally Payable (We owe dealer).
            // If balance is positive, we owe dealer.
            // If entered amount > currentDebtValue, we are paying more than we owe.
            
            if (enteredAmount > currentDebtValue) {
                showAlert(`You cannot pay more than the outstanding debt (${formatCurrency(currentDebtValue)}).`, 'Payment Limit Reached');
                return false;
            }
        }
        return true;
    }

    function togglePayTotal() {
        const check = document.getElementById('payTotalCheck');
        const amountInput = document.getElementById('txnAmount');
        // Use Global currentDebtValue instead of reading DOM
        
        if (check.checked) {
            // Only autofill if debt is positive
            if (currentDebtValue > 0) {
                amountInput.value = currentDebtValue.toFixed(2);
            } else {
                amountInput.value = '0';
            }
        } else {
            amountInput.value = '';
        }
    }

    function prepareEdit(id) {
        const txn = allTxns.find(t => t.id == id);
        if (txn) {
            editTxn(txn);
        }
    }

    function editTxn(data) {
        document.getElementById('txnType').value = data.type;
        document.getElementById('txn_id').value = data.id;
        document.getElementById('txnDate').value = data.date.substring(0, 10);
        document.getElementById('txnAmount').value = (Number(data.debit) > 0) ? data.debit : data.credit;
        document.getElementById('txnDesc').value = data.description;
        document.getElementById('payTotalCheck').checked = false;
        
        const title = "Edit " + data.type;
        document.getElementById('modalTitle').innerText = title;
        
        // Hide debt limits on edit? Or enforce? 
        // If editing, we might change history. Let's keep it simple and hide extra displays for Edit mode to avoid confusion, 
        // as "current debt" might change if we edit a past transaction.
        document.getElementById('modalDebtDisplay').classList.add('hidden');
        
        document.getElementById('txnModal').classList.remove('hidden');
        document.getElementById('txnModal').classList.add('flex');
    }
    
    // RE-INJECT FUNCTIONS AND INIT
    document.addEventListener('DOMContentLoaded', () => {
       renderTable(); // Initial Render
    });

    function confirmDelete(url) {
        showConfirm("Are you sure? If this transaction is linked to a restock or initial stock, the stock quantity will also be reverted.", function() {
            window.location.href = url;
        }, "Confirm Delete");
    }

    function downloadPDF() {
        const element = document.getElementById('printableArea');
        const container = document.createElement('div');
        container.style.position = 'fixed';
        container.style.left = '-9999px';
        container.style.top = '0';
        container.style.width = '800px'; 
        container.style.zIndex = '-9999';
        container.style.background = 'white';

        const clone = element.cloneNode(true);
        clone.classList.remove('hidden');
        clone.style.display = 'block';
        
        container.appendChild(clone);
        document.body.appendChild(container);

        const opt = {
            margin:       0.3,
            filename:     'Ledger_<?= str_replace(' ', '_', $dealer['name']) ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, logging: false },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(clone).save().then(() => {
            document.body.removeChild(container);
        }).catch(err => {
            console.error('PDF Error:', err);
            document.body.removeChild(container);
            alert('Could not generate PDF. Please use the Print option instead.');
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
</script>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
