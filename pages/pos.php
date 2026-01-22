<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$pageTitle = "Point of Sale";
include '../includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    // 1. Handle New Customer Registration
    $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : '';
    if ($customer_id == 'NEW') {
        $customer_id = insertCSV('customers', [
            'name' => cleanInput($_POST['new_customer_name']),
            'phone' => cleanInput($_POST['new_customer_phone']),
            'address' => cleanInput($_POST['new_customer_address']),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // 2. Validate & Deduct Stock Atomically
    $items = json_decode($_POST['cart_data'], true);
    $error_items = [];
    
    // We use the simpler processCSVTransaction to lock, read, verify, deduct, and write in one go.
    $final_products = [];
    $transaction_success = processCSVTransaction('products', function($all_products) use ($items, &$error_items, &$final_products) {
        $product_map = [];
        foreach($all_products as $i => $p) $product_map[$p['id']] = $i;
        
        foreach ($items as $item) {
            $pid = $item['id'];
            if (!isset($product_map[$pid])) {
                continue; 
            }
            $idx = $product_map[$pid];
            $current_stock = (int)$all_products[$idx]['stock_quantity'];
            $qty_needed = (int)$item['qty'];
            
            if ($current_stock < $qty_needed) {
                $name = $all_products[$idx]['name'] ?? 'Unknown Item';
                $error_items[] = "$name (Available: $current_stock)";
            } else {
                $all_products[$idx]['stock_quantity'] = $current_stock - $qty_needed;
            }
        }
        
        if (!empty($error_items)) {
            return false;
        }
        
        $final_products = $all_products; // Capture state
        return $all_products;
    });

    if (!$transaction_success) {
        if (!empty($error_items)) {
            $message = "ERROR: Stock limit exceeded during checkout for: " . implode(', ', $error_items);
        } else {
            $message = "ERROR: Database transaction failed. Please try again.";
        }
    } else if ($items) {
        // 3. Create Sale Header
        $sale_data = [
            'customer_id' => $customer_id,
            'total_amount' => $_POST['total_amount'],
            'paid_amount' => $_POST['paid_amount'],
            'payment_method' => $_POST['payment_method'],
            'sale_date' => date('Y-m-d H:i:s')
        ];
        $sale_id = insertCSV('sales', $sale_data);

        // 3.1 Log to Customer Transactions (Connectivity)
        if (!empty($customer_id)) {
            insertCSV('customer_transactions', [
                'customer_id' => $customer_id,
                'type' => 'Sale',
                'debit' => $_POST['total_amount'],
                'credit' => $_POST['paid_amount'],
                'description' => "Sale #$sale_id",
                'date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s'),
                'sale_id' => $sale_id
            ]);
        }

        // 4. Create Sale Items using CAPTURED state
        foreach ($items as $item) {
            // Find product in our captured state
            $cost_price = 0; 
            $avco_price = 0; 
            
            foreach ($final_products as $p) {
                if ($p['id'] == $item['id']) {
                    $cost_price = $p['buy_price'];
                    $avco_price = isset($p['avg_buy_price']) ? $p['avg_buy_price'] : $p['buy_price'];
                    break;
                }
            }

            // Insert Sale Item
            insertCSV('sale_items', [
                'sale_id' => $sale_id,
                'product_id' => $item['id'],
                'quantity' => $item['qty'],
                'price_per_unit' => $item['price'],
                'buy_price' => $cost_price,         // Standard/Latest Cost
                'avg_buy_price' => $avco_price,     // Accurate AVCO Cost
                'total_price' => $item['total']
            ]);
        }
        $message = "Sale recorded successfully!";
    }
}

// Fetch Data form CSV
$customers = readCSV('customers');
$products = readCSV('products');
$categories = readCSV('categories');

// Filter out out-of-stock? Maybe just show them
?>

<style>
<style>
    .product-card {
        border-radius: 1.5rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .product-card:active {
        transform: scale(0.95);
    }
    .product-card.selected {
        border-color: #0d9488 !important;
        box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.2) !important;
        background-color: #f0fdfa;
    }
</style>

<div class="flex flex-col lg:flex-row gap-6 h-auto lg:h-[calc(100vh-140px)]">
    <!-- Left: Product Selection -->
    <div class="w-full lg:w-2/3 flex flex-col bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden glass">
        <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex space-x-4 items-center">
            <div class="flex-1 relative group">
                <i class="fas fa-search absolute left-4 top-3.5 text-gray-300 group-focus-within:text-teal-500 transition-colors"></i>
                <input type="text" id="productSearch" autofocus placeholder="Search products..." class="w-full pl-12 pr-4 py-3 rounded-2xl border-gray-200 bg-white focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 outline-none transition-all shadow-sm font-medium">
            </div>
            <select id="categoryFilter" class="px-4 py-3 border-gray-200 rounded-2xl bg-white text-gray-600 focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 outline-none transition-all shadow-sm font-semibold text-sm">
                <option value="all">Total Inventory</option>
                <?php foreach($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="flex-1 overflow-y-auto p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 align-content-start" id="productList">
            <?php foreach ($products as $p): 
                if ($p['stock_quantity'] <= 0) continue; // Hide out of stock
            ?>
                <div class="product-card bg-white border border-gray-100 shadow-sm p-4 hover:shadow-xl cursor-pointer transition select-none flex flex-col justify-between h-40 group"
                     onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', <?= $p['sell_price'] ?>, '<?= $p['unit'] ?>', <?= $p['stock_quantity'] ?>, <?= $p['buy_price'] ?>)"
                     data-name="<?= strtolower($p['name']) ?>"
                     data-category="<?= $p['category'] ?>">
                    
                    <div>
                        <div class="flex justify-between items-start mb-2">
                             <span class="text-[10px] px-2 py-0.5 rounded-lg bg-gray-50 text-gray-400 font-bold uppercase tracking-wider border border-gray-100"><?= $p['category'] ?></span>
                        </div>
                        <h4 class="font-bold text-gray-800 text-sm leading-tight group-hover:text-teal-600 transition-colors"><?= $p['name'] ?></h4>
                    </div>
                      <div class="flex flex-col mt-auto pt-3 border-t border-gray-50/50">
                            <span class="text-[10px] text-gray-400 font-semibold uppercase mb-0.5">Price</span>
                            <div class="flex justify-between items-end">
                                <span class="font-bold text-gray-800">Rs. <?= number_format((float)$p['sell_price']) ?></span>
                                <span id="stock-label-<?= $p['id'] ?>" 
                                        data-id="<?= $p['id'] ?>"
                                        data-stock="<?= $p['stock_quantity'] ?>"
                                        class="text-[10px] font-bold <?= $p['stock_quantity'] < 5 ? 'text-red-500 bg-red-50 px-2 py-0.5 rounded-full border border-red-100' : 'text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full border border-gray-100' ?>">
                                    <?= $p['stock_quantity'] ?> left
                                </span>
                            </div>
                      </div>
                </div>
<?php endforeach; ?>
        </div>
    </div>

    <!-- Right: Cart & Checkout -->
    <div class="w-full lg:w-1/3 bg-white rounded-3xl shadow-sm border border-gray-100 flex flex-col glass overflow-hidden">
        <div class="p-4 border-b bg-teal-700 text-white">
            <h2 class="text-lg font-bold"><i class="fas fa-shopping-cart mr-2"></i> Current Sale</h2>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto p-4 space-y-2" id="cartItems">
            <!-- Items injected by JS -->
            <p class="text-center text-gray-400 mt-10" id="emptyCartMsg">Cart is empty</p>
        </div>

        <!-- Totals & Checkout -->
        <div class="p-4 bg-gray-50 border-t space-y-3">
            <div id="stockWarning" class="hidden bg-red-100 text-red-600 p-2 rounded text-xs font-bold border border-red-200">
                <i class="fas fa-exclamation-triangle mr-1"></i> Stock limit reached for some items!
            </div>
            <div class="mb-6">
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider block mb-2">Total Payable (Editable)</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-gray-400 text-sm font-bold">Rs.</span>
                    <input type="number" id="grandTotal" 
                           class="w-full pl-12 pr-4 py-3 text-2xl font-bold text-gray-800 border-2 rounded-xl focus:ring-4 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all" 
                           placeholder="0" 
                           oninput="handleTotalChange()" 
                           min="0">
                </div>
                <div id="priceWarning" class="hidden mt-2 p-2 bg-red-50 text-red-600 text-xs font-bold rounded border border-red-200">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <span id="priceWarningText">Price is below cost!</span>
                </div>
            </div>
            
            <form method="POST" id="checkoutForm">
                <input type="hidden" name="checkout" value="1">
                <input type="hidden" name="cart_data" id="cartData">
                <input type="hidden" name="total_amount" id="inputTotal">

                <div class="mb-2">
                    <label class="text-sm font-semibold text-gray-600">Customer</label>
                    <select name="customer_id" id="customerSelect" onchange="handleCustomerChange(this.value)" class="w-full p-2 border rounded text-sm bg-white focus:ring-2 focus:ring-teal-500">
                        <option value="">Walk-in Customer</option>
                        <option value="NEW" class="text-teal-600 font-bold">+ Add New Customer</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= $c['name'] ?> (<?= $c['phone'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- New Customer Fields (Hidden by default) -->
                <div id="newCustomerFields" class="hidden mb-3 p-3 bg-teal-50 rounded-lg border border-teal-100 space-y-2">
                    <input type="text" name="new_customer_name" id="new_customer_name" placeholder="Customer Name *" class="w-full p-1.5 text-xs border rounded">
                    <input type="text" name="new_customer_phone" placeholder="Phone (Optional)" class="w-full p-1.5 text-xs border rounded">
                    <input type="text" name="new_customer_address" placeholder="Address (Optional)" class="w-full p-1.5 text-xs border rounded">
                </div>

                <div class="grid grid-cols-2 gap-2 mb-2">
                    <div>
                        <label class="text-sm font-semibold text-gray-600">Paid Amount</label>
                        <input type="number" name="paid_amount" id="paidAmount" required class="w-full p-2 border rounded text-sm" placeholder="0" oninput="calculateDebt()">
                    </div>
                     <div>
                        <label class="text-sm font-semibold text-gray-600">Payment</label>
                        <select name="payment_method" id="paymentMethod" onchange="handlePaymentChange(this.value)" class="w-full p-2 border rounded text-sm">
                            <option value="Cash">Cash</option>
                            <option value="Partial">Partial / Debt</option>
                            <option value="Fully Debt">Fully Debt</option>
                        </select>
                    </div>
                </div>

                <!-- Debt Display -->
                <div id="debtDisplay" class="hidden flex justify-between items-center mb-3 p-2 bg-red-50 text-red-700 rounded text-xs font-bold border border-red-100">
                    <span>Remaining Debt:</span>
                    <span id="debtAmount">Rs. 0</span>
                </div>

                <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-teal-500/10 transition-all active:scale-95 text-lg">
                    Complete Sale
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let cart = [];

function addToCart(id, name, price, unit, stock, buyPrice) {
    const sId = String(id);
    let existing = cart.find(i => String(i.id) === sId);
    if (existing) {
        if (existing.qty + 1 > stock) {
            showAlert(`Cannot add more. Only ${stock} ${unit} available in stock!`, 'Stock Limit');
            return;
        }
        existing.qty++;
        existing.total = existing.qty * existing.price;
    } else {
        if (stock < 1) {
            showAlert("This item is out of stock!", 'Out Of Stock');
            return;
        }
        cart.push({ id: sId, name, price, unit, qty: 1, total: price, max_stock: stock, buy_price: parseFloat(buyPrice) || 0 });
    }
    updateStockLabels();
    renderCart();
}

function updateStockLabels() {
    // 1. Reset all cards to standard state
    document.querySelectorAll('.product-card').forEach(card => {
        const label = card.querySelector('[id^="stock-label-"]');
        if (!label) return;
        
        const max = parseInt(label.dataset.stock) || 0;
        label.innerText = `${max} left`;
        label.classList.remove('text-red-500', 'text-gray-300');
        label.classList.add('text-gray-400');
        card.style.opacity = '1';
        card.style.pointerEvents = 'auto';
    });

    // 2. Apply cart subtractions to labels
    cart.forEach(item => {
        const label = document.getElementById('stock-label-' + item.id);
        if (label) {
            const max = parseInt(label.dataset.stock) || 0;
            const remaining = max - item.qty;
            label.innerText = `${remaining} left`;
            
            const card = label.closest('.product-card');
            if (remaining <= 0) {
                label.classList.remove('text-gray-400');
                label.classList.add('text-gray-300');
                if (card) {
                    card.style.opacity = '0.5';
                    card.style.pointerEvents = 'none';
                }
            } else if (remaining < 5) {
                label.classList.remove('text-gray-400');
                label.classList.add('text-red-500');
            }
        }
    });
}


function removeFromCart(index) {
    cart.splice(index, 1);
    updateStockLabels();
    renderCart();
}

function updateQty(index, newQty) {
    let qty = parseInt(newQty);
    if (isNaN(qty) || qty < 1) qty = 1;
    
    if (qty > cart[index].max_stock) {
        qty = cart[index].max_stock;
    }
    
    // Sync input field if it was capped
    const qtyInput = document.getElementById(`qty-input-${index}`);
    if (qtyInput && parseInt(qtyInput.value) !== qty) {
        qtyInput.value = qty;
    }

    cart[index].qty = qty;
    cart[index].total = cart[index].qty * cart[index].price;
    
    // Update individual item total in UI
    const itemTotalEl = document.getElementById(`item-total-${index}`);
    if (itemTotalEl) {
        itemTotalEl.innerText = cart[index].total.toLocaleString();
    }
    
    updateStockLabels();
    updateTotals();
}


function renderCart() {
    const container = document.getElementById('cartItems');
    
    if (cart.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-400 mt-10" id="emptyCartMsg">Cart is empty</p>';
        updateTotals();
        return;
    }
    
    container.innerHTML = cart.map((item, index) => `
        <div class="flex justify-between items-center bg-gray-100 p-2 rounded shadow-sm">
            <div class="flex-1">
                <p class="font-semibold text-sm text-gray-800">${item.name}</p>
                <div class="flex items-center text-[10px] space-x-2">
                    <span class="text-gray-500">Rs. ${item.price} / ${item.unit}</span>
                    <span class="font-bold text-teal-600 underline">Stock: ${item.max_stock}</span>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <input type="number" id="qty-input-${index}" value="${item.qty}" min="1" max="${item.max_stock}" 
                       class="w-14 p-1 text-center text-sm border rounded font-bold ${item.qty >= item.max_stock ? 'border-red-500 text-red-600' : 'border-gray-200'}" 
                       oninput="updateQty(${index}, this.value)">
                <span class="font-mono font-bold text-sm text-teal-700 w-16 text-right" id="item-total-${index}">${item.total.toLocaleString()}</span>
                <button onclick="removeFromCart(${index})" class="w-6 h-6 rounded flex items-center justify-center text-red-400 hover:bg-red-500 hover:text-white transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        </div>
    `).join('');
    
    updateTotals();
}

function updateTotals() {
    let total = cart.reduce((sum, item) => sum + item.total, 0);
    const grandTotalInput = document.getElementById('grandTotal');
    
    // Only auto-fill if it's empty or not manually edited
    if (!grandTotalInput.dataset.manualEdit || grandTotalInput.value == '') {
        grandTotalInput.value = Math.round(total);
        delete grandTotalInput.dataset.manualEdit;
    }
    
    document.getElementById('inputTotal').value = grandTotalInput.value || total;
    document.getElementById('cartData').value = JSON.stringify(cart);
    
    const paidInput = document.getElementById('paidAmount');
    const currentTotal = parseInt(grandTotalInput.value) || total;
    // Default to cash behavior if Cash is selected
    if (document.getElementById('paymentMethod').value === 'Cash') {
        paidInput.value = currentTotal;
    }
    
    validateTotalPrice();
    calculateDebt();
}

function handlePaymentChange(method) {
    const paidInput = document.getElementById('paidAmount');
    const total = parseInt(document.getElementById('inputTotal').value) || 0;
    const debtDisplay = document.getElementById('debtDisplay');
    
    if (method === 'Fully Debt') {
        paidInput.value = 0;
        paidInput.readOnly = true;
        debtDisplay.classList.remove('hidden');
    } else if (method === 'Cash') {
        paidInput.value = total;
        paidInput.readOnly = false;
        debtDisplay.classList.add('hidden');
    } else { // Partial
        paidInput.readOnly = false;
        debtDisplay.classList.remove('hidden');
    }
    calculateDebt();
}

function calculateDebt() {
    const total = parseInt(document.getElementById('inputTotal').value) || 0;
    const paid = parseInt(document.getElementById('paidAmount').value) || 0;
    const customer = document.getElementById('customerSelect').value;
    
    // Prevent walk-in customer from overpaying
    if ((!customer || customer === '') && paid > total) {
        document.getElementById('paidAmount').value = total;
        showAlert("Walk-in customers cannot overpay! Paid amount adjusted to match total.", "Payment Adjusted");
        return;
    }
    
    const debt = total - paid;
    document.getElementById('debtAmount').innerText = 'Rs. ' + (debt > 0 ? debt.toLocaleString() : 0);
    
    const method = document.getElementById('paymentMethod').value;
    if (method !== 'Cash' && debt > 0) {
        document.getElementById('debtDisplay').classList.remove('hidden');
    } else if (method === 'Cash') {
        document.getElementById('debtDisplay').classList.add('hidden');
    }
}

function handleCustomerChange(val) {
    toggleNewCustomerFields(val);
    
    // Auto-adjust paid amount for walk-in customer
    if (!val || val === '') {
        const total = parseInt(document.getElementById('inputTotal').value) || 0;
        document.getElementById('paidAmount').value = total;
        calculateDebt();
    }
}

function toggleNewCustomerFields(val) {
    const fields = document.getElementById('newCustomerFields');
    if (val === 'NEW') {
        fields.classList.remove('hidden');
        document.getElementById('new_customer_name').required = true;
    } else {
        fields.classList.add('hidden');
        document.getElementById('new_customer_name').required = false;
    }
}

function handleTotalChange() {
    const grandTotalInput = document.getElementById('grandTotal');
    grandTotalInput.dataset.manualEdit = 'true';
    
    const customTotal = parseInt(grandTotalInput.value) || 0;
    document.getElementById('inputTotal').value = customTotal;
    
    validateTotalPrice();
    calculateDebt();
}

function validateTotalPrice() {
    const customTotal = parseInt(document.getElementById('grandTotal').value) || 0;
    
    // Calculate minimum allowed total (sum of buy_price * qty for all items)
    let minTotal = 0;
    cart.forEach(item => {
        minTotal += (item.buy_price || 0) * item.qty;
    });
    
    const warning = document.getElementById('priceWarning');
    const warningText = document.getElementById('priceWarningText');
    const grandTotalInput = document.getElementById('grandTotal');
    
    if (customTotal > 0 && customTotal < minTotal) {
        warning.classList.remove('hidden');
        warningText.innerText = `Price is below cost! Minimum: Rs. ${minTotal.toLocaleString()}`;
        grandTotalInput.classList.add('border-red-500', 'bg-red-50');
        grandTotalInput.classList.remove('border-gray-200');
        return false;
    } else {
        warning.classList.add('hidden');
        grandTotalInput.classList.remove('border-red-500', 'bg-red-50');
        grandTotalInput.classList.add('border-gray-200');
        return true;
    }
}

// Form Validation
document.getElementById('checkoutForm').onsubmit = function(e) {
    const method = document.getElementById('paymentMethod').value;
    const customer = document.getElementById('customerSelect').value;
    const total = parseInt(document.getElementById('inputTotal').value) || 0;
    const paid = parseInt(document.getElementById('paidAmount').value) || 0;

    if (total === 0) {
        showAlert("Cannot checkout with empty cart!", "Empty Cart");
        return false;
    }

    // Price Validation - Check if selling below cost
    if (!validateTotalPrice()) {
        showAlert("Cannot sell below cost price! Please adjust the total amount.", "Price Too Low");
        return false;
    }

    // Stock Validation
    let stockErrors = [];
    cart.forEach(item => {
        if (item.qty > item.max_stock) {
            stockErrors.push(`${item.name} (Stock: ${item.max_stock})`);
        }
    });

    if (stockErrors.length > 0) {
        showAlert("Found items exceeding available stock:\n" + stockErrors.join("\n"), "Stock Error");
        return false;
    }

    // Overpayment Validation - Require customer for credit to ledger
    if (paid > total) {
        if (!customer || customer === '') {
            showAlert("Customer selection is mandatory when paid amount exceeds total! The excess will be credited to customer ledger.", "Customer Required");
            return false;
        }
    }

    // Debt Validation - Require customer for unpaid amounts
    if (method !== 'Cash' || paid < total) {
        if (!customer || customer === '') {
            showAlert("Customer selection is mandatory for Debt/Credit sales!", "Customer Required");
            return false;
        }
    }
    return true;
};

function filterProducts() {
    const term = document.getElementById('productSearch').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    let firstVisibleHighlight = false;
    
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.name;
        const cat = card.dataset.category;
        
        const nameMatch = name.includes(term);
        const catMatch = (category === 'all' || cat === category);
        
        card.classList.remove('selected');

        if(nameMatch && catMatch) {
            card.style.display = 'flex';
            if (term !== '' && !firstVisibleHighlight) {
                card.classList.add('selected');
                firstVisibleHighlight = true;
            }
        } else {
            card.style.display = 'none';
        }
    });
}

document.getElementById('productSearch').addEventListener('input', filterProducts);

document.getElementById('productSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const selected = document.querySelector('.product-card.selected');
        if (selected) {
            selected.click(); // Trigger addToCart
            this.value = ''; // Clear search
            filterProducts(); // Reset view
        }
        e.preventDefault();
    }
});

document.getElementById('categoryFilter').addEventListener('change', filterProducts);
</script>

<?php if ($message): ?>
<script>
    showAlert("<?= $message ?>", "Success");
    // Optionally redirect after a delay or on close
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
