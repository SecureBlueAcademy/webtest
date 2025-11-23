<?php
// alerts.php
require __DIR__ . '/config.php';
require_login();

$user = current_user();
$username = $user['username'];
$message = '';

// ---------- Handle actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']   ?? '';
    $alertId  = $_POST['alert_id'] ?? '';

    if ($action === 'acknowledge') {
        update_alert($alertId, function (&$a) use ($username) {
            if ($a['status'] === 'pending' &&
                mb_strtolower($a['assigned_to']) === mb_strtolower($username)) {
                $a['status'] = 'acknowledged';
                $a['updated_at'] = date('Y-m-d H:i:s');
            }
        });
        $message = 'Alert acknowledged.';
    }

    if ($action === 'save_notes') {
        $verdict = $_POST['verdict'] ?? '';
        $notes   = trim($_POST['notes'] ?? '');
        $iocTypes  = $_POST['ioc_type']  ?? [];
        $iocValues = $_POST['ioc_value'] ?? [];

        $verdict = in_array($verdict, ['fp','tp'], true) ? $verdict : '';

        $iocs = [];
        for ($i = 0; $i < count($iocTypes); $i++) {
            $type  = trim($iocTypes[$i] ?? '');
            $value = trim($iocValues[$i] ?? '');
            if ($type !== '' && $value !== '') {
                $iocs[] = ['type' => $type, 'value' => $value];
            }
        }

        $alert = find_alert_by_id($alertId);
        if ($alert && mb_strtolower($alert['assigned_to']) === mb_strtolower($username)) {
            update_alert($alertId, function (&$a) use ($verdict, $notes, $iocs) {
                if ($a['status'] === 'acknowledged' || $a['status'] === 'pending') {
                    $a['status']    = 'acknowledged';
                    $a['verdict']   = $verdict;
                    $a['notes']     = $notes;
                    $a['iocs_json'] = json_encode($iocs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $a['updated_at'] = date('Y-m-d H:i:s');
                }
            });

            // Update submission file (overwrite existing entry)
            update_submission(
                $username,
                $alertId,
                $alert ? $alert['title'] : '',
                $verdict,
                $iocs,
                $notes
            );

            $message = 'Notes saved for this alert.';
        }
    }

    if ($action === 'close_alert') {
        $alert = find_alert_by_id($alertId);
        if ($alert
            && mb_strtolower($alert['assigned_to']) === mb_strtolower($username)
            && !empty($alert['notes'])) {
            update_alert($alertId, function (&$a) {
                if ($a['status'] !== 'closed') {
                    $a['status'] = 'closed';
                    $a['updated_at'] = date('Y-m-d H:i:s');
                }
            });
            $message = 'Alert closed.';
        } else {
            $message = 'Cannot close alert. Please add notes first.';
        }
    }
}

// ---------- Load alerts for this user ----------
$allAlerts = load_alerts();
$myAlerts  = array_values(array_filter($allAlerts, function ($a) use ($username) {
    return mb_strtolower($a['assigned_to']) === mb_strtolower($username);
}));

$pending = $ack = $closed = [];

foreach ($myAlerts as $a) {
    if ($a['status'] === 'pending') {
        $pending[] = $a;
    } elseif ($a['status'] === 'acknowledged') {
        $ack[] = $a;
    } elseif ($a['status'] === 'closed') {
        $closed[] = $a;
    }
}

// Get filter from URL
$filter = $_GET['filter'] ?? 'pending';
$activeAlertId = $_GET['alert_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CYBRIXEN SOC - Alerts</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .alerts-container {
            margin: 1rem;
            height: calc(100vh - 120px);
        }

        .alert-tabs {
            display: flex;
            border-bottom: 1px solid #1a2452;
            margin-bottom: 1.5rem;
            background: var(--panel);
            border-radius: var(--radius) var(--radius) 0 0;
            overflow: hidden;
        }

        .alert-tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
            font-weight: 600;
            flex: 1;
            text-align: center;
        }

        .alert-tab:hover {
            background: #1a2246;
            color: var(--text);
        }

        .alert-tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
            background: rgba(102, 217, 239, 0.1);
        }

        .alert-tab-badge {
            background: var(--muted);
            color: var(--panel);
            padding: 0.2rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }

        .alert-tab.active .alert-tab-badge {
            background: var(--accent);
            color: #0a0f22;
        }

        .alert-content {
            display: none;
            background: var(--panel);
            border: 1px solid #1a2452;
            border-radius: 0 0 var(--radius) var(--radius);
            overflow: hidden;
        }

        .alert-content.active {
            display: block;
        }

        .alert-list {
            max-height: calc(100vh - 250px);
            overflow-y: auto;
            padding: 1rem;
        }

        .alert-item {
            background: #0f1834;
            border: 1px solid #1a2452;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .alert-item:hover {
            background: #1a2246;
            border-color: var(--accent);
            transform: translateX(5px);
        }

        .alert-item.active {
            border-color: var(--accent);
            background: rgba(102, 217, 239, 0.1);
            box-shadow: 0 5px 15px rgba(102, 217, 239, 0.2);
        }

        .alert-title {
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--accent);
            font-size: 1.1rem;
        }

        .alert-meta {
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .alert-description {
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
            color: var(--text);
        }

        .alert-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .alert-actions .btn {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            border-radius: var(--radius);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 15, 34, 0.95);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex !important;
        }

        .modal-content {
            background: var(--panel);
            border: 1px solid #1a2452;
            border-radius: var(--radius);
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { 
                transform: scale(0.9) translateY(-20px);
                opacity: 0;
            }
            to { 
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #1a2452;
            background: #0f1834;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: 60vh;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: start;
        }

        .form-row label {
            color: var(--muted);
            font-weight: 600;
            padding-top: 0.5rem;
        }

        .form-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #1a2452;
        }

        /* IOC table styles */
        #ioc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0.5rem;
        }

        #ioc-table th,
        #ioc-table td {
            padding: 0.5rem;
            border: 1px solid #1a2452;
        }

        #ioc-table th {
            background: #0f1834;
            color: var(--accent);
            font-size: 0.8rem;
        }

        #ioc-table select,
        #ioc-table input {
            width: 100%;
            padding: 0.4rem;
            background: #0b122b;
            border: 1px solid #243067;
            border-radius: 0.3rem;
            color: var(--text);
        }

        /* Notes textarea */
        #notes-textarea {
            width: 100%;
            min-height: 120px;
            background: #0b122b;
            border: 1px solid #243067;
            border-radius: 0.5rem;
            color: var(--text);
            padding: 0.75rem;
            font-family: inherit;
            resize: vertical;
        }

        .alert-details {
            background: #0b122b;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #1a2452;
        }

        .detail-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #1a2452;
        }

        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: var(--accent);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .severity {
            padding: 0.3rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .severity.critical {
            background: rgba(255, 107, 107, 0.2);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .severity.high {
            background: rgba(255, 163, 102, 0.2);
            color: #ffa366;
            border: 1px solid #ffa366;
        }

        .severity.medium {
            background: rgba(255, 207, 91, 0.2);
            color: var(--warning);
            border: 1px solid var(--warning);
        }

        .severity.low {
            background: rgba(143, 255, 168, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }
    </style>
</head>
<body>
<div class="app">
    <?php render_sidebar('alerts'); ?>

    <div class="main-content">
        <?php render_header('Assigned Alerts'); ?>

        <?php if ($message): ?>
            <div class="notification success">
                <div class="notification-content">
                    <span><?= sanitize($message) ?></span>
                    <button class="notification-close" onclick="this.parentNode.parentNode.remove()">×</button>
                </div>
            </div>
        <?php endif; ?>

        <div class="alerts-container">
            <!-- Alert Tabs -->
            <div class="alert-tabs">
                <button class="alert-tab <?= $filter === 'pending' ? 'active' : '' ?>" 
                        onclick="switchTab('pending')">
                    Pending
                    <span class="alert-tab-badge"><?= count($pending) ?></span>
                </button>
                <button class="alert-tab <?= $filter === 'acknowledged' ? 'active' : '' ?>" 
                        onclick="switchTab('acknowledged')">
                    Investigating
                    <span class="alert-tab-badge"><?= count($ack) ?></span>
                </button>
                <button class="alert-tab <?= $filter === 'closed' ? 'active' : '' ?>" 
                        onclick="switchTab('closed')">
                    Closed
                    <span class="alert-tab-badge"><?= count($closed) ?></span>
                </button>
            </div>

            <!-- Pending Alerts -->
            <div class="alert-content <?= $filter === 'pending' ? 'active' : '' ?>" id="pending-content">
                <div class="alert-list">
                    <?php if (empty($pending)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <h3>No Pending Alerts</h3>
                            <p>All clear! No alerts are waiting for acknowledgment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending as $alert): ?>
                            <div class="alert-item <?= $activeAlertId == $alert['id'] ? 'active' : '' ?>" 
                                 onclick="selectAlert(<?= $alert['id'] ?>, 'pending')">
                                <div class="alert-title"><?= sanitize($alert['title']) ?></div>
                                <div class="alert-meta">
                                    <span class="severity <?= $alert['severity'] ?>"><?= ucfirst($alert['severity']) ?></span>
                                    <span>•</span>
                                    <span><?= sanitize($alert['source']) ?></span>
                                    <span>•</span>
                                    <span><?= sanitize($alert['created_at']) ?></span>
                                </div>
                                <div class="alert-description">
                                    <?= get_alert_description($alert) ?>
                                </div>
                                <div class="alert-actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                        <input type="hidden" name="action" value="acknowledge">
                                        <button type="submit" class="btn primary">Acknowledge</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acknowledged Alerts -->
            <div class="alert-content <?= $filter === 'acknowledged' ? 'active' : '' ?>" id="acknowledged-content">
                <div class="alert-list">
                    <?php if (empty($ack)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">🔍</div>
                            <h3>No Alerts Under Investigation</h3>
                            <p>No alerts are currently being investigated.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ack as $alert): ?>
                            <div class="alert-item <?= $activeAlertId == $alert['id'] ? 'active' : '' ?>" 
                                 onclick="selectAlert(<?= $alert['id'] ?>, 'acknowledged')">
                                <div class="alert-title"><?= sanitize($alert['title']) ?></div>
                                <div class="alert-meta">
                                    <span class="severity <?= $alert['severity'] ?>"><?= ucfirst($alert['severity']) ?></span>
                                    <span>•</span>
                                    <span><?= sanitize($alert['source']) ?></span>
                                    <span>•</span>
                                    <span><?= sanitize($alert['updated_at']) ?></span>
                                </div>
                                <div class="alert-description">
                                    <?= get_alert_description($alert) ?>
                                </div>
                                <div class="alert-actions">
                                    <button type="button" class="btn" onclick="event.stopPropagation(); openNotesModal(
                                        '<?= $alert['id'] ?>',
                                        '<?= sanitize($alert['title']) ?>',
                                        '<?= $alert['verdict'] ?>',
                                        `<?= sanitize($alert['iocs_json'] ?: '[]') ?>`,
                                        `<?= sanitize($alert['notes'] ?: '') ?>`
                                    )">Notes</button>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                        <input type="hidden" name="action" value="close_alert">
                                        <button type="submit" class="btn success" <?= empty($alert['notes']) ? 'disabled' : '' ?>>Close</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Closed Alerts -->
            <div class="alert-content <?= $filter === 'closed' ? 'active' : '' ?>" id="closed-content">
                <div class="alert-list">
                    <?php if (empty($closed)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <h3>No Closed Alerts</h3>
                            <p>No alerts have been closed yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($closed as $alert): ?>
                            <div class="alert-item <?= $activeAlertId == $alert['id'] ? 'active' : '' ?>" 
                                 onclick="selectAlert(<?= $alert['id'] ?>, 'closed')">
                                <div class="alert-title"><?= sanitize($alert['title']) ?></div>
                                <div class="alert-meta">
                                    <span class="severity <?= $alert['severity'] ?>"><?= ucfirst($alert['severity']) ?></span>
                                    <span>•</span>
                                    <span><?= sanitize($alert['source']) ?></span>
                                    <span>•</span>
                                    <span><?= sanitize($alert['updated_at']) ?></span>
                                </div>
                                <div class="alert-description">
                                    <?= get_alert_description($alert) ?>
                                </div>
                                <div class="alert-meta">
                                    <strong>Verdict:</strong> 
                                    <?= $alert['verdict'] === 'tp' ? 
                                        '<span style="color: var(--success);">True Positive</span>' : 
                                        ($alert['verdict'] === 'fp' ? 
                                        '<span style="color: var(--danger);">False Positive</span>' : 
                                        '<span style="color: var(--muted);">Not set</span>') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alert Details Panel -->
        <?php if ($activeAlertId): ?>
            <?php 
            $activeAlert = find_alert_by_id($activeAlertId);
            if ($activeAlert && mb_strtolower($activeAlert['assigned_to']) === mb_strtolower($username)):
            ?>
                <div class="alert-details">
                    <h4>Alert Details - <?= sanitize($activeAlert['title']) ?></h4>
                    <div class="detail-item">
                        <div class="detail-label">Description</div>
                        <div class="detail-value"><?= nl2br(sanitize($activeAlert['description'])) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Source Details</div>
                        <div class="detail-value">
                            <?= get_alert_source_details($activeAlert) ?>
                        </div>
                    </div>
                    <?php if (!empty($activeAlert['iocs_json'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Indicators of Compromise</div>
                            <div class="detail-value">
                                <?php 
                                $iocs = json_decode($activeAlert['iocs_json'], true);
                                if (is_array($iocs)): 
                                    foreach ($iocs as $ioc): ?>
                                        <div style="margin-bottom: 0.5rem;">
                                            <strong><?= $ioc['type'] ?>:</strong> 
                                            <code style="background: #1a2246; padding: 0.2rem 0.4rem; border-radius: 0.3rem;"><?= $ioc['value'] ?></code>
                                        </div>
                                    <?php endforeach; 
                                endif; 
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($activeAlert['notes'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Analyst Notes</div>
                            <div class="detail-value"><?= nl2br(sanitize($activeAlert['notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Notes Modal -->
<div class="modal" id="notes-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="notes-modal-title">Alert Notes</h3>
            <button class="notification-close" onclick="closeNotesModal()">×</button>
        </div>
        <div class="modal-body">
            <form method="post" id="notes-form">
                <input type="hidden" name="action" value="save_notes">
                <input type="hidden" name="alert_id" id="notes-alert-id" value="">

                <div class="form-row">
                    <label>Verdict</label>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem;">
                            <input type="radio" name="verdict" value="tp" style="margin-right: 0.5rem;">
                            <span style="color: var(--success);">True Positive</span>
                        </label>
                        <label style="display: block;">
                            <input type="radio" name="verdict" value="fp" style="margin-right: 0.5rem;">
                            <span style="color: var(--danger);">False Positive</span>
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <label>IoCs</label>
                    <div>
                        <table id="ioc-table">
                            <thead>
                            <tr>
                                <th style="width: 140px;">Type</th>
                                <th>Value</th>
                                <th style="width: 60px;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <!-- rows inserted via JS -->
                            </tbody>
                        </table>
                        <button type="button" class="btn" style="margin-top: 0.5rem;" onclick="addIocRow()">
                            + Add IoC
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <label>Analyst Notes</label>
                    <div>
                        <textarea name="notes" id="notes-textarea" placeholder="Enter your analysis and findings here..."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeNotesModal()">Cancel</button>
                    <button type="submit" class="btn primary">Submit Notes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    window.location.href = 'alerts.php?filter=' + tab;
}

function selectAlert(alertId, filter) {
    window.location.href = 'alerts.php?filter=' + filter + '&alert_id=' + alertId;
}

function openNotesModal(alertId, title, verdict, iocsJsonStr, notes) {
    console.log('Opening notes modal for alert:', alertId);
    
    // Set alert ID
    document.getElementById('notes-alert-id').value = alertId;
    
    // Set modal title
    document.getElementById('notes-modal-title').textContent = 'Notes for Alert #' + alertId + ' - ' + title;

    // Reset and set verdict radios
    var radios = document.querySelectorAll('#notes-form input[name="verdict"]');
    radios.forEach(function (r) { 
        r.checked = false; 
    });
    
    if (verdict === 'tp' || verdict === 'fp') {
        var targetRadio = document.querySelector('#notes-form input[name="verdict"][value="' + verdict + '"]');
        if (targetRadio) {
            targetRadio.checked = true;
        }
    }

    // Reset IoC table
    var tbody = document.querySelector('#ioc-table tbody');
    tbody.innerHTML = '';
    
    var iocs = [];
    try {
        // Clean the JSON string - remove backticks and sanitize
        iocsJsonStr = iocsJsonStr.replace(/`/g, '');
        iocs = JSON.parse(iocsJsonStr || '[]');
        if (!Array.isArray(iocs)) iocs = [];
    } catch (e) {
        console.error('Error parsing IoCs:', e);
        iocs = [];
    }

    // Add IoC rows
    if (iocs.length === 0) {
        addIocRow(); // Add one empty row by default
    } else {
        iocs.forEach(function (ioc) {
            addIocRow(ioc.type || '', ioc.value || '');
        });
    }

    // Set notes
    var notesTextarea = document.getElementById('notes-textarea');
    if (notesTextarea) {
        notesTextarea.value = notes.replace(/`/g, '') || '';
    }

    // Show modal
    var modal = document.getElementById('notes-modal');
    if (modal) {
        modal.classList.add('active');
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
    }
}

function closeNotesModal() {
    var modal = document.getElementById('notes-modal');
    if (modal) {
        modal.classList.remove('active');
        // Restore body scroll
        document.body.style.overflow = '';
    }
}

function addIocRow(typeVal, valueVal) {
    var tbody = document.querySelector('#ioc-table tbody');
    if (!tbody) {
        console.error('IoC table body not found');
        return;
    }

    var tr = document.createElement('tr');

    // Type column with dropdown
    var tdType = document.createElement('td');
    var select = document.createElement('select');
    select.name = 'ioc_type[]';
    select.style.width = '100%';
    
    var options = [
        { value: '', text: 'Select Type' },
        { value: 'url', text: 'URL' },
        { value: 'hash', text: 'Hash' },
        { value: 'IPAddress', text: 'IP Address' },
        { value: 'domain', text: 'Domain' }
    ];
    
    options.forEach(function (opt) {
        var option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.text;
        if (opt.value === typeVal) option.selected = true;
        select.appendChild(option);
    });
    
    tdType.appendChild(select);
    tr.appendChild(tdType);

    // Value column
    var tdVal = document.createElement('td');
    var input = document.createElement('input');
    input.type = 'text';
    input.name = 'ioc_value[]';
    input.value = valueVal || '';
    input.placeholder = 'Enter IoC value...';
    input.style.width = '100%';
    tdVal.appendChild(input);
    tr.appendChild(tdVal);

    // Actions column
    var tdAct = document.createElement('td');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn danger';
    btn.textContent = '×';
    btn.style.padding = '0.2rem 0.4rem';
    btn.style.fontSize = '0.9rem';
    btn.onclick = function () {
        tr.remove();
        // Ensure at least one row exists
        var rows = document.querySelectorAll('#ioc-table tbody tr');
        if (rows.length === 0) {
            addIocRow();
        }
    };
    tdAct.appendChild(btn);
    tr.appendChild(tdAct);

    tbody.appendChild(tr);
}

// Close modal when clicking outside or pressing ESC
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('notes-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeNotesModal();
            }
        });
    }
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeNotesModal();
        }
    });
    
    // Add initial IoC row when page loads
    addIocRow();
});

// Handle form submission
document.getElementById('notes-form')?.addEventListener('submit', function(e) {
    // Form will submit normally via PHP
    console.log('Submitting notes form');
    closeNotesModal();
});
</script>
</body>
</html>