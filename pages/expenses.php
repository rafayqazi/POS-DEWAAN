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
$cat_data = [];
foreach ($expenses as $e) {
    $amt = (float)$e['amount'];
    $total_expenses += $amt;
    $cat = $e['category'];
    if (!isset($cat_data[$cat])) $cat_data[$cat] = 0;
    $cat_data[$cat] += $amt;
}

$categories = ['Light Bill', 'Worker Salary', 'Rent', 'Maintenance', 'Miscellaneous', 'Food', 'Transport'];
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
    <!-- Left Column: Total & Action -->
    <div class="lg:col-span-4 flex flex-col gap-6">
        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col justify-center items-center text-center glass h-full min-h-[220px] relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-red-50 rounded-full -mr-16 -mt-16 transition-all group-hover:scale-110"></div>
            <div class="w-16 h-16 bg-red-500 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-red-200 mb-4 z-10">
                <i class="fas fa-wallet text-2xl"></i>
            </div>
            <p class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-1 z-10">Total Expenses</p>
            <h3 class="text-4xl font-black text-gray-800 tracking-tighter z-10"><?= formatCurrency($total_expenses) ?></h3>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-bold text-red-500 bg-red-50 px-3 py-1 rounded-full z-10">
                <i class="fas fa-arrow-down"></i> Cash Outflow
            </div>
        </div>
        <button onclick="openExpenseModal()" class="w-full bg-primary text-white p-5 rounded-2xl font-black hover:bg-secondary transition-all shadow-xl shadow-teal-900/20 flex items-center justify-center gap-3 group text-lg">
            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center group-hover:rotate-90 transition-all">
                <i class="fas fa-plus"></i>
            </div>
            Add New Record
        </button>
    </div>

    <!-- Right Column: Breakdown Chart -->
    <div class="lg:col-span-8 bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 glass">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h4 class="text-lg font-black text-gray-800 tracking-tight">Expense Distribution</h4>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Category breakdown for current period</p>
            </div>
            <div class="px-4 py-2 bg-gray-50 rounded-xl border border-gray-100 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
                <span class="text-[10px] font-black text-teal-700 uppercase tracking-widest">Live Sync</span>
            </div>
        </div>
        <div class="relative h-64">
            <canvas id="categoryChart"></canvas>
            <?php if (empty($expenses)): ?>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-300">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-chart-pie text-2xl"></i>
                    </div>
                    <p class="text-sm font-bold">No data available for graph</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative mb-6 animate-pulse" role="alert">
        <span class="block sm:inline"><?= $_SESSION['success']; unset($_SESSION['success']); ?></span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden glass">
    <div class="p-6 border-b border-gray-50 flex flex-col md:flex-row justify-between items-center bg-gray-50/50 gap-4">
        <h3 class="font-bold text-gray-800 flex items-center">
            <i class="fas fa-list text-teal-500 mr-2"></i> Expense History
        </h3>
        <div class="relative w-full md:w-64">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <i class="fas fa-search text-xs"></i>
            </span>
            <input type="text" id="expenseSearch" oninput="renderExpenses()" placeholder="Search expenses..." class="w-full pl-9 pr-4 py-2 rounded-xl border-gray-200 border focus:ring-2 focus:ring-primary focus:outline-none transition text-sm">
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 font-bold w-12 text-center">Sno#</th>
                    <th class="px-6 py-4 font-bold">Date</th>
                    <th class="px-6 py-4 font-bold">Category</th>
                    <th class="px-6 py-4 font-bold">Title</th>
                    <th class="px-6 py-4 font-bold text-right">Amount</th>
                    <th class="px-6 py-4 font-bold">Description</th>
                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="expenseTableBody" class="divide-y divide-gray-50">
                <!-- Data rendered by JavaScript -->
            </tbody>
        </table>
    </div>
    <div id="expensePagination" class="px-6 py-4 bg-gray-50 border-t border-gray-100"></div>
</div>
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
            
            <form id="expenseForm" class="space-y-5">
                <input type="hidden" id="expenseId">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Category</label>
                    <select id="expenseCategory" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Title / Subject</label>
                    <input type="text" id="expenseTitle" required placeholder="e.g. Electricity Bill Jan" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Amount</label>
                        <input type="number" step="0.01" id="expenseAmount" required placeholder="0.00" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none font-bold text-red-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Date</label>
                        <input type="date" id="expenseDate" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Description (Optional)</label>
                    <textarea id="expenseDescription" rows="3" placeholder="Enter more details here..." class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none resize-none"></textarea>
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
    const allExpenses = <?= json_encode($expenses) ?>;
    let currentPage_Expense = 1;
    const pageSize_Expense = 200;

    function formatCurrencyJS(amount) {
        return 'Rs.' + new Intl.NumberFormat('en-US').format(amount);
    }

    function renderExpenses() {
        const term = document.getElementById('expenseSearch').value.toLowerCase();
        
        let filtered = allExpenses.filter(e => {
            return e.title.toLowerCase().includes(term) || 
                   e.category.toLowerCase().includes(term) || 
                   (e.description || '').toLowerCase().includes(term);
        });

        const totalItems = filtered.length;
        const paginated = Pagination.paginate(filtered, currentPage_Expense, pageSize_Expense);
        const tbody = document.getElementById('expenseTableBody');

        if (totalItems === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-10 text-center text-gray-400"><i class="fas fa-folder-open text-4xl mb-3 block"></i>No expenses matched your search.</td></tr>`;
            Pagination.render('expensePagination', 0, 1, pageSize_Expense, changePage_Expense);
            return;
        }

        let html = '';
        paginated.forEach((e, index) => {
            const displayIndex = (currentPage_Expense - 1) * pageSize_Expense + index + 1;
            const formattedDate = new Date(e.date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            
            html += `
                <tr class="hover:bg-gray-50/50 transition-colors group">
                    <td class="px-6 py-4 whitespace-nowrap text-center text-xs font-mono text-gray-400">${displayIndex}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-1 rounded-md uppercase">${formattedDate}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-sm font-medium text-gray-700 bg-gray-100 px-3 py-1 rounded-full border border-gray-200">${e.category}</span>
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-gray-800">${e.title}</td>
                    <td class="px-6 py-4 text-sm font-bold text-red-600 text-right">${formatCurrencyJS(e.amount)}</td>
                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">${e.description || ''}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <button onclick='editExpense(${JSON.stringify(e)})' class="text-teal-600 hover:text-teal-900 bg-teal-50 p-2 rounded-lg transition-colors border border-teal-100">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmDelete(${e.id})" class="text-red-600 hover:text-red-900 bg-red-50 p-2 rounded-lg transition-colors border border-red-100">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
        Pagination.render('expensePagination', totalItems, currentPage_Expense, pageSize_Expense, changePage_Expense);
    }

    function changePage_Expense(page) {
        currentPage_Expense = page;
        renderExpenses();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

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
        showConfirm('Are you sure you want to delete this expense record?', async () => {
            const res = await fetch('../actions/delete_expense.php?id=' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.status === 'success') {
                allExpenses = allExpenses.filter(e => e.id != id);
                renderExpenses();
            } else {
                showAlert('Failed to delete expense.', 'Error');
            }
        }, 'Confirm Delete');
    }

    async function saveExpense(e) {
        e.preventDefault();
        const id = document.getElementById('expenseId').value;
        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('category',    document.getElementById('expenseCategory').value);
        fd.append('title',       document.getElementById('expenseTitle').value);
        fd.append('amount',      document.getElementById('expenseAmount').value);
        fd.append('date',        document.getElementById('expenseDate').value);
        fd.append('description', document.getElementById('expenseDescription').value);

        const res = await fetch('../actions/save_expense.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const data = await res.json();

        if (data.status === 'success') {
            if (data.is_edit) {
                const idx = allExpenses.findIndex(ex => ex.id == data.expense.id);
                if (idx !== -1) allExpenses[idx] = data.expense;
            } else {
                allExpenses.unshift(data.expense);
            }
            closeExpenseModal();
            renderExpenses();
            showAlert(data.message, 'Success');
        } else {
            showAlert(data.message || 'Failed to save expense.', 'Error');
        }
    }

    document.getElementById('expenseForm').addEventListener('submit', saveExpense);
    
    // Chart.js Logic
    function initCategoryChart() {
        const catData = <?= json_encode($cat_data) ?>;
        const labels = Object.keys(catData);
        const data = Object.values(catData);

        if (labels.length === 0) return;

        const ctx = document.getElementById('categoryChart').getContext('2d');
        const colors = [
            '#0f766e', // Teal
            '#f59e0b', // Amber
            '#ef4444', // Red
            '#3b82f6', // Blue
            '#8b5cf6', // Violet
            '#ec4899', // Pink
            '#10b981', // Emerald
            '#6366f1', // Indigo
            '#f97316'  // Orange
        ];

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    hoverBackgroundColor: colors.map(c => c + 'dd'),
                    borderWidth: 8,
                    borderColor: '#ffffff',
                    hoverOffset: 20,
                    borderRadius: 10
                }]
            },
            plugins: [{
                id: 'centerText',
                afterDraw: (chart) => {
                    const { ctx, chartArea: { top, bottom, left, right, width, height } } = chart;
                    ctx.save();
                    const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                    const formattedTotal = 'Rs.' + new Intl.NumberFormat().format(total);
                    
                    ctx.font = 'black 16px Inter';
                    ctx.textAlign = 'center';
                    ctx.fillStyle = '#9ca3af';
                    ctx.fillText('TOTAL', width / 2 + left, height / 2 + top - 10);
                    
                    ctx.font = 'black 20px Inter';
                    ctx.fillStyle = '#111827';
                    ctx.fillText(formattedTotal, width / 2 + left, height / 2 + top + 15);
                }
            }],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '80%',
                plugins: {
                    legend: {
                        position: 'right',
                        align: 'center',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 25,
                            font: {
                                size: 12,
                                weight: '800',
                                family: "'Inter', sans-serif"
                            },
                            color: '#374151',
                            generateLabels: (chart) => {
                                const data = chart.data;
                                return data.labels.map((label, i) => ({
                                    text: `${label.toUpperCase()}`,
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].backgroundColor[i],
                                    lineWidth: 0,
                                    hidden: isNaN(data.datasets[0].data[i]) || chart.getDatasetMeta(0).data[i].hidden,
                                    index: i
                                }));
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 118, 110, 0.95)',
                        titleFont: { size: 13, weight: '900', family: "'Inter', sans-serif" },
                        bodyFont: { size: 12, weight: '600', family: "'Inter', sans-serif" },
                        padding: 16,
                        cornerRadius: 15,
                        displayColors: true,
                        boxPadding: 8,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = ((value / total) * 100).toFixed(1);
                                return ` Rs. ${new Intl.NumberFormat().format(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderExpenses();
        initCategoryChart();
    });
</script>

<?php include '../includes/footer.php'; echo '</main></div></body></html>'; ?>
