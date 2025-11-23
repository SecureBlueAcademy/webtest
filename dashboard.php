<?php
// dashboard.php
require __DIR__ . '/config.php';
require_login();

$alerts = load_alerts();
$user   = current_user();

// Counts
$total = count($alerts);
$pending = $ack = $closed = 0;
$myAssigned = 0;
$critical = $high = $medium = $low = 0;

foreach ($alerts as $a) {
    if ($a['status'] === 'pending') {
        $pending++;
    } elseif ($a['status'] === 'acknowledged') {
        $ack++;
    } elseif ($a['status'] === 'closed') {
        $closed++;
    }
    
    if (mb_strtolower($a['assigned_to']) === mb_strtolower($user['username'])) {
        $myAssigned++;
    }
    
    // Count by severity
    switch ($a['severity']) {
        case 'critical': $critical++; break;
        case 'high': $high++; break;
        case 'medium': $medium++; break;
        case 'low': $low++; break;
    }
}

// Recent activity (last 5 alerts)
$recentAlerts = array_slice($alerts, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CYBRIXEN SOC - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .interactive-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
            box-shadow: 0 5px 15px rgba(102, 217, 239, 0.3);
        }
        
        .stat-card.active {
            border-color: var(--accent);
            background: rgba(102, 217, 239, 0.1);
        }
        
        .stat-trend {
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .recent-activity {
            background: var(--panel);
            border: 1px solid #1a2452;
            border-radius: var(--radius);
            padding: 1.5rem;
        }
        
        .activity-item {
            padding: 0.75rem;
            border-bottom: 1px solid #1a2452;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            background: #1a2246;
            transform: translateX(5px);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .activity-critical { background: rgba(255, 107, 107, 0.2); color: var(--danger); }
        .activity-high { background: rgba(255, 163, 102, 0.2); color: #ffa366; }
        .activity-medium { background: rgba(255, 207, 91, 0.2); color: var(--warning); }
        .activity-low { background: rgba(143, 255, 168, 0.2); color: var(--success); }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .activity-meta {
            font-size: 0.8rem;
            color: var(--muted);
        }
        
        .severity-breakdown {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .severity-item {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            border-radius: var(--radius);
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="app">
    <?php render_sidebar('dashboard'); ?>

    <div class="main-content">
        <?php render_header('SOC Overview'); ?>

        <div style="margin:1rem;">
            <div class="interactive-stats">
                <div class="stat-card" onclick="window.location='alerts.php'">
                    <div class="stat-label">Total Alerts</div>
                    <div class="stat-value"><?= $total ?></div>
                    <div class="stat-trend info">All security alerts</div>
                </div>
                <div class="stat-card" onclick="window.location='alerts.php?filter=my'">
                    <div class="stat-label">My Assigned</div>
                    <div class="stat-value"><?= $myAssigned ?></div>
                    <div class="stat-trend <?= $myAssigned > 0 ? 'negative' : 'positive' ?>">
                        <?= $myAssigned > 0 ? 'Requires attention' : 'All clear' ?>
                    </div>
                </div>
                <div class="stat-card" onclick="window.location='alerts.php?filter=pending'">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?= $pending ?></div>
                    <div class="stat-trend warning">Awaiting acknowledgment</div>
                </div>
                <div class="stat-card" onclick="window.location='alerts.php?filter=acknowledged'">
                    <div class="stat-label">Investigating</div>
                    <div class="stat-value"><?= $ack ?></div>
                    <div class="stat-trend info">Under investigation</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h3>Alert Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="severity-breakdown">
                            <div class="severity-item" style="background: rgba(255, 107, 107, 0.1); color: var(--danger);">
                                Critical: <?= $critical ?>
                            </div>
                            <div class="severity-item" style="background: rgba(255, 163, 102, 0.1); color: #ffa366;">
                                High: <?= $high ?>
                            </div>
                            <div class="severity-item" style="background: rgba(255, 207, 91, 0.1); color: var(--warning);">
                                Medium: <?= $medium ?>
                            </div>
                            <div class="severity-item" style="background: rgba(143, 255, 168, 0.1); color: var(--success);">
                                Low: <?= $low ?>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem;">
                            <p style="margin-bottom: 1rem;">
                                This dashboard provides a comprehensive view of your SOC workload and alert status.
                                Use the <strong>Alerts</strong> page to manage and investigate security incidents.
                            </p>
                            <ul style="margin-left: 1.5rem; font-size: 0.9rem;">
                                <li><strong>Pending</strong> – New alerts waiting to be acknowledged</li>
                                <li><strong>Acknowledged</strong> – Under investigation; add Notes and IoCs</li>
                                <li><strong>Closed</strong> – Investigation completed and documented</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="recent-activity">
                    <h3 style="margin-bottom: 1rem; color: var(--accent);">Recent Activity</h3>
                    <?php if (empty($recentAlerts)): ?>
                        <p style="color: var(--muted); font-size: 0.9rem;">No recent alerts</p>
                    <?php else: ?>
                        <?php foreach ($recentAlerts as $alert): ?>
                            <div class="activity-item" onclick="window.location='alerts.php?alert_id=<?= $alert['id'] ?>'">
                                <div class="activity-icon activity-<?= $alert['severity'] ?>">
                                    <?= $alert['severity'] === 'critical' ? '🔥' : 
                                         ($alert['severity'] === 'high' ? '⚠️' : 
                                         ($alert['severity'] === 'medium' ? '🔶' : '🔷')) ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?= sanitize($alert['title']) ?></div>
                                    <div class="activity-meta">
                                        <?= sanitize($alert['source']) ?> • <?= sanitize($alert['created_at']) ?>
                                    </div>
                                </div>
                                <span class="severity <?= $alert['severity'] ?>">
                                    <?= ucfirst($alert['severity']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function filterAlerts(type) {
    window.location = 'alerts.php?filter=' + type;
}

function showNotification(message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'notification info';
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '1000';
    
    notification.innerHTML = `
        <div class="notification-content">
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentNode.parentNode.remove()">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

// Add hover effects to stat cards
document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateY(0)';
            }
        });
    });
});
</script>
</body>
</html>