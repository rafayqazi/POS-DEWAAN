<?php
session_start();
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
        'debit' => ($type == 'Purchase') ? $amount : 0,
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

$pageTitle = "Ledger: " . $dealer['name'];
include '../includes/header.php';

// Fetch Transactions
$all_txns = readCSV('dealer_transactions');
$list = [];
foreach($all_txns as $t) {
    if($t['dealer_id'] == $dealer_id) {
        if ($from_date && $t['date'] < $from_date) continue;
        if ($to_date && $t['date'] > $to_date) continue;
        $list[] = $t;
    }
}
// Sort by date desc
usort($list, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

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
        <p class="text-3xl font-black text-gray-800 tracking-tighter mt-1"><?= formatCurrency($total_debit) ?></p>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-emerald-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Paid</h3>
        <p class="text-3xl font-black text-gray-800 tracking-tighter mt-1"><?= formatCurrency($total_credit) ?></p>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-red-500 glass">
        <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Outstanding Debt</h3>
        <p class="text-3xl font-black text-red-600 tracking-tighter mt-1"><?= formatCurrency($current_balance) ?></p>
    </div>
</div>

<div class="mb-6 flex flex-col md:flex-row justify-between items-end gap-4 bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 glass">
    <form class="flex flex-wrap items-end gap-3 flex-1">
        <input type="hidden" name="id" value="<?= $dealer_id ?>">
        <div class="flex flex-col">
            <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Quick Range</label>
            <select onchange="applyQuickDate(this.value)" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none w-36 shadow-sm">
                <option value="">Custom</option>
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="last_90">Last 90 Days</option>
                <option value="last_year">Last 1 Year</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">From Date</label>
            <input type="date" name="from" id="dateFrom" value="<?= $from_date ?>" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none shadow-sm">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" name="to" id="dateTo" value="<?= $to_date ?>" class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none shadow-sm">
        </div>
        <button type="submit" class="bg-amber-600 text-white px-6 py-3 rounded-xl hover:bg-amber-700 transition shadow-lg shadow-amber-900/10 font-bold text-xs h-[46px] active:scale-95">
            <i class="fas fa-filter mr-1"></i> Filter
        </button>
        <?php if($from_date || $to_date): ?>
            <a href="dealer_ledger.php?id=<?= $dealer_id ?>" class="bg-gray-100 text-gray-500 px-6 py-3 rounded-xl hover:bg-gray-200 transition text-xs font-bold h-[46px] flex items-center">Reset</a>
        <?php endif; ?>
    </form>
    
    <div class="flex gap-3">
        <button onclick="downloadPDF()" class="bg-red-500 text-white px-5 py-3 rounded-xl hover:bg-red-600 shadow-lg shadow-red-900/10 font-bold text-xs h-[46px] flex items-center transition active:scale-95">
            <i class="fas fa-file-pdf mr-2"></i> PDF
        </button>
        <button onclick="printReport()" class="bg-blue-500 text-white px-5 py-3 rounded-xl hover:bg-blue-600 shadow-lg shadow-blue-900/10 font-bold text-xs h-[46px] flex items-center transition active:scale-95">
            <i class="fas fa-print mr-2"></i> PRINT
        </button>
        <button onclick="openModal('Payment')" class="bg-primary text-white px-6 py-3 rounded-xl shadow-lg shadow-teal-900/10 font-bold text-xs h-[46px] hover:bg-secondary transition active:scale-95">
            <i class="fas fa-plus mr-1"></i> ADD PAYMENT
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
                    <th class="p-6">Date</th>
                    <th class="p-6">Description</th>
                    <th class="p-6 text-center">Type</th>
                    <th class="p-6 text-right">Debit (Goods)</th>
                    <th class="p-6 text-right">Credit (Paid)</th>
                    <th class="p-6 text-right text-amber-600">Balance (Debt)</th>
                    <th class="p-6 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if(empty($list)): ?>
                    <tr><td colspan="7" class="p-20 text-center text-gray-400"><i class="fas fa-history text-4xl mb-3 block opacity-20"></i>No transactions found here.</td></tr>
                <?php else: 
                    // Calculate running balances
                    $sorted_asc = array_reverse($list);
                    $running = 0;
                    foreach($sorted_asc as &$item) {
                        $running += (float)($item['debit'] ?? 0);
                        $running -= (float)($item['credit'] ?? 0);
                        $item['running_balance'] = $running;
                    }
                    $list = array_reverse($sorted_asc); // Back to descending for display
                    
                    foreach ($list as $t): 
                ?>
                        <tr class="hover:bg-amber-50/30 transition border-b border-gray-50 last:border-0 group">
                            <td class="p-6">
                                <span class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-1 rounded-md uppercase">
                                    <?= date('d M Y', strtotime($t['date'])) ?>
                                </span>
                            </td>
                            <td class="p-6">
                                <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($t['description']) ?></div>
                                <div class="text-[9px] text-gray-400 font-semibold tracking-wider mt-0.5"><?= date('h:i A', strtotime($t['created_at'])) ?></div>
                            </td>
                            <td class="p-6 text-center">
                                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?= $t['type'] == 'Purchase' ? 'bg-orange-50 text-orange-600 border-orange-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100' ?>">
                                    <?= $t['type'] ?>
                                </span>
                            </td>
                            <td class="p-6 text-right font-black text-gray-700">
                                <?= (float)$t['debit'] > 0 ? formatCurrency((float)$t['debit']) : '<span class="text-gray-200">-</span>' ?>
                            </td>
                            <td class="p-6 text-right font-black text-emerald-600">
                                <?= (float)$t['credit'] > 0 ? formatCurrency((float)$t['credit']) : '<span class="text-gray-200">-</span>' ?>
                            </td>
                            <td class="p-6 text-right font-black text-red-600 bg-red-50/20">
                                <?= formatCurrency($t['running_balance'] ?? 0) ?>
                            </td>
                             <td class="p-6 text-center">
                                 <div class="flex justify-center space-x-2 transition-opacity">
                                      <button onclick="openModal('Payment')" class="text-emerald-500 hover:text-emerald-700 w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center transition" title="Add Payment">
                                         <i class="fas fa-hand-holding-usd text-xs"></i>
                                      </button>
                                 </div>
                             </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="txnModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl max-w-md w-full transform transition-all p-8">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-black text-gray-800 tracking-tight" id="modalTitle">Record Payment</h3>
            <button onclick="document.getElementById('txnModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-5">
            <input type="hidden" name="type" id="txnType">
            <input type="hidden" name="txn_id" id="txn_id">
            
            <div class="p-4 bg-red-50 rounded-2xl border border-red-100">
                <label class="block text-[10px] font-bold text-red-400 uppercase tracking-widest mb-1">Current Balance</label>
                <div class="text-2xl font-black text-red-600"><?= formatCurrency($current_balance) ?></div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Date</label>
                     <input type="date" name="date" id="txnDate" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-amber-500 outline-none font-bold text-sm">
                </div>
                <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Amount</label>
                     <input type="number" name="amount" id="txnAmount" required step="0.01" placeholder="0.00" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-amber-500 outline-none font-black text-emerald-600">
                </div>
            </div>

            <div>
                 <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Description / Note</label>
                 <textarea name="description" id="txnDesc" rows="3" placeholder="Write details here..." class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-amber-500 outline-none resize-none text-sm font-medium"></textarea>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-primary text-white font-black py-4 rounded-2xl hover:bg-secondary transition-all shadow-xl shadow-teal-900/20 active:scale-[0.98]">
                    SAVE TRANSACTION
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Print/PDF Secret Area -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #b45309; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #b45309; margin: 0; font-size: 28px;">DEWAAN</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Management System - Dealer Ledger</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333; font-size: 14px; text-transform: uppercase;"><?= htmlspecialchars($dealer['name']) ?></h2>
                <p style="color: #888; margin: 5px 0 0 0; font-size: 10px;">Balance: <?= formatCurrency($current_balance) ?></p>
            </div>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <thead style="background: #f8fafc;">
                <tr>
                    <th style="padding: 10px; border: 1px solid #e2e8f0; text-align: left;">Date</th>
                    <th style="padding: 10px; border: 1px solid #e2e8f0; text-align: left;">Description</th>
                    <th style="padding: 10px; border: 1px solid #e2e8f0; text-align: right;">Debit (Goods)</th>
                    <th style="padding: 10px; border: 1px solid #e2e8f0; text-align: right;">Credit (Paid)</th>
                    <th style="padding: 10px; border: 1px solid #e2e8f0; text-align: right;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $running_p = 0;
                $sorted_p = array_reverse($list); // ascending order for calculation
                foreach ($sorted_p as &$tp) {
                    $running_p += (float)($tp['debit'] ?? 0);
                    $running_p -= (float)($tp['credit'] ?? 0);
                    $tp['running_balance'] = $running_p;
                }
                foreach (array_reverse($sorted_p) as $t): // back to descending for print
                ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e2e8f0;"><?= date('d M Y', strtotime($t['date'])) ?></td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold;"><?= htmlspecialchars($t['description']) ?></td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: right;"><?= (float)$t['debit'] > 0 ? formatCurrency((float)$t['debit']) : '-' ?></td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: right; color: #059669;"><?= (float)$t['credit'] > 0 ? formatCurrency((float)$t['credit']) : '-' ?></td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: right; color: #dc2626; font-weight: bold;"><?= formatCurrency($t['running_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="../assets/js/html2pdf.bundle.min.js"></script>
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

    function openModal(type) {
        document.getElementById('txnType').value = type;
        document.getElementById('txn_id').value = '';
        document.getElementById('txnDate').value = '<?= date('Y-m-d') ?>';
        document.getElementById('txnAmount').value = '';
        document.getElementById('txnDesc').value = '';
        document.getElementById('modalTitle').innerText = (type == 'Purchase') ? "Record Goods" : "Record Payment";
        document.getElementById('txnModal').classList.remove('hidden');
        document.getElementById('txnModal').classList.add('flex');
    }

    function editTxn(data) {
        document.getElementById('txnType').value = data.type;
        document.getElementById('txn_id').value = data.id;
        document.getElementById('txnDate').value = data.date;
        document.getElementById('txnAmount').value = (data.debit > 0) ? data.debit : data.credit;
        document.getElementById('txnDesc').value = data.description;
        document.getElementById('modalTitle').innerText = "Edit " + data.type;
        document.getElementById('txnModal').classList.remove('hidden');
        document.getElementById('txnModal').classList.add('flex');
    }

    function confirmDelete(url) {
        showConfirm("Archive this transaction?", function() {
            window.location.href = url;
        }, "Confirm Delete");
    }

    function downloadPDF() {
        const element = document.getElementById('printableArea');
        element.classList.remove('hidden');
        
        const opt = {
            margin:       0.5,
            filename:     'Ledger_<?= str_replace(' ', '_', $dealer['name']) ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(() => {
            element.classList.add('hidden');
        });
    }

    function printReport() {
        const element = document.getElementById('printableArea');
        element.classList.remove('hidden');
        const content = element.innerHTML;
        element.classList.add('hidden');

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
