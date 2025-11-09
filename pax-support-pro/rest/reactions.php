<?php
/**
 * Chat Reactions REST API Endpoints
 * 
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register reactions REST routes
 */
add_action( 'rest_api_init', function() {
    register_rest_route( PAX_SUP_REST_NS, '/reactions', array(
        'methods'             => 'POST',
        'callback'            => 'pax_sup_save_reaction',
        'permission_callback' => '__return_true',
    ) );
    
    register_rest_route( PAX_SUP_REST_NS, '/reactions/stats', array(
        'methods'             => 'GET',
        'callback'            => 'pax_sup_get_reaction_stats',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
} );

/**
 * Save a reaction to the database
 */
function pax_sup_save_reaction( WP_REST_Request $request ) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'pax_chat_reactions';
    
    $message_id    = sanitize_text_field( $request->get_param( 'message_id' ) );
    $reaction_type = sanitize_text_field( $request->get_param( 'reaction_type' ) );
    $is_active     = (int) $request->get_param( 'is_active' );
    $message_text  = sanitize_textarea_field( $request->get_param( 'message_text' ) );
    $session_id    = sanitize_text_field( $request->get_param( 'session_id' ) );
    $user_id       = get_current_user_id();
    
    if ( empty( $message_id ) || empty( $reaction_type ) ) {
        return new WP_Error( 'invalid_data', 'Invalid reaction data', array( 'status' => 400 ) );
    }
    
    // Check if reaction already exists
    $existing = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE message_id = %s AND reaction_type = %s AND session_id = %s",
        $message_id,
        $reaction_type,
        $session_id
    ) );
    
    if ( $existing ) {
        // Update existing reaction
        $wpdb->update(
            $table_name,
            array(
                'is_active'    => $is_active,
                'updated_at'   => current_time( 'mysql' ),
            ),
            array(
                'id' => $existing->id,
            ),
            array( '%d', '%s' ),
            array( '%d' )
        );
    } else {
        // Insert new reaction
        $wpdb->insert(
            $table_name,
            array(
                'message_id'    => $message_id,
                'reaction_type' => $reaction_type,
                'is_active'     => $is_active,
                'message_text'  => $message_text,
                'session_id'    => $session_id,
                'user_id'       => $user_id,
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
        );
    }
    
    return rest_ensure_response( array(
        'success' => true,
        'message' => 'Reaction saved successfully',
    ) );
}

/**
 * Get reaction statistics
 */
function pax_sup_get_reaction_stats() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'pax_chat_reactions';
    
    // Get total counts
    $stats = $wpdb->get_results(
        "SELECT 
            reaction_type,
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
        FROM {$table_name}
        GROUP BY reaction_type",
        ARRAY_A
    );
    
    // Get recent reactions
    $recent = $wpdb->get_results(
        "SELECT 
            reaction_type,
            message_text,
            is_active,
            created_at
        FROM {$table_name}
        ORDER BY created_at DESC
        LIMIT 50",
        ARRAY_A
    );
    
    return rest_ensure_response( array(
        'success' => true,
        'stats'   => $stats,
        'recent'  => $recent,
    ) );
}

/**
 * AJAX handler to reset all reactions
 */
add_action( 'wp_ajax_pax_reset_reactions', 'pax_sup_ajax_reset_reactions' );
function pax_sup_ajax_reset_reactions() {
    check_ajax_referer( 'pax_sup_reset_settings', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pax_chat_reactions';
    
    $result = $wpdb->query( "TRUNCATE TABLE {$table_name}" );
    
    if ( $result !== false ) {
        wp_send_json_success( array( 'message' => 'All reactions have been reset successfully.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Failed to reset reactions.' ) );
    }
}

/**
 * Create reactions table on plugin activation
 */
function pax_sup_create_reactions_table() {
    global $wpdb;
    
    $table_name      = $wpdb->prefix . 'pax_chat_reactions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        message_id varchar(255) NOT NULL,
        reaction_type varchar(50) NOT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        message_text text,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) UNSIGNED DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY message_id (message_id),
        KEY reaction_type (reaction_type),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) {$charset_collate};";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// Create table on plugin load
add_action( 'plugins_loaded', 'pax_sup_create_reactions_table' );
