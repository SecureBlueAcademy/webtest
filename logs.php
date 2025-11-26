<?php
require_once 'config.php';
checkAuth();

$all_logs = readAllLogs();

// Handle filters
$filtered_logs = $all_logs;
$filters = [];
$exclude_filters = [];

// Time filter - Set default to 'all'
$time_filter = $_GET['time_filter'] ?? 'all';
$query = $_GET['query'] ?? '';
$asset_filter = $_GET['asset'] ?? '';
$log_type_filter = $_GET['log_type'] ?? '';
$items_per_page = $_GET['per_page'] ?? 50;
$page = $_GET['page'] ?? 1;

// Custom date range
$custom_start = $_GET['custom_start'] ?? '';
$custom_end = $_GET['custom_end'] ?? '';

// Handle filters from URL parameters
foreach ($_GET as $key => $value) {
    if (!in_array($key, ['time_filter', 'query', 'per_page', 'page', 'custom_start', 'custom_end', 'columns', 'exclude']) && $value !== '') {
        $filters[$key] = $value;
    }
}

if (!empty($asset_filter)) {
    $filters['asset'] = $asset_filter;
}
if (!empty($log_type_filter)) {
    $filters['log_type'] = $log_type_filter;
}

// Handle exclude filters
if (isset($_GET['exclude'])) {
    $exclude_filters = $_GET['exclude'];
    if (!is_array($exclude_filters)) {
        $exclude_filters = [$exclude_filters];
    }
}

// ---------------------------------------------------------
// QUERY PARSER ENGINE (Recursive Descent for AND/OR/NOT)
// ---------------------------------------------------------

function parseQuerySyntax($query, $logs) {
    $tokens = tokenizeQuery($query);
    if (empty($tokens)) return $logs;
    
    $index = 0;
    $expression = parseExpression($tokens, $index);
    
    return array_filter($logs, function($log) use ($expression) {
        return evaluateCondition($log, $expression);
    });
}

function tokenizeQuery($query) {
    $tokens = [];
    $length = strlen($query);
    $i = 0;
    
    while ($i < $length) {
        $char = $query[$i];
        
        if (ctype_space($char)) {
            $i++;
            continue;
        }
        
        if ($char === '(' || $char === ')') {
            $tokens[] = $char;
            $i++;
            continue;
        }
        
        if ($char === '"' || $char === "'") {
            $quote = $char;
            $str = '';
            $i++;
            while ($i < $length && $query[$i] !== $quote) {
                if ($query[$i] === '\\' && isset($query[$i+1])) {
                    $str .= $query[$i+1];
                    $i += 2;
                } else {
                    $str .= $query[$i];
                    $i++;
                }
            }
            $tokens[] = $str; 
            $i++; 
            continue;
        }
        
        if ($i + 1 < $length) {
            $twoChars = substr($query, $i, 2);
            if (in_array($twoChars, ['!=', '!~', '>=', '<='])) {
                $tokens[] = $twoChars;
                $i += 2;
                continue;
            }
        }
        
        if (strpos('=~><', $char) !== false) {
            $tokens[] = $char;
            $i++;
            continue;
        }
        
        $word = '';
        while ($i < $length && !ctype_space($query[$i]) && strpos('()=~><!"\'', $query[$i]) === false) {
            $word .= $query[$i];
            $i++;
        }
        if ($word !== '') {
            $tokens[] = $word;
        }
    }
    return $tokens;
}

function parseExpression(&$tokens, &$index) {
    $left = parseTerm($tokens, $index);
    while (isset($tokens[$index]) && strtoupper($tokens[$index]) === 'OR') {
        $index++;
        $right = parseTerm($tokens, $index);
        $left = ['operator' => 'OR', 'left' => $left, 'right' => $right];
    }
    return $left;
}

function parseTerm(&$tokens, &$index) {
    $left = parseFactor($tokens, $index);
    while (isset($tokens[$index]) && strtoupper($tokens[$index]) === 'AND') {
        $index++;
        $right = parseFactor($tokens, $index);
        $left = ['operator' => 'AND', 'left' => $left, 'right' => $right];
    }
    return $left;
}

function parseFactor(&$tokens, &$index) {
    if (!isset($tokens[$index])) return null;

    $token = $tokens[$index];
    
    if (strtoupper($token) === 'NOT') {
        $index++;
        $operand = parseFactor($tokens, $index);
        return ['operator' => 'NOT', 'operand' => $operand];
    }
    
    if ($token === '(') {
        $index++;
        $expr = parseExpression($tokens, $index);
        if (isset($tokens[$index]) && $tokens[$index] === ')') {
            $index++;
        }
        return $expr;
    }
    
    if (isset($tokens[$index+1]) && isset($tokens[$index+2])) {
        $op = $tokens[$index+1];
        if (in_array($op, ['=', '!=', '~', '!~', '>', '<', '>=', '<='])) {
            $field = $tokens[$index];
            $operator = $tokens[$index+1];
            $value = $tokens[$index+2];
            $index += 3;
            return ['type' => 'condition', 'field' => $field, 'operator' => $operator, 'value' => $value];
        }
    }
    
    $value = $tokens[$index];
    $index++;
    return ['type' => 'condition', 'field' => 'raw_log', 'operator' => '~', 'value' => $value];
}

function evaluateCondition($log, $condition) {
    if (!$condition) return true;

    if (isset($condition['operator'])) {
        if ($condition['operator'] === 'AND') {
            return evaluateCondition($log, $condition['left']) && evaluateCondition($log, $condition['right']);
        } elseif ($condition['operator'] === 'OR') {
            return evaluateCondition($log, $condition['left']) || evaluateCondition($log, $condition['right']);
        } elseif ($condition['operator'] === 'NOT') {
            return !evaluateCondition($log, $condition['operand']);
        }
    }
    
    if (isset($condition['type']) && $condition['type'] === 'condition') {
        $field = $condition['field'];
        $log_val = isset($log[$field]) ? (string)$log[$field] : '';
        $search_val = (string)$condition['value'];
        
        switch ($condition['operator']) {
            case '=': return strcasecmp($log_val, $search_val) === 0;
            case '!=': return strcasecmp($log_val, $search_val) !== 0;
            case '~': return stripos($log_val, $search_val) !== false;
            case '!~': return stripos($log_val, $search_val) === false;
            case '>': return $log_val > $search_val;
            case '<': return $log_val < $search_val;
            case '>=': return $log_val >= $search_val;
            case '<=': return $log_val <= $search_val;
        }
    }
    return false;
}

// ---------------------------------------------------------
// APPLY FILTERS - FIXED ASSET FILTER ISSUE
// ---------------------------------------------------------

// 1. Apply query filter
if (!empty($query)) {
    try {
        $filtered_logs = parseQuerySyntax($query, $filtered_logs);
    } catch (Exception $e) {
        $filtered_logs = []; 
    }
}

// 2. Apply regular filters (AND logic) - FIXED: Proper field checking
foreach ($filters as $field => $value) {
    $filtered_logs = array_filter($filtered_logs, function($log) use ($field, $value) {
        // Check if field exists and has value
        $log_value = isset($log[$field]) ? (string)$log[$field] : '';
        return $log_value !== '' && $log_value === (string)$value;
    });
}

// 3. Apply exclude filters
foreach ($exclude_filters as $exclude_filter) {
    $parts = explode(':', $exclude_filter, 2); 
    if(count($parts) === 2) {
        $field = $parts[0];
        $value = $parts[1];
        $filtered_logs = array_filter($filtered_logs, function($log) use ($field, $value) {
            $log_value = isset($log[$field]) ? (string)$log[$field] : '';
            return $log_value === '' || $log_value !== (string)$value;
        });
    }
}

// 4. Apply time filter
if ($time_filter !== 'all') {
    $now = time();
    switch ($time_filter) {
        case '1h': $cutoff = $now - 3600; break;
        case '24h': $cutoff = $now - 86400; break;
        case '7d': $cutoff = $now - 604800; break;
        case '30d': $cutoff = $now - 2592000; break;
        case 'custom':
            if (!empty($custom_start)) {
                $cutoff = strtotime($custom_start);
                $end_time = !empty($custom_end) ? strtotime($custom_end) : $now;
            } else {
                $cutoff = $now - 86400;
            }
            break;
        default: $cutoff = $now - 86400;
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

for ($i = 23; $i >= 0; $i--) {
    $hour_start = $now - ($i * 3600);
    $hour_label = date('H:00', $hour_start);
    $hourly_counts[$hour_label] = 0;
}

foreach ($filtered_logs as $log) {
    $log_time = strtotime($log['timestamp'] ?? '');
    if ($log_time) {
        $hour_label = date('H:00', $log_time);
        if (isset($hourly_counts[$hour_label])) {
            $hourly_counts[$hour_label]++;
        }
    }
}
$timeline_data = $hourly_counts;

$available_assets = array_values(array_unique(array_column($all_logs, 'asset')));
$available_log_types = array_values(array_unique(array_column($all_logs, 'log_type')));

// ---------------------------------------------------------
// COLUMN MANAGEMENT LOGIC
// ---------------------------------------------------------

$default_columns = ['timestamp', 'raw_log'];
$visible_columns = $_GET['columns'] ?? implode(',', $default_columns);
$visible_columns = explode(',', $visible_columns);

// Ensure default columns are always included
foreach ($default_columns as $default_col) {
    if (!in_array($default_col, $visible_columns)) {
        $visible_columns[] = $default_col;
    }
}

// Pagination
$total_logs = count($filtered_logs);
$items_per_page = min($items_per_page, 100);
$total_pages = ceil($total_logs / $items_per_page);
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $items_per_page;
$paginated_logs = array_slice($filtered_logs, $offset, $items_per_page);

// Sidebar Statistics - FIXED: Proper field aggregation
$all_fields = [];
foreach ($filtered_logs as $log) {
    foreach ($log as $field => $value) {
        if (!in_array($field, ['_source_file', 'raw_log']) && $value !== '') {
            if (!isset($all_fields[$field])) {
                $all_fields[$field] = [];
            }
            $valStr = (string)$value;
            $all_fields[$field][$valStr] = ($all_fields[$field][$valStr] ?? 0) + 1;
        }
    }
}

$field_values_with_counts = [];
foreach ($all_fields as $field => $values) {
    arsort($values);
    $field_values_with_counts[$field] = array_slice($values, 0, 10, true);
}

// Dynamic column definitions (Scanning logs for all possible fields)
$column_definitions = [
    'timestamp' => ['Time', 'timestamp'],
    'event_id' => ['Event ID', 'event_id'],
    'event_category' => ['Category', 'event_category'],
    'raw_log' => ['Raw Log', 'raw_log']
];

// Scan logs using their schema definitions to build consistent headers
$scan_limit = min(50, count($filtered_logs));
for ($i = 0; $i < $scan_limit; $i++) {
    $schemaFields = $filtered_logs[$i]['_schema_fields'] ?? array_keys($filtered_logs[$i]);
    foreach ($schemaFields as $key) {
        if (!in_array($key, ['timestamp', 'raw_log', '_source_file', '_schema_fields']) && !isset($column_definitions[$key])) {
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
    <link rel="stylesheet" href="<?= $BASE ?>styles.css?v=8">
    <script src="js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app">
        <nav class="sidebar">
            <div class="sidebar-header">
                <button class="sidebar-toggle">☰</button>
                <h1>CYBRIXEN SIEM</h1>
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
                <button class="nav-item active" onclick="location.href='logs.php'">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Log Analysis</span>
                </button>
                <button class="nav-item">
                    <span class="nav-icon">🚨</span>
                    <span class="nav-text">Alerts</span>
                </button>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <button class="nav-item" onclick="location.href='admin.php'">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Admin Panel</span>
                </button>
                <?php endif; ?>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Investigation</div>
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
                        <span class="nav-text">Settings</span>
                    </button>
                    <button class="nav-item">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">User Management</span>
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
                </div>

                <button class="nav-item" onclick="location.href='logout.php'">
                    <span class="nav-icon">🚪</span>
                    <span class="nav-text">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</span>
                </button>
            </div>
        </nav>

        <main class="main-content">
            <header class="header">
                <h2>Log Analysis</h2>
                <div class="toolbar">
                    <button class="btn" onclick="location.reload()">Refresh</button>
                    <button class="btn primary" onclick="clearFilters()">Clear Filters</button>
                </div>
            </header>

            <div style="display:grid; grid-template-columns: 240px 1fr; min-height:0;">
                <aside class="field-rail">
                    <h4>Quick Filters</h4>
                    <div class="field-rail-content">
                        <?php foreach($field_values_with_counts as $field => $values): ?>
                        <div class="field-group">
                            <div class="field-header" onclick="toggleFieldGroup('<?php echo $field; ?>')">
                                <span><?php echo ucfirst(str_replace('_', ' ', $field)); ?> (<?php echo count($values); ?>)</span>
                                <span class="toggle-icon">▼</span>
                            </div>
                            <div class="field-list" id="<?php echo $field; ?>-list" style="display: none;">
                                <?php foreach($values as $value => $count): ?>
                                <div class="field-item">
                                    <span class="field-item-label"><?php echo htmlspecialchars($value); ?> (<?php echo $count; ?>)</span>
                                    <div class="field-actions">
                                        <button class="add-filter-btn" title="Add to filter" 
                                            onclick='addToFilter(<?php echo json_encode($field); ?>, <?php echo json_encode($value); ?>)'>+</button>
                                        <button class="exclude-filter-btn" title="Exclude from filter" 
                                            onclick='addExcludeFilter(<?php echo json_encode($field); ?>, <?php echo json_encode($value); ?>)'>-</button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <section style="display:flex; flex-direction:column; min-width:0;">
                    <form method="GET" id="filter-form" class="query-bar">
                        <input type="hidden" name="columns" id="columns-input" value="<?php echo implode(',', $visible_columns); ?>">
                        
                        <div class="query-input-container">
                            <input type="text" name="query" class="query-syntax-input" 
                                placeholder="Enter query (e.g. status='failed' AND (user='admin' OR ip='10.0.0.1'))" 
                                value="<?php echo htmlspecialchars($query); ?>">
                            <div class="query-help">
                                <button type="button" class="help-icon" title="Query Help">?</button>
                                <div class="help-tooltip">
                                    <h4>Query Syntax Help</h4>
                                    <p>Fields: asset, log_type, user, event_id, etc.</p>
                                    <p>Operators: =, !=, ~, !~, >, <, NOT</p>
                                    <p>Logic: AND, OR, ( )</p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <select name="asset" class="btn" onchange="document.getElementById('filter-form').submit()">
                                <option value="">All Assets</option>
                                <?php foreach($available_assets as $asset): ?>
                                    <option value="<?php echo htmlspecialchars($asset); ?>" <?php echo $asset_filter === $asset ? 'selected' : ''; ?>><?php echo htmlspecialchars($asset); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="log_type" class="btn" onchange="document.getElementById('filter-form').submit()">
                                <option value="">All Log Types</option>
                                <?php foreach($available_log_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $log_type_filter === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="time_filter" class="btn" id="time-filter-select" onchange="toggleCustomDateRange()">
                                <option value="1h" <?php echo $time_filter === '1h' ? 'selected' : ''; ?>>Last 1 hour</option>
                                <option value="24h" <?php echo $time_filter === '24h' ? 'selected' : ''; ?>>Last 24 hours</option>
                                <option value="7d" <?php echo $time_filter === '7d' ? 'selected' : ''; ?>>Last 7 days</option>
                                <option value="30d" <?php echo $time_filter === '30d' ? 'selected' : ''; ?>>Last 30 days</option>
                                <option value="custom" <?php echo $time_filter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>All time</option>
                            </select>

                            <div id="custom-date-range" style="display: none; gap: 0.5rem; align-items: center;">
                                <input type="datetime-local" name="custom_start" class="btn" style="max-width: 190px;" value="<?php echo htmlspecialchars($custom_start); ?>">
                                <span style="color: #9db0d7;">to</span>
                                <input type="datetime-local" name="custom_end" class="btn" style="max-width: 190px;" value="<?php echo htmlspecialchars($custom_end); ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn primary">Search</button>
                    </form>

                    <div class="filter-pills" id="active-filters">
                        <?php if(!empty($query)): ?>
                        <div class="pill">
                            Query: "<?php echo htmlspecialchars($query); ?>"
                            <button type="button" onclick="removeFilter('query')">×</button>
                        </div>
                        <?php endif; ?>
                        <?php foreach($filters as $field => $value): ?>
                        <div class="pill">
                            <?php echo ucfirst(str_replace('_', ' ', $field)); ?>: <?php echo htmlspecialchars($value); ?>
                            <button type="button" onclick="removeFilter('<?php echo $field; ?>')">×</button>
                        </div>
                        <?php endforeach; ?>
                        <?php foreach($exclude_filters as $exclude_filter): ?>
                        <?php 
                            $parts = explode(':', $exclude_filter, 2);
                            $dispField = $parts[0] ?? '?';
                            $dispValue = $parts[1] ?? '?';
                        ?>
                        <div class="pill exclude">
                            NOT <?php echo htmlspecialchars($dispField); ?>: <?php echo htmlspecialchars($dispValue); ?>
                            <button type="button" onclick="removeExcludeFilter('<?php echo htmlspecialchars($exclude_filter); ?>')">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="timeline-card">
                        <div class="card-header">
                            <h3>Event Timeline</h3>
                        </div>
                        <div class="timeline-container">
                            <canvas id="timeline-chart" height="80"></canvas>
                        </div>
                    </div>

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
                                        <?php foreach ($visible_columns as $column): if (isset($column_definitions[$column])): ?>
                                        <th class="table-column" data-column="<?php echo $column; ?>">
                                            <div class="column-header">
                                                <span><?php echo $column_definitions[$column][0]; ?></span>
                                                <?php if (!in_array($column, ['timestamp', 'raw_log'])): ?>
                                                <button class="remove-column-btn" onclick="removeColumn('<?php echo $column; ?>')" title="Remove column" style="background:none; border:none; color: #ff6b6b; cursor:pointer;">
                                                    -
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="column-resize-handle"></div>
                                        </th>
                                        <?php endif; endforeach; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paginated_logs as $log): ?>
                                    <tr class="expandable-log" onclick='toggleLogDetails(this, <?php echo json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        <?php foreach ($visible_columns as $column): 
                                            if (isset($column_definitions[$column])):
                                                $field = $column_definitions[$column][1];
                                                $value = $log[$field] ?? '';
                                        ?>
                                        <td>
                                            <span title="<?php echo htmlspecialchars($value); ?>">
                                                <?php 
                                                echo htmlspecialchars(strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value);
                                                ?>
                                            </span>
                                        </td>
                                        <?php endif; endforeach; ?>
                                        <td>
                                            <button class="btn" onclick='event.stopPropagation(); toggleLogDetails(this.parentElement.parentElement, <?php echo json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Details</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

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
                            <?php if (!in_array($column, $visible_columns)): ?>
                            <button class="add-column-btn" onclick="addColumn('<?php echo $column; ?>')" title="Add column">
                                <span class="icon-table-add">+</span>
                            </button>
                            <?php else: ?>
                            <span style="color: var(--success); font-size: 0.8rem;">✓ Added</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initTimelineChart();
            initColumnResize();
            initQueryInput();
            toggleCustomDateRange(); // Initialize date range visibility
        });

        // ----------------------------------------------------
        // DATE RANGE TOGGLE
        // ----------------------------------------------------
        function toggleCustomDateRange() {
            const select = document.getElementById('time-filter-select');
            const rangeContainer = document.getElementById('custom-date-range');
            if (select.value === 'custom') {
                rangeContainer.style.display = 'flex';
            } else {
                rangeContainer.style.display = 'none';
            }
        }

        // ----------------------------------------------------
        // COLUMN MANAGEMENT JS
        // ----------------------------------------------------
        let currentColumns = <?php echo json_encode($visible_columns); ?>;
        
        function addColumn(column) {
            if (!currentColumns.includes(column)) {
                currentColumns.push(column);
                updateColumns();
            }
        }
        
        function removeColumn(column) {
            const defaultColumns = ['timestamp', 'raw_log'];
            if (defaultColumns.includes(column)) {
                alert('Default columns cannot be removed');
                return;
            }
            currentColumns = currentColumns.filter(col => col !== column);
            updateColumns();
        }
        
        function updateColumns() {
            document.getElementById('columns-input').value = currentColumns.join(',');
            const params = new URLSearchParams(window.location.search);
            params.set('columns', currentColumns.join(','));
            window.location.href = '?' + params.toString();
        }

        function showColumnsModal() {
            document.getElementById('columns-modal').classList.add('active');
        }
        
        function hideColumnsModal() {
            document.getElementById('columns-modal').classList.remove('active');
        }

        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // ----------------------------------------------------
        // CHART & UTILS
        // ----------------------------------------------------

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
                        y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.1)' }, ticks: { color: '#9db0d7' } },
                        x: { grid: { color: 'rgba(255, 255, 255, 0.1)' }, ticks: { color: '#9db0d7', maxRotation: 45, minRotation: 45 } }
                    },
                    plugins: { legend: { display: false } }
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
        
        function updatePerPage(value) {
            const params = new URLSearchParams(window.location.search);
            params.set('per_page', value);
            params.set('page', '1');
            params.set('columns', currentColumns.join(','));
            window.location.href = '?' + params.toString();
        }
        
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
        
        // ----------------------------------------------------
        // FILTERING (With URL Encoding Fixes)
        // ----------------------------------------------------
        function addToFilter(field, value) {
            const params = new URLSearchParams(window.location.search);
            params.set(field, value); // URLSearchParams automatically handles encoding
            
            // Handle excludes
            const currentExcludes = params.getAll('exclude[]');
            const newExcludes = currentExcludes.filter(exclude => {
                const parts = exclude.split(':');
                if(parts.length < 2) return true;
                const [excludeField, ...excludeValParts] = parts;
                const excludeValue = excludeValParts.join(':');
                return !(excludeField === field && excludeValue === value);
            });
            
            params.delete('exclude[]');
            newExcludes.forEach(exclude => params.append('exclude[]', exclude));
            params.set('columns', currentColumns.join(',')); // Preserve columns
            
            window.location.href = '?' + params.toString();
        }

        function addExcludeFilter(field, value) {
            const params = new URLSearchParams(window.location.search);
            const currentExcludes = params.getAll('exclude[]');
            
            const newExclude = field + ':' + value;
            if (!currentExcludes.includes(newExclude)) {
                currentExcludes.push(newExclude);
            }
            
            if (params.has(field) && params.get(field) === value) {
                params.delete(field);
            }
            
            params.delete('exclude[]');
            currentExcludes.forEach(exclude => params.append('exclude[]', exclude));
            params.set('columns', currentColumns.join(',')); // Preserve columns
            window.location.href = '?' + params.toString();
        }

        function removeFilter(field) {
            const params = new URLSearchParams(window.location.search);
            params.delete(field);
            params.set('columns', currentColumns.join(','));
            window.location.href = '?' + params.toString();
        }

        function removeExcludeFilter(excludeValue) {
            const params = new URLSearchParams(window.location.search);
            const currentExcludes = params.getAll('exclude[]');
            const newExcludes = currentExcludes.filter(exclude => exclude !== excludeValue);
            
            params.delete('exclude[]');
            newExcludes.forEach(exclude => params.append('exclude[]', exclude));
            params.set('columns', currentColumns.join(','));
            window.location.href = '?' + params.toString();
        }
        
        function clearFilters() {
            window.location.href = 'logs.php?columns=' + currentColumns.join(',');
        }
        
        // ----------------------------------------------------
        // LOG DETAILS EXPANSION
        // ----------------------------------------------------
        function toggleLogDetails(row, log) {
            const isExpanded = row.classList.contains('expanded-log');
            if (isExpanded) {
                const detailsRow = row.nextElementSibling;
                if (detailsRow && detailsRow.classList.contains('log-details-row')) {
                    detailsRow.remove();
                }
                row.classList.remove('expanded-log');
            } else {
                document.querySelectorAll('.expanded-log').forEach(r => {
                    r.classList.remove('expanded-log');
                    const next = r.nextElementSibling;
                    if (next && next.classList.contains('log-details-row')) next.remove();
                });
                
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
            let details = '';
            Object.keys(log).forEach(key => {
                if (!['_source_file'].includes(key) && log[key] !== null && log[key] !== '' && log[key] !== undefined) {
                    const value = String(log[key]);
                    
                    // We must use strict encoding for values passed to JS functions to prevent syntax errors
                    const safeValue = encodeURIComponent(value);
                    const displayValue = escapeHtml(value);
                    
                    details += `
                        <div class="log-detail-field">
                            <div class="log-detail-field-content">
                                <div class="log-detail-field-left">
                                    <span class="log-detail-field-name">${ucfirst(key.replace(/_/g, ' '))}</span>
                                    <button class="add-column-btn" onclick="event.stopPropagation(); addColumn('${key}')" title="Add to table" style="background:none; border:none; cursor:pointer; color: #66d9ef;">
                                        +
                                    </button>
                                </div>
                                <span class="log-detail-field-separator">:</span>
                                <div class="log-detail-field-middle">
                                    <div class="field-with-actions">
                                        <span class="field-value">${displayValue}</span>
                                        <div class="field-hover-actions">
                                            <button class="add-filter-btn" onclick="event.stopPropagation(); addToFilter('${key}', decodeURIComponent('${safeValue}'))" title="Filter by this value">+</button>
                                            <button class="exclude-filter-btn" onclick="event.stopPropagation(); addExcludeFilter('${key}', decodeURIComponent('${safeValue}'))" title="Exclude this value">-</button>
                                        </div>
                                    </div>
                                </div>
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
                        if (newWidth > 50) {
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
    </script>
</body>
</html>
