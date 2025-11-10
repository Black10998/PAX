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
 * Render Live Agent settings section
 */
function pax_sup_render_liveagent_settings_section() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    settings_errors( 'pax_liveagent' );

    // Handle form submission
    if ( isset( $_POST['pax_liveagent_settings_nonce'] ) && 
         wp_verify_nonce( $_POST['pax_liveagent_settings_nonce'], 'pax_liveagent_settings_save' ) ) {
        pax_sup_save_liveagent_settings();
    }

    $settings = pax_sup_get_liveagent_settings();
    $rest_base = rest_url( 'pax/v1/' );
    $site_url  = get_site_url();
    $site_host = wp_parse_url( $site_url, PHP_URL_HOST );
    $resolved_ip = $site_host ? gethostbyname( $site_host ) : '';
    $resolved_ip = ( $resolved_ip && $resolved_ip !== $site_host && filter_var( $resolved_ip, FILTER_VALIDATE_IP ) )
        ? $resolved_ip
        : __( 'Unavailable', 'pax-support-pro' );

    $is_enabled = ! empty( $settings['enabled'] );
    $status_label = $is_enabled
        ? __( 'Live Agent Enabled', 'pax-support-pro' )
        : __( 'Live Agent Disabled', 'pax-support-pro' );

    ?>
    <form method="post" action="" class="pax-liveagent-form">
        <?php wp_nonce_field( 'pax_liveagent_settings_save', 'pax_liveagent_settings_nonce' ); ?>
        <div class="pax-liveagent-settings <?php echo is_rtl() ? 'is-rtl' : 'is-ltr'; ?>">
            <div class="pax-liveagent-main">
                <header class="pax-liveagent-header">
                    <div class="pax-liveagent-heading">
                        <span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
                        <div>
                            <h2><?php esc_html_e( 'Live Agent', 'pax-support-pro' ); ?></h2>
                            <p><?php esc_html_e( 'Control real-time agent handoffs, notification channels, and experience for live chats.', 'pax-support-pro' ); ?></p>
                        </div>
                    </div>
                    <span class="pax-status-chip <?php echo $is_enabled ? 'is-online' : 'is-offline'; ?>">
                        <?php echo esc_html( $status_label ); ?>
                    </span>
                </header>

                <section class="pax-surface">
                    <div class="pax-section-heading">
                        <h3><?php esc_html_e( 'Availability & Routing', 'pax-support-pro' ); ?></h3>
                        <p><?php esc_html_e( 'Decide when agents receive chats and how many conversations can be handled simultaneously.', 'pax-support-pro' ); ?></p>
                    </div>
                    <div class="pax-field">
                        <div>
                            <label for="pax-liveagent-enabled"><?php esc_html_e( 'Enable Live Agent system', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Turn on the agent console and allow visitors to request a human response.', 'pax-support-pro' ); ?></p>
                        </div>
                        <label class="pax-switch">
                            <input id="pax-liveagent-enabled" type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
                            <span class="pax-switch__track" aria-hidden="true"><span class="pax-switch__handle"></span></span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Enable Live Agent system', 'pax-support-pro' ); ?></span>
                        </label>
                    </div>
                    <div class="pax-field">
                        <div>
                            <label for="pax-liveagent-auto-accept"><?php esc_html_e( 'Auto-accept incoming sessions', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Automatically route the oldest pending request to the first available agent.', 'pax-support-pro' ); ?></p>
                        </div>
                        <label class="pax-switch">
                            <input id="pax-liveagent-auto-accept" type="checkbox" name="auto_accept" value="1" <?php checked( $settings['auto_accept'] ); ?>>
                            <span class="pax-switch__track" aria-hidden="true"><span class="pax-switch__handle"></span></span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Auto-accept incoming sessions', 'pax-support-pro' ); ?></span>
                        </label>
                    </div>
                    <div class="pax-grid">
                        <div class="pax-input-group">
                            <label for="pax-liveagent-max-chats"><?php esc_html_e( 'Max concurrent chats', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Recommended 1–10 depending on team size.', 'pax-support-pro' ); ?></p>
                            <input id="pax-liveagent-max-chats" type="number" min="1" max="25" name="max_concurrent_chats" value="<?php echo esc_attr( (int) $settings['max_concurrent_chats'] ); ?>" />
                        </div>
                        <div class="pax-input-group">
                            <label for="pax-liveagent-button-position"><?php esc_html_e( 'Launcher position', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Choose where the “Live Agent” button sits on your site.', 'pax-support-pro' ); ?></p>
                            <select id="pax-liveagent-button-position" name="button_position">
                                <option value="bottom-right" <?php selected( $settings['button_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'pax-support-pro' ); ?></option>
                                <option value="bottom-left" <?php selected( $settings['button_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'pax-support-pro' ); ?></option>
                                <option value="top-right" <?php selected( $settings['button_position'], 'top-right' ); ?>><?php esc_html_e( 'Top right', 'pax-support-pro' ); ?></option>
                                <option value="top-left" <?php selected( $settings['button_position'], 'top-left' ); ?>><?php esc_html_e( 'Top left', 'pax-support-pro' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="pax-grid">
                        <div class="pax-input-group">
                            <label for="pax-liveagent-button-text"><?php esc_html_e( 'Launcher label', 'pax-support-pro' ); ?></label>
                            <input id="pax-liveagent-button-text" type="text" name="button_text" value="<?php echo esc_attr( $settings['button_text'] ); ?>" />
                        </div>
                        <div class="pax-input-group">
                            <label for="pax-liveagent-welcome"><?php esc_html_e( 'Welcome message', 'pax-support-pro' ); ?></label>
                            <textarea id="pax-liveagent-welcome" name="welcome_message" rows="3"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="pax-surface">
                    <div class="pax-section-heading">
                        <h3><?php esc_html_e( 'Files & Notifications', 'pax-support-pro' ); ?></h3>
                        <p><?php esc_html_e( 'Control upload permissions and how agents are notified about new activity.', 'pax-support-pro' ); ?></p>
                    </div>
                    <div class="pax-field">
                        <div>
                            <label for="pax-liveagent-uploads"><?php esc_html_e( 'Allow file uploads', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Permit visitors to send attachments to the agent during a session.', 'pax-support-pro' ); ?></p>
                        </div>
                        <label class="pax-switch">
                            <input id="pax-liveagent-uploads" type="checkbox" name="allow_file_uploads" value="1" <?php checked( $settings['allow_file_uploads'] ); ?>>
                            <span class="pax-switch__track" aria-hidden="true"><span class="pax-switch__handle"></span></span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Allow file uploads', 'pax-support-pro' ); ?></span>
                        </label>
                    </div>
                    <div class="pax-grid">
                        <div class="pax-input-group">
                            <label for="pax-liveagent-max-file"><?php esc_html_e( 'Max file size (MB)', 'pax-support-pro' ); ?></label>
                            <input id="pax-liveagent-max-file" type="number" min="1" max="50" name="max_file_size_mb" value="<?php echo esc_attr( (int) $settings['max_file_size_mb'] ); ?>" />
                        </div>
                        <div class="pax-input-group">
                            <span class="pax-label"><?php esc_html_e( 'Supported formats', 'pax-support-pro' ); ?></span>
                            <p class="pax-tag-list">
                                <?php echo esc_html( implode( ', ', array_map( 'strtoupper', (array) $settings['allowed_file_types'] ) ) ); ?>
                            </p>
                        </div>
                    </div>
                    <div class="pax-field">
                        <div>
                            <label for="pax-liveagent-sound"><?php esc_html_e( 'Play sound notifications', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Play an audible chime when a new message or request arrives.', 'pax-support-pro' ); ?></p>
                        </div>
                        <label class="pax-switch">
                            <input id="pax-liveagent-sound" type="checkbox" name="sound_enabled" value="1" <?php checked( $settings['sound_enabled'] ); ?>>
                            <span class="pax-switch__track" aria-hidden="true"><span class="pax-switch__handle"></span></span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Play sound notifications', 'pax-support-pro' ); ?></span>
                        </label>
                    </div>
                    <div class="pax-field">
                        <div>
                            <label for="pax-liveagent-browser"><?php esc_html_e( 'Show browser notifications', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Request permission to surface desktop notifications when the console is unfocused.', 'pax-support-pro' ); ?></p>
                        </div>
                        <label class="pax-switch">
                            <input id="pax-liveagent-browser" type="checkbox" name="browser_notifications" value="1" <?php checked( $settings['browser_notifications'] ?? false ); ?>>
                            <span class="pax-switch__track" aria-hidden="true"><span class="pax-switch__handle"></span></span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Show browser notifications', 'pax-support-pro' ); ?></span>
                        </label>
                    </div>
                    <div class="pax-field">
                        <div>
                            <label for="pax-liveagent-email"><?php esc_html_e( 'Email notifications', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Send an email alert when a visitor is waiting for a live agent.', 'pax-support-pro' ); ?></p>
                            <div class="pax-input-inline">
                                <input id="pax-liveagent-email" type="email" name="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" placeholder="<?php esc_attr_e( 'team@example.com', 'pax-support-pro' ); ?>" />
                            </div>
                        </div>
                        <label class="pax-switch">
                            <input type="checkbox" name="email_notifications" value="1" <?php checked( $settings['email_notifications'] ); ?>>
                            <span class="pax-switch__track" aria-hidden="true"><span class="pax-switch__handle"></span></span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Enable email notifications', 'pax-support-pro' ); ?></span>
                        </label>
                    </div>
                </section>

                <section class="pax-surface">
                    <div class="pax-section-heading">
                        <h3><?php esc_html_e( 'Session Lifecycle', 'pax-support-pro' ); ?></h3>
                        <p><?php esc_html_e( 'Keep chats healthy with sensible timeouts and clean-up routines.', 'pax-support-pro' ); ?></p>
                    </div>
                    <div class="pax-grid">
                        <div class="pax-input-group">
                            <label for="pax-liveagent-auto-close"><?php esc_html_e( 'Auto-close after inactivity (minutes)', 'pax-support-pro' ); ?></label>
                            <input id="pax-liveagent-auto-close" type="number" min="5" max="240" name="auto_close_minutes" value="<?php echo esc_attr( (int) $settings['auto_close_minutes'] ); ?>" />
                        </div>
                        <div class="pax-input-group">
                            <label for="pax-liveagent-timeout"><?php esc_html_e( 'Pending request timeout (seconds)', 'pax-support-pro' ); ?></label>
                            <input id="pax-liveagent-timeout" type="number" min="15" max="600" step="5" name="timeout_seconds" value="<?php echo esc_attr( (int) $settings['timeout_seconds'] ); ?>" />
                        </div>
                    </div>
                    <div class="pax-grid">
                        <div class="pax-input-group">
                            <label for="pax-liveagent-poll"><?php esc_html_e( 'Polling interval (seconds)', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'How often the console checks for new messages.', 'pax-support-pro' ); ?></p>
                            <input id="pax-liveagent-poll" type="number" min="5" max="60" name="poll_interval" value="<?php echo esc_attr( (int) $settings['poll_interval'] ); ?>" />
                        </div>
                        <div class="pax-input-group">
                            <label for="pax-liveagent-history-limit"><?php esc_html_e( 'Message history limit', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Number of messages to keep per live session.', 'pax-support-pro' ); ?></p>
                            <input id="pax-liveagent-history-limit" type="number" min="25" max="500" step="5" name="message_history_limit" value="<?php echo esc_attr( (int) $settings['message_history_limit'] ); ?>" />
                        </div>
                    </div>
                    <div class="pax-grid">
                        <div class="pax-input-group">
                            <label for="pax-liveagent-archive"><?php esc_html_e( 'Archive closed sessions after (days)', 'pax-support-pro' ); ?></label>
                            <input id="pax-liveagent-archive" type="number" min="1" max="365" name="archive_after_days" value="<?php echo esc_attr( (int) $settings['archive_after_days'] ); ?>" />
                        </div>
                        <div class="pax-input-group pax-input-group--checkbox">
                            <label for="pax-liveagent-cloudflare"><?php esc_html_e( 'Cloudflare compatibility mode', 'pax-support-pro' ); ?></label>
                            <p><?php esc_html_e( 'Adds additional no-cache headers and respects slower polling for proxied sites.', 'pax-support-pro' ); ?></p>
                            <label class="pax-switch">
                                <input id="pax-liveagent-cloudflare" type="checkbox" name="cloudflare_mode" value="1" <?php checked( $settings['cloudflare_mode'] ); ?>>
                                <span class="pax-switch__track" aria-hidden="true"><span class="pax-switch__handle"></span></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Enable Cloudflare compatibility', 'pax-support-pro' ); ?></span>
                            </label>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="pax-liveagent-aside">
                <section class="pax-surface pax-diagnostics">
                    <div class="pax-section-heading">
                        <h3><?php esc_html_e( 'Connection & Diagnostics', 'pax-support-pro' ); ?></h3>
                        <p><?php esc_html_e( 'Verify that the REST endpoints required for live chat are reachable.', 'pax-support-pro' ); ?></p>
                    </div>
                    <ul class="pax-diagnostic-list">
                        <li>
                            <span><?php esc_html_e( 'REST base URL', 'pax-support-pro' ); ?></span>
                            <code><?php echo esc_html( untrailingslashit( $rest_base ) ); ?></code>
                        </li>
                        <li>
                            <span><?php esc_html_e( 'Site domain', 'pax-support-pro' ); ?></span>
                            <code><?php echo esc_html( $site_host ? $site_host : parse_url( $site_url, PHP_URL_HOST ) ); ?></code>
                        </li>
                        <li>
                            <span><?php esc_html_e( 'Resolved IP', 'pax-support-pro' ); ?></span>
                            <code data-resolved-ip><?php echo esc_html( $resolved_ip ); ?></code>
                        </li>
                    </ul>
                    <div class="pax-diagnostic-action">
                        <button type="button" class="button button-secondary pax-diagnostic-button" data-action="test-connection">
                            <span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
                            <?php esc_html_e( 'Test Connection', 'pax-support-pro' ); ?>
                        </button>
                        <span class="pax-diagnostic-status" data-connection-status="idle">
                            <?php esc_html_e( 'Awaiting test…', 'pax-support-pro' ); ?>
                        </span>
                    </div>
                    <p class="pax-hint"><?php esc_html_e( 'If the test fails, ensure permalinks are enabled and no security plugin blocks REST requests.', 'pax-support-pro' ); ?></p>
                </section>
            </aside>
        </div>

        <div class="pax-liveagent-actions">
            <button type="submit" class="button button-primary button-hero">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <?php esc_html_e( 'Save Live Agent Settings', 'pax-support-pro' ); ?>
            </button>
        </div>
    </form>
    <?php
}

/**
 * Save Live Agent settings
 */
function pax_sup_save_liveagent_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings = array(
        'enabled' => isset( $_POST['enabled'] ) && $_POST['enabled'] === '1',
        'auto_accept' => isset( $_POST['auto_accept'] ) && $_POST['auto_accept'] === '1',
        'allow_file_uploads' => isset( $_POST['allow_file_uploads'] ) && $_POST['allow_file_uploads'] === '1',
        'max_file_size_mb' => isset( $_POST['max_file_size_mb'] ) ? (int) $_POST['max_file_size_mb'] : 10,
        'sound_enabled' => isset( $_POST['sound_enabled'] ) && $_POST['sound_enabled'] === '1',
        'email_notifications' => isset( $_POST['email_notifications'] ) && $_POST['email_notifications'] === '1',
        'browser_notifications' => isset( $_POST['browser_notifications'] ) && $_POST['browser_notifications'] === '1',
        'notification_email' => isset( $_POST['notification_email'] ) ? sanitize_email( $_POST['notification_email'] ) : get_option( 'admin_email' ),
        'button_position' => isset( $_POST['button_position'] ) ? sanitize_text_field( $_POST['button_position'] ) : 'bottom-right',
        'button_text' => isset( $_POST['button_text'] ) ? sanitize_text_field( $_POST['button_text'] ) : __( 'Live Agent', 'pax-support-pro' ),
        'welcome_message' => isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( $_POST['welcome_message'] ) : '',
        'auto_close_minutes' => isset( $_POST['auto_close_minutes'] ) ? (int) $_POST['auto_close_minutes'] : 30,
        'timeout_seconds' => isset( $_POST['timeout_seconds'] ) ? (int) $_POST['timeout_seconds'] : 60,
        'cloudflare_mode' => isset( $_POST['cloudflare_mode'] ) && $_POST['cloudflare_mode'] === '1',
        'max_concurrent_chats' => isset( $_POST['max_concurrent_chats'] ) ? max( 1, min( 50, (int) $_POST['max_concurrent_chats'] ) ) : 5,
        'poll_interval' => isset( $_POST['poll_interval'] ) ? max( 5, min( 120, (int) $_POST['poll_interval'] ) ) : 15,
        'message_history_limit' => isset( $_POST['message_history_limit'] ) ? max( 10, min( 1000, (int) $_POST['message_history_limit'] ) ) : 100,
        'archive_after_days' => isset( $_POST['archive_after_days'] ) ? max( 1, min( 365, (int) $_POST['archive_after_days'] ) ) : 30,
    );

    update_option( 'pax_liveagent_settings', $settings );

    add_settings_error(
        'pax_liveagent',
        'settings_updated',
        __( 'Live Agent settings updated successfully.', 'pax-support-pro' ),
        'success'
    );
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
