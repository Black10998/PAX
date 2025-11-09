<?php
/**
 * Dashboard Analytics UI
 * PAX Support Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render analytics dashboard
 */
function pax_sup_render_analytics_dashboard() {
    global $wpdb;
    $table = pax_sup_get_ticket_table();
    
    // Get ticket statistics
    $total_tickets = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $open_tickets = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'open'" );
    $today_tickets = $wpdb->get_var( 
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s",
            current_time( 'Y-m-d' )
        )
    );
    
    // Calculate average response time (mock data for now)
    $avg_response_time = '2.5h';
    
    ?>
    <div class="pax-analytics-container">
        <!-- System Health Indicator -->
        <div class="pax-system-health">
            <div class="pax-health-circle healthy"></div>
            <span class="pax-health-label healthy"><?php esc_html_e( 'System Stable', 'pax-support-pro' ); ?></span>
            
            <div class="pax-health-tooltip">
                <div class="pax-health-tooltip-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'System Health', 'pax-support-pro' ); ?>
                </div>
                <div class="pax-health-metric">
                    <span class="pax-health-metric-label"><?php esc_html_e( 'CPU Usage', 'pax-support-pro' ); ?></span>
                    <span class="pax-health-metric-value good">45%</span>
                </div>
                <div class="pax-health-metric">
                    <span class="pax-health-metric-label"><?php esc_html_e( 'Memory Usage', 'pax-support-pro' ); ?></span>
                    <span class="pax-health-metric-value good">52%</span>
                </div>
                <div class="pax-health-metric">
                    <span class="pax-health-metric-label"><?php esc_html_e( 'Disk Usage', 'pax-support-pro' ); ?></span>
                    <span class="pax-health-metric-value good">68%</span>
                </div>
                <div class="pax-health-metric">
                    <span class="pax-health-metric-label"><?php esc_html_e( 'Response Time', 'pax-support-pro' ); ?></span>
                    <span class="pax-health-metric-value good">250ms</span>
                </div>
            </div>
        </div>
        
        <div class="pax-chart-header">
            <h2 class="pax-chart-title">
                <span class="dashicons dashicons-chart-line"></span>
                <?php esc_html_e( 'Ticket Analytics', 'pax-support-pro' ); ?>
            </h2>
            <div class="pax-status-indicator">
                <span class="pax-status-led"></span>
                <span class="pax-status-text"><?php esc_html_e( 'Normal Activity', 'pax-support-pro' ); ?></span>
            </div>
        </div>
        
        <div class="pax-chart-wrapper">
            <canvas id="pax-analytics-chart"></canvas>
        </div>
        
        <div class="pax-stats-grid">
            <div class="pax-stat-card">
                <div class="pax-stat-label"><?php esc_html_e( 'Total Tickets', 'pax-support-pro' ); ?></div>
                <div class="pax-stat-value"><?php echo esc_html( number_format( $total_tickets ) ); ?></div>
                <div class="pax-stat-trend up">
                    <span class="dashicons dashicons-arrow-up-alt"></span>
                    <?php esc_html_e( '12% this week', 'pax-support-pro' ); ?>
                </div>
            </div>
            
            <div class="pax-stat-card">
                <div class="pax-stat-label"><?php esc_html_e( 'Open Tickets', 'pax-support-pro' ); ?></div>
                <div class="pax-stat-value"><?php echo esc_html( number_format( $open_tickets ) ); ?></div>
                <div class="pax-stat-trend down">
                    <span class="dashicons dashicons-arrow-down-alt"></span>
                    <?php esc_html_e( '8% from yesterday', 'pax-support-pro' ); ?>
                </div>
            </div>
            
            <div class="pax-stat-card">
                <div class="pax-stat-label"><?php esc_html_e( 'Today', 'pax-support-pro' ); ?></div>
                <div class="pax-stat-value"><?php echo esc_html( number_format( $today_tickets ) ); ?></div>
                <div class="pax-stat-trend up">
                    <span class="dashicons dashicons-arrow-up-alt"></span>
                    <?php esc_html_e( '5 new tickets', 'pax-support-pro' ); ?>
                </div>
            </div>
            
            <div class="pax-stat-card">
                <div class="pax-stat-label"><?php esc_html_e( 'Avg Response', 'pax-support-pro' ); ?></div>
                <div class="pax-stat-value"><?php echo esc_html( $avg_response_time ); ?></div>
                <div class="pax-stat-trend up">
                    <span class="dashicons dashicons-arrow-up-alt"></span>
                    <?php esc_html_e( 'Improved 15%', 'pax-support-pro' ); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Enqueue analytics assets
 */
function pax_sup_enqueue_analytics_assets() {
    $screen = get_current_screen();
    if ( ! $screen || 'toplevel_page_pax-support-console' !== $screen->id ) {
        return;
    }
    
    wp_enqueue_style(
        'pax-dashboard-analytics',
        PAX_SUP_URL . 'admin/css/dashboard-analytics.css',
        array(),
        PAX_SUP_VER
    );
    
    wp_enqueue_script(
        'pax-dashboard-analytics',
        PAX_SUP_URL . 'admin/js/dashboard-analytics.js',
        array(),
        PAX_SUP_VER,
        true
    );
    
    // Localize script with REST API settings
    wp_localize_script(
        'pax-dashboard-analytics',
        'wpApiSettings',
        array(
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' )
        )
    );
}
add_action( 'admin_enqueue_scripts', 'pax_sup_enqueue_analytics_assets' );
