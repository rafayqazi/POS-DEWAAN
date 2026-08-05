<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasPermission('add_restock')) die("Unauthorized Access");
$pageTitle = "Return Product to Dealer";
include '../includes/header.php';

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$restock_id = $_GET['restock_id'] ?? '';
$dealer_return_id = $_GET['return_id'] ?? '';
$dealer_id_filter = $_GET['dealer_id'] ?? '';
$product_filter = $_GET['product_filter'] ?? '';
$restocks_list = [];

$restock = null;
$dealer = null;
$product = null;

$all_dealers = readCSV('dealers');
usort($all_dealers, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

if ($dealer_id_filter) {
    $all_restocks = readCSV('restocks');
    $restocks_list = array_filter($all_restocks, function($r) use ($dealer_id_filter) {
        return ($r['dealer_id'] ?? '') == $dealer_id_filter;
    });

    if ($product_filter) {
        $restocks_list = array_filter($restocks_list, function($r) use ($product_filter) {
            $pname = $r['product_name'] ?? '';
            return stripos($pname, $product_filter) !== false;
        });
    }

    // Sort by date DESC then ID DESC
    usort($restocks_list, function($a, $b) {
        $dateA = $a['date'] ?? '';
        $dateB = $b['date'] ?? '';
        if ($dateA != $dateB) {
            return strcmp($dateB, $dateA);
        }
        return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
    });
}

if ($restock_id) {
    $restock = findCSV('restocks', $restock_id);
    if ($restock) {
        if (!empty($restock['dealer_id']) && $restock['dealer_id'] !== 'OPEN_MARKET') {
            foreach ($all_dealers as $d) {
                if ($d['id'] == $restock['dealer_id']) {
                    $dealer = $d;
                    break;
                }
            }
        }
        if (!empty($restock['product_id'])) {
            $product = findCSV('products', $restock['product_id']);
        }
    }
}
?>

<style>
.glass {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}
.return-card {
    transition: all 300ms cubic-bezier(0.4, 0, 0.2, 1);
}
.return-card:hover { border-color: #0d9488; }
</style>

<div class="max-w-4xl mx-auto">
    <!-- Success/Error Messages -->
    <?php if ($msg): ?>
        <div class="bg-teal-50 text-teal-700 p-4 rounded-2xl border border-teal-100 mb-6 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-teal-900/20">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm">Success!</h4>
                    <p class="text-xs opacity-80"><?= htmlspecialchars($msg) ?></p>
                </div>
            </div>
            <?php if ($dealer_return_id): ?>
                <a href="print_return_dealer.php?id=<?= $dealer_return_id ?>" target="_blank" class="px-6 py-2 bg-teal-600 text-white font-black rounded-xl hover:bg-teal-700 transition shadow-lg shadow-teal-900/20 flex items-center gap-2 text-xs">
                    <i class="fas fa-print"></i> PRINT DEALER RETURN RECEIPT
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 p-4 rounded-2xl border border-red-100 mb-6 flex items-center gap-3 shadow-sm">
            <div class="w-10 h-10 bg-red-600 text-white rounded-xl flex items-center justify-center shadow-lg shadow-red-900/20">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h4 class="font-bold text-sm">Error!</h4>
                <p class="text-xs opacity-80"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Dealer Search -->
        <div class="bg-white p-6 rounded-[2rem] shadow-xl border border-gray-100 glass relative">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                <i class="fas fa-truck text-teal-600"></i> Search by Dealer
            </h3>
            <form action="" method="GET" id="dealerSearchForm" class="space-y-4">
                <div class="relative" id="dealerDropdownContainer">
                    <button type="button" onclick="toggleDealerDropdown()" id="dealerDropdownBtn" 
                            class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-teal-500 outline-none transition-all shadow-sm text-left flex justify-between items-center hover:border-teal-400">
                        <i class="fas fa-truck-loading absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                        <?php 
                        $selected_dealer_name = "Select Dealer Name...";
                        if ($dealer_id_filter) {
                            if ($dealer_id_filter === 'OPEN_MARKET') {
                                $selected_dealer_name = "Open Market";
                            } else {
                                foreach($all_dealers as $d) {
                                    if($d['id'] == $dealer_id_filter) {
                                        $selected_dealer_name = htmlspecialchars($d['name']);
                                        break;
                                    }
                                }
                            }
                        }
                        ?>
                        <span id="selectedDealerLabel" class="truncate"><?= $selected_dealer_name ?></span>
                        <i class="fas fa-chevron-down text-gray-400 text-[10px]"></i>
                    </button>
                    
                    <!-- Searchable Panel -->
                    <div id="dealerDropdownPanel" class="hidden absolute z-[100] w-full mt-2 bg-white border border-gray-200 rounded-2xl shadow-2xl overflow-hidden glass transform origin-top transition-all scale-95 opacity-0">
                        <div class="p-3 border-b border-gray-100 bg-gray-50/50">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                <input type="text" id="dealerSearchInput" autocomplete="off" oninput="filterDealers(this.value)" 
                                       placeholder="Type to search dealer..." 
                                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10 outline-none transition-all">
                            </div>
                        </div>
                        <div class="max-h-64 overflow-y-auto" id="dealerList">
                            <div onclick="selectDealer('', 'Select Dealer Name...')" class="dealer-nav-item p-3 text-sm hover:bg-teal-50 cursor-pointer text-gray-500 italic border-b border-gray-50 flex items-center gap-2">
                                <i class="fas fa-undo text-xs"></i> Reset Selection
                            </div>
                            <div onclick="selectDealer('OPEN_MARKET', 'Open Market')" class="dealer-item dealer-nav-item p-3 text-sm hover:bg-teal-50 cursor-pointer border-b border-gray-50 transition-colors flex flex-col" data-name="open market">
                                <span class="font-bold text-gray-700">Open Market</span>
                                <span class="text-[10px] text-gray-400 font-medium">Direct purchase</span>
                            </div>
                            <?php foreach($all_dealers as $d): ?>
                                <div onclick="selectDealer('<?= $d['id'] ?>', '<?= htmlspecialchars($d['name']) ?>')" 
                                     class="dealer-item dealer-nav-item p-3 text-sm hover:bg-teal-50 cursor-pointer border-b border-gray-50 transition-colors flex flex-col" 
                                     data-name="<?= strtolower(htmlspecialchars($d['name'])) ?>">
                                    <span class="font-bold text-gray-700"><?= htmlspecialchars($d['name']) ?></span>
                                    <span class="text-[10px] text-gray-400 font-medium"><?= htmlspecialchars($d['phone'] ?? ($d['company'] ?? 'No Details')) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="noDealerFound" class="hidden p-6 text-center text-gray-400 text-sm italic">
                            <i class="fas fa-user-slash block text-2xl mb-2 opacity-20"></i> No dealers found...
                        </div>
                    </div>
                    
                    <input type="hidden" name="dealer_id" id="dealerSelect" value="<?= htmlspecialchars($dealer_id_filter) ?>">
                </div>

                <?php if ($dealer_id_filter): ?>
                    <div class="flex gap-2 mb-2">
                        <div class="relative flex-1">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                            <input type="text" name="product_filter" value="<?= htmlspecialchars($product_filter) ?>" placeholder="Filter by Product name..." 
                                   class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-teal-500 outline-none transition-all shadow-sm">
                        </div>
                        <button type="submit" class="px-4 py-3 bg-teal-600 text-white font-black rounded-xl hover:bg-teal-700 transition-all shadow-lg shadow-teal-900/20 active:scale-95 text-xs">
                            FILTER
                        </button>
                        <?php if ($product_filter): ?>
                            <a href="?dealer_id=<?= $dealer_id_filter ?>" class="px-4 py-3 bg-red-50 text-red-500 font-black rounded-xl hover:bg-red-100 transition-all flex items-center justify-center text-xs shadow-sm border border-red-100 whitespace-nowrap">
                                <i class="fas fa-times mr-1"></i> CLEAR
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($restocks_list)): ?>
                        <div class="relative">
                            <i class="fas fa-boxes absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                            <select name="restock_id" onchange="this.form.submit()" class="w-full pl-10 pr-4 py-3 bg-teal-50 border border-teal-100 rounded-xl text-sm font-black text-teal-800 focus:ring-2 focus:ring-teal-500 outline-none transition-all shadow-sm">
                                <option value="">Select a Purchase from History...</option>
                                <?php foreach($restocks_list as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= $restock_id == $r['id'] ? 'selected' : '' ?>>
                                        Restock #<?= $r['id'] ?> - <?= htmlspecialchars($r['product_name']) ?> (Qty: <?= $r['quantity'] ?> <?= htmlspecialchars($r['unit'] ?? '') ?>) - <?= date('d M Y', strtotime($r['date'] ?? $r['created_at'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <p class="text-[10px] text-red-500 font-bold px-2 italic">No matching restock history found for this dealer.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </form>
        </div>

        <!-- Restock ID Search -->
        <div class="bg-white p-6 rounded-[2rem] shadow-xl border border-gray-100 glass">
            <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                <i class="fas fa-hashtag text-orange-600"></i> Direct Restock ID
            </h3>
            <form action="" method="GET" class="flex gap-2">
                <div class="flex-1 relative">
                    <i class="fas fa-barcode absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                    <input type="text" name="restock_id" value="<?= htmlspecialchars($restock_id) ?>" placeholder="Enter Restock ID (e.g. 5)..." 
                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl text-sm font-bold focus:ring-2 focus:ring-orange-500 outline-none transition-all shadow-sm">
                </div>
                <button type="submit" class="px-6 py-3 bg-orange-600 text-white font-black rounded-xl hover:bg-orange-700 transition-all shadow-lg shadow-orange-900/20 active:scale-95 text-xs">
                    LOAD
                </button>
            </form>
        </div>
    </div>

    <?php if ($restock_id && !$restock): ?>
        <div class="bg-red-50 text-red-600 p-6 rounded-[2rem] border border-red-100 mb-6 text-center font-bold shadow-xl shadow-red-900/5 glass">
            <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-search-minus text-2xl"></i>
            </div>
            <p class="text-xl tracking-tight">Restock Entry #<?= htmlspecialchars($restock_id) ?> not found.</p>
            <p class="text-sm opacity-60 font-medium mt-1">Please double check the ID or search by dealer name.</p>
        </div>
    <?php elseif ($restock): 
        $restock_qty = (float)$restock['quantity'];
        $already_returned = (float)($restock['returned_qty'] ?? 0);
        $available_to_return = max(0, $restock_qty - $already_returned);
        $unit_str = htmlspecialchars($restock['unit'] ?? ($product['unit'] ?? ''));
        $buy_price = (float)$restock['new_buy_price'];
        $dealer_disp_name = $dealer ? htmlspecialchars($dealer['name']) : (($restock['dealer_name'] ?? '') ?: 'Open Market');
    ?>
        <!-- Restock Details & Dealer Return Form -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 mb-6">
            <div class="flex justify-between items-start mb-6 pb-6 border-b border-gray-100">
                <div>
                    <h2 class="text-2xl font-black text-gray-800">Restock #<?= $restock['id'] ?></h2>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mt-1"><?= date('d M Y, h:i A', strtotime($restock['created_at'] ?? $restock['date'])) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest">Dealer / Supplier</p>
                    <p class="font-bold text-gray-700"><?= $dealer_disp_name ?></p>
                </div>
            </div>

            <form action="../actions/process_dealer_return.php" method="POST" id="dealerReturnForm" onsubmit="validateReturn(event)">
                <input type="hidden" name="restock_id" value="<?= $restock['id'] ?>">
                
                <table class="w-full text-left mb-6">
                    <thead>
                        <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                            <th class="py-3 px-2">Product</th>
                            <th class="py-3 px-2 text-center">Restocked Qty</th>
                            <th class="py-3 px-2 text-center">Already Returned</th>
                            <th class="py-3 px-2 text-center w-36">Return Qty</th>
                            <th class="py-3 px-2 text-right">Buy Rate</th>
                            <th class="py-3 px-2 text-right">Return Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr class="group hover:bg-gray-50/50 transition-colors">
                            <td class="py-4 px-2">
                                <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($restock['product_name']) ?></p>
                                <p class="text-[10px] text-gray-400 uppercase font-medium mt-0.5"><?= htmlspecialchars($product['category'] ?? '') ?></p>
                            </td>
                            <td class="py-4 px-2 text-center font-bold text-gray-600"><?= $restock_qty ?> <?= $unit_str ?></td>
                            <td class="py-4 px-2 text-center font-bold text-red-400"><?= $already_returned ?> <?= $unit_str ?></td>
                            <td class="py-4 px-2">
                                <div class="relative group/input">
                                    <input type="number" name="return_qty" 
                                           data-max="<?= $available_to_return ?>" 
                                           data-price="<?= $buy_price ?>"
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
                            <td class="py-4 px-2 text-right font-bold text-gray-500">Rs. <?= number_format($buy_price) ?></td>
                            <td class="py-4 px-2 text-right font-black text-gray-800 refund-row-total">Rs. 0</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Summary Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end border-t border-gray-100 pt-6">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Reason for Return to Dealer</label>
                        <textarea name="remarks" rows="2" placeholder="Damaged shipment / quality issue / expired batch..." 
                                  class="w-full p-3 text-sm border border-gray-200 rounded-xl focus:border-teal-500 outline-none resize-none"></textarea>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-bold text-gray-400 uppercase">Sub-Total Return Credit</span>
                            <span class="text-sm font-bold text-gray-600" id="totalRefundLabel">Rs. 0</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <span class="text-sm font-black text-teal-800 uppercase tracking-wider">Total Return Credit</span>
                            <span class="text-xl font-black text-teal-700" id="finalRefundLabel">Rs. 0</span>
                        </div>
                        <input type="hidden" name="total_refund" id="totalRefundInput" value="0">
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <a href="restock_history.php" class="px-8 py-3 bg-gray-100 text-gray-500 font-bold rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
                    <button type="submit" name="process_dealer_return" id="submitBtn" disabled
                            class="px-10 py-3 bg-teal-600 text-white font-black rounded-xl shadow-xl shadow-teal-900/20 hover:bg-teal-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        Process Dealer Return
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
    if (btn) btn.disabled = (total <= 0);
}

function validateReturn(event) {
    if (event) event.preventDefault();
    const total = parseFloat(document.getElementById('totalRefundInput').value) || 0;
    if (total <= 0) {
        showAlert("Please enter a return quantity for the item.", "Error");
        return false;
    }
    
    showConfirm("Are you sure you want to process this return to dealer? This will deduct inventory stock and adjust the dealer ledger.", () => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'process_dealer_return';
        hiddenInput.value = '1';
        const form = document.getElementById('dealerReturnForm');
        form.appendChild(hiddenInput);
        form.submit();
    }, "Process Dealer Return?");
    
    return false;
}

// --- Searchable Dealer Dropdown Logic ---
function toggleDealerDropdown() {
    const panel = document.getElementById('dealerDropdownPanel');
    if (!panel) return;
    const isHidden = panel.classList.contains('hidden');
    
    if (isHidden) {
        panel.classList.remove('hidden');
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
    if (!panel) return;
    panel.classList.remove('scale-100', 'opacity-100');
    panel.classList.add('scale-95', 'opacity-0');
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
        const name = item.dataset.name;
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

    const noFound = document.getElementById('noDealerFound');
    if (noFound) {
        if (foundCount === 0 && q !== '') {
            noFound.classList.remove('hidden');
        } else {
            noFound.classList.add('hidden');
        }
    }
}

function selectDealer(id, name) {
    document.getElementById('dealerSelect').value = id;
    document.getElementById('selectedDealerLabel').innerText = name;
    closeDealerDropdown();
    document.getElementById('dealerSearchForm').submit();
}

document.addEventListener('click', function(e) {
    const container = document.getElementById('dealerDropdownContainer');
    if (container && !container.contains(e.target)) {
        closeDealerDropdown();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
