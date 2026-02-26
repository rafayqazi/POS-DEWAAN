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
            
            // Convert sold qty into base units
            $sold_unit = $item['unit'] ?? $all_products[$idx]['unit'];
            $multiplier = getBaseMultiplier($sold_unit, $all_products[$idx]);
            $qty_needed_base = (float)$item['qty'] * $multiplier;
            
            if ($current_stock < $qty_needed_base) {
                $name = $all_products[$idx]['name'] ?? 'Unknown Item';
                $available_readable = formatStockHierarchy($current_stock, $all_products[$idx]['unit']);
                $error_items[] = "$name (Available: $available_readable)";
            } else {
                $all_products[$idx]['stock_quantity'] = $current_stock - $qty_needed_base;
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
                'unit' => $item['unit'], // Store the unit used in this sale
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
$units = readCSV('units');
?>

<div class="h-[calc(100vh-60px)] mb-0 flex flex-col lg:flex-row gap-4">
    <!-- LEFT: Product Explorer (40%) -->
    <div class="lg:w-[40%] flex flex-col bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <!-- Search Header -->
        <div class="p-4 border-b border-gray-100 flex gap-3">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="productSearch" autofocus placeholder="Search products..." 
                       class="w-full pl-10 pr-4 py-2 rounded-md border border-gray-200 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none text-sm transition-all">
            </div>
            <select id="categoryFilter" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 focus:border-teal-500 outline-none bg-gray-50">
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
                     onclick='handleProductClick(this)'
                     data-product="<?= htmlspecialchars(json_encode($p)) ?>"
                     data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
                     data-category="<?= htmlspecialchars($p['category']) ?>">
                    
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-gray-800 text-base lg:text-lg truncate group-hover:text-teal-700" title="<?= $p['name'] ?>"><?= $p['name'] ?></h4>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 font-semibold uppercase tracking-wider"><?= $p['category'] ?></span>
                            <span class="text-[10px] text-gray-400 font-medium italic">Unit: <?= $p['unit'] ?></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-6 text-right shrink-0">
                        <div class="min-w-[80px]">
                            <span class="block text-[9px] text-gray-400 font-bold uppercase tracking-tighter">Availability</span>
                            <span id="stock-label-<?= $p['id'] ?>" data-id="<?= $p['id'] ?>" data-stock="<?= $p['stock_quantity'] ?>"
                                  data-unit="<?= $p['unit'] ?>" data-f2="<?= (float)($p['factor_level2'] ?? 1) ?>" data-f3="<?= (float)($p['factor_level3'] ?? 1) ?>"
                                  class="text-xs font-black <?= $p['stock_quantity'] < 10 ? 'text-red-500' : 'text-teal-600' ?>">
                                <?= formatStockHierarchy($p['stock_quantity'], $p) ?>
                            </span>
                        </div>
                        <div class="min-w-[120px] bg-gray-50 px-4 py-2 rounded-xl border border-gray-100 group-hover:bg-teal-100 group-hover:border-teal-200 transition-colors">
                            <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-widest">Sell Price</span>
                            <span class="block font-black text-gray-800 text-base">Rs. <?= number_format((float)$p['sell_price']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Checkout Panel (60% UX Enhanced) -->
    <div class="lg:w-[60%] bg-white rounded-lg border border-gray-200 shadow-xl flex flex-col h-full relative">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-teal-50/30">
            <h2 class="text-base font-black text-teal-800 uppercase tracking-widest flex items-center">
                <i class="fas fa-shopping-cart mr-2 text-teal-600"></i> Current Checkout
            </h2>
            <button onclick="clearCart()" class="text-xs text-red-400 hover:text-red-600 font-black uppercase hover:underline transition-colors">Clear All</button>
        </div>

        <!-- Cart Table -->
        <div class="flex-1 overflow-y-auto" id="cartContainer">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 sticky top-0 z-10 text-xs font-black text-gray-500 uppercase tracking-widest">
                    <tr>
                        <th class="px-3 py-2 border-b border-gray-200">Item Details</th>
                        <th class="px-2 py-2 border-b border-gray-200 w-24 text-center">Qty</th>
                        <th class="px-2 py-2 border-b border-gray-200 w-28 text-center">Unit Price</th>
                        <th class="px-3 py-2 border-b border-gray-200 text-right">Subtotal</th>
                        <th class="px-2 py-2 border-b border-gray-200 w-10"></th>
                    </tr>
                </thead>
                <tbody id="cartItems" class="text-sm divide-y divide-gray-100">
                    <!-- JS Injected -->
                </tbody>
            </table>
            <div id="emptyCartMsg" class="h-40 flex flex-col items-center justify-center text-gray-400 opacity-60">
                <i class="fas fa-shopping-basket text-2xl mb-2"></i>
                <p class="text-xs font-medium">No items added</p>
            </div>
        </div>

        <!-- Checkout Footer (Compact) -->
        <div class="border-t border-gray-200 bg-gray-50/30 p-1.5 space-y-1 shadow-[0_-5px_15px_rgba(0,0,0,0.02)] z-20">
            
            <!-- Discount -->
            <div class="flex justify-between items-center bg-white border border-gray-200 rounded-lg px-3 py-1.5 shadow-sm">
                <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Total Discount (Rs.)</span>
                <input type="number" id="discountInput" value="0"
                       class="w-28 bg-transparent border-none p-0 text-base font-black text-red-600 text-right focus:ring-0 outline-none" 
                       oninput="updateTotals()" min="0">
            </div>

            <!-- Grand Total -->
            <div class="flex justify-between items-center bg-gradient-to-br from-teal-500 to-teal-700 border border-teal-600 rounded-lg px-4 py-2 shadow-lg mb-0.5">
                <span class="text-xs font-black text-white uppercase tracking-widest">Net Payable</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-sm font-bold text-teal-100">Rs.</span>
                    <span id="grandTotalDisplay" class="text-xl font-black text-white tracking-tight">0</span>
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
let isBelowCostConfirmed = false;
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

function formatStockHierarchyJS(qty, p) {
    qty = parseFloat(qty);
    const unitName = p.primaryUnit || p.unit || 'Units';
    if (qty <= 0) return `0 ${unitName}`;

    const chain = getUnitHierarchyJS(unitName);
    if (chain.length <= 1) return `<b>${qty.toFixed(0)}</b> <span class="text-[8px] opacity-60 uppercase">${unitName}</span>`;

    let remaining = qty;
    let parts = [];
    let factors = [];
    
    chain.forEach((u, i) => {
        let mult = getBaseMultiplierForProductJS(u.name, p);
        
        // 1. Hierarchical breakdown
        let count = Math.floor(remaining / mult);
        if (count > 0) {
            parts.push(`<b>${count}</b> <span class="text-[8px] opacity-60 uppercase">${u.name}</span>`);
            remaining = remaining % mult;
        }

        // 2. Build factors for clarity (requested by user)
        if (i === 0 && chain.length > 1) {
            const f2 = parseFloat(p.f2 || p.factor_level2 || 1) || 1;
            factors.push(`1 ${u.name} = ${f2} ${chain[1].name}`);
        }
        if (i === 1 && chain.length > 2) {
            const f3 = parseFloat(p.f3 || p.factor_level3 || 1) || 1;
            factors.push(`1 ${u.name} = ${f3} ${chain[2].name}`);
        }
    });

    let display = parts.length === 0 ? `0 ${unitName}` : parts.join(', ');
    
    // Absolute total in base unit
    const baseUnit = chain[chain.length - 1].name;
    display += ` <span class="text-[8px] text-teal-600 font-bold ml-1 tracking-tight italic">[Total: ${qty % 1 === 0 ? qty : qty.toFixed(2)} ${baseUnit}]</span>`;
    
    // Factor descriptions
    if (factors.length > 0) {
        display += ` <div class="text-[7px] text-gray-400 font-medium leading-none mt-0.5 opacity-80">Factors: ${factors.join(' | ')}</div>`;
    }
    
    return display;
}

function handleProductClick(card) {
    const p = JSON.parse(card.dataset.product);
    addToCart(p.id, p.name, p.sell_price, p.unit, p.stock_quantity, p.buy_price, p.factor_level2, p.factor_level3);
}

function addToCart(id, name, price, unit, stock, buyPrice, f2, f3) {
    const sId = String(id);
    let existing = cart.find(i => String(i.id) === sId);
    
    const productMock = { primaryUnit: unit, f2: f2, f3: f3 };

    if (existing) {
        let multiplier = getBaseMultiplierForProductJS(existing.unit, productMock);
        if ((existing.qty + 1) * multiplier > stock) {
            showAlert(`Not enough stock available.`, 'Inventory Alert');
            return;
        }
        existing.qty++;
        existing.total = existing.qty * existing.price;
    } else {
        if (stock < 0.01) { showAlert("Out of stock!", 'Empty Shelf'); return; }
        cart.push({ 
            id: sId, 
            name, 
            price: parseFloat(price), 
            unit: unit, 
            qty: 1, 
            total: parseFloat(price), 
            max_stock_base: parseFloat(stock), 
            buy_price_base: parseFloat(buyPrice) / getBaseMultiplierForProductJS(unit, productMock),
            primaryUnit: unit,
            f2: f2,
            f3: f3
        });
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
        <tr class="group hover:bg-teal-50/30 transition-colors">
            <td class="px-3 py-1.5 border-b border-gray-100">
                <div class="font-bold text-gray-800 text-sm tracking-tight leading-tight">${item.name}</div>
                <div class="text-[9px] text-gray-400 font-bold uppercase tracking-tighter">${item.primaryUnit}</div>
            </td>
            <td class="px-2 py-1.5 border-b border-gray-100 text-center">
                <div class="flex flex-col gap-0.5 items-center">
                    <input type="number" id="qty-${index}" value="${item.qty}" min="0" step="any" 
                           class="w-14 px-1 py-0.5 text-center font-bold border border-gray-200 rounded text-sm focus:border-teal-500 outline-none transition-all" 
                           oninput="updateQty(${index}, this.value)">
                    <select onchange="updateItemUnit(${index}, this.value)" class="text-[10px] border-none bg-gray-50 rounded px-1 py-0 outline-none font-bold text-teal-600">
                        ${getUnitHierarchyJS(item.primaryUnit).map((u, i) => `<option value="${u.name}" ${u.name === item.unit ? 'selected' : ''}>${'&nbsp;'.repeat(i)}${u.name}</option>`).join('')}
                    </select>
                </div>
            </td>
            <td class="px-2 py-1.5 border-b border-gray-100 text-center">
                <input type="number" id="price-${index}" value="${item.price}" min="0" step="any"
                       class="w-20 px-1 py-0.5 text-center font-semibold border border-gray-200 rounded text-xs focus:border-teal-500 outline-none transition-all" 
                       oninput="updateUnitPrice(${index}, this.value)">
            </td>
            <td class="px-3 py-1.5 border-b border-gray-100 text-right">
                <div class="flex flex-col items-end">
                    <input type="number" id="total-input-${index}" value="${item.total.toFixed(0)}" step="any"
                           class="w-24 px-1 py-0.5 text-right font-bold text-gray-700 border border-gray-200 rounded text-sm focus:border-teal-500 outline-none transition-all" 
                           oninput="updateItemTotal(${index}, this.value)">
                    ${(() => {
                        const chain = getUnitHierarchyJS(item.primaryUnit);
                        if (chain.length <= 1) return '';
                        const mult = getBaseMultiplierForProductJS(item.unit, item);
                        const totalBase = item.qty * mult;
                        const baseUnit = chain[chain.length-1].name;
                        return `<span id="total-base-${index}" class="text-[9px] text-teal-600 font-bold bg-teal-50 px-1 rounded uppercase tracking-tighter">Total: ${totalBase % 1 === 0 ? totalBase : totalBase.toFixed(2)} ${baseUnit}</span>`;
                    })()
                    }
                </div>
            </td>
            <td class="px-2 py-1.5 border-b border-gray-100 text-center">
                <button onclick="removeFromCart(${index})" class="text-gray-300 hover:text-red-500 transition-colors">
                    <i class="fas fa-times text-xs"></i>
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
    const totalInput = document.getElementById(`total-input-${index}`);
    if (totalInput) totalInput.value = cart[index].total.toFixed(0);
    
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
    
    const qtyInput = document.getElementById(`qty-${index}`);
    const mult = getBaseMultiplierForProductJS(cart[index].unit, cart[index]);
    const max_qty_allowed = cart[index].max_stock_base / mult;

    if (qty > max_qty_allowed) {
        qty = max_qty_allowed;
        if (qtyInput) qtyInput.value = qty;
        showAlert(`Quantity capped at maximum available stock (${qty.toFixed(2)} ${cart[index].unit})`, 'Stock Limit');
    }
    
    cart[index].qty = qty;
    cart[index].total = cart[index].qty * cart[index].price;
    cart[index].manualTotal = false; // Reset manual flag on qty change
    
    // Update UI without full re-render
    const totalInput = document.getElementById(`total-input-${index}`);
    const totalBaseSpan = document.getElementById(`total-base-${index}`);
    
    if (totalInput) totalInput.value = cart[index].total.toFixed(0);
    
    if (totalBaseSpan) {
        const chain = getUnitHierarchyJS(cart[index].primaryUnit);
        const totalBase = cart[index].qty * mult;
        const baseUnit = chain[chain.length-1].name;
        totalBaseSpan.innerText = `Total: ${totalBase % 1 === 0 ? totalBase : totalBase.toFixed(2)} ${baseUnit}`;
    }
    
    // Update color
    if (qtyInput) {
        if (qty >= max_qty_allowed) {
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
    const subtotal = cart.reduce((sum, item) => sum + parseFloat(item.total || 0), 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    let total = Math.max(0, Math.round(subtotal - discount));
    
    document.getElementById('grandTotalDisplay').innerText = total.toLocaleString();
    document.getElementById('inputTotal').value = total;
    document.getElementById('inputDiscount').value = discount;
    document.getElementById('cartData').value = JSON.stringify(cart);
    
    if (document.getElementById('paymentMethod').value === 'Cash') {
        document.getElementById('paidAmount').value = total;
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
    const currentTotal = parseInt(document.getElementById('inputTotal').value) || 0;
    let minTotal = 0;
    cart.forEach(item => { minTotal += (item.buy_price || 0) * item.qty; });
    const warning = document.getElementById('priceWarning');
    if (currentTotal > 0 && currentTotal < minTotal) {
        warning.classList.remove('hidden');
        return false; // Below cost
    } else {
        warning.classList.add('hidden');
        isBelowCostConfirmed = false; // Reset if price becomes valid
        return true; // OK
    }
}

function updateStockLabels() {
    let cartUsage = {};
    cart.forEach(item => {
        let mult = getBaseMultiplierForProductJS(item.unit, item);
        cartUsage[item.id] = (cartUsage[item.id] || 0) + (item.qty * mult);
    });

    document.querySelectorAll('.product-card').forEach(card => {
        const label = card.querySelector('[id^="stock-label-"]');
        if (!label) return;
        const id = label.dataset.id;
        const totalBase = parseFloat(label.dataset.stock);
        const usedBase = cartUsage[id] || 0;
        const remainingBase = totalBase - usedBase;
        
        label.innerHTML = remainingBase <= 0 ? 
            '<span class="text-red-500 font-bold">Out of Stock</span>' : 
            formatStockHierarchyJS(remainingBase, { primaryUnit: label.dataset.unit, factor_level2: label.dataset.f2, factor_level3: label.dataset.f3 });
        
        if (remainingBase <= 0) card.classList.add('opacity-50', 'pointer-events-none');
        else card.classList.remove('opacity-50', 'pointer-events-none');
    });
}

function updateItemUnit(index, newUnit) {
    const oldUnit = cart[index].unit;
    const oldMult = getBaseMultiplierForProductJS(oldUnit, cart[index]);
    const newMult = getBaseMultiplierForProductJS(newUnit, cart[index]);
    
    cart[index].unit = newUnit;
    cart[index].price = (cart[index].price / oldMult) * newMult;
    cart[index].total = cart[index].qty * cart[index].price;
    
    renderCart();
    updateStockLabels();
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
    
    const isBelowCost = !validateTotalPrice();
    if (isBelowCost && !isBelowCostConfirmed) {
        showConfirm("You are selling things below cost! Are you ready to proceed?", () => {
            isBelowCostConfirmed = true;
            document.getElementById('submitBtn').click(); // Re-trigger submit
        }, "Soft Validation Warning");
        return false;
    }
    
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
