<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$pageTitle = "Manage Units";
include '../includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action == 'add') {
        $name = cleanInput($_POST['name']);
        if ($name) {
            insertCSV('units', ['name' => $name]);
            $message = "Unit '$name' added.";
        }
    } elseif ($action == 'delete') {
        $id = $_POST['id'];
        deleteCSV('units', $id);
        $message = "Unit deleted.";
    }
}

$units = readCSV('units');
?>

<div class="max-w-xl">
    <?php if($message): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded-lg shadow-sm border-l-4 border-green-500 mb-6 flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="bg-teal-600 p-4 text-white flex items-center">
            <i class="fas fa-balance-scale mr-3"></i>
            <h2 class="text-lg font-bold">Unit Management</h2>
        </div>
        
        <div class="p-6">
            <form method="POST" class="flex gap-2 mb-8">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" placeholder="New Unit (e.g. KG, Ctn)" required 
                       class="flex-1 rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-teal-600 focus:border-teal-600 transition outline-none">
                <button type="submit" class="bg-teal-600 text-white px-6 py-3 rounded-xl hover:bg-teal-700 transition shadow-lg flex items-center">
                    <i class="fas fa-plus mr-2 text-sm"></i> Add
                </button>
            </form>

            <div class="space-y-2">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Existing Units</h3>
                <div class="divide-y divide-gray-50 border rounded-xl overflow-hidden bg-gray-50/30">
                    <?php if (count($units) > 0): ?>
                        <?php foreach($units as $u): ?>
                        <div class="flex justify-between items-center p-4 hover:bg-white transition group">
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($u['name']) ?></span>
                            <div class="opacity-0 group-hover:opacity-100 transition flex space-x-2">
                                <button onclick="confirmUnitDelete(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition flex items-center justify-center">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-400 italic">No units found.</div>
                    <?php endif; ?>
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
</script>

<?php 
include '../includes/footer.php'; 
echo '</main></div></body></html>'; 
?>
