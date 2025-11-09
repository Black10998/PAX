<?php
/**
 * Live Agent Session REST API Endpoints
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register session endpoints
 */
function pax_sup_register_liveagent_session_routes() {
    // Create session
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/session/create', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_create_session',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
    ) );

    // Accept session
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/session/accept', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_accept_session',
        'permission_callback' => function() {
            return current_user_can( 'manage_pax_chats' );
        },
    ) );

    // Decline session
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/session/decline', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_decline_session',
        'permission_callback' => function() {
            return current_user_can( 'manage_pax_chats' );
        },
    ) );

    // Close session
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/session/close', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_close_session',
        'permission_callback' => 'pax_sup_check_session_permission',
    ) );

    // Get session
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/session/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'pax_sup_rest_get_session',
        'permission_callback' => 'pax_sup_check_session_permission',
    ) );

    // List sessions (admin only)
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/sessions/list', array(
        'methods' => 'GET',
        'callback' => 'pax_sup_rest_list_sessions',
        'permission_callback' => function() {
            return current_user_can( 'manage_pax_chats' );
        },
    ) );

    // Get user's own session
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/session/my-session', array(
        'methods' => 'GET',
        'callback' => 'pax_sup_rest_get_my_session',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
    ) );

    // Convert to ticket
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/session/convert-ticket', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_convert_to_ticket',
        'permission_callback' => function() {
            return current_user_can( 'manage_pax_chats' );
        },
    ) );

    // Export session
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/session/export', array(
        'methods' => 'GET',
        'callback' => 'pax_sup_rest_export_session',
        'permission_callback' => function() {
            return current_user_can( 'manage_pax_chats' );
        },
    ) );
}
add_action( 'rest_api_init', 'pax_sup_register_liveagent_session_routes' );

/**
 * Create new session
 */
function pax_sup_rest_create_session( $request ) {
    pax_sup_liveagent_nocache_headers();

    $user_id = get_current_user_id();
    
    // Check if user already has active session
    $existing = pax_sup_get_user_active_session( $user_id );
    if ( $existing ) {
        return new WP_REST_Response( array(
            'success' => true,
            'session_id' => $existing['id'],
            'status' => $existing['status'],
            'message' => __( 'You already have an active session', 'pax-support-pro' ),
        ), 200 );
    }

    $session_id = pax_sup_create_liveagent_session( $user_id );

    if ( ! $session_id ) {
        return new WP_Error( 'create_failed', __( 'Failed to create session', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    // Send notification to agents
    pax_sup_notify_agents_new_request( $session_id );

    return new WP_REST_Response( array(
        'success' => true,
        'session_id' => $session_id,
        'status' => 'pending',
        'wait_time_estimate' => 60,
    ), 201 );
}

/**
 * Accept session
 */
function pax_sup_rest_accept_session( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );
    $agent_id = get_current_user_id();

    // Enhanced logging for debugging
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( 
            '[PAX Live Chat] Accept session attempt - Session ID: %d, Agent ID: %d', 
            $session_id, 
            $agent_id 
        ) );
    }

    // Validate session_id
    if ( empty( $session_id ) || ! is_numeric( $session_id ) ) {
        return new WP_Error( 'invalid_session_id', __( 'Invalid session ID', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[PAX Live Chat] Session not found: %d', $session_id ) );
        }
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    if ( $session['status'] !== 'pending' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( '[PAX Live Chat] Session not pending: %d (status: %s)', $session_id, $session['status'] ) );
        }
        return new WP_Error( 'invalid_status', __( 'Session is not pending', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $updated = pax_sup_update_liveagent_session_status( $session_id, 'active', $agent_id );

    if ( ! $updated ) {
        global $wpdb;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 
                '[PAX Live Chat] Failed to update session: %d, DB Error: %s', 
                $session_id, 
                $wpdb->last_error 
            ) );
        }
        return new WP_Error( 'update_failed', __( 'Failed to accept session', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    // Add system message
    pax_sup_add_liveagent_message( $session_id, array(
        'sender' => 'system',
        'message' => __( 'Agent has joined the chat', 'pax-support-pro' ),
    ) );

    // Notify user
    pax_sup_notify_user_agent_joined( $session_id );

    $session = pax_sup_get_liveagent_session( $session_id );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( '[PAX Live Chat] Session accepted successfully: %d', $session_id ) );
    }

    return new WP_REST_Response( array(
        'success' => true,
        'session' => $session,
    ), 200 );
}

/**
 * Decline session
 */
function pax_sup_rest_decline_session( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );
    $reason = $request->get_param( 'reason' );

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    $updated = pax_sup_update_liveagent_session_status( $session_id, 'closed' );

    if ( ! $updated ) {
        return new WP_Error( 'update_failed', __( 'Failed to decline session', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    // Notify user
    pax_sup_notify_user_declined( $session_id, $reason );

    return new WP_REST_Response( array(
        'success' => true,
        'message' => __( 'Session declined', 'pax-support-pro' ),
    ), 200 );
}

/**
 * Close session
 */
function pax_sup_rest_close_session( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );
    $notes = $request->get_param( 'notes' );

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    
    $wpdb->update(
        $table_name,
        array(
            'status' => 'closed',
            'ended_at' => current_time( 'mysql' ),
            'session_notes' => sanitize_textarea_field( $notes ),
        ),
        array( 'id' => $session_id )
    );

    // Add system message
    pax_sup_add_liveagent_message( $session_id, array(
        'sender' => 'system',
        'message' => __( 'Chat session ended', 'pax-support-pro' ),
    ) );

    return new WP_REST_Response( array(
        'success' => true,
        'message' => __( 'Session closed', 'pax-support-pro' ),
    ), 200 );
}

/**
 * Get session
 */
function pax_sup_rest_get_session( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'id' );

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    // Count unread messages
    $unread_count = 0;
    $is_agent = current_user_can( 'manage_pax_chats' );
    
    if ( ! empty( $session['messages'] ) && is_array( $session['messages'] ) ) {
        foreach ( $session['messages'] as $message ) {
            if ( $is_agent && $message['sender'] === 'user' && ! $message['read'] ) {
                $unread_count++;
            } elseif ( ! $is_agent && $message['sender'] === 'agent' && ! $message['read'] ) {
                $unread_count++;
            }
        }
    }

    $session['unread_count'] = $unread_count;

    return new WP_REST_Response( array(
        'success' => true,
        'session' => $session,
    ), 200 );
}

/**
 * List sessions
 */
function pax_sup_rest_list_sessions( $request ) {
    pax_sup_liveagent_nocache_headers();

    $status = $request->get_param( 'status' ) ?: 'pending';
    $agent_id = $request->get_param( 'agent_id' );
    $limit = $request->get_param( 'limit' ) ?: 50;

    $sessions = pax_sup_get_liveagent_sessions_by_status( $status );

    // Filter by agent if specified
    if ( $agent_id ) {
        $sessions = array_filter( $sessions, function( $session ) use ( $agent_id ) {
            return $session['agent_id'] == $agent_id;
        } );
    }

    // Limit results
    $sessions = array_slice( $sessions, 0, $limit );

    return new WP_REST_Response( array(
        'success' => true,
        'sessions' => $sessions,
        'total' => count( $sessions ),
    ), 200 );
}

/**
 * Convert session to ticket
 */
function pax_sup_rest_convert_to_ticket( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );

    $ticket_id = pax_sup_convert_liveagent_to_ticket( $session_id );

    if ( ! $ticket_id ) {
        return new WP_Error( 'convert_failed', __( 'Failed to convert to ticket', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    return new WP_REST_Response( array(
        'success' => true,
        'ticket_id' => $ticket_id,
        'message' => __( 'Session converted to ticket', 'pax-support-pro' ),
    ), 200 );
}

/**
 * Export session
 */
function pax_sup_rest_export_session( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );

    $json = pax_sup_export_liveagent_session( $session_id );

    if ( ! $json ) {
        return new WP_Error( 'export_failed', __( 'Failed to export session', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    return new WP_REST_Response( array(
        'success' => true,
        'data' => json_decode( $json, true ),
    ), 200 );
}

/**
 * Get user's own active session
 */
function pax_sup_rest_get_my_session( $request ) {
    pax_sup_liveagent_nocache_headers();

    $user_id = get_current_user_id();
    $session = pax_sup_get_user_active_session( $user_id );

    if ( ! $session ) {
        return new WP_REST_Response( array(
            'success' => true,
            'session' => null,
        ), 200 );
    }

    return new WP_REST_Response( array(
        'success' => true,
        'session' => array(
            'id' => $session['id'],
            'status' => $session['status'],
            'user_id' => $session['user_id'],
            'agent_id' => $session['agent_id'],
            'created_at' => $session['created_at'],
            'last_activity' => $session['last_activity'],
        ),
    ), 200 );
}

/**
 * Check session permission
 */
function pax_sup_check_session_permission( $request ) {
    if ( current_user_can( 'manage_pax_chats' ) ) {
        return true;
    }

    $session_id = $request->get_param( 'session_id' ) ?: $request->get_param( 'id' );
    if ( ! $session_id ) {
        return false;
    }

    return pax_sup_is_session_owner( $session_id );
}

/**
 * Set no-cache headers for Cloudflare compatibility
 */
function pax_sup_liveagent_nocache_headers() {
    nocache_headers();
    header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    header( 'X-Accel-Expires: 0' );
}

/**
 * Check if current user is session owner
 */
function pax_sup_is_session_owner( $session_id ) {
    $session = pax_sup_get_liveagent_session( $session_id );
    return $session && $session['user_id'] === get_current_user_id();
}

/**
 * Notify agents of new request
 */
function pax_sup_notify_agents_new_request( $session_id ) {
    $settings = get_option( 'pax_liveagent_settings', array() );
    
    if ( empty( $settings['email_notifications'] ) ) {
        return;
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    $email = $settings['notification_email'] ?? get_option( 'admin_email' );

    $subject = sprintf( __( '[PAX Support] New Live Chat Request #%d', 'pax-support-pro' ), $session_id );
    $message = sprintf(
        __( "A new live chat request has been received.\n\nFrom: %s (%s)\nTime: %s\n\nView and respond: %s", 'pax-support-pro' ),
        $session['user_name'],
        $session['user_email'],
        $session['started_at'],
        admin_url( 'admin.php?page=pax-live-agent-center&session=' . $session_id )
    );

    wp_mail( $email, $subject, $message );
}

/**
 * Notify user that agent joined
 */
function pax_sup_notify_user_agent_joined( $session_id ) {
    // This will be handled by frontend polling
    // Could add email notification here if needed
}

/**
 * Notify user that request was declined
 */
function pax_sup_notify_user_declined( $session_id, $reason = '' ) {
    // This will be handled by frontend polling
    // Could add email notification here if needed
}
