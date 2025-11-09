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

    $session_id = $request->get_param( 'session_id' );
    $message = $request->get_param( 'message' );
    $sender = $request->get_param( 'sender' );
    $reply_to = $request->get_param( 'reply_to' );
    $attachment_id = $request->get_param( 'attachment_id' );

    // Validate
    if ( empty( $message ) || strlen( $message ) > 5000 ) {
        return new WP_Error( 'invalid_message', __( 'Message is required and must be less than 5000 characters', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    if ( $session['status'] !== 'active' ) {
        return new WP_Error( 'invalid_status', __( 'Session is not active', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    // Determine sender
    $is_agent = current_user_can( 'manage_pax_chats' );
    $sender = $is_agent ? 'agent' : 'user';

    // Build message data
    $message_data = array(
        'sender' => $sender,
        'message' => sanitize_textarea_field( $message ),
        'reply_to' => $reply_to ? sanitize_text_field( $reply_to ) : null,
    );

    // Add attachment if provided
    if ( $attachment_id ) {
        $attachment = get_post( $attachment_id );
        if ( $attachment && $attachment->post_type === 'attachment' ) {
            $message_data['attachment'] = array(
                'id' => $attachment_id,
                'url' => wp_get_attachment_url( $attachment_id ),
                'filename' => basename( get_attached_file( $attachment_id ) ),
            );
        }
    }

    $added = pax_sup_add_liveagent_message( $session_id, $message_data );

    if ( ! $added ) {
        return new WP_Error( 'send_failed', __( 'Failed to send message', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    // Get the added message
    $session = pax_sup_get_liveagent_session( $session_id );
    $messages = $session['messages'];
    $new_message = end( $messages );

    // Send notification
    pax_sup_notify_new_message( $session_id, $new_message, $sender );

    return new WP_REST_Response( array(
        'success' => true,
        'message' => $new_message,
    ), 201 );
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

    $session_id = $request->get_param( 'session_id' );
    $since = $request->get_param( 'since' );
    $limit = $request->get_param( 'limit' ) ?: 100;

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    $messages = $session['messages'];

    // Filter by timestamp if provided
    if ( $since ) {
        $since_timestamp = strtotime( $since );
        $messages = array_filter( $messages, function( $msg ) use ( $since_timestamp ) {
            return strtotime( $msg['timestamp'] ) > $since_timestamp;
        } );
    }

    // Limit results
    $messages = array_slice( $messages, -$limit );

    return new WP_REST_Response( array(
        'success' => true,
        'messages' => array_values( $messages ),
        'has_more' => count( $session['messages'] ) > count( $messages ),
    ), 200 );
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
