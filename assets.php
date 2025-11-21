<?php
require_once 'config.php';
checkAuth();

$logFiles = getAllLogFiles();
$assets = [];

// Organize assets and log types
foreach ($logFiles as $fileInfo) {
    $asset = $fileInfo['asset'];
    $logType = $fileInfo['log_type'];
    
    if (!isset($assets[$asset])) {
        $assets[$asset] = [
            'name' => $asset,
            'log_types' => [],
            'total_logs' => 0,
            'last_updated' => null
        ];
    }
    
    $logs = readCSV($fileInfo['path']);
    $assets[$asset]['log_types'][$logType] = [
        'name' => $logType,
        'count' => count($logs),
        'last_entry' => !empty($logs) ? $logs[0]['timestamp'] : 'No data',
        'file_path' => $fileInfo['path']
    ];
    
    $assets[$asset]['total_logs'] += count($logs);
    
    // Update last updated time
    if (!empty($logs)) {
        $firstLogTime = strtotime($logs[0]['timestamp']);
        if (!$assets[$asset]['last_updated'] || $firstLogTime > strtotime($assets[$asset]['last_updated'])) {
            $assets[$asset]['last_updated'] = $logs[0]['timestamp'];
        }
    }
}

// Sort assets by total logs
uasort($assets, function($a, $b) {
    return $b['total_logs'] - $a['total_logs'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYBRIXEN SIEM - Assets & Log Sources</title>
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
                <button class="nav-item active" onclick="location.href='assets.php'">
                    <span class="nav-icon">🖥️</span>
                    <span class="nav-text">Assets & Log Sources</span>
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
                <h2>Assets & Log Sources</h2>
                <div class="toolbar">
                    <button class="btn" onclick="location.reload()">Refresh</button>
                    <button class="btn primary" onclick="scanForNewAssets()">Scan for New Assets</button>
                    <span class="badge"><?php echo count($assets); ?> Assets</span>
                </div>
            </header>

            <!-- Assets Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Assets</div>
                    <div class="stat-value"><?php echo count($assets); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Log Sources</div>
                    <div class="stat-value"><?php echo count($logFiles); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Log Entries</div>
                    <div class="stat-value"><?php 
                        $totalEntries = 0;
                        foreach ($assets as $asset) {
                            $totalEntries += $asset['total_logs'];
                        }
                        echo $totalEntries;
                    ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Last Updated</div>
                    <div class="stat-value" style="font-size: 1.2rem;">
                        <?php
                        $latestTime = null;
                        foreach ($assets as $asset) {
                            if ($asset['last_updated'] && (!$latestTime || strtotime($asset['last_updated']) > strtotime($latestTime))) {
                                $latestTime = $asset['last_updated'];
                            }
                        }
                        echo $latestTime ? formatDate($latestTime) : 'Never';
                        ?>
                    </div>
                </div>
            </div>

            <!-- Assets List -->
            <div class="card" style="margin: 1rem;">
                <div class="card-header">
                    <h3>Managed Assets</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Asset Name</th>
                                    <th>Log Sources</th>
                                    <th>Total Logs</th>
                                    <th>Last Updated</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo ucfirst(str_replace('-', ' ', $asset['name'])); ?></strong>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                            <?php foreach ($asset['log_types'] as $logType): ?>
                                            <span class="badge" style="background: var(--accent); font-size: 0.7rem;">
                                                <?php echo ucfirst($logType['name']); ?> (<?php echo $logType['count']; ?>)
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $asset['total_logs']; ?></td>
                                    <td><?php echo $asset['last_updated'] ? formatDate($asset['last_updated']) : 'No data'; ?></td>
                                    <td>
                                        <span class="badge" style="background: var(--ok);">Active</span>
                                    </td>
                                    <td>
                                        <button class="btn" onclick="viewAssetLogs('<?php echo $asset['name']; ?>')">View Logs</button>
                                        <button class="btn primary" onclick="viewAssetDetails('<?php echo $asset['name']; ?>', <?php echo htmlspecialchars(json_encode($asset)); ?>)">Details</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Log Sources by Type -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1rem;">
                <!-- Windows Assets -->
                <div class="card">
                    <div class="card-header">
                        <h3>Windows Assets</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $windowsAssets = array_filter($assets, function($asset) {
                            return strpos($asset['name'], 'windows') !== false;
                        });
                        ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Log Types</th>
                                        <th>Log Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($windowsAssets as $asset): ?>
                                    <tr>
                                        <td><?php echo ucfirst(str_replace('-', ' ', $asset['name'])); ?></td>
                                        <td><?php echo count($asset['log_types']); ?></td>
                                        <td><?php echo $asset['total_logs']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Network & Linux Assets -->
                <div class="card">
                    <div class="card-header">
                        <h3>Network & Linux Assets</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $otherAssets = array_filter($assets, function($asset) {
                            return strpos($asset['name'], 'windows') === false;
                        });
                        ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Log Types</th>
                                        <th>Log Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($otherAssets as $asset): ?>
                                    <tr>
                                        <td><?php echo ucfirst(str_replace('-', ' ', $asset['name'])); ?></td>
                                        <td><?php echo count($asset['log_types']); ?></td>
                                        <td><?php echo $asset['total_logs']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Asset Details Modal -->
    <div class="modal" id="asset-detail-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Asset Details</h3>
                <button class="btn" onclick="hideAssetModal()">Close</button>
            </div>
            <div class="modal-body">
                <div id="asset-detail-content"></div>
            </div>
        </div>
    </div>

    <script>
        function viewAssetLogs(assetName) {
            // Redirect to logs page with asset filter
            window.location.href = `logs.php?query=asset='${assetName}'`;
        }

        function viewAssetDetails(assetName, assetData) {
            let content = `
                <div style="margin-bottom: 1.5rem;">
                    <h4>${ucfirst(assetName.replace(/-/g, ' '))}</h4>
                    <p><strong>Total Log Sources:</strong> ${Object.keys(assetData.log_types).length}</p>
                    <p><strong>Total Log Entries:</strong> ${assetData.total_logs}</p>
                    <p><strong>Last Updated:</strong> ${assetData.last_updated || 'No data'}</p>
                </div>
                
                <h5>Log Sources</h5>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Log Type</th>
                                <th>Entry Count</th>
                                <th>Last Entry</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            Object.keys(assetData.log_types).forEach(logType => {
                const logData = assetData.log_types[logType];
                content += `
                    <tr>
                        <td><strong>${ucfirst(logType)}</strong></td>
                        <td>${logData.count}</td>
                        <td>${logData.last_entry || 'No data'}</td>
                        <td>
                            <button class="btn" onclick="viewLogType('${assetName}', '${logType}')">View Logs</button>
                        </td>
                    </tr>
                `;
            });
            
            content += `
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('asset-detail-content').innerHTML = content;
            document.getElementById('asset-detail-modal').classList.add('active');
        }

        function viewLogType(assetName, logType) {
            // Redirect to logs page with specific asset and log type filter
            window.location.href = `logs.php?query=asset='${assetName}' AND log_type='${logType}'`;
        }

        function hideAssetModal() {
            document.getElementById('asset-detail-modal').classList.remove('active');
        }

        function scanForNewAssets() {
            // Simulate scanning for new assets
            siemApp.showNotification('Scanning for new assets...', 'info');
            setTimeout(() => {
                location.reload();
            }, 2000);
        }

        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        // Close modal on outside click
        document.getElementById('asset-detail-modal').addEventListener('click', function(e) {
            if (e.target === this) hideAssetModal();
        });
    </script>
</body>
</html>