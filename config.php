<?php
// config.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----- Paths & directories -----
define('DATA_DIR', __DIR__ . '/data');
define('SUBMITTED_DIR', __DIR__ . '/submitted-alerts');

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0750, true);
}
if (!is_dir(SUBMITTED_DIR)) {
    mkdir(SUBMITTED_DIR, 0750, true);
}

// CSV files
define('USERS_CSV', DATA_DIR . '/users.csv');
define('ALERTS_CSV', DATA_DIR . '/alerts.csv');

// If users.csv is missing/empty, create a default admin (first-time setup only)
if (!file_exists(USERS_CSV) || filesize(USERS_CSV) === 0) {
    $defaultPassword = 'ChangeMeNow!123';
    $hash = password_hash($defaultPassword, PASSWORD_BCRYPT);
    $fp = fopen(USERS_CSV, 'w');
    if ($fp) {
        // id,username,password_hash,role
        fputcsv($fp, [1, 'admin', $hash, 'admin']);
        fclose($fp);
    }
}

// ----- Helpers -----

function sanitize($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($path)
{
    header('Location: ' . $path);
    exit;
}

// ----- User / Auth helpers -----

function load_users(): array
{
    if (!file_exists(USERS_CSV)) {
        return [];
    }

    $rows = array_map('str_getcsv', file(USERS_CSV));
    $users = [];

    foreach ($rows as $row) {
        if (!$row || trim($row[0] ?? '') === '') {
            continue;
        }

        $row = array_map('trim', $row);

        $id = null;
        $username = '';
        $name = '';
        $hash = '';
        $role = 'analyst';

        // Support multiple legacy formats:
        // 1) id,username,password_hash,role
        // 2) username,name,password_hash,role
        // 3) id,username,name,password_hash,role
        if (count($row) === 4 && is_numeric($row[0])) {
            // 1) id,username,password_hash,role
            list($id, $username, $hash, $role) = $row;
            $name = $username;
        } elseif (count($row) === 4) {
            // 2) username,name,password_hash,role
            list($username, $name, $hash, $role) = $row;
            $id = null;
        } elseif (count($row) >= 5) {
            // 3) id,username,name,password_hash,role
            list($id, $username, $name, $hash, $role) = $row;
        } else {
            // Unknown format – skip
            continue;
        }

        if ($username === '') {
            continue;
        }

        $users[] = [
            'id'            => $id,
            'username'      => $username,
            'name'          => $name !== '' ? $name : $username,
            'password_hash' => $hash,
            'role'          => ($role === 'admin' ? 'admin' : 'analyst'),
        ];
    }

    return $users;
}

function save_users(array $users): void
{
    $fp = fopen(USERS_CSV, 'w');
    if (!$fp) {
        return;
    }

    $i = 1;
    foreach ($users as $u) {
        $id = $u['id'] ?: $i;
        $username = $u['username'];
        $hash = $u['password_hash'];
        $role = ($u['role'] === 'admin') ? 'admin' : 'analyst';

        fputcsv($fp, [$id, $username, $hash, $role]);
        $i++;
    }

    fclose($fp);
}

function find_user(string $username): ?array
{
    $username = mb_strtolower(trim($username));
    if ($username === '') {
        return null;
    }

    foreach (load_users() as $user) {
        if (mb_strtolower($user['username']) === $username) {
            return $user;
        }
    }

    return null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['username']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

function require_admin(): void
{
    if (!is_logged_in()) {
        redirect('index.php');
    }

    $user = current_user();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Access denied. Admins only.';
        exit;
    }
}

// ----- Alerts helpers -----

function load_alerts(): array
{
    if (!file_exists(ALERTS_CSV)) {
        return [];
    }

    $rows = array_map('str_getcsv', file(ALERTS_CSV));
    $alerts = [];

    foreach ($rows as $row) {
        if (!$row || trim($row[0] ?? '') === '') {
            continue;
        }

        // Skip header if present
        if (strtolower($row[0]) === 'id') {
            continue;
        }

        $row = array_map('trim', $row);
        $row = array_pad($row, 10, '');

        list($id, $title, $severity, $status, $assigned_to, $source, $created_at, $updated_at, $description, $iocs_json) = $row;

        $alerts[] = [
            'id'          => $id,
            'title'       => $title,
            'severity'    => strtolower($severity),
            'status'      => strtolower($status),
            'assigned_to' => $assigned_to,
            'source'      => $source,
            'created_at'  => $created_at,
            'updated_at'  => $updated_at,
            'description' => $description,
            'iocs_json'   => $iocs_json,
            'verdict'     => '',
            'notes'       => ''
        ];
    }

    return $alerts;
}

function find_alert_by_id($alertId): ?array
{
    $alerts = load_alerts();
    foreach ($alerts as $alert) {
        if ($alert['id'] == $alertId) {
            return $alert;
        }
    }
    return null;
}

function update_alert($alertId, callable $updater): void
{
    $alerts = load_alerts();
    foreach ($alerts as &$alert) {
        if ($alert['id'] == $alertId) {
            $updater($alert);
            break;
        }
    }
    
    // Save updated alerts
    $fp = fopen(ALERTS_CSV, 'w');
    if ($fp) {
        foreach ($alerts as $alert) {
            fputcsv($fp, [
                $alert['id'],
                $alert['title'],
                $alert['severity'],
                $alert['status'],
                $alert['assigned_to'],
                $alert['source'],
                $alert['created_at'],
                $alert['updated_at'],
                $alert['description'],
                $alert['iocs_json'] ?? ''
            ]);
        }
        fclose($fp);
    }
}

function append_submission($username, $alertId, $title, $verdict, $iocs, $notes): void
{
    $filename = SUBMITTED_DIR . '/' . $username . '-SOCL1-' . strrev($username) . '.csv';
    
    $fileExists = file_exists($filename);
    $fp = fopen($filename, 'a');
    
    if (!$fileExists) {
        // Write header
        fputcsv($fp, ['Alert ID', 'Title', 'Verdict', 'IoCs', 'Notes', 'Timestamp']);
    }
    
    fputcsv($fp, [
        $alertId,
        $title,
        $verdict === 'tp' ? 'True Positive' : ($verdict === 'fp' ? 'False Positive' : 'Unknown'),
        json_encode($iocs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $notes,
        date('Y-m-d H:i:s')
    ]);
    
    fclose($fp);
}

function update_submission($username, $alertId, $title, $verdict, $iocs, $notes): void
{
    $filename = SUBMITTED_DIR . '/' . $username . '-SOCL1-' . strrev($username) . '.csv';
    
    $submissions = [];
    $fileExists = file_exists($filename);
    
    // Read existing submissions
    if ($fileExists) {
        $rows = array_map('str_getcsv', file($filename));
        $header = array_shift($rows); // Remove header
        
        foreach ($rows as $row) {
            if (count($row) >= 6 && $row[0] != $alertId) {
                $submissions[] = $row;
            }
        }
    }
    
    // Add/update current submission
    $submissions[] = [
        $alertId,
        $title,
        $verdict === 'tp' ? 'True Positive' : ($verdict === 'fp' ? 'False Positive' : 'Unknown'),
        json_encode($iocs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $notes,
        date('Y-m-d H:i:s')
    ];
    
    // Write back to file
    $fp = fopen($filename, 'w');
    fputcsv($fp, ['Alert ID', 'Title', 'Verdict', 'IoCs', 'Notes', 'Timestamp']);
    
    foreach ($submissions as $submission) {
        fputcsv($fp, $submission);
    }
    
    fclose($fp);
}

// ----- Alert Description Helpers -----

function get_alert_description($alert): string
{
    // Return a brief description without analyst notes
    $desc = $alert['description'];
    
    // Truncate long descriptions
    if (strlen($desc) > 150) {
        $desc = substr($desc, 0, 150) . '...';
    }
    
    return sanitize($desc);
}

function get_alert_source_details($alert): string
{
    $source = $alert['source'];
    $title = strtolower($alert['title']);
    $description = strtolower($alert['description']);
    
    $details = [];
    
    // Extract details based on alert type and content
    if (strpos($title, 'powershell') !== false || strpos($description, 'powershell') !== false) {
        $details[] = "Process: PowerShell";
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $description, $matches)) {
            $details[] = "IP Address: " . $matches[1];
        }
        if (preg_match('/from\s+([a-zA-Z0-9\-]+)/', $description, $matches)) {
            $details[] = "Workstation: " . $matches[1];
        }
    }
    
    if (strpos($title, 'ransomware') !== false || strpos($description, 'ransomware') !== false) {
        $details[] = "Threat: Ransomware";
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $description, $matches)) {
            $details[] = "Source IP: " . $matches[1];
        }
    }
    
    if (strpos($title, 'phishing') !== false || strpos($description, 'phishing') !== false) {
        $details[] = "Threat: Phishing";
        if (preg_match('/([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $description, $matches)) {
            $details[] = "Domain: " . $matches[1];
        }
    }
    
    if (strpos($title, 'brute force') !== false || strpos($description, 'brute force') !== false) {
        $details[] = "Attack: Brute Force";
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $description, $matches)) {
            $details[] = "Attacker IP: " . $matches[1];
        }
        if (preg_match('/user[:\s]+([a-zA-Z0-9]+)/', $description, $matches)) {
            $details[] = "Target User: " . $matches[1];
        }
    }
    
    // Default details if none matched
    if (empty($details)) {
        $details[] = "Source: " . $alert['source'];
        $details[] = "Created: " . $alert['created_at'];
    }
    
    return implode(" • ", $details);
}

// ----- Layout helpers -----

function render_sidebar(string $active = ''): void
{
    $user = current_user();
    ?>
    <aside class="sidebar">
        <div class="sidebar-header">
            <button class="sidebar-toggle"
                    onclick="document.querySelector('.sidebar').classList.toggle('compact');">
                ☰
            </button>
            <h1>CYBRIXEN SOC</h1>
            <span class="badge">Training</span>
        </div>
        <nav class="nav-menu">
            <button class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>"
                    onclick="window.location='dashboard.php'">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </button>
            <button class="nav-item <?= $active === 'alerts' ? 'active' : '' ?>"
                    onclick="window.location='alerts.php'">
                <span class="nav-icon">🚨</span>
                <span class="nav-text">Alerts</span>
            </button>
            <button class="nav-item" onclick="window.open('https://www.test.cybrixen.com', '_blank')">
                <span class="nav-icon">🔍</span>
                <span class="nav-text">Cybrixen SIEM</span>
            </button>
            <button class="nav-item" onclick="window.open('https://www.abc.cybrixen.com', '_blank')">
                <span class="nav-icon">🛡️</span>
                <span class="nav-text">Cybrixen EDR</span>
            </button>
            <button class="nav-item" onclick="alert('Configuration Review - Coming Soon')">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Configuration Review</span>
            </button>
            <button class="nav-item" onclick="alert('Threat Intelligence - Coming Soon')">
                <span class="nav-icon">📡</span>
                <span class="nav-text">Threat Intelligence</span>
            </button>
            <button class="nav-item" onclick="alert('Reporting - Coming Soon')">
                <span class="nav-icon">📈</span>
                <span class="nav-text">Reporting</span>
            </button>
            <?php if ($user && ($user['role'] ?? '') === 'admin'): ?>
                <button class="nav-item <?= $active === 'admin' ? 'active' : '' ?>"
                        onclick="window.location='admin.php'">
                    <span class="nav-icon">👨‍💼</span>
                    <span class="nav-text">Admin</span>
                </button>
            <?php endif; ?>
        </nav>
    </aside>
    <?php
}

function render_header(string $title = 'SOC Portal'): void
{
    $user = current_user();
    ?>
    <header class="header">
        <h2><?= sanitize($title) ?></h2>
        <div class="toolbar">
            <?php if ($user): ?>
                <span><?= sanitize($user['name'] ?? $user['username']) ?>
                    (<?= sanitize($user['role'] ?? '') ?>)</span>
                <button class="btn" onclick="window.location='change_password.php'">Change Password</button>
                <button class="btn danger" onclick="window.location='logout.php'">Logout</button>
            <?php endif; ?>
        </div>
    </header>
    <?php
}