<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (!isset($_POST['accept_terms'])) {
        $error = "You must accept the Terms and Conditions to login.";
    } else {
        $users = readCSV('users');
        $found = false;

        foreach ($users as $u) {
            if ($u['username'] == $username) {
                if (password_verify($password, $u['password'])) {
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['username'] = $u['username'];
                    $_SESSION['check_updates'] = true; 
                    $_SESSION['show_welcome'] = true; 
                    redirect('index.php');
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            $error = "Invalid credentials. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - POS DEWAAN</title>
    <script src="assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="assets/css/all.min.css">
</head>
<body class="h-full">
    <div class="min-h-full flex">
        <!-- Left Panel - Branding -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-teal-600 to-teal-800 relative overflow-hidden">
            <!-- Decorative Elements -->
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAxMCAwIEwgMCAwIDAgMTAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-40"></div>
            
            <div class="relative z-10 flex flex-col justify-between p-12 text-white w-full">
                <!-- Logo & Brand -->
                <div>
                    <div class="flex items-center gap-3 mb-16">
                        <div class="w-12 h-12 bg-white/10 backdrop-blur-sm rounded-xl flex items-center justify-center border border-white/20">
                            <i class="fas fa-leaf text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight">DEWAAN</h1>
                            <p class="text-xs text-white/60 font-medium">POS System</p>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1 flex flex-col justify-center max-w-md">
                    <h2 class="text-4xl font-extrabold leading-tight mb-6">
                        Manage your business with confidence
                    </h2>
                    <p class="text-lg text-white/80 leading-relaxed mb-8">
                        A powerful Point of Sale and Inventory Management System designed for modern businesses.
                    </p>
                    
                    <!-- Features -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check text-sm"></i>
                            </div>
                            <span class="text-white/90 font-medium">Real-time inventory tracking</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check text-sm"></i>
                            </div>
                            <span class="text-white/90 font-medium">Customer & dealer ledgers</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check text-sm"></i>
                            </div>
                            <span class="text-white/90 font-medium">Detailed reports & analytics</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-white/50 text-xs">
                    <p>&copy; 2026 DEWAAN. Developed by <span class="text-white/80 font-semibold">Abdul Rafay</span></p>
                </div>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="flex-1 flex flex-col justify-center px-4 sm:px-6 lg:px-20 xl:px-24 bg-white">
            <div class="mx-auto w-full max-w-sm">
                <!-- Mobile Logo -->
                <div class="lg:hidden flex items-center gap-3 mb-8">
                    <div class="w-10 h-10 bg-teal-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-leaf text-white text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">DEWAAN</h1>
                        <p class="text-xs text-gray-500">POS System</p>
                    </div>
                </div>

                <div>
                    <h2 class="text-3xl font-extrabold text-gray-900">
                        Sign in
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Welcome back! Please enter your details.
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="mt-6 rounded-lg bg-red-50 p-4 border border-red-200">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800"><?php echo $error; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="mt-8 space-y-6" method="POST">
                    <div class="space-y-5">
                        <!-- Username -->
                        <div>
                            <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                                Username
                            </label>
                            <input id="username" name="username" type="text" required 
                                   class="appearance-none block w-full px-4 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all text-gray-900 font-medium"
                                   placeholder="Enter your username">
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                                Password
                            </label>
                            <div class="relative">
                                <input id="password" name="password" type="password" required 
                                       class="appearance-none block w-full px-4 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent transition-all text-gray-900 font-medium pr-12"
                                       placeholder="••••••••">
                                <button type="button" onclick="togglePassword()" 
                                        class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Terms -->
                        <div class="flex items-center">
                            <input id="accept_terms" name="accept_terms" type="checkbox" required
                                   class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded">
                            <label for="accept_terms" class="ml-2 block text-sm text-gray-700">
                                I accept the <a href="terms.php" class="font-semibold text-teal-600 hover:text-teal-500">Terms and Conditions</a>
                            </label>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-colors">
                            Sign in
                        </button>
                    </div>
                </form>

                <!-- Footer for mobile -->
                <div class="mt-8 lg:hidden text-center text-xs text-gray-500">
                    <p>&copy; 2026 DEWAAN. Developed by <span class="font-semibold text-gray-700">Abdul Rafay</span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
