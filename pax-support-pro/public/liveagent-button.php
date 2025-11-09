<?php
/**
 * Live Agent Button Frontend
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue Live Chat frontend assets
 */
function pax_sup_enqueue_livechat_frontend() {
    // Check if Live Agent System is enabled
    $options = pax_sup_get_options();
    
    // Only skip if explicitly disabled (not if just not set)
    if ( isset( $options['live_agent_enabled'] ) && $options['live_agent_enabled'] === 0 ) {
        return;
    }

    // Check chat access control settings
    $chat_access = $options['chat_access_control'] ?? 'everyone';
    
    // If disabled globally, don't load Live Chat
    if ( $chat_access === 'disabled' ) {
        return;
    }

    // Show for all visitors (logged-in and logged-out) on all pages

    // Enqueue styles
    wp_enqueue_style(
        'pax-livechat-frontend',
        PAX_SUP_URL . 'public/css/livechat-frontend.css',
        array(),
        PAX_SUP_VER
    );

    // Enqueue new Live Chat Engine (v5.1.0) - Modern async/await, no jQuery dependency
    wp_enqueue_script(
        'pax-livechat-engine',
        PAX_SUP_URL . 'public/js/livechat-engine.js',
        array(), // No dependencies - pure vanilla JS
        PAX_SUP_VER,
        true // Load in footer
    );
    
    // Add defer attribute for better performance
    add_filter( 'script_loader_tag', function( $tag, $handle ) {
        if ( 'pax-livechat-engine' === $handle ) {
            return str_replace( ' src', ' defer src', $tag );
        }
        return $tag;
    }, 10, 2 );

    $is_logged_in = is_user_logged_in();
    $user_id = $is_logged_in ? get_current_user_id() : 0;
    $user = $is_logged_in ? wp_get_current_user() : null;

    // Auto-detect login plugin and get appropriate login URL
    $login_url = pax_sup_get_login_url();

    // Localize script with configuration
    wp_localize_script(
        'pax-livechat-engine',
        'paxLiveChat',
        array(
            'enabled' => true,
            'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'restUrl' => rest_url( PAX_SUP_REST_NS ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'isLoggedIn' => $is_logged_in,
            'userId' => $user_id,
            'userName' => $user ? $user->display_name : '',
            'userEmail' => $user ? $user->user_email : '',
            'userAvatar' => $user ? get_avatar_url( $user_id ) : '',
            'buttonPosition' => $options['launcher_position'] ?? 'bottom-right',
            'welcomeMessage' => $options['welcome_message'] ?? __( 'Hello! How can we help you today?', 'pax-support-pro' ),
            'loginUrl' => $login_url,
            'strings' => array(
                'liveAgent' => __( 'Live Agent', 'pax-support-pro' ),
                'online' => __( 'Online', 'pax-support-pro' ),
                'offline' => __( 'Offline', 'pax-support-pro' ),
                'connecting' => __( 'Connecting...', 'pax-support-pro' ),
                'pleaseWait' => __( 'Please wait', 'pax-support-pro' ),
                'connectingAgent' => __( 'Connecting you to an agent...', 'pax-support-pro' ),
                'cancel' => __( 'Cancel', 'pax-support-pro' ),
                'agentJoined' => __( 'Agent has joined the chat', 'pax-support-pro' ),
                'agentTyping' => __( 'Agent is typing...', 'pax-support-pro' ),
                'requestSent' => __( 'Request sent successfully', 'pax-support-pro' ),
                'sessionRestored' => __( 'Session restored', 'pax-support-pro' ),
                'requestDeclined' => __( 'Agent declined the request', 'pax-support-pro' ),
                'noResponse' => __( 'No response from agents. Please try again later.', 'pax-support-pro' ),
                'loginRequired' => __( 'Login Required', 'pax-support-pro' ),
                'loginMessage' => __( 'Please log in to start a live chat with our support team.', 'pax-support-pro' ),
                'login' => __( 'Log In', 'pax-support-pro' ),
                'typeMessage' => __( 'Type your message...', 'pax-support-pro' ),
                'send' => __( 'Send', 'pax-support-pro' ),
                'endSession' => __( 'End Session', 'pax-support-pro' ),
                'confirmEnd' => __( 'Are you sure you want to end this chat session?', 'pax-support-pro' ),
                'sessionEnded' => __( 'Chat session ended', 'pax-support-pro' ),
                'close' => __( 'Close', 'pax-support-pro' ),
                'loadingMessages' => __( 'Loading messages...', 'pax-support-pro' ),
                'startConversation' => __( 'Start the conversation by sending a message.', 'pax-support-pro' ),
                'errorOccurred' => __( 'An error occurred. Please try again.', 'pax-support-pro' ),
                'newMessage' => __( 'New message from agent', 'pax-support-pro' ),
                'uploading' => __( 'Uploading file...', 'pax-support-pro' ),
                'fileUploaded' => __( 'File uploaded successfully', 'pax-support-pro' ),
                'uploadFailed' => __( 'File upload failed', 'pax-support-pro' ),
                'fileTooLarge' => __( 'File is too large. Maximum size is 10MB.', 'pax-support-pro' ),
                'retryAvailable' => __( 'Click the button to try again', 'pax-support-pro' ),
            ),
        )
    );

    // Debug log to confirm script is enqueued
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[PAX Live Chat] Frontend script enqueued on: ' . $_SERVER['REQUEST_URI'] );
    }
}
add_action( 'wp_enqueue_scripts', 'pax_sup_enqueue_livechat_frontend' );

/**
 * Get login URL with auto-detection for login plugins
 */
function pax_sup_get_login_url() {
    $current_url = home_url( add_query_arg( null, null ) );
    
    // Check for ProfilePress
    if ( function_exists( 'ppress_login_url' ) ) {
        return ppress_login_url( $current_url );
    }
    
    // Check for Ultimate Member
    if ( function_exists( 'um_get_core_page' ) ) {
        $login_page = um_get_core_page( 'login' );
        if ( $login_page ) {
            return add_query_arg( 'redirect_to', urlencode( $current_url ), get_permalink( $login_page ) );
        }
    }
    
    // Check for WooCommerce
    if ( function_exists( 'wc_get_page_permalink' ) ) {
        $myaccount_page = wc_get_page_permalink( 'myaccount' );
        if ( $myaccount_page ) {
            return add_query_arg( 'redirect_to', urlencode( $current_url ), $myaccount_page );
        }
    }
    
    // Check for MemberPress
    if ( class_exists( 'MeprOptions' ) ) {
        $mepr_options = MeprOptions::fetch();
        if ( $mepr_options && $mepr_options->login_page_id ) {
            return add_query_arg( 'redirect_to', urlencode( $current_url ), get_permalink( $mepr_options->login_page_id ) );
        }
    }
    
    // Default WordPress login
    return wp_login_url( $current_url );
}

/**
 * Add Live Chat button to frontend
 * Button is injected by JavaScript
 */
function pax_sup_add_livechat_button() {
    // Check if Live Agent System is enabled
    $options = pax_sup_get_options();
    
    if ( empty( $options['live_agent_enabled'] ) ) {
        return;
    }

    // Show for all visitors (logged-in and logged-out)
    // Button will be injected by JavaScript
    // This hook ensures the DOM is ready
}
add_action( 'wp_footer', 'pax_sup_add_livechat_button' );
