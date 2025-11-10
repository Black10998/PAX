<?php
/**
 * Live Agent REST API Endpoints
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'pax_register_live_agent_routes' );

function pax_register_live_agent_routes() {
    register_rest_route( 'pax/v1', '/live/start', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_start',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'pax/v1', '/live/status', array(
        'methods'             => 'GET',
        'callback'            => 'pax_live_agent_status',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'pax/v1', '/live/accept', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_accept',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );

    register_rest_route( 'pax/v1', '/live/decline', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_decline',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );

    register_rest_route( 'pax/v1', '/live/message', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_message',
        'permission_callback' => '__return_true',
    ) );
}

function pax_live_agent_start( $request ) {
    global $wpdb;
    
    $user_meta = $request->get_param( 'user_meta' ) ?: array();
    $user_id = get_current_user_id();
    
    $session_data = array(
        'user_id'      => $user_id ?: 0,
        'status'       => 'pending',
        'started_at'   => current_time( 'mysql' ),
        'user_name'    => sanitize_text_field( $user_meta['name'] ?? 'Guest' ),
        'user_email'   => sanitize_email( $user_meta['email'] ?? '' ),
        'user_ip'      => pax_sup_get_client_ip(),
        'messages'     => wp_json_encode( array() ),
        'live_agent'   => 1,
        'last_activity' => current_time( 'mysql' ),
    );
    
    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    $inserted = $wpdb->insert( $table, $session_data );
    
    if ( ! $inserted ) {
        return new WP_Error( 'db_error', 'Failed to create session', array( 'status' => 500 ) );
    }
    
    $session_id = $wpdb->insert_id;
    
    pax_notify_admin_live_agent_request( $session_id, $session_data );
    
    return rest_ensure_response( array(
        'session_id' => $session_id,
        'status'     => 'pending',
        'rest_base'  => rest_url( 'pax/v1/' ),
    ) );
}

function pax_live_agent_status( $request ) {
    global $wpdb;
    
    $session_id = $request->get_param( 'session_id' );
    $ping       = $request->get_param( 'ping' );

    if ( ! $session_id || $ping ) {
        $table   = $wpdb->prefix . 'pax_liveagent_sessions';
        $pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
        $active  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );

        return rest_ensure_response( array(
            'status'     => 'ok',
            'pending'    => $pending,
            'active'     => $active,
            'timestamp'  => current_time( 'mysql' ),
            'message'    => __( 'Connection successful', 'pax-support-pro' ),
        ) );
    }
    
    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    $session = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $session_id
    ), ARRAY_A );
    
    if ( ! $session ) {
        return new WP_Error( 'not_found', 'Session not found', array( 'status' => 404 ) );
    }
    
    $response = array(
        'status' => $session['status'],
    );
    
    if ( $session['agent_id'] ) {
        $agent = get_userdata( $session['agent_id'] );
        if ( $agent ) {
            $response['agent'] = array(
                'id'   => $agent->ID,
                'name' => $agent->display_name,
            );
        }
    }
    
    return rest_ensure_response( $response );
}

function pax_live_agent_accept( $request ) {
    global $wpdb;

    $nonce = $request->get_header( 'x-wp-nonce' );
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', 'Session ID required', array( 'status' => 400 ) );
    }
    
    $agent_id = get_current_user_id();
    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    
    $updated = pax_sup_update_liveagent_session_status( $session_id, 'active', $agent_id );
    
    if ( ! $updated ) {
        return new WP_Error( 'db_error', 'Failed to accept session', array( 'status' => 500 ) );
    }

    pax_sup_add_liveagent_message( $session_id, array(
        'sender'  => 'system',
        'message' => __( 'Agent has joined the chat', 'pax-support-pro' ),
    ) );
    
    return rest_ensure_response( array(
        'success' => true,
        'status'  => 'active',
    ) );
}

function pax_live_agent_decline( $request ) {
    global $wpdb;
    
    $nonce = $request->get_header( 'x-wp-nonce' );
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }
    
    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', 'Session ID required', array( 'status' => 400 ) );
    }
    
    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    
    $updated = $wpdb->update(
        $table,
        array(
            'status'        => 'declined',
            'last_activity' => current_time( 'mysql' ),
        ),
        array( 'id' => $session_id ),
        array( '%s', '%s' ),
        array( '%d' )
    );
    
    if ( $updated === false ) {
        return new WP_Error( 'db_error', 'Failed to decline session', array( 'status' => 500 ) );
    }
    
    return rest_ensure_response( array(
        'success' => true,
        'status'  => 'declined',
    ) );
}

function pax_live_agent_message( $request ) {
    global $wpdb;
    
    $session_id = $request->get_param( 'session_id' );
    $message = $request->get_param( 'message' );
    $attachment_id = $request->get_param( 'attachment_id' );
    
    if ( ! $session_id || ! $message ) {
        return new WP_Error( 'missing_param', 'Session ID and message required', array( 'status' => 400 ) );
    }
    
    $session = pax_sup_get_liveagent_session( $session_id );
    
    if ( ! $session ) {
        return new WP_Error( 'not_found', 'Session not found', array( 'status' => 404 ) );
    }

    if ( ! in_array( $session['status'], array( 'accepted', 'active' ), true ) ) {
        return new WP_Error( 'invalid_status', 'Session not active', array( 'status' => 400 ) );
    }

    $is_agent = current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' );
    $current_user = get_current_user_id();

    if ( ! $is_agent ) {
        if ( (int) $session['user_id'] !== (int) $current_user ) {
            return new WP_Error( 'forbidden', __( 'You are not allowed to post to this session.', 'pax-support-pro' ), array( 'status' => 403 ) );
        }
    }
    
    $message_payload = array(
        'sender'  => $is_agent ? 'agent' : 'user',
        'message' => sanitize_textarea_field( $message ),
    );
    
    if ( $attachment_id ) {
        $attachment = get_post( (int) $attachment_id );
        if ( $attachment && 'attachment' === $attachment->post_type ) {
            $message_payload['attachment'] = array(
                'id'       => (int) $attachment_id,
                'url'      => wp_get_attachment_url( $attachment_id ),
                'filename' => basename( get_attached_file( $attachment_id ) ),
            );
        }
    }

    $added = pax_sup_add_liveagent_message( $session_id, $message_payload );

    if ( ! $added ) {
        return new WP_Error( 'db_error', 'Failed to save message', array( 'status' => 500 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    $new_message = end( $session['messages'] );

    return rest_ensure_response( array(
        'success' => true,
        'message' => $new_message,
    ) );
}

function pax_notify_admin_live_agent_request( $session_id, $session_data ) {
    $admin_email = get_option( 'admin_email' );
    $site_name = get_bloginfo( 'name' );
    $rest_url = rest_url( 'pax/v1/live/accept' );
    
    $subject = sprintf( '[%s] New Live Agent Request', $site_name );
    
    $message = sprintf(
        "A new live agent request has been received.\n\n" .
        "User: %s\n" .
        "Email: %s\n" .
        "Session ID: %d\n\n" .
        "To accept this request, use the Live Agent Center in your WordPress admin.\n\n" .
        "REST API URL: %s\n" .
        "Session Link: %s",
        $session_data['user_name'],
        $session_data['user_email'],
        $session_id,
        $rest_url,
        admin_url( 'admin.php?page=pax-support-live-agent&session=' . $session_id )
    );
    
    wp_mail( $admin_email, $subject, $message );
    
    set_transient( 'pax_live_agent_pending_' . $session_id, array(
        'session_id' => $session_id,
        'user_name'  => $session_data['user_name'],
        'timestamp'  => time(),
    ), HOUR_IN_SECONDS );
}
