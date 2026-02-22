<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireLogin();
if (!hasPermission('add_restock')) die("Unauthorized Access");

include '../includes/header.php';

$dealers = readCSV('dealers');
$products = readCSV('products');
$units = readCSV('units');

// Sort products by name
usort($products, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});
?>

<div class="max-w-6xl mx-auto px-4">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-black text-gray-800 tracking-tight">Quick Restock</h2>
            <p class="text-sm text-gray-500 font-medium">Select a product below to add items to your inventory</p>
        </div>
        <a href="inventory.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-6 py-3 rounded-2xl font-bold flex items-center transition-all active:scale-95 shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i> back to inventory
        </a>
    </div>

    <!-- Search Section -->
    <div class="mb-8 sticky top-0 z-20 bg-gray-50/80 backdrop-blur-md py-4 rounded-3xl">
        <div class="relative group">
            <span class="absolute inset-y-0 left-0 flex items-center pl-5 text-gray-400 group-focus-within:text-teal-500 transition-colors">
                <i class="fas fa-search text-xl"></i>
            </span>
            <input type="text" id="restockSearch" autofocus 
                   placeholder="Search products by name or category..." 
                   class="w-full pl-14 pr-6 py-5 bg-white border-2 border-transparent focus:border-teal-500 rounded-[2rem] shadow-xl outline-none text-lg font-medium transition-all placeholder:text-gray-300"
                   oninput="filterRestockProducts()">
        </div>
    </div>

    <!-- Products Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="productGrid">
        <?php foreach($products as $p): ?>
            <div class="product-card bg-white rounded-[2rem] p-6 shadow-md border border-gray-100 hover:shadow-2xl hover:-translate-y-2 transition-all cursor-pointer group relative overflow-hidden"
                 data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
                 data-category="<?= strtolower(htmlspecialchars($p['category'])) ?>"
                 onclick='openRestockModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'>
                
                <div class="flex justify-between items-start mb-4">
                    <div class="p-4 bg-teal-50 text-teal-600 rounded-2xl group-hover:bg-teal-600 group-hover:text-white transition-colors">
                        <i class="fas fa-box text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1">Stock Level</span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $p['stock_quantity'] < 10 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>">
                            <?= formatStockHierarchy($p['stock_quantity'], $p) ?>
                        </span>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="text-lg font-bold text-gray-800 leading-tight group-hover:text-teal-600 transition-colors mb-1">
                        <?= htmlspecialchars($p['name']) ?>
                    </h3>
                    <span class="text-xs font-bold text-teal-500 uppercase tracking-wider bg-teal-50 px-2 py-0.5 rounded">
                        <?= htmlspecialchars($p['category']) ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2 mt-auto border-t border-gray-50 pt-4">
                    <div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase block">Buy Rate</span>
                        <span class="text-sm font-bold text-gray-700"><?= formatCurrency($p['buy_price']) ?></span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase block">Sell Rate</span>
                        <span class="text-sm font-bold text-teal-600"><?= formatCurrency($p['sell_price']) ?></span>
                    </div>
                </div>

                <!-- Hover Effect Overlay -->
                <div class="absolute bottom-0 right-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="w-10 h-10 bg-teal-600 text-white rounded-xl flex items-center justify-center shadow-lg transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                        <i class="fas fa-plus"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="hidden py-24 text-center">
        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-search text-4xl text-gray-300"></i>
        </div>
        <h4 class="text-xl font-bold text-gray-800">No products match your search</h4>
        <p class="text-gray-500 mt-2">Try searching for a different name or category</p>
    </div>
</div>
<!-- ==================== RESTOCK MODAL ==================== -->
<div id="restockModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm hidden z-[9999] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-[0_25px_60px_rgba(0,0,0,0.18)] w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-teal-600 to-teal-800 px-7 py-5 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 bg-white/15 rounded-2xl flex items-center justify-center border border-white/20">
                    <i class="fas fa-boxes-packing text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-white tracking-tight leading-tight" id="modal_product_name">Inventory Restock</h3>
                    <p class="text-teal-200/70 font-bold text-[10px] uppercase tracking-widest mt-0.5" id="modal_product_category">Category</p>
                </div>
            </div>
            <button onclick="closeRestockModal()" 
                    class="w-10 h-10 bg-white/10 hover:bg-white/25 text-white rounded-xl flex items-center justify-center transition-all active:scale-90 hover:rotate-90 duration-200">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Scrollable Body -->
        <div class="overflow-y-auto custom-scrollbar flex-1">
            <form id="restock_form" method="POST" action="../actions/restock_process.php" class="p-6 space-y-5">
                <input type="hidden" name="product_id" id="form_product_id">

                <!-- Stats Row -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 flex flex-col gap-1.5">
                        <div class="flex items-center gap-1.5 text-slate-400">
                            <i class="fas fa-warehouse text-[11px] w-4 text-center"></i>
                            <span class="text-[9px] font-black uppercase tracking-widest">Current Stock</span>
                        </div>
                        <div class="text-base font-black text-slate-800 leading-snug" id="current_stock_display">—</div>
                    </div>
                    <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 flex flex-col gap-1.5">
                        <div class="flex items-center gap-1.5 text-amber-500">
                            <i class="fas fa-tag text-[11px] w-4 text-center"></i>
                            <span class="text-[9px] font-black uppercase tracking-widest">Old Buy Rate</span>
                        </div>
                        <div class="text-base font-black text-amber-700 leading-snug" id="current_buy_display">—</div>
                    </div>
                    <div class="bg-teal-50 border border-teal-100 rounded-2xl p-4 flex flex-col gap-1.5">
                        <div class="flex items-center gap-1.5 text-teal-500">
                            <i class="fas fa-chart-line text-[11px] w-4 text-center"></i>
                            <span class="text-[9px] font-black uppercase tracking-widest">Old Sell Rate</span>
                        </div>
                        <div class="text-base font-black text-teal-700 leading-snug" id="current_sell_display">—</div>
                    </div>
                </div>

                <!-- Section: Quantity -->
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-[9px] font-black text-gray-300 uppercase tracking-[0.2em]">Quantity</span>
                        <div class="flex-1 h-px bg-gray-100"></div>
                    </div>
                    <div class="grid grid-cols-[150px_1fr] gap-3">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Unit</label>
                            <select name="selected_unit" id="restock_unit_select" onchange="updateFactorUI('restock')" 
                                    class="w-full px-4 py-3 bg-teal-600 text-white rounded-xl font-black uppercase tracking-widest outline-none cursor-pointer hover:bg-teal-700 transition-colors shadow-md shadow-teal-900/10 text-sm">
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Amount</label>
                            <div class="relative">
                                <input type="number" step="any" name="quantity" id="restock_qty" required 
                                       class="w-full px-5 py-3 pr-28 bg-white border-2 border-gray-100 rounded-xl focus:border-teal-500 transition-all outline-none text-xl font-black placeholder:text-gray-200"
                                       placeholder="0.00" oninput="calculateTotal()">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black text-gray-300 uppercase tracking-widest pointer-events-none">Numbers Only</span>
                            </div>
                        </div>
                    </div>
                    <!-- Hierarchical factors -->
                    <div id="restock_factors_container" class="hidden mt-3 p-4 bg-gray-50 rounded-2xl border border-gray-100 space-y-3 animate-in slide-in-from-top-2 duration-200"></div>
                    <input type="hidden" name="factor_level2" id="restock_f2" value="1">
                    <input type="hidden" name="factor_level3" id="restock_f3" value="1">
                </div>

                <!-- Section: Pricing -->
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-[9px] font-black text-gray-300 uppercase tracking-[0.2em]">New Pricing</span>
                        <div class="flex-1 h-px bg-gray-100"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-2">Buy Rate</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-black text-gray-300">Rs.</span>
                                <input type="number" step="0.01" name="new_buy_price" id="restock_buy_price" required 
                                       class="w-full pl-11 pr-3 py-2.5 bg-white border-2 border-gray-100 rounded-xl focus:border-teal-500 transition-all outline-none font-black text-lg"
                                       oninput="calculateTotal()">
                            </div>
                        </div>
                        <div class="bg-teal-50 border border-teal-100 rounded-2xl p-4">
                            <label class="block text-[9px] font-black text-teal-600/60 uppercase tracking-widest mb-2">Selling Rate</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-black text-teal-300">Rs.</span>
                                <input type="number" step="0.01" name="new_sell_price" id="restock_sell_price" required 
                                       class="w-full pl-11 pr-3 py-2.5 bg-white border-2 border-teal-100 rounded-xl focus:border-teal-600 transition-all outline-none font-black text-lg text-teal-700">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Logistics -->
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-[9px] font-black text-gray-300 uppercase tracking-[0.2em]">Logistics</span>
                        <div class="flex-1 h-px bg-gray-100"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Supplier Dropdown -->
                        <div class="space-y-2">
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                <i class="fas fa-truck-moving text-teal-400 text-[10px]"></i> Supplier
                            </label>
                            <div class="relative" id="dealerDropdownContainer">
                                <button type="button" onclick="toggleDealerDropdown()" id="dealerDropdownBtn" 
                                        class="w-full px-4 py-3 bg-white border-2 border-gray-100 rounded-xl outline-none font-bold text-left flex justify-between items-center transition-all shadow-sm hover:border-gray-200">
                                    <span id="selectedDealerLabel" class="truncate text-gray-700 text-sm">Open Market (Default)</span>
                                    <i class="fas fa-chevron-down text-teal-400 text-xs transition-transform duration-200" id="dealerChevron"></i>
                                </button>
                                <div id="dealerDropdownPanel" class="hidden absolute z-[100] w-full mt-1.5 bg-white border-2 border-gray-100 rounded-2xl shadow-2xl overflow-hidden transform origin-top transition-all scale-95 opacity-0">
                                    <div class="p-2.5 border-b border-gray-50 bg-gray-50/60">
                                        <div class="relative">
                                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                            <input type="text" id="dealerSearchInput" autocomplete="off" oninput="filterDealers(this.value)" 
                                                   placeholder="Search supplier..." 
                                                   class="w-full pl-9 pr-3 py-2 text-sm border-2 border-gray-100 rounded-xl focus:border-teal-500 outline-none transition-all font-bold">
                                        </div>
                                    </div>
                                    <div class="max-h-52 overflow-y-auto custom-scrollbar" id="dealerList">
                                        <div onclick="selectDealer('OPEN_MARKET', 'Open Market (Default)')" 
                                             class="dealer-nav-item px-4 py-3 text-sm hover:bg-teal-50 cursor-pointer font-bold border-b border-gray-50 flex items-center gap-3 transition-colors">
                                            <div class="w-7 h-7 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-xs shrink-0"><i class="fas fa-store"></i></div>
                                            <span>Open Market</span>
                                        </div>
                                        <div onclick="selectDealer('ADD_NEW', '+ Create New Dealer')" 
                                             class="dealer-nav-item px-4 py-3 text-sm hover:bg-teal-50 cursor-pointer font-bold text-teal-600 border-b border-gray-50 flex items-center gap-3 transition-colors">
                                            <div class="w-7 h-7 bg-teal-50 rounded-lg flex items-center justify-center text-teal-500 text-xs shrink-0"><i class="fas fa-plus"></i></div>
                                            <span>+ New Dealer</span>
                                        </div>
                                        <div class="px-4 py-1.5 bg-gray-50 text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">Registered Partners</div>
                                        <?php foreach($dealers as $dlr): ?>
                                            <div onclick="selectDealer('<?= $dlr['id'] ?>', '<?= htmlspecialchars($dlr['name']) ?>')" 
                                                 class="dealer-item dealer-nav-item px-4 py-3 text-sm hover:bg-teal-50 cursor-pointer border-b border-gray-50 transition-all flex items-center gap-3" 
                                                 data-name="<?= strtolower(htmlspecialchars($dlr['name'])) ?>">
                                                <div class="w-7 h-7 bg-gray-50 rounded-lg flex items-center justify-center text-gray-400 font-black text-[10px] shrink-0"><?= $dlr['id'] ?></div>
                                                <div class="flex flex-col min-w-0">
                                                    <span class="font-bold text-gray-700 text-sm truncate"><?= htmlspecialchars($dlr['name']) ?></span>
                                                    <span class="text-[9px] text-gray-400 uppercase tracking-tighter">Verified Supplier</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div id="noDealerFound" class="hidden p-5 text-center text-gray-400 text-sm italic">No records found...</div>
                                </div>
                                <input type="hidden" name="dealer_id" id="dealerSelect" value="OPEN_MARKET">
                            </div>
                            <div id="new_dealer_input_container" class="hidden">
                                <input type="text" name="new_dealer_name" id="new_dealer_name" 
                                       class="w-full rounded-xl border-teal-300 border-2 px-4 py-3 focus:border-teal-500 outline-none font-bold placeholder:text-gray-300 text-sm" 
                                       placeholder="Business Name *">
                            </div>
                            <div id="restock_dealer_surplus_msg" class="hidden px-4 py-3 rounded-xl bg-green-50 border border-green-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center text-white text-[10px]"><i class="fas fa-coins"></i></div>
                                    <span class="text-[9px] font-black text-green-800 uppercase tracking-widest">Credit Balance</span>
                                </div>
                                <span class="font-black text-green-700 text-sm" id="surplus_amount">Rs. 0</span>
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="space-y-2">
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                <i class="fas fa-calendar-check text-teal-400 text-[10px]"></i> Arrival Date
                            </label>
                            <input type="date" name="date" value="<?= date('Y-m-d') ?>" 
                                   class="w-full px-4 py-3 bg-white border-2 border-gray-100 rounded-xl focus:border-teal-500 outline-none font-bold text-gray-700 transition-all text-sm">
                        </div>
                    </div>
                </div>

                <!-- Remarks -->
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-[9px] font-black text-gray-300 uppercase tracking-[0.2em]">Notes</span>
                        <div class="flex-1 h-px bg-gray-100"></div>
                    </div>
                    <textarea name="remarks" id="restock_remarks" rows="2"
                              class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-teal-500 transition-all outline-none font-medium text-gray-600 resize-none text-sm"
                              placeholder="Add batch details or special notes..."></textarea>
                </div>

                <!-- Footer: Total & Submit -->
                <div class="flex gap-3 pt-4 border-t border-gray-100">
                    <!-- Total Banner -->
                    <div class="flex-1 bg-gradient-to-br from-teal-600 to-teal-800 rounded-2xl px-5 py-4 flex items-center justify-between gap-4 relative overflow-hidden">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(255,255,255,0.1),transparent_70%)]"></div>
                        <div class="relative z-10">
                            <span class="text-[9px] font-black text-white/50 uppercase tracking-widest block mb-0.5">Grand Total</span>
                            <div class="flex items-baseline gap-1">
                                <span class="text-white/50 font-black text-sm">Rs.</span>
                                <span id="total_bill_display" class="text-2xl font-black text-white tracking-tight">0</span>
                            </div>
                        </div>
                        <div class="relative z-10 text-right shrink-0">
                            <label class="block text-[9px] font-black text-white/50 uppercase tracking-widest mb-1">Paid Now</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-black text-teal-300">Rs.</span>
                                <input type="number" step="0.01" name="amount_paid" id="amount_paid" 
                                       class="w-32 pl-9 pr-3 py-2 bg-black/20 border-2 border-white/20 rounded-xl focus:border-white focus:bg-white focus:text-teal-900 transition-all text-white font-black text-right outline-none text-sm placeholder:text-white/30"
                                       placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    <!-- Buttons -->
                    <div class="flex flex-col gap-2 w-36 shrink-0">
                        <button type="button" onclick="validateAndSubmit()" 
                                class="flex-1 bg-gray-900 text-white rounded-2xl font-black text-sm hover:bg-black shadow-lg transition-all hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2.5 group">
                            <i class="fas fa-check-double text-teal-400 group-hover:scale-125 transition-transform text-sm"></i>
                            <span>CONFIRM</span>
                        </button>
                        <button type="button" onclick="closeRestockModal()" 
                                class="py-2 text-gray-400 font-black text-[9px] uppercase tracking-widest hover:text-red-500 transition-all flex items-center justify-center gap-1.5">
                            <i class="fas fa-times-circle"></i> Cancel
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
<!-- ==================== END RESTOCK MODAL ==================== -->


<!-- Overpayment Warning Workspace -->
<div id="overpaymentModal" class="fixed inset-0 bg-gray-950/80 backdrop-blur-xl hidden z-[10000] flex items-center justify-center p-6">
    <div class="bg-white rounded-[3rem] p-10 shadow-[0_40px_100px_rgba(0,0,0,0.5)] max-w-sm w-full text-center transform transition-all scale-100 animate-in zoom-in duration-300 border border-gray-100">
        <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-8 border-4 border-white shadow-xl">
            <i class="fas fa-hand-paper text-3xl text-red-600"></i>
        </div>
        <h3 class="text-2xl font-black text-gray-900 mb-3 tracking-tight">Cap Limit Reached</h3>
        <p class="text-xs text-gray-500 font-bold mb-8 leading-relaxed uppercase tracking-wider" id="overpaymentMsg">
            You are attempting to pay beyond the net valuation.
        </p>
        <button type="button" onclick="closeOverpaymentModal()" class="w-full py-5 bg-gray-900 text-white font-black rounded-2xl hover:bg-black shadow-xl transition active:scale-95 uppercase tracking-widest text-xs">
            I will correct it
        </button>
    </div>
</div>

<script>
    function filterRestockProducts() {
        const term = document.getElementById('restockSearch').value.toLowerCase();
        const cards = document.querySelectorAll('.product-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            const category = card.getAttribute('data-category');
            
            if (name.includes(term) || category.includes(term)) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        document.getElementById('emptyState').classList.toggle('hidden', visibleCount > 0);
        document.getElementById('productGrid').classList.toggle('hidden', visibleCount === 0);
    }

    let activeProductFactors = { f2: 1, f3: 1, unit: '' };
    let currentDealerBalance = 0;

    /**
     * Logic to safely open and populate the restock modal
     */
    function openRestockModal(product) {
        try {
            // 1. Set global state for this product
            activeProductFactors = { 
                f2: parseFloat(product.factor_level2 || 1) || 1, 
                f3: parseFloat(product.factor_level3 || 1) || 1,
                unit: product.unit || ''
            };

            // 2. Populate Header & Basic Info
            document.getElementById('modal_product_name').innerText = product.name || 'Unknown Product';
            document.getElementById('modal_product_category').innerText = product.category || 'General';
            document.getElementById('form_product_id').value = product.id;
            
            // 3. Stats Dashboard
            document.getElementById('current_stock_display').innerHTML = formatStockHierarchyJS(product.stock_quantity, product);
            document.getElementById('current_buy_display').innerText = 'Rs. ' + (parseFloat(product.buy_price) || 0).toLocaleString();
            document.getElementById('current_sell_display').innerText = 'Rs. ' + (parseFloat(product.sell_price) || 0).toLocaleString();
            
            // 4. Populate Unit Dropdown based on product hierarchy
            const unitSelect = document.getElementById('restock_unit_select');
            unitSelect.innerHTML = '';
            const chain = getUnitHierarchyJS(product.unit);
            
            if (chain.length > 0) {
                chain.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.name;
                    opt.innerText = u.name;
                    unitSelect.appendChild(opt);
                });
            } else {
                // Fallback for missing unit data
                const opt = document.createElement('option');
                opt.value = product.unit || 'Units';
                opt.innerText = product.unit || 'Units';
                unitSelect.appendChild(opt);
            }

            // 5. Reset Inputs
            document.getElementById('restock_buy_price').value = product.buy_price || 0;
            document.getElementById('restock_sell_price').value = product.sell_price || 0;
            document.getElementById('restock_qty').value = '';
            document.getElementById('restock_remarks').value = '';
            document.getElementById('amount_paid').value = '0';
            document.getElementById('total_bill_display').innerText = '0';

            // 6. Dynamic UI Updates
            updateFactorUI('restock');
            
            // 7. Show Modal
            const modal = document.getElementById('restockModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // 8. Auto-focus
            setTimeout(() => document.getElementById('restock_qty').focus(), 150);
            
        } catch (error) {
            console.error("Critical error opening restock modal:", error);
            alert("Failed to open modal. Product data might be incomplete.");
        }
    }

    function closeRestockModal() {
        const modal = document.getElementById('restockModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    /**
     * Dealer Searchable Dropdown Logic
     */
    function toggleDealerDropdown() {
        const panel = document.getElementById('dealerDropdownPanel');
        const chevron = document.getElementById('dealerChevron');
        const isHidden = panel.classList.contains('hidden');
        
        if (isHidden) {
            panel.classList.remove('hidden');
            chevron.classList.add('rotate-180');
            setTimeout(() => {
                panel.classList.remove('scale-95', 'opacity-0');
                panel.classList.add('scale-100', 'opacity-100');
                document.getElementById('dealerSearchInput').focus();
            }, 10);
        } else {
            closeDealerDropdown();
        }
    }

    function closeDealerDropdown() {
        const panel = document.getElementById('dealerDropdownPanel');
        const chevron = document.getElementById('dealerChevron');
        if (!panel) return;
        panel.classList.remove('scale-100', 'opacity-100');
        panel.classList.add('scale-95', 'opacity-0');
        chevron.classList.remove('rotate-180');
        setTimeout(() => {
            panel.classList.add('hidden');
        }, 200);
    }

    function filterDealers(query) {
        const q = query.toLowerCase();
        const navItems = document.querySelectorAll('.dealer-nav-item');
        const dealerItems = document.querySelectorAll('.dealer-item');
        let foundCount = 0;
        
        dealerItems.forEach(item => {
            const name = item.dataset.name || '';
            if (name.includes(q)) {
                item.classList.remove('hidden');
                foundCount++;
            } else {
                item.classList.add('hidden');
            }
        });

        navItems.forEach(item => {
            if (!item.classList.contains('dealer-item')) {
                if (q !== '') item.classList.add('hidden');
                else item.classList.remove('hidden');
            }
        });
        
        const activeClass = 'dealer-active';
        const highlightClasses = ['bg-teal-50', 'ring-2', 'ring-teal-200', activeClass];
        navItems.forEach(item => item.classList.remove(...highlightClasses));
        
        const visibleItems = Array.from(navItems).filter(el => !el.classList.contains('hidden'));
        if (visibleItems.length > 0 && q !== '') {
            visibleItems[0].classList.add(...highlightClasses);
        }
        
        document.getElementById('noDealerFound').classList.toggle('hidden', foundCount > 0 || q === '');
    }

    function selectDealer(id, name) {
        document.getElementById('dealerSelect').value = id;
        document.getElementById('selectedDealerLabel').innerText = name;
        
        // Handle "New Dealer" UI
        const container = document.getElementById('new_dealer_input_container');
        const input = document.getElementById('new_dealer_name');
        if (id === 'ADD_NEW') {
            container.classList.remove('hidden');
            input.required = true;
            input.focus();
        } else {
            container.classList.add('hidden');
            input.required = false;
        }

        closeDealerDropdown();
        document.getElementById('dealerSearchInput').value = '';
        filterDealers('');
        fetchDealerBalance(id);
    }

    // Close on click outside
    document.addEventListener('click', function(e) {
        const container = document.getElementById('dealerDropdownContainer');
        if (container && !container.contains(e.target)) {
            closeDealerDropdown();
        }
    });

    // Keyboard Navigation
    document.getElementById('dealerSearchInput').addEventListener('keydown', function(e) {
        const activeClass = 'dealer-active';
        const highlightClasses = ['bg-teal-50', 'ring-2', 'ring-teal-200', activeClass];
        const visibleItems = Array.from(document.querySelectorAll('.dealer-nav-item')).filter(el => !el.classList.contains('hidden'));
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

    function toggleNewDealerInput(select) {
        // Obsolete but kept for safety ifreferenced elsewhere, selectDealer handles this now
    }
    
    function fetchDealerBalance(dealerId) {
        if(!dealerId || dealerId === 'ADD_NEW' || dealerId === 'OPEN_MARKET') {
            currentDealerBalance = 0;
            calculateTotal();
            return;
        }
        
        fetch(`inventory.php?action=get_balance&dealer_id=${dealerId}`)
            .then(r => r.json())
            .then(data => {
                currentDealerBalance = parseFloat(data.balance || 0);
                calculateTotal();
            })
            .catch(e => {
                console.error("Balance fetch error:", e);
                currentDealerBalance = 0;
                calculateTotal();
            });
    }

    /**
     * Unit Hierarchy Core (Shared Logic)
     */
    const availableUnits = <?= json_encode($units) ?>;

    function getUnitHierarchyJS(unitName) {
        if (!unitName) return [];
        let startNode = availableUnits.find(u => u.name.toLowerCase() === unitName.toLowerCase());
        if (!startNode) return [];
        
        // Find Root
        let root = startNode;
        while(parseInt(root.parent_id) !== 0) {
            let parent = availableUnits.find(u => parseInt(u.id) === parseInt(root.parent_id));
            if(!parent) break;
            root = parent;
        }
        
        // Build Chain
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

    function updateFactorUI(prefix) {
        const unitSelect = document.getElementById(prefix + '_unit_select');
        const currentUnit = unitSelect ? unitSelect.value : '';
        const container = document.getElementById(prefix + '_factors_container');
        if(!container) return;

        const chain = getUnitHierarchyJS(currentUnit); 
        container.innerHTML = '';
        
        if(chain.length <= 1) {
            container.classList.add('hidden');
        } else {
            container.classList.remove('hidden');
            
            // Primary Quantity Sync
            const mainQtyInput = document.getElementById(prefix + '_qty');
            const syncVal = mainQtyInput ? mainQtyInput.value : '';
            
            container.insertAdjacentHTML('beforeend', `
                <div class="p-5 bg-teal-50/30 rounded-2xl border border-teal-100 flex items-center justify-between gap-4">
                    <div class="flex-1">
                        <span class="text-[10px] font-black text-teal-800/50 uppercase tracking-widest block mb-1">Total ${chain[0].name} to Add</span>
                        <input type="number" step="any" value="${syncVal}" 
                               oninput="document.getElementById('${prefix}_qty').value=this.value; calculateTotal(); calcTotalBaseJS('${prefix}');" 
                               class="w-full bg-transparent outline-none font-black text-xl text-teal-900 placeholder:text-teal-200" 
                               placeholder="0.00">
                    </div>
                    <div class="w-10 h-10 bg-teal-600 rounded-xl flex items-center justify-center text-white"><i class="fas fa-layer-group"></i></div>
                </div>
            `);

            // Scaling Factors
            for(let i=0; i < chain.length - 1; i++) {
                const targetFactorId = prefix + '_f' + (i+2);
                const currentVal = document.getElementById(targetFactorId).value || 1;
                
                container.insertAdjacentHTML('beforeend', `
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center gap-3 px-1">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-tighter">1 ${chain[i].name} contains</span>
                        </div>
                        <div class="relative">
                            <input type="number" step="any" value="${currentVal}" 
                                   oninput="document.getElementById('${targetFactorId}').value=this.value; calcTotalBaseJS('${prefix}'); calculateTotal();" 
                                   class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl font-black text-right outline-none focus:border-teal-500">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-300 uppercase">${chain[i+1].name}</span>
                        </div>
                    </div>
                `);
            }
            
            // Result Preview
            container.insertAdjacentHTML('beforeend', `
                <div id="${prefix}_total_preview" class="p-4 bg-gray-900 text-white rounded-2xl flex justify-between items-center shadow-lg shadow-gray-900/10">
                    <span class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400">Total Calculation</span>
                    <span id="${prefix}_base_result" class="font-black text-lg">0 Units</span>
                </div>
            `);
            calcTotalBaseJS(prefix);
        }
        calculateTotal();
    }

    function calcTotalBaseJS(prefix) {
        const chain = getUnitHierarchyJS(document.getElementById(prefix + '_unit_select').value);
        if(chain.length <= 1) return;

        const q = parseFloat(document.getElementById(prefix + '_qty').value || 0);
        const f2 = parseFloat(document.getElementById(prefix + '_f2').value || 1);
        const f3 = parseFloat(document.getElementById(prefix + '_f3').value || 1);
        
        let total = q;
        if(chain.length > 1) total *= f2;
        if(chain.length > 2) total *= f3;

        const display = document.getElementById(prefix + '_base_result');
        if(display) {
            display.innerText = `${total.toLocaleString()} ${chain[chain.length-1].name}`;
        }
    }

    function calculateTotal() {
        const qty = parseFloat(document.getElementById('restock_qty').value) || 0;
        const buyPrice = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        
        const totalAmount = qty * buyPrice;
        const totalDisplay = document.getElementById('total_bill_display');
        const settlementInput = document.getElementById('amount_paid');
        const surplusMsgPanel = document.getElementById('restock_dealer_surplus_msg');
        const surplusDisplay = document.getElementById('surplus_amount');
        
        let finalRequired = totalAmount;
        let surplusUsed = 0;

        // Surplus Logic (Negative balance means credit is available)
        if (currentDealerBalance < 0) {
            const availableSurplus = Math.abs(currentDealerBalance);
            surplusMsgPanel.classList.remove('hidden');
            surplusDisplay.innerText = 'Rs. ' + availableSurplus.toLocaleString();
            
            if (availableSurplus >= totalAmount) {
                surplusUsed = totalAmount;
                finalRequired = 0;
            } else {
                surplusUsed = availableSurplus;
                finalRequired = totalAmount - availableSurplus;
            }
        } else {
            surplusMsgPanel.classList.add('hidden');
        }

        // Update UI
        if (totalDisplay) {
            totalDisplay.innerText = Math.round(finalRequired).toLocaleString();
            // Highlight if surplus is helping
            if (surplusUsed > 0) {
                totalDisplay.classList.add('text-yellow-300');
            } else {
                totalDisplay.classList.remove('text-yellow-300');
            }
        }

        // Auto-populate settlement (User can still change it but default to full)
        if (settlementInput) {
            settlementInput.value = Math.round(finalRequired);
        }
    }

    function validateAndSubmit() {
        const buy = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const sell = parseFloat(document.getElementById('restock_sell_price').value) || 0;
        const qty = parseFloat(document.getElementById('restock_qty').value) || 0;
        
        if (buy > sell) {
            alert("Error: New Buy Rate cannot exceed Sell Rate. Loss entries are restricted.");
            return;
        }
        
        if (qty <= 0) {
            alert("Please enter a valid quantity of inventory to add.");
            return;
        }

        // Overpayment Double Check
        const totalAmount = qty * buy;
        let maxAllowed = totalAmount;
        if (currentDealerBalance < 0) {
            maxAllowed = Math.max(0, totalAmount - Math.abs(currentDealerBalance));
        }

        const enteredPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
        if (enteredPaid > Math.ceil(maxAllowed) + 1) { // 1 rupee tolerance for rounding
            alert("Overpayment detected. You cannot pay more than Rs. " + Math.round(maxAllowed).toLocaleString() + " for this batch.");
            return;
        }

        document.getElementById('restock_form').submit();
    }

    /**
     * Stock Formatter (JS Version)
     */
    function formatStockHierarchyJS(qty, p) {
        qty = parseFloat(qty);
        const unitName = p.unit || 'Units';
        if (qty <= 0) return `0 ${unitName}`;

        const chain = getUnitHierarchyJS(unitName);
        if (chain.length <= 1) return `<b>${qty.toLocaleString(undefined, {maximumFractionDigits:2})}</b> <span class="text-[9px] uppercase opacity-70">${unitName}</span>`;

        let remaining = qty;
        let parts = [];
        
        // Multipliers
        const f2 = parseFloat(p.factor_level2 || 1) || 1;
        const f3 = parseFloat(p.factor_level3 || 1) || 1;

        chain.forEach((u, i) => {
            let mult = 1;
            if (i === 0) {
                if (chain.length > 2) mult = f2 * f3;
                else if (chain.length > 1) mult = f2;
            } else if (i === 1) {
                if (chain.length > 2) mult = f3;
            }

            let count = Math.floor(remaining / mult);
            if (count > 0) {
                parts.push(`<b>${count}</b> <span class="text-[9px] uppercase opacity-70">${u.name}</span>`);
                remaining = remaining % mult;
            }
        });

        const baseUnit = chain[chain.length - 1].name;
        let display = parts.length === 0 ? `0 ${unitName}` : parts.join(', ');
        display += ` <span class="text-[9px] text-teal-600 font-black ml-1 tracking-tight italic">[Total: ${qty.toLocaleString(undefined, {maximumFractionDigits:2})} ${baseUnit}]</span>`;
        return display;
    }

    // Modal Events
    document.getElementById('restockModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('restockModal')) closeRestockModal();
    });

    document.getElementById('amount_paid').addEventListener('input', function() {
        const qty = parseFloat(document.getElementById('restock_qty').value) || 0;
        const buy = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const entered = parseFloat(this.value) || 0;
        
        const total = qty * buy;
        let maxPayable = total;
        
        if (currentDealerBalance < 0) {
            maxPayable = Math.max(0, total - Math.abs(currentDealerBalance));
        }
        
        if (entered > Math.ceil(maxPayable) + 1) {
            document.getElementById('overpaymentMsg').innerText = `Maximum settlement for this batch is Rs. ${Math.round(maxPayable).toLocaleString()} after utilizing available surplus.`;
            document.getElementById('overpaymentModal').classList.remove('hidden');
            this.value = Math.round(maxPayable);
        }
    });

    function closeOverpaymentModal() {
        document.getElementById('overpaymentModal').classList.add('hidden');
    }
</script>
