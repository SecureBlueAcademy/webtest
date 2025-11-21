// Shared application state and utilities
class SIEMApp {
  constructor() {
    this.sidebarCompact = localStorage.getItem('sidebarCompact') === 'true';
    this.currentPage = window.location.pathname.split('/').pop() || 'index.php';
    this.init();
  }

  init() { 
    this.initSidebar(); 
    this.initNavigation(); 
    this.updateActiveNav(); 
  }

  initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (this.sidebarCompact) sidebar.classList.add('compact');
    toggleBtn?.addEventListener('click', () => {
      this.sidebarCompact = !this.sidebarCompact;
      sidebar.classList.toggle('compact', this.sidebarCompact);
      localStorage.setItem('sidebarCompact', this.sidebarCompact);
    });
  }

  initNavigation() {
    document.querySelectorAll('.nav-item').forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        const page = item.dataset.page;
        if (page && page !== this.currentPage) window.location.href = page;
      });
    });
  }

  updateActiveNav() {
    document.querySelectorAll('.nav-item').forEach(item =>
      item.classList.toggle('active', item.dataset.page === this.currentPage)
    );
  }

  // Utilities
  formatDate(date) { 
    const d = new Date(date);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
  }
  
  escapeHtml(unsafe) {
    return String(unsafe)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }
  
  showModal(id) { 
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  }
  
  hideModal(id) { 
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  }

  // AJAX helper for PHP interactions
  async ajaxRequest(url, data = null, method = 'POST') {
    try {
      const options = {
        method: method,
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
      };

      if (data && method === 'POST') {
        const formData = new URLSearchParams();
        for (const key in data) {
          formData.append(key, data[key]);
        }
        options.body = formData;
      }

      const response = await fetch(url, options);
      return await response.json();
    } catch (error) {
      console.error('AJAX request failed:', error);
      return { success: false, error: 'Request failed' };
    }
  }

  // Notification system
  showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
      <div class="notification-content">
        <span class="notification-message">${message}</span>
        <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
      </div>
    `;

    // Add styles if not already added
    if (!document.querySelector('#notification-styles')) {
      const styles = document.createElement('style');
      styles.id = 'notification-styles';
      styles.textContent = `
        .notification {
          position: fixed;
          top: 20px;
          right: 20px;
          background: var(--panel);
          border: 1px solid #1a2452;
          border-radius: var(--radius);
          padding: 1rem;
          max-width: 400px;
          z-index: 10000;
          box-shadow: 0 4px 12px rgba(0,0,0,0.3);
          animation: slideIn 0.3s ease-out;
        }
        .notification.success { border-left: 4px solid var(--ok); }
        .notification.error { border-left: 4px solid var(--danger); }
        .notification.warning { border-left: 4px solid var(--warn); }
        .notification.info { border-left: 4px solid var(--accent); }
        .notification-content {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 1rem;
        }
        .notification-close {
          background: none;
          border: none;
          color: var(--muted);
          cursor: pointer;
          font-size: 1.2rem;
          padding: 0;
          width: 24px;
          height: 24px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .notification-close:hover {
          color: var(--text);
        }
        @keyframes slideIn {
          from { transform: translateX(100%); opacity: 0; }
          to { transform: translateX(0); opacity: 1; }
        }
      `;
      document.head.appendChild(styles);
    }

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove();
      }
    }, 5000);
  }

  // Data export functionality
  exportData(data, filename, type = 'text/csv') {
    const blob = new Blob([data], { type });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  }

  // Search and filter utilities
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // Table sorting
  sortTable(table, columnIndex, direction = 'asc') {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
      const aVal = a.cells[columnIndex].textContent.trim();
      const bVal = b.cells[columnIndex].textContent.trim();
      
      // Try to parse as numbers for numeric comparison
      const aNum = parseFloat(aVal);
      const bNum = parseFloat(bVal);
      
      if (!isNaN(aNum) && !isNaN(bNum)) {
        return direction === 'asc' ? aNum - bNum : bNum - aNum;
      }
      
      // String comparison
      return direction === 'asc' 
        ? aVal.localeCompare(bVal)
        : bVal.localeCompare(aVal);
    });
    
    // Remove existing rows
    while (tbody.firstChild) {
      tbody.removeChild(tbody.firstChild);
    }
    
    // Add sorted rows
    rows.forEach(row => tbody.appendChild(row));
  }

  // Date range utilities
  getDateRange(range) {
    const now = new Date();
    const from = new Date(now);
    
    switch (range) {
      case '1h':
        from.setHours(now.getHours() - 1);
        break;
      case '24h':
        from.setDate(now.getDate() - 1);
        break;
      case '7d':
        from.setDate(now.getDate() - 7);
        break;
      case '30d':
        from.setDate(now.getDate() - 30);
        break;
      default:
        from.setDate(now.getDate() - 1); // Default to 24h
    }
    
    return { from, to: now };
  }

  // Local storage management
  setPreference(key, value) {
    try {
      localStorage.setItem(`siem_${key}`, JSON.stringify(value));
    } catch (e) {
      console.warn('Local storage not available');
    }
  }

  getPreference(key, defaultValue = null) {
    try {
      const value = localStorage.getItem(`siem_${key}`);
      return value ? JSON.parse(value) : defaultValue;
    } catch (e) {
      return defaultValue;
    }
  }

  // Query syntax helper
  insertQueryToken(token) {
    const queryInput = document.querySelector('.query-syntax-input');
    if (queryInput) {
      const start = queryInput.selectionStart;
      const end = queryInput.selectionEnd;
      const currentValue = queryInput.value;
      
      queryInput.value = currentValue.substring(0, start) + token + currentValue.substring(end);
      queryInput.focus();
      queryInput.setSelectionRange(start + token.length, start + token.length);
    }
  }

  // Column management
  addColumn(column) {
    if (window.currentColumns && !window.currentColumns.includes(column)) {
      window.currentColumns.push(column);
      this.updateColumns();
    }
  }

  removeColumn(column) {
    if (window.currentColumns) {
      window.currentColumns = window.currentColumns.filter(col => col !== column);
      this.updateColumns();
    }
  }

  updateColumns() {
    if (window.currentColumns) {
      const params = new URLSearchParams(window.location.search);
      params.set('columns', window.currentColumns.join(','));
      window.location.href = '?' + params.toString();
    }
  }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => { 
  window.siemApp = new SIEMApp(); 
});

// Data generators for demo purposes (fallback if PHP data not available)
const DataGenerator = {
  generateLogs(count = 100) {
    const sources = ['firewall','ids','windows','linux','router','switch'];
    const severities = ['info','low','medium','high','critical'];
    const users = ['admin','user1','service_account','system','backup'];
    const hosts = ['server-01','workstation-02','db-01','web-01','gateway-01'];
    const types = ['login','file_access','network_scan','config_change','auth_failure'];

    const logs = [];
    const now = new Date();
    for (let i=0;i<count;i++){
      const hoursAgo = Math.floor(Math.random()*24*7);
      const timestamp = new Date(now - hoursAgo*3600000);
      logs.push({
        id:`log-${i}`,
        timestamp: timestamp.toISOString(),
        severity: severities[Math.floor(Math.random()*severities.length)],
        source: sources[Math.floor(Math.random()*sources.length)],
        host: hosts[Math.floor(Math.random()*hosts.length)],
        user: users[Math.floor(Math.random()*users.length)],
        sourceIp: `192.168.${Math.floor(Math.random()*255)}.${Math.floor(Math.random()*255)}`,
        destinationIp: `10.${Math.floor(Math.random()*255)}.${Math.floor(Math.random()*255)}.${Math.floor(Math.random()*255)}`,
        eventType: types[Math.floor(Math.random()*types.length)],
        message: this.generateMessage(),
        rawData: this.generateRawData()
      });
    }
    return logs.sort((a,b)=> new Date(b.timestamp)-new Date(a.timestamp));
  },

  generateMessage(){
    const msgs = [
      'User authentication successful',
      'Failed login attempt detected',
      'Network port scan detected from external IP',
      'Configuration file modified',
      'Database query executed',
      'File upload completed',
      'System backup initiated',
      'Security policy violation',
      'Malware signature detected',
      'Privilege escalation attempt'
    ];
    return msgs[Math.floor(Math.random()*msgs.length)];
  },

  generateRawData(){
    return {
      eventId: Math.random().toString(36).slice(2,11),
      processId: Math.floor(Math.random()*10000),
      threadId: Math.floor(Math.random()*1000),
      sessionId: Math.random().toString(36).slice(2,8),
      additionalInfo: {
        bytesTransferred: Math.floor(Math.random()*1_000_000),
        duration: Math.floor(Math.random()*5000),
        resultCode: Math.floor(Math.random()*1000)
      }
    };
  },

  generateAlerts(count=50){
    const alerts=[]; const now=new Date();
    for(let i=0;i<count;i++){
      const hoursAgo=Math.floor(Math.random()*24);
      const timestamp=new Date(now - hoursAgo*3600000);
      alerts.push({
        id:`alert-${i}`,
        timestamp: timestamp.toISOString(),
        severity: ['low','medium','high','critical'][Math.floor(Math.random()*4)],
        title: `Security Alert ${i+1}`,
        description: 'Potential security incident detected',
        source: ['IDS','Firewall','AV','SIEM'][Math.floor(Math.random()*4)],
        status: ['new','in_progress','resolved'][Math.floor(Math.random()*3)],
        assignedTo: ['analyst1','analyst2','analyst3','unassigned'][Math.floor(Math.random()*4)]
      });
    }
    return alerts.sort((a,b)=> new Date(b.timestamp)-new Date(a.timestamp));
  }
};

// Global event handlers for modals
document.addEventListener('DOMContentLoaded', function() {
  // Close modals on outside click
  document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        siemApp.hideModal(this.id);
      }
    });
  });

  // Close modals on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal.active').forEach(modal => {
        siemApp.hideModal(modal.id);
      });
    }
  });

  // Auto-refresh functionality
  let refreshInterval;
  const refreshToggle = document.getElementById('auto-refresh-toggle');
  
  if (refreshToggle) {
    refreshToggle.addEventListener('change', function() {
      if (this.checked) {
        const interval = parseInt(document.getElementById('refresh-interval').value) || 30000;
        refreshInterval = setInterval(() => {
          if (typeof window.refreshData === 'function') {
            window.refreshData();
          } else {
            location.reload();
          }
        }, interval);
      } else {
        clearInterval(refreshInterval);
      }
    });
  }

  // Initialize enhanced features for logs page
  if (document.getElementById('logs-table')) {
    initColumnResize();
    initQueryInput();
  }
});

// Utility function for real-time updates
const RealTimeUpdater = {
  init() {
    this.setupEventSource();
  },

  setupEventSource() {
    // This would connect to a real-time event source in a real application
    // For now, we'll simulate updates every 30 seconds
    setInterval(() => {
      this.checkForUpdates();
    }, 30000);
  },

  async checkForUpdates() {
    try {
      // In a real application, this would check for new logs/alerts
      const hasUpdates = Math.random() > 0.7; // 30% chance of updates
      
      if (hasUpdates && document.visibilityState === 'visible') {
        siemApp.showNotification('New security events available', 'info');
        
        // Refresh data if on a relevant page
        if (window.location.pathname.includes('logs.php') || 
            window.location.pathname.includes('alerts.php')) {
          if (typeof window.refreshData === 'function') {
            window.refreshData();
          }
        }
      }
    } catch (error) {
      console.error('Error checking for updates:', error);
    }
  }
};

// Column resizing functionality
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

// Query input enhancements
function initQueryInput() {
  const queryInput = document.querySelector('.query-syntax-input');
  if (queryInput) {
    // Auto-expand textarea
    queryInput.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Submit on Enter
    queryInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('filter-form').submit();
      }
    });
    
    // Trigger initial resize
    queryInput.style.height = (queryInput.scrollHeight) + 'px';
  }
}

// Initialize real-time updates
document.addEventListener('DOMContentLoaded', () => {
  RealTimeUpdater.init();
});

// Global helper functions
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