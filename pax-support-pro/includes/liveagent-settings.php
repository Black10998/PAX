<?php
/**
 * Live Agent Settings Management
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get Live Agent settings with defaults
 */
function pax_sup_get_liveagent_settings() {
    $defaults = array(
        'enabled' => false,
        'auto_accept' => false,
        'max_concurrent_chats' => 5,
        'auto_close_minutes' => 30,
        'timeout_seconds' => 60,
        'allow_file_uploads' => true,
        'max_file_size_mb' => 10,
        'allowed_file_types' => array( 'jpg', 'png', 'gif', 'pdf', 'doc', 'docx' ),
        'sound_enabled' => true,
        'email_notifications' => true,
        'notification_email' => get_option( 'admin_email' ),
        'browser_notifications' => true,
        'button_position' => 'bottom-right',
        'button_text' => __( 'Live Agent', 'pax-support-pro' ),
        'welcome_message' => __( 'Hello! How can we help you today?', 'pax-support-pro' ),
        'cloudflare_mode' => false,
        'poll_interval' => 15,
        'message_history_limit' => 100,
        'archive_after_days' => 30,
    );

    return wp_parse_args( get_option( 'pax_liveagent_settings', array() ), $defaults );
}

/**
 * Normalize allowed file types.
 *
 * @param mixed $value Raw value from settings.
 * @return array
 */
function pax_sup_normalize_liveagent_file_types( $value ) {
    if ( is_string( $value ) ) {
        $value = array_map( 'trim', explode( ',', $value ) );
    }

    if ( ! is_array( $value ) ) {
        return array( 'jpg', 'png', 'gif', 'pdf', 'doc', 'docx' );
    }

    $normalized = array();

    foreach ( $value as $type ) {
        $type = sanitize_key( $type );
        if ( ! empty( $type ) ) {
            $normalized[] = $type;
        }
    }

    if ( empty( $normalized ) ) {
        $normalized = array( 'jpg', 'png', 'gif', 'pdf', 'doc', 'docx' );
    }

    return array_values( array_unique( $normalized ) );
}

/**
 * Save Live Agent settings
 */
function pax_sup_save_liveagent_settings( $input = null ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }

    if ( null === $input ) {
        $input = wp_unslash( $_POST );
    }

    $defaults = pax_sup_get_liveagent_settings();

    $settings = array(
        'enabled'               => ! empty( $input['enabled'] ) ? 1 : 0,
        'auto_accept'           => ! empty( $input['auto_accept'] ) ? 1 : 0,
        'max_concurrent_chats'  => max( 1, min( 50, intval( $input['max_concurrent_chats'] ?? $defaults['max_concurrent_chats'] ) ) ),
        'auto_close_minutes'    => max( 5, min( 240, intval( $input['auto_close_minutes'] ?? $defaults['auto_close_minutes'] ) ) ),
        'timeout_seconds'       => max( 30, min( 600, intval( $input['timeout_seconds'] ?? $defaults['timeout_seconds'] ) ) ),
        'allow_file_uploads'    => ! empty( $input['allow_file_uploads'] ) ? 1 : 0,
        'max_file_size_mb'      => max( 1, min( 100, intval( $input['max_file_size_mb'] ?? $defaults['max_file_size_mb'] ) ) ),
        'allowed_file_types'    => pax_sup_normalize_liveagent_file_types( $input['allowed_file_types'] ?? $defaults['allowed_file_types'] ),
        'sound_enabled'         => ! empty( $input['sound_enabled'] ) ? 1 : 0,
        'email_notifications'   => ! empty( $input['email_notifications'] ) ? 1 : 0,
        'notification_email'    => sanitize_email( $input['notification_email'] ?? $defaults['notification_email'] ),
        'browser_notifications' => ! empty( $input['browser_notifications'] ) ? 1 : 0,
        'button_position'       => in_array( $input['button_position'] ?? $defaults['button_position'], array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ), true )
            ? $input['button_position']
            : $defaults['button_position'],
        'button_text'           => sanitize_text_field( $input['button_text'] ?? $defaults['button_text'] ),
        'welcome_message'       => sanitize_textarea_field( $input['welcome_message'] ?? $defaults['welcome_message'] ),
        'cloudflare_mode'       => ! empty( $input['cloudflare_mode'] ) ? 1 : 0,
        'poll_interval'         => max( 5, min( 120, intval( $input['poll_interval'] ?? $defaults['poll_interval'] ) ) ),
        'message_history_limit' => max( 20, min( 500, intval( $input['message_history_limit'] ?? $defaults['message_history_limit'] ) ) ),
        'archive_after_days'    => max( 1, min( 180, intval( $input['archive_after_days'] ?? $defaults['archive_after_days'] ) ) ),
    );

    if ( empty( $settings['notification_email'] ) ) {
        $settings['notification_email'] = get_option( 'admin_email' );
    }

    update_option( 'pax_liveagent_settings', $settings );

    return $settings;
}

/**
 * Add Live Agent to admin bar
 */
function pax_sup_add_liveagent_admin_bar( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_pax_chats' ) ) {
        return;
    }

    $settings = pax_sup_get_liveagent_settings();
    if ( empty( $settings['enabled'] ) ) {
        return;
    }

    // Get pending count
    $pending_sessions = pax_sup_get_liveagent_sessions_by_status( 'pending' );
    $pending_count = count( $pending_sessions );

    $title = __( 'Live Agent Center', 'pax-support-pro' );
    if ( $pending_count > 0 ) {
        $title .= ' <span class="awaiting-mod count-' . $pending_count . '"><span class="pending-count">' . $pending_count . '</span></span>';
    }

    $wp_admin_bar->add_node( array(
        'id' => 'pax-live-agent-center',
        'title' => $title,
        'href' => admin_url( 'admin.php?page=pax-live-agent-center' ),
        'meta' => array(
            'class' => 'pax-live-agent-admin-bar',
        ),
    ) );
}
add_action( 'admin_bar_menu', 'pax_sup_add_liveagent_admin_bar', 100 );
