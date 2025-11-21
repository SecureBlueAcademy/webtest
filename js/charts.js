// Chart configuration and utilities for CYBRIXEN SIEM
class SIEMCharts {
  constructor() {
    this.theme = {
      colors: {
        primary: '#66d9ef',
        success: '#8fffa8',
        warning: '#ffcf5b',
        danger: '#ff6b6b',
        info: '#9db0d7',
        muted: '#6b7b9f'
      },
      grid: {
        color: 'rgba(255, 255, 255, 0.1)'
      },
      text: {
        color: '#9db0d7'
      }
    };
  }

  // Common chart options
  getCommonOptions() {
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            color: this.theme.text.color,
            font: {
              size: 11
            }
          }
        },
        tooltip: {
          backgroundColor: 'rgba(18, 26, 54, 0.95)',
          titleColor: '#e8eeff',
          bodyColor: '#9db0d7',
          borderColor: '#1a2452',
          borderWidth: 1,
          cornerRadius: 6,
          displayColors: true,
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                label += ': ';
              }
              if (context.parsed.y !== null) {
                label += context.parsed.y.toLocaleString();
              }
              return label;
            }
          }
        }
      },
      scales: {
        x: {
          grid: {
            color: this.theme.grid.color
          },
          ticks: {
            color: this.theme.text.color
          }
        },
        y: {
          grid: {
            color: this.theme.grid.color
          },
          ticks: {
            color: this.theme.text.color
          }
        }
      }
    };
  }

  // Initialize all charts on the dashboard
  initDashboardCharts() {
    this.initThreatActivityChart();
    this.initEventSourcesChart();
    this.initSeverityDistributionChart();
    this.initResponseTimeChart();
  }

  // Threat Activity Line Chart
  initThreatActivityChart() {
    const ctx = document.getElementById('threat-chart');
    if (!ctx) return;

    // Sample data - in real app, this would come from PHP/API
    const data = {
      labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
      datasets: [
        {
          label: 'Threat Events',
          data: [12, 19, 3, 5, 2, 3],
          borderColor: this.theme.colors.danger,
          backgroundColor: this.hexToRgba(this.theme.colors.danger, 0.1),
          tension: 0.4,
          fill: true,
          pointBackgroundColor: this.theme.colors.danger,
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4
        },
        {
          label: 'Normal Events',
          data: [45, 52, 38, 60, 48, 55],
          borderColor: this.theme.colors.primary,
          backgroundColor: this.hexToRgba(this.theme.colors.primary, 0.1),
          tension: 0.4,
          fill: true,
          pointBackgroundColor: this.theme.colors.primary,
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4
        }
      ]
    };

    const options = {
      ...this.getCommonOptions(),
      interaction: {
        intersect: false,
        mode: 'index'
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Number of Events',
            color: this.theme.text.color
          }
        },
        x: {
          title: {
            display: true,
            text: 'Time of Day',
            color: this.theme.text.color
          }
        }
      }
    };

    new Chart(ctx, {
      type: 'line',
      data: data,
      options: options
    });
  }

  // Event Sources Doughnut Chart
  initEventSourcesChart() {
    const ctx = document.getElementById('sources-chart');
    if (!ctx) return;

    const data = {
      labels: ['Firewall', 'IDS', 'Windows', 'Linux', 'Router', 'Switch'],
      datasets: [{
        data: [30, 20, 15, 15, 10, 10],
        backgroundColor: [
          this.theme.colors.primary,
          this.theme.colors.success,
          this.theme.colors.danger,
          this.theme.colors.warning,
          '#8fffa8',
          this.theme.colors.info
        ],
        borderWidth: 0,
        hoverOffset: 8
      }]
    };

    const options = {
      ...this.getCommonOptions(),
      cutout: '60%',
      plugins: {
        legend: {
          position: 'right',
          labels: {
            padding: 15,
            usePointStyle: true,
            pointStyle: 'circle'
          }
        }
      }
    };

    new Chart(ctx, {
      type: 'doughnut',
      data: data,
      options: options
    });
  }

  // Severity Distribution Bar Chart
  initSeverityDistributionChart() {
    const ctx = document.getElementById('severity-chart');
    if (!ctx) return;

    const data = {
      labels: ['Critical', 'High', 'Medium', 'Low', 'Info'],
      datasets: [{
        label: 'Event Count',
        data: [8, 23, 45, 67, 189],
        backgroundColor: [
          this.theme.colors.danger,
          '#ffa366',
          this.theme.colors.warning,
          '#8fffa8',
          '#9af0b0'
        ],
        borderColor: [
          this.theme.colors.danger,
          '#ffa366',
          this.theme.colors.warning,
          '#8fffa8',
          '#9af0b0'
        ],
        borderWidth: 1,
        borderRadius: 4
      }]
    };

    const options = {
      ...this.getCommonOptions(),
      indexAxis: 'y',
      scales: {
        x: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Number of Events',
            color: this.theme.text.color
          }
        },
        y: {
          title: {
            display: true,
            text: 'Severity Level',
            color: this.theme.text.color
          }
        }
      }
    };

    new Chart(ctx, {
      type: 'bar',
      data: data,
      options: options
    });
  }

  // Response Time Chart
  initResponseTimeChart() {
    const ctx = document.getElementById('response-time-chart');
    if (!ctx) return;

    const data = {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      datasets: [
        {
          label: 'Avg Response Time (min)',
          data: [4.2, 3.8, 5.1, 4.5, 3.9, 4.8, 4.1],
          borderColor: this.theme.colors.primary,
          backgroundColor: this.hexToRgba(this.theme.colors.primary, 0.1),
          tension: 0.4,
          fill: true
        },
        {
          label: 'Target Response Time',
          data: [5, 5, 5, 5, 5, 5, 5],
          borderColor: this.theme.colors.warning,
          borderDash: [5, 5],
          backgroundColor: 'transparent',
          pointRadius: 0
        }
      ]
    };

    const options = {
      ...this.getCommonOptions(),
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Response Time (minutes)',
            color: this.theme.text.color
          }
        }
      }
    };

    new Chart(ctx, {
      type: 'line',
      data: data,
      options: options
    });
  }

  // Alert Trends Over Time
  initAlertTrendsChart() {
    const ctx = document.getElementById('alert-trends-chart');
    if (!ctx) return;

    const data = {
      labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
      datasets: [
        {
          label: 'Critical Alerts',
          data: [12, 19, 8, 15, 22, 18],
          borderColor: this.theme.colors.danger,
          backgroundColor: this.hexToRgba(this.theme.colors.danger, 0.1),
          tension: 0.4,
          fill: true
        },
        {
          label: 'High Alerts',
          data: [28, 32, 25, 30, 35, 28],
          borderColor: '#ffa366',
          backgroundColor: this.hexToRgba('#ffa366', 0.1),
          tension: 0.4,
          fill: true
        },
        {
          label: 'Medium Alerts',
          data: [45, 52, 48, 55, 50, 53],
          borderColor: this.theme.colors.warning,
          backgroundColor: this.hexToRgba(this.theme.colors.warning, 0.1),
          tension: 0.4,
          fill: true
        }
      ]
    };

    const options = {
      ...this.getCommonOptions(),
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Number of Alerts',
            color: this.theme.text.color
          }
        }
      }
    };

    new Chart(ctx, {
      type: 'line',
      data: data,
      options: options
    });
  }

  // Real-time Event Stream (simulated)
  initRealTimeEventChart() {
    const ctx = document.getElementById('realtime-chart');
    if (!ctx) return;

    let timeLabels = [];
    let eventData = [];
    const maxDataPoints = 20;

    const data = {
      labels: timeLabels,
      datasets: [{
        label: 'Events per Second',
        data: eventData,
        borderColor: this.theme.colors.primary,
        backgroundColor: this.hexToRgba(this.theme.colors.primary, 0.1),
        tension: 0.4,
        fill: true
      }]
    };

    const options = {
      ...this.getCommonOptions(),
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Events/Sec',
            color: this.theme.text.color
          }
        },
        x: {
          title: {
            display: true,
            text: 'Time',
            color: this.theme.text.color
          }
        }
      },
      animation: {
        duration: 0
      },
      elements: {
        point: {
          radius: 0
        }
      }
    };

    const chart = new Chart(ctx, {
      type: 'line',
      data: data,
      options: options
    });

    // Simulate real-time data updates
    this.simulateRealTimeData(chart, timeLabels, eventData, maxDataPoints);
  }

  // Simulate real-time data for demo purposes
  simulateRealTimeData(chart, labels, data, maxPoints) {
    setInterval(() => {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', { 
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });

      // Add new data point
      labels.push(timeString);
      data.push(Math.floor(Math.random() * 50) + 10); // Random events between 10-60

      // Remove old data points
      if (labels.length > maxPoints) {
        labels.shift();
        data.shift();
      }

      // Update chart
      chart.update('quiet');
    }, 1000);
  }

  // Utility function to convert hex to rgba
  hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  // Update chart data dynamically
  updateChartData(chartId, newData) {
    const chart = Chart.getChart(chartId);
    if (chart) {
      chart.data = newData;
      chart.update();
    }
  }

  // Export chart as image
  exportChart(chartId, filename = 'chart') {
    const chart = Chart.getChart(chartId);
    if (chart) {
      const link = document.createElement('a');
      link.download = `${filename}.png`;
      link.href = chart.toBase64Image();
      link.click();
    }
  }

  // Destroy all charts (for page cleanup)
  destroyAllCharts() {
    Chart.instances.forEach(instance => {
      instance.destroy();
    });
  }
}

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  window.siemCharts = new SIEMCharts();
  
  // Initialize charts based on current page
  if (document.getElementById('threat-chart') || 
      document.getElementById('sources-chart')) {
    siemCharts.initDashboardCharts();
  }
  
  if (document.getElementById('realtime-chart')) {
    siemCharts.initRealTimeEventChart();
  }
  
  if (document.getElementById('alert-trends-chart')) {
    siemCharts.initAlertTrendsChart();
  }
});

// Chart responsive behavior
window.addEventListener('resize', function() {
  Chart.instances.forEach(instance => {
    instance.resize();
  });
});

// Performance monitoring for charts
const ChartPerformance = {
  monitor() {
    const observer = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (entry.entryType === 'measure') {
          console.log(`Chart render time: ${entry.duration.toFixed(2)}ms`);
        }
      }
    });
    
    observer.observe({ entryTypes: ['measure'] });
  }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { SIEMCharts, ChartPerformance };
}