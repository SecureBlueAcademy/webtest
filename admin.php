<?php
require_once 'config.php';
checkAuth();

// Only allow admin users
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$users = readCSV(USERS_CSV);
$current_tab = $_GET['tab'] ?? 'users';

// Handle User Management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'create_user') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        // Check if username already exists
        $username_exists = false;
        foreach ($users as $user) {
            if ($user[1] === $username) {
                $username_exists = true;
                break;
            }
        }
        
        if (!$username_exists) {
            $id = count($users) + 1;
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $new_user = [$id, $username, $hashedPassword, $role];
            $users[] = $new_user;
            
            if (writeCSV(USERS_CSV, $users)) {
                header('Location: admin.php?tab=users&message=User created successfully');
                exit();
            } else {
                header('Location: admin.php?tab=users&message=Error creating user');
                exit();
            }
        } else {
            header('Location: admin.php?tab=users&message=Username already exists');
            exit();
        }
    }

    if ($_POST['action'] === 'update_user') {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        
        $user_found = false;
        foreach ($users as &$user) {
            if ($user['id'] == $user_id) {
                $user['username'] = $username;
                $user['role'] = $role;
                if (!empty($_POST['password'])) {
                    $user['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                $user_found = true;
                break;
            }
        }
        
        if ($user_found) {
            // Convert back to indexed array for CSV writing
            $users_indexed = [];
            foreach ($users as $user) {
                $users_indexed[] = [$user['id'], $user['username'], $user['password'], $user['role']];
            }
            
            if (writeCSV(USERS_CSV, $users_indexed)) {
                header('Location: admin.php?tab=users&message=User updated successfully');
                exit();
            } else {
                header('Location: admin.php?tab=users&message=Error updating user');
                exit();
            }
        }
    }

    if ($_POST['action'] === 'delete_user') {
        $user_id = $_POST['user_id'];
        $new_users = [];
        
        foreach ($users as $user) {
            if ($user['id'] != $user_id) {
                $new_users[] = $user;
            }
        }
        
        // Reindex IDs
        foreach ($new_users as $index => &$user) {
            $user['id'] = $index + 1;
        }
        
        // Convert back to indexed array for CSV writing
        $users_indexed = [];
        foreach ($new_users as $user) {
            $users_indexed[] = [$user['id'], $user['username'], $user['password'], $user['role']];
        }
        
        if (writeCSV(USERS_CSV, $users_indexed)) {
            header('Location: admin.php?tab=users&message=User deleted successfully');
            exit();
        } else {
            header('Location: admin.php?tab=users&message=Error deleting user');
            exit();
        }
    }

    // Handle Logs Management
    if ($_POST['action'] === 'upload_logs') {
        if (isset($_FILES['logs_file']) && $_FILES['logs_file']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['logs_file']['tmp_name'];
            $destination = ASSETS_DIR . 'uploaded_logs.csv';
            
            if (move_uploaded_file($tmp_name, $destination)) {
                $message = "Logs file uploaded successfully";
            } else {
                $message = "Error uploading logs file";
            }
            header('Location: admin.php?tab=logs&message=' . urlencode($message));
            exit();
        }
    }
}

$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYBRIXEN SIEM - Admin Panel</title>
    <?php $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; ?>
    <link rel="stylesheet" href="<?= $BASE ?>styles.css?v=5">
    <script src="js/app.js"></script>
</head>
<body>
    <div class="app">
        <!-- Sidebar -->
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
                <button class="nav-item" onclick="location.href='logs.php'">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Log Analysis</span>
                </button>
                <button class="nav-item" onclick="location.href='alerts.php'">
                    <span class="nav-icon">🚨</span>
                    <span class="nav-text">Alerts & Incidents</span>
                </button>
                <button class="nav-item" onclick="location.href='assets.php'">
                    <span class="nav-icon">🖥️</span>
                    <span class="nav-text">Assets & Log Sources</span>
                </button>
                <button class="nav-item active" onclick="location.href='admin.php'">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Admin Panel</span>
                </button>
                
                <!-- Additional SIEM Features -->
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Investigation</div>
                    <button class="nav-item">
                        <span class="nav-icon">🔍</span>
                        <span class="nav-text">Search & Investigate</span>
                    </button>
                    <button class="nav-item">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Threat Hunting</span>
                    </button>
                    <button class="nav-item">
                        <span class="nav-icon">🔗</span>
                        <span class="nav-text">Correlation Rules</span>
                    </button>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Management</div>
                    <button class="nav-item">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">System Settings</span>
                    </button>
                    <button class="nav-item">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">User Management</span>
                    </button>
                    <button class="nav-item">
                        <span class="nav-icon">🛡️</span>
                        <span class="nav-text">Security Policies</span>
                    </button>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Analytics</div>
                    <button class="nav-item">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Reports</span>
                    </button>
                    <button class="nav-item">
                        <span class="nav-icon">📉</span>
                        <span class="nav-text">Dashboards</span>
                    </button>
                    <button class="nav-item">
                        <span class="nav-icon">🔔</span>
                        <span class="nav-text">Notifications</span>
                    </button>
                </div>

                <button class="nav-item" onclick="location.href='logout.php'">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-text">Logout (<?php echo escapeHtml($_SESSION['username']); ?>)</span>
                </button>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h2>Admin Panel</h2>
                <div class="toolbar">
                    <span class="badge" style="background: var(--accent);">Admin User</span>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="notification success" style="margin: 1rem;">
                <div class="notification-content">
                    <span class="notification-message"><?php echo escapeHtml($message); ?></span>
                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Admin Tabs -->
            <div class="admin-tabs">
                <button class="admin-tab <?php echo $current_tab === 'users' ? 'active' : ''; ?>" 
                        onclick="switchTab('users')">User Management</button>
                <button class="admin-tab <?php echo $current_tab === 'logs' ? 'active' : ''; ?>" 
                        onclick="switchTab('logs')">Logs Management</button>
            </div>

            <!-- User Management Tab -->
            <div class="admin-tab-content <?php echo $current_tab === 'users' ? 'active' : ''; ?>" id="users-tab">
                <div class="card" style="margin: 1rem;">
                    <div class="card-header">
                        <h3>Create New User</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="admin-form">
                            <input type="hidden" name="action" value="create_user">
                            <div class="form-row">
                                <label for="username">Username:</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            <div class="form-row">
                                <label for="password">Password:</label>
                                <input type="password" id="password" name="password" required>
                            </div>
                            <div class="form-row">
                                <label for="role">Role:</label>
                                <select id="role" name="role" required>
                                    <option value="analyst">Analyst</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn primary">Create User</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card" style="margin: 1rem;">
                    <div class="card-header">
                        <h3>Existing Users</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="user-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo escapeHtml($user['username']); ?></td>
                                        <td><span class="badge"><?php echo $user['role']; ?></span></td>
                                        <td class="user-actions">
                                            <button class="btn" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">Edit</button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Management Tab -->
            <div class="admin-tab-content <?php echo $current_tab === 'logs' ? 'active' : ''; ?>" id="logs-tab">
                <div class="card" style="margin: 1rem;">
                    <div class="card-header">
                        <h3>Upload Logs File</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="upload-form">
                            <input type="hidden" name="action" value="upload_logs">
                            
                            <div class="file-upload-area" id="upload-area">
                                <input type="file" name="logs_file" id="logs_file" class="file-input" accept=".csv" required>
                                <label for="logs_file" class="upload-btn">Choose CSV File</label>
                                <p style="margin-top: 0.5rem; color: var(--muted);">or drag and drop file here</p>
                                <div class="file-info" id="file-info" style="display: none;">
                                    Selected file: <span id="file-name"></span>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn primary">Upload Logs</button>
                                <button type="button" class="btn" onclick="generateSampleData()">Generate Sample Data</button>
                            </div>
                        </form>
                        
                        <div style="margin-top: 2rem; padding: 1rem; background: #0f1834; border-radius: var(--radius);">
                            <h4>Current Logs Information</h4>
                            <?php
                            $all_logs = readAllLogs();
                            $total_entries = count($all_logs);
                            $log_files = getAllLogFiles();
                            ?>
                            <p>Total log entries: <?php echo $total_entries; ?></p>
                            <p>Total log sources: <?php echo count($log_files); ?></p>
                            <p style="color: var(--warn);"><strong>Warning:</strong> Uploading a new file will create a new log source.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="edit-user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="btn" onclick="hideEditModal()">Close</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="edit-user-form">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="form-row">
                        <label for="edit_username">Username:</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    <div class="form-row">
                        <label for="edit_password">New Password (leave blank to keep current):</label>
                        <input type="password" id="edit_password" name="password">
                    </div>
                    <div class="form-row">
                        <label for="edit_role">Role:</label>
                        <select id="edit_role" name="role" required>
                            <option value="analyst">Analyst</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            window.location.href = 'admin.php?tab=' + tabName;
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit-user-modal').classList.add('active');
        }

        function hideEditModal() {
            document.getElementById('edit-user-modal').classList.remove('active');
        }

        function generateSampleData() {
            if (confirm('This will regenerate all sample log data. Continue?')) {
                window.location.href = 'generate_sample_data.php';
            }
        }

        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('logs_file');
            const uploadArea = document.getElementById('upload-area');
            const fileInfo = document.getElementById('file-info');
            const fileName = document.getElementById('file-name');

            fileInput.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    fileName.textContent = this.files[0].name;
                    fileInfo.style.display = 'block';
                }
            });

            // Drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    fileName.textContent = e.dataTransfer.files[0].name;
                    fileInfo.style.display = 'block';
                }
            });

            // Close modal on outside click
            document.getElementById('edit-user-modal').addEventListener('click', function(e) {
                if (e.target === this) hideEditModal();
            });
        });
    </script>
</body>
</html>