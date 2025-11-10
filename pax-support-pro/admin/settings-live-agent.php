<?php
/**
 * Live Agent Settings Screen (Modern UI)
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_render_live_agent_settings() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }

    $notice_slug = 'pax_liveagent_settings';

    if (
        'POST' === $_SERVER['REQUEST_METHOD'] &&
        isset( $_POST['pax_liveagent_settings_nonce'] ) &&
        wp_verify_nonce( wp_unslash( $_POST['pax_liveagent_settings_nonce'] ), 'pax_liveagent_settings_save' )
    ) {
        $input = wp_unslash( $_POST );

        $input['enabled']               = ! empty( $input['enabled'] );
        $input['auto_accept']           = ! empty( $input['auto_accept'] );
        $input['allow_file_uploads']    = ! empty( $input['allow_file_uploads'] );
        $input['sound_enabled']         = ! empty( $input['sound_enabled'] );
        $input['email_notifications']   = ! empty( $input['email_notifications'] );
        $input['browser_notifications'] = ! empty( $input['browser_notifications'] );
        $input['cloudflare_mode']       = ! empty( $input['cloudflare_mode'] );

        if ( isset( $input['allowed_file_types'] ) ) {
            $input['allowed_file_types'] = $input['allowed_file_types'];
        }

        $saved = pax_sup_save_liveagent_settings( $input );

        if ( $saved ) {
            add_settings_error(
                $notice_slug,
                'pax_liveagent_settings_saved',
                __( 'Live Agent settings updated successfully.', 'pax-support-pro' ),
                'updated'
            );
        } else {
            add_settings_error(
                $notice_slug,
                'pax_liveagent_settings_failed',
                __( 'Unable to save Live Agent settings. Please try again.', 'pax-support-pro' ),
                'error'
            );
        }
    }

    $settings = pax_sup_get_liveagent_settings();

    $rest_base   = trailingslashit( rest_url( 'pax/v1' ) );
    $site_url    = get_site_url();
    $site_host   = wp_parse_url( $site_url, PHP_URL_HOST );
    $resolved_ip = $site_host ? @gethostbyname( $site_host ) : '';

    $ip_display = ( empty( $resolved_ip ) || $resolved_ip === $site_host )
        ? __( 'Unable to resolve', 'pax-support-pro' )
        : $resolved_ip;

    $allowed_types_text = implode( ', ', (array) $settings['allowed_file_types'] );

    pax_sup_render_settings_tabs( 'live-agent' );

    settings_errors( $notice_slug );
    ?>
    <div class="pax-modern-settings pax-liveagent-settings">
        <div class="pax-settings-header">
            <h1>
                <span class="dashicons dashicons-headset"></span>
                <?php esc_html_e( 'Live Agent Settings', 'pax-support-pro' ); ?>
            </h1>
            <p class="pax-settings-subtitle">
                <?php esc_html_e( 'Configure diagnostics, routing, and human escalation behaviour for Live Agent chat.', 'pax-support-pro' ); ?>
            </p>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field( 'pax_liveagent_settings_save', 'pax_liveagent_settings_nonce' ); ?>

            <div class="pax-card pax-liveagent-card">
                <div class="pax-card-header">
                    <span class="dashicons dashicons-visibility"></span>
                    <h2><?php esc_html_e( 'Connection & Diagnostics', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <dl class="pax-diagnostic-list">
                        <div class="pax-diagnostic-item">
                            <dt><?php esc_html_e( 'REST Base URL', 'pax-support-pro' ); ?></dt>
                            <dd>
                                <code class="pax-diagnostic-code" data-copy-source><?php echo esc_html( $rest_base ); ?></code>
                                <button type="button" class="button button-secondary pax-copy-button" data-copy-value="<?php echo esc_attr( $rest_base ); ?>">
                                    <span class="dashicons dashicons-admin-page"></span>
                                    <span class="pax-copy-label"><?php esc_html_e( 'Copy', 'pax-support-pro' ); ?></span>
                                </button>
                            </dd>
                        </div>
                        <div class="pax-diagnostic-item">
                            <dt><?php esc_html_e( 'Site Domain', 'pax-support-pro' ); ?></dt>
                            <dd><?php echo esc_html( $site_host ? $site_host : $site_url ); ?></dd>
                        </div>
                        <div class="pax-diagnostic-item">
                            <dt><?php esc_html_e( 'Resolved IP', 'pax-support-pro' ); ?></dt>
                            <dd><?php echo esc_html( $ip_display ); ?></dd>
                        </div>
                    </dl>
                    <div class="pax-diagnostic-actions">
                        <button type="button" class="button button-primary" id="pax-liveagent-test-connection">
                            <span class="dashicons dashicons-rss"></span>
                            <?php esc_html_e( 'Test Connection', 'pax-support-pro' ); ?>
                        </button>
                        <span class="pax-test-status" id="pax-liveagent-test-status" aria-live="polite"></span>
                    </div>
                </div>
            </div>

            <div class="pax-liveagent-grid">
                <div class="pax-liveagent-column">
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-controls"></span>
                            <h2><?php esc_html_e( 'Activation & Routing', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <div class="pax-setting-row">
                                <div>
                                    <h3><?php esc_html_e( 'Enable Live Agent', 'pax-support-pro' ); ?></h3>
                                    <p><?php esc_html_e( 'Allows customers to request a human agent from the chat widget.', 'pax-support-pro' ); ?></p>
                                </div>
                                <label class="pax-toggle">
                                    <input type="checkbox" name="enabled" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
                                    <span class="pax-toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pax-setting-row">
                                <div>
                                    <h3><?php esc_html_e( 'Auto-accept Sessions', 'pax-support-pro' ); ?></h3>
                                    <p><?php esc_html_e( 'Automatically assign the oldest pending chat to the first available agent.', 'pax-support-pro' ); ?></p>
                                </div>
                                <label class="pax-toggle">
                                    <input type="checkbox" name="auto_accept" <?php checked( ! empty( $settings['auto_accept'] ) ); ?>>
                                    <span class="pax-toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pax-form-group-inline">
                                <label for="pax-max-concurrent-chats"><?php esc_html_e( 'Concurrent Chats per Agent', 'pax-support-pro' ); ?></label>
                                <input type="number" id="pax-max-concurrent-chats" name="max_concurrent_chats" min="1" max="50" value="<?php echo esc_attr( intval( $settings['max_concurrent_chats'] ) ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-clock"></span>
                            <h2><?php esc_html_e( 'Session Lifecycle', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <div class="pax-form-group-inline">
                                <label for="pax-timeout-seconds"><?php esc_html_e( 'Auto-decline After (seconds)', 'pax-support-pro' ); ?></label>
                                <input type="number" id="pax-timeout-seconds" name="timeout_seconds" min="30" max="600" step="10" value="<?php echo esc_attr( intval( $settings['timeout_seconds'] ) ); ?>">
                            </div>
                            <div class="pax-form-group-inline">
                                <label for="pax-auto-close-minutes"><?php esc_html_e( 'Auto-close Inactive Chats (minutes)', 'pax-support-pro' ); ?></label>
                                <input type="number" id="pax-auto-close-minutes" name="auto_close_minutes" min="5" max="240" value="<?php echo esc_attr( intval( $settings['auto_close_minutes'] ) ); ?>">
                            </div>
                            <div class="pax-form-group-inline">
                                <label for="pax-poll-interval"><?php esc_html_e( 'Polling Interval (seconds)', 'pax-support-pro' ); ?></label>
                                <input type="number" id="pax-poll-interval" name="poll_interval" min="5" max="120" value="<?php echo esc_attr( intval( $settings['poll_interval'] ) ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-format-chat"></span>
                            <h2><?php esc_html_e( 'Customer Experience', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <div class="pax-form-group">
                                <label for="pax-button-text"><?php esc_html_e( 'Launcher Button Text', 'pax-support-pro' ); ?></label>
                                <input type="text" id="pax-button-text" name="button_text" value="<?php echo esc_attr( $settings['button_text'] ); ?>">
                            </div>
                            <div class="pax-form-group">
                                <label for="pax-welcome-message"><?php esc_html_e( 'Welcome Message', 'pax-support-pro' ); ?></label>
                                <textarea id="pax-welcome-message" name="welcome_message" rows="3"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea>
                            </div>
                            <div class="pax-form-group">
                                <label for="pax-button-position"><?php esc_html_e( 'Launcher Position', 'pax-support-pro' ); ?></label>
                                <select id="pax-button-position" name="button_position">
                                    <option value="bottom-right" <?php selected( $settings['button_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'pax-support-pro' ); ?></option>
                                    <option value="bottom-left" <?php selected( $settings['button_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'pax-support-pro' ); ?></option>
                                    <option value="top-right" <?php selected( $settings['button_position'], 'top-right' ); ?>><?php esc_html_e( 'Top Right', 'pax-support-pro' ); ?></option>
                                    <option value="top-left" <?php selected( $settings['button_position'], 'top-left' ); ?>><?php esc_html_e( 'Top Left', 'pax-support-pro' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pax-liveagent-column">
                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-megaphone"></span>
                            <h2><?php esc_html_e( 'Notifications', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <div class="pax-setting-row">
                                <div>
                                    <h3><?php esc_html_e( 'Sound Alerts', 'pax-support-pro' ); ?></h3>
                                    <p><?php esc_html_e( 'Play a gentle notification sound when new messages land in the queue.', 'pax-support-pro' ); ?></p>
                                </div>
                                <label class="pax-toggle">
                                    <input type="checkbox" name="sound_enabled" <?php checked( ! empty( $settings['sound_enabled'] ) ); ?>>
                                    <span class="pax-toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pax-setting-row">
                                <div>
                                    <h3><?php esc_html_e( 'Browser Notifications', 'pax-support-pro' ); ?></h3>
                                    <p><?php esc_html_e( 'Prompt agents to enable desktop notifications while the console is open.', 'pax-support-pro' ); ?></p>
                                </div>
                                <label class="pax-toggle">
                                    <input type="checkbox" name="browser_notifications" <?php checked( ! empty( $settings['browser_notifications'] ) ); ?>>
                                    <span class="pax-toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pax-setting-row">
                                <div>
                                    <h3><?php esc_html_e( 'Email Alerts', 'pax-support-pro' ); ?></h3>
                                    <p><?php esc_html_e( 'Send an email when a new request waits longer than the timeout.', 'pax-support-pro' ); ?></p>
                                </div>
                                <label class="pax-toggle">
                                    <input type="checkbox" name="email_notifications" <?php checked( ! empty( $settings['email_notifications'] ) ); ?>>
                                    <span class="pax-toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pax-form-group">
                                <label for="pax-notification-email"><?php esc_html_e( 'Notification Email', 'pax-support-pro' ); ?></label>
                                <input type="email" id="pax-notification-email" name="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-media-default"></span>
                            <h2><?php esc_html_e( 'File Sharing & Retention', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <div class="pax-setting-row">
                                <div>
                                    <h3><?php esc_html_e( 'Allow File Uploads', 'pax-support-pro' ); ?></h3>
                                    <p><?php esc_html_e( 'Permit JPEG, PDF, and other safe file types from customers.', 'pax-support-pro' ); ?></p>
                                </div>
                                <label class="pax-toggle">
                                    <input type="checkbox" name="allow_file_uploads" <?php checked( ! empty( $settings['allow_file_uploads'] ) ); ?>>
                                    <span class="pax-toggle-slider"></span>
                                </label>
                            </div>
                            <div class="pax-form-group-inline">
                                <label for="pax-max-file-size"><?php esc_html_e( 'Max File Size (MB)', 'pax-support-pro' ); ?></label>
                                <input type="number" id="pax-max-file-size" name="max_file_size_mb" min="1" max="100" value="<?php echo esc_attr( intval( $settings['max_file_size_mb'] ) ); ?>">
                            </div>
                            <div class="pax-form-group">
                                <label for="pax-allowed-file-types"><?php esc_html_e( 'Allowed File Types', 'pax-support-pro' ); ?></label>
                                <input type="text" id="pax-allowed-file-types" name="allowed_file_types" value="<?php echo esc_attr( $allowed_types_text ); ?>">
                                <p class="description"><?php esc_html_e( 'Comma separated (for example: jpg,png,pdf).', 'pax-support-pro' ); ?></p>
                            </div>
                            <div class="pax-form-group-inline">
                                <label for="pax-message-limit"><?php esc_html_e( 'Message History Limit', 'pax-support-pro' ); ?></label>
                                <input type="number" id="pax-message-limit" name="message_history_limit" min="20" max="500" value="<?php echo esc_attr( intval( $settings['message_history_limit'] ) ); ?>">
                            </div>
                            <div class="pax-form-group-inline">
                                <label for="pax-archive-days"><?php esc_html_e( 'Archive Sessions After (days)', 'pax-support-pro' ); ?></label>
                                <input type="number" id="pax-archive-days" name="archive_after_days" min="1" max="180" value="<?php echo esc_attr( intval( $settings['archive_after_days'] ) ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="pax-card">
                        <div class="pax-card-header">
                            <span class="dashicons dashicons-shield"></span>
                            <h2><?php esc_html_e( 'Network Compatibility', 'pax-support-pro' ); ?></h2>
                        </div>
                        <div class="pax-card-body">
                            <div class="pax-setting-row">
                                <div>
                                    <h3><?php esc_html_e( 'Cloudflare Mode', 'pax-support-pro' ); ?></h3>
                                    <p><?php esc_html_e( 'Adjust polling intervals and caching headers for proxy/CDN environments.', 'pax-support-pro' ); ?></p>
                                </div>
                                <label class="pax-toggle">
                                    <input type="checkbox" name="cloudflare_mode" <?php checked( ! empty( $settings['cloudflare_mode'] ) ); ?>>
                                    <span class="pax-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pax-liveagent-actions">
                <button type="submit" class="button button-primary button-hero">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( 'Save Live Agent Settings', 'pax-support-pro' ); ?>
                </button>
            </div>
        </form>
    </div>
    <?php
}
