<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if (isset($_POST['login']) && $_POST['login'] == '1') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $users = readCSV(USERS_CSV);
    $login_success = false;
    
    foreach ($users as $user) {
        // Users are stored as indexed arrays: [id, username, password, role]
        if (isset($user[1]) && $user[1] === $username) {
            if (password_verify($password, $user[2])) {
                $_SESSION['user_id'] = $user[0];
                $_SESSION['username'] = $user[1];
                $_SESSION['role'] = $user[3];
                $login_success = true;
                header('Location: dashboard.php');
                exit();
            }
        }
    }
    
    if (!$login_success) {
        $error = "Invalid credentials!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYBRIXEN SIEM - Login</title>
    <link rel="stylesheet" href="styles.css">
    <script src="js/app.js"></script>
    <script src="js/charts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg,#0a0f22,#0c1530 30%,#0a0f22);
        }
        .login-card {
            background: var(--panel);
            border: 1px solid #1a2452;
            border-radius: var(--radius);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--muted);
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            background: #0b122b;
            border: 1px solid #243067;
            border-radius: 0.5rem;
            color: var(--text);
        }
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: #1a3a6b;
            border: 1px solid #2a5a9b;
            color: white;
            border-radius: 0.5rem;
            cursor: pointer;
            margin-top: 1rem;
        }
        .error {
            color: var(--danger);
            text-align: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid var(--danger);
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>CYBRIXEN SIEM</h1>
                <p style="color: var(--muted); margin: 0;">v2.1 - Secure Login</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo escapeHtml($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="login" value="1">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? escapeHtml($_POST['username']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-login">Login</button>
            </form>
            
        </div>
    </div>
</body>
</html>