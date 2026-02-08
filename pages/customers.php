<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    requirePermission('manage_customers');
    insertCSV('customers', [
        'name' => cleanInput($_POST['name']),
        'phone' => cleanInput($_POST['phone']),
        'address' => cleanInput($_POST['address']),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $message = "Customer added successfully!";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    requirePermission('manage_customers');
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

// Calculate Debt for all customers (Unified Logic)
$transactions = readCSV('customer_transactions');
$debt_map = [];

foreach($transactions as $t) {
    if(!empty($t['customer_id'])) {
        $cid = $t['customer_id'];
        $debt_map[$cid] = ($debt_map[$cid] ?? 0) + ((float)$t['debit'] - (float)$t['credit']);
    }
}

$total_outstanding_debt = array_sum($debt_map);
$total_customer_count = count($customers);

// Calculate Earliest Due Date per customer
$due_map = [];
foreach($transactions as $t) {
    if (!empty($t['due_date'])) {
        $cid = $t['customer_id'];
        $due_date = $t['due_date'];
        if (($debt_map[$cid] ?? 0) > 1) { // Only if they actually owe money
            if (!isset($due_map[$cid]) || $due_date < $due_map[$cid]) {
                $due_map[$cid] = $due_date;
            }
        }
    }
}

usort($customers, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
?>

<!-- Financial Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-amber-500 glass transition transform hover:scale-[1.02]">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Customers</h3>
                <p class="text-3xl font-black text-gray-800 tracking-tighter mt-1"><?= number_format($total_customer_count) ?></p>
            </div>
            <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center">
                <i class="fas fa-users text-xl"></i>
            </div>
        </div>
        <div class="mt-4 text-[10px] text-gray-400 font-bold uppercase tracking-wider">Active Portfolio</div>
    </div>
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 border-l-4 border-red-500 glass transition transform hover:scale-[1.02]">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-gray-400 font-bold uppercase text-[10px] tracking-widest">Total Outstanding Debt</h3>
                <p class="text-3xl font-black text-red-600 tracking-tighter mt-1"><?= formatCurrency($total_outstanding_debt) ?></p>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center">
                <i class="fas fa-file-invoice-dollar text-xl"></i>
            </div>
        </div>
        <div class="mt-4 text-[10px] text-red-400 font-bold uppercase tracking-wider">Total Collection Pending</div>
    </div>
</div>

<div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <div class="relative w-full max-w-md">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="customerSearch" autofocus placeholder="Search by name or phone..." class="w-full pl-10 pr-4 py-2 rounded-lg border focus:ring-2 focus:ring-amber-500 focus:outline-none shadow-sm transition">
    </div>
    <div class="flex gap-3 w-full md:w-auto">
        <button onclick="printReport()" class="w-full md:w-auto bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 shadow-lg flex items-center justify-center transition active:scale-95">
            <i class="fas fa-print mr-2"></i> Print / Save PDF
        </button>
        <?php if (hasPermission('manage_customers')): ?>
        <button onclick="document.getElementById('addCustomerModal').classList.remove('hidden')" class="w-full md:w-auto bg-amber-600 text-white px-6 py-2 rounded-lg hover:bg-amber-700 shadow-lg flex items-center justify-center transition transform hover:scale-105 active:scale-95">
            <i class="fas fa-user-plus mr-2"></i> Add Customer
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
        <i class="fas fa-check-circle mr-3"></i>
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-amber-600 text-white text-[10px] uppercase tracking-widest font-bold">
                    <th class="p-4 w-12 text-center">Sr #</th>
                    <th class="p-4">Customer Name</th>
                    <th class="p-4">Contact Info</th>
                    <th class="p-4">Address</th>
                    <th class="p-4 text-right">Outstanding Debt</th>
                    <th class="p-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="customerGrid">
                <!-- Rendered by JS -->
            </tbody>
        </table>
    </div>
</div>
<div id="customerPagination" class="mt-8 px-6 py-4 bg-white rounded-2xl shadow-sm border border-gray-100"></div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex items-center justify-center backdrop-blur-sm transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-0 overflow-hidden transform scale-100">
        <div class="bg-amber-600 p-4 flex justify-between items-center text-white">
            <h3 class="text-lg font-bold flex items-center"><i class="fas fa-user-plus mr-2 text-amber-200"></i> Add New Customer</h3>
            <button onclick="document.getElementById('addCustomerModal').classList.add('hidden')" class="hover:bg-amber-500 rounded-full p-1 w-8 h-8 flex items-center justify-center transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="add">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Full Name</label>
                    <input type="text" name="name" placeholder="Enter customer name" required class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Phone Number</label>
                    <input type="text" name="phone" placeholder="e.g. 03001234567" class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Residential Address</label>
                    <textarea name="address" placeholder="Enter full address" rows="3" class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition shadow-sm"></textarea>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('addCustomerModal').classList.add('hidden')" class="px-5 py-2 rounded-lg text-gray-500 font-semibold hover:bg-gray-100 transition">Cancel</button>
                <button type="submit" class="bg-amber-600 text-white px-8 py-2 rounded-lg font-bold hover:bg-amber-700 shadow-lg transition transform hover:-translate-y-0.5 active:translate-y-0">
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

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex items-center justify-center backdrop-blur-sm transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 transform scale-100 text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Delete Customer?</h3>
        <p class="text-gray-500 text-sm mb-6">
            Are you sure you want to delete this customer?
        </p>
        <div class="flex gap-3 justify-center">
            <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-gray-500 font-bold hover:bg-gray-100 transition w-full">Cancel</button>
            <button id="deleteConfirmBtn" onclick="proceedDelete()" class="px-5 py-2.5 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 shadow-lg transition w-full hidden">Delete</button>
        </div>
        <p id="deleteWarningMsg" class="text-xs text-red-500 font-bold mt-4 bg-red-50 p-3 rounded-xl hidden">
            <i class="fas fa-ban mr-1"></i> First you should clear the debt to delete this customer.
        </p>
        <input type="hidden" id="delete_customer_id">
    </div>
</div>

<script>
const allCustomers = <?= json_encode($customers) ?>;
const debtMap = <?= json_encode($debt_map) ?>;
const dueMap = <?= json_encode($due_map) ?>;
const hasManagePermission = <?= json_encode(hasPermission('manage_customers')) ?>;

let currentPage_Cust = 1;
const pageSize_Cust = 200;

function formatCurrencyJS(amount) {
    return 'Rs.' + new Intl.NumberFormat('en-US').format(amount);
}

function renderCustomers() {
    const term = document.getElementById('customerSearch').value.toLowerCase();
    
    let filtered = allCustomers.filter(c => {
        return c.name.toLowerCase().includes(term) || (c.phone || '').toLowerCase().includes(term);
    });

    const totalItems = filtered.length;
    const paginated = Pagination.paginate(filtered, currentPage_Cust, pageSize_Cust);
    const grid = document.getElementById('customerGrid');

    if (totalItems === 0) {
        grid.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-20 bg-white rounded-2xl">
                    <i class="fas fa-users text-6xl text-gray-100 mb-4"></i>
                    <p class="text-gray-400 font-medium">No customers matched your search.</p>
                </td>
            </tr>
        `;
        Pagination.render('customerPagination', 0, 1, pageSize_Cust, changePage_Cust);
        return;
    }

    let html = '';
    let sn = (currentPage_Cust - 1) * pageSize_Cust + 1;
    paginated.forEach(c => {
        const debt = debtMap[c.id] || 0;
        const dueDate = dueMap[c.id];
        const dueDateStr = dueDate ? new Date(dueDate).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : null;
        
        html += `
            <tr class="customer-card hover:bg-amber-50/30 transition-colors cursor-pointer group" onclick="window.location.href='customer_ledger.php?id=${c.id}'">
                <td class="p-4 text-gray-400 font-mono text-xs text-center">${sn++}</td>
                <td class="p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center mr-3 group-hover:bg-amber-600 group-hover:text-white transition-colors">
                            <i class="fas fa-user text-xs"></i>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-gray-800 uppercase">${c.name}</span>
                            ${debt <= 0 ? `<span title="Debt Fully Cleared!" class="text-yellow-500 text-xs"><i class="fas fa-trophy scale-110"></i></span>` : ''}
                        </div>
                    </div>
                </td>
                <td class="p-4">
                    ${c.phone ? `
                        <a href="tel:${c.phone}" class="text-amber-600 hover:text-amber-700 text-sm font-bold flex items-center" onclick="event.stopPropagation();">
                            <i class="fas fa-phone-alt mr-2 text-xs opacity-70"></i>
                            ${c.phone}
                        </a>
                    ` : `
                        <span class="text-gray-400 text-xs italic">No phone</span>
                    `}
                </td>
                <td class="p-4">
                    <p class="text-gray-500 text-xs truncate max-w-[200px]" title="${c.address || ''}">
                        ${c.address || '<span class="italic opacity-50">No address</span>'}
                    </p>
                </td>
                <td class="p-4 text-right">
                    <div>
                        <span class="font-black ${debt > 0 ? 'text-red-600' : 'text-green-600'} text-sm">
                            ${formatCurrencyJS(debt)}
                        </span>
                        ${dueDateStr ? `
                            <p class="text-[9px] font-bold text-orange-500 uppercase flex items-center justify-end gap-1 mt-0.5">
                                <i class="fas fa-calendar-day"></i> ${dueDateStr}
                            </p>
                        ` : ''}
                    </div>
                </td>
                <td class="p-4">
                    <div class="flex justify-center items-center gap-1">
                        <button onclick="window.location.href='customer_ledger.php?id=${c.id}'; event.stopPropagation();" class="p-2 text-teal-600 hover:bg-teal-50 rounded-lg transition" title="View Ledger">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </button>
                        ${hasManagePermission ? `
                            <button onclick="event.stopPropagation(); editCustomer(${JSON.stringify(c).replace(/"/g, '&quot;')})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit Customer">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="event.stopPropagation(); confirmDelete(${c.id}, ${debt})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete Customer">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    grid.innerHTML = html;
    Pagination.render('customerPagination', totalItems, currentPage_Cust, pageSize_Cust, changePage_Cust);
}

function changePage_Cust(page) {
    currentPage_Cust = page;
    renderCustomers();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function editCustomer(customer) {
    document.getElementById('edit_id').value = customer.id;
    document.getElementById('edit_name').value = customer.name;
    document.getElementById('edit_phone').value = customer.phone;
    document.getElementById('edit_address').value = customer.address;
    document.getElementById('editCustomerModal').classList.remove('hidden');
}

function confirmDelete(id, balance) {
    document.getElementById('delete_customer_id').value = id;
    const deleteBtn = document.getElementById('deleteConfirmBtn');
    const warningMsg = document.getElementById('deleteWarningMsg');
    
    if (balance > 1) { 
        deleteBtn.classList.add('hidden');
        warningMsg.classList.remove('hidden');
    } else {
        deleteBtn.classList.remove('hidden');
        warningMsg.classList.add('hidden');
    }
    
    document.getElementById('deleteModal').classList.remove('hidden');
}

function proceedDelete() {
    const id = document.getElementById('delete_customer_id').value;
    if (id) {
        window.location.href = '../actions/delete_customer.php?id=' + id;
    }
}

document.getElementById('customerSearch').addEventListener('input', function(e) {
    currentPage_Cust = 1;
    renderCustomers();
});

document.addEventListener('DOMContentLoaded', renderCustomers);

document.getElementById('customerSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const grid = document.getElementById('customerGrid');
        const firstRow = grid.querySelector('.customer-card');
        if (firstRow) {
            firstRow.click();
        }
    }
});

function printReport() {
    const element = document.getElementById('printableArea');
    const content = element.innerHTML;

    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Customer Debt Report</title><link rel="icon" type="image/png" href="../assets/img/favicon.png"><style>body { font-family: sans-serif; }</style></head><body>');
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
                <h1 style="color: #0f766e; margin: 0; font-size: 28px;"><?= getSetting('business_name', 'Fashion Shines') ?></h1>
                <p style="color: #666; margin: 5px 0 0 0;">Customer Outstanding Debt Report</p>
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
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Customer Name</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Phone</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Outstanding Debt</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($customers as $c): 
                    $debt = $debt_map[$c['id']] ?? 0;
                ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: center;"><?= $sn++ ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; font-weight: 600;"><?= htmlspecialchars($c['name']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;"><?= htmlspecialchars($c['phone']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: <?= $debt > 0 ? '#dc2626' : '#059669' ?>; font-weight: bold; font-size: 11px;"><?= formatCurrency($debt) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="3" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 11px;">Grand Total:</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #dc2626; font-size: 16px;"><?= formatCurrency($total_outstanding_debt) ?></td>
                </tr>
            </tfoot>
        </table>

        <div style="border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; text-align: center; font-size: 10px; color: #888;">
            <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>
<?php 
if (isset($_SESSION['error'])) {
    echo "<script>alert('" . addslashes($_SESSION['error']) . "');</script>";
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    echo "<script>alert('" . addslashes($_SESSION['success']) . "');</script>";
    unset($_SESSION['success']);
}
include '../includes/footer.php'; 
?>
