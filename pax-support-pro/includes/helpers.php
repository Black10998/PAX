<?php
/**
 * Helper utilities for PAX Support Pro.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_default_options() {
    return array(
        'enabled'              => 1,
        'enable_chat'          => 1,
        'chat_access_control'  => 'everyone',
        'chat_disabled_message' => 'Chat is currently disabled. Please try again later.',
        'disable_chat_menu'    => 0,
        'enable_ticket'        => 1,
        'enable_console'       => 1,
        'enable_offline_guard' => 1,
        'ai_assistant_enabled' => 1,
        'openai_enabled'       => 0,
        'openai_key'           => '',
        'openai_model'         => 'gpt-4o-mini',
        'openai_temperature'   => 0.35,
        'launcher_position'    => 'bottom-left',
        'launcher_auto_open'   => 1,
        'toggle_on_click'      => 1,
        'brand_name'           => 'PAX SUPPORT',
        'color_accent'         => '#e53935',
        'color_bg'             => '#ffffff',
        'color_panel'          => '#f5f5f5',
        'color_border'         => '#e0e0e0',
        'color_text'           => '#212121',
        'color_sub'            => '#757575',
        'live_agent_email'     => get_option( 'admin_email' ),
        'callback_enabled'     => 1,
        'live_agent_enabled'   => 0,
        'help_center_url'      => home_url( '/help/' ),
        'whats_new_url'        => '',
        'donate_url'           => 'https://www.paypal.me/AhmadAlkhalaf29',
        'console_cap'          => 'manage_options',
        'ticket_cooldown_days'   => 3,
        'scheduler_timezone'     => wp_timezone_string(),
        'welcome_message'        => '',
        'welcome_placement'      => 'banner',
        'welcome_alignment'      => 'left',
        'welcome_style'          => 'subtle',
        'welcome_display_rule'   => 'always',
        'welcome_max_lines'      => 3,
        'welcome_show_icon'      => 1,
        'welcome_animation'      => 'fade',
        'welcome_animation_duration' => 300,
        'enable_reply_to'        => 0,
        'enable_quick_actions'   => 1,
        'enable_customization'   => 0,
        'auto_update_enabled'    => 1,
        'update_check_frequency' => 'daily',
        'backup_local_enabled'   => 1,
        'backup_google_drive'    => 0,
        'backup_dropbox'         => 0,
        'chat_menu_items'        => pax_sup_default_menu_items(),
        'pax_chat_custom_menus'  => array(),
        // Chat Reactions
        'chat_reactions_enable_copy'    => 1,
        'chat_reactions_enable_like'    => 1,
        'chat_reactions_enable_dislike' => 1,
        // Chat Animations
        'chat_animations_enabled'       => 1,
        'chat_animation_duration'       => 300,
        'chat_animation_easing'         => 'ease',
    );
}

function pax_sup_default_menu_items() {
    return array(
        'chat' => array(
            'label'   => __( 'Open Chat', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'ticket' => array(
            'label'   => __( 'New Ticket', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'help' => array(
            'label'   => __( 'Help Center', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'livechat' => array(
            'label'   => __( 'Live Chat', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'whatsnew' => array(
            'label'   => __( 'What\'s New', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'troubleshooter' => array(
            'label'   => __( 'Troubleshooter', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'diag' => array(
            'label'   => __( 'Diagnostics', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'callback' => array(
            'label'   => __( 'Request a Callback', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'order' => array(
            'label'   => __( 'Order Lookup', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'myreq' => array(
            'label'   => __( 'My Request', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'feedback' => array(
            'label'   => __( 'Feedback', 'pax-support-pro' ),
            'visible' => 1,
        ),
        'donate' => array(
            'label'   => __( 'Donate', 'pax-support-pro' ),
            'visible' => 1,
        ),
    );
}

function pax_sup_get_options() {
    $saved = get_option( PAX_SUP_OPT_KEY );

    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    return wp_parse_args( $saved, pax_sup_default_options() );
}

function pax_sup_update_options( $new ) {
    $base   = pax_sup_get_options();
    $merged = array_merge( $base, (array) $new );
    update_option( PAX_SUP_OPT_KEY, $merged, false );

    return $merged;
}

/**
 * Reset all plugin settings to factory defaults
 * v5.7.6: Enhanced with better feedback and logging
 */
function pax_sup_reset_settings_to_factory() {
    $old_settings = get_option( PAX_SUP_OPT_KEY );
    delete_option( PAX_SUP_OPT_KEY );
    
    $defaults = pax_sup_default_options();
    
    // Log the reset action
    error_log( 'PAX Support Pro: Settings reset to factory defaults by user ' . get_current_user_id() );
    
    // Return default options
    return $defaults;
}

/**
 * Alias function for easier calling
 * v5.7.6: Added for consistency
 */
function reset_chat_defaults() {
    return pax_sup_reset_settings_to_factory();
}

/**
 * Debug hook to reset settings via URL parameter
 * Usage: Add ?pax_reset_settings=1 to any frontend URL
 * v5.5.7-debug: Added for troubleshooting
 */
add_action( 'init', function() {
    if ( isset( $_GET['pax_reset_settings'] ) && $_GET['pax_reset_settings'] === '1' && current_user_can( 'manage_options' ) ) {
        pax_sup_reset_settings_to_factory();
        wp_die( 'PAX Support Pro settings have been reset to factory defaults. <a href="' . home_url() . '">Go to homepage</a>' );
    }
} );

function pax_sup_ip() {
    foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' ) as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return preg_replace( '/[^0-9a-fA-F\.:]/', '', $raw[0] );
        }
    }

    return '0.0.0.0';
}

function pax_sup_rl( $key, $limit, $ttl ) {
    $transient_key = 'paxsp_rl_' . sanitize_key( $key );
    $count         = (int) get_transient( $transient_key );

    if ( $count >= $limit ) {
        return false;
    }

    set_transient( $transient_key, $count + 1, $ttl );

    return true;
}

function pax_sup_trim( $value, $max ) {
    $value = (string) $value;
    $value = wp_strip_all_tags( $value );

    return mb_substr( $value, 0, $max );
}

/**
 * Ensure REST endpoints are limited to authenticated readers.
 *
 * @return bool
 */
function pax_sup_rest_require_read_permission() {
    return is_user_logged_in() && current_user_can( 'read' );
}

function pax_sup_get_ticket_table() {
    global $wpdb;

    return $wpdb->prefix . 'pax_tickets';
}

function pax_sup_get_ticket_messages_table() {
    global $wpdb;

    return $wpdb->prefix . 'pax_ticket_messages';
}

function pax_sup_get_logs_table() {
    global $wpdb;

    return $wpdb->prefix . 'pax_logs';
}

function pax_sup_get_schedules_table() {
    global $wpdb;

    return $wpdb->prefix . 'pax_schedules';
}

function pax_sup_get_attachments_table() {
    global $wpdb;

    return $wpdb->prefix . 'pax_attachments';
}

function pax_sup_ensure_ticket_tables() {
    static $done = false;

    if ( $done ) {
        return;
    }

    $done   = true;
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset   = $wpdb->get_charset_collate();
    $tickets   = pax_sup_get_ticket_table();
    $messages  = pax_sup_get_ticket_messages_table();
    $logs      = pax_sup_get_logs_table();
    $schedules = pax_sup_get_schedules_table();

    $ticket_sql = "CREATE TABLE {$tickets} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message LONGTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY status (status)
    ) {$charset};";

    $message_sql = "CREATE TABLE {$messages} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ticket_id BIGINT UNSIGNED NOT NULL,
        sender VARCHAR(20) NOT NULL,
        note LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY ticket_id (ticket_id)
    ) {$charset};";

    $logs_sql = "CREATE TABLE {$logs} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        session_id VARCHAR(64) NOT NULL,
        language VARCHAR(32) NOT NULL DEFAULT '',
        keywords TEXT NULL,
        transcript LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY session_id (session_id),
        KEY user_id (user_id)
    ) {$charset};";

    $schedule_sql = "CREATE TABLE {$schedules} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        agent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        schedule_date DATE NOT NULL,
        schedule_time TIME NOT NULL,
        timezone VARCHAR(64) NOT NULL DEFAULT '',
        contact VARCHAR(190) NOT NULL DEFAULT '',
        note TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY agent_status (agent_id, status),
        KEY schedule_lookup (schedule_date, schedule_time, status)
    ) {$charset};";

    $attachments = pax_sup_get_attachments_table();
    $attachments_sql = "CREATE TABLE {$attachments} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ticket_id BIGINT UNSIGNED NOT NULL,
        message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        user_id BIGINT UNSIGNED NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY ticket_id (ticket_id),
        KEY message_id (message_id),
        KEY user_id (user_id)
    ) {$charset};";

    dbDelta( $ticket_sql );
    dbDelta( $message_sql );
    dbDelta( $logs_sql );
    dbDelta( $schedule_sql );
    dbDelta( $attachments_sql );
}

function pax_sup_scheduler_default_settings() {
    return array(
        'timezone'       => wp_timezone_string(),
        'hours'          => array(
            'start' => '09:00',
            'end'   => '17:00',
        ),
        'slots_per_hour' => 2,
        'agents'         => array(),
        'reminder_lead'  => 60,
    );
}

function pax_sup_is_valid_timezone( $timezone ) {
    if ( empty( $timezone ) || ! is_string( $timezone ) ) {
        return false;
    }

    try {
        new DateTimeZone( $timezone );

        return true;
    } catch ( Exception $exception ) {
        return false;
    }
}

function pax_sup_get_scheduler_settings() {
    $stored   = get_option( 'pax_support_scheduler_settings', array() );
    $defaults = pax_sup_scheduler_default_settings();

    if ( ! is_array( $stored ) ) {
        $stored = array();
    }

    $settings = wp_parse_args( $stored, $defaults );

    if ( empty( $settings['timezone'] ) || ! pax_sup_is_valid_timezone( $settings['timezone'] ) ) {
        $settings['timezone'] = $defaults['timezone'];
    }

    $start = isset( $settings['hours']['start'] ) ? $settings['hours']['start'] : $defaults['hours']['start'];
    $end   = isset( $settings['hours']['end'] ) ? $settings['hours']['end'] : $defaults['hours']['end'];

    $settings['hours']['start'] = preg_match( '/^\d{2}:\d{2}$/', $start ) ? $start : $defaults['hours']['start'];
    $settings['hours']['end']   = preg_match( '/^\d{2}:\d{2}$/', $end ) ? $end : $defaults['hours']['end'];

    $settings['slots_per_hour'] = max( 1, (int) $settings['slots_per_hour'] );
    $settings['agents']         = array_filter( array_map( 'absint', (array) $settings['agents'] ) );
    $settings['reminder_lead']  = max( 15, (int) $settings['reminder_lead'] );

    return $settings;
}

function pax_sup_save_scheduler_settings( $settings ) {
    $defaults = pax_sup_scheduler_default_settings();
    $merged   = wp_parse_args( (array) $settings, $defaults );

    if ( empty( $merged['timezone'] ) || ! pax_sup_is_valid_timezone( $merged['timezone'] ) ) {
        $merged['timezone'] = $defaults['timezone'];
    }

    if ( empty( $merged['hours']['start'] ) || ! preg_match( '/^\d{2}:\d{2}$/', $merged['hours']['start'] ) ) {
        $merged['hours']['start'] = $defaults['hours']['start'];
    }

    if ( empty( $merged['hours']['end'] ) || ! preg_match( '/^\d{2}:\d{2}$/', $merged['hours']['end'] ) ) {
        $merged['hours']['end'] = $defaults['hours']['end'];
    }

    $merged['slots_per_hour'] = max( 1, (int) $merged['slots_per_hour'] );
    $merged['agents']         = array_filter( array_map( 'absint', (array) $merged['agents'] ) );
    $merged['reminder_lead']  = max( 15, (int) $merged['reminder_lead'] );

    update_option( 'pax_support_scheduler_settings', $merged, false );

    return $merged;
}

function pax_sup_schedule_datetime_to_timestamp( $date, $time, $timezone ) {
    if ( empty( $date ) || empty( $time ) ) {
        return 0;
    }

    if ( empty( $timezone ) || ! pax_sup_is_valid_timezone( $timezone ) ) {
        $timezone = 'UTC';
    }

    try {
        $datetime = new DateTime( trim( $date . ' ' . $time ), new DateTimeZone( $timezone ) );

        return $datetime->getTimestamp();
    } catch ( Exception $exception ) {
        return 0;
    }
}

function pax_sup_prepare_schedule_row( array $row ) {
    $row['id']            = (int) $row['id'];
    $row['user_id']       = (int) $row['user_id'];
    $row['agent_id']      = (int) $row['agent_id'];
    $row['reminder_sent'] = isset( $row['reminder_sent'] ) ? (int) $row['reminder_sent'] : 0;
    $row['timestamp']     = pax_sup_schedule_datetime_to_timestamp( $row['schedule_date'], $row['schedule_time'], $row['timezone'] );

    return $row;
}

function pax_sup_get_agent_email( $agent_id ) {
    $user = $agent_id ? get_userdata( $agent_id ) : null;

    if ( $user instanceof WP_User && is_email( $user->user_email ) ) {
        return $user->user_email;
    }

    $options = pax_sup_get_options();

    return ! empty( $options['live_agent_email'] ) ? $options['live_agent_email'] : get_option( 'admin_email' );
}

function pax_sup_notify_schedule_event( array $schedule, $context = 'created' ) {
    $schedule    = pax_sup_prepare_schedule_row( $schedule );
    $user        = get_userdata( $schedule['user_id'] );
    $user_email  = $user instanceof WP_User ? $user->user_email : '';
    $agent_email = pax_sup_get_agent_email( $schedule['agent_id'] );

    $timestamp = pax_sup_schedule_datetime_to_timestamp( $schedule['schedule_date'], $schedule['schedule_time'], $schedule['timezone'] );
    $datetime  = $timestamp ? wp_date( 'Y-m-d H:i', $timestamp ) : $schedule['schedule_date'] . ' ' . $schedule['schedule_time'];

    $subject_user  = __( '[PAX] Callback scheduled', 'pax-support-pro' );
    $subject_agent = __( '[PAX] New callback scheduled', 'pax-support-pro' );

    if ( 'updated' === $context ) {
        $subject_user  = __( '[PAX] Callback updated', 'pax-support-pro' );
        $subject_agent = __( '[PAX] Callback updated', 'pax-support-pro' );
    } elseif ( 'canceled' === $context ) {
        $subject_user  = __( '[PAX] Callback canceled', 'pax-support-pro' );
        $subject_agent = __( '[PAX] Callback canceled', 'pax-support-pro' );
    } elseif ( 'reminder' === $context ) {
        $subject_user  = __( '[PAX] Callback reminder', 'pax-support-pro' );
        $subject_agent = __( '[PAX] Upcoming callback reminder', 'pax-support-pro' );
    }

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

    $message = sprintf(
        /* translators: 1: datetime, 2: timezone, 3: status, 4: note */
        __( 'Callback time: %s (%s)\nStatus: %s\n\nNotes:\n%s', 'pax-support-pro' ),
        $datetime,
        $schedule['timezone'],
        $schedule['status'],
        $schedule['note'] ? $schedule['note'] : __( 'No additional notes provided.', 'pax-support-pro' )
    );

    if ( ! empty( $schedule['contact'] ) ) {
        $message .= "\n\n" . sprintf( __( 'Contact: %s', 'pax-support-pro' ), $schedule['contact'] );
    }

    if ( 'canceled' === $context ) {
        $message .= "\n\n" . __( 'This callback has been canceled. No action is required.', 'pax-support-pro' );
    }

    if ( $user_email && is_email( $user_email ) ) {
        wp_mail( $user_email, $subject_user, $message, $headers );
    }

    if ( $agent_email && is_email( $agent_email ) ) {
        wp_mail( $agent_email, $subject_agent, $message, $headers );
    }

    do_action( 'pax_sup_schedule_notified', $schedule, $context );
}

function pax_sup_schedule_assign_agent( array $settings ) {
    $agents = array_filter( array_map( 'absint', isset( $settings['agents'] ) ? (array) $settings['agents'] : array() ) );

    if ( empty( $agents ) ) {
        return 0;
    }

    if ( 1 === count( $agents ) ) {
        return $agents[0];
    }

    global $wpdb;

    $table        = pax_sup_get_schedules_table();
    $placeholders = implode( ',', array_fill( 0, count( $agents ), '%d' ) );
    $sql          = $wpdb->prepare(
        "SELECT agent_id, COUNT(*) AS total FROM {$table} WHERE status IN ('pending','confirmed') AND agent_id IN ({$placeholders}) GROUP BY agent_id",
        $agents
    );

    $counts = $wpdb->get_results( $sql, ARRAY_A );
    $map    = array_fill_keys( $agents, 0 );

    foreach ( (array) $counts as $row ) {
        $map[ (int) $row['agent_id'] ] = (int) $row['total'];
    }

    asort( $map, SORT_NUMERIC );

    return (int) key( $map );
}

function pax_sup_schedule_slots_available( $date, $time ) {
    if ( empty( $date ) || empty( $time ) ) {
        return false;
    }

    global $wpdb;

    $settings      = pax_sup_get_scheduler_settings();
    $table         = pax_sup_get_schedules_table();
    $active_status = array( 'pending', 'confirmed' );
    $placeholders  = implode( ',', array_fill( 0, count( $active_status ), '%s' ) );
    $query         = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE schedule_date = %s AND schedule_time = %s AND status IN ({$placeholders})",
        array_merge( array( $date, $time ), $active_status )
    );

    $booked = (int) $wpdb->get_var( $query );

    return $booked < max( 1, (int) $settings['slots_per_hour'] );
}

function pax_sup_schedule_within_hours( $time, array $settings ) {
    if ( empty( $time ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
        return false;
    }

    $start = isset( $settings['hours']['start'] ) ? $settings['hours']['start'] : '09:00';
    $end   = isset( $settings['hours']['end'] ) ? $settings['hours']['end'] : '17:00';

    $time_minutes  = (int) substr( $time, 0, 2 ) * 60 + (int) substr( $time, -2 );
    $start_minutes = (int) substr( $start, 0, 2 ) * 60 + (int) substr( $start, -2 );
    $end_minutes   = (int) substr( $end, 0, 2 ) * 60 + (int) substr( $end, -2 );

    if ( $end_minutes < $start_minutes ) {
        $end_minutes += 24 * 60;
        if ( $time_minutes < $start_minutes ) {
            $time_minutes += 24 * 60;
        }
    }

    return $time_minutes >= $start_minutes && $time_minutes <= $end_minutes;
}

function pax_sup_handle_schedule_reminders() {
    global $wpdb;

    $table    = pax_sup_get_schedules_table();
    $settings = pax_sup_get_scheduler_settings();
    $lead     = max( 15, (int) $settings['reminder_lead'] );
    $now      = time();
    $window   = $now + ( $lead * MINUTE_IN_SECONDS );

    $pending = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %s AND reminder_sent = 0",
            'pending'
        ),
        ARRAY_A
    );

    foreach ( (array) $pending as $row ) {
        $timestamp = pax_sup_schedule_datetime_to_timestamp( $row['schedule_date'], $row['schedule_time'], $row['timezone'] );

        if ( ! $timestamp || $timestamp < $now || $timestamp > $window ) {
            continue;
        }

        pax_sup_notify_schedule_event( $row, 'reminder' );

        $wpdb->update(
            $table,
            array(
                'reminder_sent' => 1,
                'updated_at'    => current_time( 'mysql' ),
            ),
            array( 'id' => (int) $row['id'] ),
            array( '%d', '%s' ),
            array( '%d' )
        );
    }
}

function pax_sup_detect_user_language( $fallback = '' ) {
    $language = '';

    if ( function_exists( 'pll_current_language' ) ) {
        $language = pll_current_language( 'slug' );
    } elseif ( has_filter( 'wpml_current_language' ) ) {
        $language = apply_filters( 'wpml_current_language', null );
    }

    if ( ! empty( $fallback ) && empty( $language ) ) {
        $language = $fallback;
    }

    if ( empty( $language ) ) {
        $language = get_locale();
    }

    if ( is_string( $language ) ) {
        $language = strtolower( substr( $language, 0, 12 ) );
    } else {
        $language = 'en';
    }

    return $language ? $language : 'en';
}

function pax_sup_find_knowledge_articles( $keyword, $language = '', $limit = 5 ) {
    $keyword = trim( wp_strip_all_tags( (string) $keyword ) );

    $args = array(
        'post_type'        => array( 'pax_kb', 'faq', 'page', 'post' ),
        'post_status'      => 'publish',
        'posts_per_page'   => max( 1, (int) $limit ),
        'suppress_filters' => false,
    );

    if ( $keyword ) {
        $args['s'] = $keyword;
    }

    if ( $language && function_exists( 'pll_languages_list' ) ) {
        $args['lang'] = $language;
    }

    $query   = new WP_Query( $args );
    $results = array();

    foreach ( $query->posts as $post ) {
        $summary = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post, true ) ), 40, '…' );
        $results[] = array(
            'title'   => get_the_title( $post ),
            'summary' => $summary,
            'url'     => get_permalink( $post ),
        );
    }

    wp_reset_postdata();

    return $results;
}

function pax_sup_extract_keywords( $text ) {
    $text      = strtolower( sanitize_text_field( $text ) );
    $keywords  = array();
    $catalogue = array(
        'refund'  => array( 'refund', 'chargeback', 'return' ),
        'error'   => array( 'error', 'bug', 'issue', 'crash', 'fatal' ),
        'billing' => array( 'billing', 'invoice', 'payment', 'charge', 'receipt' ),
        'account' => array( 'account', 'login', 'password', 'credentials' ),
        'speed'   => array( 'slow', 'performance', 'speed', 'optimize' ),
    );

    foreach ( $catalogue as $key => $needles ) {
        foreach ( $needles as $needle ) {
            if ( false !== strpos( $text, $needle ) ) {
                $keywords[] = $key;
                break;
            }
        }
    }

    $unique = array_values( array_unique( $keywords ) );

    if ( empty( $unique ) && $text ) {
        $parts = preg_split( '/[^a-z0-9]+/', $text );
        foreach ( $parts as $part ) {
            $part = trim( $part );
            if ( strlen( $part ) >= 5 ) {
                $unique[] = $part;
            }
            if ( count( $unique ) >= 5 ) {
                break;
            }
        }
    }

    return array_slice( $unique, 0, 5 );
}

function pax_sup_store_ai_session( $user_id, $session_id, $language, $conversation, $keywords = array() ) {
    pax_sup_ensure_ticket_tables();

    global $wpdb;
    $table = pax_sup_get_logs_table();

    if ( ! $table ) {
        return;
    }

    $user_id    = (int) $user_id;
    $session_id = substr( preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $session_id ) ), 0, 60 );
    if ( ! $session_id ) {
        $session_id = 'sess-' . wp_generate_password( 10, false, false );
    }

    $language = substr( sanitize_text_field( $language ), 0, 20 );
    $payload  = wp_json_encode( $conversation );
    $now      = current_time( 'mysql' );
    $kw       = implode( ',', array_slice( array_map( 'sanitize_text_field', (array) $keywords ), 0, 6 ) );

    $wpdb->replace(
        $table,
        array(
            'user_id'   => $user_id,
            'session_id'=> $session_id,
            'language'  => $language,
            'keywords'  => $kw,
            'transcript'=> $payload,
            'created_at'=> $now,
            'updated_at'=> $now,
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( $user_id > 0 ) {
        $limit = 10;
        $ids   = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC, id DESC LIMIT 18446744073709551615 OFFSET %d",
                $user_id,
                $limit
            )
        );

        if ( ! empty( $ids ) ) {
            $ids = array_map( 'intval', $ids );
            $wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', $ids ) . ')' );
        }
    }
}

function pax_sup_log_event( $event, $details = array() ) {
    pax_sup_ensure_ticket_tables();

    if ( empty( $event ) ) {
        return;
    }

    global $wpdb;
    $table = pax_sup_get_logs_table();

    if ( empty( $table ) ) {
        return;
    }

    $user_id    = get_current_user_id();
    $session_id = substr( sanitize_key( $event ) . '-' . wp_generate_password( 8, false, false ), 0, 60 );
    $payload    = wp_json_encode(
        array(
            'event'   => sanitize_key( $event ),
            'details' => $details,
        )
    );

    $wpdb->insert(
        $table,
        array(
            'user_id'    => (int) $user_id,
            'session_id' => $session_id,
            'language'   => '',
            'keywords'   => substr( sanitize_key( $event ), 0, 32 ),
            'transcript' => $payload,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
}

function pax_sup_get_backup_directory() {
    $upload = wp_upload_dir();

    if ( ! empty( $upload['error'] ) ) {
        return false;
    }

    $dir = trailingslashit( $upload['basedir'] ) . 'pax-support-pro-backups/';
    wp_mkdir_p( $dir );

    return $dir;
}

function pax_sup_run_backup( $context = 'manual' ) {
    $options = pax_sup_get_options();

    if ( empty( $options['backup_local_enabled'] ) ) {
        return new WP_Error( 'backup_disabled', __( 'Local backups are disabled.', 'pax-support-pro' ) );
    }

    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'missing_zip', __( 'ZipArchive extension is required for backups.', 'pax-support-pro' ) );
    }

    $dir = pax_sup_get_backup_directory();

    if ( ! $dir ) {
        return new WP_Error( 'backup_dir', __( 'Unable to determine backup directory.', 'pax-support-pro' ) );
    }

    $timestamp = gmdate( 'Ymd-His' );
    $zip_path  = $dir . 'pax-support-pro-' . $timestamp . '.zip';

    $zip = new ZipArchive();

    if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
        return new WP_Error( 'backup_open', __( 'Could not create backup archive.', 'pax-support-pro' ) );
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( PAX_SUP_DIR, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ( $iterator as $file ) {
        $path = str_replace( PAX_SUP_DIR, '', (string) $file );

        if ( $file->isDir() ) {
            $zip->addEmptyDir( $path );
        } else {
            $zip->addFile( $file->getPathname(), $path );
        }
    }

    $zip->close();

    $size = file_exists( $zip_path ) ? filesize( $zip_path ) : 0;

    pax_sup_log_event(
        'backup_created',
        array(
            'context' => $context,
            'path'    => $zip_path,
            'size'    => $size,
        )
    );

    if ( ! empty( $options['backup_google_drive'] ) ) {
        do_action( 'pax_sup_backup_google_drive', $zip_path, $context, $options );
        pax_sup_log_event(
            'backup_cloud_google',
            array(
                'context' => $context,
                'path'    => $zip_path,
            )
        );
    }

    if ( ! empty( $options['backup_dropbox'] ) ) {
        do_action( 'pax_sup_backup_dropbox', $zip_path, $context, $options );
        pax_sup_log_event(
            'backup_cloud_dropbox',
            array(
                'context' => $context,
                'path'    => $zip_path,
            )
        );
    }

    return $zip_path;
}

function pax_sup_restore_backup( $zip_path ) {
    if ( empty( $zip_path ) || ! file_exists( $zip_path ) ) {
        return new WP_Error( 'missing_backup', __( 'Backup archive not found.', 'pax-support-pro' ) );
    }

    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'missing_zip', __( 'ZipArchive extension is required to restore backups.', 'pax-support-pro' ) );
    }

    $zip = new ZipArchive();

    if ( true !== $zip->open( $zip_path ) ) {
        return new WP_Error( 'open_backup', __( 'Unable to open backup archive.', 'pax-support-pro' ) );
    }

    $temp_dir = wp_tempnam( 'pax-support-restore' );

    if ( ! $temp_dir ) {
        $zip->close();

        return new WP_Error( 'temp_dir', __( 'Unable to create temporary directory for restore.', 'pax-support-pro' ) );
    }

    unlink( $temp_dir );
    wp_mkdir_p( $temp_dir );
    $zip->extractTo( $temp_dir );
    $zip->close();

    $source = trailingslashit( $temp_dir ) . basename( PAX_SUP_DIR );
    if ( ! is_dir( $source ) ) {
        $source = $temp_dir;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();

    global $wp_filesystem;

    if ( ! $wp_filesystem ) {
        return new WP_Error( 'filesystem', __( 'Filesystem credentials are required for restore.', 'pax-support-pro' ) );
    }

    $target = trailingslashit( WP_PLUGIN_DIR ) . basename( PAX_SUP_DIR );
    $wp_filesystem->delete( $target, true );

    $result = copy_dir( $source, $target );
    $wp_filesystem->delete( $temp_dir, true );

    if ( is_wp_error( $result ) || false === $result ) {
        pax_sup_log_event(
            'backup_restore_failed',
            array(
                'path' => $zip_path,
            )
        );

        return is_wp_error( $result ) ? $result : new WP_Error( 'restore_failed', __( 'Failed to restore plugin files from backup.', 'pax-support-pro' ) );
    }

    pax_sup_log_event(
        'backup_restored',
        array(
            'path' => $zip_path,
        )
    );

    return true;
}

function pax_sup_format_bytes( $bytes ) {
    if ( $bytes <= 0 ) {
        return '0 B';
    }

    $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    $power = min( (int) floor( log( $bytes, 1024 ) ), count( $units ) - 1 );

    return sprintf( '%.2f %s', $bytes / pow( 1024, $power ), $units[ $power ] );
}

function pax_sup_get_server_metrics() {
    $memory_usage = function_exists( 'memory_get_usage' ) ? memory_get_usage( true ) : 0;
    $load         = function_exists( 'sys_getloadavg' ) ? sys_getloadavg() : array();

    return array(
        'php_version'   => PHP_VERSION,
        'wordpress'     => get_bloginfo( 'version' ),
        'memory_limit'  => ini_get( 'memory_limit' ),
        'memory_usage'  => pax_sup_format_bytes( $memory_usage ),
        'server_load'   => ! empty( $load ) ? implode( ', ', array_map( 'floatval', $load ) ) : __( 'N/A', 'pax-support-pro' ),
        'server_time'   => current_time( 'mysql' ),
        'ip'            => pax_sup_ip(),
    );
}

function pax_sup_register_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['pax_sup_weekly'] ) ) {
        $schedules['pax_sup_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Once Weekly', 'pax-support-pro' ),
        );
    }

    return $schedules;
}
add_filter( 'cron_schedules', 'pax_sup_register_cron_schedules' );

function pax_sup_store_admin_notice( $message, $type = 'success' ) {
    $key = 'pax_sup_notice_' . get_current_user_id();
    set_transient(
        $key,
        array(
            'message' => wp_kses_post( $message ),
            'type'    => $type,
        ),
        MINUTE_IN_SECONDS
    );
}

function pax_sup_pull_admin_notice() {
    $key    = 'pax_sup_notice_' . get_current_user_id();
    $notice = get_transient( $key );

    if ( $notice ) {
        delete_transient( $key );
    }

    return $notice;
}

function pax_sup_get_recent_ai_sessions( $user_id, $limit = 3 ) {
    global $wpdb;

    $table = pax_sup_get_logs_table();
    if ( ! $table ) {
        return array();
    }

    $user_id = (int) $user_id;
    $limit   = max( 1, (int) $limit );
    $sql     = $wpdb->prepare(
        "SELECT transcript FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC, id DESC LIMIT %d",
        $user_id,
        $limit
    );

    $rows = $wpdb->get_col( $sql );
    $data = array();

    foreach ( $rows as $row ) {
        $decoded = json_decode( $row, true );
        if ( $decoded ) {
            $data[] = $decoded;
        }
    }

    return $data;
}

function pax_sup_format_ticket_status( $status ) {
    switch ( $status ) {
        case 'frozen':
            return __( 'Frozen', 'pax-support-pro' );
        case 'closed':
            return __( 'Closed', 'pax-support-pro' );
        case 'answered':
            return __( 'Answered', 'pax-support-pro' );
        default:
            return __( 'Open', 'pax-support-pro' );
    }
}

function pax_sup_get_user_cooldown_meta_key() {
    return 'pax_ticket_cooldown_until';
}

function pax_sup_get_user_cooldown_until( $user_id ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return 0;
    }

    return (int) get_user_meta( $user_id, pax_sup_get_user_cooldown_meta_key(), true );
}

function pax_sup_set_user_cooldown_until( $user_id, $timestamp ) {
    $user_id = (int) $user_id;

    if ( $user_id <= 0 ) {
        return;
    }

    if ( $timestamp <= 0 ) {
        delete_user_meta( $user_id, pax_sup_get_user_cooldown_meta_key() );
        return;
    }

    update_user_meta( $user_id, pax_sup_get_user_cooldown_meta_key(), (int) $timestamp );
}

/**
 * Migrate old dark theme colors to new bright theme colors
 * v5.7.8: One-time migration for existing installations
 */
function pax_sup_migrate_to_bright_theme() {
    // Check if migration already done
    if ( get_option( 'pax_sup_bright_theme_migrated' ) ) {
        return;
    }

    $options = get_option( PAX_SUP_OPT_KEY );
    
    if ( ! is_array( $options ) ) {
        // No saved options, will use new defaults
        update_option( 'pax_sup_bright_theme_migrated', true );
        return;
    }

    // Check if user has the old dark theme defaults
    $old_dark_colors = array(
        'color_bg'     => '#0d0f12',
        'color_panel'  => '#121418',
        'color_border' => '#2a2d33',
        'color_text'   => '#e8eaf0',
        'color_sub'    => '#9aa0a8',
    );

    $has_old_defaults = true;
    foreach ( $old_dark_colors as $key => $old_value ) {
        if ( ! isset( $options[ $key ] ) || $options[ $key ] !== $old_value ) {
            $has_old_defaults = false;
            break;
        }
    }

    // If user has old defaults, update to new bright theme
    if ( $has_old_defaults ) {
        $options['color_bg']     = '#ffffff';
        $options['color_panel']  = '#f5f5f5';
        $options['color_border'] = '#e0e0e0';
        $options['color_text']   = '#212121';
        $options['color_sub']    = '#757575';
        
        update_option( PAX_SUP_OPT_KEY, $options );
        error_log( 'PAX Support Pro: Migrated to bright theme colors' );
    }

    // Mark migration as done
    update_option( 'pax_sup_bright_theme_migrated', true );
}
add_action( 'admin_init', 'pax_sup_migrate_to_bright_theme' );
add_action( 'wp', 'pax_sup_migrate_to_bright_theme' );

/**
 * Attempt to read the current Git commit hash for display purposes.
 *
 * @return string
 */
function pax_sup_get_current_commit_hash() {
    static $hash = null;

    if ( null !== $hash ) {
        return $hash;
    }

    $hash    = 'n/a';
    $git_dir = trailingslashit( dirname( PAX_SUP_DIR ) ) . '.git';

    if ( is_dir( $git_dir ) ) {
        $head_file = $git_dir . 'HEAD';
        if ( file_exists( $head_file ) ) {
            $head_contents = trim( (string) file_get_contents( $head_file ) );

            if ( 0 === strpos( $head_contents, 'ref:' ) ) {
                $ref_path = trim( substr( $head_contents, 4 ) );
                $ref_file = $git_dir . $ref_path;
                if ( file_exists( $ref_file ) ) {
                    $hash = trim( (string) file_get_contents( $ref_file ) );
                }
            } elseif ( ! empty( $head_contents ) ) {
                $hash = $head_contents;
            }
        }
    }

    if ( $hash && 'n/a' !== $hash ) {
        $hash = substr( $hash, 0, 7 );
    } else {
        $hash = 'n/a';
    }

    return $hash;
}