<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = "Inventory Management";
include '../includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $data = [
            'name' => cleanInput($_POST['name']),
            'category' => cleanInput($_POST['category']),
            'description' => '',
            'buy_price' => cleanInput($_POST['buy_price']),
            'sell_price' => cleanInput($_POST['sell_price']),
            'stock_quantity' => cleanInput($_POST['stock_quantity']),
            'unit' => cleanInput($_POST['unit']),
            'created_at' => date('Y-m-d H:i:s')
        ];

        if (insertCSV('products', $data)) {
            $message = "Product added successfully!";
        } else {
            $error = "Error adding product.";
        }
    } elseif ($_POST['action'] == 'edit') {
        $id = $_POST['id'];
        $data = [
            'name' => cleanInput($_POST['name']),
            'category' => cleanInput($_POST['category']),
            'buy_price' => cleanInput($_POST['buy_price']),
            'sell_price' => cleanInput($_POST['sell_price']),
            'stock_quantity' => cleanInput($_POST['stock_quantity']),
            'unit' => cleanInput($_POST['unit'])
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
}

// Reverse sort to show newest first
usort($products, function($a, $b) {
    return $b['id'] - $a['id'];
});
?>

<div class="mb-6 flex justify-between items-center">
    <div class="relative w-full max-w-md">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="inventorySearch" autofocus placeholder="Search inventory..." class="w-full pl-10 pr-4 py-2 rounded-lg border focus:ring-2 focus:ring-teal-500 focus:outline-none">
    </div>
    <button onclick="document.getElementById('addProductModal').classList.remove('hidden')" class="bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 transition flex items-center shadow-lg">
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
                        $total_cost = $buy * $qty;
                        $total_profit = ($sell - $buy) * $qty;
                        
                        $grand_total_cost += $total_cost;
                        $grand_total_profit += $total_profit;
                ?>
                        <tr class="hover:bg-gray-50 transition border-b">
                            <td class="p-4 text-gray-400 font-mono text-xs text-center"><?= $sn++ ?></td>
                            <td class="p-4 font-bold text-gray-800"><?= htmlspecialchars($product['name']) ?></td>
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
                            <td class="p-4 text-right font-mono font-bold text-gray-700 bg-gray-50/50"><?= formatCurrency($total_cost) ?></td>
                            <td class="p-4 text-right font-mono font-bold text-green-600 bg-green-50/30"><?= formatCurrency($total_profit) ?></td>
                            <td class="p-4 text-center">
                                <div class="flex justify-center space-x-2">
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
                        <td colspan="9" class="p-12 text-center text-gray-400">
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
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Name</label>
                    <input type="text" name="name" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500 focus:border-teal-500">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" onchange="checkDropdown(this)" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                            <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ADD_NEW" class="text-teal-600 font-bold">+ Add Category</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <select name="unit" onchange="checkDropdown(this)" class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
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
                        <input type="number" step="0.01" name="buy_price" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sell Price</label>
                        <input type="number" step="0.01" name="sell_price" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Initial Stock</label>
                    <input type="number" name="stock_quantity" required class="w-full rounded-lg border-gray-300 border p-2 focus:ring-teal-500">
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('addProductModal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700 shadow-md">Save Product</button>
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
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700 shadow-md">Update Product</button>
            </div>
        </form>
    </div>
</div>

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
    
    document.getElementById('editProductModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editProductModal').classList.add('hidden');
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
