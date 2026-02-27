<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= getSetting('business_name', 'Fashion Shines POS') ?></title>
    <?php $base = (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? '../' : ''; ?>
    <link rel="icon" type="image/png" href="<?= $base . getSetting('business_favicon', 'assets/img/favicon.png') . '?v=' . time() ?>">
    <script src="<?= $base ?>assets/js/tailwind.js"></script>
    <script src="<?= $base ?>assets/js/pagination.js"></script>
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
        @keyframes swing {
            0% { transform: rotate(0); }
            10% { transform: rotate(10deg); }
            30% { transform: rotate(-10deg); }
            45% { transform: rotate(5deg); }
            55% { transform: rotate(-5deg); }
            65% { transform: rotate(2deg); }
            75% { transform: rotate(-2deg); }
            100% { transform: rotate(0); }
        }
        .animate-swing {
            animation: swing 2s ease infinite;
            transform-origin: top center;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

<?php if (isset($_SESSION['user_id'])): ?>
    <?php
    $global_notifications = getGlobalNotifications();
    $notif_count = count($global_notifications);
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
            <div class="p-4 flex items-center justify-between border-b border-teal-800 min-h-[6rem] bg-teal-900/20">
                <div class="flex items-center gap-3 sidebar-text">
                    <?php $business_logo = getSetting('business_favicon', 'assets/img/logo.png'); ?>
                    <div class="w-10 h-10 rounded-lg p-1 flex-shrink-0">
                        <img src="<?= $base . $business_logo . '?v=' . time() ?>" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl md:text-2xl font-bold tracking-tight text-accent logo-text leading-tight"><?= getSetting('business_name', 'Fashion Shines') ?></h1>
                        <p class="text-[9px] text-teal-400 font-medium uppercase tracking-[0.1em]">Management System</p>
                    </div>
                </div>
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-teal-800 transition-all text-teal-400 hover:text-white outline-none ring-1 ring-teal-800 hover:ring-teal-600 flex-shrink-0 ml-2">
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
                    <?php if (isRole(['Admin', 'Viewer'])): ?>
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
                            <?php if (hasPermission('add_product')): ?>
                            <li>
                                <a href="<?= $base ?>pages/inventory.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Add Inventory">
                                    <i class="fas fa-plus-circle mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Add Inventory</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a href="<?= $base ?>pages/check_inventory.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'check_inventory.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Check Inventory">
                                    <i class="fas fa-search mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Check Inventory</span>
                                </a>
                            </li>
                            <?php if (hasPermission('add_sale')): ?>
                            <li>
                                <a href="<?= $base ?>pages/return_product.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'return_product.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Return Product">
                                    <i class="fas fa-undo mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Return Product</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (isRole(['Admin', 'Viewer', 'Dealer'])): ?>
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
                            <?php if (hasPermission('add_restock')): ?>
                            <li>
                                <a href="<?= $base ?>pages/quick_restock.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'quick_restock.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Quick Restock">
                                    <i class="fas fa-plus-square mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Quick Restock</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a href="<?= $base ?>pages/restock_history.php" class="flex items-center pl-16 pr-6 py-3 hover:bg-teal-800 transition-colors text-sm <?= basename($_SERVER['PHP_SELF']) == 'restock_history.php' ? 'text-accent font-bold bg-teal-800/50' : 'text-teal-200' ?>" title="Restock History">
                                    <i class="fas fa-history mr-3 text-[10px]"></i>
                                    <span class="sidebar-text">Restock History</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission('add_sale')): ?>
                    <li>
                        <a href="<?= $base ?>pages/pos.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'pos.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="POS / Sale">
                            <i class="fas fa-cash-register w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">POS / Sale</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isRole(['Admin', 'Viewer'])): ?>
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
                    <?php endif; ?>

                    <?php if (isRole('Customer')): ?>
                    <li>
                        <a href="<?= $base ?>pages/customer_ledger.php?id=<?= $_SESSION['related_id'] ?? '' ?>" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'customer_ledger.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="My Ledger">
                            <i class="fas fa-file-invoice-dollar w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">My Ledger</span>
                        </a>
                    </li>
                     <li>
                        <a href="<?= $base ?>pages/sales_history.php?customer_id=<?= $_SESSION['related_id'] ?? '' ?>" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'sales_history.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Sales History">
                            <i class="fas fa-history w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">Sales History</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (isRole('Dealer')): ?>
                    <li>
                        <a href="<?= $base ?>pages/dealer_ledger.php?id=<?= $_SESSION['related_id'] ?? '' ?>" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'dealer_ledger.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="My Ledger">
                            <i class="fas fa-file-invoice-dollar w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">My Ledger</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isRole('Admin')): ?>
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
                    <?php endif; ?>
                    <li>
                        <a href="<?= $base ?>pages/settings.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Settings">
                            <i class="fas fa-cog w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">Settings</span>
                        </a>
                    </li>
                    <?php if (isRole('Admin')): ?>
                    <li>
                        <a href="<?= $base ?>pages/backup_restore.php" class="flex items-center px-6 py-4 hover:bg-teal-800 transition-colors <?= basename($_SERVER['PHP_SELF']) == 'backup_restore.php' ? 'bg-teal-800 border-r-4 border-accent' : '' ?>" title="Backup & Restore">
                            <i class="fas fa-database w-6 text-xl"></i>
                            <span class="font-medium ml-4 sidebar-text">Backup/Restore</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="px-6 mt-6 pb-4">
                    <a href="<?= $base ?>logout.php" class="flex items-center justify-center w-full px-4 py-3 bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500 hover:text-white rounded-xl transition-all duration-300 text-sm font-bold shadow-lg shadow-red-500/5">
                        <i class="fas fa-power-off"></i> 
                        <span class="ml-3 sidebar-text">Sign Out</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4 md:p-8 relative">

            <!-- Overlay for mobile -->
            <div id="sidebarOverlay" onclick="toggleSidebarMobile()" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden glass"></div>

            <!-- Top Navigation Bar -->
            <div class="sticky top-0 z-40 bg-white border-b border-gray-100 px-4 md:px-8 py-3 flex items-center justify-between shadow-sm mb-6">
                <!-- Mobile Menu and Search Placeholder -->
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebarMobile()" class="md:hidden p-2 text-gray-400 hover:text-teal-600 transition-colors">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    <div class="relative hidden md:flex items-center bg-gray-50 rounded-xl px-4 py-2 border border-gray-100 group focus-within:border-teal-300 focus-within:ring-2 focus-within:ring-teal-100 transition-all">
                        <i class="fas fa-search text-gray-400 mr-2 group-focus-within:text-teal-500"></i>
                        <input type="text" id="global-feature-search" placeholder="Search features (POS, Reports)..." class="bg-transparent border-none focus:ring-0 text-sm font-medium text-gray-700 w-48" autocomplete="off">
                        
                        <!-- Search Results Dropdown -->
                        <div id="search-results" class="absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 hidden overflow-hidden z-[100] transform origin-top transition-all">
                            <div id="search-results-list" class="max-h-64 overflow-y-auto p-2">
                                <!-- Results JS Populated -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Actions and Notifications -->
                <div class="flex items-center gap-3 md:gap-6">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button onclick="toggleDropdown('notificationDropdown')" class="w-10 h-10 flex items-center justify-center bg-gray-50 rounded-xl text-gray-500 hover:bg-teal-50 hover:text-teal-600 transition-all border border-gray-100 group relative">
                            <i class="fas fa-bell <?= $notif_count > 0 ? 'animate-swing' : '' ?>"></i>
                            <?php if ($notif_count > 0): ?>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white">
                                    <?= $notif_count ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div id="notificationDropdown" class="absolute right-0 mt-3 w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 hidden overflow-hidden transform origin-top-right transition-all z-50">
                            <div class="p-4 border-b border-gray-50 flex items-center justify-between">
                                <h4 class="font-bold text-gray-800">Notifications</h4>
                                <span class="text-[10px] font-black uppercase tracking-widest text-teal-600 bg-teal-50 px-2 py-0.5 rounded-full">New (<?= $notif_count ?>)</span>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                <?php if ($notif_count > 0): ?>
                                    <?php foreach ($global_notifications as $notif): ?>
                                        <a href="<?= $base . $notif['link'] ?>" class="flex items-start gap-4 p-4 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-none">
                                            <div class="w-10 h-10 <?= $notif['color'] ?> text-white rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-<?= str_replace('bg-', '', $notif['color']) ?>/20">
                                                <i class="<?= $notif['icon'] ?>"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-gray-800"><?= $notif['title'] ?></p>
                                                <p class="text-xs text-gray-500 mt-1"><?= $notif['message'] ?></p>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-8 text-center">
                                        <div class="w-16 h-16 bg-gray-50 text-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <i class="fas fa-bell-slash text-2xl"></i>
                                        </div>
                                        <p class="text-gray-400 text-sm font-medium">All caught up!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($notif_count > 0): ?>
                                <div class="p-3 bg-gray-50 text-center">
                                    <button onclick="clearAllNotifications()" class="text-[10px] font-black text-gray-400 uppercase tracking-widest hover:text-teal-600 transition-colors">Clear All</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="h-8 w-[1px] bg-gray-100 hidden md:block"></div>

                    <!-- User Information -->
                    <div class="relative">
                        <button onclick="toggleDropdown('userProfileDropdown')" class="flex items-center gap-3 p-1 rounded-xl hover:bg-gray-50 transition-all border border-transparent hover:border-gray-100">
                            <div class="w-8 h-8 md:w-10 md:h-10 bg-teal-600 text-white rounded-xl flex items-center justify-center font-bold text-sm shadow-lg shadow-teal-500/20">
                                <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-xs font-black text-gray-800 tracking-tight leading-none"><?= $_SESSION['username'] ?></p>
                                <p class="text-[10px] font-medium text-teal-600 mt-1 uppercase tracking-wider"><?= $_SESSION['user_role'] ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-[10px] text-gray-400 ml-1 hidden md:block" id="userProfileDropdownIcon"></i>
                        </button>

                        <!-- User Dropdown -->
                        <div id="userProfileDropdown" class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-2xl border border-gray-100 hidden overflow-hidden transition-all z-50">
                            <div class="p-4 border-b border-gray-50 md:hidden">
                                <p class="text-sm font-bold text-gray-800"><?= $_SESSION['username'] ?></p>
                                <p class="text-xs text-teal-600 font-medium lowercase italic"><?= $_SESSION['user_role'] ?></p>
                            </div>
                            <div class="p-2">
                                <a href="<?= $base ?>pages/settings.php" class="flex items-center gap-3 px-4 py-3 text-sm text-gray-600 font-bold hover:bg-teal-50 hover:text-teal-700 rounded-xl transition-all">
                                    <i class="fas fa-user-circle text-gray-400"></i> My Profile
                                </a>
                                <a href="<?= $base ?>pages/settings.php" class="flex items-center gap-3 px-4 py-3 text-sm text-gray-600 font-bold hover:bg-teal-50 hover:text-teal-700 rounded-xl transition-all">
                                    <i class="fas fa-cog text-gray-400"></i> Settings
                                </a>
                                <div class="h-[1px] bg-gray-50 my-2"></div>
                                <a href="<?= $base ?>logout.php" class="flex items-center gap-3 px-4 py-3 text-sm text-red-600 font-bold hover:bg-red-50 rounded-xl transition-all">
                                    <i class="fas fa-power-off text-red-300"></i> Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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

        async function clearAllNotifications() {
            try {
                const base = '<?= $base ?>';
                const response = await fetch(base + 'actions/clear_all_notifications.php', {
                    method: 'POST'
                });
                if (response.ok) {
                    location.reload(); 
                }
            } catch (e) {
                console.error("Failed to clear notifications", e);
            }
        }

        // Global Feature Search Logic
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('global-feature-search');
            const resultsContainer = document.getElementById('search-results');
            const resultsList = document.getElementById('search-results-list');
            const base = '<?= $base ?>';

            const features = [
                { name: 'Dashboard', url: 'index.php', icon: 'fa-th-large', keywords: 'home main overview' },
                <?php if (hasPermission('add_sale')): ?>
                { name: 'POS / New Sale', url: 'pages/pos.php', icon: 'fa-cash-register', keywords: 'bill invoice checkout sell counter' },
                <?php endif; ?>
                <?php if (isRole(['Admin', 'Viewer', 'Customer'])): ?>
                { name: 'Sales History', url: 'pages/sales_history.php', icon: 'fa-history', keywords: 'records transactions history past sales' },
                <?php endif; ?>
                <?php if (isRole(['Admin', 'Viewer'])): ?>
                { name: 'Inventory / Stock', url: 'pages/inventory.php', icon: 'fa-boxes', keywords: 'products items warehouse stock' },
                { name: 'Check Inventory', url: 'pages/check_inventory.php', icon: 'fa-search', keywords: 'verify search stock look' },
                { name: 'Reports & Analytics', url: 'pages/reports.php', icon: 'fa-chart-line', keywords: 'profit loss analysis statistics status' },
                { name: 'Customers', url: 'pages/customers.php', icon: 'fa-users', keywords: 'clients people buyers' },
                { name: 'Customer Ledgers', url: 'pages/customer_ledger.php', icon: 'fa-file-invoice-dollar', keywords: 'debt khata recovery balance history' },
                { name: 'Dealers / Suppliers', url: 'pages/dealers.php', icon: 'fa-truck', keywords: 'vendors suppliers wholesale' },
                { name: 'Dealer Ledgers', url: 'pages/dealer_ledger.php', icon: 'fa-file-invoice-dollar', keywords: 'payment payable bill records' },
                { name: 'Expenses', url: 'pages/expenses.php', icon: 'fa-wallet', keywords: 'kharcha bills utility rent' },
                <?php endif; ?>
                <?php if (isRole(['Admin', 'Viewer', 'Dealer'])): ?>
                { name: 'Quick Restock', url: 'pages/quick_restock.php', icon: 'fa-plus-square', keywords: 'order buy supply refill' },
                <?php endif; ?>
                <?php if (hasPermission('add_sale')): ?>
                { name: 'Return Product', url: 'pages/return_product.php', icon: 'fa-undo', keywords: 'refund exchange back' },
                <?php endif; ?>
                <?php if (isRole('Admin')): ?>
                { name: 'Categories', url: 'pages/categories.php', icon: 'fa-tags', keywords: 'group type classification' },
                { name: 'Units', url: 'pages/units.php', icon: 'fa-balance-scale', keywords: 'measurement kg piece pack' },
                <?php endif; ?>
                { name: 'Settings', url: 'pages/settings.php', icon: 'fa-cog', keywords: 'config profile business setup' },
                <?php if (isRole('Admin')): ?>
                { name: 'Backup & Restore', url: 'pages/backup_restore.php', icon: 'fa-database', keywords: 'security save download database' }
                <?php endif; ?>
            ];

            let selectedIndex = -1;

            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                if (!query) {
                    resultsContainer.classList.add('hidden');
                    return;
                }

                const matches = features.filter(f => 
                    f.name.toLowerCase().includes(query) || 
                    f.keywords.toLowerCase().includes(query)
                ).slice(0, 6);

                if (matches.length > 0) {
                    renderResults(matches);
                    resultsContainer.classList.remove('hidden');
                } else {
                    resultsContainer.classList.add('hidden');
                }
                selectedIndex = -1;
            });

            function renderResults(matches) {
                resultsList.innerHTML = matches.map((f, idx) => `
                    <div class="result-item flex items-center gap-3 p-3 rounded-xl hover:bg-teal-50 cursor-pointer transition-colors group" data-url="${base + f.url}">
                        <div class="w-8 h-8 bg-gray-100 text-gray-400 rounded-lg flex items-center justify-center group-hover:bg-teal-600 group-hover:text-white transition-all">
                            <i class="fas ${f.icon} text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs font-bold text-gray-800">${f.name}</p>
                            <p class="text-[9px] text-gray-400 uppercase tracking-widest">${f.url}</p>
                        </div>
                        <i class="fas fa-chevron-right text-[10px] text-gray-200 group-hover:text-teal-400"></i>
                    </div>
                `).join('');

                document.querySelectorAll('.result-item').forEach(item => {
                    item.addEventListener('click', () => {
                        window.location.href = item.dataset.url;
                    });
                });
            }

            searchInput.addEventListener('keydown', (e) => {
                const items = document.querySelectorAll('.result-item');
                if (resultsContainer.classList.contains('hidden')) return;

                if (e.key === 'ArrowDown') {
                    selectedIndex = (selectedIndex + 1) % items.length;
                    updateSelection(items);
                    e.preventDefault();
                } else if (e.key === 'ArrowUp') {
                    selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                    updateSelection(items);
                    e.preventDefault();
                } else if (e.key === 'Enter') {
                    if (selectedIndex > -1) {
                        window.location.href = items[selectedIndex].dataset.url;
                    } else if (items.length > 0) {
                        window.location.href = items[0].dataset.url;
                    }
                } else if (e.key === 'Escape') {
                    resultsContainer.classList.add('hidden');
                }
            });

            function updateSelection(items) {
                items.forEach((item, idx) => {
                    if (idx === selectedIndex) {
                        item.classList.add('bg-teal-50', 'ring-1', 'ring-teal-100');
                        item.scrollIntoView({ block: 'nearest' });
                    } else {
                        item.classList.remove('bg-teal-50', 'ring-1', 'ring-teal-100');
                    }
                });
            }

            // Close results when clicking outside
            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                    resultsContainer.classList.add('hidden');
                }
            });

            // Intercept Ctrl+F / Cmd+F to focus the app search bar
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
        // Global Escape Key to close all modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // 1. Close Global Alert/Confirm
                if (typeof closeAlert === 'function') closeAlert();
                
                // 2. Trigger standard closing functions if they exist
                if (typeof closeModal === 'function') closeModal();
                if (typeof closeIssuingModal === 'function') closeIssuingModal();
                if (typeof closeRestockModal === 'function') closeRestockModal();
                
                // 3. Brute force hide any visible fixed overlays/modals
                const overlays = [
                    'globalAlertModal', 
                    'issuingHistoryModal', 
                    'restockLogModal', 
                    'addProductModal',
                    'notificationDropdown',
                    'userProfileDropdown',
                    'search-results'
                ];
                
                overlays.forEach(id => {
                    const el = document.getElementById(id);
                    if (el && !el.classList.contains('hidden')) {
                        el.classList.add('hidden');
                        if (el.classList.contains('flex')) el.classList.remove('flex');
                    }
                });

                // 4. Specifically for POS or other pages that might use window-level overrides
                if (window.closeModal && typeof window.closeModal === 'function') window.closeModal();
            }
        });
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
