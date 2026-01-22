<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Inventory Management";
include '../includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $price_buy = (float)cleanInput($_POST['buy_price']);
        $price_sell = (float)cleanInput($_POST['sell_price']);
        $qty = (float)cleanInput($_POST['stock_quantity']);
        
        // Validation: Prevent Loss
        if ($price_buy > $price_sell) {
            $error = "Error: Buy Price ($price_buy) cannot be greater than Sell Price ($price_sell).";
        } else {
            $dealer_id = cleanInput($_POST['dealer_id'] ?? '');
            
            // Handle Inline New Dealer Creation
            if ($dealer_id === 'ADD_NEW') {
                $new_dealer_name = cleanInput($_POST['new_dealer_name']);
                if (!empty($new_dealer_name)) {
                    $dealer_data = [
                        'name' => $new_dealer_name,
                        'contact' => '', // Optional info can be skipped for quick add
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $dealer_id = insertCSV('dealers', $dealer_data);
                } else {
                    $dealer_id = ''; // Fallback if name empty
                }
            }

            $amount_paid = (float)cleanInput($_POST['amount_paid'] ?? 0);
            $purchase_date = cleanInput($_POST['purchase_date'] ?? date('Y-m-d'));
            $expiry_date = cleanInput($_POST['expiry_date'] ?? '');
            $remarks = cleanInput($_POST['remarks'] ?? '');

            $data = [
                'name' => cleanInput($_POST['name']),
                'category' => cleanInput($_POST['category']),
                'description' => '',
                'buy_price' => $price_buy,
                'avg_buy_price' => $price_buy, // Initial AVCO matches buy price
                'sell_price' => $price_sell,
                'stock_quantity' => $qty,
                'unit' => cleanInput($_POST['unit']),
                'expiry_date' => $expiry_date,
                'remarks' => $remarks,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Capture the returned New ID from insertCSV
            $new_product_id = insertCSV('products', $data);

            if ($new_product_id) {
                // LOG INITIAL RESTOCK logic (same as before)...
            if ($qty > 0) {
                // Determine Dealer Name
                $dealer_name = "N/A";
                $is_open_market = ($dealer_id === 'OPEN_MARKET');

                if ($is_open_market) {
                    $dealer_name = "Open Market";
                } elseif (!empty($dealer_id)) {
                    $dealers = readCSV('dealers');
                    foreach ($dealers as $d) {
                        if ($d['id'] == $dealer_id) {
                            $dealer_name = $d['name'];
                            break;
                        }
                    }
                }

                $restock_data = [
                    'product_id' => $new_product_id,
                    'product_name' => $data['name'],
                    'quantity' => $qty,
                    'new_buy_price' => $price_buy,
                    'old_buy_price' => 0, // No previous price
                    'new_sell_price' => $data['sell_price'],
                    'old_sell_price' => 0,
                    'dealer_id' => $dealer_id,
                    'dealer_name' => $dealer_name,
                    'dealer_name' => $dealer_name,
                    'amount_paid' => $amount_paid,
                    'date' => $purchase_date,
                    'expiry_date' => $expiry_date, // Log initial expiry
                    'remarks' => $remarks,         // Log initial remarks
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $restock_id = insertCSV('restocks', $restock_data);

                // LOG DEALER TRANSACTION (Only if specific dealer selected)
                if (!empty($dealer_id) && !$is_open_market) {
                    $total_bill = $qty * $price_buy;
                    
                    // Log Single Consolidated Transaction (Purchase & Payment together)
                    $transaction_data = [
                        'dealer_id' => $dealer_id,
                        'date' => $purchase_date,
                        'type' => 'Purchase',
                        'debit' => $total_bill,
                        'credit' => $amount_paid,
                        'description' => "Initial Stock: " . $data['name'] . " ($qty x $price_buy)",
                        'created_at' => date('Y-m-d H:i:s'),
                        'restock_id' => $restock_id
                    ];
                    insertCSV('dealer_transactions', $transaction_data);
                }
            }

            $message = "Product added successfully with Initial Stock logged!";
        } else {
            $error = "Error adding product.";
        }
        }
    } elseif ($_POST['action'] == 'edit') {
        $id = $_POST['id'];
        $data = [
            'name' => cleanInput($_POST['name']),
            'category' => cleanInput($_POST['category']),
            'buy_price' => cleanInput($_POST['buy_price']),
            'sell_price' => cleanInput($_POST['sell_price']),
            'stock_quantity' => cleanInput($_POST['stock_quantity']),
            'sell_price' => cleanInput($_POST['sell_price']),
            'stock_quantity' => cleanInput($_POST['stock_quantity']),
            'unit' => cleanInput($_POST['unit']),
            'expiry_date' => cleanInput($_POST['expiry_date'] ?? ''),
            'remarks' => cleanInput($_POST['remarks'] ?? '')
        ];

        updateCSV('products', $id, $data);
        $message = "Product updated successfully!";
    } elseif ($_POST['action'] == 'delete') {
        $id = $_POST['id'];
        deleteCSV('products', $id);
        $message = "Product deleted successfully!";
    }
}

$products = readCSV('products');
$units = readCSV('units');
$categories = readCSV('categories');

// Filtering
if (isset($_GET['filter']) && $_GET['filter'] == 'low') {
    $products = array_filter($products, function($p) {
        return (int)$p['stock_quantity'] < 10;
    });
} elseif (isset($_GET['filter']) && $_GET['filter'] == 'expiring') {
    $notify_days = (int)getSetting('expiry_notify_days', '7');
    $threshold = date('Y-m-d', strtotime("+$notify_days days"));
    $products = array_filter($products, function($p) use ($threshold) {
        return !empty($p['expiry_date']) && $p['expiry_date'] <= $threshold && $p['expiry_date'] >= date('Y-m-d');
    });
}

// Reverse sort to show newest first
usort($products, function($a, $b) {
    return $b['id'] - $a['id'];
});
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
    <div class="relative w-full max-w-md">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="inventorySearch" autofocus placeholder="Search inventory..." class="w-full pl-10 pr-4 py-2 rounded-lg border focus:ring-2 focus:ring-teal-500 focus:outline-none">
    </div>
    <button onclick="document.getElementById('addProductModal').classList.remove('hidden')" class="bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 transition flex items-center shadow-lg w-full md:w-auto justify-center">
        <i class="fas fa-plus mr-2"></i> Add Product
    </button>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?= $error ?></div>
<?php endif; ?>

<!-- Inventory Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-teal-700 text-white text-sm uppercase tracking-wider">
                    <th class="p-4 w-12 text-center">Sr #</th>
                    <th class="p-4">Product Name</th>
                    <th class="p-4">Category</th>
                    <th class="p-4">Stock</th>
                    <th class="p-4 text-right">Buy Price</th>
                    <th class="p-4 text-right">Sell Price</th>
                    <th class="p-4 text-right bg-teal-800">Total Cost</th>
                    <th class="p-4 text-right bg-teal-900">Est. Profit</th>
                    <th class="p-4 text-right bg-emerald-900 text-emerald-100">Profit (AVCO)</th>
                    <th class="p-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $grand_total_cost = 0;
                $grand_total_profit = 0;
                $sn = 1;
                if (count($products) > 0): 
                    foreach ($products as $product): 
                        $buy = (float)$product['buy_price'];
                        $sell = (float)$product['sell_price'];
                        $qty = (float)$product['stock_quantity'];
                        $stock_val = $buy * $qty;
                        $total_profit = ($sell - $buy) * $qty;
                        
                        // Weighted Average Profit Logic
                        $avco_cost = isset($product['avg_buy_price']) ? (float)$product['avg_buy_price'] : $buy;
                        $avco_profit_unit = $sell - $avco_cost;
                        $avco_profit_total = $avco_profit_unit * $qty;

                        $grand_total_cost += $stock_val;
                        $grand_total_profit += $total_profit;
                        $grand_total_profit_avco = ($grand_total_profit_avco ?? 0) + $avco_profit_total;
                ?>
                        <tr class="hover:bg-gray-50 transition border-b">
                            <td class="p-4 text-gray-400 font-mono text-xs text-center"><?= $sn++ ?></td>
                            <td class="p-4 font-bold text-gray-800">
                                <?= htmlspecialchars($product['name']) ?>
                                <?php if(!empty($product['expiry_date'])): ?>
                                    <div class="text-[10px] text-red-500 font-normal mt-1"><i class="far fa-calendar-alt mr-1"></i>Exp: <?= htmlspecialchars($product['expiry_date']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600 border border-gray-200">
                                    <?= htmlspecialchars($product['category']) ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <span class="font-bold <?= $qty < 5 ? 'text-red-600' : 'text-gray-700' ?>">
                                    <?= $qty ?> <span class="text-[10px] font-normal uppercase"><?= $product['unit'] ?></span>
                                </span>
                            </td>
                            <td class="p-4 text-right text-gray-600 text-sm"><?= formatCurrency($buy) ?></td>
                            <td class="p-4 text-right font-bold text-teal-700 text-sm"><?= formatCurrency($sell) ?></td>
                            <td class="p-4 text-right font-mono font-bold text-gray-700 bg-gray-50/50"><?= formatCurrency($stock_val) ?></td>
                            <td class="p-4 text-right font-mono font-bold text-green-600 bg-green-50/30"><?= formatCurrency($total_profit) ?></td>
                            <td class="p-4 text-right font-mono font-bold text-emerald-600 bg-emerald-50/30 border-l border-emerald-100">
                                <?= formatCurrency($avco_profit_total) ?>
                                <div class="text-[9px] text-gray-400 font-normal">@ <?= number_format($avco_cost, 2) ?> avg</div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex justify-center space-x-2">
                                    <button onclick='openRestockModal(<?= json_encode($product) ?>)' class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-600 hover:text-white transition flex items-center justify-center shadow-sm" title="Restock">
                                        <i class="fas fa-plus-circle text-xs"></i>
                                    </button>
                                    <button onclick='openEditModal(<?= json_encode($product) ?>)' class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition flex items-center justify-center shadow-sm" title="Edit">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button onclick="confirmDelete(<?= $product['id'] ?>)" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition flex items-center justify-center shadow-sm" title="Delete">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="p-12 text-center text-gray-400">
                            <i class="fas fa-box-open text-5xl mb-4 text-gray-200"></i><br>
                            No products found. Start by adding one.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (count($products) > 0): ?>
            <tfoot class="bg-gray-800 text-white font-bold">
                <tr>
                    <td colspan="6" class="p-4 text-right uppercase tracking-wider text-xs">Grand Inventory Totals:</td>
                    <td class="p-4 text-right font-mono"><?= formatCurrency($grand_total_cost) ?></td>
                    <td class="p-4 text-right font-mono text-green-400"><?= formatCurrency($grand_total_profit) ?></td>
                    <td class="p-4 text-right font-mono text-emerald-400 border-l border-gray-700"><?= formatCurrency($grand_total_profit_avco ?? 0) ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 transform transition-all scale-100">
        <div class="flex justify-between items-center mb-6 border-b pb-2">
            <h3 class="text-xl font-bold text-gray-800">Add New Product</h3>
            <button onclick="document.getElementById('addProductModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="space-y-3">
                <!-- Row 1: Basic Info -->
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-6">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Product Name</label>
                        <input type="text" name="name" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 focus:border-teal-500 text-sm placeholder-gray-400" placeholder="e.g. Urea 50kg">
                    </div>
                    <div class="col-span-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Category</label>
                        <select name="category" onchange="checkDropdown(this)" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-sm">
                            <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ADD_NEW" class="text-teal-600 font-bold">+ Add New</option>
                        </select>
                    </div>
                    <div class="col-span-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit</label>
                        <select name="unit" onchange="checkDropdown(this)" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-sm">
                            <?php foreach($units as $u): ?>
                            <option value="<?= htmlspecialchars($u['name']) ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ADD_NEW" class="text-teal-600 font-bold">+ Add New</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Pricing involved -->
                <div class="grid grid-cols-2 gap-3 bg-gray-50 p-2 rounded-lg border border-gray-100">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Buy Price</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1.5 text-gray-400 text-xs">Rs.</span>
                            <input type="number" step="0.01" name="buy_price" id="add_buy_price" required class="w-full rounded-lg border-gray-300 border p-1.5 pl-8 focus:ring-teal-500 text-sm font-semibold">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sell Price</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1.5 text-gray-400 text-xs">Rs.</span>
                            <input type="number" step="0.01" name="sell_price" required class="w-full rounded-lg border-gray-300 border p-1.5 pl-8 focus:ring-teal-500 text-sm font-semibold">
                        </div>
                    </div>
                </div>
                
                <!-- Row 3: Stock & Dates -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Initial Stock</label>
                        <input type="number" name="stock_quantity" id="add_stock_qty" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-sm font-bold text-gray-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Purchase Date</label>
                        <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-xs">
                    </div>
                     <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Expiry <span class="text-[9px] text-gray-400 normal-case">(Opt)</span></label>
                        <input type="date" name="expiry_date" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-xs">
                    </div>
                </div>

                <!-- Row 4: Remarks -->
                <div>
                    <input type="text" name="remarks" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-sm" placeholder="Remarks (optional)...">
                </div>

                <!-- Row 5: Supplier / Payment -->
                <div class="p-3 bg-teal-50 rounded-lg border border-teal-100">
                    <div class="flex justify-between items-center mb-2">
                         <h4 class="text-xs font-bold text-teal-800 uppercase">Supplier Details</h4>
                         <span class="text-[10px] text-teal-600 bg-white px-2 py-0.5 rounded border border-teal-200">Optional</span>
                    </div>
                    
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-6">
                             <label class="block text-[10px] font-bold text-teal-700 uppercase mb-1">Select Dealer</label>
                             <select name="dealer_id" id="add_dealer_select" onchange="toggleNewDealerInput(this)" class="w-full rounded border-teal-200 border p-1.5 focus:ring-teal-500 text-xs bg-white">
                                <option value="OPEN_MARKET">Open Market</option>
                                <?php 
                                $dealers_list_add = readCSV('dealers');
                                foreach($dealers_list_add as $dlr): 
                                ?>
                                <option value="<?= $dlr['id'] ?>"><?= htmlspecialchars($dlr['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="ADD_NEW" class="font-bold text-teal-600">+ Add New</option>
                            </select>
                            
                            <!-- Hidden Input for New Dealer -->
                            <div id="new_dealer_input_container" class="mt-2 hidden">
                                <input type="text" name="new_dealer_name" id="new_dealer_name" class="w-full rounded border-teal-300 border p-1.5 focus:ring-teal-500 text-xs" placeholder="New Dealer Name">
                            </div>
                        </div>
                        <div class="col-span-3">
                             <label class="block text-[10px] font-bold text-teal-700 uppercase mb-1">Total Bill</label>
                             <div id="add_total_bill" class="font-mono font-bold text-teal-900 text-sm pt-1">Rs. 0</div>
                        </div>
                        <div class="col-span-3">
                            <label class="block text-[10px] font-bold text-teal-700 uppercase mb-1">Paid Amount</label>
                            <input type="number" step="0.01" name="amount_paid" id="add_amount_paid" placeholder="0" class="w-full rounded border-teal-200 border p-1.5 focus:ring-teal-500 text-xs font-mono">
                        </div>
                    </div>
                </div>
            </div>

                <div class="mt-5 grid grid-cols-2 gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="document.getElementById('addProductModal').classList.add('hidden')" class="py-2.5 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 font-bold transition">Cancel</button>
                    <button type="submit" class="py-2.5 rounded-lg bg-teal-600 text-white hover:bg-teal-700 font-bold shadow-lg shadow-teal-500/30 transition">Save Product</button>
                </div>
            </form>
        </div>
    </div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 transform transition-all scale-100">
        <div class="flex justify-between items-center mb-6 border-b pb-2">
            <h3 class="text-xl font-bold text-gray-800">Edit Product</h3>
            <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 focus:border-teal-500">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" id="edit_category" onchange="checkDropdown(this)" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                            <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ADD_NEW" class="text-teal-600 font-bold">+ Add Category</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <select name="unit" id="edit_unit" onchange="checkDropdown(this)" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                            <?php foreach($units as $u): ?>
                            <option value="<?= htmlspecialchars($u['name']) ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ADD_NEW" class="text-teal-600 font-bold">+ Add Unit</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Buy Price</label>
                        <input type="number" step="0.01" name="buy_price" id="edit_buy_price" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sell Price</label>
                        <input type="number" step="0.01" name="sell_price" id="edit_sell_price" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stock Quantity</label>
                    <input type="number" name="stock_quantity" id="edit_stock_quantity" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                </div>
                
                 <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                        <input type="date" name="expiry_date" id="edit_expiry_date" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                    </div>
                    <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                         <input type="text" name="remarks" id="edit_remarks" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700 shadow-md">Update Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Restock Product Modal -->
<div id="restockProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center backdrop-blur-sm">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
        <div class="bg-orange-600 p-6 flex justify-between items-center text-white">
            <div>
                <h3 class="text-xl font-bold">Restock Product</h3>
                <p class="text-xs opacity-80" id="restock_product_name_display"></p>
            </div>
            <button onclick="closeRestockModal()" class="hover:bg-orange-700 p-2 rounded-full transition">&times;</button>
        </div>
        
        <form method="POST" action="../actions/restock_process.php" class="p-8">
            <input type="hidden" name="product_id" id="restock_id">
            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Quantity to Add</label>
                        <input type="number" step="0.01" name="quantity" required class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition font-bold text-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Purchase Date</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Expiry Date <span class="text-[9px] lowercase font-normal">(opt)</span></label>
                        <input type="date" name="expiry_date" id="restock_expiry_date" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Remarks <span class="text-[9px] lowercase font-normal">(opt)</span></label>
                         <input type="text" name="remarks" id="restock_remarks" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">New Buy Price</label>
                        <input type="number" step="0.01" name="new_buy_price" id="restock_buy_price" required class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">New Sell Price</label>
                        <input type="number" step="0.01" name="new_sell_price" id="restock_sell_price" required class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Supplier / Dealer</label>
                    <select name="dealer_id" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition">
                        <option value="OPEN_MARKET">Open Market (Default)</option>
                        <?php 
                        $dealers_list = readCSV('dealers');
                        foreach($dealers_list as $dlr): 
                        ?>
                        <option value="<?= $dlr['id'] ?>"><?= htmlspecialchars($dlr['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-4 bg-orange-50 rounded-2xl border border-orange-100 flex justify-between items-center">
                    <span class="text-xs font-bold text-orange-800 uppercase">Total Bill Amount:</span>
                    <span id="restock_total_amount" class="text-xl font-black text-orange-600">Rs. 0</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Paid to Dealer (Cash)</label>
                    <input type="number" step="0.01" name="amount_paid" id="restock_amount_paid" value="0" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition text-green-600 font-bold">
                </div>
            </div>

            <div class="mt-8 flex gap-3">
                <button type="button" onclick="closeRestockModal()" class="flex-1 py-4 bg-gray-100 text-gray-600 rounded-2xl font-bold hover:bg-gray-200 transition">Cancel</button>
                <button type="button" onclick="validateAndSubmitRestock()" class="flex-1 py-4 bg-orange-600 text-white rounded-2xl font-bold hover:bg-orange-700 shadow-lg shadow-orange-900/20 transition active:scale-95">Complete Restock</button>
            </div>
        </form>
    </div>
</div>

<script>
    function validateAndSubmitRestock() {
        const buyPrice = parseFloat(document.getElementById('restock_buy_price').value) || 0;
        const sellPrice = parseFloat(document.getElementById('restock_sell_price').value) || 0;
        
        if (buyPrice > sellPrice) {
            showAlert(`Buy Price (${buyPrice}) cannot be greater than Sell Price (${sellPrice}). Please adjust the prices.`, 'Pricing Error');
            return;
        }
        
        // If valid, submit the form found inside the modal
        document.querySelector('#restockProductModal form').submit();
    }
</script>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" action="" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function openEditModal(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_category').value = product.category;
    document.getElementById('edit_unit').value = product.unit;
    document.getElementById('edit_buy_price').value = product.buy_price;
    document.getElementById('edit_sell_price').value = product.sell_price;
    document.getElementById('edit_stock_quantity').value = product.stock_quantity;
    document.getElementById('edit_expiry_date').value = product.expiry_date || '';
    document.getElementById('edit_remarks').value = product.remarks || '';
    
    document.getElementById('editProductModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editProductModal').classList.add('hidden');
}

function openRestockModal(product) {
    document.getElementById('restock_id').value = product.id;
    document.getElementById('restock_product_name_display').innerText = product.name;
    document.getElementById('restock_buy_price').value = product.buy_price;
    document.getElementById('restock_sell_price').value = product.sell_price;
    
    // Clear quantity and amount paid for fresh entry
    document.querySelector('input[name="quantity"]').value = '';
    document.getElementById('restock_expiry_date').value = ''; // Clear for fresh entry
    document.getElementById('restock_remarks').value = '';     // Clear for fresh entry
    document.getElementById('restock_amount_paid').value = '0';
    document.getElementById('restock_total_amount').innerText = 'Rs. 0';
    
    document.getElementById('restockProductModal').classList.remove('hidden');
}

function calculateRestockTotal() {
    const qty = parseFloat(document.querySelector('input[name="quantity"]').value) || 0;
    const price = parseFloat(document.getElementById('restock_buy_price').value) || 0;
    const total = qty * price;
    
    document.getElementById('restock_total_amount').innerText = 'Rs. ' + total.toLocaleString();
    // Suggest the paid amount to be the total bill
    document.getElementById('restock_amount_paid').value = total;
}

function updateAddProductTotal() {
    const qty = parseFloat(document.getElementById('add_stock_qty').value) || 0;
    const price = parseFloat(document.getElementById('add_buy_price').value) || 0;
    
    const total = qty * price;
    const totalDisplay = document.getElementById('add_total_bill');
    const paidInput = document.getElementById('add_amount_paid');
    
    if (totalDisplay) totalDisplay.innerText = 'Rs. ' + total.toLocaleString();
    if (paidInput) paidInput.value = total;
}

function toggleNewDealerInput(select) {
    const container = document.getElementById('new_dealer_input_container');
    const input = document.getElementById('new_dealer_name');
    
    if (select.value === 'ADD_NEW') {
        if(container) container.classList.remove('hidden');
        if(input) {
            input.required = true;
            input.focus();
        }
    } else {
        if(container) container.classList.add('hidden');
        if(input) {
            input.required = false;
            input.value = '';
        }
    }
}

// Add event listeners for calculation
document.addEventListener('DOMContentLoaded', function() {
    // Restock Modal
    const rQty = document.querySelector('#restockProductModal input[name="quantity"]');
    const rPrice = document.getElementById('restock_buy_price');
    if (rQty) rQty.addEventListener('input', calculateRestockTotal);
    if (rPrice) rPrice.addEventListener('input', calculateRestockTotal);

    // Add Product Modal
    const aQty = document.getElementById('add_stock_qty');
    const aPrice = document.getElementById('add_buy_price');
    if (aQty) aQty.addEventListener('input', updateAddProductTotal);
    if (aPrice) aPrice.addEventListener('input', updateAddProductTotal);
});

function closeRestockModal() {
    document.getElementById('restockProductModal').classList.add('hidden');
}

function confirmDelete(id) {
    showConfirm('Are you sure you want to delete this product? This action cannot be undone.', () => {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }, 'Delete Product');
}

function checkDropdown(select) {
    if (select.value === 'ADD_NEW') {
        if (select.name === 'category') {
            window.location.href = 'categories.php';
        } else if (select.name === 'unit') {
            window.location.href = 'units.php';
        }
    }
}

// Search functionality
document.getElementById('inventorySearch').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const nameNode = row.querySelector('td:nth-child(2)');
        if (!nameNode) return; // Skip footer or header if any
        
        const name = nameNode.textContent.toLowerCase();
        const cat = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        
        if (name.includes(term) || cat.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

document.getElementById('inventorySearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const visibleRows = document.querySelectorAll('tbody tr:not([style*="display: none"])');
        if (visibleRows.length > 0) {
            const editBtn = visibleRows[0].querySelector('button[title="Edit"]');
            if (editBtn) editBtn.click();
        }
    }
});
</script>

<?php 
include '../includes/footer.php';
echo '</main></div></body></html>'; 
?>
