<?php
/**
 * Live Agent Message REST API Endpoints
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register message endpoints
 */
function pax_sup_register_liveagent_message_routes() {
    // Send message
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/message/send', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_send_message',
        'permission_callback' => 'pax_sup_check_message_permission',
    ) );

    // Mark messages as read
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/message/mark-read', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_mark_read',
        'permission_callback' => 'pax_sup_check_message_permission',
    ) );

    // Get messages
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/messages/(?P<session_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'pax_sup_rest_get_messages',
        'permission_callback' => 'pax_sup_check_message_permission',
    ) );
}
add_action( 'rest_api_init', 'pax_sup_register_liveagent_message_routes' );

/**
 * Send message
 */
function pax_sup_rest_send_message( $request ) {
    pax_sup_liveagent_nocache_headers();

    $proxy = new WP_REST_Request( 'POST', '/pax/v1/live/message' );
    $proxy->set_param( 'session_id', $request->get_param( 'session_id' ) );
    $proxy->set_param( 'message', $request->get_param( 'message' ) );

    if ( $request->get_param( 'reply_to' ) ) {
        $proxy->set_param( 'reply_to', $request->get_param( 'reply_to' ) );
    }

    if ( $request->get_param( 'attachment_id' ) ) {
        $proxy->set_param( 'attachment_id', $request->get_param( 'attachment_id' ) );
    }

    return pax_live_agent_message( $proxy );
}

/**
 * Mark messages as read
 */
function pax_sup_rest_mark_read( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );
    $reader_type = $request->get_param( 'reader_type' );

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    // Determine reader type
    $is_agent = current_user_can( 'manage_pax_chats' );
    $reader_type = $is_agent ? 'agent' : 'user';

    $marked = pax_sup_mark_liveagent_messages_read( $session_id, $reader_type );

    return new WP_REST_Response( array(
        'success' => true,
        'marked_count' => $marked ? 1 : 0,
    ), 200 );
}

/**
 * Get messages
 */
function pax_sup_rest_get_messages( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = (int) $request->get_param( 'session_id' );
    $limit      = (int) $request->get_param( 'limit' );
    $limit      = $limit > 0 ? min( $limit, 500 ) : 100;
    $after_id   = (int) $request->get_param( 'after' );
    $if_match   = trim( (string) $request->get_header( 'If-None-Match' ) );

    if ( $session_id <= 0 ) {
        return new WP_Error( 'invalid_session', __( 'Invalid session ID', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    $messages = array();
    if ( ! empty( $session['messages'] ) && is_array( $session['messages'] ) ) {
        foreach ( $session['messages'] as $message ) {
            $prepared = pax_sup_prepare_liveagent_message_for_response( $message );
            if ( null !== $prepared ) {
                $messages[] = $prepared;
            }
        }
    }

    $filtered = array();
    $last_id  = 0;

    foreach ( $messages as $message ) {
        $message_id = isset( $message['id'] ) ? (int) $message['id'] : 0;
        $last_id    = max( $last_id, $message_id );
        if ( $after_id > 0 && $message_id <= $after_id ) {
            continue;
        }

        $filtered[] = $message;
    }

    $total_messages = count( $messages );
    $filtered       = array_slice( $filtered, -$limit );
    $has_more       = false;

    if ( $after_id > 0 ) {
        $newer_total = 0;
        foreach ( $messages as $message ) {
            $message_id = isset( $message['id'] ) ? (int) $message['id'] : 0;
            if ( $message_id > $after_id ) {
                $newer_total++;
            }
        }
        $has_more = $newer_total > count( $filtered );
    } else {
        $has_more = $total_messages > count( $filtered );
    }

    $etag_seed = implode(
        '|',
        array(
            $session_id,
            $last_id,
            $total_messages,
            $session['status'],
            $session['last_activity'],
        )
    );
    $etag = 'W/"' . hash( 'sha256', $etag_seed ) . '"';

    if ( $if_match && $if_match === $etag && empty( $filtered ) && $after_id >= $last_id ) {
        $not_modified = new WP_REST_Response( null, 304 );
        $not_modified->header( 'ETag', $etag );
        return $not_modified;
    }

    $response = new WP_REST_Response( array(
        'success'         => true,
        'messages'        => array_values( $filtered ),
        'has_more'        => $has_more,
        'last_id'         => $last_id,
        'last_message_id' => $last_id,
        'status'          => sanitize_key( $session['status'] ),
    ), 200 );

    $response->header( 'ETag', $etag );

    return $response;
}

/**
 * Check message permission
 */
function pax_sup_check_message_permission( $request ) {
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
 * Notify about new message
 */
function pax_sup_notify_new_message( $session_id, $message, $sender ) {
    $settings = get_option( 'pax_liveagent_settings', array() );
    
    if ( empty( $settings['email_notifications'] ) ) {
        return;
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    
    // Notify agent if user sent message
    if ( $sender === 'user' && $session['agent_id'] ) {
        $agent = get_userdata( $session['agent_id'] );
        if ( $agent ) {
            $subject = sprintf( __( '[PAX Support] New message in chat #%d', 'pax-support-pro' ), $session_id );
            $email_message = sprintf(
                __( "New message from %s:\n\n%s\n\nView chat: %s", 'pax-support-pro' ),
                $session['user_name'],
                wp_trim_words( $message['message'], 50 ),
                admin_url( 'admin.php?page=pax-live-agent-center&session=' . $session_id )
            );
            wp_mail( $agent->user_email, $subject, $email_message );
        }
    }
}

/**
 * Prepare message response payload
 *
 * @param array $message Raw message data.
 * @return array|null
 */
function pax_sup_prepare_liveagent_message_for_response( $message ) {
    if ( empty( $message ) || ! is_array( $message ) ) {
        return null;
    }

    $prepared = array(
        'id'        => isset( $message['id'] ) ? (int) $message['id'] : 0,
        'sender'    => isset( $message['sender'] ) ? sanitize_key( $message['sender'] ) : 'system',
        'message'   => isset( $message['message'] ) ? wp_kses_post( $message['message'] ) : '',
        'timestamp' => isset( $message['timestamp'] ) ? sanitize_text_field( $message['timestamp'] ) : current_time( 'mysql' ),
        'read'      => ! empty( $message['read'] ),
    );

    if ( ! empty( $message['attachment'] ) && is_array( $message['attachment'] ) ) {
        $prepared['attachment'] = array(
            'url'      => isset( $message['attachment']['url'] ) ? esc_url_raw( $message['attachment']['url'] ) : '',
            'filename' => isset( $message['attachment']['filename'] ) ? sanitize_file_name( $message['attachment']['filename'] ) : '',
            'id'       => isset( $message['attachment']['id'] ) ? (int) $message['attachment']['id'] : 0,
        );
    }

    if ( isset( $message['meta'] ) && is_array( $message['meta'] ) ) {
        $prepared['meta'] = array_map( 'sanitize_text_field', $message['meta'] );
    }

    return $prepared;
}
