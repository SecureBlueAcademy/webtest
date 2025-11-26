<?php
session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

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

// Read all logs from the new structure
function readAllLogs() {
    $allLogs = [];
    $logFiles = getAllLogFiles();
    
    foreach ($logFiles as $fileInfo) {
        $logs = readCSV($fileInfo['path']);
        foreach ($logs as $log) {
            // Add source information
            $log['asset'] = $fileInfo['asset'];
            $log['log_type'] = $fileInfo['log_type'];
            $log['raw_log'] = generateRawLogString($log);
            $allLogs[] = $log;
        }
    }
    
    // Sort by timestamp
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

// Generate sample log data for each log type - UPDATED WITH REALISTIC FIELDS
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

// Sample data generators - UPDATED WITH EVENT-SPECIFIC FIELDS
function generateWindowsWorkstationLogs($logType) {
    $data = [];
    $baseTime = time() - 86400; // Start from 24 hours ago
    
    switch ($logType) {
        case 'powershell':
            // PowerShell logs with different fields based on event type
            $headers = ['timestamp', 'event_id', 'event_type', 'user', 'computer', 'script_name', 'command_line', 'result', 'script_block_id', 'total_blocks', 'path'];
            $data[] = array_combine($headers, $headers);
            
            $eventTypes = [
                [400, 'ScriptBlock', 'Get-Process.ps1', 'Get-Process -Name explorer', 'Success', 'SB_001', 1, 'C:\\Scripts\\Get-Process.ps1'],
                [403, 'ScriptBlock', 'ScriptBlock', 'Invoke-WebRequest http://external.com/file.exe', 'Blocked', 'SB_002', 1, ''],
                [600, 'Provider', 'NULL', 'Set-ExecutionPolicy Unrestricted', 'Success', '', 0, ''],
                [800, 'Pipeline', 'Start-Process.ps1', 'Start-Process notepad.exe', 'Failure', '', 0, 'C:\\Scripts\\Start-Process.ps1'],
                [4104, 'Execute', 'RemoteCommand', 'Invoke-Command -ComputerName SERVER01 {Get-Service}', 'Success', '', 0, '']
            ];
            
            for ($i = 0; $i < 20; $i++) {
                $event = $eventTypes[$i % count($eventTypes)];
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 1800));
                $data[] = [
                    'timestamp' => $time,
                    'event_id' => $event[0],
                    'event_type' => $event[1],
                    'user' => ['DOMAIN\\jdoe', 'DOMAIN\\asmith', 'WS-001\\admin'][$i % 3],
                    'computer' => 'WS-001',
                    'script_name' => $event[2],
                    'command_line' => $event[3],
                    'result' => $event[4],
                    'script_block_id' => $event[5],
                    'total_blocks' => $event[6],
                    'path' => $event[7]
                ];
            }
            break;
            
        case 'sysmon':
            // Sysmon logs with event-specific fields
            $headers = ['timestamp', 'event_id', 'user', 'computer', 'process_name', 'process_id', 'image', 'command_line', 'parent_process', 'parent_command_line', 'logon_guid', 'hash', 'target_filename', 'destination_ip', 'destination_port'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 25; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 1200));
                $eventId = [1, 3, 5, 7, 10, 11, 13][$i % 7];
                
                $baseLog = [
                    'timestamp' => $time,
                    'event_id' => $eventId,
                    'user' => ['DOMAIN\\jdoe', 'SYSTEM', 'WS-001\\user1'][$i % 3],
                    'computer' => 'WS-001',
                    'process_name' => ['explorer.exe', 'svchost.exe', 'chrome.exe', 'powershell.exe', 'notepad.exe'][$i % 5],
                    'process_id' => 1000 + $i,
                    'image' => [
                        'C:\\Windows\\explorer.exe',
                        'C:\\Windows\\System32\\svchost.exe',
                        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                        'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
                        'C:\\Windows\\System32\\notepad.exe'
                    ][$i % 5],
                ];
                
                // Add event-specific fields
                switch ($eventId) {
                    case 1: // Process creation
                        $baseLog['command_line'] = [
                            'explorer.exe',
                            'svchost.exe -k netsvcs',
                            'chrome.exe --type=renderer',
                            'powershell.exe -Command Get-Process',
                            'notepad.exe C:\\temp\\file.txt'
                        ][$i % 5];
                        $baseLog['parent_process'] = ['services.exe', 'services.exe', 'explorer.exe', 'explorer.exe', 'explorer.exe'][$i % 5];
                        $baseLog['parent_command_line'] = 'userinit.exe';
                        $baseLog['logon_guid'] = '{' . substr(md5($i), 0, 8) . '-' . substr(md5($i), 8, 4) . '-' . substr(md5($i), 12, 4) . '-' . substr(md5($i), 16, 4) . '-' . substr(md5($i), 20, 12) . '}';
                        $baseLog['hash'] = 'SHA256=' . substr(hash('sha256', $i . 'process'), 0, 64);
                        break;
                        
                    case 3: // Network connection
                        $baseLog['destination_ip'] = ['8.8.8.8', '192.168.1.10', '10.1.1.100', '172.16.1.50'][$i % 4];
                        $baseLog['destination_port'] = [80, 443, 53, 3389][$i % 4];
                        $baseLog['command_line'] = $baseLog['image'];
                        break;
                        
                    case 5: // Process terminated
                        $baseLog['command_line'] = $baseLog['image'];
                        break;
                        
                    case 7: // Image loaded
                        $baseLog['image_loaded'] = ['kernel32.dll', 'user32.dll', 'ntdll.dll', 'advapi32.dll'][$i % 4];
                        break;
                        
                    case 10: // Process accessed
                        $baseLog['call_trace'] = 'C:\\Windows\\SYSTEM32\\ntdll.dll+C6D7';
                        $baseLog['source_process'] = 'lsass.exe';
                        break;
                        
                    case 11: // File created
                        $baseLog['target_filename'] = ['C:\\temp\\file' . $i . '.txt', 'C:\\Users\\Public\\doc' . $i . '.doc', 'C:\\Windows\\Temp\\tmp' . $i . '.tmp'][$i % 3];
                        break;
                        
                    case 13: // Registry value set
                        $baseLog['target_object'] = ['HKLM\\Software\\Microsoft\\Windows\\CurrentVersion\\Run\\App' . $i, 'HKCU\\Software\\Microsoft\\Windows\\CurrentVersion\\Run\\Startup' . $i][$i % 2];
                        $baseLog['details'] = 'String: C:\\Program Files\\App' . $i . '\\app.exe';
                        break;
                }
                
                $data[] = $baseLog;
            }
            break;
            
        case 'security':
            // Security logs with different Event IDs and their specific fields
            $headers = ['timestamp', 'event_id', 'log_name', 'user', 'computer', 'source_address', 'logon_type', 'result', 'logon_process', 'auth_package', 'target_user', 'target_domain', 'session_id', 'process_name'];
            $data[] = array_combine($headers, $headers);
            
            $securityEvents = [
                [4624, 'Success', 2, 'User32', 'Negotiate', '', '', rand(1000, 9999), 'explorer.exe'], // Logon
                [4625, 'Failure', 3, 'NtLmSsp', 'NTLM', '', '', 0, ''], // Failed logon
                [4672, 'Success', 2, 'Advapi', 'Negotiate', 'SYSTEM', 'NT AUTHORITY', rand(1000, 9999), 'services.exe'], // Special privileges
                [4648, 'Success', 5, 'Advapi', 'Negotiate', '', '', rand(1000, 9999), 'cmd.exe'], // Explicit credentials
                [4732, 'Success', 0, 'SAM', '', 'Backup Operators', 'BUILTIN', 0, ''], // Group membership
                [4720, 'Success', 0, 'SAM', '', 'jdoe', 'DOMAIN', 0, ''], // User account created
                [4738, 'Success', 0, 'SAM', '', 'WS-001$', 'DOMAIN', 0, ''] // User account changed
            ];
            
            for ($i = 0; $i < 20; $i++) {
                $event = $securityEvents[$i % count($securityEvents)];
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 1500));
                $data[] = [
                    'timestamp' => $time,
                    'event_id' => $event[0],
                    'log_name' => 'Security',
                    'user' => ['DOMAIN\\jdoe', 'Unknown', 'DOMAIN\\admin', 'SYSTEM', 'WS-001$'][$i % 5],
                    'computer' => 'WS-001',
                    'source_address' => ['192.168.1.45', '10.1.1.100', '192.168.1.45', '::1', 'fe80::1234'][$i % 5],
                    'logon_type' => $event[2],
                    'result' => $event[1],
                    'logon_process' => $event[3],
                    'auth_package' => $event[4],
                    'target_user' => $event[5],
                    'target_domain' => $event[6],
                    'session_id' => $event[7],
                    'process_name' => $event[8]
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
            $headers = ['timestamp', 'server_ip', 'method', 'uri_stem', 'uri_query', 'port', 'username', 'client_ip', 'user_agent', 'status', 'substatus', 'win32_status', 'bytes_sent', 'bytes_received', 'time_taken'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 900));
                $data[] = [
                    'timestamp' => $time,
                    'server_ip' => '192.168.1.10',
                    'method' => ['GET', 'POST', 'PUT', 'DELETE', 'HEAD'][$i % 5],
                    'uri_stem' => ['/login.aspx', '/api/users', '/images/logo.png', '/admin/config', '/api/data'][$i % 5],
                    'uri_query' => ['', 'user=admin&pass=test', 'id=123', 'action=delete', 'search=test'][$i % 5],
                    'port' => [80, 443][$i % 2],
                    'username' => ['-', 'admin', 'user1', '-', 'api_user'][$i % 5],
                    'client_ip' => ['203.0.113.45', '198.51.100.23', '192.168.1.150', '203.0.113.67', '10.1.2.100'][$i % 5],
                    'user_agent' => [
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
                        'curl/7.68.0',
                        'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0)',
                        'PostmanRuntime/7.26.0'
                    ][$i % 5],
                    'status' => [200, 401, 404, 500, 301, 304][$i % 6],
                    'substatus' => [0, 2, 0, 0, 0, 0][$i % 6],
                    'win32_status' => [0, 5, 2, 1, 0, 0][$i % 6],
                    'bytes_sent' => [512, 1024, 2048, 512, 4096][$i % 5],
                    'bytes_received' => [0, 256, 512, 0, 1024][$i % 5],
                    'time_taken' => [12, 45, 8, 120, 23][$i % 5]
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
            $headers = ['timestamp', 'type', 'pid', 'uid', 'auid', 'ses', 'subj', 'op', 'success', 'exe', 'hostname', 'addr', 'terminal', 'res', 'key', 'comm', 'path'];
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
                    'exe' => ['/usr/sbin/sshd', '/usr/bin/vim', '/bin/mkdir', '/usr/bin/curl', '/bin/bash'][$i % 5],
                    'hostname' => 'linux-srv01',
                    'addr' => ['192.168.1.45', NULL, '10.1.1.100', '192.168.1.100'][$i % 4],
                    'terminal' => ['pts/0', 'pts/1', 'tty1', 'ssh'][$i % 4],
                    'res' => ['success', 'failed', 'denied'][$i % 3],
                    'key' => ['sshd', 'file-access', 'user-cmd'][$i % 3],
                    'comm' => ['sshd', 'vim', 'mkdir', 'curl', 'bash'][$i % 5],
                    'path' => ['/etc/passwd', '/home/user/file.txt', '/tmp/tempfile', '/var/log/auth.log'][$i % 4]
                ];
            }
            break;
            
        case 'cron':
            $headers = ['timestamp', 'user', 'command', 'pid', 'hostname', 'cron_job'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 3600));
                $data[] = [
                    'timestamp' => $time,
                    'user' => ['root', 'www-data', 'backup', 'mysql'][$i % 4],
                    'command' => [
                        '/usr/lib/php/sessionclean',
                        '/opt/backup/run-backup.sh',
                        '/usr/bin/php /var/www/html/cron.php',
                        '/usr/bin/updatedb',
                        '/usr/sbin/logrotate'
                    ][$i % 5],
                    'pid' => 500 + $i,
                    'hostname' => 'linux-srv01',
                    'cron_job' => ['session-clean', 'nightly-backup', 'app-cron', 'updatedb', 'log-rotate'][$i % 5]
                ];
            }
            break;
            
        case 'application':
            $headers = ['timestamp', 'level', 'component', 'message', 'pid', 'hostname', 'thread', 'logger', 'exception'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 800));
                $data[] = [
                    'timestamp' => $time,
                    'level' => ['INFO', 'WARNING', 'ERROR', 'DEBUG', 'CRITICAL'][$i % 5],
                    'component' => ['webapp', 'database', 'auth', 'api', 'cache'][$i % 5],
                    'message' => [
                        'User login successful',
                        'Database connection timeout',
                        'Failed authentication attempt',
                        'API rate limit exceeded',
                        'Cache initialization failed'
                    ][$i % 5],
                    'pid' => 800 + $i,
                    'hostname' => 'linux-srv01',
                    'thread' => ['main', 'worker-1', 'worker-2', 'scheduler'][$i % 4],
                    'logger' => ['com.app.web.Auth', 'com.app.db.Connection', 'com.app.api.Controller', 'com.app.cache.Manager'][$i % 4],
                    'exception' => ['', 'SQLException: Connection refused', 'AuthException: Invalid credentials', '', 'CacheException: Out of memory'][$i % 5]
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
            $headers = ['timestamp', 'client_ip', 'query_name', 'query_type', 'response_code', 'server', 'zone', 'record_type', 'record_data', 'ttl'];
            $data[] = array_combine($headers, $headers);
            
            for ($i = 0; $i < 15; $i++) {
                $time = date('Y-m-d H:i:s', $baseTime + ($i * 600));
                $data[] = [
                    'timestamp' => $time,
                    'client_ip' => ['192.168.1.45', '192.168.1.100', '10.1.1.50', '192.168.1.200'][$i % 4],
                    'query_name' => ['google.com', 'malicious-domain.com', 'internal-app.corp.local', 'update.microsoft.com', 'dc01.corp.local'][$i % 5],
                    'query_type' => ['A', 'AAAA', 'MX', 'TXT', 'SRV', 'PTR'][$i % 6],
                    'response_code' => ['NOERROR', 'NXDOMAIN', 'REFUSED', 'SERVFAIL'][$i % 4],
                    'server' => 'dc-01.corp.local',
                    'zone' => ['corp.local', '.', '1.168.192.in-addr.arpa'][$i % 3],
                    'record_type' => ['A', 'MX', 'TXT', 'SRV'][$i % 4],
                    'record_data' => ['192.168.1.10', '10 mail.corp.local', '"v=spf1 include:corp.com ~all"', '0 10 5060 sip.corp.local'][$i % 4],
                    'ttl' => [300, 3600, 86400, 60][$i % 4]
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
    $headers = ['timestamp', 'action', 'protocol', 'src_ip', 'dest_ip', 'src_port', 'dest_port', 'size', 'tcp_flags', 'rule_id', 'interface', 'service', 'duration'];
    $data[] = array_combine($headers, $headers);
    
    for ($i = 0; $i < 15; $i++) {
        $time = date('Y-m-d H:i:s', $baseTime + ($i * 500));
        $data[] = [
            'timestamp' => $time,
            'action' => ['DROP', 'ALLOW', 'REJECT'][$i % 3],
            'protocol' => ['TCP', 'UDP', 'ICMP'][$i % 3],
            'src_ip' => ['198.51.100.67', '192.168.1.45', '203.0.113.89', '10.1.1.100', '172.16.1.200'][$i % 5],
            'dest_ip' => ['192.168.1.10', '8.8.8.8', '192.168.1.20', '192.168.1.30', '10.2.2.100'][$i % 5],
            'src_port' => [54321, 12345, 4444, 5555, 3389][$i % 5],
            'dest_port' => [22, 53, 161, 443, 80, 25][$i % 6],
            'size' => [60, 512, 120, 1500, 84][$i % 5],
            'tcp_flags' => ['S', 'PA', 'RA', 'FA', NULL][$i % 5],
            'rule_id' => [4001, 1002, 4003, 2001, 3005][$i % 5],
            'interface' => ['eth0', 'eth1', 'wan0', 'lan0'][$i % 4],
            'service' => ['ssh', 'dns', 'snmp', 'https', 'http', 'smtp'][$i % 6],
            'duration' => [0, 5, 120, 30, 2][$i % 5]
        ];
    }
    
    return $data;
}

function generateIDSLogs() {
    $data = [];
    $baseTime = time() - 86400;
    $headers = ['timestamp', 'alert_type', 'protocol', 'src_ip', 'dest_ip', 'src_port', 'dest_port', 'alert_severity', 'alert_message', 'signature_id', 'classification', 'priority', 'flow_id', 'category'];
    $data[] = array_combine($headers, $headers);
    
    for ($i = 0; $i < 15; $i++) {
        $time = date('Y-m-d H:i:s', $baseTime + ($i * 700));
        $data[] = [
            'timestamp' => $time,
            'alert_type' => [
                'ET SCAN Potential SSH Scan',
                'ET POLICY MS Terminal Server traffic',
                'ET TROJAN Possible Malware Connection',
                'ET WEB_SERVER Possible SQL Injection',
                'ET CINS Active Threat Intelligence Poor Reputation IP'
            ][$i % 5],
            'protocol' => ['TCP', 'UDP', 'ICMP'][$i % 3],
            'src_ip' => ['198.51.100.23', '203.0.113.50', '192.168.1.150', '198.51.100.89', '10.5.6.100'][$i % 5],
            'dest_ip' => ['192.168.1.10', '203.0.113.50', '192.168.1.20', '192.168.1.30', '192.168.1.40'][$i % 5],
            'src_port' => [54321, 3389, 4444, 8080, 1337][$i % 5],
            'dest_port' => [22, 54321, 80, 443, 3389][$i % 5],
            'alert_severity' => [2, 3, 1, 2, 3][$i % 5],
            'alert_message' => [
                'GPL ATTACK_RESPONSE id check returned root',
                'GPL RPC sadmind query with root credentials attempt',
                'ET TROJAN Metasploit payload detected',
                'ET WEB_SERVER SQL Injection SELECT UNION',
                'ET CINS Active Threat Intelligence Poor Reputation IP Group 1'
            ][$i % 5],
            'signature_id' => [2100498, 2003340, 2024411, 2013023, 2001289][$i % 5],
            'classification' => [
                'Attempted Information Leak',
                'Potential Corporate Privacy Violation',
                'A Network Trojan was detected',
                'Web Application Attack',
                'Known Bad IP'
            ][$i % 5],
            'priority' => [1, 2, 3, 1, 2][$i % 5],
            'flow_id' => rand(1000000, 9999999),
            'category' => ['scan', 'policy', 'trojan', 'web', 'reputation'][$i % 5]
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
