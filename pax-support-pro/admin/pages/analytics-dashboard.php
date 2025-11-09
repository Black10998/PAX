<?php
/**
 * Analytics Dashboard Admin Page
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Analytics Dashboard page
 */
function pax_sup_render_analytics_dashboard_page() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'view_pax_analytics' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pax-support-pro' ) );
    }

    // Get date range from query params
    $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
    $end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : date( 'Y-m-d' );

    // Get analytics data
    $analytics = pax_sup_get_analytics_data( $start_date, $end_date );

    ?>
    <div class="wrap pax-modern-page pax-analytics-page">
        <div class="pax-page-header">
            <div class="pax-breadcrumb">
                <span class="dashicons dashicons-admin-home"></span>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-dashboard' ) ); ?>">
                    <?php esc_html_e( 'Dashboard', 'pax-support-pro' ); ?>
                </a>
                <span class="separator">/</span>
                <span class="current"><?php esc_html_e( 'Analytics', 'pax-support-pro' ); ?></span>
            </div>
            <h1>
                <span class="dashicons dashicons-chart-bar"></span>
                <?php esc_html_e( 'Analytics Dashboard', 'pax-support-pro' ); ?>
            </h1>
            <p class="description">
                <?php esc_html_e( 'Comprehensive analytics and insights for your support system.', 'pax-support-pro' ); ?>
            </p>
        </div>

        <div class="pax-analytics-filters">
            <form method="get" action="" class="pax-date-filter-form">
                <input type="hidden" name="page" value="pax-support-analytics">
                <div class="pax-filter-group">
                    <label for="start_date"><?php esc_html_e( 'Start Date', 'pax-support-pro' ); ?></label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" class="pax-input">
                </div>
                <div class="pax-filter-group">
                    <label for="end_date"><?php esc_html_e( 'End Date', 'pax-support-pro' ); ?></label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" class="pax-input">
                </div>
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-filter"></span>
                    <?php esc_html_e( 'Apply Filter', 'pax-support-pro' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="pax-export-csv">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e( 'Export CSV', 'pax-support-pro' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="pax-refresh-analytics">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e( 'Refresh', 'pax-support-pro' ); ?>
                </button>
            </form>
        </div>

        <div class="pax-analytics-grid">
            <!-- Summary Cards -->
            <div class="pax-card pax-stat-card">
                <div class="pax-stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <span class="dashicons dashicons-tickets-alt"></span>
                </div>
                <div class="pax-stat-content">
                    <h3><?php esc_html_e( 'Total Tickets', 'pax-support-pro' ); ?></h3>
                    <div class="pax-stat-value"><?php echo esc_html( $analytics['total_tickets'] ); ?></div>
                    <div class="pax-stat-meta">
                        <span class="pax-stat-open"><?php echo esc_html( $analytics['open_tickets'] ); ?> <?php esc_html_e( 'Open', 'pax-support-pro' ); ?></span>
                        <span class="pax-stat-closed"><?php echo esc_html( $analytics['closed_tickets'] ); ?> <?php esc_html_e( 'Closed', 'pax-support-pro' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="pax-card pax-stat-card">
                <div class="pax-stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="pax-stat-content">
                    <h3><?php esc_html_e( 'Avg Response Time', 'pax-support-pro' ); ?></h3>
                    <div class="pax-stat-value"><?php echo esc_html( $analytics['avg_response_time'] ); ?></div>
                    <div class="pax-stat-meta">
                        <?php esc_html_e( 'Minutes', 'pax-support-pro' ); ?>
                    </div>
                </div>
            </div>

            <div class="pax-card pax-stat-card">
                <div class="pax-stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <span class="dashicons dashicons-format-chat"></span>
                </div>
                <div class="pax-stat-content">
                    <h3><?php esc_html_e( 'Total Chats', 'pax-support-pro' ); ?></h3>
                    <div class="pax-stat-value"><?php echo esc_html( $analytics['total_chats'] ); ?></div>
                    <div class="pax-stat-meta">
                        <?php echo esc_html( $analytics['total_messages'] ); ?> <?php esc_html_e( 'Messages', 'pax-support-pro' ); ?>
                    </div>
                </div>
            </div>

            <div class="pax-card pax-stat-card">
                <div class="pax-stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <span class="dashicons dashicons-phone"></span>
                </div>
                <div class="pax-stat-content">
                    <h3><?php esc_html_e( 'Callback Requests', 'pax-support-pro' ); ?></h3>
                    <div class="pax-stat-value"><?php echo esc_html( $analytics['total_callbacks'] ); ?></div>
                    <div class="pax-stat-meta">
                        <?php echo esc_html( $analytics['pending_callbacks'] ); ?> <?php esc_html_e( 'Pending', 'pax-support-pro' ); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="pax-charts-grid">
            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Tickets Trend', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <canvas id="pax-tickets-chart"></canvas>
                </div>
            </div>

            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Chat Activity', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <canvas id="pax-chats-chart"></canvas>
                </div>
            </div>

            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Response Time Distribution', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <canvas id="pax-response-chart"></canvas>
                </div>
            </div>

            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Callback Requests Trend', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <canvas id="pax-callbacks-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Auto-refresh indicator -->
        <div class="pax-auto-refresh-indicator">
            <span class="dashicons dashicons-update"></span>
            <span id="pax-refresh-countdown"><?php esc_html_e( 'Auto-refresh in 60s', 'pax-support-pro' ); ?></span>
        </div>
    </div>

    <script>
    // Pass analytics data to JavaScript
    window.paxAnalyticsData = <?php echo wp_json_encode( $analytics ); ?>;
    </script>
    <?php
}

/**
 * Get analytics data for date range
 */
function pax_sup_get_analytics_data( $start_date, $end_date ) {
    global $wpdb;

    $data = array(
        'total_tickets' => 0,
        'open_tickets' => 0,
        'closed_tickets' => 0,
        'avg_response_time' => 0,
        'total_chats' => 0,
        'total_messages' => 0,
        'total_callbacks' => 0,
        'pending_callbacks' => 0,
        'tickets_by_date' => array(),
        'chats_by_date' => array(),
        'response_times' => array(),
        'callbacks_by_date' => array(),
    );

    // Get ticket statistics
    $tickets_table = $wpdb->prefix . 'pax_tickets';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$tickets_table}'" ) === $tickets_table ) {
        $data['total_tickets'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tickets_table} WHERE created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            )
        );

        $data['open_tickets'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tickets_table} WHERE status = 'open' AND created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            )
        );

        $data['closed_tickets'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tickets_table} WHERE status = 'closed' AND created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            )
        );

        // Get tickets by date
        $tickets_by_date = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM {$tickets_table} 
                WHERE created_at BETWEEN %s AND %s 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            ),
            ARRAY_A
        );

        foreach ( $tickets_by_date as $row ) {
            $data['tickets_by_date'][ $row['date'] ] = (int) $row['count'];
        }
    }

    // Get chat statistics
    $chats_table = $wpdb->prefix . 'pax_chats';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$chats_table}'" ) === $chats_table ) {
        $data['total_chats'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$chats_table} WHERE created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            )
        );

        // Get chats by date
        $chats_by_date = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM {$chats_table} 
                WHERE created_at BETWEEN %s AND %s 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            ),
            ARRAY_A
        );

        foreach ( $chats_by_date as $row ) {
            $data['chats_by_date'][ $row['date'] ] = (int) $row['count'];
        }
    }

    // Get messages count
    $messages_table = $wpdb->prefix . 'pax_messages';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$messages_table}'" ) === $messages_table ) {
        $data['total_messages'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$messages_table} WHERE created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            )
        );
    }

    // Get callback statistics
    $callbacks_table = $wpdb->prefix . 'pax_callbacks';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$callbacks_table}'" ) === $callbacks_table ) {
        $data['total_callbacks'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$callbacks_table} WHERE created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            )
        );

        $data['pending_callbacks'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$callbacks_table} WHERE status = 'pending' AND created_at BETWEEN %s AND %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            )
        );

        // Get callbacks by date
        $callbacks_by_date = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM {$callbacks_table} 
                WHERE created_at BETWEEN %s AND %s 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            ),
            ARRAY_A
        );

        foreach ( $callbacks_by_date as $row ) {
            $data['callbacks_by_date'][ $row['date'] ] = (int) $row['count'];
        }
    }

    // Calculate average response time (mock data for now)
    $data['avg_response_time'] = rand( 5, 45 );
    $data['response_times'] = array(
        '0-5 min' => rand( 10, 30 ),
        '5-15 min' => rand( 20, 50 ),
        '15-30 min' => rand( 15, 35 ),
        '30+ min' => rand( 5, 20 ),
    );

    return $data;
}
