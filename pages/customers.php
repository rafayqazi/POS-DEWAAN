<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    insertCSV('customers', [
        'name' => cleanInput($_POST['name']),
        'phone' => cleanInput($_POST['phone']),
        'address' => cleanInput($_POST['address']),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $message = "Customer added successfully!";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $data = [
        'name' => cleanInput($_POST['name']),
        'phone' => cleanInput($_POST['phone']),
        'address' => cleanInput($_POST['address'])
    ];
    updateCSV('customers', $id, $data);
    $message = "Customer updated successfully!";
}

$pageTitle = "Customer Management";
include '../includes/header.php';

$customers = readCSV('customers');

// Calculate Debt for all customers
$sales = readCSV('sales');
$payments = readCSV('customer_payments');
$debt_map = [];

foreach($sales as $s) {
    if(!empty($s['customer_id'])) {
        $cid = $s['customer_id'];
        $debt_map[$cid] = ($debt_map[$cid] ?? 0) + ((float)$s['total_amount'] - (float)$s['paid_amount']);
    }
}

foreach($payments as $p) {
    $cid = $p['customer_id'];
    $debt_map[$cid] = ($debt_map[$cid] ?? 0) - (float)$p['amount'];
}

usort($customers, function($a, $b) { return $b['id'] - $a['id']; }); // Newest first
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <div class="relative w-full max-w-md">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="customerSearch" autofocus placeholder="Search by name or phone..." class="w-full pl-10 pr-4 py-2 rounded-lg border focus:ring-2 focus:ring-purple-500 focus:outline-none shadow-sm transition">
    </div>
    <button onclick="document.getElementById('addCustomerModal').classList.remove('hidden')" class="w-full md:w-auto bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 shadow-lg flex items-center justify-center transition transform hover:scale-105 active:scale-95">
        <i class="fas fa-user-plus mr-2"></i> Add Customer
    </button>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
        <i class="fas fa-check-circle mr-3"></i>
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-purple-700 text-white text-sm uppercase tracking-wider">
                    <th class="p-3 pl-6">ID</th>
                    <th class="p-3">Customer Name</th>
                    <th class="p-3">Phone Number</th>
                    <th class="p-3 text-right">Outstanding Debt</th>
                    <th class="p-3">Location / Address</th>
                    <th class="p-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="customerTableBody" class="divide-y divide-gray-100">
                <?php if (count($customers) > 0): ?>
                    <?php foreach ($customers as $c): ?>
                        <tr class="hover:bg-purple-50 transition customer-row" data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>" data-phone="<?= strtolower(htmlspecialchars($c['phone'])) ?>">
                            <td class="p-3 pl-6 text-gray-500 text-sm">#<?= $c['id'] ?></td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="font-bold text-gray-800"><?= htmlspecialchars($c['name']) ?></div>
                                    <?php if(($debt_map[$c['id']] ?? 0) <= 0): ?>
                                        <span title="Debt Fully Cleared!" class="text-yellow-500 text-xs">
                                            <i class="fas fa-trophy scale-110"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <a href="tel:<?= $c['phone'] ?>" class="text-gray-600 hover:text-purple-600 flex items-center text-sm">
                                    <i class="fas fa-phone-alt mr-2 text-xs opacity-50"></i>
                                    <?= htmlspecialchars($c['phone']) ?>
                                </a>
                            </td>
                            <td class="p-3 text-right">
                                <?php $debt = $debt_map[$c['id']] ?? 0; ?>
                                <span class="font-bold <?= $debt > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= formatCurrency($debt) ?>
                                </span>
                            </td>
                            <td class="p-3 text-gray-500 text-sm">
                                <div class="truncate max-w-xs" title="<?= htmlspecialchars($c['address']) ?>">
                                    <?= htmlspecialchars($c['address']) ?: '<span class="italic text-gray-300">No address set</span>' ?>
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <div class="flex justify-center space-x-2">
                                    <button onclick="editCustomer(<?= htmlspecialchars(json_encode($c)) ?>)" class="bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg hover:bg-blue-200 transition text-xs font-bold flex items-center">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    <a href="customer_ledger.php?id=<?= $c['id'] ?>" class="bg-purple-100 text-purple-600 px-3 py-1.5 rounded-lg hover:bg-purple-200 transition text-xs font-bold flex items-center">
                                        <i class="fas fa-history mr-1"></i> Ledger
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="p-12 text-center text-gray-400 italic">
                            <i class="fas fa-users text-4xl mb-4 text-gray-200"></i><br>
                            No customers found. Start by adding one.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex items-center justify-center backdrop-blur-sm transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-0 overflow-hidden transform scale-100">
        <div class="bg-purple-700 p-4 flex justify-between items-center text-white">
            <h3 class="text-lg font-bold flex items-center"><i class="fas fa-user-plus mr-2 text-purple-300"></i> Add New Customer</h3>
            <button onclick="document.getElementById('addCustomerModal').classList.add('hidden')" class="hover:bg-purple-600 rounded-full p-1 w-8 h-8 flex items-center justify-center transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Full Name</label>
                    <input type="text" name="name" placeholder="Enter customer name" required class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Phone Number</label>
                    <input type="text" name="phone" placeholder="e.g. 03001234567" class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Residential Address</label>
                    <textarea name="address" placeholder="Enter full address" rows="3" class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition shadow-sm"></textarea>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('addCustomerModal').classList.add('hidden')" class="px-5 py-2 rounded-lg text-gray-500 font-semibold hover:bg-gray-100 transition">Cancel</button>
                <button type="submit" class="bg-purple-700 text-white px-8 py-2 rounded-lg font-bold hover:bg-purple-800 shadow-lg transition transform hover:-translate-y-0.5 active:translate-y-0">
                    Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editCustomerModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <div class="bg-blue-600 p-4 flex justify-between items-center text-white">
            <h3 class="text-lg font-bold">Edit Customer</h3>
            <button onclick="document.getElementById('editCustomerModal').classList.add('hidden')" class="hover:bg-blue-700 p-1 rounded-full px-2">&times;</button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1 ml-1">Customer Name</label>
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
                <button type="button" onclick="document.getElementById('editCustomerModal').classList.add('hidden')" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl font-bold hover:bg-gray-200 transition">Cancel</button>
                <button type="submit" class="flex-1 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 shadow-lg transition">Update Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCustomer(customer) {
    document.getElementById('edit_id').value = customer.id;
    document.getElementById('edit_name').value = customer.name;
    document.getElementById('edit_phone').value = customer.phone;
    document.getElementById('edit_address').value = customer.address;
    document.getElementById('editCustomerModal').classList.remove('hidden');
}

    });
});

document.getElementById('customerSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const visibleRows = Array.from(document.querySelectorAll('.customer-row')).filter(r => r.style.display !== 'none');
        if (visibleRows.length > 0) {
            const editBtn = visibleRows[0].querySelector('button[title="Edit"]');
            if (editBtn) editBtn.click();
        }
    }
});
</script>

<?php 
include '../includes/footer.php'; 
echo '</main></div></body></html>'; 
?>
