<?php
// admin.php
require __DIR__ . '/config.php';
require_admin();

$msg   = '';
$error = '';
$users = load_users();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $role     = $_POST['role'] ?? 'analyst';
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } elseif (find_user($username)) {
            $error = 'User already exists.';
        } else {
            $users = load_users(); // reload latest
            $hash  = password_hash($password, PASSWORD_BCRYPT);

            $users[] = [
                'id'            => null,
                'username'      => $username,
                'name'          => $name !== '' ? $name : $username,
                'password_hash' => $hash,
                'role'          => ($role === 'admin') ? 'admin' : 'analyst',
            ];

            save_users($users);
            $users = load_users();
            $msg   = 'User created successfully.';
        }
    }

    if ($action === 'update_user') {
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $role     = $_POST['role'] ?? 'analyst';
        $password = $_POST['password'] ?? '';

        $existing = find_user($username);
        if (!$existing) {
            $error = 'User not found.';
        } else {
            $users = load_users();
            foreach ($users as &$u) {
                if (mb_strtolower($u['username']) === mb_strtolower($username)) {
                    if ($name !== '') {
                        $u['name'] = $name;
                    }
                    $u['role'] = ($role === 'admin') ? 'admin' : 'analyst';

                    if ($password !== '') {
                        $u['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    break;
                }
            }
            unset($u);

            save_users($users);
            $users = load_users();
            $msg   = 'User updated successfully.';
        }
    }

    if ($action === 'delete_user') {
        $username = trim($_POST['username'] ?? '');
        $current  = current_user();

        if (mb_strtolower($username) === mb_strtolower($current['username'])) {
            $error = 'You cannot delete your own account while logged in.';
        } else {
            $users = load_users();
            $beforeCount = count($users);

            $users = array_values(array_filter($users, function ($u) use ($username) {
                return mb_strtolower($u['username']) !== mb_strtolower($username);
            }));

            if ($beforeCount === count($users)) {
                $error = 'User not found or already deleted.';
            } else {
                save_users($users);
                $users = load_users();
                $msg   = 'User deleted successfully.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CYBRIXEN SOC - Admin</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="app">
    <?php render_sidebar('admin'); ?>

    <div class="main-content">
        <?php render_header('Admin - User Management'); ?>

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

            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-header">
                    <h3>Create User</h3>
                </div>
                <div class="card-body">
                    <form method="post" class="admin-form">
                        <input type="hidden" name="action" value="create_user">

                        <div class="form-row">
                            <label for="c-username">Username</label>
                            <input id="c-username" name="username" required>
                        </div>
                        <div class="form-row">
                            <label for="c-name">Display Name</label>
                            <input id="c-name" name="name" placeholder="Optional">
                        </div>
                        <div class="form-row">
                            <label for="c-role">Role</label>
                            <select id="c-role" name="role">
                                <option value="analyst">Analyst</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="c-password">Password</label>
                            <input id="c-password" name="password" type="password" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn primary">Create User</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Existing Users</h3>
                </div>
                <div class="card-body">
                    <table class="user-table">
                        <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= sanitize($u['username']) ?></td>
                                <td><?= sanitize($u['name'] ?? $u['username']) ?></td>
                                <td><?= sanitize($u['role']) ?></td>
                                <td class="user-actions">
                                    <!-- Inline update form -->
                                    <form method="post" style="display:inline-block; margin-right:0.25rem;">
                                        <input type="hidden" name="action" value="update_user">
                                        <input type="hidden" name="username" value="<?= sanitize($u['username']) ?>">
                                        <input type="hidden" name="name" value="<?= sanitize($u['name'] ?? $u['username']) ?>">
                                        <input type="hidden" name="role" value="<?= sanitize($u['role']) ?>">
                                        <button type="submit" class="btn">Reset (keep as is)</button>
                                    </form>

                                    <!-- Delete -->
                                    <form method="post" style="display:inline-block;"
                                          onsubmit="return confirm('Delete user <?= sanitize($u['username']) ?>?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="username" value="<?= sanitize($u['username']) ?>">
                                        <button type="submit" class="btn danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?>
                            <tr>
                                <td colspan="4" style="color:var(--muted); font-size:0.9rem;">
                                    No users found. The default admin is created automatically if users.csv is empty.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <p style="margin-top:1rem; font-size:0.8rem; color:var(--muted);">
                        Note: Password hashes are stored in <code>data/users.csv</code>. Share credentials only
                        with trusted trainees.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
