<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasPermission('add_product')) die("Unauthorized Access");

$message = '';
$error = '';

// AJAX Helper to get Realtime Balance
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
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
                        'phone' => '', // Standardize key
                        'address' => '', // Ensure key exists
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
        }

            if ($isAjax) {
                header('Content-Type: application/json');
                if ($new_product_id) {
                    echo json_encode(['status' => 'success', 'message' => "Product " . cleanInput($_POST['name']) . " added successfully!"]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => "Error adding product."]);
                }
                exit;
            }

            $message = "Product added successfully with Initial Stock logged!";
        }
        
        if ($isAjax && $error) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $error]);
            exit;
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

$pageTitle = "Inventory Management";
include '../includes/header.php';
?>
<!-- Chart.js CDN -->
<script src="../assets/vendor/chartjs/chart.min.js"></script>

<?php
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
// Sort by created_at desc (newest first)
usort($products, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Calculate Analytics Data
$total_products = count($products);
$total_stock_value = 0;
$low_stock_count = 0;
$category_counts = [];

foreach ($products as $p) {
    $total_stock_value += (float)$p['buy_price'] * (float)$p['stock_quantity'];
    if ((float)$p['stock_quantity'] < 10) $low_stock_count++;
    
    $cat = $p['category'] ?: 'Uncategorized';
    $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
}

// Prepare Chart Data
$chart_labels = array_keys($category_counts);
$chart_data = array_values($category_counts);

// Calculate Dealer Balances for Surplus Logic
$all_txns_inv = readCSV('dealer_transactions');
$dealer_balances = [];
foreach($all_txns_inv as $tx) {
    if(!isset($dealer_balances[$tx['dealer_id']])) $dealer_balances[$tx['dealer_id']] = 0;
    $dealer_balances[$tx['dealer_id']] += (float)($tx['debit'] ?? 0);
    $dealer_balances[$tx['dealer_id']] -= (float)($tx['credit'] ?? 0);
}
?>

<!-- Analytics Dashboard -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8 mt-2">
    <!-- KPI Cards -->
    <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-teal-500 relative overflow-hidden group hover:shadow-xl transition-all">
            <div class="absolute -right-4 -top-4 text-teal-50 opacity-10 group-hover:scale-110 transition-transform">
                <i class="fas fa-boxes text-8xl"></i>
            </div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Total Products</p>
            <h3 class="text-3xl font-black text-gray-800"><?= number_format($total_products) ?></h3>
            <p class="text-xs text-gray-500 mt-2">Active in inventory</p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-amber-500 relative overflow-hidden group hover:shadow-xl transition-all">
             <div class="absolute -right-4 -top-4 text-amber-50 opacity-10 group-hover:scale-110 transition-transform">
                <i class="fas fa-wallet text-8xl"></i>
            </div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Stock Value</p>
            <h3 class="text-3xl font-black text-gray-800"><?= formatCurrency($total_stock_value) ?></h3>
            <p class="text-xs text-gray-500 mt-2">Total investment</p>
        </div>

        <a href="inventory.php?filter=low" class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-red-500 relative overflow-hidden group hover:shadow-xl transition-all cursor-pointer block">
             <div class="absolute -right-4 -top-4 text-red-50 opacity-10 group-hover:scale-110 transition-transform">
                <i class="fas fa-exclamation-triangle text-8xl"></i>
            </div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Low Stock Alerts</p>
            <h3 class="text-3xl font-black text-red-600"><?= number_format($low_stock_count) ?></h3>
            <p class="text-xs text-red-500 font-bold mt-2 flex items-center">
                <i class="fas fa-arrow-down mr-1"></i> Below 10 units
            </p>
        </a>
    </div>

    <!-- Category Pie Chart -->
    <div class="bg-white p-6 rounded-2xl shadow-lg flex flex-col items-center justify-center relative min-h-[200px]">
        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4 w-full text-center">Category Spread</h4>
        <div class="w-full h-full max-h-[150px] relative mt-[-20px]">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<div class="mb-6 flex flex-col lg:flex-row justify-between items-center gap-4 bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
    <!-- Search and Filters -->
    <div class="flex flex-col md:flex-row items-center gap-3 flex-1 w-full">
        <div class="relative w-full max-w-md">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <i class="fas fa-search text-xs"></i>
            </span>
            <input type="text" id="inventorySearch" autofocus placeholder="Search inventory..." class="w-full pl-9 pr-4 py-2.5 rounded-xl border-gray-200 border focus:ring-2 focus:ring-teal-500 focus:outline-none transition text-sm">
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto">
            <div class="flex-1 md:flex-none">
                <select id="filterType" onchange="updateFilterOptions()" class="w-full md:w-40 pl-3 pr-8 py-2.5 rounded-xl border-gray-200 border focus:ring-2 focus:ring-teal-500 focus:outline-none transition text-sm appearance-none bg-no-repeat bg-[right_0.5rem_center] bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E');">
                    <option value="none">Searched By</option>
                    <option value="category">By Categories</option>
                    <option value="unit">By Units</option>
                </select>
            </div>
            <div class="flex-1 md:flex-none">
                <select id="filterValue" onchange="applyFilters()" class="w-full md:w-48 pl-3 pr-8 py-2.5 rounded-xl border-gray-200 border focus:ring-2 focus:ring-teal-500 focus:outline-none transition text-sm appearance-none bg-no-repeat bg-[right_0.5rem_center] bg-[length:1em_1em]" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E');">
                    <option value="all">All Values</option>
                </select>
            </div>
            <button onclick="clearFilters()" class="px-4 py-2.5 bg-gray-100 text-gray-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 transition h-[42px] flex items-center border border-gray-200">
                Clear
            </button>
        </div>
    </div>

    <div class="flex gap-3 w-full lg:w-auto">
        <button onclick="printReport()" class="w-full lg:w-auto bg-blue-600 text-white px-6 py-2.5 rounded-xl hover:bg-blue-700 shadow-lg flex items-center justify-center transition active:scale-95 font-bold text-sm">
            <i class="fas fa-print mr-2"></i> Print / Save PDF
        </button>
        <button onclick="document.getElementById('addProductModal').classList.remove('hidden')" class="bg-teal-600 text-white px-6 py-2.5 rounded-xl hover:bg-teal-700 transition flex items-center shadow-lg w-full lg:w-auto justify-center font-bold text-sm transform active:scale-95">
            <i class="fas fa-plus mr-2"></i> Add Product
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?= $message ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?= $error ?></div>
<?php endif; ?>

<style>
    #inventoryTable thead {
        position: sticky;
        top: 0;
        z-index: 10;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    #inventoryTable thead th {
        background-color: #0f766e !important;
    }
</style>

<!-- Inventory Table -->
<div class="bg-white rounded-xl shadow-lg">
    <div class="overflow-auto max-h-[600px]">
        <table id="inventoryTable" class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-teal-700 text-white text-sm uppercase tracking-wider">
                    <th class="p-4 w-12 text-center">Sr #</th>
                    <th class="p-4">Purchased Date</th>
                    <th class="p-4">Product Name</th>
                    <th class="p-4">Category</th>
                    <th class="p-4">Remarks</th>
                    <th class="p-4">Stock</th>
                    <th class="p-4 text-right">Buy Price</th>
                    <th class="p-4 text-right">Sell Price</th>
                    <th class="p-4 text-right bg-teal-800">Total Cost</th>
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
                        $grand_total_cost += $stock_val;
                ?>
                        <tr class="hover:bg-gray-50 transition border-b product-row" 
                            data-category="<?= strtolower(htmlspecialchars($product['category'])) ?>"
                            data-unit="<?= strtolower(htmlspecialchars($product['unit'])) ?>">
                            <td class="p-4 text-gray-400 font-mono text-xs text-center"><?= $sn++ ?></td>
                            <td class="p-4">
                                <span class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-1 rounded uppercase">
                                    <?= date('d M Y', strtotime($product['created_at'])) ?>
                                </span>
                            </td>
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
                                <span class="text-xs text-gray-500 italic">
                                    <?= !empty($product['remarks']) ? htmlspecialchars($product['remarks']) : 'No Remarks' ?>
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
                            <td class="p-4 text-center">
                                <div class="flex justify-center space-x-2">
                                    <button onclick='openRestockModal(<?= htmlspecialchars(json_encode($product), ENT_QUOTES, "UTF-8") ?>)' class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 hover:bg-orange-600 hover:text-white transition flex items-center justify-center shadow-sm" title="Restock">
                                        <i class="fas fa-plus-circle text-xs"></i>
                                    </button>
                                    <button onclick='openEditModal(<?= htmlspecialchars(json_encode($product), ENT_QUOTES, "UTF-8") ?>)' class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition flex items-center justify-center shadow-sm" title="Edit">
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
                        <td colspan="12" class="p-12 text-center text-gray-400">
                            <i class="fas fa-box-open text-5xl mb-4 text-gray-200"></i><br>
                            No products found. Start by adding one.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (count($products) > 0): ?>
            <tfoot class="bg-gray-800 text-white font-bold">
                <tr>
                    <td colspan="8" class="p-4 text-right uppercase tracking-wider text-xs">Grand Inventory Totals:</td>
                    <td class="p-4 text-right font-mono"><?= formatCurrency($grand_total_cost) ?></td>
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
            <button onclick="closeAddProductModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" action="" id="addProductForm">
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
                            
                            <!-- Surplus Display -->
                            <div id="add_dealer_surplus_msg" class="hidden mt-1 text-[10px] font-bold text-green-600 bg-green-50 p-1 rounded border border-green-100">
                                Surplus Available: <span class="font-black">Rs. 0</span>
                            </div>                            
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
                    <button type="button" onclick="closeAddProductModal()" class="py-2.5 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 font-bold transition">Cancel</button>
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
            <div class="space-y-3">
                <!-- Row 1: Basic Info -->
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-6">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Product Name</label>
                        <input type="text" name="name" id="edit_name" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 focus:border-teal-500 text-sm font-bold text-gray-800">
                    </div>
                    <div class="col-span-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Category</label>
                        <select name="category" id="edit_category" onchange="checkDropdown(this)" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-sm">
                            <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ADD_NEW" class="text-teal-600 font-bold">+ Add New</option>
                        </select>
                    </div>
                    <div class="col-span-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Unit</label>
                        <select name="unit" id="edit_unit" onchange="checkDropdown(this)" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-sm">
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
                            <input type="number" step="0.01" name="buy_price" id="edit_buy_price" required class="w-full rounded-lg border-gray-300 border p-1.5 pl-8 focus:ring-teal-500 text-sm font-semibold">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sell Price</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1.5 text-gray-400 text-xs">Rs.</span>
                            <input type="number" step="0.01" name="sell_price" id="edit_sell_price" required class="w-full rounded-lg border-gray-300 border p-1.5 pl-8 focus:ring-teal-500 text-sm font-semibold">
                        </div>
                    </div>
                </div>
                
                <!-- Row 3: Stock & Dates -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Current Stock</label>
                        <input type="number" name="stock_quantity" id="edit_stock_quantity" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-sm font-bold text-gray-700 bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Expiry Date <span class="text-[9px] text-gray-400 normal-case">(Opt)</span></label>
                        <input type="date" name="expiry_date" id="edit_expiry_date" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-xs">
                    </div>
                     <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Last Purchase</label>
                        <div class="text-xs text-gray-500 p-2 italic" id="edit_created_at_display">-</div>
                    </div>
                </div>

                <!-- Row 4: Remarks -->
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Remarks</label>
                    <input type="text" name="remarks" id="edit_remarks" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 text-sm" placeholder="Remarks (optional)...">
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeEditModal()" class="py-2.5 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 font-bold transition">Cancel</button>
                <button type="submit" class="py-2.5 rounded-lg bg-teal-600 text-white hover:bg-teal-700 font-bold shadow-lg shadow-teal-500/30 transition">Update Product</button>
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
    
    // Optional: display created_at date
    if(document.getElementById('edit_created_at_display')) {
        const d = new Date(product.created_at);
        document.getElementById('edit_created_at_display').innerText = d.toLocaleDateString();
    }
    
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
    // Trigger Recalc
    fetchDealerBalance(select.value);
}

// const dealerBalances = ... (Removed in favor of AJAX)
let currentDealerBalance = 0;

function fetchDealerBalance(dealerId) {
    if(!dealerId || dealerId === 'ADD_NEW' || dealerId === 'OPEN_MARKET') {
        currentDealerBalance = 0;
        updateAddProductTotal();
        return;
    }
    
    // Show loading or keep old until loaded? Best to reset 0 briefly or show loader.
    // We'll keep prev value or 0? Let's use 0 safely.
    // currentDealerBalance = 0; // optional
    // updateAddProductTotal(); 

    fetch(`inventory.php?action=get_balance&dealer_id=${dealerId}`)
        .then(r => r.json())
        .then(data => {
            currentDealerBalance = parseFloat(data.balance || 0);
            updateAddProductTotal();
        })
        .catch(e => {
            console.error(e);
            currentDealerBalance = 0;
            updateAddProductTotal();
        });
}

function updateAddProductTotal() {
    const qty = parseFloat(document.getElementById('add_stock_qty').value) || 0;
    const price = parseFloat(document.getElementById('add_buy_price').value) || 0;
    // const dealerId = document.getElementById('add_dealer_select').value; // No longer needed for lookup, using cached var
    
    const total = qty * price;
    const totalDisplay = document.getElementById('add_total_bill');
    const paidInput = document.getElementById('add_amount_paid');
    const surplusMsg = document.getElementById('add_dealer_surplus_msg');
    
    let finalPayable = total;
    let surplusUsed = 0;
    
    // Check Surplus (currentDealerBalance < 0 means surplus)
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
                <span class="line-through text-gray-400 text-xs">Rs. ${total.toLocaleString()}</span>
                <span class="block text-teal-700">Rs. ${finalPayable.toLocaleString()}</span>
                <span class="text-[9px] text-green-600 font-normal block mt-0.5">(- Rs. ${surplusUsed.toLocaleString()} from Surplus)</span>
             `;
        }
    } else {
        if (surplusMsg) surplusMsg.classList.add('hidden');
        if (totalDisplay) totalDisplay.innerText = 'Rs. ' + total.toLocaleString();
    }
    
    if (paidInput) paidInput.value = finalPayable;
}

// Hook inputs to update function
document.getElementById('add_stock_qty').addEventListener('input', updateAddProductTotal);
document.getElementById('add_buy_price').addEventListener('input', updateAddProductTotal);


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

    // AJAX Submission for Add Product
    const addProductForm = document.getElementById('addProductForm');
    let productAdded = false; // Flag to track if any product was added

    if (addProductForm) {
        addProductForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerText;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showAlert(result.message, 'Success');
                    productAdded = true; // Mark that at least one product was added
                    // Reset form but keep some defaults
                    const keepFields = ['purchase_date', 'category', 'unit', 'dealer_id', 'action'];
                    Array.from(this.elements).forEach(el => {
                        if (el.name && !keepFields.includes(el.name)) {
                            el.value = '';
                        }
                    });
                    // Reset specific displays
                    document.getElementById('add_total_bill').innerText = 'Rs. 0';
                    document.getElementById('add_amount_paid').value = '0';
                    this.querySelector('input[name="name"]').focus();
                } else {
                    showAlert(result.message, 'Error');
                }
            } catch (error) {
                showAlert('An unexpected error occurred.', 'Error');
                console.error(error);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = originalBtnText;
            }
        });
    }

    // Function to close modal and refresh if needed
    window.closeAddProductModal = function() {
        if (productAdded) {
            window.location.reload();
        } else {
            document.getElementById('addProductModal').classList.add('hidden');
        }
    };
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

// Filtering logic
const categoriesData = <?= json_encode(array_column($categories, 'name')) ?>;
const unitsData = <?= json_encode(array_column($units, 'name')) ?>;

function updateFilterOptions() {
    const type = document.getElementById('filterType').value;
    const valueSelect = document.getElementById('filterValue');
    valueSelect.innerHTML = '<option value="all">All Values</option>';
    
    let options = [];
    if (type === 'category') options = categoriesData;
    else if (type === 'unit') options = unitsData;
    
    options.forEach(opt => {
        const o = document.createElement('option');
        o.value = opt;
        o.textContent = opt;
        valueSelect.appendChild(o);
    });
    
    applyFilters();
}

function clearFilters() {
    document.getElementById('inventorySearch').value = '';
    document.getElementById('filterType').value = 'none';
    updateFilterOptions();
}

function applyFilters() {
    const term = document.getElementById('inventorySearch').value.toLowerCase();
    const type = document.getElementById('filterType').value;
    const filterVal = document.getElementById('filterValue').value.toLowerCase();
    const rows = document.querySelectorAll('.product-row');
    
    rows.forEach(row => {
        const nameNode = row.querySelector('td:nth-child(3)'); 
        if (!nameNode) return;
        
        const name = nameNode.textContent.toLowerCase();
        const category = row.getAttribute('data-category');
        const unit = row.getAttribute('data-unit');
        
        const matchesSearch = name.includes(term) || category.includes(term);
        let matchesFilter = true;
        
        if (type === 'category' && filterVal !== 'all') {
            matchesFilter = (category === filterVal);
        } else if (type === 'unit' && filterVal !== 'all') {
            matchesFilter = (unit === filterVal);
        }
        
        row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
    });
}

document.getElementById('inventorySearch').addEventListener('input', applyFilters);

document.getElementById('inventorySearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const visibleRows = document.querySelectorAll('tbody tr:not([style*="display: none"])');
        if (visibleRows.length > 0) {
            const editBtn = visibleRows[0].querySelector('button[title="Edit"]');
            if (editBtn) editBtn.click();
        }
    }
});

// Category Chart Initialization
const ctx = document.getElementById('categoryChart').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: [
                '#14b8a6', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#6366f1'
            ],
            borderWidth: 0,
            hoverOffset: 12
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return ` ${context.label}: ${context.raw} Products`;
                    }
                }
            }
        },
        cutout: '10%'
    }
});

function printReport() {
    const element = document.getElementById('printableArea');
    const content = element.innerHTML;

    const printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Overall Inventory Report</title><link rel="icon" type="image/png" href="../assets/img/favicon.png"><style>body { font-family: sans-serif; }</style></head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}
</script>

<!-- Printable Area -->
<div id="printableArea" class="hidden">
    <div style="padding: 40px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0d9488; padding-bottom: 20px; margin-bottom: 30px;">
            <div>
                <h1 style="color: #0f766e; margin: 0; font-size: 28px;"><?= getSetting('business_name', 'Fashion Shines') ?></h1>
                <p style="color: #666; margin: 5px 0 0 0;">Overall Inventory Preview Report</p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; color: #333;">Inventory Summary</h2>
                <p style="color: #888; margin: 5px 0 0 0;">Generated on: <?= date('d M Y, h:i A') ?></p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #0f766e; color: #fff;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; width: 40px; font-size: 11px;">Sr #</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Product Name</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Category</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Stock</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Unit</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Buy Price</th>
                    <th style="padding: 10px; text-align: right; border: 1px solid #ddd; font-size: 11px;">Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sn_p = 1; 
                $total_inv_val = 0;
                foreach ($products as $p): 
                    $val = (float)$p['buy_price'] * (float)$p['stock_quantity'];
                    $total_inv_val += $val;
                ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: center;"><?= $sn_p++ ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; font-weight: 600;"><?= htmlspecialchars($p['name']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;"><?= htmlspecialchars($p['category']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right;"><?= $p['stock_quantity'] ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right; color: #666;"><?= htmlspecialchars($p['unit']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 11px; text-align: right;"><?= formatCurrency($p['buy_price']) ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: right; font-weight: bold; font-size: 11px;"><?= formatCurrency($val) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: bold;">
                    <td colspan="6" style="padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 11px;">Grand Total Stock Value:</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right; color: #0d9488; font-size: 14px;"><?= formatCurrency($total_inv_val) ?></td>
                </tr>
            </tfoot>
        </table>

        <div style="border-top: 1px solid #ddd; margin-top: 30px; padding-top: 10px; text-align: center; font-size: 10px; color: #888;">
            <p style="margin: 0; font-weight: bold;">Software by Abdul Rafay</p>
            <p style="margin: 5px 0 0 0;">WhatsApp: 03000358189 / 03710273699</p>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php';
echo '</main></div></body></html>'; 
?>
