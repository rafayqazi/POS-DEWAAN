<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
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
        
        // 1. Fetch latest from all remotes
        exec('git fetch --all 2>&1', $fetch_out, $fetch_ret);
        
        // 2. Get current branch name
        exec('git rev-parse --abbrev-ref HEAD 2>&1', $branch_out, $branch_ret);
        $current_branch = $branch_out[0] ?? 'master';
        
        // 3. Compare local HEAD with the corresponding remote branch
        // We check how many commits the remote is ahead of local
        exec("git rev-list --count HEAD..origin/$current_branch 2>&1", $count_out, $count_ret);
        
        $commits_behind = (int)($count_out[0] ?? 0);
        $updateAvailable = ($commits_behind > 0);
        
        // Check local vs remote hash for additional info
        exec('git rev-parse HEAD 2>&1', $local_hash_out);
        exec("git rev-parse origin/$current_branch 2>&1", $remote_hash_out);
        $local_hash = substr($local_hash_out[0] ?? '', 0, 7);
        $remote_hash = substr($remote_hash_out[0] ?? '', 0, 7);
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success', 
            'update_available' => $updateAvailable,
            'message' => $updateAvailable ? "$commits_behind new update(s) found!" : "You are up to date.",
            'debug' => "Branch: $current_branch | Local: $local_hash | Remote: $remote_hash"
        ]);
        exit;
    } elseif ($action == 'do_update') {
        ob_end_clean();
        ob_start();
        ini_set('display_errors', 0);
        set_time_limit(120);
        
        // Get current branch to pull correctly
        exec('git rev-parse --abbrev-ref HEAD 2>&1', $branch_out);
        $current_branch = $branch_out[0] ?? 'master';
        
        // Pull changes
        exec("git pull origin $current_branch 2>&1", $output, $return_var);
        
        ob_clean();
        header('Content-Type: application/json');
        if ($return_var === 0) {
            echo json_encode(['status' => 'success', 'message' => "Update installed successfully from $current_branch branch!"]);
        } else {
             echo json_encode(['status' => 'error', 'message' => "Update failed: " . implode(" ", $output)]);
        }
        exit;
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
        <button onclick="switchTab('updates')" id="tab-updates" class="tab-btn px-8 py-4 font-bold text-gray-500 hover:text-primary transition-colors border-b-2 border-transparent focus:outline-none relative whitespace-nowrap">
            <i class="fas fa-cloud-download-alt mr-2"></i> Updates
        </button>
    </div>

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
