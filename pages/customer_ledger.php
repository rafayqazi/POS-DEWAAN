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

// Handle Payment (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    $amount = (float)$_POST['amount'];
    $date = $_POST['payment_date'];
    $notes = cleanInput($_POST['notes']);

    if (isset($_POST['txn_id']) && !empty($_POST['txn_id'])) {
        updateCSV('customer_transactions', $_POST['txn_id'], [
            'credit' => $amount,
            'date' => $date,
            'description' => "Payment Received: " . $notes
        ]);
    } else {
        insertCSV('customer_transactions', [
            'customer_id' => $cid,
            'type' => 'Payment',
            'debit' => 0,
            'credit' => $amount,
            'description' => "Payment Received: " . $notes,
            'date' => $date,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    redirect("customer_ledger.php?id=$cid" . ($from_date ? "&from=$from_date" : "") . ($to_date ? "&to=$to_date" : ""));
}

$pageTitle = "Ledger: " . $customer['name'];
include '../includes/header.php';

// Fetch all transactions for this customer
$all_txns = readCSV('customer_transactions');
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
    </div>
    
    <div class="flex gap-3">
        <button onclick="downloadPDF()" class="bg-red-500 text-white px-5 py-3 rounded-xl hover:bg-red-600 shadow-lg shadow-red-900/10 font-bold text-xs h-[46px] flex items-center transition active:scale-95">
            <i class="fas fa-file-pdf mr-2"></i> PDF
        </button>
        <button onclick="printReport()" class="bg-blue-500 text-white px-5 py-3 rounded-xl hover:bg-blue-600 shadow-lg shadow-blue-900/10 font-bold text-xs h-[46px] flex items-center transition active:scale-95">
            <i class="fas fa-print mr-2"></i> PRINT
        </button>
        <button onclick="openPayModal()" class="bg-primary text-white px-6 py-3 rounded-xl shadow-lg shadow-teal-900/10 font-bold text-xs h-[46px] hover:bg-secondary transition active:scale-95">
            <i class="fas fa-hand-holding-usd mr-2"></i> RECEIVE PAYMENT
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
                    <th class="p-6">Description</th>
                    <th class="p-6 text-right">Debit (Sale)</th>
                    <th class="p-6 text-right">Credit (Paid)</th>
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

<!-- Printable Area (Hidden from UI, used for PDF) -->
<div id="printableArea" class="hidden">
    <!-- Demo Watermark -->
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 40px; color: rgba(200, 200, 200, 0.3); font-weight: bold; text-align: center; z-index: -1; pointer-events: none; white-space: nowrap; width: 100%;">
        THIS APPLICATION IS FOR DEMO<br>
        CONTACT DEVELOPER: 0300-0358189<br>
        abdulrafehqazi@gmail.com
    </div>
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #6b21a8; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #6b21a8; margin: 0; font-size: 28px;">DEWAAN</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Fertilizers & Pesticides Management System</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333;">Customer Ledger Report</h2>
                <p style="color: #888; margin: 5px 0 0 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <div style="display: flex; gap: 40px; margin-bottom: 30px;">
            <div style="flex: 1; background: #faf5ff; padding: 15px; border-radius: 8px; border-left: 4px solid #6b21a8;">
                <h4 style="margin: 0 0 10px 0; color: #6b21a8; text-transform: uppercase; font-size: 11px;">Customer Details</h4>
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
                <tr style="background: #6b21a8; color: #fff;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; width: 40px; font-size: 11px;">Sr #</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Date</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Description</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Debit (Sale)</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Credit (Paid)</th>
                </tr>
            </thead>
            <tbody id="printBody">
                <!-- JS Populated -->
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="3" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 11px;">Total / Balance Due:</td>
                    <td colspan="2" id="printFooterTotal" style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #e11d48; font-size: 16px;"><?= formatCurrency($total_due) ?></td>
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
    const allTxns = <?= json_encode($ledger) ?>;
    const initialBalance = <?= $total_due ?>;

    // Helper for currency formatting
    const formatCurrency = (amount) => {
        return 'Rs.' + new Intl.NumberFormat('en-US').format(amount);
    };

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
                    <td colspan="4" style="${cellStyle} font-style: italic; color: #666;">Opening Balance ${dateLabel}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #e11d48; font-size: 11px; font-weight: bold;">${formatCurrency(opening)}</td>
                </tr>`;
            } else {
                 html += `<tr class="${bgClass}">
                    <td colspan="4" class="${cellStyle} text-xs font-bold text-gray-500 uppercase tracking-widest">Opening Balance ${dateLabel}</td>
                    <td class="${balStyle}">${formatCurrency(opening)}</td>
                    <td class="p-6"></td>
                </tr>`;
            }
        }

        if (list.length === 0 && opening === 0) {
            html += `<tr><td colspan="${isPrint ? 5 : 6}" style="padding: 50px; text-align: center; color: #999;">No transactions found for this period.</td></tr>`;
            return html;
        }

        list.forEach((t, index) => {
            const dateObj = new Date(t.date);
            const displayDate = dateObj.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
            
            // Re-calc Sr # based on reverse index? Or just use Loop Index + 1?
            // Since it's descending, usually Sr# 1 is the latest.
            const sn = index + 1;

            if (isPrint) {
                html += `<tr>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: center;">${sn}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">${displayDate}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; font-weight: 600;">${t.description}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right; color: #e11d48;">${parseFloat(t.debit) > 0 ? formatCurrency(parseFloat(t.debit)) : '-'}</td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right; color: #059669;">${parseFloat(t.credit) > 0 ? formatCurrency(parseFloat(t.credit)) : '-'}</td>
                </tr>`;
            } else {
                html += `<tr class="hover:bg-purple-50/30 transition border-b border-gray-50 last:border-0 group">
                    <td class="p-6 text-center text-xs font-mono text-gray-400 italic">${sn}</td>
                    <td class="p-6">
                        <span class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-1 rounded-md uppercase">${displayDate}</span>
                    </td>
                    <td class="p-6">
                        <div class="text-sm font-bold text-gray-800">${t.description}</div>
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
                               <button onclick="editPayment({id:'${t.id}', credit:'${t.credit}', date:'${t.date.substring(0,10)}', description:'${t.description}'})" class="text-blue-500 hover:text-blue-700 p-1" title="Edit">
                                   <i class="fas fa-edit"></i>
                               </button>
                               <button onclick="confirmDelete('customer_ledger.php?id=<?= $cid ?>&delete_txn=${t.id}')" class="text-red-500 hover:text-red-700 p-1" title="Delete">
                                   <i class="fas fa-trash"></i>
                               </button>
                         </div>
                    </td>
                </tr>`;
            }
        });
        
        return html;
    }

    function editPayment(data) {
        document.getElementById('payModalTitle').innerText = "Edit Payment Entry";
        document.getElementById('txn_id').value = data.id;
        document.getElementById('paymentDate').value = data.date;
        document.getElementById('paymentAmount').value = data.credit;
        document.getElementById('paymentNotes').value = data.description.replace("Payment Received: ", "");
        document.getElementById('payModal').classList.remove('hidden');
    }

    function openPayModal() {
        document.getElementById('payModalTitle').innerText = "Receive Payment";
        document.getElementById('txn_id').value = '';
        document.getElementById('paymentDate').value = '<?= date('Y-m-d') ?>';
        document.getElementById('paymentAmount').value = '';
        document.getElementById('paymentNotes').value = '';
        document.getElementById('payModal').classList.remove('hidden');
    }

    function closePayModal() {
        document.getElementById('payModal').classList.add('hidden');
    }

    function confirmDelete(url) {
        showConfirm("Are you sure you want to delete this entry? This action cannot be undone.", function() {
            window.location.href = url;
        });
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
            filename:     'Ledger_<?= str_replace(' ', '_', $customer['name'] ?? 'Customer') ?>.pdf',
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
    
    // Init Object
    document.addEventListener('DOMContentLoaded', () => {
       renderTable();
    });
</script>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
