<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasPermission('add_product')) die("Unauthorized Access");

$message = ''; $error = '';

// 1. AJAX Helper: Get Dealer Balance
if (isset($_GET['action']) && $_GET['action'] == 'get_balance' && isset($_GET['dealer_id'])) {
    $did = $_GET['dealer_id'];
    $txns = readCSV('dealer_transactions');
    $bal = 0;
    foreach($txns as $t) {
        if($t['dealer_id'] == $did) {
             $bal += (float)($t['debit'] ?? 0);
             $bal -= (float)($t['credit'] ?? 0);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['balance' => $bal]);
    exit;
}

// 2. Main POST Handlers
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // ACTION: ADD
    if ($_POST['action'] == 'add') {
        $price_buy = (float)cleanInput($_POST['buy_price']);
        $price_sell = (float)cleanInput($_POST['sell_price']);
        $qty = (float)cleanInput($_POST['stock_quantity']);
        if ($price_buy > $price_sell) $error = "Buy Price cannot be higher than Sell Price.";
        else {
            $dealer_id = cleanInput($_POST['dealer_id'] ?? '');
            if ($dealer_id === 'ADD_NEW') {
                $new_dealer_name = cleanInput($_POST['new_dealer_name']);
                $did = insertCSV('dealers', ['name' => $new_dealer_name, 'created_at' => date('Y-m-d H:i:s')]);
                if($did) $dealer_id = $did;
            }
            $amount_paid = (float)cleanInput($_POST['amount_paid'] ?? 0);
            $unit_id = cleanInput($_POST['unit']);
            $f2 = (float)($_POST['factor_level2'] ?? 1);
            $f3 = (float)($_POST['factor_level3'] ?? 1);
            $multiplier = getBaseMultiplier($unit_id, ['unit' => $unit_id, 'factor_level2' => $f2, 'factor_level3' => $f3]);
            $base_qty = $qty * $multiplier;

            $data = [
                'name' => cleanInput($_POST['name']),
                'category' => cleanInput($_POST['category']),
                'description' => cleanInput($_POST['description'] ?? ''),
                'buy_price' => $price_buy,
                'avg_buy_price' => $price_buy / $multiplier, // Normalized base AVCO
                'sell_price' => $price_sell,
                'stock_quantity' => $base_qty,
                'unit' => $unit_id,
                'factor_level2' => $f2,
                'factor_level3' => $f3,
                'expiry_date' => cleanInput($_POST['expiry_date'] ?? ''),
                'remarks' => cleanInput($_POST['remarks'] ?? ''),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $new_id = insertCSV('products', $data);
            if ($new_id && $qty > 0) {
                $dealer_name = ($dealer_id == 'OPEN_MARKET') ? "Open Market" : (findById('dealers', $dealer_id)['name'] ?? 'Unknown');
                $rid = insertCSV('restocks', ['product_id'=>$new_id,'product_name'=>$data['name'],'quantity'=>$qty,'new_buy_price'=>$price_buy,'old_buy_price'=>0,'new_sell_price'=>$price_sell,'old_sell_price'=>0,'dealer_id'=>$dealer_id,'dealer_name'=>$dealer_name,'amount_paid'=>$amount_paid,'date'=>date('Y-m-d'),'created_at'=>date('Y-m-d H:i:s')]);
                if ($dealer_id !== 'OPEN_MARKET') {
                    insertCSV('dealer_transactions', ['dealer_id'=>$dealer_id,'date'=>date('Y-m-d'),'type'=>'Purchase','debit'=>$qty*$price_buy,'credit'=>$amount_paid,'description'=>"Initial: ".$data['name'],'created_at'=>date('Y-m-d H:i:s'),'restock_id'=>$rid]);
                }
            }
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status' =>'success', 'message' => "Product saved!"]); exit; }
        }
    } 
    // ACTION: EDIT
    elseif ($_POST['action'] == 'edit') {
        $id = $_POST['id']; $qty = (float)$_POST['stock_quantity']; $unit = $_POST['unit'];
        $f2 = (float)$_POST['factor_level2'] ?? 1; $f3 = (float)$_POST['factor_level3'] ?? 1;
        $multiplier = getBaseMultiplier($unit, ['unit'=>$unit, 'factor_level2'=>$f2, 'factor_level3'=>$f3]);
        $base_qty = $qty * $multiplier;
        $success = updateCSV('products', $id, [
            'name'=>$_POST['name'], 'category'=>$_POST['category'], 'description'=>$_POST['description'], 'buy_price'=>(float)$_POST['buy_price'],
            'sell_price'=>(float)$_POST['sell_price'], 'stock_quantity'=>$base_qty, 'unit'=>$unit,
            'factor_level2'=>$f2, 'factor_level3'=>$f3, 'expiry_date'=>$_POST['expiry_date'] ?? '', 'remarks'=>$_POST['remarks'] ?? ''
        ]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['status'=>$success?'success':'error', 'message'=>$success?"Updated!":"Failed!"]); exit; }
    }
    // ACTION: DELETE
    elseif ($_POST['action'] == 'delete') {
        deleteCSV('products', $_POST['id']);
        if (!$isAjax) header("Location: inventory.php?msg=deleted");
        exit;
    }
    // ACTION: RESTOCK
    elseif ($_POST['action'] == 'restock') {
        $id = $_POST['product_id'];
        $qty = (float)cleanInput($_POST['quantity']);
        $product = findById('products', $id);
        if ($product) {
            $multiplier = getBaseMultiplier($product['unit'], $product);
            $base_qty = $qty * $multiplier;
            $old_stock = (float)$product['stock_quantity'];
            $new_stock = $old_stock + $base_qty;
            $buy_price = (float)cleanInput($_POST['buy_price']); // Product unit price
            $sell_price = (float)cleanInput($_POST['sell_price']);
            
            $multiplier = getBaseMultiplier($product['unit'], $product);
            $new_price_base = $buy_price / $multiplier;
            $old_avco_base = isset($product['avg_buy_price']) ? ((float)$product['avg_buy_price'] / $multiplier) : ($product['buy_price'] / $multiplier);
            
            $total_value = ($old_stock * $old_avco_base) + ($base_qty * $new_price_base);
            $new_avco_base = ($new_stock > 0) ? ($total_value / $new_stock) : $new_price_base;
            $new_avco_product = $new_avco_base * $multiplier;

            updateCSV('products', $id, [
                'stock_quantity' => $new_stock, 
                'buy_price' => $buy_price, 
                'avg_buy_price' => number_format($new_avco_product, 2, '.', ''),
                'sell_price' => $sell_price
            ]);
            $dealer_id = $_POST['dealer_id'];
            $dealer_name = ($dealer_id == 'OPEN_MARKET') ? "Open Market" : (findById('dealers', $dealer_id)['name'] ?? 'Unknown');
            $rid = insertCSV('restocks', [
                'product_id' => $id, 
                'product_name' => $product['name'], 
                'quantity' => $qty, 
                'new_buy_price' => $buy_price, 
                'old_buy_price' => $product['buy_price'], 
                'new_sell_price' => $sell_price,
                'old_sell_price' => $product['sell_price'],
                'dealer_id' => $dealer_id, 
                'dealer_name' => $dealer_name, 
                'amount_paid' => (float)$_POST['amount_paid'], 
                'date' => $_POST['date'] ?? date('Y-m-d'), 
                'expiry_date' => $_POST['expiry_date'] ?? '',
                'remarks' => $_POST['remarks'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            // Also update the product itself with latest expiry/remarks
            if(!empty($_POST['expiry_date'])) updateCSV('products', $id, ['expiry_date' => $_POST['expiry_date']]);
            if(!empty($_POST['remarks'])) updateCSV('products', $id, ['remarks' => $_POST['remarks']]);
            if ($dealer_id !== 'OPEN_MARKET') {
                insertCSV('dealer_transactions', ['dealer_id' => $dealer_id, 'date' => date('Y-m-d'), 'type' => 'Restock', 'debit' => $qty * $buy_price, 'credit' => (float)$_POST['amount_paid'], 'description' => "Restock: " . $product['name'], 'created_at' => date('Y-m-d H:i:s'), 'restock_id' => $rid]);
            }
        }
        header("Location: inventory.php?msg=restocked");
        exit;
    }
}

$products = readCSV('products');
$units = readCSV('units');
$categories = readCSV('categories');
$restocks = readCSV('restocks');

$latest_restocks = [];
foreach ($restocks as $r) {
    if (!isset($latest_restocks[$r['product_id']]) || strtotime($r['date']) > strtotime($latest_restocks[$r['product_id']])) {
        $latest_restocks[$r['product_id']] = $r['date'];
    }
}

$total_inv_value = 0; $low_stock_count = 0; $category_counts = [];
foreach ($products as $p) {
    $total_inv_value += (float)$p['buy_price'] * (float)$p['stock_quantity'];
    if ((float)$p['stock_quantity'] < 10) $low_stock_count++;
    $cat = $p['category'] ?: 'Uncategorized';
    $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
}

$chart_labels = array_keys($category_counts);
$chart_data = array_values($category_counts);

usort($products, function($a, $b) use ($latest_restocks) {
    $dateA = $latest_restocks[$a['id']] ?? $a['created_at'];
    $dateB = $latest_restocks[$b['id']] ?? $b['created_at'];
    return strtotime($dateB) - strtotime($dateA);
});

$pageTitle = "Inventory Management";
include '../includes/header.php';
?>

<!-- Analytics Dashboard -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8 mt-4">
    <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-teal-500 group">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Total Products</p>
            <h3 class="text-3xl font-black text-gray-800"><?= number_format(count($products)) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-amber-500 group">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Stock Value</p>
            <h3 class="text-3xl font-black text-gray-800"><?= formatCurrency($total_inv_value) ?></h3>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 border-l-4 border-red-500 group">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Low Stock Alerts</p>
            <h3 class="text-3xl font-black text-red-600"><?= number_format($low_stock_count) ?></h3>
        </div>
    </div>
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-center">
        <canvas id="categoryChart" style="max-height: 120px;"></canvas>
    </div>
</div>

<!-- Actions Bar -->
<div class="mb-6 flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
    <div class="flex-1 w-full relative">
        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <input type="text" id="inventorySearch" placeholder="Search products, categories..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border-gray-100 border bg-gray-50 focus:bg-white focus:ring-2 focus:ring-teal-500 transition text-sm">
    </div>
    <div class="flex gap-2 w-full md:w-auto">
        <button onclick="openCatalogPreview()" class="px-4 py-2.5 bg-teal-50 text-teal-700 rounded-xl hover:bg-teal-100 transition font-bold text-xs"><i class="fas fa-book mr-2"></i>Catalog</button>
        <button onclick="printReport()" class="px-4 py-2.5 bg-blue-50 text-blue-700 rounded-xl hover:bg-blue-100 transition font-bold text-xs"><i class="fas fa-print mr-2"></i>Report</button>
        <button onclick="openModal('addProductModal')" class="px-6 py-2.5 bg-teal-600 text-white rounded-xl hover:bg-teal-700 shadow-lg shadow-teal-500/20 transition font-bold text-xs"><i class="fas fa-plus mr-2"></i>Add Product</button>
    </div>
</div>

<!-- Products Table -->
<div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-50 text-gray-400 text-[10px] font-black uppercase tracking-[0.2em] border-b">
                    <th class="p-5 w-16 text-center">Sr</th>
                    <th class="p-5">Product Details</th>
                    <th class="p-5">Category</th>
                    <th class="p-5">Stock Status</th>
                    <th class="p-5">Remarks</th>
                    <th class="p-5 text-right">Pricing</th>
                    <th class="p-5 text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryTableBody" class="divide-y divide-gray-50">
                <?php $sn = 1; foreach ($products as $p): ?>
                <tr class="hover:bg-gray-50/50 transition product-row" data-category="<?= strtolower($p['category']) ?>" data-unit="<?= strtolower($p['unit']) ?>">
                    <td class="p-5 text-center text-gray-300 font-mono text-xs"><?= $sn++ ?></td>
                    <td class="p-5">
                        <div class="font-bold text-gray-800"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="text-[10px] text-gray-400 mt-0.5"><?= $latest_restocks[$p['id']] ? 'Purchased: '.date('d M Y', strtotime($latest_restocks[$p['id']])) : 'New Item' ?></div>
                        <?php if(!empty($p['expiry_date'])): ?>
                            <div class="text-[9px] text-red-500 font-bold mt-1"><i class="fas fa-calendar-times mr-1"></i>Exp: <?= date('d M Y', strtotime($p['expiry_date'])) ?></div>
                        <?php endif; ?>
                        <!-- Hidden search content -->
                        <span class="hidden"><?= htmlspecialchars($p['description'] ?? '') ?> <?= htmlspecialchars($p['remarks'] ?? '') ?></span>
                    </td>
                    <td class="p-5">
                        <span class="px-2 py-1 rounded-md bg-gray-100 text-gray-500 text-[9px] font-black uppercase tracking-wider"><?= htmlspecialchars($p['category']) ?></span>
                    </td>
                    <td class="p-5">
                        <div class="text-sm font-bold <?= (float)$p['stock_quantity'] < 10 ? 'text-red-500' : 'text-gray-700' ?>">
                            <?= formatStockHierarchy($p['stock_quantity'], $p) ?>
                        </div>
                    </td>
                    <td class="p-5">
                        <div class="text-[10px] text-gray-500 max-w-[150px] truncate" title="<?= htmlspecialchars($p['remarks'] ?? '') ?>">
                            <?= htmlspecialchars($p['remarks'] ?? '-') ?>
                        </div>
                    </td>
                    <td class="p-5 text-right">
                        <div class="text-sm font-black text-gray-800"><?= formatCurrency($p['sell_price']) ?> <span class="text-[9px] text-gray-400 font-medium">/ <?= $p['unit'] ?></span></div>
                        <div class="text-[10px] font-bold text-gray-500 mt-0.5">Buy: <span class="text-teal-700"><?= formatCurrency($p['buy_price']) ?></span></div>
                    </td>
                    <td class="p-5">
                        <div class="flex justify-center gap-2">
                            <button class="restock-btn w-8 h-8 rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-600 hover:text-white transition flex items-center justify-center shadow-sm" data-product='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>' title="Restock"><i class="fas fa-plus text-[10px]"></i></button>
                            <button class="edit-btn w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition flex items-center justify-center shadow-sm" data-product='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>' title="Edit"><i class="fas fa-edit text-[10px]"></i></button>
                            <button class="delete-btn w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition flex items-center justify-center shadow-sm" data-id="<?= $p['id'] ?>" title="Delete"><i class="fas fa-trash text-[10px]"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals -->

<!-- 1. Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50/50">
            <h3 class="text-xl font-black text-gray-800 uppercase tracking-tight">Add New Product</h3>
            <button onclick="closeModal('addProductModal')" class="w-10 h-10 rounded-full hover:bg-gray-100 flex items-center justify-center">&times;</button>
        </div>
        <div class="p-8 overflow-y-auto flex-1 custom-scrollbar">
            <form id="addProductForm" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Product Name</label>
                        <input type="text" name="name" required class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-teal-500 transition font-bold" placeholder="Enter product name...">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Description</label>
                        <textarea name="description" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-teal-500 transition text-sm" placeholder="Optional product description..."></textarea>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <select name="category" class="p-3 bg-gray-50 border rounded-xl" onchange="checkRedirect(this)">
                        <?php foreach($categories as $c) echo "<option value='{$c['name']}'>{$c['name']}</option>"; ?>
                        <option value="ADD_NEW">+ New Category</option>
                    </select>
                    <select name="unit" id="add_unit_select" class="p-3 bg-gray-50 border rounded-xl" onchange="updateFactorUI('add'); checkRedirect(this)">
                        <option value="">Select Unit</option>
                        <?php foreach($units as $u) if($u['parent_id']==0) echo "<option value='{$u['name']}'>{$u['name']}</option>"; ?>
                        <option value="ADD_NEW">+ New Unit</option>
                    </select>
                </div>
                <div id="add_factors_container" class="hidden p-4 bg-teal-50 rounded-2xl border border-teal-100 space-y-4"></div>
                <input type="hidden" name="factor_level2" id="add_f2" value="1"><input type="hidden" name="factor_level3" id="add_f3" value="1">
                <div class="grid grid-cols-2 gap-4">
                    <input type="number" step="any" name="buy_price" id="add_buy_price" required class="p-3 border rounded-xl font-bold text-teal-700" placeholder="Buy Price">
                    <input type="number" step="any" name="sell_price" required class="p-3 border rounded-xl font-bold" placeholder="Sell Price">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div id="add_qty_row">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Initial Stock</label>
                        <input type="number" step="any" name="stock_quantity" id="add_stock_qty" required class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-teal-500 transition font-bold" placeholder="0">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Purchase Date</label>
                        <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-teal-500 transition">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Expiry Date</label>
                        <input type="date" name="expiry_date" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-teal-500 transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Remarks</label>
                        <input type="text" name="remarks" class="w-full p-3 bg-gray-50 border border-gray-100 rounded-xl focus:ring-2 focus:ring-teal-500 transition" placeholder="Optional notes...">
                    </div>
                </div>
                <div class="bg-blue-50 p-4 rounded-2xl border">
                    <select name="dealer_id" id="add_dealer" class="w-full p-3 bg-white border rounded-xl mb-3" onchange="fetchDealerData(this.value)">
                        <option value="OPEN_MARKET">Open Market</option>
                        <?php $dealers = readCSV('dealers'); foreach($dealers as $d) echo "<option value='{$d['id']}'>{$d['name']}</option>"; ?>
                        <option value="ADD_NEW">+ New Dealer</option>
                    </select>
                    <div id="add_surplus_msg" class="hidden text-[10px] text-green-600 font-bold mb-2"></div>
                    <input type="number" step="any" name="amount_paid" id="add_paid" class="p-3 bg-white border rounded-xl font-bold text-green-600 w-full" placeholder="Paid Amount">
                    <input type="text" name="new_dealer_name" id="new_dealer_input" class="hidden w-full mt-3 p-3 bg-white border rounded-xl" placeholder="New Dealer Name">
                </div>
                <button type="submit" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-bold">Save Product</button>
            </form>
        </div>
    </div>
</div>

<!-- 2. Edit Product Modal -->
<div id="editProductModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50/50">
            <h3 class="text-xl font-black text-gray-800 uppercase tracking-tight">Edit Product</h3>
            <button onclick="closeModal('editProductModal')" class="w-10 h-10 rounded-full hover:bg-gray-100 flex items-center justify-center">&times;</button>
        </div>
        <div class="p-8 overflow-y-auto flex-1 custom-scrollbar">
            <form id="editProductForm" class="space-y-6">
                <input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit_id">
                <input type="text" name="name" id="edit_name" required class="w-full p-3 border rounded-xl font-bold">
                <div class="space-y-1">
                    <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Description</label>
                    <textarea name="description" id="edit_description" class="w-full p-3 border rounded-xl text-sm" placeholder="Product description..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <select name="category" id="edit_category" class="p-3 border rounded-xl"><?php foreach($categories as $c) echo "<option value='{$c['name']}'>{$c['name']}</option>"; ?></select>
                    <select name="unit" id="edit_unit" class="p-3 border rounded-xl" onchange="updateFactorUI('edit')"><?php foreach($units as $u) if($u['parent_id']==0) echo "<option value='{$u['name']}'>{$u['name']}</option>"; ?></select>
                </div>
                <div id="edit_factors_container" class="hidden p-4 bg-blue-50 rounded-2xl border space-y-4"></div>
                <input type="hidden" name="factor_level2" id="edit_f2"><input type="hidden" name="factor_level3" id="edit_f3">
                <div class="grid grid-cols-2 gap-4">
                    <input type="number" name="buy_price" id="edit_buy_price" class="p-3 border rounded-xl font-bold text-teal-700">
                    <input type="number" name="sell_price" id="edit_sell_price" class="p-3 border rounded-xl font-bold">
                </div>
                <div id="edit_qty_row"><input type="number" name="stock_quantity" id="edit_stock_qty" class="p-3 border rounded-xl font-bold w-full"></div>
                <input type="date" name="expiry_date" id="edit_expiry_date" class="p-3 border rounded-xl w-full">
                <textarea name="remarks" id="edit_remarks" class="p-3 border rounded-xl w-full" placeholder="Remarks"></textarea>
                <button type="submit" class="w-full py-4 bg-blue-600 text-white rounded-2xl font-bold">Update Product</button>
            </form>
        </div>
    </div>
</div>

<!-- 3. Restock Modal -->
<div id="restockProductModal" class="fixed inset-0 bg-gray-900/60 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-lg overflow-hidden flex flex-col">
        <div class="p-6 border-b flex justify-between items-center bg-orange-50/50"><b>Restock Product</b><button onclick="closeModal('restockProductModal')">&times;</button></div>
        <form action="" method="POST" class="p-8 space-y-5">
            <input type="hidden" name="action" value="restock"><input type="hidden" name="product_id" id="restock_id">
            <h4 id="restock_product_name_display" class="text-lg font-black text-orange-800"></h4>
            <div class="grid grid-cols-2 gap-4">
                <div id="restock_qty_row">
                    <label class="block text-[10px] font-black text-gray-400 uppercase mb-2 ml-1">Quantity</label>
                    <input type="number" step="any" name="quantity" id="restock_qty" placeholder="Qty" required class="w-full p-3 bg-gray-50 border rounded-xl font-bold" oninput="calculateRestockTotal()">
                </div>
                <input type="number" step="any" name="buy_price" id="restock_buy_price" class="p-3 border rounded-xl font-bold text-teal-700" placeholder="Buy Price" oninput="calculateRestockTotal()">
            </div>
            <div id="restock_factors_container" class="hidden p-4 bg-orange-50 rounded-2xl border border-orange-100 space-y-4"></div>
            <input type="hidden" id="restock_unit"><input type="hidden" name="factor_level2" id="restock_f2" value="1"><input type="hidden" name="factor_level3" id="restock_f3" value="1">
            <input type="number" step="any" name="sell_price" id="restock_sell_price" class="p-3 border rounded-xl font-bold w-full" placeholder="Sell Price">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[8px] font-black text-gray-400 uppercase mb-1 ml-1">Expiry Date</label>
                    <input type="date" name="expiry_date" class="w-full p-2.5 bg-gray-50 border rounded-xl text-xs">
                </div>
                <div>
                    <label class="block text-[8px] font-black text-gray-400 uppercase mb-1 ml-1">Purchase Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full p-2.5 bg-gray-50 border rounded-xl text-xs">
                </div>
            </div>
            <input type="text" name="remarks" class="w-full p-3 bg-gray-50 border rounded-xl text-xs" placeholder="Restock notes / Batch no...">
            <div class="p-4 bg-gray-50 rounded-2xl border">
                <div class="flex justify-between mb-2"><span>Total Bill:</span><b id="restock_total_amount">Rs. 0</b></div>
                <input type="number" name="amount_paid" id="restock_amount_paid" class="p-3 bg-white border rounded-xl font-bold text-green-600 w-full" placeholder="Paid">
                <select name="dealer_id" class="w-full mt-3 p-3 border rounded-xl">
                    <option value="OPEN_MARKET">Open Market</option>
                    <?php foreach(readCSV('dealers') as $d) echo "<option value='{$d['id']}'>{$d['name']}</option>"; ?>
                </select>
            </div>
            <button type="button" onclick="validateAndSubmitRestock()" class="w-full py-4 bg-orange-600 text-white rounded-2xl font-bold">Complete Restock</button>
        </form>
    </div>
</div>

<!-- 4. Catalog Preview Modal -->
<div id="catalogPreviewModal" class="fixed inset-0 bg-gray-900/80 backdrop-blur-md hidden z-[100] flex items-center justify-center p-6">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-5xl h-[90vh] flex flex-col overflow-hidden">
        <div class="bg-teal-700 p-6 flex justify-between text-white">
            <h3 class="text-xl font-bold">Catalog Preview</h3>
            <div class="flex gap-2"><button onclick="printCatalog()" class="bg-white text-teal-700 px-6 py-2 rounded-xl text-xs font-black">PRINT</button><button onclick="closeModal('catalogPreviewModal')">&times;</button></div>
        </div>
        <div id="catalogPreviewBody" class="flex-1 overflow-auto bg-gray-100 p-8"></div>
    </div>
</div>

<script src="../assets/vendor/chartjs/chart.min.js"></script>
<script>
const availableUnits = <?= json_encode($units) ?>;
function getUnitHierarchyJS(name) {
    if(!name) return []; let chain = []; let current = availableUnits.find(u => u.name.toLowerCase() === name.toLowerCase());
    let safety = 0; while(current && safety < 10) { chain.push(current); safety++; let child = availableUnits.find(u => u.parent_id == current.id); if(child) current = child; else current = null; }
    return chain;
}
function calcTotalBase(prefix) {
    const name = document.getElementById(prefix + '_unit' + (prefix === 'add' ? '_select' : '')).value;
    const h = getUnitHierarchyJS(name);
    if(h.length <= 1) return;
    const q = parseFloat(document.getElementById(prefix + '_stock_qty').value || 0);
    const f2 = parseFloat(document.getElementById(prefix + '_f2').value || 1);
    const f3 = parseFloat(document.getElementById(prefix + '_f3').value || 1);
    let total = q;
    if(h.length > 1) total *= f2;
    if(h.length > 2) total *= f3;
    const display = document.getElementById(prefix + '_total_base_display');
    if(display) {
        display.innerHTML = `<div class="flex justify-between items-center text-teal-800 font-black text-xs">
            <span class="opacity-60 uppercase tracking-tight">Total ${h[h.length-1].name}:</span>
            <span class="text-lg">${total.toLocaleString()}</span>
        </div>`;
    }
}
function updateFactorUI(prefix, saved = null) {
    const name = document.getElementById(prefix + '_unit' + (prefix === 'add' ? '_select' : '')).value;
    const con = document.getElementById(prefix + '_factors_container'), qrow = document.getElementById(prefix + '_qty_row');
    const h = getUnitHierarchyJS(name); con.innerHTML = '';
    if(h.length <= 1) { con.classList.add('hidden'); if(qrow) qrow.classList.remove('hidden'); }
    else {
        con.classList.remove('hidden'); if(qrow) qrow.classList.add('hidden');
        const q = saved ? saved.q : (document.getElementById(prefix + '_stock_qty').value || 0);
        con.insertAdjacentHTML('beforeend', `<div class='p-3 bg-white rounded-xl border'><b>${h[0].name} Qty:</b> <input type='number' step='any' value='${q}' oninput="document.getElementById('${prefix}_stock_qty').value=this.value; if(typeof updateTotal==='function'&&'${prefix}'==='add') updateTotal(); calcTotalBase('${prefix}');" class='w-full outline-none font-bold'></div>`);
        for(let i=0; i<h.length-1; i++) {
            let v = saved ? (i===0?saved.f2:saved.f3) : 1;
            con.insertAdjacentHTML('beforeend', `<div class='p-3 bg-white rounded-xl border'><b>${h[i+1].name} in ${h[i].name}:</b> <input type='number' step='any' value='${v}' oninput="document.getElementById('${prefix}_f${i+2}').value=this.value; calcTotalBase('${prefix}');" class='w-full outline-none font-bold'></div>`);
            document.getElementById(prefix+'_f'+(i+2)).value = v;
        }
        con.insertAdjacentHTML('beforeend', `<div id='${prefix}_total_base_display' class='p-4 bg-teal-500/10 rounded-2xl border border-teal-200 mt-2'></div>`);
        calcTotalBase(prefix);
    }
}
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { if(id==='addProductModal'&&window.needsReload) location.reload(); else document.getElementById(id).classList.add('hidden'); }

let dealerBal = 0;
function fetchDealerData(id) {
    if(!id || id==='OPEN_MARKET') { dealerBal=0; updateTotal(); return; }
    fetch('inventory.php?action=get_balance&dealer_id='+id).then(r=>r.json()).then(d=>{ dealerBal=parseFloat(d.balance||0); updateTotal(); });
}
function updateTotal() {
    let q = parseFloat(document.getElementById('add_stock_qty').value)||0, b = parseFloat(document.getElementById('add_buy_price').value)||0, total = q*b, f = total;
    const msg = document.getElementById('add_surplus_msg'); if(dealerBal < 0) { msg.classList.remove('hidden'); msg.innerText = 'Surplus: Rs. '+Math.abs(dealerBal); f = Math.max(0, total-Math.abs(dealerBal)); } else msg.classList.add('hidden');
    document.getElementById('add_paid').value = f;
}

function openEditModal(p) {
    document.getElementById('edit_id').value=p.id; document.getElementById('edit_name').value=p.name; 
    document.getElementById('edit_description').value=p.description || '';
    document.getElementById('edit_category').value=p.category; document.getElementById('edit_unit').value=p.unit;
    document.getElementById('edit_buy_price').value=p.buy_price; document.getElementById('edit_sell_price').value=p.sell_price; document.getElementById('edit_expiry_date').value=p.expiry_date || ''; document.getElementById('edit_remarks').value=p.remarks || '';
    let h = getUnitHierarchyJS(p.unit), m = 1, f2 = parseFloat(p.factor_level2)||1, f3 = parseFloat(p.factor_level3)||1;
    if(h.length>2) m=f2*f3; else if(h.length>1) m=f2;
    let q = (parseFloat(p.stock_quantity)/m).toFixed(2); document.getElementById('edit_stock_qty').value=q;
    updateFactorUI('edit', {q, f2, f3}); openModal('editProductModal');
}

function openRestockModal(p) {
    document.getElementById('restock_id').value=p.id; 
    document.getElementById('restock_product_name_display').innerText=p.name; 
    document.getElementById('restock_buy_price').value=p.buy_price; 
    document.getElementById('restock_sell_price').value=p.sell_price;
    document.getElementById('restock_qty').value=''; 
    document.getElementById('restock_unit').value=p.unit;
    updateFactorUI('restock');
    calculateRestockTotal();
    openModal('restockProductModal');
}

function calculateRestockTotal() {
    let q = parseFloat(document.getElementById('restock_qty').value)||0, b = parseFloat(document.getElementById('restock_buy_price').value)||0;
    document.getElementById('restock_total_amount').innerText = 'Rs. '+(q*b).toLocaleString(); document.getElementById('restock_amount_paid').value = q*b;
    calcTotalBase('restock');
}
function validateAndSubmitRestock() { document.querySelector('#restockProductModal form').submit(); }

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => openEditModal(JSON.parse(b.dataset.product))));
    document.querySelectorAll('.restock-btn').forEach(b => b.addEventListener('click', () => openRestockModal(JSON.parse(b.dataset.product))));
    document.querySelectorAll('.delete-btn').forEach(b => b.addEventListener('click', () => { if(confirm('Delete?')) { let f=document.createElement('form'); f.method='POST'; f.innerHTML=`<input name='action' value='delete'><input name='id' value='${b.dataset.id}'>`; document.body.appendChild(f); f.submit(); } }));
    
    const addForm = document.getElementById('addProductForm');
    addForm.onsubmit = async (e) => {
        e.preventDefault(); try { let r = await fetch('', {method:'POST', body:new FormData(addForm), headers:{'X-Requested-With':'XMLHttpRequest'}}); let res=await r.json(); showAlert(res.message, 'Success'); if(res.status==='success'){window.needsReload=true; addForm.reset(); updateFactorUI('add');} } catch(err){ showAlert('Error', 'Error'); }
    };
    const editForm = document.getElementById('editProductForm');
    editForm.onsubmit = async (e) => {
        e.preventDefault(); try { let r = await fetch('', {method:'POST', body:new FormData(editForm), headers:{'X-Requested-With':'XMLHttpRequest'}}); let res=await r.json(); showAlert(res.message, 'Success'); if(res.status==='success') location.reload(); } catch(err){ showAlert('Error', 'Error'); }
    };
    
    document.querySelector('#restockProductModal input[name="quantity"]').oninput = calculateRestockTotal;
    document.getElementById('restock_buy_price').oninput = calculateRestockTotal;
    document.getElementById('add_stock_qty').oninput = updateTotal;
    document.getElementById('add_buy_price').oninput = updateTotal;
    
    new Chart(document.getElementById('categoryChart').getContext('2d'), { type:'doughnut', data:{ labels:<?= json_encode($chart_labels) ?>, datasets:[{data:<?= json_encode($chart_data) ?>, backgroundColor:['#0d9488','#3b82f6','#f59e0b','#ef4444']}] }, options:{plugins:{legend:{display:false}}, cutout:'70%'} });
    document.getElementById('inventorySearch').oninput = (e) => { let v=e.target.value.toLowerCase(); document.querySelectorAll('.product-row').forEach(r => r.style.display = r.textContent.toLowerCase().includes(v)?'':'none'); };
});

function openCatalogPreview() { document.getElementById('catalogPreviewBody').innerHTML = document.getElementById('catalogPrintableArea').innerHTML; openModal('catalogPreviewModal'); }
function printCatalog() { let w=window.open('','_blank'); w.document.write('<html><body>'+document.getElementById('catalogPreviewBody').innerHTML+'</body></html>'); w.document.close(); w.print(); }
function printReport() { let w=window.open('','_blank'); w.document.write('<html><body>'+document.getElementById('printableArea').innerHTML+'</body></html>'); w.document.close(); w.print(); }
function checkRedirect(s) { if(s.value==='ADD_NEW') location.href = s.name==='unit'?'units.php':'categories.php'; }
</script>

<div id="printableArea" class="hidden"><div style="padding:40px; font-family:sans-serif;"><div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #0d9488; padding-bottom:15px; margin-bottom:20px;"><div><h1 style="color:#0d9488; margin:0;"><?= getSetting('business_name') ?></h1><p style="margin:5px 0 0; color:#666;">Inventory Stock Report</p></div><div style="text-align:right;"><p style="margin:0; font-weight:bold;"><?= date('d M Y') ?></p></div></div><table style="width:100%; border-collapse:collapse;"><thead><tr style="background:#0d9488; color:#fff;"> <th style="padding:12px; border:1px solid #ddd; text-align:left;">S.No</th> <th style="padding:12px; border:1px solid #ddd; text-align:left;">Product Name</th> <th style="padding:12px; border:1px solid #ddd; text-align:left;">Category</th> <th style="padding:12px; border:1px solid #ddd; text-align:left;">Stock</th> <th style="padding:12px; border:1px solid #ddd; text-align:left;">Remarks</th> <th style="padding:12px; border:1px solid #ddd; text-align:left;">Expiry</th> <th style="padding:12px; border:1px solid #ddd; text-align:right;">Buy Price</th> <th style="padding:12px; border:1px solid #ddd; text-align:right;">Total Value</th> </tr></thead><tbody><?php $total_val = 0; $sn=1; foreach($products as $p): $val = (float)$p['buy_price'] * (float)$p['stock_quantity']; $total_val += $val; ?> <tr><td style="padding:10px; border:1px solid #ddd;"><?= $sn++ ?></td><td style="padding:10px; border:1px solid #ddd; font-weight:bold;"><?= $p['name'] ?></td><td style="padding:10px; border:1px solid #ddd;"><?= $p['category'] ?></td><td style="padding:10px; border:1px solid #ddd;"><?= formatStockHierarchy($p['stock_quantity'], $p) ?></td><td style="padding:10px; border:1px solid #ddd; font-size:10px;"><?= $p['remarks'] ?: '-' ?></td><td style="padding:10px; border:1px solid #ddd; color:<?= (strtotime($p['expiry_date']) < time()) ? 'red' : 'black' ?>;"><?= !empty($p['expiry_date']) ? date('d-m-y', strtotime($p['expiry_date'])) : '-' ?></td><td style="padding:10px; border:1px solid #ddd; text-align:right;"><?= formatCurrency($p['buy_price']) ?></td><td style="padding:10px; border:1px solid #ddd; text-align:right;"><?= formatCurrency($val) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr style="background:#f0fdfa; font-weight:bold;"><td colspan="7" style="padding:12px; border:1px solid #ddd; text-align:right;">Grand Total:</td><td style="padding:12px; border:1px solid #ddd; text-align:right; color:#0d9488;"><?= formatCurrency($total_val) ?></td></tr></tfoot></table></div></div>
<div id="catalogPrintableArea" class="hidden"><div style="padding:40px; font-family:sans-serif;"><div style="text-align:center; margin-bottom:30px;"><h1 style="color:#0d9488; margin:0;"><?= getSetting('business_name') ?></h1><h3 style="text-transform:uppercase; color:#666; letter-spacing:3px;">Product Catalog</h3><div style="width:60px; height:3px; background:#0d9488; margin:15px auto;"></div></div><?php $g=[]; foreach($products as $p) $g[$p['category']?:'Uncategorized'][]=$p; ksort($g); foreach($g as $c=>$items): ?><div style="margin-bottom:40px;"><h2 style="color:#0d9488; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:15px; text-transform:uppercase; font-size:16px;"><?= $c ?></h2><div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;"><?php usort($items, function($a,$b){return strcmp($a['name'],$b['name']);}); foreach($items as $item): ?><div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px dashed #eee; padding-bottom:10px;"><div><div style="font-weight:bold; color:#333;"><?= $item['name'] ?></div><?php if(!empty($item['description'])): ?><div style="font-size:10px; color:#888; margin-top:3px;"><?= $item['description'] ?></div><?php endif; ?></div><div style="font-weight:900; color:#0d9488; white-space:nowrap; margin-left:15px;"><?= formatCurrency($item['sell_price']) ?></div></div><?php endforeach; ?></div></div><?php endforeach; ?></div></div>
<?php include '../includes/footer.php'; ?>
