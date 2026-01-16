<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $users = readCSV('users');
    $found = false;

    foreach ($users as $u) {
        if ($u['username'] == $username) {
            // Verify password
            if (password_verify($password, $u['password'])) {
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['username'] = $u['username'];
                redirect('index.php');
                $found = true;
                break;
            }
        }
    }

    if (!$found) {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - POS DEWAAN</title>
    <script src="assets/js/tailwind.js"></script>
    <style>
        .login-bg {
            background: linear-gradient(135deg, #0f766e 0%, #064e3b 100%);
        }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 transform transition-all hover:scale-105 duration-300">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-teal-800 mb-2">DEWAAN</h1>
            <p class="text-gray-500">POS & Inventory Management (Excel DB)</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2 uppercase tracking-wider" for="username">
                    Username
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fas fa-user"></i>
                    </span>
                    <input class="w-full pl-10 pr-3 py-3 rounded-lg border-2 border-gray-200 focus:outline-none focus:border-teal-500 transition-colors" id="username" name="username" type="text" placeholder="Enter your username" required>
                </div>
            </div>
            
            <div class="mb-8">
                <label class="block text-gray-700 text-sm font-bold mb-2 uppercase tracking-wider" for="password">
                    Password
                </label>
                <div class="relative">
                     <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input class="w-full pl-10 pr-3 py-3 rounded-lg border-2 border-gray-200 focus:outline-none focus:border-teal-500 transition-colors" id="password" name="password" type="password" placeholder="**************" required>
                </div>
            </div>
            
            <button class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-1" type="submit">
                Sign In
            </button>
        </form>
        
        <div class="mt-6 text-center text-sm text-gray-400">
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="assets/css/all.min.css">
</body>
</html>
