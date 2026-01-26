<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
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
        $sale_id = insertCSV('sales', [
            'customer_id' => $customer_id,
            'total_amount' => $_POST['total_amount'],
            'paid_amount' => $_POST['paid_amount'],
            'payment_method' => $_POST['payment_method'],
            'remarks' => cleanInput($_POST['remarks'] ?? ''),
            'sale_date' => date('Y-m-d H:i:s')
        ]);

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

<div class="h-[calc(100vh-80px)] mb-0 flex flex-col lg:flex-row gap-4">
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

    <!-- RIGHT: Checkout Panel (Enlarged for better UX) -->
    <div class="w-full lg:w-[480px] bg-white rounded-lg border border-gray-200 shadow-lg flex flex-col h-full overflow-hidden">
        <!-- Header -->
        <div class="px-4 py-4 border-b border-gray-100 flex justify-between items-center bg-teal-50/50">
            <h2 class="text-base font-black text-teal-800 uppercase tracking-wider flex items-center">
                <i class="fas fa-shopping-cart mr-3"></i> Current Sale
            </h2>
            <button onclick="clearCart()" class="text-xs text-red-500 hover:text-red-700 font-bold uppercase hover:underline">Clear All</button>
        </div>

        <!-- Cart Table -->
        <div class="flex-1 overflow-y-auto" id="cartContainer">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 sticky top-0 z-10 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-3 py-2 border-b border-gray-200">Item</th>
                        <th class="px-2 py-2 border-b border-gray-200 w-16 text-center">Qty</th>
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

                <div class="grid grid-cols-2 gap-2">
                    <!-- Customer -->
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Customer</label>
                        <div class="relative">
                            <select name="customer_id" id="customerSelect" onchange="handleCustomerChange(this.value)" 
                                    class="w-full pl-2 pr-6 py-2 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-teal-500 focus:border-teal-500 outline-none appearance-none">
                                <option value="">Walk-in</option>
                                <option value="NEW" class="font-bold text-teal-600">+ New</option>
                                <?php foreach($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= substr($c['name'], 0, 15) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-[8px] pointer-events-none"></i>
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
    
    tbody.innerHTML = cart.map((item, index) => `
        <tr class="group hover:bg-gray-50 transition-colors">
            <td class="px-3 py-2 border-b border-gray-100">
                <div class="font-bold text-gray-800 leading-tight">${item.name}</div>
                <div class="text-[9px] text-gray-400 mt-0.5">Rs. ${item.price} / ${item.unit}</div>
            </td>
            <td class="px-2 py-2 border-b border-gray-100 text-center">
                <input type="number" id="qty-${index}" value="${item.qty}" min="1" max="${item.max_stock}" 
                       class="w-12 p-1 text-center font-bold border border-gray-200 rounded text-xs focus:border-teal-500 outline-none ${item.qty >= item.max_stock ? 'text-red-600' : 'text-gray-700'}" 
                       oninput="updateQty(${index}, this.value)">
            </td>
            <td class="px-3 py-2 border-b border-gray-100 text-right font-mono font-bold text-gray-700" id="total-${index}">
                ${Math.round(item.total).toLocaleString()}
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

function updateQty(index, newQty) {
    let qty = parseFloat(newQty);
    if (isNaN(qty) || qty < 0) qty = 0;
    if (qty > cart[index].max_stock) qty = cart[index].max_stock;
    
    cart[index].qty = qty;
    cart[index].total = cart[index].qty * cart[index].price;
    
    // Update UI without full re-render
    const qtyInput = document.getElementById(`qty-${index}`);
    const totalCell = document.getElementById(`total-${index}`);
    
    if (qtyInput && qtyInput.value != qty) qtyInput.value = qty;
    if (totalCell) totalCell.innerText = Math.round(cart[index].total).toLocaleString();
    
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
    let total = cart.reduce((sum, item) => sum + item.total, 0);
    total = Math.round(total);
    const grandTotalInput = document.getElementById('grandTotal');
    
    if (!grandTotalInput.dataset.manualEdit || grandTotalInput.value == '') {
        grandTotalInput.value = total;
        delete grandTotalInput.dataset.manualEdit;
    }
    
    const currentTotal = parseInt(grandTotalInput.value) || total;
    document.getElementById('inputTotal').value = currentTotal;
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
    if (method !== 'Cash' && debt > 0) display.classList.remove('hidden'); 
    else display.classList.add('hidden');
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
    if (method === 'Fully Debt') { paidInput.value = 0; paidInput.readOnly = true; }
    else if (method === 'Cash') { paidInput.value = total; paidInput.readOnly = false; }
    else { paidInput.readOnly = false; }
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
    if (method !== 'Cash' && !cust) { showAlert("Customer required for debt.", "Error"); return false; }
    return true;
};
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
