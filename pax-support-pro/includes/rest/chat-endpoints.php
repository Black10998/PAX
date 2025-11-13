<?php
/**
 * Unified Chat REST API Endpoints
 * Handles Assistant mode
 *
 * @package PAX_Support_Pro
 * @version 6.5.1
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

    // Route to assistant mode
    return pax_sup_unified_send_assistant( $message, $reply_to, $params );
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
 * Get messages for unified chat
 */
function pax_sup_rest_unified_messages( WP_REST_Request $request ) {
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
 * Unified session management
 */
function pax_sup_rest_unified_session( WP_REST_Request $request ) {
    // Assistant mode does not require session management
    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => __( 'Assistant mode does not require session management', 'pax-support-pro' ),
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

    return new WP_REST_Response(
        array(
            'success' => true,
            'assistant' => $assistant_status,
        ),
        200
    );
}


