<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['id'])) redirect('sales_history.php');
$sale_id = $_GET['id'];

// Load Sale Data
$sale = findCSV('sales', $sale_id);
if (!$sale) redirect('sales_history.php?error=Sale not found');

// Get Due Date from transactions if not in sale
if (!isset($sale['due_date'])) {
    $sale['due_date'] = '';
    $transactions = readCSV('customer_transactions');
    foreach ($transactions as $tx) {
        if (isset($tx['sale_id']) && $tx['sale_id'] == $sale_id && !empty($tx['due_date'])) {
            $sale['due_date'] = $tx['due_date'];
            break;
        }
    }
}

// Load Sale Items
$all_sale_items = readCSV('sale_items');
$current_items = array_filter($all_sale_items, function($si) use ($sale_id) {
    return $si['sale_id'] == $sale_id;
});

// Load Products & Categories for selection
$products = readCSV('products');
$categories = readCSV('categories');
$customers = readCSV('customers');

$pageTitle = "Edit Sale #" . $sale_id;
include '../includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 pb-20">
    <!-- Left: Cart & Finalize -->
    <div class="lg:col-span-5 flex flex-col space-y-4">
        <div class="bg-white rounded-[2rem] shadow-xl border border-gray-100 flex flex-col overflow-hidden glass">
            <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                <div>
                    <?php 
                        $c_name = "Walk-in Customer";
                        if (!empty($sale['customer_id'])) {
                            foreach($customers as $c) {
                                if ($c['id'] == $sale['customer_id']) {
                                    $c_name = $c['name'];
                                    break;
                                }
                            }
                        }
                    ?>
                    <h2 class="font-black text-gray-800 text-xl tracking-tight"><?= htmlspecialchars($c_name) ?></h2>
                    <p class="text-[10px] font-bold text-teal-600 uppercase tracking-widest mt-0.5">Sale #<?= $sale_id ?> • Modify Bill</p>
                </div>
                <div class="bg-teal-100 text-teal-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">Active Edit</div>
            </div>

            <!-- Cart Table -->
            <div class="overflow-y-auto max-h-[400px] custom-scrollbar p-4">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-2">
                            <th class="py-2">Item</th>
                            <th class="py-2 text-center w-24">QTY</th>
                            <th class="py-2 text-right w-24">Price</th>
                            <th class="py-2 text-right w-24">Total</th>
                            <th class="py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody id="cartItems" class="divide-y divide-gray-50">
                        <!-- JS Populated -->
                    </tbody>
                </table>
            </div>

            <!-- Totals Section -->
            <div class="p-6 bg-gray-50/80 border-t border-gray-100 space-y-3">
                <div class="flex justify-between items-center text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">
                    <span>Summary</span>
                    <span class="text-teal-600">Active Bill</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs font-bold text-gray-500">Cart Subtotal</span>
                    <span id="subtotal" class="font-bold text-gray-700">Rs. 0</span>
                </div>
                <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-tight">Discount Amount</span>
                    <div class="text-right">
                        <input type="number" id="discount_input" name="discount" value="<?= $sale['discount'] ?? 0 ?>" oninput="calculateTotals()" placeholder="Discount Rs..." class="w-32 bg-gray-50 border border-gray-100 rounded-lg p-2 text-right text-sm font-black text-red-600 focus:ring-2 focus:ring-red-500 outline-none transition-all">
                    </div>
                </div>
                <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                    <span class="text-xs font-black text-gray-800 uppercase tracking-tight">Manual Grand Total</span>
                    <div class="text-right">
                        <input type="number" id="grand_total_input" oninput="handleManualTotal(this.value)" placeholder="Override Total..." class="w-32 bg-gray-50 border border-gray-100 rounded-lg p-2 text-right text-sm font-black text-teal-600 focus:ring-2 focus:ring-teal-500 outline-none transition-all">
                    </div>
                </div>
                <div id="priceWarning" class="hidden text-center text-[10px] text-red-600 font-bold bg-red-50 p-2 rounded-xl border border-red-100 mb-2">
                    <i class="fas fa-exclamation-circle mr-1"></i> Warning: Items selling below cost price!
                </div>
                <div class="flex justify-between items-center pt-2">
                    <span class="font-black text-gray-800 tracking-tight uppercase text-sm">Grand Total (Final)</span>
                    <span id="grandTotal" class="font-black text-teal-600 text-xl tracking-tighter">Rs. 0</span>
                </div>
            </div>
        </div>

        <!-- Payment & Remarks -->
        <div class="bg-white p-6 rounded-[2rem] shadow-xl border border-gray-100 glass">
            <form id="updateSaleForm" action="../actions/update_sale.php" method="POST">
                <input type="hidden" name="sale_id" value="<?= $sale_id ?>">
                <input type="hidden" name="cart_data" id="cart_data">
                <input type="hidden" name="total_amount" id="total_amount_input">
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">Paid Amount</label>
                        <input type="number" name="paid_amount" id="paid_amount" value="<?= $sale['paid_amount'] ?>" step="0.01" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-teal-500 outline-none transition-all shadow-sm" oninput="calculateDebt()">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-teal-500 outline-none shadow-sm" onchange="handlePaymentMethodChange(this.value)">
                            <option value="Cash" <?= $sale['payment_method'] == 'Cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="Partial" <?= $sale['payment_method'] == 'Partial' ? 'selected' : '' ?>>Partial</option>
                            <option value="Fully Debt" <?= $sale['payment_method'] == 'Fully Debt' ? 'selected' : '' ?>>Debt</option>
                        </select>
                    </div>
                </div>

                <div id="debtInfoContainer" class="mb-4 hidden">
                    <div class="p-4 bg-red-50 border border-red-100 rounded-2xl flex justify-between items-center mb-4">
                        <span class="text-[10px] font-black text-red-600 uppercase tracking-widest">Remaining Due</span>
                        <span id="remainingDebt" class="text-sm font-black text-red-700">Rs. 0</span>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-orange-600 uppercase block mb-1.5 ml-1">Expected Payment Date (Required for Debt)</label>
                        <input type="date" name="due_date" id="due_date" value="<?= $sale['due_date'] ?? '' ?>" class="w-full p-3 bg-orange-50 border border-orange-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-orange-500 outline-none transition-all shadow-sm">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">Update Remarks</label>
                    <textarea name="remarks" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-medium focus:ring-2 focus:ring-teal-500 outline-none h-20 resize-none shadow-sm"><?= htmlspecialchars($sale['remarks']) ?></textarea>
                </div>

                <button type="submit" name="update_sale" class="w-full bg-teal-600 text-white font-black py-4 rounded-2xl hover:bg-teal-700 transition-all shadow-lg shadow-teal-900/20 active:scale-95 flex items-center justify-center gap-2">
                    <i class="fas fa-check-circle"></i> SAVE CHANGES
                </button>
            </form>
        </div>
    </div>

    <!-- Right: Product Browser -->
    <div class="lg:col-span-7 flex flex-col space-y-4">
        <!-- Search & Category -->
        <div class="bg-white p-4 rounded-[1.5rem] shadow-lg border border-gray-100 flex gap-4 glass shrink-0">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
                <input type="text" id="productSearch" placeholder="Search products (Name, ID)..." class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold focus:ring-2 focus:ring-teal-500 outline-none transition-all shadow-sm">
            </div>
            <select id="categoryFilter" class="w-40 p-3 bg-gray-50 border border-gray-100 rounded-xl text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-teal-500 outline-none shadow-sm">
                <option value="">All Categories</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Product Grid -->
        <div id="productGrid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 p-2">
            <!-- JS Populated -->
        </div>
    </div>
</div>

<script>
let products = <?= json_encode($products) ?>;
let cart = [];

// Initialize Cart with current items
<?php foreach($current_items as $item): 
    // Find product details for initial cart display
    $p_name = 'Unknown'; $p_unit = ''; $p_stock = 0;
    foreach($products as $p) {
        if ($p['id'] == $item['product_id']) {
            $p_name = $p['name'];
            $p_unit = $p['unit'];
            $p_stock = (float)$p['stock_quantity'];
            break;
        }
    }
?>
cart.push({
    id: '<?= $item['product_id'] ?>',
    name: '<?= addslashes($p_name) ?>',
    unit: '<?= addslashes($p_unit) ?>',
    price: <?= $item['price_per_unit'] ?>,
    qty: <?= $item['quantity'] ?>,
    total: <?= $item['total_price'] ?>,
    buy_price: <?= $item['buy_price'] ?? 0 ?>,
    max_stock: <?= $p_stock + (float)$item['quantity'] ?>
});
<?php endforeach; ?>

function renderProducts() {
    const search = document.getElementById('productSearch').value.toLowerCase();
    const cat = document.getElementById('categoryFilter').value;
    const grid = document.getElementById('productGrid');
    
    let html = '';
    products.forEach(p => {
        if (cat && p.category !== cat) return;
        if (search && !p.name.toLowerCase().includes(search) && !p.id.includes(search)) return;
        
        const price = parseFloat(p.sell_price) || 0;
        const buyPrice = parseFloat(p.buy_price) || 0;
        const stock = parseFloat(p.stock_quantity) || 0;
        
        html += `
            <div onclick="addToCart('${p.id}', '${p.name.replace(/'/g, "\\'")}', ${price}, '${p.unit}', ${buyPrice}, ${stock})" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl transition-all hover:-translate-y-1 cursor-pointer group glass text-left">
                <div class="h-24 bg-gray-50 rounded-xl mb-3 flex items-center justify-center overflow-hidden">
                    ${p.image ? `<img src="../uploads/products/${p.image}" class="w-full h-full object-cover">` : `<i class="fas fa-box text-gray-200 text-3xl"></i>`}
                </div>
                <h3 class="text-[11px] font-black text-gray-800 uppercase tracking-tight line-clamp-1 group-hover:text-teal-600 transition-colors">${p.name}</h3>
                <div class="flex justify-between items-center mt-2">
                    <span class="text-xs font-black text-teal-600">Rs. ${price.toLocaleString()}</span>
                    <span class="text-[9px] font-bold text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded uppercase">${p.unit}</span>
                </div>
                <div class="mt-2 text-[9px] font-bold ${p.stock_quantity < 5 ? 'text-red-500' : 'text-gray-400'}">Stock: ${p.stock_quantity}</div>
            </div>
        `;
    });
    grid.innerHTML = html;
}

function addToCart(id, name, price, unit, buyPrice = 0, stock = 9999) {
    const existingIndex = cart.findIndex(i => i.id == id);
    if (existingIndex !== -1) {
        const existing = cart[existingIndex];
        if (existing.qty + 1 > existing.max_stock) {
            showAlert(`Only ${existing.max_stock} units available.`, 'Inventory Alert');
            return;
        }
        existing.qty++;
        existing.total = existing.qty * existing.price;
        
        // Move to end so it appears at top of reversed list
        cart.splice(existingIndex, 1);
        cart.push(existing);
    } else {
        if (stock < 0.01) { showAlert("Out of stock!", 'Empty Shelf'); return; }
        cart.push({ id, name, price, unit, qty: 1, total: price, buy_price: buyPrice, max_stock: parseFloat(stock) });
    }
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id == id);
    if (item) {
        if (delta > 0 && item.qty + delta > item.max_stock) {
            showAlert(`Only ${item.max_stock} units available.`, 'Inventory Alert');
            return;
        }
        item.qty += delta;
        if (item.qty <= 0) {
            cart = cart.filter(i => i.id != id);
        } else {
            item.total = item.qty * item.price;
        }
    }
    renderCart();
}

function updatePrice(id, newPrice) {
    const item = cart.find(i => i.id == id);
    if (item) {
        item.price = parseFloat(newPrice) || 0;
        item.total = item.qty * item.price;
        
        // Update the item total field without full re-render
        const totalInput = document.getElementById(`total-${item.id}`);
        if (totalInput) totalInput.value = Math.round(item.total);
        
        validateItemPrice(item);
        calculateTotals();
    }
}

function updateItemTotal(id, newTotal) {
    const item = cart.find(i => i.id == id);
    if (item) {
        item.total = parseFloat(newTotal) || 0;
        // Adjust price based on total / qty
        if (item.qty > 0) {
            item.price = item.total / item.qty;
            const priceInput = document.getElementById(`price-${item.id}`);
            if (priceInput) priceInput.value = item.price.toFixed(2);
        }
        validateItemPrice(item);
        calculateTotals();
    }
}

function validateItemPrice(item) {
    const priceInput = document.getElementById(`price-${item.id}`);
    const errorMsg = document.getElementById(`error-${item.id}`);
    const buyPrice = parseFloat(item.buy_price) || 0;
    
    if (item.price < buyPrice) {
        priceInput.classList.add('border-red-500', 'text-red-600', 'bg-red-50');
        priceInput.classList.remove('border-gray-100', 'text-gray-500');
        if (errorMsg) {
            errorMsg.innerText = "Below Purchase Price!";
            errorMsg.classList.remove('hidden');
        }
    } else {
        priceInput.classList.remove('border-red-500', 'text-red-600', 'bg-red-50');
        priceInput.classList.add('border-gray-100', 'text-gray-500');
        if (errorMsg) {
            errorMsg.classList.add('hidden');
        }
    }
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id != id);
    renderCart();
}

function calculateTotals() {
    let subtotal = 0;
    cart.forEach(item => subtotal += item.total);
    subtotal = Math.round(subtotal);

    // 1. Update Subtotal Label
    document.getElementById('subtotal').innerText = 'Rs. ' + subtotal.toLocaleString();
    
    // 2. Determine Final Total (Manual vs Calculated)
    const gtInput = document.getElementById('grand_total_input');
    const discountInput = document.getElementById('discount_input');
    const discount = parseFloat(discountInput.value) || 0;
    let totalAfterDiscount = Math.max(0, subtotal - discount);
    
    let finalTotal = totalAfterDiscount;

    if (gtInput.dataset.manual && gtInput.value !== "") {
        finalTotal = parseFloat(gtInput.value) || 0;
    } else {
        gtInput.value = Math.round(totalAfterDiscount); // Sync manual field with calculation if not overriding
    }
    
    // 3. Sync Hidden Input and Final Label
    document.getElementById('total_amount_input').value = finalTotal;
    document.getElementById('grandTotal').innerText = 'Rs. ' + Math.round(finalTotal).toLocaleString();
    
    // 4. Auto-update paid amount if Payment Method is Cash
    if (document.getElementById('payment_method').value === 'Cash') {
        document.getElementById('paid_amount').value = finalTotal;
    }

    validateTotalPrice();
    calculateDebt();
}

function validateTotalPrice() {
    const finalTotal = parseFloat(document.getElementById('total_amount_input').value) || 0;
    let minCostTotal = 0;
    cart.forEach(item => { 
        minCostTotal += (parseFloat(item.buy_price) || 0) * (parseFloat(item.qty) || 0); 
    });
    
    const warning = document.getElementById('priceWarning');
    if (finalTotal > 0 && finalTotal < Math.round(minCostTotal)) {
        warning.classList.remove('hidden');
    } else {
        warning.classList.add('hidden');
    }
    return (finalTotal >= Math.round(minCostTotal));
}

function handleManualTotal(val) {
    const gtInput = document.getElementById('grand_total_input');
    if (val === "" || val === null) {
        delete gtInput.dataset.manual;
    } else {
        gtInput.dataset.manual = "true";
    }
    calculateTotals();
}

function handlePaymentMethodChange(val) {
    const paidInput = document.getElementById('paid_amount');
    const grandTotal = parseFloat(document.getElementById('total_amount_input').value) || 0;
    
    if (val === 'Fully Debt') {
        paidInput.value = 0;
        paidInput.readOnly = true;
    } else if (val === 'Cash') {
        paidInput.value = grandTotal;
        paidInput.readOnly = false;
    } else {
        paidInput.readOnly = false;
    }
    calculateDebt();
}

function calculateDebt() {
    const grandTotal = parseFloat(document.getElementById('total_amount_input').value) || 0;
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
    const debt = grandTotal - paidAmount;
    
    const container = document.getElementById('debtInfoContainer');
    const remainingLabel = document.getElementById('remainingDebt');
    const dueDateInput = document.getElementById('due_date');
    
    // Show debt info if debt > 0 or if payment method is Partial/Debt
    const method = document.getElementById('payment_method').value;

    if (debt > 0.01 || method === 'Partial' || method === 'Fully Debt') {
        container.classList.remove('hidden');
        remainingLabel.innerText = 'Rs. ' + Math.max(0, debt).toLocaleString();
        
        if (debt > 0.01) {
            dueDateInput.required = true;
        } else {
            dueDateInput.required = false;
        }
    } else {
        container.classList.add('hidden');
        dueDateInput.required = false;
    }
}

function manualUpdateQty(id, val) {
    const item = cart.find(i => i.id == id);
    if (item) {
        let newQty = parseFloat(val);
        if (isNaN(newQty)) return;

        if (newQty > item.max_stock) {
            showAlert(`Only ${item.max_stock} units available.`, 'Inventory Alert');
            newQty = item.max_stock;
            const input = document.getElementById(`qty-input-${id}`);
            if (input) input.value = newQty;
        }
        
        item.qty = newQty;
        item.total = item.qty * item.price;
        
        // Update the item total field in the row
        const totalInput = document.getElementById(`total-${item.id}`);
        if (totalInput) totalInput.value = Math.round(item.total);
        
        calculateTotals();
    }
}

function renderCart(fullRedraw = true) {
    const tbody = document.getElementById('cartItems');
    let html = '';
    let subtotal = 0;
    
    // Reverse the cart items for display, so newest are on top
    const displayCart = [...cart].reverse();
    
    // Calculate subtotal from original cart to ensure accuracy
    cart.forEach(item => subtotal += item.total);
    
    displayCart.forEach(item => {
        html += `
            <tr class="group hover:bg-gray-50/50 transition">
                <td class="py-3">
                    <div class="font-bold text-gray-800 text-xs">${item.name}</div>
                    <div class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">${item.unit}</div>
                    <div id="error-${item.id}" class="text-[8px] font-black text-red-500 uppercase mt-1 hidden"></div>
                </td>
                <td class="py-3">
                    <div class="flex items-center justify-center gap-1.5 bg-gray-100/50 rounded-lg p-1 w-28 mx-auto">
                        <button onclick="updateQty('${item.id}', -1)" class="w-6 h-6 flex items-center justify-center bg-white rounded-md shadow-sm text-gray-400 hover:text-red-500 transition-colors shrink-0"><i class="fas fa-minus text-[8px]"></i></button>
                        <input type="number" id="qty-input-${item.id}" value="${item.qty}" 
                               oninput="manualUpdateQty('${item.id}', this.value)" 
                               class="w-12 bg-white border border-gray-100 rounded text-center text-xs font-black text-gray-700 focus:ring-1 focus:ring-teal-500 outline-none p-1">
                        <button onclick="updateQty('${item.id}', 1)" class="w-6 h-6 flex items-center justify-center bg-white rounded-md shadow-sm text-gray-400 hover:text-teal-500 transition-colors shrink-0"><i class="fas fa-plus text-[8px]"></i></button>
                    </div>
                </td>
                <td class="py-3 text-right">
                    <input type="number" id="price-${item.id}" value="${item.price}" oninput="updatePrice('${item.id}', this.value)" 
                           class="w-20 p-1 bg-transparent border-b border-gray-100 text-right text-xs font-bold text-gray-500 focus:border-teal-500 outline-none transition-all">
                </td>
                <td class="py-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                        <span class="text-[10px] text-gray-400 font-bold">Rs.</span>
                        <input type="number" id="total-${item.id}" value="${Math.round(item.total)}" oninput="updateItemTotal('${item.id}', this.value)" 
                               class="w-24 p-1 bg-transparent border-b border-gray-100 text-right text-xs font-black text-gray-800 focus:border-teal-500 outline-none">
                    </div>
                </td>
                <td class="py-3 text-center">
                    <button onclick="removeFromCart('${item.id}')" class="text-gray-300 hover:text-red-500 transition-colors"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
    });
    
    if (fullRedraw) {
        tbody.innerHTML = html || '<tr><td colspan="5" class="py-12 text-center text-gray-400 italic text-sm">Cart is empty.</td></tr>';
        // After redraw, validate all items to ensure warnings are correct
        cart.forEach(item => validateItemPrice(item));
    }
    
    calculateTotals();
}

document.getElementById('productSearch').oninput = renderProducts;
document.getElementById('categoryFilter').onchange = renderProducts;

// Handle Initial Render
window.onload = () => {
    renderProducts();
    renderCart();
    calculateDebt(); // Initialize debt view
};

document.getElementById('updateSaleForm').onsubmit = function(e) {
    if (cart.length === 0) {
        showAlert('Cannot update a sale with an empty cart.', 'Cart Empty');
        return false;
    }

    // Check individual items strictly
    let invalidItems = [];
    cart.forEach(item => {
        if (parseFloat(item.price) < (parseFloat(item.buy_price) || 0)) {
            invalidItems.push(item.name);
        }
    });

    if (invalidItems.length > 0) {
        showAlert(`You cannot save this sale! The following items are priced below their purchase price: \n\n - ${invalidItems.join('\n - ')}\n\nPlease correct the prices to proceed.`, 'Validation Error');
        return false;
    }

    if (!validateTotalPrice()) {
        if (!confirm("Warning: Total amount is below the total cost price. Are you sure you want to proceed?")) {
            return false;
        }
    }

    const totalAmount = parseFloat(document.getElementById('total_amount_input').value) || 0;
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
    const method = document.getElementById('payment_method').value;
    const dueDate = document.getElementById('due_date').value;
    const customerId = '<?= $sale['customer_id'] ?>';

    // 1. Walk-in customer MUST pay exactly full (No debt, no credit/advance)
    if (!customerId && Math.abs(paidAmount - totalAmount) > 0.01) {
        const msg = paidAmount < totalAmount 
            ? "Walk-in customer cannot have outstanding debt." 
            : "Walk-in customer cannot pay more than the total amount.";
        showAlert(msg + " Please assign a customer to this sale for debt/credit transactions.", "Customer Required");
        return false;
    }

    // 2. Expected Payment Date is mandatory ONLY for actual debt (Underpayment)
    if (paidAmount < totalAmount && !dueDate) {
        showAlert("Payment due date is mandatory for partial or debt transactions.", "Date Required");
        return false;
    }

    // Update cart_data before submission
    document.getElementById('cart_data').value = JSON.stringify(cart);
    
    return true;
};
</script>

<?php include '../includes/footer.php'; ?>
