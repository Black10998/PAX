<?php
/**
 * Live Agent Status REST API Endpoints
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register status endpoints
 */
function pax_sup_register_liveagent_status_routes() {
    // Typing indicator
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/status/typing', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_typing_status',
        'permission_callback' => 'pax_sup_check_status_permission',
    ) );

    // Poll for updates
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/status/poll', array(
        'methods' => 'GET',
        'callback' => 'pax_sup_rest_poll_updates',
        'permission_callback' => 'pax_sup_check_status_permission',
    ) );

    // Check agent availability
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/status/agent-online', array(
        'methods' => 'GET',
        'callback' => 'pax_sup_rest_agent_online',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
    ) );
}
add_action( 'rest_api_init', 'pax_sup_register_liveagent_status_routes' );

/**
 * Update typing status
 */
function pax_sup_rest_typing_status( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );
    $is_typing = $request->get_param( 'is_typing' );
    $sender = $request->get_param( 'sender' );

    // Store typing status in transient (expires in 5 seconds)
    $is_agent = current_user_can( 'manage_pax_chats' );
    $key = $is_agent ? 'agent' : 'user';
    
    set_transient( "pax_typing_{$session_id}_{$key}", $is_typing, 5 );

    return new WP_REST_Response( array(
        'success' => true,
    ), 200 );
}

/**
 * Poll for updates
 */
function pax_sup_rest_poll_updates( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );
    $last_message_id = $request->get_param( 'last_message_id' );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( '[PAX Live Chat] Poll request - Session: %d, Last Message ID: %d', $session_id, $last_message_id ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[PAX Live Chat] Poll - Session not found: %d', $session_id ) );
        }
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    $has_updates  = false;
    $new_messages = array();
    $latest_id    = (int) $last_message_id;

    // Check for new messages by ID (more reliable than timestamp)
    if ( ! empty( $session['messages'] ) && is_array( $session['messages'] ) ) {
        foreach ( $session['messages'] as $message ) {
            if ( isset( $message['id'] ) ) {
                $message_id = (int) $message['id'];
                if ( $message_id > $latest_id ) {
                    $latest_id = $message_id;
                }
                if ( $message_id > $last_message_id ) {
                    $new_messages[] = $message;
                    $has_updates    = true;
                }
            }
        }
    }

    // Check typing status
    $is_agent = current_user_can( 'manage_pax_chats' );
    $agent_typing = (bool) get_transient( "pax_typing_{$session_id}_agent" );
    $user_typing = (bool) get_transient( "pax_typing_{$session_id}_user" );

    if ( $agent_typing || $user_typing || ! empty( $new_messages ) ) {
        $has_updates = true;
    }

    $etag_seed = implode(
        '|',
        array(
            $session_id,
            $session['status'],
            $session['last_activity'],
            $latest_id,
            $agent_typing ? '1' : '0',
            $user_typing ? '1' : '0',
        )
    );
    $etag = '"' . hash( 'sha256', $etag_seed ) . '"';

    $client_etag = trim( (string) $request->get_header( 'If-None-Match' ) );
    if (
        $client_etag
        && $client_etag === $etag
        && empty( $new_messages )
        && ! $agent_typing
        && ! $user_typing
        && (int) $last_message_id >= $latest_id
    ) {
        $response = new WP_REST_Response( null, 304 );
        $response->header( 'ETag', $etag );
        return $response;
    }

    $response_body = array(
        'success' => true,
        'has_updates' => $has_updates,
        'new_messages' => $new_messages,
        'agent_typing' => $agent_typing,
        'user_typing' => $user_typing,
        'session_status' => $session['status'],
        'last_activity' => $session['last_activity'],
        'server_time' => current_time( 'mysql' ),
        'last_message_id' => $latest_id,
    );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( '[PAX Live Chat] Poll response - Updates: %s, New messages: %d, Status: %s', 
            $has_updates ? 'yes' : 'no', 
            count( $new_messages ), 
            $session['status'] 
        ) );
    }

    $response = new WP_REST_Response( $response_body, 200 );
    $response->header( 'ETag', $etag );

    return $response;
}

/**
 * Check agent availability
 */
function pax_sup_rest_agent_online( $request ) {
    pax_sup_liveagent_nocache_headers();

    // Count agents who have been active in last 5 minutes
    $agents = get_users( array(
        'role__in' => array( 'administrator', 'support_manager' ),
        'meta_query' => array(
            array(
                'key' => 'pax_last_seen',
                'value' => time() - 300,
                'compare' => '>',
                'type' => 'NUMERIC',
            ),
        ),
    ) );

    // Get average wait time from recent sessions
    global $wpdb;
    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    $avg_wait = $wpdb->get_var(
        "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, 
            CASE WHEN status = 'active' THEN 
                (SELECT MIN(timestamp) FROM JSON_TABLE(messages, '$[*]' COLUMNS(timestamp DATETIME PATH '$.timestamp')) AS jt WHERE sender = 'agent')
            ELSE NULL END
        )) 
        FROM {$table_name} 
        WHERE status IN ('active', 'closed') 
        AND started_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );

    return new WP_REST_Response( array(
        'success' => true,
        'agents_online' => count( $agents ),
        'average_wait_time' => $avg_wait ? round( $avg_wait ) : 60,
    ), 200 );
}

/**
 * Check status permission
 */
function pax_sup_check_status_permission( $request ) {
    if ( current_user_can( 'manage_pax_chats' ) ) {
        return true;
    }

    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return false;
    }

    return pax_sup_is_session_owner( $session_id );
}

/**
 * Update agent last seen
 */
function pax_sup_update_agent_last_seen() {
    if ( current_user_can( 'manage_pax_chats' ) ) {
        update_user_meta( get_current_user_id(), 'pax_last_seen', time() );
    }
}
add_action( 'admin_init', 'pax_sup_update_agent_last_seen' );
