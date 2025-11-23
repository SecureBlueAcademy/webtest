<?php
// change_password.php
require __DIR__ . '/config.php';
require_login();

$user  = current_user();
$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $dbUser = find_user($user['username']);

    if (!$dbUser) {
        $error = 'User record not found.';
    } elseif (!password_verify($current, $dbUser['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new === '' || strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $users = load_users();
        foreach ($users as &$u) {
            if (mb_strtolower($u['username']) === mb_strtolower($dbUser['username'])) {
                $u['password_hash'] = password_hash($new, PASSWORD_BCRYPT);
                break;
            }
        }
        unset($u);

        save_users($users);

        // Update session copy
        $_SESSION['user']['name'] = $dbUser['name'] ?? $dbUser['username'];

        $msg = 'Password changed successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CYBRIXEN SOC - Change Password</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="app">
    <?php render_sidebar(); ?>

    <div class="main-content">
        <?php render_header('Change Password'); ?>

        <div style="margin:1rem;">
            <?php if ($msg): ?>
                <div class="notification success">
                    <div class="notification-content">
                        <span><?= sanitize($msg) ?></span>
                        <button class="notification-close" onclick="this.closest('.notification').remove();">&times;</button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="notification error">
                    <div class="notification-content">
                        <span><?= sanitize($error) ?></span>
                        <button class="notification-close" onclick="this.closest('.notification').remove();">&times;</button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Update Your Password</h3>
                </div>
                <div class="card-body">
                    <form method="post" class="admin-form">
                        <div class="form-row">
                            <label for="current">Current Password</label>
                            <input type="password" id="current" name="current_password" required>
                        </div>
                        <div class="form-row">
                            <label for="new">New Password</label>
                            <input type="password" id="new" name="new_password" required>
                        </div>
                        <div class="form-row">
                            <label for="confirm">Confirm New Password</label>
                            <input type="password" id="confirm" name="confirm_password" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn primary">Change Password</button>
                        </div>
                    </form>
                    <p style="margin-top:1rem; font-size:0.8rem; color:var(--muted);">
                        Use a strong password. This platform is public on Hostinger, so treat it like a real SOC portal.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
