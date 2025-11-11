<?php
/**
 * Admin settings and menu registration.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_get_console_capability() {
    $options = pax_sup_get_options();

    return ! empty( $options['console_cap'] ) ? $options['console_cap'] : 'manage_options';
}

function pax_sup_enqueue_support_button_assets() {
    static $loaded = false;

    if ( $loaded ) {
        return;
    }

    $loaded = true;

    wp_register_script( 'pax-support-admin', '', array(), PAX_SUP_VER, true );
    wp_enqueue_script( 'pax-support-admin' );
    wp_localize_script(
        'pax-support-admin',
        'paxSupportAdmin',
        array(
            'ajax'       => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'pax_sup_support_click' ),
            'resetNonce' => wp_create_nonce( 'pax_sup_reset_settings' ),
        )
    );

    $script = 'window.addEventListener("DOMContentLoaded",function(){var buttons=document.querySelectorAll("[data-pax-support-button]");buttons.forEach(function(btn){btn.addEventListener("click",function(){if(!window.paxSupportAdmin||!paxSupportAdmin.ajax){return;}var data=new window.FormData();data.append("action","pax_sup_support_click");data.append("nonce",paxSupportAdmin.nonce);if(navigator.sendBeacon){paxSupSendBeacon(paxSupportAdmin.ajax,data);}else{fetch(paxSupportAdmin.ajax,{method:"POST",credentials:"same-origin",body:data});}});});});function paxSupSendBeacon(url,form){var params=new URLSearchParams();form.forEach(function(value,key){params.append(key,value);});navigator.sendBeacon(url,params);}';
    wp_add_inline_script( 'pax-support-admin', $script );
}

function pax_sup_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, 'pax-support' ) === false ) {
        return;
    }

    pax_sup_enqueue_support_button_assets();
    wp_enqueue_style( 'wp-components' );

    // Enqueue modern settings UI assets
    if ( strpos( $hook, 'pax-support-settings' ) !== false ) {
        wp_enqueue_style(
            'pax-settings-modern',
            PAX_SUP_URL . 'admin/css/settings-modern.css',
            array(),
            PAX_SUP_VER
        );
        
        wp_enqueue_script(
            'pax-settings-modern',
            PAX_SUP_URL . 'admin/js/settings-modern.js',
            array(),
            PAX_SUP_VER,
            true
        );
        
        // Enqueue live preview assets
        wp_enqueue_style(
            'pax-live-preview',
            PAX_SUP_URL . 'admin/live-preview/live-preview.css',
            array(),
            PAX_SUP_VER
        );
        
        wp_enqueue_script(
            'pax-live-preview',
            PAX_SUP_URL . 'admin/live-preview/live-preview.js',
            array( 'jquery' ),
            PAX_SUP_VER,
            true
        );

        $settings_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
        if ( 'live-agent' === $settings_tab ) {
            wp_enqueue_style(
                'pax-live-agent-settings',
                PAX_SUP_URL . 'admin/css/live-agent-settings.css',
                array( 'pax-settings-modern' ),
                PAX_SUP_VER
            );

            wp_enqueue_script(
                'pax-live-agent-settings',
                PAX_SUP_URL . 'admin/js/live-agent-settings.js',
                array(),
                PAX_SUP_VER,
                true
            );

            wp_localize_script(
                'pax-live-agent-settings',
                'paxLiveAgentSettings',
                array(
                    'restBase'     => trailingslashit( rest_url( 'pax/v1' ) ),
                    'testEndpoint' => rest_url( 'pax/v1/live/status' ),
                    'nonce'        => wp_create_nonce( 'wp_rest' ),
                    'strings'      => array(
                        'testing' => __( 'Testing connection…', 'pax-support-pro' ),
                        'success' => __( 'Connection successful!', 'pax-support-pro' ),
                        'failure' => __( 'Connection failed. Please review your REST API configuration.', 'pax-support-pro' ),
                        'copied'  => __( 'Copied!', 'pax-support-pro' ),
                    ),
                )
            );
        }
    }

    // Enqueue modern console UI assets
    if ( strpos( $hook, 'pax-support-console' ) !== false ) {
        wp_enqueue_style(
            'pax-console-modern',
            PAX_SUP_URL . 'admin/css/console-modern.css',
            array(),
            PAX_SUP_VER
        );
        
        wp_enqueue_script(
            'pax-theme-toggle',
            PAX_SUP_URL . 'admin/js/theme-toggle.js',
            array(),
            PAX_SUP_VER,
            true
        );
    }

    // Enqueue modern scheduler UI assets
    if ( strpos( $hook, 'pax-support-scheduler' ) !== false ) {
        wp_enqueue_style(
            'pax-scheduler-modern',
            PAX_SUP_URL . 'admin/css/scheduler-modern.css',
            array(),
            PAX_SUP_VER
        );
        
        wp_enqueue_script(
            'pax-scheduler-modern',
            PAX_SUP_URL . 'admin/js/scheduler-modern.js',
            array(),
            PAX_SUP_VER,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script(
            'pax-scheduler-modern',
            'paxScheduler',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'pax_scheduler_nonce' ),
                'strings' => array(
                    'confirmDelete' => __( 'Are you sure you want to delete this callback?', 'pax-support-pro' ),
                    'deleteSuccess' => __( 'Callback deleted successfully', 'pax-support-pro' ),
                    'updateSuccess' => __( 'Callback updated successfully', 'pax-support-pro' ),
                    'saveSuccess' => __( 'Changes saved successfully', 'pax-support-pro' ),
                    'errorOccurred' => __( 'An error occurred. Please try again.', 'pax-support-pro' ),
                    'loading' => __( 'Loading...', 'pax-support-pro' ),
                ),
            )
        );
    }

    // Enqueue assets for new admin pages
    $new_pages = array( 'roles-permissions', 'analytics', 'system-health', 'theme-settings' );
    foreach ( $new_pages as $page ) {
        if ( strpos( $hook, 'pax-support-' . $page ) !== false ) {
            wp_enqueue_style(
                'pax-pages-modern',
                PAX_SUP_URL . 'admin/css/pages-modern.css',
                array(),
                PAX_SUP_VER
            );
            break;
        }
    }

    // Live Agent Center assets
    if ( strpos( $hook, 'pax-live-agent-center' ) !== false ) {
        wp_enqueue_style(
            'pax-liveagent-center',
            PAX_SUP_URL . 'admin/css/live-agent-center.css',
            array(),
            PAX_SUP_VER
        );
        
        wp_enqueue_script(
            'pax-liveagent-center',
            PAX_SUP_URL . 'admin/js/live-agent-center.js',
            array( 'jquery' ),
            PAX_SUP_VER,
            true
        );

        $rest_base    = trailingslashit( rest_url( 'pax/v1' ) );
        $site_domain  = wp_parse_url( get_site_url(), PHP_URL_HOST );
        $site_domain  = $site_domain ? $site_domain : wp_parse_url( get_site_url(), PHP_URL_HOST );
        $server_name  = isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : $site_domain;
        $server_ip    = $server_name ? @gethostbyname( $server_name ) : '';
        $current_user = wp_get_current_user();

        wp_localize_script(
            'pax-liveagent-center',
            'paxLiveAgentCenterConfig',
            array(
                'rest'   => array(
                    'base'        => $rest_base,
                    'sessions'    => $rest_base . 'sessions',
                    'session'     => $rest_base . 'session/',
                    'messages'    => $rest_base . 'messages',
                    'message'     => $rest_base . 'message',
                    'accept'      => $rest_base . 'accept',
                    'decline'     => $rest_base . 'decline',
                    'close'       => $rest_base . 'close',
                    'status'      => $rest_base . 'status',
                    'typing'      => trailingslashit( rest_url( 'pax-support-pro/v1/liveagent/status/typing' ) ),
                    'markRead'    => trailingslashit( rest_url( 'pax-support-pro/v1/liveagent/message/mark-read' ) ),
                    'fileUpload'  => trailingslashit( rest_url( 'pax-support-pro/v1/liveagent/file/upload' ) ),
                ),
                'nonce'  => wp_create_nonce( 'wp_rest' ),
                'assets' => array(
                    'version' => PAX_SUP_VER,
                    'commit'  => function_exists( 'pax_sup_get_current_commit_hash' ) ? pax_sup_get_current_commit_hash() : 'n/a',
                ),
                'diagnostics' => array(
                    'restBase'   => $rest_base,
                    'siteDomain' => $site_domain ?: get_site_url(),
                    'serverIp'   => $server_ip ?: '',
                ),
                'user' => array(
                    'id'   => $current_user instanceof WP_User ? $current_user->ID : 0,
                    'name' => $current_user instanceof WP_User ? $current_user->display_name : '',
                ),
                'strings' => array(
                    'pendingTab'      => __( 'Pending', 'pax-support-pro' ),
                    'activeTab'       => __( 'Active', 'pax-support-pro' ),
                    'recentTab'       => __( 'Recent', 'pax-support-pro' ),
                    'noPending'       => __( 'No pending requests.', 'pax-support-pro' ),
                    'noActive'        => __( 'No active chats.', 'pax-support-pro' ),
                    'noRecent'        => __( 'No recent sessions yet.', 'pax-support-pro' ),
                    'accept'          => __( 'Accept', 'pax-support-pro' ),
                    'decline'         => __( 'Decline', 'pax-support-pro' ),
                    'close'           => __( 'End Session', 'pax-support-pro' ),
                    'cancel'          => __( 'Cancel', 'pax-support-pro' ),
                    'confirmClose'    => __( 'End this chat session?', 'pax-support-pro' ),
                    'confirmDecline'  => __( 'Decline this chat request?', 'pax-support-pro' ),
                    'composerHint'    => __( 'Type a reply…', 'pax-support-pro' ),
                    'acceptPrompt'    => __( 'Accept this chat to reply.', 'pax-support-pro' ),
                    'closedMessage'   => __( 'This session is closed.', 'pax-support-pro' ),
                    'uploadLabel'     => __( 'Attach file', 'pax-support-pro' ),
                    'uploading'       => __( 'Uploading…', 'pax-support-pro' ),
                    'uploadFailed'    => __( 'Upload failed. Please try again.', 'pax-support-pro' ),
                    'messageFailed'   => __( 'Unable to send message. Please try again.', 'pax-support-pro' ),
                    'send'            => __( 'Send', 'pax-support-pro' ),
                    'unread'          => __( 'Unread', 'pax-support-pro' ),
                    'live'            => __( 'Live', 'pax-support-pro' ),
                    'unknownUser'     => __( 'Guest', 'pax-support-pro' ),
                    'justNow'         => __( 'Just now', 'pax-support-pro' ),
                    'ago'             => __( 'ago', 'pax-support-pro' ),
                    'read'            => __( 'Read', 'pax-support-pro' ),
                    'pingTesting'     => __( 'Pinging…', 'pax-support-pro' ),
                    'pingSuccess'     => __( 'REST API reachable', 'pax-support-pro' ),
                    'pingError'       => __( 'Unable to reach REST API', 'pax-support-pro' ),
                    'emptyStateTitle' => __( 'No chat selected', 'pax-support-pro' ),
                    'emptyStateBody'  => __( 'Choose a conversation to see messages here.', 'pax-support-pro' ),
                ),
            )
        );

        wp_localize_script(
            'pax-liveagent-center',
            'PAX_LIVE',
            array(
                'restBase' => esc_url_raw( $rest_base ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'noStore'  => true,
                'strings'  => array(
                    'connecting'  => __( 'Connecting to an agent…', 'pax-support-pro' ),
                    'queued'      => __( 'You are now in queue, please wait…', 'pax-support-pro' ),
                    'connected'   => __( 'Agent connected!', 'pax-support-pro' ),
                    'typeHere'    => __( 'Type your message…', 'pax-support-pro' ),
                    'statusError' => __( 'Unable to connect right now. Please try again.', 'pax-support-pro' ),
                    'newRequest'  => __( 'New live request', 'pax-support-pro' ),
                    'newMessage'  => __( 'New message', 'pax-support-pro' ),
                ),
                'quickPrompts' => array(
                    __( 'I need help with my order', 'pax-support-pro' ),
                    __( 'How long does delivery take?', 'pax-support-pro' ),
                    __( 'Speak to a human agent', 'pax-support-pro' ),
                ),
                'assets' => array(
                    'ding' => esc_url_raw( PAX_SUP_URL . 'assets/audio/ding.mp3' ),
                ),
            )
        );
    }

    // Analytics page specific assets
    if ( strpos( $hook, 'pax-support-analytics' ) !== false ) {
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
        
        wp_enqueue_script(
            'pax-analytics-dashboard',
            PAX_SUP_URL . 'admin/js/analytics-dashboard.js',
            array( 'jquery', 'chart-js' ),
            PAX_SUP_VER,
            true
        );
    }

    // System Health page specific assets
    if ( strpos( $hook, 'pax-support-system-health' ) !== false ) {
        wp_enqueue_script(
            'pax-system-health',
            PAX_SUP_URL . 'admin/js/system-health.js',
            array( 'jquery' ),
            PAX_SUP_VER,
            true
        );
    }

    $options = pax_sup_get_options();
    $accent  = sanitize_hex_color( $options['color_accent'] ?? '#e53935' );
    if ( empty( $accent ) ) {
        $accent = '#e53935';
    }

    $style = '.pax-support-dev-cta{position:absolute;top:16px;right:16px;z-index:10;text-align:right}.pax-support-dev-cta .pax-support-dev-button{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;font-weight:600;text-decoration:none;color:#fff;background:linear-gradient(135deg,' . $accent . ',rgba(255,255,255,0.12));box-shadow:0 12px 32px rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.2);backdrop-filter:blur(6px);transition:transform .2s ease, box-shadow .2s ease}.pax-support-dev-cta .pax-support-dev-button:hover{transform:translateY(-2px);box-shadow:0 16px 40px rgba(0,0,0,0.4)}.pax-support-dev-cta .pax-support-dev-button svg{width:18px;height:18px;fill:#fff}@media(max-width:782px){.pax-support-dev-cta{position:static;margin-bottom:12px;text-align:left}}';
    wp_add_inline_style( 'wp-components', $style );
}
add_action( 'admin_enqueue_scripts', 'pax_sup_enqueue_admin_assets' );

function pax_sup_enqueue_support_button_front() {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
        return;
    }

    pax_sup_enqueue_support_button_assets();
}
add_action( 'wp_enqueue_scripts', 'pax_sup_enqueue_support_button_front' );

function pax_sup_admin_button_styles() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $options = pax_sup_get_options();
    $accent  = sanitize_hex_color( $options['color_accent'] ?? '#e53935' );
    if ( empty( $accent ) ) {
        $accent = '#e53935';
    }

    echo '<style id="pax-support-admin-button">#wpadminbar #wp-admin-bar-pax-support-dev>.ab-item{background:linear-gradient(135deg,' . $accent . ',rgba(255,255,255,0.18));color:#fff !important;border-radius:999px;padding:0 12px;display:flex;align-items:center;gap:6px;box-shadow:0 6px 18px rgba(0,0,0,0.4)}#wpadminbar #wp-admin-bar-pax-support-dev .ab-icon{margin-right:4px}#wpadminbar #wp-admin-bar-pax-support-dev>.ab-item:hover{opacity:0.95}</style>';
}
add_action( 'admin_head', 'pax_sup_admin_button_styles' );
add_action( 'wp_head', 'pax_sup_admin_button_styles' );

function pax_sup_add_admin_bar_support_button( WP_Admin_Bar $admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $admin_bar->add_node(
        array(
            'id'    => 'pax-support-dev',
            'title' => '<span class="ab-icon dashicons dashicons-heart"></span><span class="ab-label">' . esc_html__( 'Support Developer', 'pax-support-pro' ) . '</span>',
            'href'  => 'https://www.paypal.me/AhmadAlkhalaf29',
            'meta'  => array(
                'target' => '_blank',
                'rel'    => 'noopener noreferrer',
                'class'  => 'pax-support-dev-admin-bar',
                'data-pax-support-button' => '1',
            ),
        )
    );
}
add_action( 'admin_bar_menu', 'pax_sup_add_admin_bar_support_button', 90 );

function pax_sup_handle_support_click() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error();
    }

    check_ajax_referer( 'pax_sup_support_click', 'nonce' );

    pax_sup_log_event(
        'support_click',
        array(
            'user_id' => get_current_user_id(),
            'ip'      => pax_sup_ip(),
            'time'    => current_time( 'mysql' ),
        )
    );

    wp_send_json_success();
}
add_action( 'wp_ajax_pax_sup_support_click', 'pax_sup_handle_support_click' );

function pax_sup_handle_manual_backup_request() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_die();
    }

    check_admin_referer( 'pax_sup_backup_now' );

    $result = pax_sup_run_backup( 'manual' );

    if ( is_wp_error( $result ) ) {
        pax_sup_store_admin_notice( $result->get_error_message(), 'error' );
    } else {
        pax_sup_store_admin_notice( sprintf( __( 'Backup created successfully (%s).', 'pax-support-pro' ), basename( $result ) ), 'success' );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=pax-support-settings' ) );
    exit;
}
add_action( 'admin_post_pax_sup_backup_now', 'pax_sup_handle_manual_backup_request' );

function pax_sup_register_dashboard_widget() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }

    wp_add_dashboard_widget( 'pax_sup_monitor', __( 'PAX Support Monitor', 'pax-support-pro' ), 'pax_sup_render_dashboard_widget' );
}
add_action( 'wp_dashboard_setup', 'pax_sup_register_dashboard_widget' );

function pax_sup_render_dashboard_widget() {
    pax_sup_ensure_ticket_tables();
    $metrics = pax_sup_get_server_metrics();

    global $wpdb;
    $table = pax_sup_get_ticket_table();
    $counts = array(
        'open'   => 0,
        'closed' => 0,
        'frozen' => 0,
    );

    if ( $table ) {
        $rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status" );
        foreach ( (array) $rows as $row ) {
            $status = $row->status;
            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ] = (int) $row->total;
            }
        }
    }

    echo '<ul class="pax-monitor">';
    echo '<li><strong>' . esc_html__( 'PHP Version', 'pax-support-pro' ) . ':</strong> ' . esc_html( $metrics['php_version'] ) . '</li>';
    echo '<li><strong>' . esc_html__( 'WordPress', 'pax-support-pro' ) . ':</strong> ' . esc_html( $metrics['wordpress'] ) . '</li>';
    echo '<li><strong>' . esc_html__( 'Memory Usage', 'pax-support-pro' ) . ':</strong> ' . esc_html( $metrics['memory_usage'] ) . ' / ' . esc_html( $metrics['memory_limit'] ) . '</li>';
    echo '<li><strong>' . esc_html__( 'Server Load', 'pax-support-pro' ) . ':</strong> ' . esc_html( $metrics['server_load'] ) . '</li>';
    echo '<li><strong>' . esc_html__( 'Open Tickets', 'pax-support-pro' ) . ':</strong> ' . esc_html( $counts['open'] ) . '</li>';
    echo '<li><strong>' . esc_html__( 'Frozen Tickets', 'pax-support-pro' ) . ':</strong> ' . esc_html( $counts['frozen'] ) . '</li>';
    echo '<li><strong>' . esc_html__( 'Closed Tickets', 'pax-support-pro' ) . ':</strong> ' . esc_html( $counts['closed'] ) . '</li>';
    echo '</ul>';
}

function pax_sup_register_admin_menu() {
    $cap = pax_sup_get_console_capability();

    add_menu_page(
        __( 'PAX Support Pro', 'pax-support-pro' ),
        __( 'PAX Support', 'pax-support-pro' ),
        $cap,
        'pax-support-dashboard',
        'pax_sup_render_dashboard',
        'dashicons-format-chat',
        58
    );

    add_submenu_page( 'pax-support-dashboard', __( 'Dashboard', 'pax-support-pro' ), __( 'Dashboard', 'pax-support-pro' ), $cap, 'pax-support-dashboard', 'pax_sup_render_dashboard' );
    add_submenu_page( 'pax-support-dashboard', __( 'Live Agent Center', 'pax-support-pro' ), __( 'Live Agent Center', 'pax-support-pro' ), $cap, 'pax-live-agent-center', 'pax_sup_render_live_agent_center_page' );
    add_submenu_page( 'pax-support-dashboard', __( 'Settings', 'pax-support-pro' ), __( 'Settings', 'pax-support-pro' ), $cap, 'pax-support-settings', 'pax_sup_render_settings' );
    add_submenu_page( 'pax-support-dashboard', __( 'Console', 'pax-support-pro' ), __( 'Console', 'pax-support-pro' ), $cap, 'pax-support-console', 'pax_sup_render_console' );
    add_submenu_page( 'pax-support-dashboard', __( 'Tickets', 'pax-support-pro' ), __( 'Tickets', 'pax-support-pro' ), $cap, 'pax-support-tickets', 'pax_sup_render_tickets' );
    add_submenu_page( 'pax-support-dashboard', __( 'Scheduler', 'pax-support-pro' ), __( 'Scheduler', 'pax-support-pro' ), $cap, 'pax-support-scheduler', 'pax_sup_render_scheduler_page' );
    add_submenu_page( 'pax-support-dashboard', __( 'Analytics', 'pax-support-pro' ), __( 'Analytics', 'pax-support-pro' ), $cap, 'pax-support-analytics', 'pax_sup_render_analytics_dashboard_page' );
    add_submenu_page( 'pax-support-dashboard', __( 'Roles & Permissions', 'pax-support-pro' ), __( 'Roles & Permissions', 'pax-support-pro' ), $cap, 'pax-support-roles-permissions', 'pax_sup_render_roles_permissions_page' );
    add_submenu_page( 'pax-support-dashboard', __( 'System Health', 'pax-support-pro' ), __( 'System Health', 'pax-support-pro' ), $cap, 'pax-support-system-health', 'pax_sup_render_system_health_page' );
    add_submenu_page( 'pax-support-dashboard', __( 'Theme Settings', 'pax-support-pro' ), __( 'Theme Settings', 'pax-support-pro' ), $cap, 'pax-support-theme-settings', 'pax_sup_render_theme_settings_page' );
    add_submenu_page( 'pax-support-dashboard', __( 'Feedback', 'pax-support-pro' ), __( 'Feedback', 'pax-support-pro' ), $cap, 'pax-support-feedback', 'pax_sup_render_feedback_page' );
}
add_action( 'admin_menu', 'pax_sup_register_admin_menu' );

function pax_sup_render_feedback_page() {
    $url = 'https://www.paypal.me/AhmadAlkhalaf29';
    wp_safe_redirect( $url );
    exit;
}

function pax_sup_render_dashboard() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }

    $options = pax_sup_get_options();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'PAX Support Dashboard', 'pax-support-pro' ); ?></h1>
        <p><?php esc_html_e( 'Welcome to PAX Support Pro. Use the quick stats below to monitor chat and ticket activity.', 'pax-support-pro' ); ?></p>
        <div class="pax-cards" style="display:flex;gap:20px;flex-wrap:wrap;margin-top:20px;">
            <div class="card" style="background:#fff;border:1px solid #dcdcdc;border-radius:8px;padding:20px;min-width:220px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Status', 'pax-support-pro' ); ?></h2>
                <p><?php echo esc_html( $options['enabled'] ? __( 'Chat launcher is active.', 'pax-support-pro' ) : __( 'Chat launcher is disabled.', 'pax-support-pro' ) ); ?></p>
                <p><?php echo esc_html( $options['enable_ticket'] ? __( 'Ticket intake enabled.', 'pax-support-pro' ) : __( 'Ticket intake disabled.', 'pax-support-pro' ) ); ?></p>
            </div>
            <div class="card" style="background:#fff;border:1px solid #dcdcdc;border-radius:8px;padding:20px;min-width:220px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'OpenAI', 'pax-support-pro' ); ?></h2>
                <p><?php echo esc_html( ( $options['ai_assistant_enabled'] && $options['openai_enabled'] ) ? __( 'AI assistant is online.', 'pax-support-pro' ) : __( 'AI assistant disabled.', 'pax-support-pro' ) ); ?></p>
                <?php if ( ! empty( $options['openai_model'] ) ) : ?>
                    <p><strong><?php esc_html_e( 'Model:', 'pax-support-pro' ); ?></strong> <?php echo esc_html( $options['openai_model'] ); ?></p>
                <?php endif; ?>
            </div>
            <div class="card" style="background:#fff;border:1px solid #dcdcdc;border-radius:8px;padding:20px;min-width:220px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Quick Links', 'pax-support-pro' ); ?></h2>
                <ul style="margin:0;padding-left:18px;">
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-settings' ) ); ?>"><?php esc_html_e( 'Update settings', 'pax-support-pro' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-console' ) ); ?>"><?php esc_html_e( 'Open console', 'pax-support-pro' ); ?></a></li>
                    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-console' ) ); ?>"><?php esc_html_e( 'View tickets', 'pax-support-pro' ); ?></a></li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

function pax_sup_admin_notice( $message, $type = 'success' ) {
    printf(
        '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr( $type ),
        wp_kses_post( $message )
    );
}

/**
 * Render settings tab navigation.
 *
 * @param string $active_tab Active tab slug.
 */
function pax_sup_render_settings_tabs( $active_tab = 'general' ) {
    $general_url    = admin_url( 'admin.php?page=pax-support-settings' );
    $live_agent_url = add_query_arg(
        array(
            'page' => 'pax-support-settings',
            'tab'  => 'live-agent',
        ),
        admin_url( 'admin.php' )
    );

    $tabs = array(
        'general'    => array(
            'label' => __( 'General', 'pax-support-pro' ),
            'url'   => $general_url,
            'icon'  => 'admin-generic',
        ),
        'live-agent' => array(
            'label' => __( 'Live Agent', 'pax-support-pro' ),
            'url'   => $live_agent_url,
            'icon'  => 'format-chat',
        ),
    );

    echo '<nav class="pax-settings-tabs" aria-label="' . esc_attr__( 'PAX Support settings tabs', 'pax-support-pro' ) . '">';
    foreach ( $tabs as $slug => $tab ) {
        $is_active = $active_tab === $slug;
        printf(
            '<a href="%1$s" class="pax-settings-tab %4$s" %5$s>'
                . '<span class="dashicons dashicons-%3$s"></span>'
                . '<span class="pax-settings-tab-label">%2$s</span>'
            . '</a>',
            esc_url( $tab['url'] ),
            esc_html( $tab['label'] ),
            esc_attr( $tab['icon'] ),
            $is_active ? 'active' : '',
            $is_active ? 'aria-current="page"' : ''
        );
    }
    echo '</nav>';
}

function pax_sup_render_settings() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }

    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

    if ( 'live-agent' === $active_tab ) {
        require_once plugin_dir_path( __FILE__ ) . 'settings-live-agent.php';
        pax_sup_render_live_agent_settings();
        return;
    }

    // Handle form submission
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'pax_sup_save_settings' ) ) {
        $input = wp_unslash( $_POST );

        // Process chat menu items
        $menu_items = array();
        $default_menu = pax_sup_default_menu_items();
        
        if ( ! empty( $input['menu_items'] ) && is_array( $input['menu_items'] ) ) {
            foreach ( $default_menu as $key => $default_item ) {
                if ( isset( $input['menu_items'][ $key ] ) ) {
                    $menu_items[ $key ] = array(
                        'label'   => sanitize_text_field( $input['menu_items'][ $key ]['label'] ?? $default_item['label'] ),
                        'visible' => ! empty( $input['menu_items'][ $key ]['visible'] ) ? 1 : 0,
                    );
                } else {
                    $menu_items[ $key ] = $default_item;
                }
            }
        } else {
            $menu_items = $default_menu;
        }

        $new = array(
            'enabled'              => ! empty( $input['enabled'] ) ? 1 : 0,
            'enable_chat'          => ! empty( $input['enable_chat'] ) ? 1 : 0,
            'chat_access_control'  => in_array( $input['chat_access_control'] ?? 'everyone', array( 'everyone', 'logged_in', 'disabled' ), true ) ? $input['chat_access_control'] : 'everyone',
            'chat_disabled_message' => sanitize_text_field( $input['chat_disabled_message'] ?? 'Chat is currently disabled. Please try again later.' ),
            'disable_chat_menu'    => ! empty( $input['disable_chat_menu'] ) ? 1 : 0,
            'enable_ticket'        => ! empty( $input['enable_ticket'] ) ? 1 : 0,
            'enable_console'       => ! empty( $input['enable_console'] ) ? 1 : 0,
            'enable_offline_guard' => ! empty( $input['enable_offline_guard'] ) ? 1 : 0,
            'ai_assistant_enabled' => ! empty( $input['ai_assistant_enabled'] ) ? 1 : 0,
            'openai_enabled'       => ! empty( $input['openai_enabled'] ) ? 1 : 0,
            'openai_key'           => sanitize_text_field( $input['openai_key'] ?? '' ),
            'openai_model'         => sanitize_text_field( $input['openai_model'] ?? 'gpt-4o-mini' ),
            'openai_temperature'   => min( 1, max( 0, floatval( $input['openai_temperature'] ?? 0.35 ) ) ),
            'launcher_position'    => in_array( $input['launcher_position'] ?? 'bottom-left', array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ), true ) ? $input['launcher_position'] : 'bottom-left',
            'launcher_auto_open'   => ! empty( $input['launcher_auto_open'] ) ? 1 : 0,
            'toggle_on_click'      => ! empty( $input['toggle_on_click'] ) ? 1 : 0,
            'brand_name'           => sanitize_text_field( $input['brand_name'] ?? 'PAX SUPPORT' ),
            'color_accent'         => sanitize_hex_color( $input['color_accent'] ?? '#e53935' ),
            'color_bg'             => sanitize_hex_color( $input['color_bg'] ?? '#0d0f12' ),
            'color_panel'          => sanitize_hex_color( $input['color_panel'] ?? '#121418' ),
            'color_border'         => sanitize_hex_color( $input['color_border'] ?? '#2a2d33' ),
            'color_text'           => sanitize_hex_color( $input['color_text'] ?? '#e8eaf0' ),
            'color_sub'            => sanitize_hex_color( $input['color_sub'] ?? '#9aa0a8' ),
            'reaction_btn_color'   => sanitize_hex_color( $input['reaction_btn_color'] ?? '#e53935' ),
            'custom_send_icon'     => esc_url_raw( $input['custom_send_icon'] ?? '' ),
            'custom_launcher_icon' => esc_url_raw( $input['custom_launcher_icon'] ?? '' ),
            'live_agent_email'     => sanitize_email( $input['live_agent_email'] ?? get_option( 'admin_email' ) ),
            'callback_enabled'     => ! empty( $input['callback_enabled'] ) ? 1 : 0,
            'live_agent_enabled'   => ! empty( $input['live_agent_enabled'] ) ? 1 : 0,
            'help_center_url'      => esc_url_raw( $input['help_center_url'] ?? home_url( '/help/' ) ),
            'whats_new_url'        => esc_url_raw( $input['whats_new_url'] ?? '' ),
            'donate_url'           => esc_url_raw( $input['donate_url'] ?? 'https://www.paypal.me/AhmadAlkhalaf29' ),
            'console_cap'          => sanitize_text_field( $input['console_cap'] ?? 'manage_options' ),
            'ticket_cooldown_days' => max( 0, intval( $input['ticket_cooldown_days'] ?? 0 ) ),
            'auto_update_enabled'    => ! empty( $input['auto_update_enabled'] ) ? 1 : 0,
            'update_check_frequency' => in_array( $input['update_check_frequency'] ?? 'daily', array( 'daily', 'weekly' ), true ) ? $input['update_check_frequency'] : 'daily',
            'backup_local_enabled'   => ! empty( $input['backup_local_enabled'] ) ? 1 : 0,
            'backup_google_drive'    => ! empty( $input['backup_google_drive'] ) ? 1 : 0,
            'backup_dropbox'         => ! empty( $input['backup_dropbox'] ) ? 1 : 0,
            'chat_menu_items'        => $menu_items,
            'pax_chat_custom_menus'  => isset( $input['pax_chat_custom_menus'] ) && is_array( $input['pax_chat_custom_menus'] ) 
                ? array_map( function( $menu ) {
                    return array(
                        'name'    => sanitize_text_field( $menu['name'] ?? '' ),
                        'url'     => esc_url_raw( $menu['url'] ?? '' ),
                        'enabled' => ! empty( $menu['enabled'] ) ? 1 : 0,
                    );
                }, array_filter( $input['pax_chat_custom_menus'], function( $menu ) {
                    return ! empty( $menu['name'] ) && ! empty( $menu['url'] );
                } ) )
                : array(),
            'welcome_message'        => sanitize_textarea_field( $input['welcome_message'] ?? '' ),
            'welcome_placement'      => in_array( $input['welcome_placement'] ?? 'banner', array( 'banner', 'inline', 'bubble' ), true ) ? $input['welcome_placement'] : 'banner',
            'welcome_alignment'      => in_array( $input['welcome_alignment'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? $input['welcome_alignment'] : 'left',
            'welcome_style'          => in_array( $input['welcome_style'] ?? 'subtle', array( 'subtle', 'accent', 'high-contrast' ), true ) ? $input['welcome_style'] : 'subtle',
            'welcome_display_rule'   => in_array( $input['welcome_display_rule'] ?? 'always', array( 'always', 'first-session' ), true ) ? $input['welcome_display_rule'] : 'always',
            'welcome_max_lines'      => max( 1, min( 10, intval( $input['welcome_max_lines'] ?? 3 ) ) ),
            'welcome_show_icon'      => ! empty( $input['welcome_show_icon'] ) ? 1 : 0,
            'welcome_animation'      => in_array( $input['welcome_animation'] ?? 'fade', array( 'none', 'fade', 'slide', 'typewriter' ), true ) ? $input['welcome_animation'] : 'fade',
            'welcome_animation_duration' => max( 100, min( 1000, intval( $input['welcome_animation_duration'] ?? 300 ) ) ),
            'enable_reply_to'        => ! empty( $input['enable_reply_to'] ) ? 1 : 0,
            'enable_quick_actions'   => ! empty( $input['enable_quick_actions'] ) ? 1 : 0,
            // v5.3.0: Removed enable_customization (feature removed)
            // Chat Reactions
            'chat_reactions_enable_copy'    => ! empty( $input['chat_reactions_enable_copy'] ) ? 1 : 0,
            'chat_reactions_enable_like'    => ! empty( $input['chat_reactions_enable_like'] ) ? 1 : 0,
            'chat_reactions_enable_dislike' => ! empty( $input['chat_reactions_enable_dislike'] ) ? 1 : 0,
            // Chat Animations
            'chat_animations_enabled'       => ! empty( $input['chat_animations_enabled'] ) ? 1 : 0,
            'chat_animation_duration'       => max( 100, min( 1000, intval( $input['chat_animation_duration'] ?? 300 ) ) ),
            'chat_animation_easing'         => sanitize_text_field( $input['chat_animation_easing'] ?? 'ease' ),
        );

        pax_sup_update_options( $new );
        if ( function_exists( 'pax_sup_updater' ) ) {
            pax_sup_updater()->maybe_schedule_checks();
        }
        
        // Clear update cache to ensure immediate detection of new releases
        if ( function_exists( 'pax_sup_updater' ) ) {
            pax_sup_updater()->clear_update_cache();
        }
        
        pax_sup_admin_notice( __( 'Settings saved.', 'pax-support-pro' ) );
    }

    $options = pax_sup_get_options();
    $stored_notice = pax_sup_pull_admin_notice();
    
    // Include modern UI rendering
    require_once plugin_dir_path( __FILE__ ) . 'settings-modern-ui.php';
    pax_sup_render_modern_settings( $options, $stored_notice, $active_tab );
}

/**
 * AJAX handler to update agent online status
 */
function pax_sup_ajax_update_agent_status() {
    check_ajax_referer( 'pax_liveagent_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_pax_chats' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }

    $user_id = get_current_user_id();
    $timestamp = isset( $_POST['timestamp'] ) ? intval( $_POST['timestamp'] ) : time();

    update_user_meta( $user_id, 'pax_last_seen', $timestamp );

    wp_send_json_success( array( 'timestamp' => $timestamp ) );
}
add_action( 'wp_ajax_pax_update_agent_status', 'pax_sup_ajax_update_agent_status' );

/**
 * AJAX handler for resetting settings to defaults
 */
function pax_sup_ajax_reset_settings() {
    // Verify nonce
    if ( ! check_ajax_referer( 'pax_sup_reset_settings', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pax-support-pro' ) ) );
    }

    // Check user capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'You do not have permission to reset settings.', 'pax-support-pro' ) ) );
    }

    // Call the reset function
    $result = reset_chat_defaults();

    if ( $result ) {
        wp_send_json_success( array( 
            'message' => __( 'Settings have been reset to defaults successfully.', 'pax-support-pro' ),
            'reload' => true
        ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Failed to reset settings.', 'pax-support-pro' ) ) );
    }
}
add_action( 'wp_ajax_pax_sup_reset_settings', 'pax_sup_ajax_reset_settings' );