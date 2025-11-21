<?php
require_once 'config.php';
checkAuth();

$logs = readAllLogs(); // Updated to use new function
$alerts = readCSV(ALERTS_CSV);

// Handle filters
$time_filter = getParam('time_filter', INPUT_GET, '24h');
$severity_filter = getParam('severity');

// Apply time filter to logs
$filtered_logs = $logs;
if ($time_filter !== 'all') {
    $now = time();
    switch ($time_filter) {
        case '1h': $cutoff = $now - 3600; break;
        case '24h': $cutoff = $now - 86400; break;
        case '7d': $cutoff = $now - 604800; break;
        case '30d': $cutoff = $now - 2592000; break;
        default: $cutoff = $now - 86400;
    }
    
    $filtered_logs = array_filter($filtered_logs, function($log) use ($cutoff) {
        return strtotime($log['timestamp'] ?? '') >= $cutoff;
    });
}

// Apply severity filter
if (!empty($severity_filter)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($severity_filter) {
        return ($log['severity'] ?? '') === $severity_filter;
    });
}

// Apply same filters to alerts
$filtered_alerts = $alerts;
if ($time_filter !== 'all') {
    $filtered_alerts = array_filter($filtered_alerts, function($alert) use ($cutoff) {
        return strtotime($alert[1]) >= $cutoff;
    });
}

if (!empty($severity_filter)) {
    $filtered_alerts = array_filter($filtered_alerts, function($alert) use ($severity_filter) {
        return $alert[2] === $severity_filter;
    });
}

// Calculate stats with filters
$totalEvents = count($filtered_logs);
$activeAlerts = count(array_filter($filtered_alerts, function($alert) {
    return $alert[5] !== 'resolved';
}));
$criticalEvents = count(array_filter($filtered_logs, function($log) {
    return ($log['severity'] ?? '') === 'critical';
}));
$highEvents = count(array_filter($filtered_logs, function($log) {
    return ($log['severity'] ?? '') === 'high';
}));

// Calculate top event types
$eventTypes = [];
foreach ($filtered_logs as $log) {
    $type = $log['event_category'] ?? ($log['log_type'] ?? 'Unknown');
    $eventTypes[$type] = ($eventTypes[$type] ?? 0) + 1;
}
arsort($eventTypes);
$topEventTypes = array_slice($eventTypes, 0, 5, true);

// Calculate top log sources
$logSources = [];
foreach ($filtered_logs as $log) {
    $source = $log['asset'] ?? 'unknown';
    $logSources[$source] = ($logSources[$source] ?? 0) + 1;
}
arsort($logSources);
$topLogSources = array_slice($logSources, 0, 5, true);

// Get unique severities for filter
$severities = array_unique(array_column($logs, 'severity'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYBRIXEN SIEM - Dashboard</title>

    <?php $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; ?>
    <link rel="stylesheet" href="<?= $BASE ?>styles.css?v=3">

    <script src="<?= $BASE ?>js/app.js?v=2"></script>
    <script src="<?= $BASE ?>js/charts.js?v=2"></script>
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
                <button class="nav-item active" onclick="location.href='dashboard.php'">
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
                <button class="nav-item" onclick="location.href='alerts.php'">
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
                <h2>Security Dashboard</h2>
                <div class="toolbar">
                    <form method="GET" class="filter-form">
                        <select name="time_filter" class="btn" onchange="this.form.submit()">
                            <option value="1h" <?php echo $time_filter === '1h' ? 'selected' : ''; ?>>Last 1 hour</option>
                            <option value="24h" <?php echo $time_filter === '24h' ? 'selected' : ''; ?>>Last 24 hours</option>
                            <option value="7d" <?php echo $time_filter === '7d' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30d" <?php echo $time_filter === '30d' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>All time</option>
                        </select>
                        <select name="severity" class="btn" onchange="this.form.submit()">
                            <option value="">All Severities</option>
                            <?php foreach($severities as $sev): ?>
                            <option value="<?php echo $sev; ?>" <?php echo $severity_filter === $sev ? 'selected' : ''; ?>><?php echo ucfirst($sev); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn" onclick="location.reload()">Refresh</button>
                    </form>
                </div>
            </header>

            <!-- Active Filters -->
            <?php if(!empty($severity_filter)): ?>
            <div class="filter-pills">
                <div class="pill">
                    Severity: <?php echo $severity_filter; ?>
                    <button type="button" onclick="removeFilter('severity')">×</button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Events</div>
                    <div class="stat-value"><?php echo $totalEvents; ?></div>
                    <div class="stat-trend positive">↑ 12%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Alerts</div>
                    <div class="stat-value"><?php echo $activeAlerts; ?></div>
                    <div class="stat-trend negative">↓ 5%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Critical Events</div>
                    <div class="stat-value"><?php echo $criticalEvents; ?></div>
                    <div class="stat-trend positive">↑ 3%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">High Events</div>
                    <div class="stat-value"><?php echo $highEvents; ?></div>
                    <div class="stat-trend negative">↓ 8%</div>
                </div>
            </div>

            <!-- Top Event Types and Log Sources -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; margin: 0 .8rem;">
                <!-- Top Event Types -->
                <div class="card">
                    <div class="card-header">
                        <h3>Top 5 Event Types</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Event Type</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($topEventTypes as $type => $count): ?>
                                    <tr>
                                        <td><?php echo escapeHtml($type); ?></td>
                                        <td><?php echo $count; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Log Sources -->
                <div class="card">
                    <div class="card-header">
                        <h3>Top 5 Log Sources</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($topLogSources as $source => $count): ?>
                                    <tr>
                                        <td><?php echo escapeHtml($source); ?></td>
                                        <td><?php echo $count; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SIEM Health and Recent Alerts -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; margin: .8rem;">
                <!-- SIEM Health Graph -->
                <div class="card">
                    <div class="card-header">
                        <h3>SIEM Health</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="health-chart" height="180"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Critical Alerts</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Severity</th>
                                        <th>Alert</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recentAlerts = array_slice($filtered_alerts, 0, 5);
                                    foreach ($recentAlerts as $alert) {
                                        echo "<tr>
                                            <td>" . formatDate($alert[1]) . "</td>
                                            <td><span class='severity {$alert[2]}'>{$alert[2]}</span></td>
                                            <td>" . escapeHtml($alert[3]) . "</td>
                                            <td>" . escapeHtml($alert[5]) . "</td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; margin: 0 .8rem .8rem;">
                <div class="card">
                    <div class="card-header">
                        <h3>Threat Activity</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="threat-chart" height="180"></canvas>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h3>Event Sources</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="sources-chart" height="180"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // SIEM Health Chart
            const healthCtx = document.getElementById('health-chart').getContext('2d');
            new Chart(healthCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'System Health %',
                        data: [98, 97, 99, 96, 98, 95, 97],
                        borderColor: '#8fffa8',
                        backgroundColor: 'rgba(143, 255, 168, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 90,
                            max: 100
                        }
                    }
                }
            });

            // Initialize other charts
            if (window.siemCharts) {
                siemCharts.initDashboardCharts();
            }
        });

        function removeFilter(field) {
            const url = new URL(window.location.href);
            url.searchParams.delete(field);
            window.location.href = url.toString();
        }
    </script>
</body>

</html>

