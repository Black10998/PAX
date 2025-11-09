<?php
/**
 * Unified Chat REST API Endpoints
 * Handles both Assistant and Live Agent modes
 *
 * @package PAX_Support_Pro
 * @version 5.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register unified chat endpoints
 */
function pax_sup_register_unified_chat_endpoints() {
    // Unified send message endpoint
    register_rest_route(
        PAX_SUP_REST_NS,
        '/unified/send',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_read_permission',
            'callback'            => 'pax_sup_rest_unified_send',
        )
    );

    // Unified get messages endpoint
    register_rest_route(
        PAX_SUP_REST_NS,
        '/unified/messages',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => 'pax_sup_rest_require_read_permission',
            'callback'            => 'pax_sup_rest_unified_messages',
        )
    );

    // Unified session management
    register_rest_route(
        PAX_SUP_REST_NS,
        '/unified/session',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_read_permission',
            'callback'            => 'pax_sup_rest_unified_session',
        )
    );

    // Unified status endpoint
    register_rest_route(
        PAX_SUP_REST_NS,
        '/unified/status',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => 'pax_sup_rest_require_read_permission',
            'callback'            => 'pax_sup_rest_unified_status',
        )
    );
}
add_action( 'rest_api_init', 'pax_sup_register_unified_chat_endpoints' );

/**
 * Unified send message handler
 */
function pax_sup_rest_unified_send( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $mode = isset( $params['mode'] ) ? sanitize_text_field( $params['mode'] ) : 'assistant';
    $message = isset( $params['message'] ) ? $params['message'] : '';
    $session_id = isset( $params['sessionId'] ) ? intval( $params['sessionId'] ) : null;
    $reply_to = isset( $params['replyTo'] ) ? sanitize_text_field( $params['replyTo'] ) : null;

    // Validate message
    if ( empty( $message ) || strlen( $message ) > 5000 ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Message is required and must be less than 5000 characters', 'pax-support-pro' ),
            ),
            400
        );
    }

    // Route based on mode
    if ( $mode === 'liveagent' ) {
        return pax_sup_unified_send_liveagent( $message, $session_id, $reply_to );
    } else {
        return pax_sup_unified_send_assistant( $message, $reply_to, $params );
    }
}

/**
 * Send message to Assistant
 */
function pax_sup_unified_send_assistant( $message, $reply_to, $params ) {
    $options = pax_sup_get_options();
    
    // Trim message
    $message = pax_sup_trim( $message, 1500 );

    // Get language
    $lang_param = isset( $params['lang'] ) ? sanitize_text_field( $params['lang'] ) : '';
    $language = pax_sup_detect_user_language( $lang_param );

    // Security check
    $bad_phrases = array( '<script', '</script', '<?php', 'union select', 'drop table', 'base64_' );
    $lower = mb_strtolower( $message );

    foreach ( $bad_phrases as $phrase ) {
        if ( false !== strpos( $lower, $phrase ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'reply' => __( 'Blocked input.', 'pax-support-pro' ),
                    'status' => 'blocked',
                    'mode' => 'assistant',
                ),
                200
            );
        }
    }

    // Check if AI is enabled
    if ( empty( $options['ai_assistant_enabled'] ) ) {
        return new WP_REST_Response(
            array(
                'success' => true,
                'reply' => __( 'AI Assistant is currently disabled. Please contact support.', 'pax-support-pro' ),
                'mode' => 'assistant',
                'messageId' => time(),
            ),
            200
        );
    }

    // Call AI assistant
    $ai_response = pax_sup_call_openai_api( $message, $language );

    if ( is_wp_error( $ai_response ) ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'reply' => __( 'Sorry, I encountered an error. Please try again.', 'pax-support-pro' ),
                'mode' => 'assistant',
            ),
            200
        );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'reply' => $ai_response,
            'mode' => 'assistant',
            'messageId' => time(),
            'language' => $language,
        ),
        200
    );
}

/**
 * Send message to Live Agent
 */
function pax_sup_unified_send_liveagent( $message, $session_id, $reply_to ) {
    if ( ! $session_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'No active Live Agent session', 'pax-support-pro' ),
            ),
            400
        );
    }

    // Get session
    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Session not found', 'pax-support-pro' ),
            ),
            404
        );
    }

    // Check session status
    if ( $session['status'] !== 'active' && $session['status'] !== 'pending' ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Session is not active', 'pax-support-pro' ),
            ),
            400
        );
    }

    // Determine sender
    $is_agent = current_user_can( 'manage_pax_chats' );
    $sender = $is_agent ? 'agent' : 'user';

    // Insert message
    $message_id = pax_sup_insert_liveagent_message(
        $session_id,
        $sender,
        sanitize_textarea_field( $message ),
        $reply_to
    );

    if ( ! $message_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Failed to send message', 'pax-support-pro' ),
            ),
            500
        );
    }

    // Update session activity
    pax_sup_update_liveagent_session_activity( $session_id );

    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => __( 'Message sent', 'pax-support-pro' ),
            'messageId' => $message_id,
            'mode' => 'liveagent',
        ),
        200
    );
}

/**
 * Get messages for unified chat
 */
function pax_sup_rest_unified_messages( WP_REST_Request $request ) {
    $mode = $request->get_param( 'mode' );
    $session_id = $request->get_param( 'sessionId' );

    if ( $mode === 'liveagent' && $session_id ) {
        return pax_sup_unified_get_liveagent_messages( $session_id );
    }

    // Assistant mode doesn't store messages server-side
    return new WP_REST_Response(
        array(
            'success' => true,
            'messages' => array(),
            'mode' => 'assistant',
        ),
        200
    );
}

/**
 * Get Live Agent messages
 */
function pax_sup_unified_get_liveagent_messages( $session_id ) {
    $session = pax_sup_get_liveagent_session( $session_id );
    
    if ( ! $session ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Session not found', 'pax-support-pro' ),
            ),
            404
        );
    }

    $messages = pax_sup_get_liveagent_messages( $session_id );
    
    // Format messages for frontend
    $formatted_messages = array();
    foreach ( $messages as $msg ) {
        $formatted_messages[] = array(
            'id' => $msg['id'],
            'text' => $msg['message'],
            'sender' => $msg['sender'],
            'timestamp' => $msg['created_at'],
            'replyTo' => $msg['reply_to'],
            'attachment' => isset( $msg['attachment'] ) ? $msg['attachment'] : null,
        );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'messages' => $formatted_messages,
            'mode' => 'liveagent',
            'status' => $session['status'],
            'agent' => isset( $session['agent_id'] ) ? pax_sup_get_agent_info( $session['agent_id'] ) : null,
        ),
        200
    );
}

/**
 * Unified session management
 */
function pax_sup_rest_unified_session( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : '';
    $mode = isset( $params['mode'] ) ? sanitize_text_field( $params['mode'] ) : 'assistant';

    if ( $mode !== 'liveagent' ) {
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Assistant mode does not require session management', 'pax-support-pro' ),
            ),
            200
        );
    }

    switch ( $action ) {
        case 'create':
            return pax_sup_unified_create_liveagent_session();
        
        case 'close':
            $session_id = isset( $params['sessionId'] ) ? intval( $params['sessionId'] ) : null;
            return pax_sup_unified_close_liveagent_session( $session_id );
        
        default:
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => __( 'Invalid action', 'pax-support-pro' ),
                ),
                400
            );
    }
}

/**
 * Create Live Agent session
 */
function pax_sup_unified_create_liveagent_session() {
    $user_id = get_current_user_id();
    
    if ( ! $user_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'You must be logged in to start a Live Agent session', 'pax-support-pro' ),
            ),
            401
        );
    }

    // Check for existing active session
    $existing = pax_sup_get_user_active_liveagent_session( $user_id );
    if ( $existing ) {
        return new WP_REST_Response(
            array(
                'success' => true,
                'session_id' => $existing['id'],
                'status' => $existing['status'],
                'message' => __( 'Using existing session', 'pax-support-pro' ),
            ),
            200
        );
    }

    // Create new session
    $session_id = pax_sup_create_liveagent_session( $user_id );
    
    if ( ! $session_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Failed to create session', 'pax-support-pro' ),
            ),
            500
        );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'session_id' => $session_id,
            'status' => 'pending',
            'message' => __( 'Session created', 'pax-support-pro' ),
        ),
        200
    );
}

/**
 * Close Live Agent session
 */
function pax_sup_unified_close_liveagent_session( $session_id ) {
    if ( ! $session_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Session ID required', 'pax-support-pro' ),
            ),
            400
        );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Session not found', 'pax-support-pro' ),
            ),
            404
        );
    }

    // Update session status
    $updated = pax_sup_update_liveagent_session_status( $session_id, 'closed' );
    
    if ( ! $updated ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Failed to close session', 'pax-support-pro' ),
            ),
            500
        );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => __( 'Session closed', 'pax-support-pro' ),
        ),
        200
    );
}

/**
 * Get unified status
 */
function pax_sup_rest_unified_status( WP_REST_Request $request ) {
    $options = pax_sup_get_options();
    
    // Assistant status
    $assistant_status = array(
        'enabled' => ! empty( $options['ai_assistant_enabled'] ),
        'available' => ! empty( $options['ai_assistant_enabled'] ),
    );

    // Live Agent status
    $liveagent_status = array(
        'enabled' => ! empty( $options['live_agent_enabled'] ),
        'online' => pax_sup_is_agent_online(),
        'sessions' => array(
            'pending' => count( pax_sup_get_liveagent_sessions_by_status( 'pending' ) ),
            'active' => count( pax_sup_get_liveagent_sessions_by_status( 'active' ) ),
        ),
    );

    return new WP_REST_Response(
        array(
            'success' => true,
            'assistant' => $assistant_status,
            'liveagent' => $liveagent_status,
        ),
        200
    );
}

/**
 * Helper: Get agent info
 */
function pax_sup_get_agent_info( $agent_id ) {
    $user = get_userdata( $agent_id );
    
    if ( ! $user ) {
        return null;
    }

    return array(
        'id' => $user->ID,
        'name' => $user->display_name,
        'avatar' => get_avatar_url( $user->ID, array( 'size' => 64 ) ),
    );
}

/**
 * Helper: Check if any agent is online
 */
function pax_sup_is_agent_online() {
    // Check if any user with manage_pax_chats capability has been active in last 5 minutes
    $users = get_users( array(
        'capability' => 'manage_pax_chats',
        'number' => 10,
    ) );

    foreach ( $users as $user ) {
        $last_activity = get_user_meta( $user->ID, 'pax_last_activity', true );
        if ( $last_activity && ( time() - $last_activity ) < 300 ) {
            return true;
        }
    }

    return false;
}
