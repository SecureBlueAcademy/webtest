<?php
// Script to create new users - run this via command line or web interface
require_once 'config.php';

if (php_sapi_name() === 'cli' || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    
    function createUser($username, $password, $role = 'analyst') {
        $users = readCSV(USERS_CSV);
        
        $id = count($users) + 1;
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $users[] = [$id, $username, $hashedPassword, $role];
        
        if (writeCSV(USERS_CSV, $users)) {
            return "User '$username' created successfully with ID $id";
        } else {
            return "Error creating user '$username'";
        }
    }
    
    // Example usage via web: 
    if (isset($_POST['create_user'])) {
        $result = createUser($_POST['username'], $_POST['password'], $_POST['role']);
        echo $result;
    }
    
    // Example usage via CLI: php create_user.php username password role
    if (php_sapi_name() === 'cli') {
        if ($argc >= 3) {
            $username = $argv[1];
            $password = $argv[2];
            $role = $argv[3] ?? 'analyst';
            echo createUser($username, $password, $role) . "\n";
        } else {
            echo "Usage: php create_user.php username password [role]\n";
        }
    }
    
} else {
    echo "Access denied.";
}
?>

<!-- Web interface for user creation (optional) -->
<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - CYBRIXEN SIEM</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app">
        <nav class="sidebar">
            <div class="sidebar-header">
                <button class="sidebar-toggle">☰</button>
                <h1>CYBRIXEN SIEM</h1>
                <span class="badge">v2.1</span>
            </div>
            <div class="nav-menu">
                <button class="nav-item" onclick="location.href='dashboard.php'">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </button>
                <button class="nav-item" onclick="location.href='create_user.php'">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">Create User</span>
                </button>
                <button class="nav-item" onclick="location.href='logout.php'">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-text">Logout (<?php echo escapeHtml($_SESSION['username']); ?>)</span>
                </button>
            </div>
        </nav>

        <main class="main-content">
            <header class="header">
                <h2>Create New User</h2>
                <div class="toolbar">
                    <button class="btn" onclick="location.href='dashboard.php'">Back to Dashboard</button>
                </div>
            </header>

            <div class="card" style="margin: 1rem; max-width: 500px;">
                <div class="card-header">
                    <h3>User Creation Form</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div style="margin-bottom: 1rem;">
                            <label for="username" style="display: block; margin-bottom: 0.5rem; color: var(--muted);">Username</label>
                            <input type="text" id="username" name="username" required style="width: 100%; padding: 0.75rem; background: #0b122b; border: 1px solid #243067; border-radius: 0.5rem; color: var(--text);">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label for="password" style="display: block; margin-bottom: 0.5rem; color: var(--muted);">Password</label>
                            <input type="password" id="password" name="password" required style="width: 100%; padding: 0.75rem; background: #0b122b; border: 1px solid #243067; border-radius: 0.5rem; color: var(--text);">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label for="role" style="display: block; margin-bottom: 0.5rem; color: var(--muted);">Role</label>
                            <select id="role" name="role" style="width: 100%; padding: 0.75rem; background: #0b122b; border: 1px solid #243067; border-radius: 0.5rem; color: var(--text);">
                                <option value="analyst">Analyst</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" name="create_user" class="btn primary" style="width: 100%;">Create User</button>
                    </form>
                    
                    <?php if (isset($result)): ?>
                        <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(143, 255, 168, 0.1); border: 1px solid var(--ok); border-radius: 0.5rem; color: var(--ok);">
                            <?php echo $result; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php endif; ?>