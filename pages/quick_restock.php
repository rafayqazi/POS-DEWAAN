<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
requireLogin();

include '../includes/header.php';

$dealers = readCSV('dealers');
$products = readCSV('products');
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Quick Restock</h2>
        <a href="inventory.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg shadow transition">
            <i class="fas fa-arrow-left mr-2"></i> Back to Inventory
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-100">
        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2 uppercase">Select Product to Restock</label>
            <select id="product_search" class="w-full p-4 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 text-lg" onchange="loadProductDetails(this.value)">
                <option value="">-- Search Product --</option>
                <?php foreach($products as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>" 
                        data-buy="<?= htmlspecialchars($p['buy_price']) ?>"
                        data-sell="<?= htmlspecialchars($p['sell_price']) ?>"
                        data-stock="<?= htmlspecialchars($p['stock_quantity']) ?>">
                    <?= htmlspecialchars($p['name']) ?> (Current Stock: <?= htmlspecialchars($p['stock_quantity']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="restock_form_container" class="hidden animate-fade-in-up">
            <form method="POST" action="../actions/restock_process.php" class="space-y-6">
                <input type="hidden" name="product_id" id="form_product_id">
                
                <!-- Current Info -->
                <div class="grid grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div>
                        <span class="block text-xs text-gray-500 uppercase">Current Stock</span>
                        <span class="text-xl font-bold text-gray-800" id="current_stock_display">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-500 uppercase">Current Buy Price</span>
                        <span class="text-xl font-bold text-gray-800" id="current_buy_display">-</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-500 uppercase">Current Sell Price</span>
                        <span class="text-xl font-bold text-gray-800" id="current_sell_display">-</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Quantity to Add</label>
                        <input type="number" step="0.01" name="quantity" id="restock_qty" oninput="calculateTotal()" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 text-lg font-bold">
                    </div>
                     <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Purchase Date</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                         <label class="block text-sm font-bold text-gray-700 mb-2">Expiry Date <span class="text-xs font-normal text-gray-400">(Optional)</span></label>
                         <input type="date" name="expiry_date" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500">
                    </div>
                    <div>
                         <label class="block text-sm font-bold text-gray-700 mb-2">Remarks <span class="text-xs font-normal text-gray-400">(Optional)</span></label>
                         <input type="text" name="remarks" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500" placeholder="Any additional notes...">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">New Buy Price</label>
                        <input type="number" step="0.01" name="new_buy_price" id="restock_buy_price" oninput="calculateTotal()" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">New Sell Price</label>
                        <input type="number" step="0.01" name="new_sell_price" id="restock_sell_price" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                   <div class="col-span-2">
                         <label class="block text-sm font-bold text-gray-700 mb-2">Supplier / Dealer</label>
                         <select name="dealer_id" id="dealer_select" onchange="toggleNewDealerInput(this)" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-teal-500">
                            <option value="OPEN_MARKET">Open Market (Default)</option>
                            <?php foreach($dealers as $dlr): ?>
                            <option value="<?= $dlr['id'] ?>"><?= htmlspecialchars($dlr['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ADD_NEW" class="text-teal-600 font-bold">+ Add New Dealer</option>
                        </select>
                        <!-- Hidden Input for New Dealer -->
                        <div id="new_dealer_input_container" class="mt-2 hidden">
                            <label class="block text-xs font-medium text-gray-600 mb-1">New Dealer Name</label>
                            <input type="text" name="new_dealer_name" id="new_dealer_name" class="w-full rounded-lg border-teal-300 border-2 p-2 focus:ring-teal-500 text-sm" placeholder="Enter Dealer Name">
                        </div>
                    </div>
                </div>
                
                <div class="p-4 bg-orange-50 rounded-xl border border-orange-100 flex justify-between items-center">
                    <div>
                        <span class="block text-xs font-bold text-orange-800 uppercase">Total Bill Amount</span>
                        <span id="total_bill_display" class="text-2xl font-black text-orange-600">Rs. 0</span>
                    </div>
                    <div class="w-1/2">
                         <label class="block text-xs font-bold text-orange-800 uppercase mb-1">Amount Paid to Dealer</label>
                         <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="w-full p-2 border border-orange-200 rounded-lg focus:ring-orange-500 text-right font-bold text-green-700">
                    </div>
                </div>

                <button type="button" onclick="validateAndSubmit()" class="w-full py-4 bg-teal-600 text-white rounded-xl font-bold text-lg hover:bg-teal-700 shadow-lg transition transform active:scale-95">
                    Confirm Restock
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Select2 CSS/JS for better search -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('#product_search').select2({
            placeholder: "Search for a product...",
            allowClear: true
        });
    });

    function loadProductDetails(productId) {
        if(!productId) {
            document.getElementById('restock_form_container').classList.add('hidden');
            return;
        }

        const option = document.querySelector(`#product_search option[value="${productId}"]`);
        const buy = option.getAttribute('data-buy');
        const sell = option.getAttribute('data-sell');
        const stock = option.getAttribute('data-stock');

        // Populate Form
        document.getElementById('form_product_id').value = productId;
        document.getElementById('current_stock_display').innerText = stock;
        document.getElementById('current_buy_display').innerText = 'Rs. ' + buy;
        document.getElementById('current_sell_display').innerText = 'Rs. ' + sell;
        
        document.getElementById('restock_buy_price').value = buy;
        document.getElementById('restock_sell_price').value = sell;
        document.getElementById('restock_qty').value = '';
        document.getElementById('amount_paid').value = '0';
        document.getElementById('total_bill_display').innerText = 'Rs. 0';

        document.getElementById('restock_form_container').classList.remove('hidden');
    }

    // Auto Calculation
    function calculateTotal() {
        const qty = parseFloat(document.getElementById('restock_qty').value) || 0;
        const price = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const total = qty * price;
        
        document.getElementById('total_bill_display').innerText = 'Rs. ' + total.toLocaleString();
        document.getElementById('amount_paid').value = total;
    }

    document.getElementById('restock_qty').addEventListener('input', calculateTotal);
    document.getElementById('restock_buy_price').addEventListener('input', calculateTotal);

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
    }

    function validateAndSubmit() {
        const buy = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const sell = parseFloat(document.getElementById('restock_sell_price').value) || 0;
        
        if (buy > sell) {
            alert("Error: Buy Price cannot be greater than Sell Price.");
            return;
        }
        document.querySelector('form').submit();
    }
</script>
