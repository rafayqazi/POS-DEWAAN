<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

requireLogin();
$pageTitle = "Settings";
include '../includes/header.php';

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
    }
}

$units = readCSV('units');
$categories = readCSV('categories');
?>

<div class="space-y-8">
    <!-- Messages -->
    <?php if($message): ?><div class="max-w-4xl mx-auto bg-green-100 text-green-700 p-4 rounded-lg shadow-sm border-l-4 border-green-500"><?= $message ?></div><?php endif; ?>
    <?php if($error): ?><div class="max-w-4xl mx-auto bg-red-100 text-red-700 p-4 rounded-lg shadow-sm border-l-4 border-red-500"><?= $error ?></div><?php endif; ?>

    <div class="max-w-2xl mx-auto">
        <!-- Password Section -->
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <h2 class="text-2xl font-bold text-gray-800 mb-8 border-b pb-4 flex items-center">
                <i class="fas fa-user-shield mr-4 text-primary"></i> Account Security
            </h2>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-sm uppercase tracking-wide">Current Password</label>
                    <input type="password" name="current_password" required placeholder="••••••••"
                           class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-sm uppercase tracking-wide">New Password</label>
                    <input type="password" name="new_password" required placeholder="Leave blank to keep current"
                           class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-sm uppercase tracking-wide">Confirm New Password</label>
                    <input type="password" name="confirm_password" required placeholder="Repeat new password"
                           class="w-full rounded-xl border-gray-200 border p-3 focus:ring-2 focus:ring-primary focus:border-primary transition outline-none shadow-sm">
                </div>
                <div class="pt-4">
                    <button type="submit" class="w-full bg-primary text-white font-bold py-4 rounded-xl hover:bg-secondary transition shadow-lg transform active:scale-95">
                        <i class="fas fa-save mr-2"></i> Update Security Settings
                    </button>
                    <p class="text-center text-gray-400 text-xs mt-4 italic">Your password is encrypted for maximum security.</p>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
include '../includes/footer.php'; 
echo '</main></div></body></html>'; 
?>
