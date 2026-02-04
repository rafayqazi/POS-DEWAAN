<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasPermission('add_sale')) die("Unauthorized Access");
$pageTitle = "Product Returns";
include '../includes/header.php';

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$sale_id = $_GET['sale_id'] ?? '';
$return_id = $_GET['return_id'] ?? '';
$customer_id_filter = $_GET['customer_id'] ?? '';
$sales_list = [];

$sale = null;
$customer = null;
$items = [];
$products = [];
?>

<style>
.glass {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.return-card {
    transition: all 300ms cubic-bezier(0.4, 0, 0.2, 1);
}
.return-card:hover { border-color: #0d9488; }
</style>

<?php
if ($customer_id_filter) {
    $all_sales = readCSV('sales');
    $sales_list = array_filter($all_sales, function($s) use ($customer_id_filter) {
        return $s['customer_id'] == $customer_id_filter;
    });
    // Sort by date DESC
    usort($sales_list, function($a, $b) {
        return strtotime($b['sale_date']) - strtotime($a['sale_date']);
    });
}

if ($sale_id) {
    $sale = findCSV('sales', $sale_id);
    if ($sale) {
        $customers = readCSV('customers');
        foreach ($customers as $c) {
            if ($c['id'] == $sale['customer_id']) {
                $customer = $c;
                break;
            }
        }

        $all_sale_items = readCSV('sale_items');
        $items = array_filter($all_sale_items, function ($item) use ($sale_id) {
            return $item['sale_id'] == $sale_id;
        });

        $all_products = readCSV('products');
        foreach ($all_products as $p) {
            $products[$p['id']] = $p;
        }
    }
}
$customers = readCSV('customers');
usort($customers, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});
?>

<div class="max-w-4xl mx-auto">
    <!-- Success/Error Messages -->
    <?php if ($msg): ?>
        <div class="bg-teal-50 text-teal-700 p-4 rounded-2xl border border-teal-100 mb-6 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-teal-900/20">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm">Success!</h4>
                    <p class="text-xs opacity-80"><?= htmlspecialchars($msg) ?></p>
                </div>
            </div>
            <?php if ($return_id): ?>
                <a href="print_return.php?id=<?= $return_id ?>" target="_blank" class="px-6 py-2 bg-teal-600 text-white font-black rounded-xl hover:bg-teal-700 transition shadow-lg shadow-teal-900/20 flex items-center gap-2 text-xs">
                    <i class="fas fa-print"></i> PRINT RETURN RECEIPT
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-2xl border border-red-100 mb-6 flex items-center gap-3 shadow-sm">
            <div class="w-10 h-10 bg-red-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-red-900/20">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h4 class="font-bold text-sm">Error!</h4>
                <p class="text-xs opacity-80"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Customer Search -->
        <div class="bg-white p-6 rounded-[2rem] shadow-xl border border-gray-100 glass relative">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                <i class="fas fa-user-tag text-teal-600"></i> Search by Customer
            </h3>
            <form action="" method="GET" id="customerSearchForm" class="space-y-4">
                <div class="relative" id="customerDropdownContainer">
                    <button type="button" onclick="toggleCustomerDropdown()" id="customerDropdownBtn" 
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-teal-500 outline-none transition-all shadow-sm text-left flex justify-between items-center hover:border-teal-400">
                        <i class="fas fa-users absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                        <?php 
                        $selected_cust_name = "Select Customer Name...";
                        if ($customer_id_filter) {
                            foreach($customers as $c) {
                                if($c['id'] == $customer_id_filter) {
                                    $selected_cust_name = htmlspecialchars($c['name']);
                                    break;
                                }
                            }
                        }
                        ?>
                        <span id="selectedCustomerLabel" class="truncate"><?= $selected_cust_name ?></span>
                        <i class="fas fa-chevron-down text-gray-400 text-[10px]"></i>
                    </button>
                    
                    <!-- Searchable Panel -->
                    <div id="customerDropdownPanel" class="hidden absolute z-[100] w-full mt-2 bg-white border border-gray-200 rounded-2xl shadow-2xl overflow-hidden glass transform origin-top transition-all scale-95 opacity-0">
                        <div class="p-3 border-b border-gray-100 bg-gray-50/50">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" id="customerSearchInput" autocomplete="off" oninput="filterCustomers(this.value)" 
                                       placeholder="Type to search name..." 
                                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10 outline-none transition-all">
                            </div>
                        </div>
                        <div class="max-h-64 overflow-y-auto" id="customerList">
                            <div onclick="selectCustomer('', 'Select Customer Name...')" class="customer-nav-item p-3 text-sm hover:bg-teal-50 cursor-pointer text-gray-500 italic border-b border-gray-50 flex items-center gap-2">
                                <i class="fas fa-undo text-xs"></i> Reset Selection
                            </div>
                            <?php foreach($customers as $c): ?>
                                <div onclick="selectCustomer('<?= $c['id'] ?>', '<?= htmlspecialchars($c['name']) ?>')" 
                                     class="customer-item customer-nav-item p-3 text-sm hover:bg-teal-50 cursor-pointer border-b border-gray-50 transition-colors flex flex-col" 
                                     data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>">
                                    <span class="font-bold text-gray-700"><?= htmlspecialchars($c['name']) ?></span>
                                    <span class="text-[10px] text-gray-400 font-medium"><?= htmlspecialchars($c['phone'] ?? 'No Phone') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="noCustomerFound" class="hidden p-6 text-center text-gray-400 text-sm italic">
                            <i class="fas fa-user-slash block text-2xl mb-2 opacity-20"></i> No customers found...
                        </div>
                    </div>
                    
                    <input type="hidden" name="customer_id" id="customerSelect" value="<?= htmlspecialchars($customer_id_filter) ?>">
                </div>
                <?php if ($customer_id_filter && !empty($sales_list)): ?>
                    <div class="relative">
                        <i class="fas fa-file-invoice absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                        <select name="sale_id" onchange="this.form.submit()" class="w-full pl-10 pr-4 py-3 bg-teal-50 border border-teal-100 rounded-xl text-sm font-black text-teal-800 focus:ring-2 focus:ring-teal-500 outline-none transition-all shadow-sm">
                            <option value="">Select a Sale from History...</option>
                            <?php foreach($sales_list as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $sale_id == $s['id'] ? 'selected' : '' ?>>Sale #<?= $s['id'] ?> - <?= date('d M Y', strtotime($s['sale_date'])) ?> (Rs. <?= number_format($s['total_amount']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php elseif ($customer_id_filter): ?>
                    <p class="text-[10px] text-red-500 font-bold px-2 italic">No sale history found for this customer.</p>
                <?php endif; ?>
            </form>
        </div>

        <!-- Sale ID Search -->
        <div class="bg-white p-6 rounded-[2rem] shadow-xl border border-gray-100 glass">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                <i class="fas fa-hashtag text-orange-600"></i> Direct Sale ID
            </h3>
            <form action="" method="GET" class="flex gap-2">
                <div class="flex-1 relative">
                    <i class="fas fa-barcode absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                    <input type="text" name="sale_id" value="<?= htmlspecialchars($sale_id) ?>" placeholder="Enter ID (e.g. 102)..." 
                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-orange-500 outline-none transition-all shadow-sm">
                </div>
                <button type="submit" class="px-6 py-3 bg-orange-600 text-white font-black rounded-xl hover:bg-orange-700 transition-all shadow-lg shadow-orange-900/20 active:scale-95 text-xs">
                    LOAD
                </button>
            </form>
        </div>
    </div>

    <?php if ($sale_id && !$sale): ?>
        <div class="bg-red-50 text-red-600 p-6 rounded-[2rem] border border-red-100 mb-6 text-center font-bold shadow-xl shadow-red-900/5 glass">
            <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-search-minus text-2xl"></i>
            </div>
            <p class="text-xl tracking-tight">Sale #<?= htmlspecialchars($sale_id) ?> not found.</p>
            <p class="text-sm opacity-60 font-medium mt-1">Please double check the ID or search by customer name.</p>
        </div>
    <?php elseif ($sale): ?>
        <!-- Sale Details -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
            <div class="flex justify-between items-start mb-6 pb-6 border-b border-gray-100">
                <div>
                    <h2 class="text-2xl font-black text-gray-800">Sale #<?= $sale['id'] ?></h2>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mt-1"><?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest">Customer</p>
                    <p class="font-bold text-gray-700"><?= $customer ? htmlspecialchars($customer['name']) : 'Walk-in' ?></p>
                </div>
            </div>

            <form action="../actions/process_return.php" method="POST" id="returnForm" onsubmit="validateReturn(event)">
                <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                
                <table class="w-full text-left mb-6">
                    <thead>
                        <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                            <th class="py-3 px-2">Product</th>
                            <th class="py-3 px-2 text-center">Sold Qty</th>
                            <th class="py-3 px-2 text-center">Already Returned</th>
                            <th class="py-3 px-2 text-center w-32">Return Qty</th>
                            <th class="py-3 px-2 text-right">Price</th>
                            <th class="py-3 px-2 text-right">Refund Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($items as $item): 
                            $p = $products[$item['product_id']] ?? null;
                            $available_to_return = (float)$item['quantity'] - (float)($item['returned_qty'] ?? 0);
                        ?>
                        <tr class="group hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-2">
                                <p class="font-bold text-gray-800 text-sm"><?= $p ? htmlspecialchars($p['name']) : 'Unknown' ?></p>
                                <p class="text-[10px] text-gray-400 uppercase font-medium mt-0.5"><?= $p['category'] ?? '' ?></p>
                            </td>
                            <td class="py-4 px-2 text-center font-bold text-gray-600"><?= $item['quantity'] ?></td>
                            <td class="py-4 px-2 text-center font-bold text-red-400"><?= $item['returned_qty'] ?? 0 ?></td>
                            <td class="py-4 px-2">
                                <div class="relative group/input">
                                    <input type="number" name="return_qty[<?= $item['id'] ?>]" 
                                           data-max="<?= $available_to_return ?>" 
                                           data-price="<?= $item['price_per_unit'] ?>"
                                           value="0" min="0" max="<?= $available_to_return ?>" step="any"
                                           oninput="calculateRefundTotal(this)"
                                           class="return-input w-full p-2 text-center font-black text-teal-700 bg-teal-50/30 border border-teal-100 rounded-lg focus:border-teal-500 focus:bg-white outline-none transition-all <?= $available_to_return <= 0 ? 'opacity-30 pointer-events-none' : '' ?>">
                                    <?php if($available_to_return <= 0): ?>
                                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                            <span class="text-[8px] font-black text-gray-400 uppercase bg-white px-1">Full Returned</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-4 px-2 text-right font-bold text-gray-500">Rs. <?= number_format($item['price_per_unit']) ?></td>
                            <td class="py-4 px-2 text-right font-black text-gray-800 refund-row-total">Rs. 0</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Summary Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end border-t border-gray-100 pt-6">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Reason for Return</label>
                        <textarea name="remarks" rows="2" placeholder="Item damaged / customer changed mind..." 
                                  class="w-full p-3 text-sm border border-gray-200 rounded-xl focus:border-teal-500 outline-none resize-none"></textarea>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-gray-400 uppercase">Sub-Total Refund</span>
                            <span class="text-sm font-bold text-gray-600" id="totalRefundLabel">Rs. 0</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <span class="text-sm font-black text-teal-800 uppercase tracking-wider">Total Refund Amount</span>
                            <span class="text-xl font-black text-teal-700" id="finalRefundLabel">Rs. 0</span>
                        </div>
                        <input type="hidden" name="total_refund" id="totalRefundInput" value="0">
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <a href="sales_history.php" class="px-8 py-3 bg-gray-100 text-gray-500 font-bold rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
                    <button type="submit" name="process_return" id="submitBtn" disabled
                            class="px-10 py-3 bg-teal-600 text-white font-black rounded-xl shadow-xl shadow-teal-900/20 hover:bg-teal-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        Process Return
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function calculateRefundTotal(input) {
    const val = parseFloat(input.value) || 0;
    const max = parseFloat(input.dataset.max);
    const price = parseFloat(input.dataset.price);

    if (val > max) {
        input.value = max;
    }

    const rowTotal = (parseFloat(input.value) || 0) * price;
    input.closest('tr').querySelector('.refund-row-total').innerText = 'Rs. ' + Math.round(rowTotal).toLocaleString();

    updateGrandTotal();
}

function updateGrandTotal() {
    let total = 0;
    document.querySelectorAll('.return-input').forEach(input => {
        const val = parseFloat(input.value) || 0;
        const price = parseFloat(input.dataset.price);
        total += val * price;
    });

    total = Math.round(total);
    document.getElementById('totalRefundLabel').innerText = 'Rs. ' + total.toLocaleString();
    document.getElementById('finalRefundLabel').innerText = 'Rs. ' + total.toLocaleString();
    document.getElementById('totalRefundInput').value = total;

    const btn = document.getElementById('submitBtn');
    btn.disabled = (total <= 0);
}

function validateReturn(event) {
    if (event) event.preventDefault();
    const total = parseFloat(document.getElementById('totalRefundInput').value) || 0;
    if (total <= 0) {
        showAlert("Please enter a return quantity for at least one item.", "Error");
        return false;
    }
    
    showConfirm("Are you sure you want to process this return? This will adjust stock and customer ledger.", () => {
        // Create a hidden input to ensure the button name is sent
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'process_return';
        hiddenInput.value = '1';
        const form = document.getElementById('returnForm');
        form.appendChild(hiddenInput);
        form.submit();
    }, "Process Return?");
    
    return false;
}

// --- Searchable Customer Dropdown Logic ---
function toggleCustomerDropdown() {
    const panel = document.getElementById('customerDropdownPanel');
    const isHidden = panel.classList.contains('hidden');
    
    if (isHidden) {
        panel.classList.remove('hidden');
        setTimeout(() => {
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
            document.getElementById('customerSearchInput').focus();
        }, 10);
    } else {
        closeCustomerDropdown();
    }
}

function closeCustomerDropdown() {
    const panel = document.getElementById('customerDropdownPanel');
    if (!panel) return;
    panel.classList.remove('scale-100', 'opacity-100');
    panel.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        panel.classList.add('hidden');
    }, 200);
}

function filterCustomers(query) {
    const q = query.toLowerCase();
    const navItems = document.querySelectorAll('.customer-nav-item');
    const customerItems = document.querySelectorAll('.customer-item');
    let foundCount = 0;
    
    customerItems.forEach(item => {
        const name = item.dataset.name;
        if (name.includes(q)) {
            item.classList.remove('hidden');
            foundCount++;
        } else {
            item.classList.add('hidden');
        }
    });

    navItems.forEach(item => {
        if (!item.classList.contains('customer-item')) {
            if (q !== '') item.classList.add('hidden');
            else item.classList.remove('hidden');
        }
    });
    
    const activeClass = 'customer-active';
    const highlightClasses = ['bg-teal-100', 'shadow-inner', 'ring-1', 'ring-teal-200', activeClass];
    navItems.forEach(item => item.classList.remove(...highlightClasses));
    
    const visibleItems = Array.from(navItems).filter(el => !el.classList.contains('hidden'));
    if (visibleItems.length > 0 && q !== '') {
        visibleItems[0].classList.add(...highlightClasses);
    }
    
    document.getElementById('noCustomerFound').classList.toggle('hidden', foundCount > 0 || q === '');
}

function selectCustomer(id, name) {
    document.getElementById('customerSelect').value = id;
    document.getElementById('selectedCustomerLabel').innerText = name;
    closeCustomerDropdown();
    // In return_product.php, selecting a customer should auto-submit to load sales
    document.getElementById('customerSearchForm').submit();
}

// Close on outside click
document.addEventListener('click', function(e) {
    const container = document.getElementById('customerDropdownContainer');
    if (container && !container.contains(e.target)) {
        closeCustomerDropdown();
    }
});

// Key nav
document.getElementById('customerSearchInput').addEventListener('keydown', function(e) {
    const activeClass = 'customer-active';
    const highlightClasses = ['bg-teal-100', 'shadow-inner', 'ring-1', 'ring-teal-200', activeClass];
    const visibleItems = Array.from(document.querySelectorAll('.customer-nav-item')).filter(el => !el.classList.contains('hidden'));
    let currentIndex = visibleItems.findIndex(el => el.classList.contains(activeClass));

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (visibleItems.length === 0) return;
        if (currentIndex !== -1) visibleItems[currentIndex].classList.remove(...highlightClasses);
        currentIndex = (currentIndex + 1) % visibleItems.length;
        visibleItems[currentIndex].classList.add(...highlightClasses);
        visibleItems[currentIndex].scrollIntoView({ block: 'nearest' });
    } 
    else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (visibleItems.length === 0) return;
        if (currentIndex !== -1) visibleItems[currentIndex].classList.remove(...highlightClasses);
        currentIndex = (currentIndex === -1 || currentIndex === 0) ? visibleItems.length - 1 : currentIndex - 1;
        visibleItems[currentIndex].classList.add(...highlightClasses);
        visibleItems[currentIndex].scrollIntoView({ block: 'nearest' });
    } 
    else if (e.key === 'Enter') {
        e.preventDefault();
        const activeItem = visibleItems[currentIndex] || document.querySelector('.' + activeClass);
        if (activeItem) activeItem.click();
    }
});
</script>

<?php if ($msg): ?>
<script>showAlert("<?= htmlspecialchars($msg) ?>", "Success");</script>
<?php endif; ?>

<?php if ($error): ?>
<script>showAlert("<?= htmlspecialchars($error) ?>", "Error");</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
