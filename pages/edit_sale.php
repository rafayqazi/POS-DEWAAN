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
$units = readCSV('units');

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
                
                <div class="mb-4">
                    <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5 ml-1">Sale Date</label>
                    <input type="date" name="sale_date" value="<?= date('Y-m-d', strtotime($sale['sale_date'])) ?>" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-teal-500 outline-none transition-all shadow-sm">
                </div>

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
const products = <?= json_encode($products) ?>;
const customers = <?= json_encode($customers) ?>;
const availableUnits = <?= json_encode($units) ?>;

function getUnitHierarchyJS(unitName) {
    if (!unitName) return [];
    let startNode = availableUnits.find(u => u.name.toLowerCase() === unitName.toLowerCase());
    if (!startNode) return [];
    let root = startNode;
    while(root.parent_id != 0) {
        let parent = availableUnits.find(u => u.id == root.parent_id);
        if(!parent) break;
        root = parent;
    }
    let chain = [];
    let current = root;
    while(current) {
        chain.push(current);
        let next = availableUnits.find(u => parseInt(u.parent_id) === parseInt(current.id));
        if(!next) break;
        current = next;
    }
    return chain;
}

function getBaseMultiplierForProductJS(unitName, p) {
    const chain = getUnitHierarchyJS(p.primaryUnit || p.unit);
    let targetIdx = chain.findIndex(u => u.name.toLowerCase() === unitName.toLowerCase());
    if (targetIdx === -1) return 1;
    const f2 = parseFloat(p.f2 || p.factor_level2 || 1) || 1;
    const f3 = parseFloat(p.f3 || p.factor_level3 || 1) || 1;
    if (targetIdx === 0) {
        if (chain.length > 2) return f2 * f3;
        if (chain.length > 1) return f2;
    } else if (targetIdx === 1) {
        if (chain.length > 2) return f3;
    }
    return 1;
}

const currentItemsRaw = <?= json_encode(array_values($current_items)) ?>;
// Smart Unit Detection for Legacy Sales
function detectBestUnit(item, p) {
    if (item.unit && item.unit !== 'Units') return item.unit; // Start with saved unit if exists
    if (!p) return 'Units';

    const savedPrice = parseFloat(item.price_per_unit || item.price || 0);
    const primaryPrice = parseFloat(p.sell_price);
    
    // Get Multipliers
    const chain = getUnitHierarchyJS(p.unit);
    // If chain is simple, return primary
    if (chain.length <= 1) return p.unit;

    // Check Primary
    if (Math.abs(savedPrice - primaryPrice) < (primaryPrice * 0.2)) return p.unit;

    // Check Secondary (e.g. Box)
    if (chain.length > 1) {
        const u2 = chain[1];
        const f2 = parseFloat(p.factor_level2 || 1);
        const price2 = primaryPrice / f2;
        if (Math.abs(savedPrice - price2) < (price2 * 0.2)) return u2.name;
    }

    // Check Tertiary (e.g. Piece)
    if (chain.length > 2) {
        const u3 = chain[2];
        const f2 = parseFloat(p.factor_level2 || 1);
        const f3 = parseFloat(p.factor_level3 || 1);
        const price3 = primaryPrice / (f2 * f3);
        if (Math.abs(savedPrice - price3) < (price3 * 0.2)) return u3.name;
    }

    // Fallback: If price is significantly lower than primary, assume base unit (smallest)
    const baseMult = getBaseMultiplierForProductJS(p.unit, { ...p, unit: p.unit });
    if (savedPrice < (primaryPrice / baseMult) * 1.5) {
         return chain[chain.length-1].name;
    }

    return p.unit;
}

let cart = currentItemsRaw.map(item => {
    const p = products.find(x => x.id == item.product_id);
    // Attempt to detect the correct unit if not explicitly saved (or if saved is generic)
    const detectedUnit = detectBestUnit(item, p);
    
    // If we detected a different unit, we might need to adjust the display logic, 
    // but the stored QTY is likely for that unit (e.g. 12 Pieces).
    // So we just set the unit.
    
    const unitName = detectedUnit;
    const primaryUnit = p ? p.unit : unitName;
    
    return {
        id: item.product_id,
        name: p ? p.name : (item.p_name || 'Unknown Product'),
        qty: parseFloat(item.quantity),
        price: parseFloat(item.price_per_unit),
        total: parseFloat(item.total_price),
        unit: unitName,
        primaryUnit: primaryUnit,
        buy_price: parseFloat(item.buy_price || 0),
        f2: p ? p.factor_level2 : 1,
        f3: p ? p.factor_level3 : 1,
        max_stock_base: (p ? parseFloat(p.stock_quantity) : 0) + (parseFloat(item.quantity) * getBaseMultiplierForProductJS(unitName, p || { unit: unitName, f2: 1, f3: 1 }))
    };
});
let isBelowCostConfirmed = false;

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
            <div onclick="addToCart('${p.id}', '${p.name.replace(/'/g, "\\'")}', ${price}, '${p.unit}', ${buyPrice}, ${stock}, '${p.factor_level2}', '${p.factor_level3}')" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl transition-all hover:-translate-y-1 cursor-pointer group glass text-left">
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

function addToCart(id, name, price, unit, buyPrice = 0, stock = 9999, f2 = 1, f3 = 1) {
    const existingIndex = cart.findIndex(i => i.id == id);
    const primaryUnit = unit; // Store the product's primary unit
    const mult = getBaseMultiplierForProductJS(unit, { primaryUnit, f2, f3 });

    if (existingIndex !== -1) {
        const existing = cart[existingIndex];
        const currentMult = getBaseMultiplierForProductJS(existing.unit, existing);
        if ((existing.qty + 1) * currentMult > existing.max_stock_base) {
            showAlert(`Only ${Math.floor(existing.max_stock_base / currentMult)} ${existing.unit} available.`, 'Inventory Alert');
            return;
        }
        existing.qty++;
        existing.total = existing.qty * existing.price;
        
        // Move to end so it appears at top of reversed list
        cart.splice(existingIndex, 1);
        cart.push(existing);
    } else {
        if (stock < 0.01) { showAlert("Out of stock!", 'Empty Shelf'); return; }
        cart.push({ 
            id, name, price, unit, qty: 1, total: price, buy_price: buyPrice, 
            primaryUnit: primaryUnit, f2: f2, f3: f3,
            max_stock_base: parseFloat(stock) + (1 * mult) // Add the current item's quantity back to stock for calculation
        });
    }
    renderCart();
}

function updateItemUnit(id, newUnit) {
    const item = cart.find(i => i.id == id);
    if (item && item.unit !== newUnit) {
        const p = products.find(x => x.id == item.id);
        if (!p) return;

        const oldMult = getBaseMultiplierForProductJS(item.unit, item);
        const newMult = getBaseMultiplierForProductJS(newUnit, item);
        
        // 1. Convert Quantity (Magnitude Conservation)
        // Base Qty = Current Qty * Old Multiplier
        // New Qty = Base Qty / New Multiplier
        const baseQty = item.qty * oldMult;
        let newQty = baseQty / newMult;
        
        // Round nicely to avoid 0.9999999
        newQty = parseFloat(newQty.toFixed(4)); 
        
        // 2. Adjust Price per Unit
        // New Price = (Old Price / Old Mult) * New Mult
        // Or better: Base Price * New Mult
        // We defer to the Product's defined sell price for accuracy if available, otherwise scale existing
        
        let newPrice = 0;
        // Calculate theoretical base price from current setting
        const currentBasePrice = item.price / oldMult;
        newPrice = currentBasePrice * newMult;
        
        // Round price
        newPrice = Math.round(newPrice);

        // Update Item
        item.unit = newUnit;
        item.qty = newQty;
        item.price = newPrice;
        item.total = item.qty * item.price; // Should remain roughly same
        
        // Validate Stock
        // Note: max_stock_base checks are done against base units, so changing units shouldn't trigger limit
        // unless rounding errors pushed it up.
        if (item.qty * newMult > item.max_stock_base + 0.001) {
             showAlert(`Stock adjusted to maximum available in ${newUnit}.`, 'Inventory Notice');
             item.qty = Math.floor(item.max_stock_base / newMult);
             item.total = item.qty * item.price;
        }

        renderCart();
    }
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id == id);
    if (item) {
        let newQty = item.qty + delta;
        if (newQty < 1) newQty = 1;

        const mult = getBaseMultiplierForProductJS(item.unit, item);
        if (newQty * mult > item.max_stock_base) {
            showAlert('Stock limit reached', 'Inventory Alert');
            return;
        }

        item.qty = newQty;
        item.total = item.qty * item.price;
        renderCart();
    }
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
    
    // Normalize purchase price based on selected unit level
    const primaryMult = getBaseMultiplierForProductJS(item.primaryUnit, item);
    const selectedMult = getBaseMultiplierForProductJS(item.unit, item);
    
    // scaledBuyPrice = (Primary Buy Price / Primary Multiplier) * Selected Multiplier
    const scaledBuyPrice = (parseFloat(item.buy_price) / primaryMult) * selectedMult;
    
    if (item.price < scaledBuyPrice - 0.01) { // 0.01 epsilon for float
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
        const primaryMult = getBaseMultiplierForProductJS(item.primaryUnit, item);
        const selectedMult = getBaseMultiplierForProductJS(item.unit, item);
        const scaledBuyPrice = (parseFloat(item.buy_price) / primaryMult) * selectedMult;
        
        minCostTotal += scaledBuyPrice * (parseFloat(item.qty) || 0); 
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

function syncUnitInputs(id, inputUnit, val) {
    const item = cart.find(i => i.id == id);
    if (!item) return;
    
    let newQty = parseFloat(val);
    if (isNaN(newQty)) newQty = 0;
    
    // 1. Update the Item to match this new input
    // We treat the inputUnit as the new "active" unit for the item
    const p = products.find(x => x.id == item.id);
    
    // Calculate new price for this unit
    const newMult = getBaseMultiplierForProductJS(inputUnit, item);
    // Base Price calculation: primary sell price / primary mult * new mult
    let newPrice = 0;
    if (p) {
        const primaryMult = getBaseMultiplierForProductJS(p.unit, p);
        const basePrice = parseFloat(p.sell_price) / primaryMult;
        newPrice = basePrice * newMult;
    } else {
        // Fallback if product missing
        newPrice = item.price; 
    }
    
    item.unit = inputUnit;
    item.qty = newQty;
    item.price = Math.round(newPrice);
    item.total = item.qty * item.price;
    
    // 2. Refresh the Price and Total Fields immediately
    const priceInput = document.getElementById(`price-${item.id}`);
    const totalInput = document.getElementById(`total-${item.id}`);
    if (priceInput) priceInput.value = item.price;
    if (totalInput) totalInput.value = Math.round(item.total);
    
    // 3. Update ALL neighbor inputs in this group (Connected Fields)
    // We don't want to re-render the whole table because user is typing.
    // So we calculate manually and update values.
    const chain = getUnitHierarchyJS(item.primaryUnit);
    chain.forEach(u => {
        if (u.name === inputUnit) return; // Skip self
        
        // Convert: NewQty (in inputUnit) -> TargetQty (in u.name)
        const targetMult = getBaseMultiplierForProductJS(u.name, item);
        
        // Base Qty = newQty * newMult
        // Target = Base / targetMult
        let targetVal = (newQty * newMult) / targetMult;
        targetVal = parseFloat(targetVal.toFixed(4));
        
        // Find the input element. We need a way to select it.
        // The render loop creates inputs. We can't easily select by ID unless we gave them IDs.
        // Let's rely on renderCart for now? NO, renderCart loses focus.
        // BETTER: Re-render is risky for focus. simple way: select via traversing?
        // Let's assume we can do a full re-render IF we manage focus, but simpler is direct update.
        // We added unique IDs? No.
        // Let's add renderCart() but passing the focused element? Complex.
        // Alternative: Let's assume the user finishes typing? No, "connected".
        
        // Let's try to update all inputs in the table row
        // We can traverse DOM: row -> cells -> inputs
        // This is getting complex to do purely in JS without IDs.
        // Let's just create IDs for the inputs: `qty-${item.id}-${u.name}`
    });
    
    // Retrying with IDs approach in the render function in next step if needed. 
    // Actually, simply calling renderCart() is the standard way, BUT it resets cursor position.
    // Given the constraints, I will implement a "Smart Update" that finds the other inputs by ID.
    
    // Recalculate Totals
    calculateTotals();
    
    // Trigger updates for other fields
    updateNeighborInputs(item);
    
    // Update Total Base Summary Display
    const summaryDiv = document.getElementById(`total-base-summary-${item.id}`);
    if (summaryDiv) {
        const chain = getUnitHierarchyJS(item.primaryUnit);
        if (chain.length > 1) {
            const mult = getBaseMultiplierForProductJS(item.unit, item);
            const totalBase = item.qty * mult;
            const baseUnit = chain[chain.length-1].name;
            summaryDiv.innerText = `Total: ${totalBase % 1 === 0 ? totalBase : totalBase.toFixed(2)} ${baseUnit}`;
            summaryDiv.classList.remove('hidden');
        } else {
            summaryDiv.classList.add('hidden');
        }
    }
}

function updateNeighborInputs(item) {
    const chain = getUnitHierarchyJS(item.primaryUnit);
    chain.forEach(u => {
        if (u.name === item.unit) return; // Don't update the ONE user is typing in/just typed in?
        // Wait, 'item.unit' is now the one user typed in.
        
        const input = document.getElementById(`qty-${item.id}-${u.name}`);
        if(input) {
             const currentMult = getBaseMultiplierForProductJS(item.unit, item);
             const targetMult = getBaseMultiplierForProductJS(u.name, item);
             let val = (item.qty * currentMult) / targetMult;
             val = parseFloat(val.toFixed(4));
             input.value = val;
             
             // Update Styling
             input.classList.remove('border-teal-400', 'bg-teal-50', 'opacity-100');
             input.classList.add('border-gray-200', 'opacity-60');
             input.parentNode.classList.remove('opacity-100');
             input.parentNode.classList.add('opacity-60');
        }
    });
    
    // Highlight the active one
    const activeInput = document.getElementById(`qty-${item.id}-${item.unit}`);
    if(activeInput) {
        activeInput.classList.add('border-teal-400', 'bg-teal-50', 'opacity-100');
        activeInput.classList.remove('border-gray-200', 'opacity-60');
        activeInput.parentNode.classList.add('opacity-100');
        activeInput.parentNode.classList.remove('opacity-60');
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
                <td class="py-3">
                    <div class="flex flex-col gap-1 items-start justify-center min-w-[120px]">
                        ${(() => {
                            const chain = getUnitHierarchyJS(item.primaryUnit);
                            // If no hierarchy (or item not found), fallback to single input (but we still use the structure)
                            const unitsToShow = chain.length > 0 ? chain : [{name: item.unit || 'Units'}];
                            
                            return unitsToShow.map(u => {
                                // Calculate value for this unit based on current item state
                                // We need to convert from item.unit -> u.name
                                const currentMult = getBaseMultiplierForProductJS(item.unit, products.find(p => p.id == item.id) || {unit: item.unit});
                                const targetMult = getBaseMultiplierForProductJS(u.name, products.find(p => p.id == item.id) || {unit: item.unit});
                                
                                // Base Qty = item.qty * currentMult
                                // Target Qty = Base Qty / targetMult
                                let val = (item.qty * currentMult) / targetMult;
                                
                                // Format nicer
                                val = parseFloat(val.toFixed(4));
                                
                                const isSelected = u.name === item.unit;
                                
                                return `
                                <div class="flex items-center gap-2 w-full group/input ${isSelected ? 'opacity-100' : 'opacity-60 hover:opacity-100'} transition-opacity">
                                    <input type="number" step="0.0001" 
                                           id="qty-${item.id}-${u.name}"
                                           value="${val}" 
                                           onfocus="this.select()"
                                           oninput="syncUnitInputs('${item.id}', '${u.name}', this.value)"
                                           class="w-16 bg-white border ${isSelected ? 'border-teal-400 bg-teal-50' : 'border-gray-200'} rounded p-1 text-xs font-bold text-center outline-none focus:border-teal-500 transition-colors">
                                    <span class="text-[9px] font-black uppercase tracking-wider text-gray-500 w-8">${u.name}</span>
                                </div>
                                `;
                        }).join('');
                        })()}
                        ${(() => {
                            const chain = getUnitHierarchyJS(item.primaryUnit);
                            const hasHierarchy = chain.length > 1;
                            const mult = getBaseMultiplierForProductJS(item.unit, item);
                            const totalBase = item.qty * mult;
                            const baseUnit = chain.length > 0 ? chain[chain.length-1].name : '';
                            return `<div id="total-base-summary-${item.id}" class="mt-2 py-1 px-2 bg-teal-50 border border-teal-100 rounded text-[9px] font-black text-teal-600 uppercase tracking-tighter ${hasHierarchy ? '' : 'hidden'}" title="Total base items being sold">Total: ${totalBase % 1 === 0 ? totalBase : totalBase.toFixed(2)} ${baseUnit}</div>`;
                        })()}
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

    // Check individual items & Total price for "Soft Validation"
    let invalidItems = [];
    cart.forEach(item => {
        if (parseFloat(item.price) < (parseFloat(item.buy_price) || 0)) {
            invalidItems.push(item.name);
        }
    });

    const isBelowTotalCost = !validateTotalPrice();

    if ((invalidItems.length > 0 || isBelowTotalCost) && !isBelowCostConfirmed) {
        let msg = "";
        if (invalidItems.length > 0) {
            msg = `The following items are priced below their purchase price:\n- ${invalidItems.join('\n- ')}\n\n`;
        }
        if (isBelowTotalCost) {
            msg += "The total sale amount is also below the total cost price.\n\n";
        }
        msg += "Are you sure you want to proceed with these prices?";

        showConfirm(msg, () => {
            isBelowCostConfirmed = true;
            document.querySelector('#updateSaleForm button[type="submit"]').click();
        }, "Soft Validation Warning");
        return false;
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
