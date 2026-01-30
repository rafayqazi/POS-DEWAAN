<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasPermission('add_sale')) die("Unauthorized Access");
$pageTitle = "Point of Sale";
include '../includes/header.php';

// Handle Checkout Logic
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : '';
    if ($customer_id == 'NEW') {
        $customer_id = insertCSV('customers', [
            'name' => cleanInput($_POST['new_customer_name']),
            'phone' => cleanInput($_POST['new_customer_phone']),
            'address' => cleanInput($_POST['new_customer_address']),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    $items = json_decode($_POST['cart_data'], true);
    $error_items = [];
    $final_products = [];
    
    $transaction_success = processCSVTransaction('products', function($all_products) use ($items, &$error_items, &$final_products) {
        $product_map = [];
        foreach($all_products as $i => $p) $product_map[$p['id']] = $i;
        
        foreach ($items as $item) {
            $pid = $item['id'];
            if (!isset($product_map[$pid])) continue; 
            
            $idx = $product_map[$pid];
            $current_stock = (float)$all_products[$idx]['stock_quantity'];
            $qty_needed = (float)$item['qty'];
            
            if ($current_stock < $qty_needed) {
                $name = $all_products[$idx]['name'] ?? 'Unknown Item';
                $error_items[] = "$name (Available: $current_stock)";
            } else {
                $all_products[$idx]['stock_quantity'] = $current_stock - $qty_needed;
            }
        }
        
        if (!empty($error_items)) return false;
        $final_products = $all_products;
        return $all_products;
    });

    if (!$transaction_success) {
        $message = "ERROR: " . (!empty($error_items) ? "Stock limit exceeded for: " . implode(', ', $error_items) : "Database transaction failed.");
    } else if ($items) {
        $sale_date = !empty($_POST['sale_date']) ? $_POST['sale_date'] . ' ' . date('H:i:s') : date('Y-m-d H:i:s');
        $sale_id = insertCSV('sales', [
            'customer_id' => $customer_id,
            'total_amount' => $_POST['total_amount'],
            'paid_amount' => $_POST['paid_amount'],
            'discount' => $_POST['discount'] ?? 0,
            'payment_method' => $_POST['payment_method'],
            'remarks' => cleanInput($_POST['remarks'] ?? ''),
            'due_date' => $_POST['due_date'] ?? '',
            'sale_date' => $sale_date
        ]);

        if (!empty($customer_id)) {
            insertCSV('customer_transactions', [
                'customer_id' => $customer_id,
                'type' => 'Sale',
                'debit' => $_POST['total_amount'],
                'credit' => $_POST['paid_amount'],
                'description' => "Sale #$sale_id",
                'date' => !empty($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s'),
                'sale_id' => $sale_id,
                'due_date' => $_POST['due_date'] ?? ''
            ]);
        }

        foreach ($items as $item) {
            $cost_price = 0; $avco_price = 0; 
            foreach ($final_products as $p) {
                if ($p['id'] == $item['id']) {
                    $cost_price = $p['buy_price'];
                    $avco_price = isset($p['avg_buy_price']) ? $p['avg_buy_price'] : $p['buy_price'];
                    break;
                }
            }
            insertCSV('sale_items', [
                'sale_id' => $sale_id,
                'product_id' => $item['id'],
                'quantity' => $item['qty'],
                'price_per_unit' => $item['price'],
                'buy_price' => $cost_price,
                'avg_buy_price' => $avco_price,
                'total_price' => $item['total']
            ]);
        }
        $message = "Sale #$sale_id recorded successfully!";
    }
}

$customers = readCSV('customers');
$products = readCSV('products');
$categories = readCSV('categories');
?>

<div class="h-[calc(100vh-60px)] mb-0 flex flex-col lg:flex-row gap-4">
    <!-- LEFT: Product Explorer -->
    <div class="flex-1 flex flex-col bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <!-- Search Header -->
        <div class="p-4 border-b border-gray-100 flex gap-3">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="productSearch" autofocus placeholder="Search products..." 
                       class="w-full pl-10 pr-4 py-2 rounded-md border border-gray-200 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none text-sm transition-all">
            </div>
            <select id="categoryFilter" class="px-4 py-2 rounded-md border border-gray-200 text-sm text-gray-600 focus:border-teal-500 outline-none">
                <option value="all">All Categories</option>
                <?php foreach($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Product List -->
        <div class="flex-1 overflow-y-auto p-2 bg-gray-50/50" id="productList">
            <div class="flex flex-col gap-1 pr-2">
            <?php foreach ($products as $p): 
                if ($p['stock_quantity'] <= 0) continue; 
            ?>
                <div class="product-card bg-white border border-gray-100 p-2.5 rounded-lg hover:border-teal-400 hover:bg-teal-50/50 cursor-pointer transition-all flex items-center gap-4 group"
                     onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', <?= $p['sell_price'] ?>, '<?= $p['unit'] ?>', <?= $p['stock_quantity'] ?>, <?= $p['buy_price'] ?>)"
                     data-name="<?= strtolower($p['name']) ?>"
                     data-category="<?= $p['category'] ?>">
                    
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-gray-800 text-sm truncate group-hover:text-teal-700" title="<?= $p['name'] ?>"><?= $p['name'] ?></h4>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 font-semibold uppercase tracking-wider"><?= $p['category'] ?></span>
                            <span class="text-[10px] text-gray-400 font-medium italic">Unit: <?= $p['unit'] ?></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-6 text-right shrink-0">
                        <div class="min-w-[80px]">
                            <span class="block text-[9px] text-gray-400 font-bold uppercase tracking-tighter">Availability</span>
                            <span id="stock-label-<?= $p['id'] ?>" data-id="<?= $p['id'] ?>" data-stock="<?= $p['stock_quantity'] ?>"
                                  class="text-xs font-black <?= $p['stock_quantity'] < 5 ? 'text-red-500' : 'text-teal-600' ?>">
                                <?= $p['stock_quantity'] ?> <?= $p['unit'] ?>
                            </span>
                        </div>
                        <div class="min-w-[100px] bg-gray-50 px-3 py-1 rounded border border-gray-100 group-hover:bg-teal-100 group-hover:border-teal-200 transition-colors">
                            <span class="block text-[9px] text-gray-400 font-bold uppercase tracking-tighter">Sell Price</span>
                            <span class="block font-black text-gray-800 text-sm">Rs. <?= number_format((float)$p['sell_price']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Checkout Panel (Widened and UX Enhanced) -->
    <div class="w-full lg:w-[540px] bg-white rounded-lg border border-gray-200 shadow-xl flex flex-col h-full relative">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-teal-50/30">
            <h2 class="text-sm font-black text-teal-800 uppercase tracking-widest flex items-center">
                <i class="fas fa-shopping-cart mr-2 text-teal-600"></i> Current Sale
            </h2>
            <button onclick="clearCart()" class="text-[10px] text-red-400 hover:text-red-600 font-bold uppercase hover:underline transition-colors">Clear All</button>
        </div>

        <!-- Cart Table -->
        <div class="flex-1 overflow-y-auto" id="cartContainer">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 sticky top-0 z-10 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-3 py-2 border-b border-gray-200">Item</th>
                        <th class="px-2 py-2 border-b border-gray-200 w-16 text-center">Qty</th>
                        <th class="px-2 py-2 border-b border-gray-200 w-20 text-center">Price</th>
                        <th class="px-3 py-2 border-b border-gray-200 text-right">Total</th>
                        <th class="px-2 py-2 border-b border-gray-200 w-8"></th>
                    </tr>
                </thead>
                <tbody id="cartItems" class="text-xs divide-y divide-gray-100">
                    <!-- JS Injected -->
                </tbody>
            </table>
            <div id="emptyCartMsg" class="h-40 flex flex-col items-center justify-center text-gray-400 opacity-60">
                <i class="fas fa-shopping-basket text-2xl mb-2"></i>
                <p class="text-xs font-medium">No items added</p>
            </div>
        </div>

        <!-- Checkout Footer (Compact) -->
        <div class="border-t border-gray-200 bg-gray-50/30 p-3 space-y-2.5 shadow-[0_-5px_15px_rgba(0,0,0,0.02)] z-20">
            
            <!-- Discount -->
            <div class="flex justify-between items-center bg-white border border-gray-200 rounded-xl px-3 py-2 shadow-sm">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Discount (Rs.)</span>
                <input type="number" id="discountInput" value="0"
                       class="w-28 bg-transparent border-none p-0 text-lg font-black text-red-600 text-right focus:ring-0 outline-none" 
                       oninput="updateTotals()" min="0">
            </div>

            <!-- Grand Total -->
            <div class="flex justify-between items-center bg-gradient-to-br from-teal-50 to-white border border-teal-100 rounded-xl px-3 py-2 shadow-inner">
                <span class="text-[10px] font-black text-teal-800 uppercase tracking-widest">Grand Total</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-xs font-bold text-teal-700">Rs.</span>
                    <input type="number" id="grandTotal" value="0"
                           class="w-28 bg-transparent border-none p-0 text-xl font-black text-teal-900 text-right focus:ring-0 outline-none" 
                           oninput="handleTotalChange()" min="0">
                </div>
            </div>
            
            <div id="priceWarning" class="hidden text-center text-xs text-red-600 font-bold bg-red-50 p-1 rounded border border-red-100">
               <i class="fas fa-exclamation-circle mr-1"></i> Below Cost Price!
            </div>

            <!-- Context Inputs -->
            <form method="POST" id="checkoutForm" class="space-y-2">
                <input type="hidden" name="checkout" value="1">
                <input type="hidden" name="cart_data" id="cartData">
                <input type="hidden" name="total_amount" id="inputTotal">
                <input type="hidden" name="discount" id="inputDiscount">

                <div class="grid grid-cols-2 gap-2">
                    <!-- Sale Date -->
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Sale Date</label>
                        <input type="date" name="sale_date" id="sale_date" value="<?= date('Y-m-d') ?>" 
                               class="w-full px-2 py-2 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none h-[34px] shadow-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <!-- Customer -->
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Customer</label>
                        <div class="relative" id="customerDropdownContainer">
                            <button type="button" onclick="toggleCustomerDropdown()" id="customerDropdownBtn" class="w-full px-2 py-2 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none bg-white text-left flex justify-between items-center h-[34px] shadow-sm hover:border-teal-400 transition-all">
                                <span id="selectedCustomerLabel" class="truncate max-w-[80%]">Walk-in</span>
                                <i class="fas fa-chevron-down text-gray-400 text-[8px]"></i>
                            </button>
                            
                            <!-- Dropdown Panel -->
                            <div id="customerDropdownPanel" class="hidden absolute z-[100] w-64 mt-1 bg-white border border-gray-200 rounded shadow-2xl overflow-hidden transform origin-top transition-all scale-95 opacity-0">
                                <div class="p-2 border-b border-gray-100 bg-gray-50">
                                    <div class="relative">
                                        <i class="fas fa-search absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                                        <input type="text" id="customerSearchInput" autocomplete="off" oninput="filterCustomers(this.value)" placeholder="Search name..." class="w-full pl-7 pr-2 py-1.5 text-xs border border-gray-200 rounded focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none transition-all">
                                    </div>
                                </div>
                                <div class="max-h-60 overflow-y-auto" id="customerList">
                                    <div onclick="selectCustomer('', 'Walk-in')" class="customer-nav-item p-2.5 text-xs hover:bg-teal-50 cursor-pointer font-bold border-b border-gray-50 flex items-center gap-2 transition-colors">
                                        <i class="fas fa-user-alt text-[10px] text-gray-400"></i> Walk-in
                                    </div>
                                    <div onclick="selectCustomer('NEW', '+ New Customer')" class="customer-nav-item p-2.5 text-xs hover:bg-teal-50 cursor-pointer font-bold text-teal-600 border-b border-gray-50 flex items-center gap-2 transition-colors">
                                        <i class="fas fa-user-plus text-[10px]"></i> + New Customer
                                    </div>
                                    <div class="px-2 py-1.5 bg-gray-50 text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">Registered Customers</div>
                                    <?php foreach($customers as $c): ?>
                                        <div onclick="selectCustomer('<?= $c['id'] ?>', '<?= htmlspecialchars($c['name']) ?>')" 
                                             class="customer-item customer-nav-item p-2.5 text-xs hover:bg-teal-50 cursor-pointer border-b border-gray-50 transition-colors flex flex-col" 
                                             data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>">
                                            <span class="font-bold text-gray-700"><?= htmlspecialchars($c['name']) ?></span>
                                            <span class="text-[9px] text-gray-400 font-medium"><?= htmlspecialchars($c['phone'] ?? 'No Phone') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div id="noCustomerFound" class="hidden p-4 text-center text-gray-400 text-xs italic">No matches found...</div>
                            </div>
                            
                            <!-- Hidden input for form persistence -->
                            <input type="hidden" name="customer_id" id="customerSelect" value="">
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Method</label>
                        <div class="relative">
                            <select name="payment_method" id="paymentMethod" onchange="handlePaymentChange(this.value)" 
                                    class="w-full pl-2 pr-6 py-2 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none appearance-none">
                                <option value="Cash">Cash</option>
                                <option value="Partial">Partial</option>
                                <option value="Fully Debt">Debt</option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-[8px] pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <!-- Conditional Fields -->
                <div id="newCustomerFields" class="hidden p-2 bg-gray-100 rounded border border-gray-200 space-y-2">
                    <input type="text" name="new_customer_name" id="new_customer_name" placeholder="Full Name *" class="w-full p-1.5 text-xs border border-gray-300 rounded focus:border-teal-500 outline-none">
                    <input type="text" name="new_customer_phone" placeholder="Phone" class="w-full p-1.5 text-xs border border-gray-300 rounded focus:border-teal-500 outline-none">
                </div>

                <!-- Amount Paid -->
                <div class="flex items-end gap-2">
                     <div class="flex-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Paid Amount</label>
                        <input type="number" name="paid_amount" id="paidAmount" required class="w-full p-2 text-sm font-bold border border-gray-300 rounded focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none" placeholder="0" oninput="calculateDebt()">
                     </div>
                     <div id="debtDisplay" class="hidden px-2 py-1 bg-red-100 text-red-700 border border-red-200 rounded text-[10px] font-bold text-center self-stretch flex flex-col justify-center min-w-[60px]">
                        <span class="block uppercase opacity-50 text-[8px]">Due</span>
                        <span id="debtAmount">0</span>
                     </div>
                </div>

                <!-- Due Date (Mandatory for Debt) -->
                <div id="dueDateContainer" class="hidden">
                    <label class="block text-[10px] font-bold text-orange-600 uppercase mb-1 flex items-center gap-1">
                        <i class="fas fa-calendar-alt"></i> Expected Payment Date *
                    </label>
                    <input type="date" name="due_date" id="dueDate" class="w-full p-2 text-xs border border-orange-200 bg-orange-50 rounded focus:ring-1 focus:ring-orange-500 focus:border-orange-500 outline-none">
                </div>

                <!-- Remarks -->
                <div>
                     <textarea name="remarks" rows="1" class="w-full px-2 py-1.5 text-[11px] border border-gray-300 rounded focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none resize-none" placeholder="Sale Remarks (Optional)..."></textarea>
                </div>

                <!-- Action -->
                <button type="submit" id="submitBtn" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-black py-3 rounded-xl shadow-lg hover:shadow-teal-100 transition-all text-xs uppercase tracking-widest flex items-center justify-center gap-2 group">
                    <i class="fas fa-check-circle group-hover:scale-110 transition-transform"></i>
                    Complete Transaction
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
            showAlert(`Only ${stock} units available.`, 'Inventory Alert');
            return;
        }
        existing.qty++;
        existing.total = existing.qty * existing.price;
    } else {
        if (stock < 0.01) { showAlert("Out of stock!", 'Empty Shelf'); return; }
        cart.push({ id: sId, name, price, unit, qty: 1, total: price, max_stock: stock, buy_price: parseFloat(buyPrice) || 0 });
    }
    updateStockLabels();
    renderCart();
}

function clearCart() {
    if(cart.length > 0) {
        if(confirm("Are you sure you want to clear the cart?")) {
            cart = [];
            updateStockLabels();
            renderCart();
        }
    }
}

function renderCart() {
    const tbody = document.getElementById('cartItems');
    const emptyMsg = document.getElementById('emptyCartMsg');
    
    if (cart.length === 0) {
        tbody.innerHTML = '';
        emptyMsg.classList.remove('hidden');
        updateTotals();
        return;
    }
    
    emptyMsg.classList.add('hidden');
    
    // Display newest items at the top
    tbody.innerHTML = [...cart].map((item, index) => ({item, index})).reverse().map(({item, index}) => `
        <tr class="group hover:bg-gray-50 transition-colors">
            <td class="px-3 py-2 border-b border-gray-100">
                <div class="font-bold text-gray-800 leading-tight">${item.name}</div>
                <div class="text-[9px] text-gray-400 mt-0.5">${item.unit}</div>
            </td>
            <td class="px-2 py-2 border-b border-gray-100 text-center">
                <input type="number" id="qty-${index}" value="${item.qty}" min="0" step="any" max="${item.max_stock}" 
                       class="w-12 p-1 text-center font-bold border border-gray-200 rounded text-xs focus:border-teal-500 outline-none ${item.qty >= item.max_stock ? 'text-red-600' : 'text-gray-700'}" 
                       oninput="updateQty(${index}, this.value)">
            </td>
            <td class="px-2 py-2 border-b border-gray-100 text-center">
                <input type="number" id="price-${index}" value="${item.price}" min="0" step="any"
                       class="w-16 p-1 text-center font-bold border border-gray-200 rounded text-xs focus:border-teal-500 outline-none" 
                       oninput="updateUnitPrice(${index}, this.value)">
            </td>
            <td class="px-3 py-2 border-b border-gray-100 text-right font-mono font-bold text-gray-700">
                <input type="number" id="total-${index}" value="${Math.round(item.total)}" 
                       class="w-20 p-1 text-right font-bold border border-gray-200 rounded text-xs focus:border-teal-500 outline-none bg-gray-50 group-hover:bg-white" 
                       oninput="updateItemTotal(${index}, this.value)">
            </td>
            <td class="px-2 py-2 border-b border-gray-100 text-right">
                <button onclick="removeFromCart(${index})" class="text-gray-300 hover:text-red-500 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>
    `).join('');
    
    updateTotals();
}

function updateUnitPrice(index, newPrice) {
    let price = parseFloat(newPrice);
    if (isNaN(price)) price = 0;
    cart[index].price = price;
    cart[index].total = cart[index].price * cart[index].qty;
    cart[index].manualTotal = false; 
    
    // Update Total field in UI
    const totalInput = document.getElementById(`total-${index}`);
    if (totalInput) totalInput.value = Math.round(cart[index].total);
    
    updateTotals();
}

function updateItemTotal(index, newTotal) {
    let total = parseFloat(newTotal);
    if (isNaN(total)) total = 0;
    cart[index].total = total;
    
    // Interlink: Update price based on total / qty
    if (cart[index].qty > 0) {
        cart[index].price = total / cart[index].qty;
        const priceInput = document.getElementById(`price-${index}`);
        if (priceInput) priceInput.value = cart[index].price.toFixed(2);
    }
    
    cart[index].manualTotal = true;
    updateTotals();
}

function updateQty(index, newQty) {
    let qty = parseFloat(newQty);
    if (isNaN(qty) || qty < 0) qty = 0;
    if (qty > cart[index].max_stock) qty = cart[index].max_stock;
    
    cart[index].qty = qty;
    cart[index].total = cart[index].qty * cart[index].price;
    cart[index].manualTotal = false; // Reset manual flag on qty change
    
    // Update UI without full re-render
    const qtyInput = document.getElementById(`qty-${index}`);
    const totalCell = document.getElementById(`total-${index}`);
    
    if (qtyInput && qtyInput.value != qty) qtyInput.value = qty;
    if (totalCell) totalCell.value = Math.round(cart[index].total);
    
    // Update color
    if (qtyInput) {
        if (qty >= cart[index].max_stock) {
            qtyInput.classList.add('text-red-600');
            qtyInput.classList.remove('text-gray-700');
        } else {
            qtyInput.classList.remove('text-red-600');
            qtyInput.classList.add('text-gray-700');
        }
    }
    
    updateStockLabels();
    updateTotals();
}

function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    let total = Math.max(0, Math.round(subtotal - discount));
    
    const grandTotalInput = document.getElementById('grandTotal');
    
    if (!grandTotalInput.dataset.manualEdit || grandTotalInput.value == '') {
        grandTotalInput.value = total;
        delete grandTotalInput.dataset.manualEdit;
    }
    
    const currentTotal = parseInt(grandTotalInput.value) || total;
    document.getElementById('inputTotal').value = currentTotal;
    document.getElementById('inputDiscount').value = discount;
    document.getElementById('cartData').value = JSON.stringify(cart);
    
    if (document.getElementById('paymentMethod').value === 'Cash') {
        document.getElementById('paidAmount').value = currentTotal;
    }
    
    validateTotalPrice();
    calculateDebt();
}

function calculateDebt() {
    const total = parseInt(document.getElementById('inputTotal').value) || 0;
    const paid = parseInt(document.getElementById('paidAmount').value) || 0;
    const debt = total - paid;
    document.getElementById('debtAmount').innerText = debt > 0 ? 'Rs. ' + debt.toLocaleString() : '0';
    const display = document.getElementById('debtDisplay');
    const method = document.getElementById('paymentMethod').value;
    
    // Show debt badge
    if (debt > 0) display.classList.remove('hidden'); 
    else display.classList.add('hidden');

    // Dynamically show/hide due date container
    const dueDateContainer = document.getElementById('dueDateContainer');
    const dueDateInput = document.getElementById('dueDate');
    
    // Only show due date if there is actual debt (Underpayment)
    if (debt > 0) {
        dueDateContainer.classList.remove('hidden');
        dueDateInput.required = true;
    } else {
        dueDateContainer.classList.add('hidden');
        dueDateInput.required = false;
        dueDateInput.value = '';
    }
}

function validateTotalPrice() {
    const customTotal = parseInt(document.getElementById('grandTotal').value) || 0;
    let minTotal = 0;
    cart.forEach(item => { minTotal += (item.buy_price || 0) * item.qty; });
    const warning = document.getElementById('priceWarning');
    if (customTotal > 0 && customTotal < minTotal) warning.classList.remove('hidden');
    else warning.classList.add('hidden');
    return (customTotal >= minTotal);
}

function updateStockLabels() {
    document.querySelectorAll('.product-card').forEach(card => {
        const label = card.querySelector('[id^="stock-label-"]');
        if (!label) return;
        const max = parseFloat(label.dataset.stock);
        label.innerText = `${max} left`;
        label.className = `text-[10px] font-bold ${max < 5 ? 'text-red-600' : 'text-teal-600'}`;
        card.classList.remove('opacity-50', 'pointer-events-none');
    });

    cart.forEach(item => {
        const label = document.getElementById('stock-label-' + item.id);
        if (label) {
            const max = parseFloat(label.dataset.stock);
            const rem = max - item.qty;
            label.innerText = `${rem.toFixed(1)} ${item.unit}`;
            if (rem <= 0) label.closest('.product-card').classList.add('opacity-50', 'pointer-events-none');
        }
    });
}

function handlePaymentChange(method) {
    const paidInput = document.getElementById('paidAmount');
    const total = parseInt(document.getElementById('inputTotal').value) || 0;
    
    if (method === 'Fully Debt') { 
        paidInput.value = 0; 
        paidInput.readOnly = true; 
    } else if (method === 'Cash') { 
        paidInput.value = total; 
        paidInput.readOnly = false; 
    } else { 
        paidInput.readOnly = false; 
    }
    
    calculateDebt();
}

function handleTotalChange() {
    document.getElementById('grandTotal').dataset.manualEdit = 'true';
    updateTotals();
}

function handleCustomerChange(val) {
    const fields = document.getElementById('newCustomerFields');
    if (val === 'NEW') {
        fields.classList.remove('hidden');
        document.getElementById('new_customer_name').required = true;
    } else {
        fields.classList.add('hidden');
        document.getElementById('new_customer_name').required = false;
    }
    calculateDebt();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateStockLabels();
    renderCart();
}

function filterProducts() {
    const term = document.getElementById('productSearch').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.name;
        const cat = card.dataset.category;
        const match = name.includes(term) && (category === 'all' || cat === category);
        card.style.display = match ? 'flex' : 'none';
    });
}

document.getElementById('productSearch').addEventListener('input', filterProducts);
document.getElementById('categoryFilter').addEventListener('change', filterProducts);

document.getElementById('productSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const firstVisible = Array.from(document.querySelectorAll('.product-card')).find(c => c.style.display !== 'none');
        if (firstVisible) { firstVisible.click(); this.value = ''; filterProducts(); }
        e.preventDefault();
    }
});

document.getElementById('checkoutForm').onsubmit = function(e) {
    if (cart.length === 0) { showAlert("Cart is empty!", "Error"); return false; }
    if (!validateTotalPrice()) { showAlert("Below cost price!", "Error"); return false; }
    
    const method = document.getElementById('paymentMethod').value;
    const cust = document.getElementById('customerSelect').value;
    const dueDate = document.getElementById('dueDate').value;
    const totalAmount = parseInt(document.getElementById('inputTotal').value) || 0;
    const paidAmount = parseInt(document.getElementById('paidAmount').value) || 0;

    // 1. Walk-in customer MUST pay exactly full (No debt, no credit/advance)
    if (!cust && paidAmount !== totalAmount) {
        const msg = paidAmount < totalAmount 
            ? "Walk-in customer cannot have outstanding debt." 
            : "Walk-in customer cannot pay more than the total amount.";
        showAlert(msg + " Please select or add a customer first to proceed.", "Customer Required");
        return false;
    }

    // 2. Expected Payment Date is mandatory ONLY for actual debt (Underpayment)
    if (paidAmount < totalAmount && !dueDate) {
        showAlert("Payment due date is mandatory for partial or debt transactions.", "Date Required");
        return false;
    }

    // 3. Ensure credit transactions (Under/Over payment) have a customer
    if (paidAmount !== totalAmount && !cust) {
        showAlert("Please select or add a customer for transactions involving credit or debt.", "Customer Required");
        return false;
    }

    return true;
};
// --- Customer Searchable Dropdown ---
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
    
    // 1. Hide/Show registered customers based on query
    customerItems.forEach(item => {
        const name = item.dataset.name;
        if (name.includes(q)) {
            item.classList.remove('hidden');
            foundCount++;
        } else {
            item.classList.add('hidden');
        }
    });

    // 2. Hide "Walk-in" and "+ New" if searching
    navItems.forEach(item => {
        if (!item.classList.contains('customer-item')) {
            if (q !== '') item.classList.add('hidden');
            else item.classList.remove('hidden');
        }
    });
    
    // 3. Clear all highlights first
    const activeClass = 'customer-active';
    const highlightClasses = ['bg-teal-100', 'shadow-inner', 'ring-1', 'ring-teal-200', activeClass];
    navItems.forEach(item => item.classList.remove(...highlightClasses));
    
    // 4. Highlight the first visible item
    const visibleItems = Array.from(navItems).filter(el => !el.classList.contains('hidden'));
    if (visibleItems.length > 0 && q !== '') {
        visibleItems[0].classList.add(...highlightClasses);
    }
    
    document.getElementById('noCustomerFound').classList.toggle('hidden', foundCount > 0 || q === '');
}

function selectCustomer(id, name) {
    document.getElementById('customerSelect').value = id;
    document.getElementById('selectedCustomerLabel').innerText = name;
    handleCustomerChange(id);
    closeCustomerDropdown();
    // Clear search for next time
    document.getElementById('customerSearchInput').value = '';
    filterCustomers('');
}

// Close dropdown on click outside
document.addEventListener('click', function(e) {
    const container = document.getElementById('customerDropdownContainer');
    if (container && !container.contains(e.target)) {
        closeCustomerDropdown();
    }
});

// Keyboard Navigation for Customer Search
document.getElementById('customerSearchInput').addEventListener('keydown', function(e) {
    const activeClass = 'customer-active';
    const highlightClasses = ['bg-teal-100', 'shadow-inner', 'ring-1', 'ring-teal-200', activeClass];
    
    // Get all items that are NOT hidden
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
        if (activeItem) {
            activeItem.click();
        }
    }
});
</script>

<?php if ($message): ?>
<script>
    showAlert("<?= $message ?>", "Status");
    <?php if (strpos($message, 'recorded successfully') !== false && isset($sale_id)): ?>
        showConfirm("Sale recorded. Do you want to print the bill?", () => {
            window.open('print_bill.php?id=<?= $sale_id ?>', '_blank');
        }, "Print Receipt?");
    <?php endif; ?>
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
