<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fashion Shines POS</title>
    <?php $base = (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? '../' : ''; ?>
    <link rel="icon" type="image/png" href="<?= $base ?>assets/img/favicon.png">
    <script src="<?= $base ?>assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0f766e', // Teal 700
                        secondary: '#134e4a', // Teal 900
                        accent: '#f59e0b', // Amber 500
                    }
                }
            }
        }
    </script>
    <link href="<?= $base ?>assets/vendor/inter-font/inter.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/all.min.css">
    <script src="<?= $base ?>assets/vendor/chartjs/chart.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .glass {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .sidebar-link-active {
            background: #0f766e;
            border-left: 4px solid #f59e0b;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

<?php if (isset($_SESSION['user_id'])): ?>
    <?php
    // Global Lockdown Check
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'lockdown.php') {
        $update_status = getUpdateStatus();
        if ($update_status['available'] && $update_status['overdue']) {
            $base = (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? '../' : '';
            redirect($base . 'pages/lockdown.php');
        }
    }
    ?>
    <div class="flex flex-1 h-screen overflow-hidden">
        <!-- Sidebar -->
        <!-- Sidebar -->
        <!-- Added mobile responsive classes: fixed on mobile, translate-x-full to hide, md:relative to show -->
        <aside class="fixed inset-y-0 left-0 z-50 w-64 bg-secondary text-white shadow-2xl flex flex-col transition-transform duration-300 transform -translate-x-full md:translate-x-0 md:relative border-r border-teal-800/50" id="sidebar">
            <div class="p-6 flex items-center justify-between border-b border-teal-800 h-24 bg-teal-900/20">
                <div class="sidebar-text truncate">
                    <h1 class="text-2xl font-bold tracking-tight text-accent logo-text">Fashion Shines</h1>
                    <p class="text-[10px] text-teal-400 font-medium uppercase tracking-[0.2em]">Management System</p>
                </div>
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-teal-800 transition-all text-teal-400 hover:text-white outline-none ring-1 ring-teal-800 hover:ring-teal-600">
                    <i class="fas fa-bars text-lg" id="sidebarToggleIcon"></i>
                </button>
            </div>
            
            <nav class="flex-1 overflow-y-auto py-4">
                <ul class="space-y-1">
                    <li>
                        <a href="<?= $base ?>index.php" class="flex items-center px-6 py-4 hover:bg-teal-800/50 transition-all group <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'sidebar-link-active shadow-lg' : '' ?>" title="Dashboard">
                            <i class="fas fa-th-large w-6 text-xl group-hover:scale-110 transition-transform"></i>
                            <span class="font-bold ml-4 sidebar-text">Dashboard</span>
                        </a>
                    </li>
                    <!-- Dropdown for Inventory -->
                    <li class="relative">
                        <button onclick="toggleDropdown('inventoryDropdown')" class="w-full flex items-center justify-between px-6 py-4 hover:bg-teal-800 transition-colors cursor-pointer outline-none" title="Inventory">
                            <div class="flex items-center">
                                <i class="fas fa-boxes w-6 text-xl text-teal-300"></i>
                                <span class="font-medium ml-4 sidebar-text">Inventory</span>
                            </div>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300 sidebar-text" id="inventoryDropdownIcon"></i>
                        </button>
                        <ul id="inventoryDropdown" class="bg-teal-900/50 hidden overflow-hidden transition-all duration-300">
                            <li>
                                <a href="<?= $base ?>pages/inventory.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Add Inventory">
                                    <i class="fas fa-plus-circle mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Add Inventory</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= $base ?>pages/check_inventory.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'check_inventory.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Check Inventory">
                                    <i class="fas fa-search mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Check Inventory</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= $base ?>pages/return_product.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'return_product.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Return Product">
                                    <i class="fas fa-undo mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Return Product</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- Dropdown for Restock Management -->
                    <li class="relative">
                        <button onclick="toggleDropdown('restockDropdown')" class="w-full flex items-center justify-between px-6 py-4 hover:bg-teal-800 transition-colors cursor-pointer outline-none" title="Inventory Restock">
                            <div class="flex items-center">
                                <i class="fas fa-plus-square w-6 text-xl text-accent"></i>
                                <span class="font-bold ml-4 sidebar-text text-teal-50">Inventory Restock</span>
                            </div>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300 sidebar-text" id="restockDropdownIcon"></i>
                        </button>
                        <ul id="restockDropdown" class="bg-teal-900/50 hidden overflow-hidden transition-all duration-300">
                            <li>
                                <a href="<?= $base ?>pages/quick_restock.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'quick_restock.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Quick Restock">
                                    <i class="fas fa-plus-square mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Quick Restock</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= $base ?>pages/restock_history.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'restock_history.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Restock History">
                                    <i class="fas fa-history mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Restock History</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="<?= $base ?>pages/pos.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="POS / Sale">
                            <i class="fas fa-cash-register w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">POS / Sale</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base ?>pages/customers.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Customers">
                            <i class="fas fa-users w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">Customers</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base ?>pages/dealers.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'dealers.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Dealers">
                            <i class="fas fa-truck w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">Dealers</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base ?>pages/expenses.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Expenses">
                            <i class="fas fa-wallet w-6 text-xl text-red-300"></i>
                            <span class="font-medium ml-4 sidebar-text text-red-200">Expenses</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base ?>pages/reports.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Reports">
                            <i class="fas fa-chart-line w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">Reports</span>
                        </a>
                    </li>
                    <!-- Dropdown for Product Setup -->
                    <li class="relative">
                        <button onclick="toggleDropdown('setupDropdown')" class="w-full flex items-center justify-between px-6 py-4 hover:bg-teal-800 transition-colors cursor-pointer outline-none" title="Product Setup">
                            <div class="flex items-center">
                                <i class="fas fa-tools w-6 text-xl"></i>
                                <span class="font-medium ml-4 sidebar-text">Product Setup</span>
                            </div>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-300 sidebar-text" id="setupDropdownIcon"></i>
                        </button>
                        <ul id="setupDropdown" class="bg-teal-900/50 hidden overflow-hidden transition-all duration-300">
                            <li>
                                <a href="<?= $base ?>pages/categories.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Categories">
                                    <i class="fas fa-tags mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Categories</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?= $base ?>pages/units.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'units.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Units">
                                    <i class="fas fa-balance-scale mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Units</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="<?= $base ?>pages/settings.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Settings">
                            <i class="fas fa-cog w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= $base ?>pages/backup_restore.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'backup_restore.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Backup & Restore">
                            <i class="fas fa-database w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">Backup/Restore</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="p-6">
                <a href="<?= $base ?>logout.php" class="flex items-center justify-center w-full px-4 py-3 bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500 hover:text-white rounded-xl transition-all duration-300 text-sm font-bold shadow-lg shadow-red-500/5">
                    <i class="fas fa-power-off"></i> 
                    <span class="ml-3 sidebar-text">Sign Out</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4 md:p-8 relative">
            <!-- Mobile Toggle Button -->
            <button onclick="toggleSidebarMobile()" class="md:hidden fixed top-4 right-4 z-40 bg-teal-700 text-white p-2 rounded-lg shadow-lg">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Overlay for mobile -->
            <div id="sidebarOverlay" onclick="toggleSidebarMobile()" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden glass"></div>

            <!-- Update Notification Banner -->
            <div id="globalUpdateBanner" class="hidden mb-6 p-4 bg-orange-600 text-white rounded-2xl flex flex-col md:flex-row items-center justify-between shadow-xl animate-bounce-short z-30">
                <div class="flex items-center gap-4 mb-4 md:mb-0">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-cloud-download-alt text-lg"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm">System Update Available!</h4>
                        <p class="text-xs text-orange-100" id="updateBannerMsg">A new version of the software is ready to install.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="applyGlobalUpdate()" id="globalUpdateBtn" class="px-6 py-2 bg-white text-orange-600 rounded-xl font-bold hover:bg-orange-50 transition active:scale-95 text-xs uppercase">
                        Update Now
                    </button>
                    <button onclick="document.getElementById('globalUpdateBanner').remove()" class="p-2 text-white/50 hover:text-white transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100 glass gap-4">
               <div class="w-full">
                   <h2 class="text-2xl font-bold text-gray-800 tracking-tight"><?= isset($pageTitle) ? $pageTitle : 'Dashboard' ?></h2>
                   <div class="flex items-center mt-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-teal-500 mr-2"></span>
                        <p class="text-gray-400 text-[10px] font-semibold uppercase tracking-[0.15em]"><?= date('M Y') ?> Overview</p>
                   </div>
               </div>
               <div class="text-right">
                   <div class="text-sm font-semibold text-teal-700 bg-teal-50 px-4 py-2 rounded-xl border border-teal-100 inline-block">
                       <i class="far fa-calendar-alt mr-2 text-teal-400"></i><?= date('l, d M Y') ?>
                   </div>
               </div>
            </div>
            
    <style>
        #sidebar.collapsed { width: 80px; }
        #sidebar.collapsed .sidebar-text { display: none; }
        #sidebar.collapsed .logo-text { font-size: 1.25rem; }
        #sidebar.collapsed .logo-text::after { content: 'D'; }
        #sidebar.collapsed .logo-text { display: none; }
        #sidebar.collapsed .logo-min { display: block !important; }
        .logo-min { display: none; }
    </style>

    <script>
        // Use a separate function for mobile toggle to avoid conflict with desktop collapse
        function toggleSidebarMobile() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            // Toggle translate class for mobile
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }

        function toggleSidebar() {
            // Desktop toggle (Collapse/Expand)
            if (window.innerWidth >= 768) {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('w-64');
                sidebar.classList.toggle('w-20');
                
                const texts = document.querySelectorAll('.sidebar-text');
                texts.forEach(t => t.classList.toggle('hidden'));
                
                // Save state
                const isCollapsed = sidebar.classList.contains('w-20');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            } else {
                // Should not really be called on mobile via the internal button as it's hidden, 
                // but just in case, redirect to mobile toggle
                toggleSidebarMobile();
            }
        }

        function toggleDropdown(id) {
            const dropdown = document.getElementById('dropdown-' + id) || document.getElementById(id); // Handle both potential ID formats if needed, currently using id directly
            const icon = document.getElementById(id + 'Icon');
            dropdown.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        }

        // Initialize state from localStorage (Desktop only)
        window.addEventListener('DOMContentLoaded', () => {
            if (window.innerWidth >= 768) {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.remove('w-64');
                    sidebar.classList.add('w-20');
                    const texts = document.querySelectorAll('.sidebar-text');
                    texts.forEach(t => t.classList.add('hidden'));
                }
            }

            const currentFile = "<?= basename($_SERVER['PHP_SELF']) ?>";
            if (['categories.php', 'units.php'].includes(currentFile)) {
                // Ensure dropdown is visible if we are on a child page
                const dropdown = document.getElementById('setupDropdown');
                if(dropdown) {
                    dropdown.classList.remove('hidden');
                    const icon = document.getElementById('setupDropdownIcon');
                    if(icon) icon.classList.add('rotate-180');
                }
            }
            if (['restock_history.php', 'quick_restock.php'].includes(currentFile)) {
                // Ensure dropdown is visible if we are on a child page
                const dropdown = document.getElementById('restockDropdown');
                if(dropdown) {
                    dropdown.classList.remove('hidden');
                    const icon = document.getElementById('restockDropdownIcon');
                    if(icon) icon.classList.add('rotate-180');
                }
            }
            if (['inventory.php', 'check_inventory.php'].includes(currentFile)) {
                // Ensure dropdown is visible if we are on a child page
                const dropdown = document.getElementById('inventoryDropdown');
                if(dropdown) {
                    dropdown.classList.remove('hidden');
                    const icon = document.getElementById('inventoryDropdownIcon');
                    if(icon) icon.classList.add('rotate-180');
                }
            }
        });

        let alertCallback = null;

        function showAlert(message, title = 'Attention') {
            const modal = document.getElementById('globalAlertModal');
            const titleEl = document.getElementById('globalAlertTitle');
            const messageEl = document.getElementById('globalAlertMessage');
            const confirmBtn = document.getElementById('globalAlertConfirmBtn');
            const closeBtn = document.getElementById('globalAlertCloseBtn');
            
            titleEl.innerText = title;
            messageEl.innerText = message;
            
            confirmBtn.classList.add('hidden');
            closeBtn.classList.remove('hidden');
            closeBtn.innerText = "Got it, Thanks";
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            alertCallback = null;
        }

        function showConfirm(message, callback, title = 'Are you sure?') {
            const modal = document.getElementById('globalAlertModal');
            const titleEl = document.getElementById('globalAlertTitle');
            const messageEl = document.getElementById('globalAlertMessage');
            const confirmBtn = document.getElementById('globalAlertConfirmBtn');
            const closeBtn = document.getElementById('globalAlertCloseBtn');
            
            titleEl.innerText = title;
            messageEl.innerText = message;
            
            confirmBtn.classList.remove('hidden');
            closeBtn.classList.remove('hidden');
            closeBtn.innerText = "Cancel";
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            alertCallback = callback;
        }

        function handleAlertConfirm() {
            if (alertCallback) alertCallback();
            closeAlert();
        }

        function closeAlert() {
            const modal = document.getElementById('globalAlertModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            alertCallback = null;
        }

        // Background Update Check
        document.addEventListener('DOMContentLoaded', function() {
            // Check only once per session to avoid spamming the server
            if (!sessionStorage.getItem('updateChecked')) {
                const handlerPath = '<?= $base ?>actions/update_handler.php';
                
                fetch(handlerPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=check'
                })
                .then(response => response.json())
                .then(data => {
                    sessionStorage.setItem('updateChecked', 'true');
                    if (data.available) {
                        const banner = document.getElementById('globalUpdateBanner');
                        const msg = document.getElementById('updateBannerMsg');
                        if (banner && msg) {
                            msg.innerText = `${data.count} new update(s) found on branch: ${data.branch}`;
                            banner.classList.remove('hidden');
                        }
                    }
                })
                .catch(err => console.error('Update check failed:', err));
            }
        });

        async function applyGlobalUpdate() {
            const btn = document.getElementById('globalUpdateBtn');
            const banner = document.getElementById('globalUpdateBanner');
            const msg = document.getElementById('updateBannerMsg');
            
            if (!confirm('Are you sure you want to update the system now?')) return;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'apply');
                
                const response = await fetch('<?= $base ?>actions/update_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    msg.innerHTML = "<b>Update Successful!</b> Reloading...";
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    alert('Update failed: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = 'Try Again';
                }
            } catch (err) {
                alert('Update error: ' + err.message);
                btn.disabled = false;
                btn.innerText = 'Try Again';
            }
        }
    </script>

    <!-- Global Alert Modal -->
    <div id="globalAlertModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-[9999] items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full transform transition-all animate-in fade-in zoom-in duration-200">
            <div class="p-6 text-center">
                <div id="alertIconBox" class="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-2xl" id="globalAlertIcon"></i>
                </div>
                <h3 id="globalAlertTitle" class="text-xl font-bold text-gray-900 mb-2">Attention</h3>
                <p id="globalAlertMessage" class="text-gray-500 text-sm leading-relaxed mb-6"></p>
                <div class="flex gap-3">
                    <button id="globalAlertCloseBtn" onclick="closeAlert()" class="flex-1 bg-gray-100 text-gray-600 font-bold py-3 rounded-xl hover:bg-gray-200 transition-colors active:scale-95">
                        Cancel
                    </button>
                    <button id="globalAlertConfirmBtn" onclick="handleAlertConfirm()" class="flex-1 bg-red-600 text-white font-bold py-3 rounded-xl hover:bg-red-700 transition-colors shadow-lg shadow-red-900/20 active:scale-95 hidden">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
