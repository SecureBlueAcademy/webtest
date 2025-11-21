<?php
session_start();
require __DIR__ . '/php/data.php';

$config = $cfg;
$indicators = loadIndicators($config['csv_path']);
$summary = summarizeIndicators($indicators);

$page = $_GET['page'] ?? 'dashboard';
$pages = ['dashboard', 'indicators', 'search', 'families', 'tags', 'upload', 'about'];
if (!in_array($page, $pages, true)) {
    $page = 'dashboard';
}

$filters = [
    'q' => $_GET['q'] ?? '',
    'type' => $_GET['type'] ?? '',
    'tag' => $_GET['tag'] ?? '',
    'category' => $_GET['category'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$isAdmin = $_SESSION['is_admin'] ?? false;
$loginError = '';
$uploadMessage = '';

if (isset($_POST['login_password'])) {
    if (password_verify($_POST['login_password'], $config['admin_password_hash'])) {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        $loginError = 'Invalid admin password.';
    }
}

if ($isAdmin && isset($_POST['upload_csv'])) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadMessage = 'Upload failed. Please try again.';
    } else {
        $file = $_FILES['csv_file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($extension !== 'csv' || !in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true)) {
            $uploadMessage = 'Invalid file type. Please upload a CSV file.';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $uploadMessage = 'Potentially unsafe upload detected.';
        } else {
            if (move_uploaded_file($file['tmp_name'], $config['csv_path'])) {
                chmod($config['csv_path'], 0640);
                $uploadMessage = 'CSV successfully updated. Latest data is now live.';
                $indicators = loadIndicators($config['csv_path']);
                $summary = summarizeIndicators($indicators);
            } else {
                $uploadMessage = 'Unable to save the uploaded file. Check permissions.';
            }
        }
    }
}

$filteredIndicators = filterIndicators($indicators, $filters);
$types = uniqueValues($indicators, 'Indicator Type');
$statuses = uniqueValues($indicators, 'Status');
$tags = array_keys($summary['byTags']);
$categories = array_keys(getCategories());

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($config['site_title']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">⚡ <?php echo e($config['site_title']); ?></div>
        <nav>
            <a href="?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
            <a href="?page=indicators" class="<?php echo $page === 'indicators' ? 'active' : ''; ?>">Indicators</a>
            <a href="?page=search" class="<?php echo $page === 'search' ? 'active' : ''; ?>">Search</a>
            <a href="?page=families" class="<?php echo $page === 'families' ? 'active' : ''; ?>">Threat Families</a>
            <a href="?page=tags" class="<?php echo $page === 'tags' ? 'active' : ''; ?>">Tag Explorer</a>
            <?php if ($isAdmin): ?>
            <a href="?page=upload" class="<?php echo $page === 'upload' ? 'active' : ''; ?>">Upload</a>
            <?php else: ?>
            <a href="?page=upload" class="<?php echo $page === 'upload' ? 'active' : ''; ?>">Admin Login</a>
            <?php endif; ?>
            <a href="?page=about" class="<?php echo $page === 'about' ? 'active' : ''; ?>">About</a>
        </nav>
        <div class="sidebar-footer">
            <span class="badge">CSV-Driven</span>
            <span class="badge">No DB</span>
        </div>
    </aside>
    <main class="content">
        <header class="topbar">
            <div>
                <div class="page-title"><?php echo ucfirst($page); ?></div>
                <div class="subtitle">Cyber threat intelligence workspace</div>
            </div>
            <div class="top-actions">
                <div class="pill">Indicators: <strong><?php echo number_format($summary['total']); ?></strong></div>
                <div class="pill">Updated: <?php echo date('Y-m-d H:i'); ?></div>
            </div>
        </header>

        <?php if ($page === 'dashboard'): ?>
            <section class="grid">
                <div class="card span-2">
                    <div class="card-title">IOC Overview</div>
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-label">Total IOCs</div>
                            <div class="stat-value"><?php echo number_format($summary['total']); ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Indicator Types</div>
                            <div class="stat-value"><?php echo count($summary['byType']); ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Threat Families</div>
                            <div class="stat-value"><?php echo count($summary['byFamily']); ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Distinct Tags</div>
                            <div class="stat-value"><?php echo count($summary['byTags']); ?></div>
                        </div>
                    </div>
                    <div class="chart-bar">
                        <?php foreach ($summary['confidence'] as $level => $count): ?>
                            <div class="bar" style="width: <?php echo $summary['total'] ? ($count / $summary['total'] * 100) : 0; ?>%">
                                <span><?php echo e(ucfirst($level)); ?> (<?php echo $count; ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-title">By Type</div>
                    <ul class="list">
                        <?php foreach ($summary['byType'] as $type => $count): ?>
                            <li><span><?php echo e($type); ?></span><span class="badge"><?php echo $count; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card">
                    <div class="card-title">Top Tags</div>
                    <div class="tag-cloud">
                        <?php foreach ($summary['byTags'] as $tag => $count): ?>
                            <span class="tag" data-tag="<?php echo e($tag); ?>"><?php echo e($tag); ?> (<?php echo $count; ?>)</span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-title">Threat Families</div>
                    <ul class="list">
                        <?php foreach ($summary['byFamily'] as $family => $count): ?>
                            <li><span><?php echo e($family); ?></span><span class="badge"><?php echo $count; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card span-2">
                    <div class="card-title">Recently Added</div>
                    <div class="table-wrapper compact">
                        <table>
                            <thead><tr><th>Indicator</th><th>Type</th><th>Family</th><th>Last Seen</th><th>Confidence</th></tr></thead>
                            <tbody>
                            <?php foreach ($summary['recent'] as $ioc): ?>
                                <tr>
                                    <td><?php echo e($ioc['Value']); ?></td>
                                    <td><?php echo e($ioc['Indicator Type']); ?></td>
                                    <td><?php echo e($ioc['Threat Family']); ?></td>
                                    <td><?php echo e($ioc['Last Seen']); ?></td>
                                    <td><span class="badge"><?php echo e($ioc['Confidence']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php elseif ($page === 'indicators' || $page === 'search'): ?>
            <section class="card">
                <div class="card-title">Indicator Repository</div>
                <form class="filters" method="get">
                    <input type="hidden" name="page" value="<?php echo e($page); ?>">
                    <input type="text" name="q" placeholder="Search across all metadata" value="<?php echo e($filters['q']); ?>">
                    <select name="type">
                        <option value="">Type (all)</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?php echo e($type); ?>" <?php echo strtolower($filters['type']) === strtolower($type) ? 'selected' : ''; ?>><?php echo e($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="category">
                        <option value="">Category (all)</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo e($cat); ?>" <?php echo $filters['category'] === $cat ? 'selected' : ''; ?>><?php echo e($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tag">
                        <option value="">Tag (all)</option>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo e($tag); ?>" <?php echo strtolower($filters['tag']) === strtolower($tag) ? 'selected' : ''; ?>><?php echo e($tag); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">Status (all)</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo strtolower($filters['status']) === strtolower($status) ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn">Apply</button>
                    <button type="button" class="btn ghost" id="exportCsv">Export</button>
                </form>
                <div class="badge-row">
                    <?php foreach ($categories as $cat): ?>
                        <span class="badge clickable category-badge" data-category="<?php echo e($cat); ?>"><?php echo e($cat); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="table-wrapper" id="iocTable" data-page="<?php echo e($page); ?>">
                    <table>
                        <thead>
                            <tr>
                                <th data-sort="Value">Indicator</th>
                                <th data-sort="Indicator Type">Type</th>
                                <th data-sort="Threat Family">Threat Family</th>
                                <th data-sort="Tags">Tags</th>
                                <th data-sort="Confidence">Confidence</th>
                                <th data-sort="Last Seen">Last Seen</th>
                                <th data-sort="Status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filteredIndicators as $ioc): ?>
                            <?php $rowTags = array_filter(array_map('trim', explode(',', $ioc['Tags'] ?? ''))); ?>
                            <tr data-meta='<?php echo json_encode($ioc); ?>' data-category="<?php echo e(mapCategory($ioc['Indicator Type'] ?? '')); ?>">
                                <td class="value-cell"><?php echo e($ioc['Value']); ?></td>
                                <td><?php echo e($ioc['Indicator Type']); ?></td>
                                <td><?php echo e($ioc['Threat Family']); ?></td>
                                <td>
                                    <?php foreach ($rowTags as $tag): ?>
                                        <span class="tag" data-tag="<?php echo e($tag); ?>"><?php echo e($tag); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><span class="badge"><?php echo e($ioc['Confidence']); ?></span></td>
                                <td><?php echo e($ioc['Last Seen']); ?></td>
                                <td><?php echo e($ioc['Status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="pagination"></div>
            </section>
        <?php elseif ($page === 'families'): ?>
            <section class="card">
                <div class="card-title">Threat Families</div>
                <div class="list-grid">
                <?php foreach ($summary['byFamily'] as $family => $count): ?>
                    <div class="mini-card">
                        <div class="mini-title"><?php echo e($family); ?></div>
                        <div class="mini-count"><?php echo $count; ?> indicators</div>
                    </div>
                <?php endforeach; ?>
                </div>
            </section>
        <?php elseif ($page === 'tags'): ?>
            <section class="card">
                <div class="card-title">Tag Explorer</div>
                <div class="tag-cloud">
                    <?php foreach ($summary['byTags'] as $tag => $count): ?>
                        <span class="tag clickable" data-tag="<?php echo e($tag); ?>"><?php echo e($tag); ?> (<?php echo $count; ?>)</span>
                    <?php endforeach; ?>
                </div>
                <p class="muted">Click a tag to pivot to the Indicators view with that tag filter applied.</p>
            </section>
        <?php elseif ($page === 'upload'): ?>
            <section class="card narrow">
                <div class="card-title">Administrative Upload</div>
                <?php if (!$isAdmin): ?>
                    <form method="post" class="auth-form">
                        <label>Admin Password</label>
                        <input type="password" name="login_password" required placeholder="Enter admin password">
                        <?php if ($loginError): ?><div class="error"><?php echo e($loginError); ?></div><?php endif; ?>
                        <button type="submit" class="btn">Authenticate</button>
                    </form>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" class="upload-form">
                        <label for="csv_file">Upload new indicators CSV</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <input type="hidden" name="upload_csv" value="1">
                        <p class="muted">The file will replace the current indicators.csv. Ensure headers stay consistent.</p>
                        <button type="submit" class="btn">Upload & Replace</button>
                    </form>
                    <?php if ($uploadMessage): ?><div class="success"><?php echo e($uploadMessage); ?></div><?php endif; ?>
                <?php endif; ?>
            </section>
        <?php elseif ($page === 'about'): ?>
            <section class="card">
                <div class="card-title">About</div>
                <p>This static-ready threat intelligence portal is powered purely by PHP, JavaScript, and CSS. All data is sourced from a CSV file so updates require no code changes. Designed for Hostinger and similar static PHP hosting with no external dependencies.</p>
                <ul class="list bullets">
                    <li>Dark, SOC-friendly UI with responsive layout.</li>
                    <li>Role-based upload to refresh the CSV on the fly.</li>
                    <li>Client-side interactivity for quick pivots, filtering, and exports.</li>
                    <li>Server-side filtering to keep responses lean.</li>
                </ul>
            </section>
        <?php endif; ?>
    </main>
</div>

<div class="modal" id="detailModal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <div class="modal-title">Indicator Details</div>
                <div class="modal-subtitle" id="modalSubtitle"></div>
            </div>
            <button class="close" id="closeModal">×</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>const indicatorData = <?php echo json_encode($filteredIndicators); ?>;</script>
<script src="assets/js/app.js"></script>
</body>
</html>
