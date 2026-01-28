<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $data = [
        'name' => cleanInput($_POST['name']),
        'phone' => cleanInput($_POST['phone']),
        'address' => cleanInput($_POST['address']),
        'created_at' => date('Y-m-d H:i:s')
    ];
    insertCSV('dealers', $data);
    $message = "Dealer added successfully!";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $data = [
        'name' => cleanInput($_POST['name']),
        'phone' => cleanInput($_POST['phone']),
        'address' => cleanInput($_POST['address'])
    ];
    updateCSV('dealers', $id, $data);
    $message = "Dealer updated successfully!";
}

$pageTitle = "Dealer Management";
include '../includes/header.php';

$dealers = readCSV('dealers');

// Calculate balances for all dealers
$all_txns = readCSV('dealer_transactions');
$balance_map = [];

foreach($all_txns as $t) {
    if(!empty($t['dealer_id'])) {
        $did = $t['dealer_id'];
        $balance_map[$did] = ($balance_map[$did] ?? 0) + (float)($t['debit'] ?? 0) - (float)($t['credit'] ?? 0);
    }
}

$total_payable_balance = array_sum($balance_map);
$total_dealer_count = count($dealers);

usort($dealers, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
?>

<!-- Financial Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-amber-500 glass transition transform hover:scale-[1.02]">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Dealers</h3>
                <p class="text-3xl font-black text-gray-800 tracking-tighter mt-1"><?= number_format($total_dealer_count) ?></p>
            </div>
            <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center">
                <i class="fas fa-truck-loading text-xl"></i>
            </div>
        </div>
        <div class="mt-4 text-[10px] text-gray-400 font-bold uppercase tracking-wider">Active Suppliers</div>
    </div>
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-red-500 glass transition transform hover:scale-[1.02]">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Payable Balance</h3>
                <p class="text-3xl font-black text-red-600 tracking-tighter mt-1"><?= formatCurrency($total_payable_balance) ?></p>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center">
                <i class="fas fa-hand-holding-usd text-xl"></i>
            </div>
        </div>
        <div class="mt-4 text-[10px] text-red-400 font-bold uppercase tracking-wider">Total Amount to Pay</div>
    </div>
</div>

<div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <div class="relative w-full max-w-md">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="dealerSearch" autofocus placeholder="Search dealers..." class="w-full pl-10 pr-4 py-2 rounded-lg border focus:ring-2 focus:ring-amber-500 focus:outline-none shadow-sm transition">
    </div>
    <div class="flex gap-3 w-full md:w-auto">
        <button onclick="printReport()" class="w-full md:w-auto bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 shadow-lg flex items-center justify-center transition active:scale-95">
            <i class="fas fa-print mr-2"></i> Print / Save PDF
        </button>
        <button onclick="document.getElementById('addDealerModal').classList.remove('hidden')" class="w-full md:w-auto bg-amber-600 text-white px-6 py-2 rounded-lg hover:bg-amber-700 shadow-lg flex items-center justify-center transition transform hover:scale-105 active:scale-95">
            <i class="fas fa-truck mr-2"></i> Add Dealer
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
        <i class="fas fa-check-circle mr-3"></i>
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="dealerGrid">
    <?php if (count($dealers) > 0): ?>
        <?php foreach ($dealers as $dealer): ?>
            <div class="dealer-card bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 cursor-pointer" 
                 data-name="<?= strtolower(htmlspecialchars($dealer['name'])) ?>"
                 onclick="window.location.href='dealer_ledger.php?id=<?= $dealer['id'] ?>'">
                <div class="bg-amber-500 h-2 w-full"></div>
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-amber-50 rounded-xl text-amber-600 mr-4">
                            <i class="fas fa-building text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-800 leading-tight"><?= htmlspecialchars($dealer['name']) ?></h3>
                            <div class="flex items-center mt-1">
                                <?php if(!empty($dealer['phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($dealer['phone']) ?>" class="text-amber-600 hover:text-amber-700 text-sm font-bold flex items-center" onclick="event.stopPropagation();">
                                        <i class="fas fa-phone-alt mr-2 text-xs opacity-70"></i>
                                        <?= htmlspecialchars($dealer['phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs italic flex items-center">
                                        <i class="fas fa-phone-slash mr-2 text-xs opacity-40"></i>
                                        No phone provided
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button onclick="event.stopPropagation(); editDealer(<?= htmlspecialchars(json_encode($dealer)) ?>)" class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit Dealer">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    
                    <div class="text-gray-500 text-sm mb-6 flex items-start min-h-[40px]">
                        <i class="fas fa-map-marker-alt mr-2 mt-1 text-xs opacity-40"></i>
                        <p class="line-clamp-2"><?= htmlspecialchars($dealer['address'] ?? '') ?: '<span class="italic opacity-50">No address provided</span>' ?></p>
                    </div>
                    
                    <div class="mb-6 bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Payable Balance</p>
                        <?php $bal = $balance_map[$dealer['id']] ?? 0; ?>
                        <p class="text-xl font-black <?= $bal > 0 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= formatCurrency($bal) ?>
                        </p>
                    </div>

                    <div class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-teal-600 text-white rounded-xl text-sm font-bold transition shadow-md group-hover:bg-teal-700">
                        <i class="fas fa-file-invoice-dollar mr-2"></i> View Account Ledger
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-span-1 md:col-span-2 lg:col-span-3 text-center py-20 bg-white rounded-2xl shadow border-2 border-dashed border-gray-100">
            <i class="fas fa-truck-loading text-6xl text-gray-100 mb-4"></i>
            <p class="text-gray-400 font-medium">No dealers found in the database.</p>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('dealerSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const visibleCards = Array.from(document.querySelectorAll('.dealer-card')).filter(c => c.style.display !== 'none');
        if (visibleCards.length > 0) {
            const editBtn = visibleCards[0].querySelector('button[title="Edit Dealer"]');
            if (editBtn) editBtn.click();
        }
    }
});
</script>

<!-- Add Dealer Modal -->
<div id="addDealerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Add New Dealer</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                     <label class="block text-sm font-medium text-gray-700">Dealer/Company Name</label>
                     <input type="text" name="name" required class="w-full rounded-lg border p-2 focus:ring-teal-500">
                </div>
                <div>
                     <label class="block text-sm font-medium text-gray-700">Phone</label>
                     <input type="text" name="phone" class="w-full rounded-lg border p-2 focus:ring-teal-500">
                </div>
                <div>
                     <label class="block text-sm font-medium text-gray-700">Address</label>
                     <textarea name="address" rows="2" class="w-full rounded-lg border p-2 focus:ring-teal-500"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('addDealerModal').classList.add('hidden')" class="text-gray-600 px-4 py-2 rounded hover:bg-gray-100">Cancel</button>
                <button type="submit" class="bg-amber-600 text-white px-6 py-2 rounded-lg hover:bg-amber-700">Save Dealer</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Dealer Modal -->
<div id="editDealerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <div class="bg-blue-600 p-4 flex justify-between items-center text-white">
            <h3 class="text-lg font-bold">Edit Dealer</h3>
            <button onclick="document.getElementById('editDealerModal').classList.add('hidden')" class="hover:bg-blue-700 p-1 rounded-full px-2">&times;</button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 ml-1">Dealer/Company Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 ml-1">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 ml-1">Location / Address</label>
                    <textarea name="address" id="edit_address" rows="3" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition"></textarea>
                </div>
            </div>
            <div class="mt-8 flex gap-3">
                <button type="button" onclick="document.getElementById('editDealerModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl font-bold hover:bg-gray-200 transition">Cancel</button>
                <button type="submit" class="flex-1 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 shadow-lg transition">Update Dealer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editDealer(dealer) {
    document.getElementById('edit_id').value = dealer.id || '';
    document.getElementById('edit_name').value = dealer.name || '';
    document.getElementById('edit_phone').value = dealer.phone || '';
    document.getElementById('edit_address').value = dealer.address || '';
    document.getElementById('editDealerModal').classList.remove('hidden');
}

document.getElementById('dealerSearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('.dealer-card');
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        if (name.includes(term)) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });
});

document.getElementById('dealerSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const visibleCards = Array.from(document.querySelectorAll('.dealer-card')).filter(c => !c.classList.contains('hidden'));
        if (visibleCards.length > 0) {
            const editBtn = visibleCards[0].querySelector('button[title="Edit Dealer"]');
            if (editBtn) editBtn.click();
        }
    }
});

function printReport() {
    const element = document.getElementById('printableArea');
    const content = element.innerHTML;

    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Dealer Payable Report</title><style>body { font-family: sans-serif; }</style></head><body>');
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

<!-- Printable Area -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #0f766e; margin: 0; font-size: 28px;">Fashion Shines</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Dealer Payable Balance Report</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333;">Summary Report</h2>
                <p style="color: #888; margin: 5px 0 0 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #0f766e; color: #fff;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; width: 40px; font-size: 11px;">Sr #</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Dealer/Company</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Phone</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Payable Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($dealers as $d): 
                    $bal = $balance_map[$d['id']] ?? 0;
                ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: center;"><?= $sn++ ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; font-weight: 600;"><?= htmlspecialchars($d['name']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;"><?= htmlspecialchars($d['phone'] ?? '') ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: <?= $bal > 0 ? '#dc2626' : '#059669' ?>; font-weight: bold; font-size: 11px;"><?= formatCurrency($bal) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="3" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 11px;">Grand Total Payable:</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #dc2626; font-size: 16px;"><?= formatCurrency($total_payable_balance) ?></td>
                </tr>
            </tfoot>
        </table>

        <div style="border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; text-align: center; font-size: 10px; color: #888;">
            <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>
