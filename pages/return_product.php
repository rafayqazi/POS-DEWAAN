<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasPermission('add_sale')) die("Unauthorized Access");
$pageTitle = "Product Returns";
include '../includes/header.php';

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$sale_id = $_GET['sale_id'] ?? '';
$sale = null;
$customer = null;
$items = [];
$products = [];

if ($sale_id) {
    $sale = findCSV('sales', $sale_id);
    if ($sale) {
        $customers = readCSV('customers');
        foreach ($customers as $c) {
            if ($c['id'] == $sale['customer_id']) {
                $customer = $c;
                break;
            }
        }

        $all_sale_items = readCSV('sale_items');
        $items = array_filter($all_sale_items, function ($item) use ($sale_id) {
            return $item['sale_id'] == $sale_id;
        });

        $all_products = readCSV('products');
        foreach ($all_products as $p) {
            $products[$p['id']] = $p;
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    <!-- Search Section -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Search Sale to Return</h3>
        <form action="" method="GET" class="flex gap-3">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="sale_id" value="<?= htmlspecialchars($sale_id) ?>" placeholder="Enter Sale ID (e.g. 60)..." 
                       class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-200 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 outline-none transition-all">
            </div>
            <button type="submit" class="px-6 py-2 bg-teal-600 text-white font-bold rounded-xl hover:bg-teal-700 transition-all shadow-lg shadow-teal-900/10">
                Load Sale
            </button>
        </form>
    </div>

    <?php if ($sale_id && !$sale): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-xl border border-red-100 mb-6 text-center font-medium">
            <i class="fas fa-exclamation-circle mr-2"></i> Sale #<?= htmlspecialchars($sale_id) ?> not found.
        </div>
    <?php elseif ($sale): ?>
        <!-- Sale Details -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
            <div class="flex justify-between items-start mb-6 pb-6 border-b border-gray-100">
                <div>
                    <h2 class="text-2xl font-black text-gray-800">Sale #<?= $sale['id'] ?></h2>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mt-1"><?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest">Customer</p>
                    <p class="font-bold text-gray-700"><?= $customer ? htmlspecialchars($customer['name']) : 'Walk-in' ?></p>
                </div>
            </div>

            <form action="../actions/process_return.php" method="POST" onsubmit="return validateReturn()">
                <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                
                <table class="w-full text-left mb-6">
                    <thead>
                        <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                            <th class="py-3 px-2">Product</th>
                            <th class="py-3 px-2 text-center">Sold Qty</th>
                            <th class="py-3 px-2 text-center">Already Returned</th>
                            <th class="py-3 px-2 text-center w-32">Return Qty</th>
                            <th class="py-3 px-2 text-right">Price</th>
                            <th class="py-3 px-2 text-right">Refund Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($items as $item): 
                            $p = $products[$item['product_id']] ?? null;
                            $available_to_return = (float)$item['quantity'] - (float)($item['returned_qty'] ?? 0);
                        ?>
                        <tr class="group hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-2">
                                <p class="font-bold text-gray-800 text-sm"><?= $p ? htmlspecialchars($p['name']) : 'Unknown' ?></p>
                                <p class="text-[10px] text-gray-400 uppercase font-medium mt-0.5"><?= $p['category'] ?? '' ?></p>
                            </td>
                            <td class="py-4 px-2 text-center font-bold text-gray-600"><?= $item['quantity'] ?></td>
                            <td class="py-4 px-2 text-center font-bold text-red-400"><?= $item['returned_qty'] ?? 0 ?></td>
                            <td class="py-4 px-2">
                                <div class="relative group/input">
                                    <input type="number" name="return_qty[<?= $item['id'] ?>]" 
                                           data-max="<?= $available_to_return ?>" 
                                           data-price="<?= $item['price_per_unit'] ?>"
                                           value="0" min="0" max="<?= $available_to_return ?>" step="any"
                                           oninput="calculateRefundTotal(this)"
                                           class="return-input w-full p-2 text-center font-black text-teal-700 bg-teal-50/30 border border-teal-100 rounded-lg focus:border-teal-500 focus:bg-white outline-none transition-all <?= $available_to_return <= 0 ? 'opacity-30 pointer-events-none' : '' ?>">
                                    <?php if($available_to_return <= 0): ?>
                                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                            <span class="text-[8px] font-black text-gray-400 uppercase bg-white px-1">Full Returned</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-4 px-2 text-right font-bold text-gray-500">Rs. <?= number_format($item['price_per_unit']) ?></td>
                            <td class="py-4 px-2 text-right font-black text-gray-800 refund-row-total">Rs. 0</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Summary Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end border-t border-gray-100 pt-6">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Reason for Return</label>
                        <textarea name="remarks" rows="2" placeholder="Item damaged / customer changed mind..." 
                                  class="w-full p-3 text-sm border border-gray-200 rounded-xl focus:border-teal-500 outline-none resize-none"></textarea>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-gray-400 uppercase">Sub-Total Refund</span>
                            <span class="text-sm font-bold text-gray-600" id="totalRefundLabel">Rs. 0</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <span class="text-sm font-black text-teal-800 uppercase tracking-wider">Total Refund Amount</span>
                            <span class="text-xl font-black text-teal-700" id="finalRefundLabel">Rs. 0</span>
                        </div>
                        <input type="hidden" name="total_refund" id="totalRefundInput" value="0">
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <a href="sales_history.php" class="px-8 py-3 bg-gray-100 text-gray-500 font-bold rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
                    <button type="submit" name="process_return" id="submitBtn" disabled
                            class="px-10 py-3 bg-teal-600 text-white font-black rounded-xl shadow-xl shadow-teal-900/20 hover:bg-teal-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        Process Return
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function calculateRefundTotal(input) {
    const val = parseFloat(input.value) || 0;
    const max = parseFloat(input.dataset.max);
    const price = parseFloat(input.dataset.price);

    if (val > max) {
        input.value = max;
    }

    const rowTotal = (parseFloat(input.value) || 0) * price;
    input.closest('tr').querySelector('.refund-row-total').innerText = 'Rs. ' + Math.round(rowTotal).toLocaleString();

    updateGrandTotal();
}

function updateGrandTotal() {
    let total = 0;
    document.querySelectorAll('.return-input').forEach(input => {
        const val = parseFloat(input.value) || 0;
        const price = parseFloat(input.dataset.price);
        total += val * price;
    });

    total = Math.round(total);
    document.getElementById('totalRefundLabel').innerText = 'Rs. ' + total.toLocaleString();
    document.getElementById('finalRefundLabel').innerText = 'Rs. ' + total.toLocaleString();
    document.getElementById('totalRefundInput').value = total;

    const btn = document.getElementById('submitBtn');
    btn.disabled = (total <= 0);
}

function validateReturn() {
    const total = parseFloat(document.getElementById('totalRefundInput').value) || 0;
    if (total <= 0) {
        showAlert("Please enter a return quantity for at least one item.", "Error");
        return false;
    }
    return confirm("Are you sure you want to process this return? This will adjust stock and customer ledger.");
}
</script>

<?php if ($msg): ?>
<script>showAlert("<?= htmlspecialchars($msg) ?>", "Success");</script>
<?php endif; ?>

<?php if ($error): ?>
<script>showAlert("<?= htmlspecialchars($error) ?>", "Error");</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
