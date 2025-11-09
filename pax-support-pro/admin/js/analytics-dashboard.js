/**
 * Analytics Dashboard JavaScript
 * PAX Support Pro
 */

(function($) {
    'use strict';

    let refreshTimer;
    let countdown = 60;

    // Initialize on document ready
    $(document).ready(function() {
        if (typeof window.paxAnalyticsData === 'undefined') {
            return;
        }

        initializeCharts();
        initializeRefresh();
        initializeExport();
    });

    /**
     * Initialize all charts
     */
    function initializeCharts() {
        const data = window.paxAnalyticsData;

        // Tickets Trend Chart
        if ($('#pax-tickets-chart').length) {
            const ticketsCtx = document.getElementById('pax-tickets-chart').getContext('2d');
            new Chart(ticketsCtx, {
                type: 'line',
                data: {
                    labels: Object.keys(data.tickets_by_date),
                    datasets: [{
                        label: 'Tickets',
                        data: Object.values(data.tickets_by_date),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Chat Activity Chart
        if ($('#pax-chats-chart').length) {
            const chatsCtx = document.getElementById('pax-chats-chart').getContext('2d');
            new Chart(chatsCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(data.chats_by_date),
                    datasets: [{
                        label: 'Chats',
                        data: Object.values(data.chats_by_date),
                        backgroundColor: 'rgba(79, 172, 254, 0.8)',
                        borderColor: '#4facfe',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Response Time Distribution Chart
        if ($('#pax-response-chart').length) {
            const responseCtx = document.getElementById('pax-response-chart').getContext('2d');
            new Chart(responseCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data.response_times),
                    datasets: [{
                        data: Object.values(data.response_times),
                        backgroundColor: [
                            'rgba(76, 175, 80, 0.8)',
                            'rgba(33, 150, 243, 0.8)',
                            'rgba(255, 152, 0, 0.8)',
                            'rgba(244, 67, 54, 0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Callbacks Trend Chart
        if ($('#pax-callbacks-chart').length) {
            const callbacksCtx = document.getElementById('pax-callbacks-chart').getContext('2d');
            new Chart(callbacksCtx, {
                type: 'line',
                data: {
                    labels: Object.keys(data.callbacks_by_date),
                    datasets: [{
                        label: 'Callbacks',
                        data: Object.values(data.callbacks_by_date),
                        borderColor: '#fa709a',
                        backgroundColor: 'rgba(250, 112, 154, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * Initialize auto-refresh
     */
    function initializeRefresh() {
        updateCountdown();

        refreshTimer = setInterval(function() {
            countdown--;
            updateCountdown();

            if (countdown <= 0) {
                refreshAnalytics();
                countdown = 60;
            }
        }, 1000);

        // Manual refresh button
        $('#pax-refresh-analytics').on('click', function(e) {
            e.preventDefault();
            refreshAnalytics();
            countdown = 60;
        });
    }

    /**
     * Update countdown display
     */
    function updateCountdown() {
        $('#pax-refresh-countdown').text('Auto-refresh in ' + countdown + 's');
    }

    /**
     * Refresh analytics data
     */
    function refreshAnalytics() {
        location.reload();
    }

    /**
     * Initialize CSV export
     */
    function initializeExport() {
        $('#pax-export-csv').on('click', function(e) {
            e.preventDefault();
            exportToCSV();
        });
    }

    /**
     * Export analytics data to CSV
     */
    function exportToCSV() {
        const data = window.paxAnalyticsData;
        
        let csv = 'Metric,Value\n';
        csv += 'Total Tickets,' + data.total_tickets + '\n';
        csv += 'Open Tickets,' + data.open_tickets + '\n';
        csv += 'Closed Tickets,' + data.closed_tickets + '\n';
        csv += 'Average Response Time,' + data.avg_response_time + ' min\n';
        csv += 'Total Chats,' + data.total_chats + '\n';
        csv += 'Total Messages,' + data.total_messages + '\n';
        csv += 'Total Callbacks,' + data.total_callbacks + '\n';
        csv += 'Pending Callbacks,' + data.pending_callbacks + '\n';
        
        csv += '\nTickets by Date\n';
        csv += 'Date,Count\n';
        for (const [date, count] of Object.entries(data.tickets_by_date)) {
            csv += date + ',' + count + '\n';
        }
        
        csv += '\nChats by Date\n';
        csv += 'Date,Count\n';
        for (const [date, count] of Object.entries(data.chats_by_date)) {
            csv += date + ',' + count + '\n';
        }
        
        csv += '\nCallbacks by Date\n';
        csv += 'Date,Count\n';
        for (const [date, count] of Object.entries(data.callbacks_by_date)) {
            csv += date + ',' + count + '\n';
        }

        // Create download link
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'pax-analytics-' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
    });

})(jQuery);
