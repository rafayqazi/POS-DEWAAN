<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();

// AJAX Helper to update hierarchy — MUST be before any HTML output
if (isset($_GET['action']) && $_GET['action'] == 'update_hierarchy') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data && isset($data['hierarchy'])) {
        $units = readCSV('units');
        $id_to_parent = $data['hierarchy']; // Array: [{id, parent_id}, ...]

        // Build a lookup for quick access
        $map = [];
        foreach ($id_to_parent as $update) {
            $map[$update['id']] = $update['parent_id'];
        }

        foreach ($units as &$u) {
            if (array_key_exists($u['id'], $map)) {
                $u['parent_id'] = $map[$u['id']];
            }
        }
        unset($u);

        writeCSV('units', $units);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}


$pageTitle = "Manage Units";
include '../includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action == 'add') {
        $name = cleanInput($_POST['name']);
        $parent_id = (int)$_POST['parent_id'];
        if ($name) {
            insertCSV('units', [
                'name' => $name,
                'parent_id' => $parent_id
            ]);
            $message = "Unit '$name' added.";
        }
    } elseif ($action == 'delete') {
        $id = $_POST['id'];
        deleteCSV('units', $id);
        $message = "Unit deleted.";
    }
}

$units = readCSV('units');
$unitTree = buildUnitTree($units);

function renderUnitList($tree, $level = 0) {
    if (empty($tree) && $level == 0) return '<ul class="nested-sortable min-h-[50px]" data-parent-id="0"></ul>';
    if (empty($tree)) return '';
    
    $parentId = $level == 0 ? 0 : $tree[0]['parent_id'];
    $html = '<ul class="nested-sortable space-y-2 ' . ($level > 0 ? 'ml-8 mt-2 border-l-2 border-dashed border-gray-100 pl-4' : '') . '" data-parent-id="' . $parentId . '">';
    
    foreach ($tree as $unit) {
        $html .= '
        <li class="unit-item-container mb-2" data-id="' . $unit['id'] . '">
            <div class="flex justify-between items-center p-3 bg-white rounded-xl border border-gray-100 hover:border-teal-400 hover:shadow-md transition group drag-handle cursor-move">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center mr-3 text-gray-400 group-hover:bg-teal-50 group-hover:text-teal-500 transition">
                        <i class="fas fa-grip-vertical text-xs"></i>
                    </div>
                    <div>
                        <span class="font-bold text-gray-700">' . htmlspecialchars($unit['name']) . '</span>
                    </div>
                </div>
                <div class="flex space-x-1">
                    <button onclick="addChildUnit(' . $unit['id'] . ', \'' . addslashes($unit['name']) . '\')" class="w-7 h-7 rounded-lg bg-teal-50 text-teal-600 hover:bg-teal-600 hover:text-white transition flex items-center justify-center" title="Add Child">
                        <i class="fas fa-plus text-[10px]"></i>
                    </button>
                    <button onclick="confirmUnitDelete(' . $unit['id'] . ', \'' . addslashes($unit['name']) . '\')" class="w-7 h-7 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition flex items-center justify-center" title="Delete">
                        <i class="fas fa-trash-alt text-[10px]"></i>
                    </button>
                </div>
            </div>';
            
        // Always render a ul inside to allow children to be dropped in
        $html .= renderUnitList($unit['children'], $level + 1);
        
        if (!empty($unit['children']) || $level < 3) { // Limit depth if desired, but keep it flexible
             // Recursion handles the sub-ul
        }

        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}
?>

<script src="../assets/vendor/chartjs/chart.min.js"></script> <!-- Placeholder for other assets if needed, but we need Sortable -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
    .nested-sortable {
        min-height: 5px;
    }
    .sortable-ghost {
        opacity: 0.4;
        background: #f0fdfa !important;
        border: 2px dashed #0d9488 !important;
    }
    .sortable-chosen {
        background: #fff;
    }
    .unit-item-container {
        touch-action: none;
    }
</style>

<div class="max-w-2xl">
    <?php if($message): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded-lg shadow-sm border-l-4 border-green-500 mb-6 flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Add Form -->
        <div class="lg:col-span-5">
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden sticky top-4">
                <div class="bg-teal-600 p-4 text-white flex items-center shrink-0">
                    <i class="fas fa-plus-circle mr-3"></i>
                    <h2 class="text-sm font-bold uppercase tracking-widest">Add New Unit</h2>
                </div>
                <div class="p-5">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Unit Name</label>
                            <input type="text" name="name" id="unit_name_input" placeholder="e.g. Piece" required 
                                   class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition outline-none text-sm font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1 ml-1">Parent Unit</label>
                            <select name="parent_id" id="parent_id_select" class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-teal-600 transition outline-none text-sm appearance-none bg-white font-medium">
                                <option value="0">None (Base Unit)</option>
                                <?= renderUnitOptions($unitTree) ?>
                            </select>
                        </div>
                        <button type="submit" class="w-full bg-teal-600 text-white px-6 py-4 rounded-xl hover:bg-teal-700 transition shadow-lg shadow-teal-700/20 flex items-center justify-center font-black uppercase tracking-widest text-xs">
                            Create Unit
                        </button>
                    </form>
                    
                    <div class="mt-8 p-4 bg-blue-50 rounded-xl border border-blue-100">
                        <div class="flex items-center mb-2">
                             <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                             <h4 class="text-[10px] font-black text-blue-800 uppercase tracking-tight">Pro Tip</h4>
                        </div>
                        <p class="text-[10px] text-blue-700 leading-relaxed">
                            You can <b>Drag & Drop</b> units on the right to rearrange them or change their parents instantly.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit List -->
        <div class="lg:col-span-7">
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden min-h-[500px]">
                <div class="bg-gray-800 p-4 text-white flex justify-between items-center">
                    <div class="flex items-center">
                        <i class="fas fa-list-ul mr-3 text-teal-400"></i>
                        <h2 class="text-sm font-bold uppercase tracking-widest">Unit Hierarchy</h2>
                    </div>
                    <span class="text-[9px] bg-white/10 px-2 py-0.5 rounded font-black uppercase tracking-tighter opacity-60">WordPress Style</span>
                </div>
                
                <div class="p-6 bg-gray-50/30">
                    <div id="mainUnitSortable" class="nested-sortable">
                        <?php if (count($units) > 0): ?>
                            <?= renderUnitList($unitTree) ?>
                        <?php else: ?>
                            <div class="p-12 text-center text-gray-300">
                                <i class="fas fa-balance-scale text-5xl mb-4 opacity-20"></i>
                                <p class="text-sm font-medium italic">No units created yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="saveStatus" class="fixed bottom-8 right-8 bg-gray-900 text-white px-6 py-3 rounded-2xl shadow-2xl flex items-center transform translate-y-32 transition-transform duration-300 z-50">
                        <i class="fas fa-sync-alt fa-spin mr-3 text-teal-400"></i>
                        <span class="text-xs font-bold uppercase tracking-widest">Saving Hierarchy...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmUnitDelete(id, name) {
    showConfirm(`Are you sure you want to delete the unit "${name}"?`, () => {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }, 'Delete Unit');
}

function addChildUnit(parentId, parentName) {
    document.getElementById('parent_id_select').value = parentId;
    document.getElementById('unit_name_input').focus();
    // Smooth scroll to form
    document.querySelector('form').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Drag and Drop Logic
document.addEventListener('DOMContentLoaded', function() {
    const nestedSortables = document.querySelectorAll('.nested-sortable');
    
    nestedSortables.forEach(el => {
        new Sortable(el, {
            group: 'nested',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                saveHierarchy();
            }
        });
    });
});

async function saveHierarchy() {
    const status = document.getElementById('saveStatus');
    status.classList.remove('translate-y-32');
    
    // Build flat hierarchy list from current DOM
    const hierarchy = [];
    document.querySelectorAll('.unit-item-container').forEach(item => {
        const id = item.dataset.id;
        // Walk up: parent <ul> → its parent <li.unit-item-container> (if any) = the parent unit
        const parentUl = item.parentElement; // the <ul class="nested-sortable">
        const parentLi = parentUl ? parentUl.closest('.unit-item-container') : null;
        const parentId = parentLi ? parentLi.dataset.id : '0';
        hierarchy.push({ id, parent_id: parentId });
    });
    
    try {
        const response = await fetch('units.php?action=update_hierarchy', {
            method: 'POST',
            body: JSON.stringify({ hierarchy }),
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        if (result.status === 'success') {
            setTimeout(() => {
                status.classList.add('translate-y-32');
                // Refresh dropdowns without page reload if possible, but for now reload is safest
                // location.reload(); 
            }, 1000);
        }
    } catch (e) {
        console.error(e);
        showAlert('Failed to save hierarchy change.', 'Error');
        status.classList.add('translate-y-32');
    }
}
</script>

<?php 
include '../includes/footer.php'; 
echo '</main></div></body></html>'; 
?>
