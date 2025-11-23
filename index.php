<?php
// index.php - Login
require __DIR__ . '/config.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $user = find_user($username);

        if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
            // Successful login
            $_SESSION['user'] = [
                'username' => $user['username'],
                'name'     => $user['name'] ?? $user['username'],
                'role'     => $user['role'] ?? 'analyst',
            ];
            redirect('dashboard.php');
        } else {
            // Wrong credentials
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CYBRIXEN SOC - Login</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top, #1a2246 0, #050814 55%, #02030a 100%);
        }
        .login-card {
            background: var(--panel);
            border-radius: var(--radius);
            border: 1px solid #1a2452;
            box-shadow: 0 15px 40px rgba(0,0,0,0.6);
            width: 400px;
            max-width: 95vw;
            padding: 2rem;
        }
        .login-title {
            margin-bottom: 0.5rem;
            color: var(--accent);
            font-size: 1.4rem;
        }
        .login-subtitle {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .form-row {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            margin-bottom: 1rem;
        }
        .form-row label {
            font-size: 0.85rem;
            color: var(--muted);
        }
        .form-row input {
            padding: 0.7rem;
            border-radius: 0.4rem;
            border: 1px solid #243067;
            background: #050b1f;
            color: var(--text);
        }
        .form-row input:focus {
            outline: none;
            border-color: var(--accent);
        }
        .error-banner {
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: 0.4rem;
            background: rgba(255,107,107,0.08);
            border: 1px solid var(--danger);
            color: var(--danger);
            font-size: 0.85rem;
        }
        .login-footer {
            margin-top: 1rem;
            font-size: 0.8rem;
            color: var(--muted);
        }
        .login-footer strong {
            color: var(--accent);
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <h1 class="login-title">CYBRIXEN SOC Console</h1>
        <p class="login-subtitle">
            Centralized management for SIEM & EDR training scenarios.  
            Log in with your assigned analyst or admin account.
        </p>

        <?php if ($error): ?>
            <div class="error-banner">
                <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="form-row">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    autofocus
                    value="<?= isset($_POST['username']) ? sanitize($_POST['username']) : '' ?>"
                >
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; margin-top:1.25rem;">
                <button type="submit" class="btn primary">Login</button>
            </div>

        </form>
    </div>
</div>
</body>
</html>
