<?php
session_start();
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
if (isset($_GET['delete_payment'])) {
    deleteCSV('customer_payments', $_GET['delete_payment']);
    redirect("customer_ledger.php?id=$cid&msg=Payment deleted successfully");
}

// Handle Payment (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount'])) {
    if (isset($_POST['payment_id']) && !empty($_POST['payment_id'])) {
        updateCSV('customer_payments', $_POST['payment_id'], [
            'amount' => $_POST['amount'],
            'date' => $_POST['payment_date'],
            'notes' => cleanInput($_POST['notes'])
        ]);
    } else {
        insertCSV('customer_payments', [
            'customer_id' => $cid,
            'amount' => $_POST['amount'],
            'date' => $_POST['payment_date'],
            'notes' => cleanInput($_POST['notes'])
        ]);
    }
    redirect("customer_ledger.php?id=$cid" . ($from_date ? "&from=$from_date" : "") . ($to_date ? "&to=$to_date" : ""));
}

$pageTitle = "Ledger: " . $customer['name'];
include '../includes/header.php';

// Fetch all sales for this customer
$all_sales = readCSV('sales');
$customer_sales = [];
foreach($all_sales as $s) {
    if($s['customer_id'] == $cid) {
        if ($from_date && $s['sale_date'] < $from_date . ' 00:00:00') continue;
        if ($to_date && $s['sale_date'] > $to_date . ' 23:59:59') continue;
        $customer_sales[] = $s;
    }
}

// Fetch payments
$all_payments = readCSV('customer_payments');
$customer_payments = [];
foreach($all_payments as $p) {
    if($p['customer_id'] == $cid) {
        if ($from_date && $p['date'] < $from_date) continue;
        if ($to_date && $p['date'] > $to_date) continue;
        $customer_payments[] = $p;
    }
}

// Merge and Ledger Logic
$ledger = [];
$total_due = 0;

foreach ($customer_sales as $s) {
    $ledger[] = [
        'id' => $s['id'],
        'date' => $s['sale_date'],
        'desc' => "Sale Recorded",
        'debit' => $s['total_amount'],
        'credit' => $s['paid_amount'],
        'type' => 'Sale'
    ];
    $total_due += ((float)$s['total_amount'] - (float)$s['paid_amount']);
}

foreach ($customer_payments as $p) {
     $ledger[] = [
        'id' => $p['id'],
        'date' => $p['date'],
        'desc' => "Payment Received: " . $p['notes'],
        'debit' => 0,
        'credit' => $p['amount'],
        'type' => 'Payment',
        'notes' => $p['notes']
    ];
    $total_due -= (float)$p['amount'];
}

usort($ledger, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-purple-500">
         <h3 class="text-gray-500 uppercase text-sm">Customer Details</h3>
         <div class="flex items-center gap-3">
            <p class="font-bold text-gray-800 text-xl"><?= htmlspecialchars($customer['name']) ?></p>
            <?php if($total_due <= 0): ?>
                <span class="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 border border-yellow-200 rounded-full text-[10px] font-black uppercase tracking-widest shadow-sm">
                    <i class="fas fa-trophy mr-1.5 text-yellow-500"></i> Debt Cleared!
                </span>
            <?php endif; ?>
         </div>
         <p class="text-gray-600"><?= htmlspecialchars($customer['phone']) ?></p>
         <p class="text-gray-400 text-sm mt-1"><?= htmlspecialchars($customer['address']) ?></p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-md border-l-4 border-red-500 flex flex-col justify-center">
         <h3 class="text-gray-500 uppercase text-sm">Outstanding Balance (Debt)</h3>
         <p class="text-3xl font-bold text-red-600"><?= formatCurrency($total_due) ?></p>
    </div>
</div>

<div class="mb-6 flex flex-col md:flex-row justify-between items-end gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
    <form class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="id" value="<?= $cid ?>">
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">From Date</label>
            <input type="date" name="from" value="<?= $from_date ?>" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500 outline-none">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">To Date</label>
            <input type="date" name="to" value="<?= $to_date ?>" class="p-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500 outline-none">
        </div>
        <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition shadow-md font-bold text-sm h-[38px]">
            <i class="fas fa-filter mr-1"></i> Filter
        </button>
        <?php if($from_date || $to_date): ?>
            <a href="customer_ledger.php?id=<?= $cid ?>" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200 transition text-sm h-[38px] flex items-center">Reset</a>
        <?php endif; ?>
    </form>
    
    <div class="flex gap-2">
        <button onclick="downloadPDF()" class="bg-red-600 text-white px-5 py-2 rounded-lg hover:bg-red-700 shadow-lg font-bold text-sm h-[38px] flex items-center transition transform hover:scale-105">
            <i class="fas fa-file-pdf mr-2 text-lg"></i> PDF
        </button>
        <button onclick="printReport()" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 shadow-lg font-bold text-sm h-[38px] flex items-center transition transform hover:scale-105">
            <i class="fas fa-print mr-2 text-lg"></i> Print
        </button>
        <button onclick="openPayModal()" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 shadow-lg font-bold text-sm h-[38px] flex items-center transition transform hover:scale-105">
            <i class="fas fa-hand-holding-usd mr-2 text-lg"></i> Receive Payment
        </button>
    </div>
</div>

<!-- Printable Area (Hidden from UI, used for PDF) -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-between; align-items: center; border-bottom: 2px solid #6b21a8; padding-bottom: 20px; margin-bottom: 30px;">
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
                <h4 style="margin: 0 0 10px 0; color: #6b21a8; text-transform: uppercase; font-size: 12px;">Customer Details</h4>
                <p style="margin: 0; font-weight: bold; font-size: 16px;"><?= htmlspecialchars($customer['name']) ?></p>
                <p style="margin: 5px 0; color: #555;"><?= htmlspecialchars($customer['phone']) ?></p>
                <p style="margin: 0; color: #888; font-size: 12px;"><?= htmlspecialchars($customer['address']) ?></p>
                
                <?php if($total_due <= 0): ?>
                <div style="margin-top: 10px;">
                    <span style="background: #fef9c3; color: #a16207; padding: 4px 10px; border-radius: 12px; font-size: 9px; font-weight: bold; border: 1px solid #fde047; text-transform: uppercase;">🏆 Debt Cleared!</span>
                    <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 12px; font-size: 9px; font-weight: bold; border: 1px solid #bbf7d0; text-transform: uppercase; margin-left: 5px;">♥ Thank You!</span>
                </div>
                <?php endif; ?>
            </div>
            <div style="flex: 1; background: #fff1f2; padding: 15px; border-radius: 8px; border-left: 4px solid #e11d48; text-align: right;">
                <h4 style="margin: 0 0 10px 0; color: #e11d48; text-transform: uppercase; font-size: 12px;">Outstanding Balance</h4>
                <p style="margin: 0; font-weight: bold; font-size: 24px; color: #e11d48;"><?= formatCurrency($total_due) ?></p>
                <?php if($from_date || $to_date): ?>
                    <p style="margin: 5px 0 0 0; font-size: 11px; color: #991b1b;">Filtered: <?= $from_date ?: 'Start' ?> to <?= $to_date ?: 'End' ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #6b21a8; color: #fff;">
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd; width: 40px;">Sr #</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Date</th>
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Description</th>
                    <th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Debit (Total Sale)</th>
                    <th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Credit (Paid)</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach (array_reverse($ledger) as $row): ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px; text-align: center;"><?= $sn++ ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px;"><?= date('d M Y', strtotime($row['date'])) ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px; font-weight: bold;"><?= $row['desc'] ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px; text-align: right; color: #e11d48; font-weight: bold;"><?= $row['debit'] > 0 ? formatCurrency((float)$row['debit']) : '-' ?></td>
                        <td style="padding: 10px; border: 1px solid #ddd; font-size: 13px; text-align: right; color: #059669; font-weight: bold;"><?= $row['credit'] > 0 ? formatCurrency((float)$row['credit']) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="2" style="padding: 12px; border: 1px solid #ddd; text-align: right;">Total / Balance Due:</td>
                    <td colspan="2" style="padding: 12px; border: 1px solid #ddd; text-align: right; color: #e11d48; font-size: 18px;"><?= formatCurrency($total_due) ?></td>
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
                <th class="p-4 text-right text-red-600">Debit (Total Sale)</th>
                <th class="p-4 text-right text-green-600">Credit (Paid)</th>
                <th class="p-4 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php $sn = 1; foreach ($ledger as $row): ?>
                <tr class="hover:bg-purple-50 transition border-b border-gray-50 last:border-0">
                    <td class="p-4 text-gray-500 font-mono text-xs"><?= $sn++ ?></td>
                    <td class="p-4 text-gray-600 text-sm"><?= date('d M Y', strtotime($row['date'])) ?></td>
                    <td class="p-4 font-medium text-gray-800"><?= $row['desc'] ?></td>
                    <td class="p-4 text-right font-bold text-red-600"><?= $row['debit'] > 0 ? formatCurrency((float)$row['debit']) : '-' ?></td>
                    <td class="p-4 text-right font-bold text-green-600"><?= $row['credit'] > 0 ? formatCurrency((float)$row['credit']) : '-' ?></td>
                    <td class="p-4 text-center">
                        <div class="flex justify-center space-x-2">
                             <?php if ($row['type'] == 'Payment'): ?>
                                <button onclick="editPayment(<?= htmlspecialchars(json_encode($row)) ?>)" class="text-blue-500 hover:text-blue-700 p-1" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="confirmDelete('customer_ledger.php?id=<?= $cid ?>&delete_payment=<?= $row['id'] ?>')" class="text-red-500 hover:text-red-700 p-1" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                             <?php else: ?>
                                <button onclick="confirmDelete('../actions/delete_sale.php?id=<?= $row['id'] ?>&ref=<?= urlencode('pages/customer_ledger.php?id=' . $cid) ?>')" class="text-red-500 hover:text-red-700 p-1" title="Delete Sale">
                                    <i class="fas fa-trash"></i>
                                </button>
                             <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Payment Modal -->
<div id="payModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="text-xl font-bold mb-4" id="payModalTitle">Receive Payment</h3>
        <form method="POST">
             <input type="hidden" name="payment_id" id="payment_id">
             <div class="space-y-3 mb-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Current Total Debt</label>
                    <input type="text" value="<?= formatCurrency($total_due) ?>" readonly class="w-full bg-gray-50 border p-2 rounded font-bold text-red-600 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Date</label>
                    <input type="date" name="payment_date" id="paymentDate" value="<?= date('Y-m-d') ?>" required class="w-full border p-2 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                    <input type="number" name="amount" id="paymentAmount" placeholder="0" required class="w-full border p-2 rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Notes</label>
                    <input type="text" name="notes" id="paymentNotes" value="Cash" placeholder="e.g. Cash, Bank Transfer" class="w-full border p-2 rounded">
                </div>
             </div>
             <div class="flex justify-end space-x-2">
                 <button type="button" onclick="closePayModal()" class="px-4 py-2 bg-gray-200 rounded font-semibold hover:bg-gray-300">Cancel</button>
                 <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded font-bold hover:bg-green-700 shadow-md">Complete Receipt</button>
             </div>
        </form>
    </div>
</div>

<!-- PDF Scripts -->
<script src="../assets/js/html2pdf.bundle.min.js"></script>
<script>
function editPayment(data) {
    document.getElementById('payModalTitle').innerText = "Edit Payment Entry";
    document.getElementById('payment_id').value = data.id;
    document.getElementById('paymentDate').value = data.date;
    document.getElementById('paymentAmount').value = data.credit;
    document.getElementById('paymentNotes').value = data.notes;
    document.getElementById('payModal').classList.remove('hidden');
}

function openPayModal() {
    document.getElementById('payModalTitle').innerText = "Receive Payment";
    document.getElementById('payment_id').value = '';
    document.getElementById('paymentDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('paymentAmount').value = '';
    document.getElementById('paymentNotes').value = 'Cash';
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
    element.classList.remove('hidden'); // Temporarily show to capture
    
    const opt = {
        margin:       0.5,
        filename:     'Ledger_<?= str_replace(' ', '_', $customer['name']) ?>_<?= date('Y-m-d') ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save().then(() => {
        element.classList.add('hidden'); // Hide again
    });
}

function printReport() {
    const element = document.getElementById('printableArea');
    element.classList.remove('hidden');
    const content = element.innerHTML;
    element.classList.add('hidden');

    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Print Ledger</title>');
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
