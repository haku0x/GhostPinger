<?php
require_once 'config.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$isNewInstallation = initUserDatabase();
$hasUsers = hasUsers();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'setup') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            if (addUser($username, $password)) {
                $success = 'Administrator user created successfully. You can now log in.';
                $hasUsers = true;
            } else {
                $error = 'Error creating user.';
            }
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } else {
            if (authenticateUser($username, $password)) {
                $_SESSION['user_logged_in'] = true;
                $_SESSION['username'] = $username;
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostPinger - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --background: #0f172a;
            --card-bg: #1e293b;
            --border: #334155;
        }

        body {
            background-color: var(--background);
            color: #e2e8f0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            background: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.08) 0%, transparent 25%);
            animation: bgPulse 10s ease-in-out infinite alternate;
        }

        @keyframes bgPulse {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .ghost-input {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            color: #e2e8f0;
            transition: all 0.2s ease;
        }

        .ghost-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
            background-color: rgba(15, 23, 42, 0.8);
        }

        .ghost-button {
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .ghost-button::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: buttonShine 3s ease-in-out infinite;
        }

        @keyframes buttonShine {
            0% { transform: translateX(-100%) rotate(45deg); }
            50% { transform: translateX(100%) rotate(45deg); }
            100% { transform: translateX(-100%) rotate(45deg); }
        }

        .ghost-logo {
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
    </style>
</head>
<body class="flex items-center justify-center">
    <div class="animated-bg"></div>

    <div class="w-full max-w-md p-6">
        <div class="text-center mb-8">
            <h1 class="ghost-logo text-4xl font-bold mb-2">GhostPinger</h1>
            <p class="text-gray-400">Continuous URL Pinging</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-900/30 border border-red-800 text-red-300 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-900/30 border border-green-800 text-green-300 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php if (!$hasUsers): ?>
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-blue-400 mb-4">First-time Setup</h2>
                    <p class="text-gray-400 mb-4">
                        Welcome to GhostPinger! Since this is the first login, you need to create an administrator user.
                    </p>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="setup">
                        <div class="space-y-4">
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-400 mb-2">Username:</label>
                                <input type="text" id="username" name="username" 
                                       class="ghost-input w-full rounded-md px-4 py-2 focus:outline-none"
                                       required>
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-400 mb-2">Password:</label>
                                <input type="password" id="password" name="password" 
                                       class="ghost-input w-full rounded-md px-4 py-2 focus:outline-none"
                                       required>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-400 mb-2">Confirm Password:</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="ghost-input w-full rounded-md px-4 py-2 focus:outline-none"
                                       required>
                            </div>
                            <button type="submit" class="ghost-button w-full">
                                <i class="fas fa-user-plus mr-2"></i> Create Administrator
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-blue-400 mb-4">Login</h2>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="login">
                        <div class="space-y-4">
                            <div>
                                <label for="login-username" class="block text-sm font-medium text-gray-400 mb-2">Username:</label>
                                <input type="text" id="login-username" name="username" 
                                       class="ghost-input w-full rounded-md px-4 py-2 focus:outline-none"
                                       required>
                            </div>
                            <div>
                                <label for="login-password" class="block text-sm font-medium text-gray-400 mb-2">Password:</label>
                                <input type="password" id="login-password" name="password" 
                                       class="ghost-input w-full rounded-md px-4 py-2 focus:outline-none"
                                       required>
                            </div>
                            <button type="submit" class="ghost-button w-full">
                                <i class="fas fa-sign-in-alt mr-2"></i> Login
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-6 text-sm text-gray-500">
            &copy; <?php echo date('Y'); ?> GhostPinger | Developed by 
            <a href="https://github.com/haku0x" target="_blank" 
               class="text-blue-400 hover:text-blue-300 transition-colors">
                haku0x
            </a>
        </div>
    </div>
</body>
</html>
