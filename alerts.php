<?php
require_once 'config.php';
checkAuth();

$alerts = readCSV(ALERTS_CSV);

// Handle alert updates
if ($_POST['update_alert'] ?? false) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        http_response_code(400);
        exit('Invalid request token');
    }

    $alertId = getParam('alert_id', INPUT_POST, '');
    $field = getParam('field', INPUT_POST, '');
    $value = getParam('value', INPUT_POST, '');

    foreach ($alerts as &$alert) {
        if ($alert[0] === $alertId) {
            if ($field === 'status') $alert[5] = $value;
            if ($field === 'assigned') $alert[6] = $value;
            break;
        }
    }
    writeCSV(ALERTS_CSV, $alerts);
    header('Location: alerts.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYBRIXEN SIEM - Alerts & Incidents</title>
    <?php $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; ?>
    <link rel="stylesheet" href="<?= $BASE ?>styles.css?v=3">
	<script src="js/app.js"></script>
    <script src="js/charts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <button class="nav-item" onclick="location.href='assets.php'">
                    <span class="nav-icon">🖥️</span>
                    <span class="nav-text">Assets & Log Sources</span>
                </button>
                <button class="nav-item" onclick="location.href='logs.php'">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Log Analysis</span>
                </button>
                <button class="nav-item active" onclick="location.href='alerts.php'">
                    <span class="nav-icon">🚨</span>
                    <span class="nav-text">Alerts & Incidents</span>
                </button>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <button class="nav-item" onclick="location.href='admin.php'">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Admin Panel</span>
                </button>
                <?php endif; ?>
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
                <h2>Alerts & Incidents</h2>
                <div class="toolbar">
                    <button class="btn" onclick="location.reload()">Refresh</button>
                    <button class="btn primary">New Incident</button>
                    <button class="btn success" onclick="acknowledgeAll()">Acknowledge All</button>
                </div>
            </header>

            <!-- Alert Summary -->
            <div class="stats-grid">
                <?php
                $totalAlerts = count($alerts);
                $criticalAlerts = count(array_filter($alerts, function($alert) {
                    return $alert[2] === 'critical';
                }));
                $highAlerts = count(array_filter($alerts, function($alert) {
                    return $alert[2] === 'high';
                }));
                $unassignedAlerts = count(array_filter($alerts, function($alert) {
                    return $alert[6] === 'unassigned';
                }));
                ?>
                <div class="stat-card">
                    <div class="stat-label">Total Alerts</div>
                    <div class="stat-value"><?php echo $totalAlerts; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Critical</div>
                    <div class="stat-value" style="color: #ff6b6b;"><?php echo $criticalAlerts; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">High</div>
                    <div class="stat-value" style="color: #ffa366;"><?php echo $highAlerts; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Unassigned</div>
                    <div class="stat-value" style="color: #ffd66b;"><?php echo $unassignedAlerts; ?></div>
                </div>
            </div>

            <!-- Alert Filters -->
            <div style="padding: 1rem; border-bottom: 1px solid #1a2452; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <strong>Filter by:</strong>
                <select class="btn" onchange="filterAlerts()" id="severity-filter">
                    <option value="">All Severities</option>
                    <option value="critical">Critical</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
                <select class="btn" onchange="filterAlerts()" id="status-filter">
                    <option value="">All Statuses</option>
                    <option value="new">New</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                </select>
                <select class="btn" onchange="filterAlerts()" id="assigned-filter">
                    <option value="">All Assignments</option>
                    <option value="unassigned">Unassigned</option>
                    <option value="assigned">Assigned</option>
                </select>
                <input type="text" class="query-input" style="flex: 1; max-width: 300px;" placeholder="Search alerts..." id="alert-search" oninput="filterAlerts()">
            </div>

            <!-- Alerts Table -->
            <div class="card" style="margin: 0; border: none; border-radius: 0; flex: 1; display: flex; flex-direction: column;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Security Alerts</h3>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <span id="alerts-count"><?php echo $totalAlerts; ?> alerts found</span>
                    </div>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Severity</th>
                                <th>Alert Title</th>
                                <th>Description</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts as $alert): ?>
                            <tr class="alert-row" data-severity="<?php echo $alert[2]; ?>" data-status="<?php echo $alert[5]; ?>" data-assigned="<?php echo $alert[6]; ?>">
                                <td><?php echo formatDate($alert[1]); ?></td>
                                <td><span class="severity <?php echo $alert[2]; ?>"><?php echo $alert[2]; ?></span></td>
                                <td><strong><?php echo escapeHtml($alert[3]); ?></strong></td>
                                <td><?php echo escapeHtml($alert[4]); ?></td>
                                <td><?php echo escapeHtml($alert[7]); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="update_alert" value="1">
                                        <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                                        <input type="hidden" name="alert_id" value="<?php echo $alert[0]; ?>">
                                        <input type="hidden" name="field" value="status">
                                        <select class="btn" name="value" onchange="this.form.submit()" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">
                                            <option value="new" <?php echo $alert[5] === 'new' ? 'selected' : ''; ?>>New</option>
                                            <option value="in_progress" <?php echo $alert[5] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $alert[5] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="update_alert" value="1">
                                        <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                                        <input type="hidden" name="alert_id" value="<?php echo $alert[0]; ?>">
                                        <input type="hidden" name="field" value="assigned">
                                        <select class="btn" name="value" onchange="this.form.submit()" style="font-size: 0.8rem; padding: 0.25rem 0.5rem;">
                                            <option value="unassigned" <?php echo $alert[6] === 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                                            <option value="analyst1" <?php echo $alert[6] === 'analyst1' ? 'selected' : ''; ?>>Analyst 1</option>
                                            <option value="analyst2" <?php echo $alert[6] === 'analyst2' ? 'selected' : ''; ?>>Analyst 2</option>
                                            <option value="analyst3" <?php echo $alert[6] === 'analyst3' ? 'selected' : ''; ?>>Analyst 3</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <button class="btn" onclick="viewAlertDetails(<?php echo htmlspecialchars(json_encode($alert)); ?>)">View</button>
                                    <button class="btn primary">Investigate</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Alert Detail Modal -->
    <div class="modal" id="alert-detail-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Alert Details</h3>
                <button class="btn" onclick="hideAlertModal()">Close</button>
            </div>
            <div class="modal-body">
                <div id="alert-detail-content"></div>
            </div>
        </div>
    </div>

    <script>
        function filterAlerts() {
            const severity = document.getElementById('severity-filter').value;
            const status = document.getElementById('status-filter').value;
            const assigned = document.getElementById('assigned-filter').value;
            const search = document.getElementById('alert-search').value.toLowerCase();
            
            const rows = document.querySelectorAll('.alert-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const rowSeverity = row.dataset.severity;
                const rowStatus = row.dataset.status;
                const rowAssigned = row.dataset.assigned;
                const rowText = row.textContent.toLowerCase();
                
                const severityMatch = !severity || rowSeverity === severity;
                const statusMatch = !status || rowStatus === status;
                const assignedMatch = !assigned || 
                    (assigned === 'unassigned' && rowAssigned === 'unassigned') ||
                    (assigned === 'assigned' && rowAssigned !== 'unassigned');
                const searchMatch = !search || rowText.includes(search);
                
                if (severityMatch && statusMatch && assignedMatch && searchMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('alerts-count').textContent = `${visibleCount} alerts found`;
        }

        function viewAlertDetails(alert) {
            document.getElementById('alert-detail-content').innerHTML = `
                <div style="margin-bottom: 1rem;">
                    <h4>${alert[3]}</h4>
                    <p><strong>Severity:</strong> <span class="severity ${alert[2]}">${alert[2]}</span></p>
                    <p><strong>Source:</strong> ${alert[7]}</p>
                    <p><strong>Time:</strong> ${alert[1]}</p>
                    <p><strong>Status:</strong> ${alert[5]}</p>
                    <p><strong>Assigned To:</strong> ${alert[6]}</p>
                </div>
                <div>
                    <h5>Description</h5>
                    <p>${alert[4]}</p>
                </div>
                <div class="json-view" style="margin-top: 1rem;">
                    ${JSON.stringify(alert, null, 2)}
                </div>
            `;
            document.getElementById('alert-detail-modal').classList.add('active');
        }

        function hideAlertModal() {
            document.getElementById('alert-detail-modal').classList.remove('active');
        }

        function acknowledgeAll() {
            if (confirm('Acknowledge all new alerts and set them to In Progress?')) {
                // This would typically make an AJAX call to update all alerts
                alert('All new alerts acknowledged and set to In Progress');
                location.reload();
            }
        }

        // Close modal on outside click
        document.getElementById('alert-detail-modal').addEventListener('click', function(e) {
            if (e.target === this) hideAlertModal();
        });
    </script>
</body>
</html>