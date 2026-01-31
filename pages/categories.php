<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$pageTitle = "Manage Categories";
include '../includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action == 'add') {
        $name = cleanInput($_POST['name']);
        if ($name) {
            insertCSV('categories', ['name' => $name]);
            $message = "Category '$name' added.";
        }
    } elseif ($action == 'delete') {
        $id = $_POST['id'];
        deleteCSV('categories', $id);
        $message = "Category deleted.";
    }
}

$categories = readCSV('categories');
?>

<div class="max-w-xl">
    <?php if($message): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded-lg shadow-sm border-l-4 border-green-500 mb-6 flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="bg-primary p-4 text-white flex items-center">
            <i class="fas fa-tags mr-3"></i>
            <h2 class="text-lg font-bold">Category Management</h2>
        </div>
        
        <div class="p-6">
            <div class="flex flex-col md:flex-row gap-3 mb-8">
                <form method="POST" class="flex flex-1 gap-2">
                    <input type="hidden" name="action" value="add">
                    <div class="relative flex-1">
                        <i class="fas fa-plus absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" name="name" placeholder="New Category Name (e.g. Fertilizer)" required 
                               class="w-full rounded-xl border-gray-200 border pl-10 pr-3 py-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none text-sm font-medium">
                    </div>
                    <button type="submit" class="bg-primary text-white px-6 py-3 rounded-xl hover:bg-secondary transition shadow-lg flex items-center font-bold text-sm">
                        ADD
                    </button>
                </form>
                <div class="relative flex-1 md:max-w-[200px]">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" id="catSearch" placeholder="Search..." 
                           class="w-full rounded-xl border-gray-200 border pl-10 pr-3 py-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none text-sm font-medium">
                </div>
            </div>

            <div class="space-y-2">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Existing Categories</h3>
                <div class="divide-y divide-gray-50 border rounded-xl overflow-hidden bg-gray-50/30" id="categoryList">
                    <!-- Rendered by JS -->
                </div>
                <div id="catPagination" class="mt-4"></div>
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
const allCategories = <?= json_encode($categories) ?>;
let currentPage_Cat = 1;
const pageSize_Cat = 200;

function renderCategories() {
    const term = document.getElementById('catSearch').value.toLowerCase();
    const filtered = allCategories.filter(c => c.name.toLowerCase().includes(term));
    const totalItems = filtered.length;
    
    const paginated = Pagination.paginate(filtered, currentPage_Cat, pageSize_Cat);
    const container = document.getElementById('categoryList');

    if (totalItems === 0) {
        container.innerHTML = '<div class="p-8 text-center text-gray-400 italic text-sm">No categories found matching your search.</div>';
        Pagination.render('catPagination', 0, 1, pageSize_Cat, changePage_Cat);
        return;
    }

    let html = '';
    paginated.forEach((cat, index) => {
        const sn = (currentPage_Cat - 1) * pageSize_Cat + index + 1;
        html += `
            <div class="flex justify-between items-center p-4 hover:bg-white transition group border-b last:border-0 border-gray-100">
                <div class="flex items-center">
                    <span class="w-8 text-xs font-mono text-gray-400">${sn}.</span>
                    <span class="font-bold text-gray-700 text-sm">${cat.name}</span>
                </div>
                <div class="opacity-0 group-hover:opacity-100 transition flex space-x-2">
                    <button onclick="confirmCategoryDelete(${cat.id}, '${cat.name.replace(/'/g, "\\'")}')" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition flex items-center justify-center">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
    Pagination.render('catPagination', totalItems, currentPage_Cat, pageSize_Cat, changePage_Cat);
}

function changePage_Cat(page) {
    currentPage_Cat = page;
    renderCategories();
}

function confirmCategoryDelete(id, name) {
    showConfirm(`Are you sure you want to delete the category "${name}"?`, () => {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }, 'Delete Category');
}

document.getElementById('catSearch').addEventListener('input', () => {
    currentPage_Cat = 1;
    renderCategories();
});

document.addEventListener('DOMContentLoaded', renderCategories);
</script>

<?php 
include '../includes/footer.php'; 
echo '</main></div></body></html>'; 
?>
