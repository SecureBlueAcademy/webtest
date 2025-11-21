<?php
require_once 'config.php';
checkAuth();

$all_logs = readAllLogs();

// Handle filters
$filtered_logs = $all_logs;
$filters = [];
$exclude_filters = [];

// Time filter
$time_filter = getParam('time_filter', INPUT_GET, '24h');
$query = trim(getParam('query'));
$asset_filter = getParam('asset');
$log_type_filter = getParam('log_type');
$items_per_page = (int)(getParam('per_page') ?: 50);
$page = (int)(getParam('page') ?: 1);

// Custom date range
$custom_start = getParam('custom_start');
$custom_end = getParam('custom_end');

// Handle exclude filters
if (isset($_GET['exclude'])) {
    $exclude_filters = sanitizeArray($_GET['exclude']);
    if (!is_array($exclude_filters)) {
        $exclude_filters = [$exclude_filters];
    }
}

// Apply query filter
if (!empty($query)) {
    $filtered_logs = parseQuerySyntax($query, $filtered_logs);
}

// Apply asset filter
if (!empty($asset_filter)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($asset_filter) {
        return ($log['asset'] ?? '') === $asset_filter;
    });
}

// Apply log type filter
if (!empty($log_type_filter)) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($log_type_filter) {
        return ($log['log_type'] ?? '') === $log_type_filter;
    });
}

// Apply exclude filters
foreach ($exclude_filters as $exclude_filter) {
    list($field, $value) = explode(':', $exclude_filter);
    $filtered_logs = array_filter($filtered_logs, function($log) use ($field, $value) {
        return ($log[$field] ?? '') !== $value;
    });
}

// Apply time filter
if ($time_filter !== 'all') {
    $now = time();
    switch ($time_filter) {
        case '1h':
            $cutoff = $now - 3600;
            break;
        case '24h':
            $cutoff = $now - 86400;
            break;
        case '7d':
            $cutoff = $now - 604800;
            break;
        case '30d':
            $cutoff = $now - 2592000;
            break;
        case 'custom':
            if (!empty($custom_start)) {
                $cutoff = strtotime($custom_start);
                $end_time = !empty($custom_end) ? strtotime($custom_end) : $now;
            } else {
                $cutoff = $now - 86400;
            }
            break;
        default:
            $cutoff = $now - 86400;
    }
    
    if ($time_filter === 'custom' && !empty($custom_start)) {
        $filtered_logs = array_filter($filtered_logs, function($log) use ($cutoff, $end_time) {
            $log_time = strtotime($log['timestamp'] ?? '');
            return $log_time >= $cutoff && $log_time <= $end_time;
        });
    } else if ($time_filter !== 'custom') {
        $filtered_logs = array_filter($filtered_logs, function($log) use ($cutoff) {
            $log_time = strtotime($log['timestamp'] ?? '');
            return $log_time >= $cutoff;
        });
    }
}

// Convert back to indexed array
$filtered_logs = array_values($filtered_logs);

// Generate timeline data
$timeline_data = [];
$hourly_counts = [];
$now = time();

// Initialize hourly buckets for last 24 hours
for ($i = 23; $i >= 0; $i--) {
    $hour_start = $now - ($i * 3600);
    $hour_label = date('H:00', $hour_start);
    $hourly_counts[$hour_label] = 0;
}

// Count events per hour
foreach ($filtered_logs as $log) {
    $log_time = strtotime($log['timestamp'] ?? '');
    $hour_label = date('H:00', $log_time);
    if (isset($hourly_counts[$hour_label])) {
        $hourly_counts[$hour_label]++;
    }
}

$timeline_data = $hourly_counts;

// Get column preferences
$visible_columns = getParam('columns') ?: 'timestamp,asset,log_type,event_id,event_category,severity,event_summary,raw_log';
$visible_columns = explode(',', $visible_columns);

// Pagination
$total_logs = count($filtered_logs);
$items_per_page = min($items_per_page, 100);
$total_pages = ceil($total_logs / $items_per_page);
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $items_per_page;
$paginated_logs = array_slice($filtered_logs, $offset, $items_per_page);

// Get unique values for filters based on filtered data
$assets = array_unique(array_column($filtered_logs, 'asset'));
$log_types = array_unique(array_column($filtered_logs, 'log_type'));

// Get top values for each field for the sidebar with counts
$asset_counts = array_count_values(array_column($filtered_logs, 'asset'));
arsort($asset_counts);
$top_assets = array_slice($asset_counts, 0, 10, true);

$log_type_counts = array_count_values(array_column($filtered_logs, 'log_type'));
arsort($log_type_counts);
$top_log_types = array_slice($log_type_counts, 0, 10, true);

// Function to parse query syntax
function parseQuerySyntax($query, $logs) {
    $conditions = [];
    
    // Split by AND/OR but preserve quoted strings
    preg_match_all('/([a-zA-Z_]+)\s*([=!<>~]+)\s*[\'"]?([^\'"\s]+)[\'"]?/', $query, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $field = strtolower(trim($match[1]));
        $operator = trim($match[2]);
        $value = trim($match[3]);
        
        $conditions[] = ['field' => $field, 'operator' => $operator, 'value' => $value];
    }
    
    // Apply conditions
    if (!empty($conditions)) {
        $logs = array_filter($logs, function($log) use ($conditions) {
            foreach ($conditions as $condition) {
                $log_value = $log[$condition['field']] ?? '';
                $match = false;
                
                switch ($condition['operator']) {
                    case '=':
                        $match = (strcasecmp($log_value, $condition['value']) === 0);
                        break;
                    case '!=':
                        $match = (strcasecmp($log_value, $condition['value']) !== 0);
                        break;
                    case '~': // Contains
                        $match = (stripos($log_value, $condition['value']) !== false);
                        break;
                }
                
                if (!$match) {
                    return false;
                }
            }
            return true;
        });
    }
    
    return $logs;
}

// Dynamic column definitions based on available fields
$column_definitions = [
    'timestamp' => ['Time', 'timestamp'],
    'asset' => ['Asset', 'asset'],
    'log_type' => ['Log Type', 'log_type'],
    'event_id' => ['Event ID', 'event_id'],
    'event_category' => ['Category', 'event_category'],
    'severity' => ['Severity', 'severity'],
    'event_summary' => ['Summary', 'event_summary'],
    'raw_log' => ['Raw Log', 'raw_log']
];

// Add dynamic fields from the first log entry if available
if (!empty($filtered_logs)) {
    $sample_log = $filtered_logs[0];
    foreach ($sample_log as $key => $value) {
        if (!in_array($key, ['timestamp', 'asset', 'log_type', 'raw_log', '_source_file']) && !isset($column_definitions[$key])) {
            $column_definitions[$key] = [ucfirst(str_replace('_', ' ', $key)), $key];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYBRIXEN SIEM - Log Analysis</title>
    <?php $BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; ?>
    <link rel="stylesheet" href="<?= $BASE ?>styles.css?v=5">
    <script src="js/app.js"></script>
    <script src="js/charts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app">
        <!-- Enhanced Sidebar -->
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
                <button class="nav-item active" onclick="location.href='logs.php'">
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
                <h2>Log Analysis</h2>
                <div class="toolbar">
                    <button class="btn" onclick="location.reload()">Refresh</button>
                    <button class="btn primary" onclick="clearFilters()">Clear Filters</button>
                    <button class="btn" onclick="exportLogs()">Export Results</button>
                    <button class="btn" onclick="showColumnsModal()">Manage Columns</button>
                </div>
            </header>

            <!-- Field Rail & Main Column -->
            <div style="display:grid; grid-template-columns: 240px 1fr; min-height:0;">
                <!-- Enhanced Field Rail -->
                <aside class="field-rail">
                    <h4>Quick Filters</h4>
                    
                    <!-- Assets -->
                    <div class="field-group">
                        <div class="field-header" onclick="toggleFieldGroup('assets')">
                            <span>Assets (<?php echo count($top_assets); ?>)</span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        <div class="field-list" id="assets-list" style="display: none;">
                            <?php foreach($top_assets as $asset => $count): ?>
                            <div class="field-item">
                                <span class="field-item-label"><?php echo $asset; ?> (<?php echo $count; ?>)</span>
                                <div class="field-actions">
                                    <button class="add-filter-btn" title="Add to filter" onclick="addToFilter('asset', '<?php echo $asset; ?>')">+</button>
                                    <button class="exclude-filter-btn" title="Exclude from filter" onclick="addExcludeFilter('asset', '<?php echo $asset; ?>')">-</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Log Types -->
                    <div class="field-group">
                        <div class="field-header" onclick="toggleFieldGroup('log-types')">
                            <span>Log Types (<?php echo count($top_log_types); ?>)</span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        <div class="field-list" id="log-types-list" style="display: none;">
                            <?php foreach($top_log_types as $log_type => $count): ?>
                            <div class="field-item">
                                <span class="field-item-label"><?php echo $log_type; ?> (<?php echo $count; ?>)</span>
                                <div class="field-actions">
                                    <button class="add-filter-btn" title="Add to filter" onclick="addToFilter('log_type', '<?php echo $log_type; ?>')">+</button>
                                    <button class="exclude-filter-btn" title="Exclude from filter" onclick="addExcludeFilter('log_type', '<?php echo $log_type; ?>')">-</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>

                <!-- Column -->
                <section style="display:flex; flex-direction:column; min-width:0;">
                    <!-- Query Bar with Custom Date Range -->
                    <form method="GET" id="filter-form" class="query-bar">
                        <div class="query-input-container">
                            <input type="text" name="query" class="query-syntax-input" placeholder="Enter query (e.g., asset='windows-workstation' AND log_type='security')" value="<?php echo escapeHtml($query); ?>">
                            <div class="query-help">
                                <button type="button" class="help-icon" title="Query Help">?</button>
                                <div class="help-tooltip">
                                    <h4>Query Syntax Help</h4>
                                    <p>Supported fields: asset, log_type, and any field from log entries</p>
                                    <p>Operators: =, !=, ~ (contains)</p>
                                    <div class="help-example">
                                        <strong>Examples:</strong><br>
                                        asset='windows-workstation' AND log_type='security'<br>
                                        user~'admin' AND result='Success'<br>
                                        client_ip='192.168.1.45' OR src_ip='192.168.1.45'
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <select name="time_filter" class="btn" id="time-filter-select" onchange="toggleCustomDateRange()">
                            <option value="1h" <?php echo $time_filter === '1h' ? 'selected' : ''; ?>>Last 1 hour</option>
                            <option value="24h" <?php echo $time_filter === '24h' ? 'selected' : ''; ?>>Last 24 hours</option>
                            <option value="7d" <?php echo $time_filter === '7d' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30d" <?php echo $time_filter === '30d' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="custom" <?php echo $time_filter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>All time</option>
                        </select>

                        <button type="submit" class="btn primary">Search</button>
                    </form>

                    <!-- Active Filters -->
                    <div class="filter-pills" id="active-filters">
                        <?php if(!empty($query)): ?>
                        <div class="pill">
                            Query: "<?php echo escapeHtml($query); ?>"
                            <button type="button" onclick="removeFilter('query')">×</button>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($asset_filter)): ?>
                        <div class="pill">
                            Asset: <?php echo $asset_filter; ?>
                            <button type="button" onclick="removeFilter('asset')">×</button>
                        </div>
                        <?php endif; ?>
                        <?php if(!empty($log_type_filter)): ?>
                        <div class="pill">
                            Log Type: <?php echo $log_type_filter; ?>
                            <button type="button" onclick="removeFilter('log_type')">×</button>
                        </div>
                        <?php endif; ?>
                        <?php foreach($exclude_filters as $exclude_filter): ?>
                        <?php list($field, $value) = explode(':', $exclude_filter); ?>
                        <div class="pill exclude">
                            Not <?php echo $field; ?>: <?php echo $value; ?>
                            <button type="button" onclick="removeExcludeFilter('<?php echo $exclude_filter; ?>')">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Timeline Chart -->
                    <div class="timeline-card">
                        <div class="card-header">
                            <h3>Event Timeline (Last 24 Hours)</h3>
                        </div>
                        <div class="timeline-container">
                            <canvas id="timeline-chart" height="80"></canvas>
                        </div>
                    </div>

                    <!-- Logs Table -->
                    <div class="card" style="margin:0; border:none; border-radius:0; flex:1; display:flex; flex-direction:column; min-height:0;">
                        <div class="card-header">
                            <h3>Log Events</h3>
                            <div style="display:flex; gap:.4rem; align-items:center;">
                                <span><?php echo $total_logs; ?> results</span>
                                <select name="per_page" class="btn" onchange="updatePerPage(this.value)">
                                    <option value="20" <?php echo $items_per_page == 20 ? 'selected' : ''; ?>>20 per page</option>
                                    <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50 per page</option>
                                    <option value="100" <?php echo $items_per_page == 100 ? 'selected' : ''; ?>>100 per page</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-container">
                            <table id="logs-table">
                                <thead>
                                    <tr>
                                        <?php 
                                        foreach ($visible_columns as $column): 
                                            if (isset($column_definitions[$column])):
                                        ?>
                                        <th class="table-column" data-column="<?php echo $column; ?>">
                                            <div class="column-header">
                                                <span><?php echo $column_definitions[$column][0]; ?></span>
                                                <div class="column-actions">
                                                    <button class="remove-column-btn" onclick="removeColumn('<?php echo $column; ?>')" title="Remove column">
                                                        <span class="icon-table-remove">📊−</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="column-resize-handle"></div>
                                        </th>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paginated_logs as $log): ?>
                                    <tr class="expandable-log" onclick="toggleLogDetails(this, <?php echo htmlspecialchars(json_encode($log)); ?>)">
                                        <?php foreach ($visible_columns as $column): 
                                            if (isset($column_definitions[$column])):
                                                $field = $column_definitions[$column][1];
                                                $value = $log[$field] ?? '';
                                        ?>
                                        <td>
                                            <span title="<?php echo escapeHtml($value); ?>">
                                                <?php 
                                                if ($column === 'raw_log') {
                                                    echo escapeHtml(strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value);
                                                } else {
                                                    echo escapeHtml(strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value);
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                        <td>
                                            <button class="btn" onclick="event.stopPropagation(); toggleLogDetails(this.parentElement.parentElement, <?php echo htmlspecialchars(json_encode($log)); ?>)">Details</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <div class="page-info">
                                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_logs); ?> of <?php echo $total_logs; ?> results
                            </div>
                            <div class="page-controls">
                                <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn">Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="btn <?php echo $i == $page ? 'primary' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Available Columns Panel -->
    <div class="modal" id="columns-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Manage Columns</h3>
                <button class="btn" onclick="hideColumnsModal()">Close</button>
            </div>
            <div class="modal-body">
                <div class="field-list">
                    <?php foreach ($column_definitions as $column => $def): ?>
                    <div class="field-item">
                        <span class="field-item-label"><?php echo $def[0]; ?></span>
                        <div class="field-actions">
                            <button class="add-column-btn" onclick="addColumn('<?php echo $column; ?>')" title="Add column">
                                <span class="icon-table-add">📊+</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize timeline chart
        document.addEventListener('DOMContentLoaded', function() {
            initTimelineChart();
            initColumnResize();
            initQueryInput();
        });

        function initTimelineChart() {
            const ctx = document.getElementById('timeline-chart').getContext('2d');
            const timelineData = <?php echo json_encode(array_values($timeline_data)); ?>;
            const labels = <?php echo json_encode(array_keys($timeline_data)); ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Events per Hour',
                        data: timelineData,
                        backgroundColor: '#66d9ef',
                        borderColor: '#1a3a6b',
                        borderWidth: 1,
                        borderRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#9db0d7'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#9db0d7',
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(18, 26, 54, 0.95)',
                            titleColor: '#e8eeff',
                            bodyColor: '#9db0d7'
                        }
                    },
                    onClick: (e) => {
                        const points = e.chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, true);
                        if (points.length) {
                            const firstPoint = points[0];
                            const label = e.chart.data.labels[firstPoint.index];
                            // You could implement time-based filtering here
                            console.log('Clicked on hour:', label);
                        }
                    }
                }
            });
        }

        function initQueryInput() {
            const queryInput = document.querySelector('.query-syntax-input');
            if (queryInput) {
                queryInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('filter-form').submit();
                    }
                });
            }
        }

        // Column management
        let currentColumns = <?php echo json_encode($visible_columns); ?>;
        
        function addColumn(column) {
            if (!currentColumns.includes(column)) {
                currentColumns.push(column);
                updateColumns();
            }
        }
        
        function removeColumn(column) {
            currentColumns = currentColumns.filter(col => col !== column);
            updateColumns();
        }
        
        function updateColumns() {
            const params = new URLSearchParams(window.location.search);
            params.set('columns', currentColumns.join(','));
            window.location.href = '?' + params.toString();
        }
        
        function updatePerPage(value) {
            const params = new URLSearchParams(window.location.search);
            params.set('per_page', value);
            params.set('page', '1'); // Reset to first page
            window.location.href = '?' + params.toString();
        }
        
        // Field group toggling
        function toggleFieldGroup(groupId) {
            const list = document.getElementById(groupId + '-list');
            const icon = list.previousElementSibling.querySelector('.toggle-icon');
            
            if (list.style.display === 'none') {
                list.style.display = 'block';
                icon.textContent = '▲';
            } else {
                list.style.display = 'none';
                icon.textContent = '▼';
            }
        }
        
        // Filter management
        function addToFilter(field, value) {
            const form = document.getElementById('filter-form');
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
                input.value = value;
            } else {
                // Create hidden input if it doesn't exist
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = field;
                hiddenInput.value = value;
                form.appendChild(hiddenInput);
            }
            form.submit();
        }

        function addExcludeFilter(field, value) {
            const form = document.getElementById('filter-form');
            const excludeInput = document.createElement('input');
            excludeInput.type = 'hidden';
            excludeInput.name = 'exclude[]';
            excludeInput.value = field + ':' + value;
            form.appendChild(excludeInput);
            form.submit();
        }

        function removeFilter(field) {
            const form = document.getElementById('filter-form');
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
                input.remove();
            }
            form.submit();
        }

        function removeExcludeFilter(excludeValue) {
            const form = document.getElementById('filter-form');
            const excludeInputs = form.querySelectorAll('input[name="exclude[]"]');
            
            excludeInputs.forEach(input => {
                if (input.value === excludeValue) {
                    input.remove();
                }
            });
            
            form.submit();
        }
        
        function clearFilters() {
            window.location.href = 'logs.php';
        }
        
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            window.open('export_logs.php?' + params.toString(), '_blank');
        }
        
        // Log details expansion
        function toggleLogDetails(row, log) {
            const isExpanded = row.classList.contains('expanded-log');
            
            if (isExpanded) {
                // Remove details row if it exists
                const detailsRow = row.nextElementSibling;
                if (detailsRow && detailsRow.classList.contains('log-details-row')) {
                    detailsRow.remove();
                }
                row.classList.remove('expanded-log');
            } else {
                // Remove any existing expanded rows
                document.querySelectorAll('.expanded-log').forEach(r => {
                    r.classList.remove('expanded-log');
                    const next = r.nextElementSibling;
                    if (next && next.classList.contains('log-details-row')) {
                        next.remove();
                    }
                });
                
                // Add details row
                const detailsRow = document.createElement('tr');
                detailsRow.className = 'log-details-row';
                detailsRow.innerHTML = `
                    <td colspan="${currentColumns.length + 1}">
                        <div style="padding: 1rem; background: #0f1834;">
                            <h4>Log Details</h4>
                            <div class="log-detail-fields">
                                ${generateLogDetails(log)}
                            </div>
                        </div>
                    </td>
                `;
                row.parentNode.insertBefore(detailsRow, row.nextSibling);
                row.classList.add('expanded-log');
            }
        }
        
        function generateLogDetails(log) {
            // Show raw log at the top
            let details = `
                <div class="log-detail-field">
                    <span class="log-detail-field-name">Raw Log</span>
                    <span class="log-detail-field-value" style="font-family: monospace; white-space: pre-wrap; background: #0b122b; padding: 0.5rem; border-radius: 0.25rem; display: block;">${escapeHtml(log.raw_log || 'No raw log data')}</span>
                    <div class="log-detail-field-actions">
                        <button class="add-filter-btn" onclick="event.stopPropagation(); addToFilter('asset', '${escapeHtml(log.asset)}')" title="Filter by asset">
                            <span class="icon-filter-add">+</span>
                        </button>
                        <button class="add-column-btn" onclick="event.stopPropagation(); addColumn('raw_log')" title="Add raw log column">
                            <span class="icon-table-add">📊+</span>
                        </button>
                    </div>
                </div>
            `;
            
            // Add all other fields dynamically
            Object.keys(log).forEach(key => {
                if (!['raw_log', '_source_file'].includes(key) && log[key]) {
                    details += `
                        <div class="log-detail-field">
                            <span class="log-detail-field-name">${ucfirst(key.replace(/_/g, ' '))}</span>
                            <span class="log-detail-field-value">${escapeHtml(log[key])}</span>
                            <div class="log-detail-field-actions">
                                <button class="add-filter-btn" onclick="event.stopPropagation(); addToFilter('${key}', '${escapeHtml(log[key])}')">
                                    <span class="icon-filter-add">+</span>
                                </button>
                                <button class="add-column-btn" onclick="event.stopPropagation(); addColumn('${key}')">
                                    <span class="icon-table-add">📊+</span>
                                </button>
                            </div>
                        </div>
                    `;
                }
            });
            
            return details;
        }
        
        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        function escapeHtml(unsafe) {
            return String(unsafe)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        function showColumnsModal() {
            document.getElementById('columns-modal').classList.add('active');
        }
        
        function hideColumnsModal() {
            document.getElementById('columns-modal').classList.remove('active');
        }
        
        // Column resizing
        function initColumnResize() {
            const resizeHandles = document.querySelectorAll('.column-resize-handle');
            
            resizeHandles.forEach(handle => {
                handle.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    const column = this.parentElement;
                    const startX = e.pageX;
                    const startWidth = column.offsetWidth;
                    
                    function onMouseMove(e) {
                        const newWidth = startWidth + (e.pageX - startX);
                        if (newWidth > 50) { // Minimum width
                            column.style.minWidth = newWidth + 'px';
                            column.style.width = newWidth + 'px';
                        }
                    }
                    
                    function onMouseUp() {
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                    }
                    
                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
            });
        }

        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>