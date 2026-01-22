<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$pageTitle = "Expense Management";
include '../includes/header.php';

$expenses = readCSV('expenses');
usort($expenses, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

$total_expenses = 0;
foreach ($expenses as $e) {
    $total_expenses += (float)$e['amount'];
}

$categories = ['Light Bill', 'Worker Salary', 'Rent', 'Maintenance', 'Miscellaneous', 'Food', 'Transport'];
?>

<div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
        <div class="w-12 h-12 bg-red-100 text-red-600 rounded-xl flex items-center justify-center">
            <i class="fas fa-wallet text-xl"></i>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Total Expenses</p>
            <h3 class="text-2xl font-bold text-gray-800"><?= formatCurrency($total_expenses) ?></h3>
        </div>
    </div>
    <button onclick="openExpenseModal()" class="bg-primary text-white px-6 py-3 rounded-xl font-bold hover:bg-secondary transition-all shadow-lg shadow-teal-700/20 flex items-center gap-2">
        <i class="fas fa-plus"></i> Add New Expense
    </button>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative mb-6 animate-pulse" role="alert">
        <span class="block sm:inline"><?= $_SESSION['success']; unset($_SESSION['success']); ?></span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden glass">
    <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
        <h3 class="font-bold text-gray-800 flex items-center">
            <i class="fas fa-list text-teal-500 mr-2"></i> Expense History
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 font-bold">Date</th>
                    <th class="px-6 py-4 font-bold">Category</th>
                    <th class="px-6 py-4 font-bold">Title</th>
                    <th class="px-6 py-4 font-bold">Amount</th>
                    <th class="px-6 py-4 font-bold">Description</th>
                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                            <i class="fas fa-folder-open text-4xl mb-3 block"></i>
                            No expenses recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-1 rounded-md uppercase">
                                    <?= date('d M, Y', strtotime($e['date'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium text-gray-700 bg-gray-100 px-3 py-1 rounded-full border border-gray-200"><?= $e['category'] ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-gray-800"><?= $e['title'] ?></td>
                            <td class="px-6 py-4 text-sm font-bold text-red-600"><?= formatCurrency($e['amount']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate"><?= $e['description'] ?></td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button onclick='editExpense(<?= json_encode($e) ?>)' class="text-teal-600 hover:text-teal-900 bg-teal-50 p-2 rounded-lg transition-colors border border-teal-100">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="confirmDelete(<?= $e['id'] ?>)" class="text-red-600 hover:text-red-900 bg-red-50 p-2 rounded-lg transition-colors border border-red-100">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Expense Modal -->
<div id="expenseModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl max-w-md w-full transform transition-all">
        <div class="p-8">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalTitle" class="text-2xl font-bold text-gray-800 tracking-tight">Add New Expense</h3>
                <button onclick="closeExpenseModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form action="../actions/save_expense.php" method="POST" class="space-y-5">
                <input type="hidden" name="id" id="expenseId">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Category</label>
                    <select name="category" id="expenseCategory" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Title / Subject</label>
                    <input type="text" name="title" id="expenseTitle" required placeholder="e.g. Electricity Bill Jan" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Amount</label>
                        <input type="number" step="0.01" name="amount" id="expenseAmount" required placeholder="0.00" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none font-bold text-red-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Date</label>
                        <input type="date" name="date" id="expenseDate" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Description (Optional)</label>
                    <textarea name="description" id="expenseDescription" rows="3" placeholder="Enter more details here..." class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none resize-none"></textarea>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-primary text-white font-bold py-4 rounded-xl hover:bg-secondary transition-all shadow-lg shadow-teal-700/20 active:scale-[0.98]">
                        Save Expense Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openExpenseModal() {
        document.getElementById('modalTitle').innerText = 'Add New Expense';
        document.getElementById('expenseId').value = '';
        document.getElementById('expenseCategory').value = 'Miscellaneous';
        document.getElementById('expenseTitle').value = '';
        document.getElementById('expenseAmount').value = '';
        document.getElementById('expenseDescription').value = '';
        document.getElementById('expenseDate').value = '<?= date('Y-m-d') ?>';
        
        const modal = document.getElementById('expenseModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeExpenseModal() {
        const modal = document.getElementById('expenseModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function editExpense(expense) {
        document.getElementById('modalTitle').innerText = 'Edit Expense';
        document.getElementById('expenseId').value = expense.id;
        document.getElementById('expenseCategory').value = expense.category;
        document.getElementById('expenseTitle').value = expense.title;
        document.getElementById('expenseAmount').value = expense.amount;
        document.getElementById('expenseDescription').value = expense.description;
        document.getElementById('expenseDate').value = expense.date;
        
        const modal = document.getElementById('expenseModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function confirmDelete(id) {
        showConfirm('Are you sure you want to delete this expense record?', () => {
            window.location.href = '../actions/delete_expense.php?id=' + id;
        }, 'Confirm Delete');
    }
</script>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
