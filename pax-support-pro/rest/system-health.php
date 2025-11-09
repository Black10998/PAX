<?php
/**
 * System Health REST API
 * PAX Support Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register system health endpoint
 */
function pax_sup_register_system_health_endpoint() {
    register_rest_route(
        PAX_SUP_REST_NS,
        '/system-health',
        array(
            'methods'             => 'GET',
            'callback'            => 'pax_sup_get_system_health',
            'permission_callback' => function() {
                return current_user_can( pax_sup_get_console_capability() );
            }
        )
    );
}
add_action( 'rest_api_init', 'pax_sup_register_system_health_endpoint' );

/**
 * Get system health metrics
 */
function pax_sup_get_system_health() {
    global $wpdb;
    
    // Get memory usage
    $memory_limit = ini_get( 'memory_limit' );
    $memory_limit_bytes = wp_convert_hr_to_bytes( $memory_limit );
    $memory_usage = memory_get_usage( true );
    $memory_percent = $memory_limit_bytes > 0 ? round( ( $memory_usage / $memory_limit_bytes ) * 100 ) : 0;
    
    // Get disk usage
    $disk_free = @disk_free_space( ABSPATH );
    $disk_total = @disk_total_space( ABSPATH );
    $disk_percent = $disk_total > 0 ? round( ( ( $disk_total - $disk_free ) / $disk_total ) * 100 ) : 0;
    
    // Get server load (Unix-like systems only)
    $cpu_percent = 0;
    if ( function_exists( 'sys_getloadavg' ) ) {
        $load = sys_getloadavg();
        if ( is_array( $load ) && isset( $load[0] ) ) {
            // Normalize to percentage (assuming 4 cores)
            $cpu_percent = min( 100, round( ( $load[0] / 4 ) * 100 ) );
        }
    }
    
    // Get response time (measure database query time)
    $start_time = microtime( true );
    $wpdb->get_var( "SELECT 1" );
    $response_time = round( ( microtime( true ) - $start_time ) * 1000 ); // Convert to ms
    
    // Check for errors in error log
    $error_count = 0;
    $error_log = ini_get( 'error_log' );
    if ( $error_log && file_exists( $error_log ) ) {
        $log_content = @file_get_contents( $error_log );
        if ( $log_content ) {
            // Count recent errors (last 1000 chars)
            $recent_log = substr( $log_content, -1000 );
            $error_count = substr_count( $recent_log, '[error]' ) + substr_count( $recent_log, 'Fatal error' );
        }
    }
    
    // Get ticket volume for context
    $table = pax_sup_get_ticket_table();
    $today_tickets = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s",
            current_time( 'Y-m-d' )
        )
    );
    
    return rest_ensure_response(
        array(
            'cpu'          => $cpu_percent,
            'memory'       => $memory_percent,
            'disk'         => $disk_percent,
            'responseTime' => $response_time,
            'errors'       => $error_count,
            'tickets'      => intval( $today_tickets ),
            'timestamp'    => current_time( 'timestamp' )
        )
    );
}
