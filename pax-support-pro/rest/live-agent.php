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
            return current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' );
        },
    ) );

    register_rest_route( 'pax/v1', '/live/decline', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_decline',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' );
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

    if ( $request->get_param( 'healthcheck' ) ) {
        return rest_ensure_response( array(
            'status'    => 'ok',
            'timestamp' => current_time( 'mysql' ),
        ) );
    }

    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', 'Session ID required', array( 'status' => 400 ) );
    }

    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    $session = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $session_id
        ),
        ARRAY_A
    );

    if ( ! $session ) {
        return new WP_Error( 'not_found', 'Session not found', array( 'status' => 404 ) );
    }

    $status = $session['status'];
    if ( 'accepted' === $status ) {
        $status = 'active';
    }

    $response_status = $status;
    if ( 'active' === $status ) {
        $response_status = 'accepted';
    } elseif ( 'closed' === $status ) {
        $response_status = $session['agent_id'] ? 'closed' : 'declined';
    }

    $response = array(
        'status' => $response_status,
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
    check_ajax_referer( 'wp_rest', '_wpnonce' );
    
    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', 'Session ID required', array( 'status' => 400 ) );
    }
    
    $agent_id = get_current_user_id();

    $updated = pax_sup_update_liveagent_session_status( $session_id, 'active', $agent_id );
    
    if ( false === $updated ) {
        return new WP_Error( 'db_error', 'Failed to accept session', array( 'status' => 500 ) );
    }
    
    return rest_ensure_response( array(
        'success' => true,
        'status'  => 'accepted',
    ) );
}

function pax_live_agent_decline( $request ) {
    global $wpdb;
    
    check_ajax_referer( 'wp_rest', '_wpnonce' );
    
    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', 'Session ID required', array( 'status' => 400 ) );
    }
    
    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    
    $updated = $wpdb->update(
        $table,
        array(
            'status'        => 'closed',
            'last_activity' => current_time( 'mysql' ),
            'ended_at'      => current_time( 'mysql' ),
        ),
        array( 'id' => $session_id ),
        array( '%s', '%s', '%s' ),
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
    $session_id     = (int) $request->get_param( 'session_id' );
    $raw_message    = $request->get_param( 'message' );
    $reply_to       = $request->get_param( 'reply_to' );
    $attachment_id  = $request->get_param( 'attachment_id' );

    if ( ! $session_id || '' === trim( (string) $raw_message ) ) {
        return new WP_Error( 'missing_param', 'Session ID and message required', array( 'status' => 400 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', 'Session not found', array( 'status' => 404 ) );
    }

    $current_status = $session['status'];
    if ( 'accepted' === $current_status ) {
        $current_status = 'active';
    }

    $current_user_id = get_current_user_id();
    $is_agent        = current_user_can( 'manage_pax_chats' );
    $sender          = $is_agent ? 'agent' : 'user';

    if ( $is_agent ) {
        if ( empty( $session['agent_id'] ) ) {
            pax_sup_update_liveagent_session_status( $session_id, 'active', $current_user_id );
            $session['agent_id'] = $current_user_id;
            $current_status      = 'active';
        } elseif ( (int) $session['agent_id'] !== $current_user_id ) {
            return new WP_Error( 'not_assigned', __( 'This session is assigned to another agent.', 'pax-support-pro' ), array( 'status' => 403 ) );
        }

        if ( 'active' !== $current_status ) {
            return new WP_Error( 'invalid_status', __( 'Session is not active.', 'pax-support-pro' ), array( 'status' => 400 ) );
        }
    } else {
        if ( 'closed' === $current_status ) {
            return new WP_Error( 'invalid_status', __( 'Session is closed.', 'pax-support-pro' ), array( 'status' => 400 ) );
        }
    }

    $message_text = sanitize_textarea_field( $raw_message );
    if ( '' === $message_text ) {
        return new WP_Error( 'invalid_message', __( 'Message cannot be empty.', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $message_data = array(
        'sender'  => $sender,
        'message' => $message_text,
    );

    if ( $reply_to ) {
        $message_data['reply_to'] = sanitize_text_field( $reply_to );
    }

    if ( $attachment_id ) {
        $attachment_id = (int) $attachment_id;
        $attachment    = get_post( $attachment_id );

        if ( $attachment && 'attachment' === $attachment->post_type ) {
            $message_data['attachment'] = array(
                'id'       => $attachment_id,
                'url'      => wp_get_attachment_url( $attachment_id ),
                'filename' => basename( get_attached_file( $attachment_id ) ),
            );
        }
    }

    if ( 'agent' === $sender ) {
        $message_data['agent_id'] = $current_user_id;
    } elseif ( ! empty( $session['user_id'] ) ) {
        $message_data['user_id'] = (int) $session['user_id'];
    }

    $added = pax_sup_add_liveagent_message( $session_id, $message_data );

    if ( ! $added ) {
        return new WP_Error( 'db_error', __( 'Failed to save message.', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    $updated_session = pax_sup_get_liveagent_session( $session_id );
    $messages        = $updated_session['messages'];
    $new_message     = end( $messages );

    pax_sup_notify_new_message( $session_id, $new_message, $sender );

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
