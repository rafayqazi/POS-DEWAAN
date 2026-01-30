<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
if (!hasPermission('update_settings')) die("Unauthorized Access");
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'change_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        $user_id = $_SESSION['user_id'];

        if ($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            $user = findCSV('users', $user_id);
            if ($user && password_verify($current, $user['password'])) {
                $new_hash = password_hash($new, PASSWORD_DEFAULT);
                updateCSV('users', $user_id, ['password' => $new_hash]);
                $message = "Password updated successfully.";
            } else {
                $error = "Current password is incorrect.";
            }
        }
    } elseif ($action == 'add_unit') {
        $name = cleanInput($_POST['name']);
        if ($name) {
            insertCSV('units', ['name' => $name]);
            $message = "Unit '$name' added.";
        }
    } elseif ($action == 'delete_unit') {
        $id = $_POST['id'];
        deleteCSV('units', $id);
        $message = "Unit deleted.";
    } elseif ($action == 'add_category') {
        $name = cleanInput($_POST['name']);
        if ($name) {
            insertCSV('categories', ['name' => $name]);
            $message = "Category '$name' added.";
        }
    } elseif ($action == 'delete_category') {
        $id = $_POST['id'];
        deleteCSV('categories', $id);
        $message = "Category deleted.";
    } elseif ($action == 'check_update') {
        ob_end_clean(); 
        ob_start();
        ini_set('display_errors', 0);
        error_reporting(E_ALL);
        set_time_limit(60);
        
        $status = getUpdateStatus();
        
        ob_clean();
        header('Content-Type: application/json');
        
        $msg = "You are up to date.";
        if ($status['available']) {
            $msg = $status['count'] . " new update(s) found!";
        } elseif (!empty($status['error'])) {
            $msg = $status['error'];
        }

        echo json_encode([
            'status' => empty($status['error']) ? 'success' : 'error', 
            'update_available' => $status['available'],
            'message' => $msg,
            'debug' => "Branch: " . $status['branch'] . " | Local: " . $status['local'] . " | Remote: " . $status['remote'] . " | Count: " . $status['count']
        ]);
        exit;
    } elseif ($action == 'do_update') {
        ob_end_clean();
        ob_start();
        ini_set('display_errors', 0);
        set_time_limit(120);
        
        $result = runUpdate();
        
        ob_clean();
        header('Content-Type: application/json');
        if ($result['success']) {
            // Clear the update detection timestamp
            $u_id = findSettingId('update_first_detected');
            if ($u_id) deleteCSV('settings', $u_id);
            
            // Force re-check on next page load
            $_SESSION['check_updates'] = true;
            
            echo json_encode(['status' => 'success', 'message' => "Update installed successfully from " . $result['branch'] . " branch!"]);
        } else {
             echo json_encode(['status' => 'error', 'message' => "Update failed: " . $result['message']]);
        }
        exit;
    } elseif ($action == 'update_general_settings') {
        $expiry_days = $_POST['expiry_notify_days'] ?? '7';
        $recovery_days = $_POST['recovery_notify_days'] ?? '7';
        $business_name = cleanInput($_POST['business_name']);
        
        updateSetting('expiry_notify_days', $expiry_days);
        updateSetting('recovery_notify_days', $recovery_days);
        if ($business_name) updateSetting('business_name', $business_name);
        
        // Handle Favicon Upload
        if (isset($_FILES['business_favicon']) && $_FILES['business_favicon']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'ico'];
            $filename = $_FILES['business_favicon']['name'];
            $filetmp = $_FILES['business_favicon']['tmp_name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                // Ensure directory exists
                if (!is_dir('../uploads/settings')) mkdir('../uploads/settings', 0777, true);
                
                $new_name = 'favicon_' . time() . '.' . $ext;
                $dest = '../uploads/settings/' . $new_name;
                
                if (move_uploaded_file($filetmp, $dest)) {
                    updateSetting('business_favicon', 'uploads/settings/' . $new_name);
                } else {
                    $error = "Failed to upload favicon.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, and ICO are allowed.";
            }
        }
        
        if (!$error) $message = "General settings updated successfully.";
    } elseif ($action == 'add_user') {
        requirePermission('manage_users');
        $username = cleanInput($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'] ?? 'Viewer';
        $related_id = $_POST['related_id'] ?? '';

        if (!$username || !$password) {
            $error = "Username and password are required.";
        } else {
            // Check if username exists
            $users = readCSV('users');
            $exists = false;
            foreach ($users as $u) {
                if ($u['username'] == $username) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                $error = "Username already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                insertCSV('users', [
                    'username' => $username,
                    'password' => $hash,
                    'role' => $role,
                    'related_id' => $related_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'plain_password' => $password
                ]);
                $message = "User '$username' added successfully.";
            }
        }
    } elseif ($action == 'admin_update_password') {
        requirePermission('manage_users');
        $id = $_POST['id'];
        $new_password = $_POST['new_password'];
        
        if (!$new_password) {
            $error = "Password cannot be empty.";
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            updateCSV('users', $id, [
                'password' => $hash,
                'plain_password' => $new_password
            ]);
            $message = "User password updated successfully.";
        }
    } elseif ($action == 'delete_user') {
        requirePermission('manage_users');
        $id = $_POST['id'];
        if ($id == $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            deleteCSV('users', $id);
            $message = "User deleted.";
        }
    }
}

$pageTitle = "Settings";
include '../includes/header.php';

$units = readCSV('units');
$categories = readCSV('categories');
?>

<div class="max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Settings</h1>
            <p class="text-gray-500 mt-1">Manage your account security and system preferences.</p>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-t-2xl shadow-sm border-b border-gray-100 flex overflow-x-auto">
        <button onclick="switchTab('security')" id="tab-security" class="tab-btn px-8 py-4 font-bold text-gray-500 hover:text-primary transition-colors border-b-2 border-transparent focus:outline-none relative whitespace-nowrap active-tab">
            <i class="fas fa-user-shield mr-2"></i> Security
        </button>
        <?php if (hasPermission('manage_users')): ?>
        <button onclick="switchTab('users')" id="tab-users" class="tab-btn px-8 py-4 font-bold text-gray-500 hover:text-primary transition-colors border-b-2 border-transparent focus:outline-none relative whitespace-nowrap">
            <i class="fas fa-users mr-2"></i> Users
        </button>
        <?php endif; ?>
        <button onclick="switchTab('updates')" id="tab-updates" class="tab-btn px-8 py-4 font-bold text-gray-500 hover:text-primary transition-colors border-b-2 border-transparent focus:outline-none relative whitespace-nowrap">
            <i class="fas fa-cloud-download-alt mr-2"></i> Updates
        </button>
        <button onclick="switchTab('general')" id="tab-general" class="tab-btn px-8 py-4 font-bold text-gray-500 hover:text-primary transition-colors border-b-2 border-transparent focus:outline-none relative whitespace-nowrap">
            <i class="fas fa-cogs mr-2"></i> General
        </button>
    </div>

    <?php 
    $users = readCSV('users');
    $all_customers = readCSV('customers');
    $all_dealers = readCSV('dealers');
    ?>

    <!-- Messages -->
    <?php if($message): ?><div class="mt-4 bg-green-100 text-green-700 p-4 rounded-xl shadow-sm border-l-4 border-green-500 animate-fade-in"><i class="fas fa-check-circle mr-2"></i><?= $message ?></div><?php endif; ?>
    <?php if($error): ?><div class="mt-4 bg-red-100 text-red-700 p-4 rounded-xl shadow-sm border-l-4 border-red-500 animate-fade-in"><i class="fas fa-exclamation-circle mr-2"></i><?= $error ?></div><?php endif; ?>

    <!-- Security Tab -->
    <div id="content-security" class="tab-content bg-white rounded-b-2xl shadow-xl p-8 border border-t-0 border-gray-100 mb-8 min-h-[400px]">
        <div class="max-w-2xl">
            <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                <span class="w-10 h-10 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center mr-3">
                    <i class="fas fa-lock text-sm"></i>
                </span>
                Change Password
            </h2>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wider">Current Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-key"></i></span>
                        <input type="password" name="current_password" required placeholder="••••••••"
                               class="w-full rounded-xl border-gray-200 border pl-10 p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm bg-gray-50 focus:bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wider">New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-unlock-alt"></i></span>
                        <input type="password" name="new_password" required placeholder="Leave blank to keep current"
                               class="w-full rounded-xl border-gray-200 border pl-10 p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm bg-gray-50 focus:bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wider">Confirm New Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-check-double"></i></span>
                        <input type="password" name="confirm_password" required placeholder="Repeat new password"
                               class="w-full rounded-xl border-gray-200 border pl-10 p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm bg-gray-50 focus:bg-white">
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100 mt-6">
                    <button type="submit" class="bg-primary text-white font-bold py-3 px-6 rounded-xl hover:bg-secondary transition shadow-md transform active:scale-95 flex items-center">
                        <i class="fas fa-save mr-2"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Tab -->
    <?php if (hasPermission('manage_users')): ?>
    <div id="content-users" class="tab-content hidden bg-white rounded-b-2xl shadow-xl p-8 border border-t-0 border-gray-100 mb-8 min-h-[400px]">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Add User Form -->
            <div class="lg:col-span-1 border-r border-gray-100 pr-8">
                <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <span class="w-10 h-10 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center mr-3">
                        <i class="fas fa-user-plus text-sm"></i>
                    </span>
                    Add New User
                </h2>
                <form method="POST" id="addUserForm" class="space-y-4">
                    <input type="hidden" name="action" value="add_user">
                    <div>
                        <label class="block text-gray-700 font-bold mb-1 text-xs uppercase tracking-wider">Username</label>
                        <input type="text" name="username" id="usernameInput" required placeholder="Login ID"
                               class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary transition outline-none shadow-sm bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-1 text-xs uppercase tracking-wider">Password</label>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary transition outline-none shadow-sm bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-1 text-xs uppercase tracking-wider">User Role</label>
                        <select name="role" id="roleSelect" onchange="toggleRoleFields()" required
                                class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary transition outline-none shadow-sm bg-gray-50">
                            <option value="Admin">Admin (Full Access)</option>
                            <option value="Viewer" selected>Viewer (View & Download Only)</option>
                            <option value="Customer">Customer (View Own Data Only)</option>
                            <option value="Dealer">Dealer (View Own Data Only)</option>
                        </select>
                    </div>

                    <!-- Role-specific description -->
                    <div id="roleDesc" class="p-3 rounded-lg bg-blue-50 text-blue-700 text-xs italic">
                        Viewers can see records and download reports but cannot edit or delete anything.
                    </div>

                    <!-- Relation selection (Hidden initially) -->
                    <div id="customerField" class="hidden">
                        <label class="block text-gray-700 font-bold mb-1 text-xs uppercase tracking-wider">Select Customer</label>
                        <select name="related_id_customer" onchange="autoFetchUsername(this)" class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary transition outline-none shadow-sm bg-gray-50">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($all_customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="dealerField" class="hidden">
                        <label class="block text-gray-700 font-bold mb-1 text-xs uppercase tracking-wider">Select Dealer</label>
                        <select name="related_id_dealer" onchange="autoFetchUsername(this)" class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary transition outline-none shadow-sm bg-gray-50">
                            <option value="">-- Select Dealer --</option>
                            <?php foreach ($all_dealers as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <input type="hidden" name="related_id" id="related_id_final" value="">

                    <div class="pt-2">
                        <button type="submit" class="w-full bg-primary text-white font-bold py-3 px-6 rounded-xl hover:bg-secondary transition shadow-md flex items-center justify-center">
                            <i class="fas fa-plus-circle mr-2"></i> Create User
                        </button>
                    </div>
                </form>
            </div>

            <!-- Users List -->
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <span class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mr-3">
                        <i class="fas fa-user-friends text-sm"></i>
                    </span>
                    System Users
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-400 text-xs uppercase tracking-widest border-b border-gray-100">
                                <th class="pb-4 pl-4 font-extrabold">Username</th>
                                <th class="pb-4 font-extrabold">Password</th>
                                <th class="pb-4 font-extrabold">Role</th>
                                <th class="pb-4 font-extrabold">Linked Entity</th>
                                <th class="pb-4 pr-4 font-extrabold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach ($users as $u): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                                <td class="py-4 pl-4 font-bold text-gray-700">
                                    <?= htmlspecialchars($u['username']) ?>
                                    <?php if($u['id'] == $_SESSION['user_id']): ?>
                                        <span class="ml-2 text-[10px] bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full">YOU</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 font-mono text-xs">
                                    <div class="flex items-center gap-2">
                                        <span id="pass-<?= $u['id'] ?>" class="hidden"><?= htmlspecialchars($u['plain_password'] ?? '********') ?></span>
                                        <span id="stars-<?= $u['id'] ?>">********</span>
                                        <button onclick="togglePass(<?= $u['id'] ?>)" class="text-gray-400 hover:text-primary transition">
                                            <i class="fas fa-eye" id="eye-<?= $u['id'] ?>"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="py-4 font-medium">
                                    <span class="px-2 py-1 rounded-lg text-xs 
                                        <?= ($u['role'] ?? 'Admin') == 'Admin' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' ?>">
                                        <?= htmlspecialchars($u['role'] ?? 'Admin') ?>
                                    </span>
                                </td>
                                <td class="py-4 text-gray-500 text-xs">
                                    <?php 
                                    if ($u['role'] == 'Customer') {
                                        $rel = findCSV('customers', $u['related_id']);
                                        echo $rel ? htmlspecialchars($rel['name']) : 'Unknown';
                                    } elseif ($u['role'] == 'Dealer') {
                                        $rel = findCSV('dealers', $u['related_id']);
                                        echo $rel ? htmlspecialchars($rel['name']) : 'Unknown';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="py-4 pr-4 text-right px-2">
                                    <div class="flex justify-end gap-1">
                                        <button onclick="adminChangePass(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')" class="text-blue-400 hover:text-blue-600 transition p-2" title="Change Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" onsubmit="return confirm('Delete user?')" class="inline">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button class="text-red-400 hover:text-red-600 transition p-2"><i class="fas fa-trash"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Updates Tab -->
    <div id="content-updates" class="tab-content hidden bg-white rounded-b-2xl shadow-xl p-8 border border-t-0 border-gray-100 mb-8 min-h-[400px]">
        <div class="max-w-2xl mx-auto text-center pt-8">
            <div id="updateStatusIcon" class="mb-6 w-24 h-24 mx-auto rounded-full bg-gray-50 flex items-center justify-center text-5xl text-gray-300 shadow-inner">
                <i class="fas fa-cloud-download-alt"></i>
            </div>
            
            <h3 class="text-2xl font-bold text-gray-800 mb-2" id="updateTitle">System Version</h3>
            <p class="text-gray-500 mb-10 max-w-md mx-auto leading-relaxed" id="updateMessage">
                Check for the latest updates to ensure you have the newest features and security improvements.
            </p>
            
            <div class="flex justify-center gap-4 mb-4">
                <button type="button" onclick="checkUpdate()" id="checkUpdateBtn" 
                        class="bg-gray-800 text-white font-bold py-4 px-10 rounded-2xl hover:bg-gray-900 transition shadow-xl transform active:scale-95 flex items-center text-lg">
                    <i class="fas fa-sync-alt mr-3" id="checkIcon"></i> Check for Updates
                </button>
                
                <button type="button" onclick="performUpdate()" id="performUpdateBtn" 
                        class="hidden bg-primary text-white font-bold py-4 px-10 rounded-2xl hover:bg-secondary transition shadow-xl transform active:scale-95 flex items-center text-lg animate-bounce-short">
                    <i class="fas fa-cloud-download-alt mr-3"></i> Update Now
                </button>
            </div>

            <div id="updateSpinner" class="hidden mt-8">
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 border-4 border-gray-200 border-t-primary rounded-full animate-spin mb-3"></div>
                    <span class="text-sm font-semibold text-gray-500 tracking-wide" id="spinnerText">CHECKING FOR UPDATES...</span>
                </div>
            </div>
            
            <!-- Terminal Output -->
            <div id="updateTerminal" class="hidden mt-10 mx-auto max-w-xl text-left">
                <div class="bg-gray-900 rounded-t-lg p-3 flex items-center border-b border-gray-800">
                    <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                    <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                    <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                    <span class="ml-auto text-gray-500 text-xs font-mono">System Log</span>
                </div>
                <div class="bg-gray-950 rounded-b-lg p-4 shadow-2xl overflow-hidden">
                    <pre id="terminalContent" class="font-mono text-sm text-green-400 overflow-x-auto whitespace-pre-wrap h-40 custom-scrollbar">Initializing...</pre>
                </div>
            </div>
        </div>
    </div>

    <!-- General Settings Tab -->
    <div id="content-general" class="tab-content hidden bg-white rounded-b-2xl shadow-xl p-8 border border-t-0 border-gray-100 mb-8 min-h-[400px]">
        <div class="max-w-2xl">
            <!-- Business Configuration Section -->
            <div class="mb-10 pb-8 border-b border-gray-100">
                <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                    <span class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mr-3">
                        <i class="fas fa-building text-sm"></i>
                    </span>
                    Business Configuration
                </h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="update_general_settings">
                    
                    <!-- Business Name -->
                    <div>
                        <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wider">Business Name</label>
                        <p class="text-gray-500 text-xs mb-4">This name will be displayed across the entire system (Header, Footer, Reports).</p>
                        <?php $current_name = getSetting('business_name', 'Fashion Shines POS'); ?>
                        <input type="text" name="business_name" value="<?= htmlspecialchars($current_name) ?>" 
                               class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm bg-gray-50 focus:bg-white" 
                               placeholder="e.g. My Awesome Shop">
                    </div>

                    <!-- Favicon Upload -->
                    <div>
                        <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wider">Business Logo / Favicon</label>
                        <p class="text-gray-500 text-xs mb-4">Upload a square image (PNG, JPG, ICO) to be used as the browser icon and logo.</p>
                        <div class="flex items-center gap-4">
                            <?php $current_favicon = getSetting('business_favicon', 'assets/img/logo.png'); ?>
                            <div class="w-16 h-16 rounded-xl border border-gray-200 p-2 bg-white shadow-sm flex items-center justify-center">
                                <img src="../<?= $current_favicon . '?v=' . time() ?>" alt="Current Favicon" class="max-w-full max-h-full object-contain">
                            </div>
                            <input type="file" name="business_favicon" accept=".png, .jpg, .jpeg, .ico"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition">
                        </div>
                    </div>
                    
                    <div>
                        <hr class="my-6 border-gray-100">
                        <h3 class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wider">Notification Preferences</h3>
                    </div>

                    <!-- Existing Notification Settings (Wrapped in the same form) -->
                    <div>
                        <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wider">Expiry Notification Period</label>
                        <p class="text-gray-500 text-xs mb-4">Set how many days before expiry you want to be notified on the dashboard.</p>
                        <?php $current_expiry = getSetting('expiry_notify_days', '7'); ?>
                        <select name="expiry_notify_days" class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm bg-gray-50 focus:bg-white appearance-none">
                            <option value="7" <?= $current_expiry == '7' ? 'selected' : '' ?>>1 Week (7 Days)</option>
                            <option value="15" <?= $current_expiry == '15' ? 'selected' : '' ?>>15 Days</option>
                            <option value="30" <?= $current_expiry == '30' ? 'selected' : '' ?>>1 Month (30 Days)</option>
                        </select>
                    </div>
    
                    <div>
                        <label class="block text-gray-700 font-bold mb-2 text-xs uppercase tracking-wider">Customer Recovery Notification Period</label>
                        <p class="text-gray-500 text-xs mb-4">Set how many days before a payment is due you want to be notified on the dashboard.</p>
                        <?php $current_recovery = getSetting('recovery_notify_days', '7'); ?>
                        <select name="recovery_notify_days" class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm bg-gray-50 focus:bg-white appearance-none">
                            <option value="7" <?= $current_recovery == '7' ? 'selected' : '' ?>>1 Week (7 Days)</option>
                            <option value="15" <?= $current_recovery == '15' ? 'selected' : '' ?>>15 Days</option>
                            <option value="30" <?= $current_recovery == '30' ? 'selected' : '' ?>>1 Month (30 Days)</option>
                        </select>
                    </div>
                    
                    <div class="pt-6 border-t border-gray-100">
                        <button type="submit" class="bg-primary text-white font-bold py-3 px-8 rounded-xl hover:bg-secondary transition shadow-md transform active:scale-95 flex items-center">
                            <i class="fas fa-save mr-2"></i> Save All Settings
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Remove the old form since we merged it -->
        </div>
    </div>
</div>

<style>
    .active-tab {
        color: #0f766e; /* primary color */
        border-bottom-color: #0f766e;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #1f2937;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #374151;
        border-radius: 4px;
    }
</style>

<script>
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    
    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active-tab', 'text-primary', 'border-primary');
        btn.classList.add('text-gray-500', 'border-transparent');
    });
    
    // Highlight active button
    const activeBtn = document.getElementById('tab-' + tabName);
    activeBtn.classList.add('active-tab', 'text-primary', 'border-primary');
    activeBtn.classList.remove('text-gray-500', 'border-transparent');
    
    // Save preference
    localStorage.setItem('settingsActiveTab', tabName);
}

function toggleRoleFields() {
    const role = document.getElementById('roleSelect').value;
    const desc = document.getElementById('roleDesc');
    const custField = document.getElementById('customerField');
    const dealerField = document.getElementById('dealerField');
    const relatedIdFinal = document.getElementById('related_id_final');
    
    custField.classList.add('hidden');
    dealerField.classList.add('hidden');
    
    let description = "";
    switch(role) {
        case 'Admin':
            description = "Admins have full access to all features, settings, and user management.";
            break;
        case 'Viewer':
            description = "Viewers can see records and download reports but cannot edit or delete anything.";
            break;
        case 'Customer':
            description = "Customers can only see their own sales history and ledger.";
            custField.classList.remove('hidden');
            break;
        case 'Dealer':
            description = "Dealers can only see their own restock history and ledger.";
            dealerField.classList.remove('hidden');
            break;
    }
    desc.innerText = description;
}

// Update related_id before submit
document.getElementById('addUserForm')?.addEventListener('submit', function() {
    const role = document.getElementById('roleSelect').value;
    const relatedIdFinal = document.getElementById('related_id_final');
    if (role === 'Customer') {
        relatedIdFinal.value = document.querySelector('select[name="related_id_customer"]').value;
    } else if (role === 'Dealer') {
        relatedIdFinal.value = document.querySelector('select[name="related_id_dealer"]').value;
    } else {
        relatedIdFinal.value = "";
    }
});

function autoFetchUsername(select) {
    const text = select.options[select.selectedIndex].text;
    if (!text || text.includes('-- Select')) return;
    
    // Convert to lowercase, remove spaces and special chars
    const username = text.toLowerCase().replace(/[^a-z0-9]/g, '');
    document.getElementById('usernameInput').value = username;
}

function togglePass(id) {
    const pass = document.getElementById('pass-' + id);
    const stars = document.getElementById('stars-' + id);
    const eye = document.getElementById('eye-' + id);
    
    if (pass.classList.contains('hidden')) {
        pass.classList.remove('hidden');
        stars.classList.add('hidden');
        eye.classList.remove('fa-eye');
        eye.classList.add('fa-eye-slash');
    } else {
        pass.classList.add('hidden');
        stars.classList.remove('hidden');
        eye.classList.add('fa-eye');
        eye.classList.remove('fa-eye-slash');
    }
}

function adminChangePass(id, username) {
    const newPass = prompt("Enter new password for '" + username + "':");
    if (newPass === null) return;
    if (!newPass.trim()) {
        alert("Password cannot be empty.");
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'admin_update_password';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;
    form.appendChild(idInput);
    
    const passInput = document.createElement('input');
    passInput.type = 'hidden';
    passInput.name = 'new_password';
    passInput.value = newPass;
    form.appendChild(passInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Restore active tab logic
document.addEventListener('DOMContentLoaded', () => {
    const savedTab = localStorage.getItem('settingsActiveTab');
    if (savedTab) {
        switchTab(savedTab);
    }
});

async function checkUpdate() {
    const btn = document.getElementById('checkUpdateBtn');
    const updateBtn = document.getElementById('performUpdateBtn');
    const spinner = document.getElementById('updateSpinner');
    const spinnerText = document.getElementById('spinnerText');
    const icon = document.getElementById('checkIcon');
    const statusIcon = document.getElementById('updateStatusIcon');
    const title = document.getElementById('updateTitle');
    const msg = document.getElementById('updateMessage');
    const terminal = document.getElementById('updateTerminal');
    
    // Reset UI
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    icon.classList.add('fa-spin');
    spinner.classList.remove('hidden');
    spinnerText.innerText = 'CHECKING FOR UPDATES...';
    updateBtn.classList.add('hidden');
    terminal.classList.add('hidden');
    
    try {
        const formData = new FormData();
        formData.append('action', 'check_update');
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            console.error("Invalid JSON:", text);
            const preview = text.substring(0, 200).replace(/</g, "&lt;");
            throw new Error(`Server returned invalid JSON. Raw response: ${preview}...`);
        }
        
        if (data.status === 'error') {
            throw new Error(data.message);
        }
        
        if (data.update_available) {
            statusIcon.innerHTML = '<i class="fas fa-gift text-accent animate-pulse"></i>';
            statusIcon.className = "mb-6 w-24 h-24 mx-auto rounded-full bg-orange-100 flex items-center justify-center text-5xl text-accent shadow-inner";
            title.innerText = "New Update Available!";
            title.classList.add('text-accent');
            msg.innerText = "A new version of the software is available. Click 'Update Now' to verify and install.";
            updateBtn.classList.remove('hidden');
            btn.classList.add('hidden');
        } else {
            statusIcon.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>';
            statusIcon.className = "mb-6 w-24 h-24 mx-auto rounded-full bg-green-100 flex items-center justify-center text-5xl text-green-500 shadow-inner";
            title.innerText = "System is Up to Date";
            title.classList.remove('text-accent');
            msg.innerText = "You are using the latest version of the software.";
        }
    } catch (error) {
        console.error(error);
        msg.innerText = "Error checking updates: " + error.message;
        statusIcon.innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
        statusIcon.className = "mb-6 w-24 h-24 mx-auto rounded-full bg-red-100 flex items-center justify-center text-5xl text-red-500 shadow-inner";
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
        icon.classList.remove('fa-spin');
        spinner.classList.add('hidden');
    }
}

async function performUpdate() {
    if(!confirm('Are you sure you want to install the latest updates? The system will restart after updating.')) return;

    const btn = document.getElementById('performUpdateBtn');
    const spinner = document.getElementById('updateSpinner');
    const spinnerText = document.getElementById('spinnerText');
    const terminal = document.getElementById('updateTerminal');
    const termContent = document.getElementById('terminalContent');
    
    btn.disabled = true;
    btn.classList.add('opacity-50');
    spinner.classList.remove('hidden');
    spinnerText.innerText = 'INSTALLING UPDATES...';
    // terminal.classList.remove('hidden'); // Keep terminal hidden during update for seamless feel unless error
    termContent.innerText = "> Initializing update process...\n> Waiting for server response...";

    try {
        const formData = new FormData();
        formData.append('action', 'do_update');
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
             terminal.classList.remove('hidden'); // Show terminal on error
             termContent.innerText += "\n> Error parsing response: " + text;
             throw new Error("Server returned invalid response");
        }
        
        termContent.innerText += "\n> " + data.message;
        
        if (data.status === 'success') {
            spinnerText.innerText = 'UPDATE COMPLETE! RELOADING...';
            setTimeout(() => window.location.reload(), 2000);
        } else {
            terminal.classList.remove('hidden'); // Show terminal on error
            spinnerText.innerText = 'UPDATE FAILED.';
            btn.disabled = false;
            btn.classList.remove('opacity-50');
        }
        
    } catch (error) {
        terminal.classList.remove('hidden'); // Show terminal on error
        termContent.innerText += "\n> Error: " + error.message;
        btn.disabled = false;
        btn.classList.remove('opacity-50');
    }
}
</script>

<?php 
include '../includes/footer.php'; 
echo '</main></div></body></html>'; 
?>
