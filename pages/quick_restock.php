<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireLogin();
if (!hasPermission('add_restock')) die("Unauthorized Access");

include '../includes/header.php';

$dealers = readCSV('dealers');
$products = readCSV('products');

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
                 onclick='openRestockModal(<?= json_encode($p) ?>)'>
                
                <div class="flex justify-between items-start mb-4">
                    <div class="p-4 bg-teal-50 text-teal-600 rounded-2xl group-hover:bg-teal-600 group-hover:text-white transition-colors">
                        <i class="fas fa-box text-2xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 block mb-1">Stock Level</span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $p['stock_quantity'] < 10 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>">
                            <?= htmlspecialchars($p['stock_quantity']) ?> <?= htmlspecialchars($p['unit'] ?? 'Units') ?>
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

<!-- Restock Modal -->
<div id="restockModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[9999] flex items-center justify-center p-2 sm:p-4">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-2xl max-h-[95vh] transform transition-all animate-in zoom-in fade-in duration-200 overflow-y-auto custom-scrollbar">
        <!-- Modal Header -->
        <div class="bg-teal-600 p-6 sm:p-8 text-white flex justify-between items-center sticky top-0 z-30">
            <div>
                <h3 class="text-xl sm:2xl font-black tracking-tight" id="modal_product_name">Add Stock</h3>
                <p class="text-teal-100 text-xs sm:sm mt-1" id="modal_product_category">Product Category</p>
            </div>
            <button onclick="closeRestockModal()" class="w-10 h-10 sm:w-12 sm:h-12 bg-white/10 hover:bg-white/20 rounded-2xl flex items-center justify-center transition-colors">
                <i class="fas fa-times text-lg sm:xl"></i>
            </button>
        </div>

        <form method="POST" action="../actions/restock_process.php" class="p-6 sm:p-8">
            <input type="hidden" name="product_id" id="form_product_id">
            
            <!-- Quick Summary Panel -->
            <div class="grid grid-cols-3 gap-4 sm:gap-6 bg-gray-50 p-4 sm:p-6 rounded-3xl border border-gray-100 mb-6 sm:mb-8">
                <div>
                    <span class="text-[9px] sm:text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Current Stock</span>
                    <span class="text-lg sm:text-xl font-black text-gray-800" id="current_stock_display">-</span>
                </div>
                <div>
                    <span class="text-[9px] sm:text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Old Buy Price</span>
                    <span class="text-lg sm:text-xl font-black text-gray-800" id="current_buy_display">-</span>
                </div>
                <div>
                    <span class="text-[9px] sm:text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Old Sell Price</span>
                    <span class="text-lg sm:text-xl font-black text-teal-600" id="current_sell_display">-</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Quantity to Add</label>
                        <input type="number" step="0.01" name="quantity" id="restock_qty" required 
                               class="w-full p-4 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-teal-500 focus:bg-white transition-all outline-none text-lg font-bold"
                               oninput="calculateTotal()">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">New Buy Rate</label>
                        <input type="number" step="0.01" name="new_buy_price" id="restock_buy_price" required 
                               class="w-full p-4 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-teal-500 focus:bg-white transition-all outline-none font-bold"
                               oninput="calculateTotal()">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">New Selling Rate</label>
                        <input type="number" step="0.01" name="new_sell_price" id="restock_sell_price" required 
                               class="w-full p-4 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-teal-500 focus:bg-white transition-all outline-none font-bold text-teal-600">
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Supplier / Dealer</label>
                        <select name="dealer_id" id="dealer_select" onchange="toggleNewDealerInput(this)" 
                                class="w-full p-3.5 sm:p-4 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-teal-500 focus:bg-white outline-none font-bold appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1.2em_1.2em]"
                                style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23999%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E');">
                            <option value="OPEN_MARKET">Open Market (Default)</option>
                            <?php foreach($dealers as $dlr): ?>
                            <option value="<?= $dlr['id'] ?>"><?= htmlspecialchars($dlr['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ADD_NEW" class="text-teal-600 font-bold">+ Create New Dealer</option>
                        </select>
                        <div id="new_dealer_input_container" class="mt-2 hidden">
                            <input type="text" name="new_dealer_name" id="new_dealer_name" class="w-full rounded-xl border-teal-200 border-2 p-3 sm:p-3.5 focus:border-teal-500 outline-none text-sm font-medium" placeholder="Enter Dealer Name">
                        </div>
                        <div id="restock_dealer_surplus_msg" class="hidden mt-2 text-[10px] font-bold text-green-600 bg-green-50 p-2 rounded-xl border border-green-100">
                             Surplus Available: <span class="font-black">Rs. 0</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5 ml-1">Purchase Date</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full p-3.5 sm:p-4 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-teal-500 focus:bg-white outline-none font-medium">
                    </div>
                </div>
            </div>

            <!-- Remarks Field -->
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2 ml-1">Remarks (Optional)</label>
                <input type="text" name="remarks" id="restock_remarks" 
                       class="w-full p-4 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-teal-500 focus:bg-white transition-all outline-none font-medium text-gray-700"
                       placeholder="Enter any notes or remarks here...">
            </div>

            <!-- Footer Section -->
            <div class="flex flex-col md:flex-row items-center gap-6 mt-6 sm:mt-10 pt-6 sm:pt-8 border-t border-gray-100">
                <div class="flex-1 w-full p-5 sm:p-6 bg-teal-50 rounded-3xl border border-teal-100">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-[9px] sm:text-[10px] font-black text-teal-800 uppercase tracking-widest block mb-1">Subtotal Bill</span>
                            <span id="total_bill_display" class="text-2xl sm:text-3xl font-black text-teal-600">Rs. 0</span>
                        </div>
                        <div class="text-right">
                             <label class="block text-[9px] sm:text-[10px] font-black text-teal-800 uppercase tracking-widest mb-1">Amount Paid</label>
                             <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="w-28 sm:w-32 p-2.5 sm:p-3 bg-white border-2 border-teal-200 rounded-2xl focus:border-teal-500 text-teal-700 font-bold text-right outline-none">
                        </div>
                    </div>
                </div>
                <button type="button" onclick="validateAndSubmit()" class="w-full md:w-auto px-10 sm:px-12 py-5 sm:py-6 bg-teal-600 text-white rounded-3xl font-black text-lg hover:bg-teal-700 shadow-xl shadow-teal-900/20 transition-all hover:scale-105 active:scale-95 flex items-center justify-center gap-3">
                    <i class="fas fa-check-circle"></i>
                    <span>CONFIRM RESTOCK</span>
                </button>
                </div>
</div>

<!-- Overpayment Warning Modal -->
<div id="overpaymentModal" class="fixed inset-0 bg-black/50 hidden z-[10000] flex items-center justify-center backdrop-blur-sm p-4">
    <div class="bg-white rounded-3xl p-6 shadow-2xl max-w-sm w-full text-center transform transition-all scale-100 animate-in zoom-in duration-200">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-hand-paper text-3xl text-red-600"></i>
        </div>
        <h3 class="text-xl font-black text-gray-800 mb-2">Overpayment Warning</h3>
        <p class="text-sm text-gray-600 font-medium mb-6 leading-relaxed" id="overpaymentMsg">
            You cannot pay more than the Net Payable amount.
        </p>
        <button type="button" onclick="closeOverpaymentModal()" class="w-full py-3.5 bg-red-600 text-white font-bold rounded-2xl hover:bg-red-700 shadow-lg shadow-red-500/30 transition active:scale-95">
            Understood, will correct
        </button>
    </div>
</div>
        </form>
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

    function openRestockModal(product) {
        document.getElementById('modal_product_name').innerText = product.name;
        document.getElementById('modal_product_category').innerText = product.category;
        document.getElementById('form_product_id').value = product.id;
        
        document.getElementById('current_stock_display').innerText = `${product.stock_quantity} ${product.unit || 'Units'}`;
        document.getElementById('current_buy_display').innerText = 'Rs. ' + parseInt(product.buy_price).toLocaleString();
        document.getElementById('current_sell_display').innerText = 'Rs. ' + parseInt(product.sell_price).toLocaleString();
        
        document.getElementById('restock_buy_price').value = product.buy_price;
        document.getElementById('restock_sell_price').value = product.sell_price;
        document.getElementById('restock_qty').value = '';
        document.getElementById('restock_remarks').value = '';
        document.getElementById('amount_paid').value = '0';
        document.getElementById('total_bill_display').innerText = 'Rs. 0';

        document.getElementById('restockModal').classList.remove('hidden');
        document.getElementById('restockModal').classList.add('flex');
        setTimeout(() => document.getElementById('restock_qty').focus(), 100);
    }

    function closeRestockModal() {
        document.getElementById('restockModal').classList.add('hidden');
        document.getElementById('restockModal').classList.remove('flex');
    }

    function toggleNewDealerInput(select) {
        const container = document.getElementById('new_dealer_input_container');
        const input = document.getElementById('new_dealer_name');

        if (select.value === 'ADD_NEW') {
            container.classList.remove('hidden');
            input.required = true;
            input.focus();
        } else {
            container.classList.add('hidden');
            input.required = false;
        }
        // Trigger Ajax Fetch
        fetchDealerBalance(select.value);
    }
    
    let currentDealerBalance = 0;
    
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
                console.error(e);
                currentDealerBalance = 0;
                calculateTotal();
            });
    }

    function calculateTotal() {
        const qty = parseFloat(document.getElementById('restock_qty').value) || 0;
        const price = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const total = qty * price;
        const totalDisplay = document.getElementById('total_bill_display');
        const paidInput = document.getElementById('amount_paid');
        const surplusMsg = document.getElementById('restock_dealer_surplus_msg');
        
        let finalPayable = total;
        let surplusUsed = 0;
        
        // Match logic from inventory.php
        if (currentDealerBalance < 0) {
            const surplus = Math.abs(currentDealerBalance);
            
            if (surplusMsg) {
                surplusMsg.innerHTML = `Avail. Surplus: <span class="font-black">Rs. ${surplus.toLocaleString()}</span>`;
                surplusMsg.classList.remove('hidden');
            }
            
            if (surplus >= total) {
                surplusUsed = total;
                finalPayable = 0;
            } else {
                surplusUsed = surplus;
                finalPayable = total - surplus;
            }
            
            if (totalDisplay) {
                 totalDisplay.innerHTML = `
                    <div class="flex flex-col items-start">
                        <span class="line-through text-gray-400 text-xs">Rs. ${Math.round(total).toLocaleString()}</span>
                        <span class="text-2xl sm:text-3xl font-black text-teal-600">Rs. ${Math.round(finalPayable).toLocaleString()}</span>
                        <span class="text-[9px] text-green-600 font-bold bg-green-50 px-1 rounded border border-green-100 uppercase tracking-wide mt-1">(-${Math.round(surplusUsed).toLocaleString()} Surplus)</span>
                    </div>
                 `;
            }
        } else {
            if (surplusMsg) surplusMsg.classList.add('hidden');
            if (totalDisplay) totalDisplay.innerText = 'Rs. ' + Math.round(total).toLocaleString();
        }

        if(paidInput) paidInput.value = Math.round(finalPayable);
    }

    function validateAndSubmit() {
        const buy = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const sell = parseFloat(document.getElementById('restock_sell_price').value) || 0;
        
        if (buy > sell) {
            showAlert("Error: Buy Price cannot be greater than Sell Price. Business security prevents entering losses.", "Warning");
            return;
        }
        
        if (parseFloat(document.getElementById('restock_qty').value) <= 0 || !document.getElementById('restock_qty').value) {
            showAlert("Please enter a valid quantity to add.", "Invalid Quantity");
            return;
        }
        
        // Overpayment Protection
        const qty = parseFloat(document.getElementById('restock_qty').value) || 0;
        const price = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const enteredPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
        
        const totalBill = qty * price;
        let maxPayable = totalBill;
        
        // Recalculate max payable based on surplus
        if (currentDealerBalance < 0) {
            const surplus = Math.abs(currentDealerBalance);
            if (surplus >= totalBill) {
                maxPayable = 0;
            } else {
                maxPayable = totalBill - surplus;
            }
        }
        
        if (enteredPaid > Math.ceil(maxPayable)) { 
            showAlert(`You cannot pay more than the Net Bill Amount (Rs. ${Math.round(maxPayable).toLocaleString()}). <br>Surplus has already covered part/all of the bill.`, "Overpayment Error");
            return;
        }

        document.querySelector('#restockModal form').submit();
    }

    // Modal Close on click outside
    document.getElementById('restockModal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('restockModal')) {
            closeRestockModal();
        }
    });

    function closeOverpaymentModal() {
        document.getElementById('overpaymentModal').classList.add('hidden');
    }

    // Realtime Overpayment Check
    document.getElementById('amount_paid').addEventListener('input', function() {
        const qty = parseFloat(document.getElementById('restock_qty').value) || 0;
        const price = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const entered = parseFloat(this.value) || 0;
        
        const total = qty * price;
        let maxPayable = total;
        
        if (currentDealerBalance < 0) {
            const surplus = Math.abs(currentDealerBalance);
            maxPayable = Math.max(0, total - surplus);
        }
        
        // Allow a small epsilon for floating point, but strict enough for currency
        if (entered > Math.ceil(maxPayable)) {
            document.getElementById('overpaymentMsg').innerHTML = `
                Total Bill: <span class="font-bold">Rs. ${Math.round(total)}</span><br>
                Surplus Used: <span class="font-bold text-green-600">Rs. ${Math.round(total - maxPayable)}</span><br>
                <hr class="my-2 border-gray-100">
                Max Payable: <span class="font-black text-red-600 text-lg">Rs. ${Math.ceil(maxPayable)}</span>
            `;
            document.getElementById('overpaymentModal').classList.remove('hidden');
            
            // Auto-correct
            this.value = Math.ceil(maxPayable);
        }
    });
</script>
