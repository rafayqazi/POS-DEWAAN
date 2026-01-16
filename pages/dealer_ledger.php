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

// Handle Deletion
if (isset($_GET['delete_txn'])) {
    deleteCSV('dealer_transactions', $_GET['delete_txn']);
    redirect("dealer_ledger.php?id=$dealer_id");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'dealer_id' => $dealer_id,
        'type' => $_POST['type'],
        'amount' => $_POST['amount'],
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

$total_debt = 0;
$total_paid = 0;
foreach ($list as $t) {
    if ($t['type'] == 'Purchase') $total_debt += (float)$t['amount'];
    else $total_paid += (float)$t['amount'];
}
$current_balance = $total_debt - $total_paid;
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-amber-500">
        <h3 class="text-gray-500 font-semibold uppercase text-sm">Total Goods Value</h3>
        <p class="text-2xl font-bold text-gray-800"><?= formatCurrency($total_debt) ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-green-500">
        <h3 class="text-gray-500 font-semibold uppercase text-sm">Total Paid</h3>
        <p class="text-2xl font-bold text-gray-800"><?= formatCurrency($total_paid) ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-red-500">
        <h3 class="text-gray-500 font-semibold uppercase text-sm">Current Debt (Balance)</h3>
        <p class="text-2xl font-bold text-red-600"><?= formatCurrency($current_balance) ?></p>
    </div>
</div>

<div class="mb-6 flex flex-col md:flex-row justify-between items-end gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
    <form class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="id" value="<?= $dealer_id ?>">
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">From Date</label>
            <input type="date" name="from" value="<?= $from_date ?>" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-amber-500 outline-none">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" name="to" value="<?= $to_date ?>" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-amber-500 outline-none">
        </div>
        <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition shadow-md font-bold text-sm h-[38px]">
            <i class="fas fa-filter mr-1"></i> Filter
        </button>
        <?php if($from_date || $to_date): ?>
            <a href="dealer_ledger.php?id=<?= $dealer_id ?>" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm h-[38px] flex items-center">Reset</a>
        <?php endif; ?>
    </form>
    
    <div class="flex gap-2">
        <button onclick="downloadPDF()" class="bg-red-600 text-white px-5 py-2 rounded-lg hover:bg-red-700 shadow-lg font-bold text-sm h-[38px] flex items-center transition transform hover:scale-105">
            <i class="fas fa-file-pdf mr-2 text-lg"></i> PDF
        </button>
        <button onclick="printReport()" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 shadow-lg font-bold text-sm h-[38px] flex items-center transition transform hover:scale-105">
            <i class="fas fa-print mr-2 text-lg"></i> Print
        </button>
        <div class="flex gap-2">
            <button onclick="openModal('Purchase')" class="bg-red-600 text-white px-4 py-2 rounded-lg shadow font-bold text-sm h-[38px] hover:bg-red-700 transition">
                <i class="fas fa-boxes mr-1"></i> Received
            </button>
            <button onclick="openModal('Payment')" class="bg-green-600 text-white px-4 py-2 rounded-lg shadow font-bold text-sm h-[38px] hover:bg-green-700 transition">
                <i class="fas fa-money-bill-wave mr-1"></i> Payment
            </button>
        </div>
    </div>
</div>

<!-- Printable Area (Hidden for UI) -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-between; align-items: center; border-bottom: 2px solid #b45309; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #b45309; margin: 0; font-size: 28px;">DEWAAN</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Fertilizers & Pesticides Management System</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333;">Dealer Ledger Report</h2>
                <p style="color: #888; margin: 5px 0 0 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <div style="display: flex; gap: 40px; margin-bottom: 30px;">
            <div style="flex: 1; background: #fffbeb; padding: 15px; border-radius: 8px; border-left: 4px solid #b45309;">
                <h4 style="margin: 0 0 10px 0; color: #b45309; text-transform: uppercase; font-size: 12px;">Dealer Details</h4>
                <p style="margin: 0; font-weight: bold; font-size: 16px;"><?= htmlspecialchars($dealer['name']) ?></p>
                <p style="margin: 5px 0; color: #555;"><?= htmlspecialchars($dealer['phone']) ?></p>
                <p style="margin: 0; color: #888; font-size: 12px;"><?= htmlspecialchars($dealer['address']) ?></p>
            </div>
            <div style="flex: 1; background: #fff1f2; padding: 15px; border-radius: 8px; border-left: 4px solid #e11d48; text-align: right;">
                <h4 style="margin: 0 0 10px 0; color: #e11d48; text-transform: uppercase; font-size: 12px;">Outstanding Balance (Payable)</h4>
                <p style="margin: 0; font-weight: bold; font-size: 24px; color: #e11d48;"><?= formatCurrency($current_balance) ?></p>
                <?php if($from_date || $to_date): ?>
                    <p style="margin: 5px 0 0 0; font-size: 11px; color: #991b1b;">Filtered: <?= $from_date ?: 'Start' ?> to <?= $to_date ?: 'End' ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #b45309; color: #fff;">
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd; width: 40px;">Sr #</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Date</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Description</th>
                    <th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Debit (Goods)</th>
                    <th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Credit (Paid)</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach (array_reverse($list) as $t): ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px; text-align: center;"><?= $sn++ ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px;"><?= date('d M Y', strtotime($t['date'])) ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px; font-weight: bold;"><?= htmlspecialchars($t['description']) ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px; text-align: right; color: #7f1d1d; font-weight: bold;"><?= $t['type'] == 'Purchase' ? formatCurrency((float)$t['amount']) : '-' ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px; text-align: right; color: #065f46; font-weight: bold;"><?= $t['type'] == 'Payment' ? formatCurrency((float)$t['amount']) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #fdf2f2; font-weight: bold;">
                    <td colspan="2" style="padding: 12px; border: 1px solid #ddd; text-align: right;">Final Balance Payable:</td>
                    <td colspan="2" style="padding: 12px; border: 1px solid #ddd; text-align: right; color: #e11d48; font-size: 18px;"><?= formatCurrency($current_balance) ?></td>
                </tr>
            </tfoot>
        </table>
        
        <div style="margin-top: 50px; text-align: center; color: #aaa; font-size: 10px; border-top: 1px solid #eee; padding-top: 10px;">
            This is a computer generated report from Dewaan POS. 
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
    <table class="w-full text-left">
        <thead class="bg-gray-100 border-b">
            <tr>
                <th class="p-4 w-12">Sr #</th>
                <th class="p-4">Date</th>
                <th class="p-4">Description</th>
                <th class="p-4 text-center">Type</th>
                <th class="p-4 text-right">Debit (Goods)</th>
                <th class="p-4 text-right">Credit (Paid)</th>
                <th class="p-4 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php $sn = 1; foreach ($list as $t): ?>
                <tr class="hover:bg-amber-50 transition border-b border-gray-50 last:border-0">
                    <td class="p-4 text-gray-400 font-mono text-xs"><?= $sn++ ?></td>
                    <td class="p-4 text-gray-600 text-sm"><?= date('d M Y', strtotime($t['date'])) ?></td>
                    <td class="p-4 text-gray-800 font-bold"><?= htmlspecialchars($t['description']) ?></td>
                    <td class="p-4 text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= $t['type'] == 'Purchase' ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-green-100 text-green-700 border border-green-200' ?>">
                            <?= $t['type'] ?>
                        </span>
                    </td>
                    <td class="p-4 text-right font-bold text-gray-700"><?= $t['type'] == 'Purchase' ? formatCurrency((float)$t['amount']) : '-' ?></td>
                    <td class="p-4 text-right font-bold text-green-600"><?= $t['type'] == 'Payment' ? formatCurrency((float)$t['amount']) : '-' ?></td>
                    <td class="p-4 text-center">
                        <div class="flex justify-center space-x-2">
                             <button onclick="editTxn(<?= htmlspecialchars(json_encode($t)) ?>)" class="text-blue-500 hover:text-blue-700 p-1" title="Edit">
                                <i class="fas fa-edit"></i>
                             </button>
                             <button onclick="confirmDelete('dealer_ledger.php?id=<?= $dealer_id ?>&delete_txn=<?= $t['id'] ?>')" class="text-red-500 hover:text-red-700 p-1" title="Delete">
                                <i class="fas fa-trash"></i>
                             </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Transaction Modal reused from previous step, just works because of standard form post -->
<div id="txnModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4" id="modalTitle">Record Transaction</h3>
        <form method="POST">
            <input type="hidden" name="type" id="txnType">
            <input type="hidden" name="txn_id" id="txn_id">
            <div class="space-y-4">
                <div>
                     <label class="block text-sm font-medium text-gray-700 opacity-60">Current Payable Balance</label>
                     <input type="text" value="<?= formatCurrency($current_balance) ?>" readonly class="w-full bg-gray-50 border p-2 rounded-lg font-bold text-red-600 cursor-not-allowed">
                </div>
                <div>
                     <label class="block text-sm font-medium text-gray-700">Date</label>
                     <input type="date" name="date" id="txnDate" value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border p-2">
                </div>
                <div>
                     <label class="block text-sm font-medium text-gray-700">Amount</label>
                     <input type="number" name="amount" id="txnAmount" required step="0.01" class="w-full rounded-lg border p-2">
                </div>
                <div>
                     <label class="block text-sm font-medium text-gray-700">Description</label>
                     <textarea name="description" id="txnDesc" placeholder="e.g. 50 Bags Urea OR Cash Payment" class="w-full rounded-lg border p-2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('txnModal').classList.add('hidden')" class="text-gray-600 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-teal-600 text-white px-6 py-2 rounded-lg hover:bg-teal-700">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- PDF Scripts -->
<script src="../assets/js/html2pdf.bundle.min.js"></script>
<script>
    function openModal(type) {
        document.getElementById('txnType').value = type;
        document.getElementById('txn_id').value = '';
        document.getElementById('txnDate').value = '<?= date('Y-m-d') ?>';
        document.getElementById('txnAmount').value = '';
        document.getElementById('txnDesc').value = '';
        document.getElementById('modalTitle').innerText = (type == 'Purchase') ? "Record Goods Received (Increases Debt)" : "Record Payment (Decreases Debt)";
        document.getElementById('txnModal').classList.remove('hidden');
    }

    function editTxn(data) {
        document.getElementById('txnType').value = data.type;
        document.getElementById('txn_id').value = data.id;
        document.getElementById('txnDate').value = data.date;
        document.getElementById('txnAmount').value = data.amount;
        document.getElementById('txnDesc').value = data.description;
        document.getElementById('modalTitle').innerText = "Edit " + data.type;
        document.getElementById('txnModal').classList.remove('hidden');
    }

    function confirmDelete(url) {
        showConfirm("Are you sure you want to delete this record?", function() {
            window.location.href = url;
        });
    }

    function downloadPDF() {
        const element = document.getElementById('printableArea');
        element.classList.remove('hidden');
        
        const opt = {
            margin:       0.5,
            filename:     'Dealer_Ledger_<?= str_replace(' ', '_', $dealer['name']) ?>_<?= date('Y-m-d') ?>.pdf',
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
        printWindow.document.write('<html><head><title>Print Dealer Ledger</title>');
        printWindow.document.write('<style>body { font-family: sans-serif; }</style>');
        printWindow.document.write('</head><body>');
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
