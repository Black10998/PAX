/**
 * Dashboard Analytics Chart
 * PAX Support Pro - PlayArcadiaX Style
 */

(function() {
    'use strict';

    // Color states
    const COLORS = {
        NORMAL: '#00FFFF',      // Neon blue
        HIGH_LOAD: '#FFC107',   // Amber glow
        ERROR: '#FF1744',       // Red glow
        IDLE: '#AA00FF'         // Soft violet
    };

    // Thresholds
    const THRESHOLDS = {
        HIGH_LOAD: 30,  // tickets per day
        IDLE: 5         // tickets per day
    };

    let chart = null;
    let updateInterval = null;
    let healthInterval = null;

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        const canvas = document.getElementById('pax-analytics-chart');
        if (!canvas) return;

        // Initialize system health indicator
        initSystemHealth();

        // Load Chart.js if not already loaded
        if (typeof Chart === 'undefined') {
            loadChartJS().then(() => {
                initChart(canvas);
            });
        } else {
            initChart(canvas);
        }
    }

    /**
     * Load Chart.js from CDN
     */
    function loadChartJS() {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Initialize the chart
     */
    function initChart(canvas) {
        const ctx = canvas.getContext('2d');
        
        // Generate initial data
        const data = generateChartData();
        
        // Create gradient
        const gradient = createGradient(ctx, data.datasets[0].data);
        
        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Ticket Volume',
                    data: data.datasets[0].data,
                    borderColor: gradient,
                    backgroundColor: createAreaGradient(ctx, gradient),
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: data.datasets[0].pointColors,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverBorderWidth: 3,
                    segment: {
                        borderColor: (ctx) => getSegmentColor(ctx)
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false,
                        external: customTooltip
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.6)',
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.6)',
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                return value + ' tickets';
                            }
                        },
                        beginAtZero: true
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart',
                    onComplete: () => {
                        // Add glow effect after animation
                        const wrapper = canvas.closest('.pax-chart-wrapper');
                        if (wrapper) {
                            wrapper.classList.add('glow-active');
                        }
                    }
                }
            }
        });

        // Update status indicator
        updateStatusIndicator(data.datasets[0].data);

        // Start real-time updates (every 30 seconds)
        startRealTimeUpdates();
    }

    /**
     * Generate chart data
     */
    function generateChartData() {
        const labels = [];
        const data = [];
        const pointColors = [];
        
        // Generate last 24 hours of data
        const now = new Date();
        for (let i = 23; i >= 0; i--) {
            const hour = new Date(now.getTime() - (i * 60 * 60 * 1000));
            labels.push(hour.getHours() + ':00');
            
            // Simulate ticket volume with some variation
            const baseVolume = 15 + Math.random() * 25;
            const volume = Math.round(baseVolume + Math.sin(i / 4) * 10);
            data.push(volume);
            
            // Determine point color based on volume
            pointColors.push(getColorForValue(volume));
        }
        
        return {
            labels,
            datasets: [{
                data,
                pointColors
            }]
        };
    }

    /**
     * Get color based on ticket volume
     */
    function getColorForValue(value) {
        if (value >= THRESHOLDS.HIGH_LOAD) {
            return COLORS.HIGH_LOAD;
        } else if (value <= THRESHOLDS.IDLE) {
            return COLORS.IDLE;
        } else {
            return COLORS.NORMAL;
        }
    }

    /**
     * Get status text for value
     */
    function getStatusText(value) {
        if (value >= THRESHOLDS.HIGH_LOAD) {
            return 'High Load';
        } else if (value <= THRESHOLDS.IDLE) {
            return 'Idle State';
        } else {
            return 'Normal Activity';
        }
    }

    /**
     * Get status class for value
     */
    function getStatusClass(value) {
        if (value >= THRESHOLDS.HIGH_LOAD) {
            return 'high-load';
        } else if (value <= THRESHOLDS.IDLE) {
            return 'idle';
        } else {
            return 'normal';
        }
    }

    /**
     * Create gradient for line
     */
    function createGradient(ctx, data) {
        const gradient = ctx.createLinearGradient(0, 0, ctx.canvas.width, 0);
        
        // Calculate color stops based on data
        const segments = data.length - 1;
        data.forEach((value, index) => {
            const position = index / segments;
            const color = getColorForValue(value);
            gradient.addColorStop(position, color);
        });
        
        return gradient;
    }

    /**
     * Create area gradient
     */
    function createAreaGradient(ctx, lineGradient) {
        const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
        gradient.addColorStop(0, 'rgba(0, 255, 255, 0.2)');
        gradient.addColorStop(1, 'rgba(0, 255, 255, 0)');
        return gradient;
    }

    /**
     * Get segment color for smooth transitions
     */
    function getSegmentColor(ctx) {
        const p0 = ctx.p0;
        const p1 = ctx.p1;
        
        if (!p0 || !p1) return COLORS.NORMAL;
        
        const value0 = p0.parsed.y;
        const value1 = p1.parsed.y;
        const avgValue = (value0 + value1) / 2;
        
        return getColorForValue(avgValue);
    }

    /**
     * Custom tooltip
     */
    function customTooltip(context) {
        // Tooltip element
        let tooltipEl = document.getElementById('pax-chart-tooltip');
        
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = 'pax-chart-tooltip';
            tooltipEl.className = 'pax-chart-tooltip';
            document.body.appendChild(tooltipEl);
        }
        
        // Hide if no tooltip
        const tooltipModel = context.tooltip;
        if (tooltipModel.opacity === 0) {
            tooltipEl.style.opacity = 0;
            return;
        }
        
        // Set content
        if (tooltipModel.body) {
            const dataPoint = tooltipModel.dataPoints[0];
            const value = dataPoint.parsed.y;
            const label = dataPoint.label;
            const status = getStatusText(value);
            const statusClass = getStatusClass(value);
            
            tooltipEl.innerHTML = `
                <div class="tooltip-title">${label}</div>
                <div><strong>${value}</strong> tickets</div>
                <div class="tooltip-status ${statusClass}">${status}</div>
            `;
        }
        
        // Position tooltip
        const position = context.chart.canvas.getBoundingClientRect();
        tooltipEl.style.opacity = 1;
        tooltipEl.style.position = 'absolute';
        tooltipEl.style.left = position.left + window.pageXOffset + tooltipModel.caretX + 'px';
        tooltipEl.style.top = position.top + window.pageYOffset + tooltipModel.caretY + 'px';
        tooltipEl.style.pointerEvents = 'none';
        tooltipEl.style.zIndex = 10000;
    }

    /**
     * Update status indicator
     */
    function updateStatusIndicator(data) {
        const indicator = document.querySelector('.pax-status-indicator');
        if (!indicator) return;
        
        // Calculate average of last 3 hours
        const recentData = data.slice(-3);
        const avgVolume = recentData.reduce((a, b) => a + b, 0) / recentData.length;
        
        // Update indicator
        indicator.className = 'pax-status-indicator ' + getStatusClass(avgVolume);
        
        const statusText = indicator.querySelector('.pax-status-text');
        if (statusText) {
            statusText.textContent = getStatusText(avgVolume);
        }
    }

    /**
     * Start real-time updates
     */
    function startRealTimeUpdates() {
        updateInterval = setInterval(() => {
            if (!chart) return;
            
            // Remove first data point
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
            chart.data.datasets[0].pointBackgroundColor.shift();
            
            // Add new data point
            const now = new Date();
            chart.data.labels.push(now.getHours() + ':' + String(now.getMinutes()).padStart(2, '0'));
            
            const newValue = Math.round(15 + Math.random() * 25);
            chart.data.datasets[0].data.push(newValue);
            chart.data.datasets[0].pointBackgroundColor.push(getColorForValue(newValue));
            
            // Update gradient
            const ctx = chart.ctx;
            chart.data.datasets[0].borderColor = createGradient(ctx, chart.data.datasets[0].data);
            
            // Trigger pulsing animation
            const wrapper = chart.canvas.closest('.pax-chart-wrapper');
            if (wrapper) {
                wrapper.classList.remove('glow-active');
                setTimeout(() => wrapper.classList.add('glow-active'), 50);
            }
            
            // Update chart
            chart.update('none'); // No animation for real-time updates
            
            // Update status indicator
            updateStatusIndicator(chart.data.datasets[0].data);
        }, 30000); // Update every 30 seconds
    }

    /**
     * Initialize system health indicator
     */
    function initSystemHealth() {
        updateSystemHealth();
        
        // Update health every 10 seconds
        healthInterval = setInterval(updateSystemHealth, 10000);
    }

    /**
     * Update system health indicator
     */
    function updateSystemHealth() {
        const healthCircle = document.querySelector('.pax-health-circle');
        const healthLabel = document.querySelector('.pax-health-label');
        const healthMetrics = document.querySelectorAll('.pax-health-metric-value');
        
        if (!healthCircle || !healthLabel) return;
        
        // Fetch system metrics (in production, this would be an AJAX call)
        fetchSystemMetrics().then(metrics => {
            const status = calculateHealthStatus(metrics);
            
            // Update circle
            healthCircle.className = 'pax-health-circle ' + status.class;
            
            // Update label with smooth transition
            healthLabel.style.opacity = '0';
            setTimeout(() => {
                healthLabel.textContent = status.label;
                healthLabel.className = 'pax-health-label ' + status.class;
                healthLabel.style.opacity = '1';
            }, 150);
            
            // Update tooltip metrics
            if (healthMetrics.length >= 4) {
                updateMetricValue(healthMetrics[0], metrics.cpu, 80, 90);
                updateMetricValue(healthMetrics[1], metrics.memory, 75, 85);
                updateMetricValue(healthMetrics[2], metrics.disk, 80, 90);
                updateMetricValue(healthMetrics[3], metrics.responseTime, 500, 1000, true);
            }
        });
    }

    /**
     * Fetch system metrics
     */
    function fetchSystemMetrics() {
        // Try to fetch from WordPress REST API
        if (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) {
            return fetch(wpApiSettings.root + 'pax/v1/system-health', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': wpApiSettings.nonce
                }
            })
            .then(response => response.json())
            .catch(() => {
                // Fallback to simulated data if API fails
                return getSimulatedMetrics();
            });
        }
        
        // Fallback to simulated data
        return Promise.resolve(getSimulatedMetrics());
    }

    /**
     * Get simulated metrics (fallback)
     */
    function getSimulatedMetrics() {
        return {
            cpu: Math.round(30 + Math.random() * 50),
            memory: Math.round(40 + Math.random() * 40),
            disk: Math.round(50 + Math.random() * 30),
            responseTime: Math.round(100 + Math.random() * 400),
            errors: Math.random() > 0.9 ? Math.round(Math.random() * 5) : 0
        };
    }

    /**
     * Calculate health status based on metrics
     */
    function calculateHealthStatus(metrics) {
        // Critical conditions
        if (metrics.cpu > 90 || metrics.memory > 85 || metrics.errors > 3) {
            return {
                class: 'critical',
                label: 'Critical Error'
            };
        }
        
        // Warning conditions
        if (metrics.cpu > 80 || metrics.memory > 75 || metrics.responseTime > 500) {
            return {
                class: 'warning',
                label: 'High Load'
            };
        }
        
        // Healthy
        return {
            class: 'healthy',
            label: 'System Stable'
        };
    }

    /**
     * Update metric value with color coding
     */
    function updateMetricValue(element, value, warningThreshold, criticalThreshold, inverse = false) {
        if (!element) return;
        
        let className = 'good';
        let displayValue = value;
        
        if (inverse) {
            // For response time, higher is worse
            displayValue = value + 'ms';
            if (value > criticalThreshold) {
                className = 'critical';
            } else if (value > warningThreshold) {
                className = 'warning';
            }
        } else {
            // For CPU, memory, disk - higher is worse
            displayValue = value + '%';
            if (value > criticalThreshold) {
                className = 'critical';
            } else if (value > warningThreshold) {
                className = 'warning';
            }
        }
        
        element.textContent = displayValue;
        element.className = 'pax-health-metric-value ' + className;
    }

    /**
     * Cleanup on page unload
     */
    window.addEventListener('beforeunload', () => {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        if (healthInterval) {
            clearInterval(healthInterval);
        }
        if (chart) {
            chart.destroy();
        }
    });

})();
