<?php
// Hardened session configuration
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'use_strict_mode' => true,
]);

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net");

// Database configuration for user storage
define('USERS_CSV', __DIR__ . '/data/users.csv');
define('ALERTS_CSV', __DIR__ . '/data/alerts.csv');
define('ASSETS_DIR', __DIR__ . '/data/assets/');

// Create data directory if it doesn't exist
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// Check if user is logged in
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

// Input helpers
function getParam($key, $method = INPUT_GET, $default = '') {
    $value = filter_input($method, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    return $value === null ? $default : $value;
}

function sanitizeArray($values) {
    if (!is_array($values)) {
        return [];
    }
    return array_map(function($value) {
        return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
    }, $values);
}

// Secure CSV file operations - UPDATED FOR USER HANDLING
function readCSV($filename) {
    // Special handling for users.csv - always use indexed arrays
    if (strpos($filename, 'users.csv') !== false) {
        $data = [];
        if (file_exists($filename)) {
            $file = fopen($filename, 'r');
            if ($file) {
                while (($row = fgetcsv($file)) !== FALSE) {
                    $data[] = $row;
                }
                fclose($file);
            }
        }
        return $data;
    }
    
    // For other CSV files, use the new format (associative arrays)
    $data = [];
    if (file_exists($filename)) {
        $file = fopen($filename, 'r');
        if ($file) {
            // Read header
            $header = fgetcsv($file);
            if ($header === false) {
                fclose($file);
                return $data;
            }
            
            while (($row = fgetcsv($file)) !== FALSE) {
                // Combine header with row data
                $combined = [];
                foreach ($header as $index => $key) {
                    $combined[$key] = $row[$index] ?? '';
                }
                $combined['_source_file'] = $filename;
                $data[] = $combined;
            }
            fclose($file);
        }
    }
    return $data;
}

function writeCSV($filename, $data) {
    if (empty($data)) return false;
    
    // For users.csv, handle indexed arrays
    if (strpos($filename, 'users.csv') !== false) {
        $file = fopen($filename, 'w');
        if ($file) {
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
            return true;
        }
        return false;
    }
    
    // For other CSV files
    $file = fopen($filename, 'w');
    if ($file) {
        // Write header from first row keys
        $header = array_keys($data[0]);
        // Drop accidental inline header rows
        if ($data && array_values($data[0]) === $header) {
            array_shift($data);
        }
        // Remove internal fields from header
        $header = array_filter($header, function($key) {
            return !in_array($key, ['_source_file']);
        });
        fputcsv($file, $header);

        foreach ($data as $row) {
            // Remove internal fields before writing
            $write_row = [];
            foreach ($header as $key) {
                $write_row[] = $row[$key] ?? '';
            }
            fputcsv($file, $write_row);
        }
        fclose($file);
        return true;
    }
    return false;
}

// CSRF protection
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Utility functions
function escapeHtml($unsafe) {
    return htmlspecialchars($unsafe ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}


// Get all log files from the new structure
function getAllLogFiles() {
    $logFiles = [];
    
    if (!file_exists(ASSETS_DIR)) {
        createAssetDirectoryStructure();
    }
    
    $assets = scandir(ASSETS_DIR);
    foreach ($assets as $asset) {
        if ($asset === '.' || $asset === '..') continue;
        $assetPath = ASSETS_DIR . $asset;
        if (is_dir($assetPath)) {
            $logTypes = scandir($assetPath);
            foreach ($logTypes as $logType) {
                if ($logType === '.' || $logType === '..') continue;
                if (pathinfo($logType, PATHINFO_EXTENSION) === 'csv') {
                    $logFiles[] = [
                        'path' => $assetPath . '/' . $logType,
                        'asset' => $asset,
                        'log_type' => pathinfo($logType, PATHINFO_FILENAME)
                    ];
                }
            }
        }
    }
    return $logFiles;
}

// Read all logs from the new structure and normalize them for UI/analytics
function readAllLogs() {
    $allLogs = [];
    $logFiles = getAllLogFiles();

    foreach ($logFiles as $fileInfo) {
        $logs = readCSV($fileInfo['path']);
        foreach ($logs as $log) {
            $log['asset'] = $fileInfo['asset'];
            $log['log_type'] = $fileInfo['log_type'];
            $log = enrichLog($log);
            $log['raw_log'] = generateRawLogString($log);
            $allLogs[] = $log;
        }
    }

    usort($allLogs, function($a, $b) {
        $timeA = strtotime($a['timestamp'] ?? '');
        $timeB = strtotime($b['timestamp'] ?? '');
        return $timeB - $timeA;
    });

    return $allLogs;
}

// Generate raw log string for display
function generateRawLogString($log) {
    $raw = '';
    foreach ($log as $key => $value) {
        if (!in_array($key, ['asset', 'log_type', 'raw_log', '_source_file']) && !empty($value)) {
            $raw .= "$key: $value | ";
        }
    }
    return rtrim($raw, ' | ');
}

// Normalize logs to expose consistent SIEM fields
function enrichLog($log) {
    $log['event_id'] = $log['event_id'] ?? ($log['signature_id'] ?? ($log['rule_id'] ?? 'N/A'));
    $log['severity'] = normalizeSeverity($log);
    $log['event_category'] = normalizeCategory($log);
    $log['event_summary'] = buildSummary($log);
    $log['source'] = $log['asset'] . ' / ' . ($log['log_type'] ?? '');
    $log['normalized_time'] = $log['timestamp'] ?? '';
    return $log;
}

function normalizeSeverity($log) {
    $logType = $log['log_type'] ?? '';
    $eventId = (string)($log['event_id'] ?? '');

    $severityMaps = [
        'security' => [
            '4625' => 'high',
            '4672' => 'high',
            '4732' => 'medium',
            '4648' => 'medium',
            '4624' => 'low',
        ],
        'sysmon' => [
            '1' => 'medium',
            '3' => 'medium',
            '7' => 'high',
            '10' => 'high',
            '11' => 'medium',
        ],
        'powershell' => [
            '400' => 'medium',
            '403' => 'critical',
            '600' => 'low',
            '800' => 'medium',
        ],
    ];

    if (isset($severityMaps[$logType][$eventId])) {
        return $severityMaps[$logType][$eventId];
    }

    if (isset($log['alert_severity'])) {
        return intval($log['alert_severity']) >= 3 ? 'high' : (intval($log['alert_severity']) >= 2 ? 'medium' : 'low');
    }

    if (isset($log['action']) && in_array(strtoupper($log['action']), ['DROP', 'REJECT'])) {
        return 'high';
    }

    return $log['severity'] ?? 'info';
}

function normalizeCategory($log) {
    $logType = $log['log_type'] ?? '';
    $eventId = (string)($log['event_id'] ?? '');

    $map = [
        'security' => [
            '4624' => 'Authentication Success',
            '4625' => 'Authentication Failure',
            '4672' => 'Privilege Assignment',
            '4732' => 'Group Modification',
            '4648' => 'Explicit Credentials',
        ],
        'sysmon' => [
            '1' => 'Process Creation',
            '3' => 'Network Connection',
            '5' => 'Process Termination',
            '7' => 'Image Loaded',
            '10' => 'Process Access',
            '11' => 'File Created',
        ],
        'powershell' => [
            '400' => 'Engine Lifecycle',
            '403' => 'Blocked Script',
            '600' => 'Provider Lifecycle',
            '800' => 'Pipeline Execution',
        ],
    ];

    return $map[$logType][$eventId] ?? ucfirst(str_replace('_', ' ', $logType));
}

function buildSummary($log) {
    if (!empty($log['message'])) {
        return $log['message'];
    }

    if (($log['log_type'] ?? '') === 'security') {
        return sprintf('User %s %s from %s (Logon type %s)',
            $log['user'] ?? 'unknown',
            strtolower($log['result'] ?? 'activity'),
            $log['source_address'] ?? 'N/A',
            $log['logon_type'] ?? 'N/A'
        );
    }

    if (($log['log_type'] ?? '') === 'sysmon') {
        return sprintf('%s (%s) executed with PID %s',
            $log['process_name'] ?? 'process',
            $log['image'] ?? 'unknown image',
            $log['process_id'] ?? 'N/A'
        );
    }

    if (($log['log_type'] ?? '') === 'firewall') {
        return sprintf('%s %s:%s -> %s:%s via %s',
            strtoupper($log['action'] ?? 'action'),
            $log['src_ip'] ?? '-',
            $log['src_port'] ?? '-',
            $log['dest_ip'] ?? '-',
            $log['dest_port'] ?? '-',
            $log['protocol'] ?? ''
        );
    }

    if (($log['log_type'] ?? '') === 'iis') {
        return sprintf('%s %s%s returned %s',
            $log['method'] ?? 'REQ',
            $log['uri_stem'] ?? '/',
            !empty($log['uri_query']) ? '?' . $log['uri_query'] : '',
            $log['status'] ?? ''
        );
    }

    if (isset($log['alert_message'])) {
        return $log['alert_message'];
    }

    return $log['raw_log'] ?? 'Activity recorded';
}

// Create the asset directory structure
function createAssetDirectoryStructure() {
    $structure = [
        'windows-workstation' => ['powershell', 'sysmon', 'security'],
        'windows-web-server' => ['powershell', 'sysmon', 'security', 'iis'],
        'linux-app-server' => ['audit', 'cron', 'application'],
        'domain-controller' => ['powershell', 'security', 'dns'],
        'perimeter-firewall' => ['firewall'],
        'ids-ips' => ['suricata']
    ];
    
    foreach ($structure as $asset => $logTypes) {
        $assetDir = ASSETS_DIR . $asset;
        if (!file_exists($assetDir)) {
            mkdir($assetDir, 0755, true);
        }
        foreach ($logTypes as $logType) {
            $filePath = $assetDir . '/' . $logType . '.csv';
            if (!file_exists($filePath)) {
                generateSampleLogData($asset, $logType);
            }
        }
    }
}

// Generate sample log data for each log type
function generateSampleLogData($asset, $logType) {
    $data = [];
    
    switch ($asset) {
        case 'windows-workstation':
            $data = generateWindowsWorkstationLogs($logType);
            break;
        case 'windows-web-server':
            $data = generateWindowsWebServerLogs($logType);
            break;
        case 'linux-app-server':
            $data = generateLinuxAppServerLogs($logType);
            break;
        case 'domain-controller':
            $data = generateDomainControllerLogs($logType);
            break;
        case 'perimeter-firewall':
            $data = generateFirewallLogs();
            break;
        case 'ids-ips':
            $data = generateIDSLogs();
            break;
    }
    
    if (!empty($data)) {
        writeCSV(ASSETS_DIR . $asset . '/' . $logType . '.csv', $data);
    }
}

// Sample data generators
function generateWindowsWorkstationLogs($logType) {
    $data = [];
    $baseTime = time() - 86400; // Start from 24 hours ago
    
    switch ($logType) {
        case 'powershell':
            $headers = ['timestamp', 'event_id', 'user', 'computer', 'script_name', 'command_line', 'result'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 3600));
                $data[] = [
                    'timestamp' => $time,
                    'event_id' => [400, 403, 600, 800][$i % 4],
                    'user' => ['DOMAIN\\jdoe', 'DOMAIN\\asmith', 'WS-001\\admin'][$i % 3],
                    'computer' => 'WS-001',
                    'script_name' => ['Get-Process.ps1', 'ScriptBlock', 'NULL', 'Start-Process.ps1'][$i % 4],
                    'command_line' => [
                        'Get-Process -Name explorer',
                        'Invoke-WebRequest http://external.com/file.exe',
                        'Set-ExecutionPolicy Unrestricted',
                        'Start-Process notepad.exe'
                    ][$i % 4],
                    'result' => ['Success', 'Blocked', 'Success', 'Failure'][$i % 4]
                ];
            }
            break;
            
        case 'sysmon':
            $headers = ['timestamp', 'event_id', 'user', 'computer', 'process_name', 'process_id', 'image', 'command_line', 'parent_process'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 1800));
                $data[] = [
                    'timestamp' => $time,
                    'event_id' => [1, 3, 5, 7, 10, 11][$i % 6],
                    'user' => ['DOMAIN\\jdoe', 'SYSTEM', 'WS-001\\user1'][$i % 3],
                    'computer' => 'WS-001',
                    'process_name' => ['explorer.exe', 'svchost.exe', 'chrome.exe', 'powershell.exe'][$i % 4],
                    'process_id' => 1000 + $i,
                    'image' => [
                        'C:\\Windows\\explorer.exe',
                        'C:\\Windows\\System32\\svchost.exe',
                        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                        'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe'
                    ][$i % 4],
                    'command_line' => [
                        'explorer.exe',
                        'svchost.exe -k netsvcs',
                        'chrome.exe --type=renderer',
                        'powershell.exe -Command Get-Process'
                    ][$i % 4],
                    'parent_process' => ['services.exe', 'services.exe', 'explorer.exe', 'explorer.exe'][$i % 4]
                ];
            }
            break;
            
        case 'security':
            $headers = ['timestamp', 'event_id', 'log_name', 'user', 'computer', 'source_address', 'logon_type', 'result'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 1200));
                $data[] = [
                    'timestamp' => $time,
                    'event_id' => [4624, 4625, 4672, 4648, 4732][$i % 5],
                    'log_name' => 'Security',
                    'user' => ['DOMAIN\\jdoe', 'Unknown', 'DOMAIN\\admin', 'SYSTEM'][$i % 4],
                    'computer' => 'WS-001',
                    'source_address' => ['192.168.1.45', '10.1.1.100', '192.168.1.45', '::1'][$i % 4],
                    'logon_type' => [2, 3, 2, 5, 10][$i % 5],
                    'result' => ['Success', 'Failure', 'Success', 'Success', 'Failure'][$i % 5]
                ];
            }
            break;
    }
    
    return $data;
}

function generateWindowsWebServerLogs($logType) {
    $data = [];
    $baseTime = time() - 86400;
    
    switch ($logType) {
        case 'iis':
            $headers = ['timestamp', 'server_ip', 'method', 'uri_stem', 'uri_query', 'port', 'username', 'client_ip', 'user_agent', 'status', 'substatus', 'win32_status'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 900));
                $data[] = [
                    'timestamp' => $time,
                    'server_ip' => '192.168.1.10',
                    'method' => ['GET', 'POST', 'PUT', 'DELETE'][$i % 4],
                    'uri_stem' => ['/login.aspx', '/api/users', '/images/logo.png', '/admin/config'][$i % 4],
                    'uri_query' => ['', 'user=admin&pass=test', 'id=123', 'action=delete'][$i % 4],
                    'port' => 80,
                    'username' => ['-', 'admin', 'user1', '-'][$i % 4],
                    'client_ip' => ['203.0.113.45', '198.51.100.23', '192.168.1.150', '203.0.113.67'][$i % 4],
                    'user_agent' => [
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
                        'curl/7.68.0',
                        'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0)'
                    ][$i % 4],
                    'status' => [200, 401, 404, 500, 301][$i % 5],
                    'substatus' => [0, 2, 0, 0, 0][$i % 5],
                    'win32_status' => [0, 5, 2, 1, 0][$i % 5]
                ];
            }
            break;
            
        // Add other web server log types similarly...
        default:
            $data = generateWindowsWorkstationLogs($logType);
            break;
    }
    
    return $data;
}

function generateLinuxAppServerLogs($logType) {
    $data = [];
    $baseTime = time() - 86400;
    
    switch ($logType) {
        case 'audit':
            $headers = ['timestamp', 'type', 'pid', 'uid', 'auid', 'ses', 'subj', 'op', 'success', 'exe', 'hostname', 'addr', 'terminal', 'res'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 1100));
                $data[] = [
                    'timestamp' => $time,
                    'type' => ['USER_LOGIN', 'SYSCALL', 'EXECVE', 'PATH', 'SOCKADDR'][$i % 5],
                    'pid' => 1000 + $i,
                    'uid' => [0, 1000, 1001][$i % 3],
                    'auid' => 1000,
                    'ses' => $i + 1,
                    'subj' => 'unconfined_u:unconfined_r:unconfined_t:s0-s0:c0.c1023',
                    'op' => ['login', 'openat', 'execve', 'mkdir', 'connect'][$i % 5],
                    'success' => ['yes', 'no'][$i % 2],
                    'exe' => ['/usr/sbin/sshd', '/usr/bin/vim', '/bin/mkdir', '/usr/bin/curl'][$i % 4],
                    'hostname' => 'linux-srv01',
                    'addr' => ['192.168.1.45', NULL, '10.1.1.100'][$i % 3],
                    'terminal' => ['pts/0', 'pts/1', 'tty1'][$i % 3],
                    'res' => ['success', 'failed', 'denied'][$i % 3]
                ];
            }
            break;
            
        case 'cron':
            $headers = ['timestamp', 'user', 'command', 'pid', 'hostname'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 3600));
                $data[] = [
                    'timestamp' => $time,
                    'user' => ['root', 'www-data', 'backup'][$i % 3],
                    'command' => [
                        '/usr/lib/php/sessionclean',
                        '/opt/backup/run-backup.sh',
                        '/usr/bin/php /var/www/html/cron.php',
                        '/usr/bin/updatedb'
                    ][$i % 4],
                    'pid' => 500 + $i,
                    'hostname' => 'linux-srv01'
                ];
            }
            break;
            
        case 'application':
            $headers = ['timestamp', 'level', 'component', 'message', 'pid', 'hostname'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 800));
                $data[] = [
                    'timestamp' => $time,
                    'level' => ['INFO', 'WARNING', 'ERROR', 'DEBUG'][$i % 4],
                    'component' => ['webapp', 'database', 'auth', 'api'][$i % 4],
                    'message' => [
                        'User login successful',
                        'Database connection timeout',
                        'Failed authentication attempt',
                        'API rate limit exceeded'
                    ][$i % 4],
                    'pid' => 800 + $i,
                    'hostname' => 'linux-srv01'
                ];
            }
            break;
    }
    
    return $data;
}

function generateDomainControllerLogs($logType) {
    $data = [];
    $baseTime = time() - 86400;
    
    switch ($logType) {
        case 'dns':
            $headers = ['timestamp', 'client_ip', 'query_name', 'query_type', 'response_code', 'server', 'zone'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 600));
                $data[] = [
                    'timestamp' => $time,
                    'client_ip' => ['192.168.1.45', '192.168.1.100', '10.1.1.50'][$i % 3],
                    'query_name' => ['google.com', 'malicious-domain.com', 'internal-app.corp.local', 'update.microsoft.com'][$i % 4],
                    'query_type' => ['A', 'AAAA', 'MX', 'TXT'][$i % 4],
                    'response_code' => ['NOERROR', 'NXDOMAIN', 'REFUSED'][$i % 3],
                    'server' => 'dc-01.corp.local',
                    'zone' => ['corp.local', '.'][$i % 2]
                ];
            }
            break;
            
        default:
            $data = generateWindowsWorkstationLogs($logType);
            break;
    }
    
    return $data;
}

function generateFirewallLogs() {
    $data = [];
    $baseTime = time() - 86400;
    $headers = ['timestamp', 'action', 'protocol', 'src_ip', 'dest_ip', 'src_port', 'dest_port', 'size', 'tcp_flags', 'rule_id'];
    $data[] = array_combine($headers, $headers);
    
    for ($i = 0; $i < 15; $i++) {
        $time = date('Y-m-d H:i:s', $baseTime + ($i * 500));
        $data[] = [
            'timestamp' => $time,
            'action' => ['DROP', 'ALLOW', 'REJECT'][$i % 3],
            'protocol' => ['TCP', 'UDP', 'ICMP'][$i % 3],
            'src_ip' => ['198.51.100.67', '192.168.1.45', '203.0.113.89', '10.1.1.100'][$i % 4],
            'dest_ip' => ['192.168.1.10', '8.8.8.8', '192.168.1.20', '192.168.1.30'][$i % 4],
            'src_port' => [54321, 12345, 4444, 5555][$i % 4],
            'dest_port' => [22, 53, 161, 443, 80][$i % 5],
            'size' => [60, 512, 120, 1500][$i % 4],
            'tcp_flags' => ['S', 'PA', 'RA', NULL][$i % 4],
            'rule_id' => [4001, 1002, 4003, 2001][$i % 4]
        ];
    }
    
    return $data;
}

function generateIDSLogs() {
    $data = [];
    $baseTime = time() - 86400;
    $headers = ['timestamp', 'alert_type', 'protocol', 'src_ip', 'dest_ip', 'src_port', 'dest_port', 'alert_severity', 'alert_message', 'signature_id', 'classification'];
    $data[] = array_combine($headers, $headers);
    
    for ($i = 0; $i < 15; $i++) {
        $time = date('Y-m-d H:i:s', $baseTime + ($i * 700));
        $data[] = [
            'timestamp' => $time,
            'alert_type' => [
                'ET SCAN Potential SSH Scan',
                'ET POLICY MS Terminal Server traffic',
                'ET TROJAN Possible Malware Connection',
                'ET WEB_SERVER Possible SQL Injection'
            ][$i % 4],
            'protocol' => 'TCP',
            'src_ip' => ['198.51.100.23', '203.0.113.50', '192.168.1.150', '198.51.100.89'][$i % 4],
            'dest_ip' => ['192.168.1.0/24', '203.0.113.50', '192.168.1.10', '192.168.1.20'][$i % 4],
            'src_port' => [54321, 3389, 4444, 8080][$i % 4],
            'dest_port' => [22, 54321, 80, 443][$i % 4],
            'alert_severity' => [2, 3, 1, 2][$i % 4],
            'alert_message' => [
                'GPL ATTACK_RESPONSE id check returned root',
                'GPL RPC sadmind query with root credentials attempt',
                'ET TROJAN Metasploit payload detected',
                'ET WEB_SERVER SQL Injection SELECT UNION'
            ][$i % 4],
            'signature_id' => [2100498, 2003340, 2024411, 2013023][$i % 4],
            'classification' => [
                'Attempted Information Leak',
                'Potential Corporate Privacy Violation',
                'A Network Trojan was detected',
                'Web Application Attack'
            ][$i % 4]
        ];
    }
    
    return $data;
}

// Initialize files on first run
createAssetDirectoryStructure();

// Function to initialize CSV files if they don't exist - UPDATED
function initializeCSVFiles() {
    $files = [
        USERS_CSV => [
            ['1', 'admin', '$2y$10$8S8Sz8QzQzQzQzQzQzQzQeQzQzQzQzQzQzQzQzQzQzQzQzQzQzQ', 'admin'],
            ['2', 'analyst1', '$2y$10$8S8Sz8QzQzQzQzQzQzQzQeQzQzQzQzQzQzQzQzQzQzQzQzQzQzQ', 'analyst'],
            ['3', 'analyst2', '$2y$10$8S8Sz8QzQzQzQzQzQzQzQeQzQzQzQzQzQzQzQzQzQzQzQzQzQzQ', 'analyst']
        ],
        ALERTS_CSV => [
            ['id', 'timestamp', 'severity', 'title', 'description', 'status', 'assigned_to', 'source'],
            ['alert-1', '2024-01-15 08:30:00', 'critical', 'Multiple Failed Login Attempts', 'Multiple failed login attempts from single IP', 'new', 'unassigned', 'firewall'],
            ['alert-2', '2024-01-15 08:25:00', 'high', 'Port Scan Detected', 'Port scanning activity detected from external IP', 'in_progress', 'analyst1', 'ids'],
            ['alert-3', '2024-01-15 08:20:00', 'medium', 'Unauthorized File Access', 'User attempted to access restricted files', 'new', 'unassigned', 'windows']
        ]
    ];
    
    foreach ($files as $filename => $defaultData) {
        if (!file_exists($filename)) {
            $file = fopen($filename, 'w');
            if ($file) {
                foreach ($defaultData as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
            }
        }
    }
}

// Initialize files
initializeCSVFiles();

?>

