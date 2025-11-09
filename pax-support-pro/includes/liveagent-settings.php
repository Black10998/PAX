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

    // Handle form submission
    if ( isset( $_POST['pax_liveagent_settings_nonce'] ) && 
         wp_verify_nonce( $_POST['pax_liveagent_settings_nonce'], 'pax_liveagent_settings_save' ) ) {
        pax_sup_save_liveagent_settings();
    }

    $settings = pax_sup_get_liveagent_settings();
    ?>
    <div class="pax-card">
        <div class="pax-card-header">
            <h2>
                <span class="dashicons dashicons-format-chat"></span>
                <?php esc_html_e( 'Live Agent Settings', 'pax-support-pro' ); ?>
            </h2>
        </div>
        <div class="pax-card-body">
            <form method="post" action="">
                <?php wp_nonce_field( 'pax_liveagent_settings_save', 'pax_liveagent_settings_nonce' ); ?>

                <!-- Enable Live Agent -->
                <div class="pax-form-group">
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
                        <?php esc_html_e( 'Enable Live Agent System', 'pax-support-pro' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Enable real-time live chat between users and support agents.', 'pax-support-pro' ); ?>
                    </p>
                </div>

                <hr>

                <!-- File Uploads -->
                <h3><?php esc_html_e( 'File Uploads', 'pax-support-pro' ); ?></h3>
                
                <div class="pax-form-group">
                    <label>
                        <input type="checkbox" name="allow_file_uploads" value="1" <?php checked( $settings['allow_file_uploads'] ); ?>>
                        <?php esc_html_e( 'Allow File Uploads', 'pax-support-pro' ); ?>
                    </label>
                </div>

                <div class="pax-form-group">
                    <label><?php esc_html_e( 'Max File Size (MB)', 'pax-support-pro' ); ?></label>
                    <input type="number" name="max_file_size_mb" value="<?php echo esc_attr( $settings['max_file_size_mb'] ); ?>" min="1" max="50" class="pax-input">
                </div>

                <hr>

                <!-- Notifications -->
                <h3><?php esc_html_e( 'Notifications', 'pax-support-pro' ); ?></h3>

                <div class="pax-form-group">
                    <label>
                        <input type="checkbox" name="sound_enabled" value="1" <?php checked( $settings['sound_enabled'] ); ?>>
                        <?php esc_html_e( 'Enable Sound Notifications', 'pax-support-pro' ); ?>
                    </label>
                </div>

                <div class="pax-form-group">
                    <label>
                        <input type="checkbox" name="email_notifications" value="1" <?php checked( $settings['email_notifications'] ); ?>>
                        <?php esc_html_e( 'Enable Email Notifications', 'pax-support-pro' ); ?>
                    </label>
                </div>

                <div class="pax-form-group">
                    <label><?php esc_html_e( 'Notification Email', 'pax-support-pro' ); ?></label>
                    <input type="email" name="notification_email" value="<?php echo esc_attr( $settings['notification_email'] ); ?>" class="pax-input">
                </div>

                <hr>

                <!-- Display -->
                <h3><?php esc_html_e( 'Display Settings', 'pax-support-pro' ); ?></h3>

                <div class="pax-form-group">
                    <label><?php esc_html_e( 'Button Position', 'pax-support-pro' ); ?></label>
                    <select name="button_position" class="pax-select">
                        <option value="bottom-right" <?php selected( $settings['button_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'pax-support-pro' ); ?></option>
                        <option value="bottom-left" <?php selected( $settings['button_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'pax-support-pro' ); ?></option>
                        <option value="top-right" <?php selected( $settings['button_position'], 'top-right' ); ?>><?php esc_html_e( 'Top Right', 'pax-support-pro' ); ?></option>
                        <option value="top-left" <?php selected( $settings['button_position'], 'top-left' ); ?>><?php esc_html_e( 'Top Left', 'pax-support-pro' ); ?></option>
                    </select>
                </div>

                <div class="pax-form-group">
                    <label><?php esc_html_e( 'Button Text', 'pax-support-pro' ); ?></label>
                    <input type="text" name="button_text" value="<?php echo esc_attr( $settings['button_text'] ); ?>" class="pax-input">
                </div>

                <div class="pax-form-group">
                    <label><?php esc_html_e( 'Welcome Message', 'pax-support-pro' ); ?></label>
                    <textarea name="welcome_message" rows="3" class="pax-input"><?php echo esc_textarea( $settings['welcome_message'] ); ?></textarea>
                </div>

                <hr>

                <!-- Advanced -->
                <h3><?php esc_html_e( 'Advanced Settings', 'pax-support-pro' ); ?></h3>

                <div class="pax-form-group">
                    <label><?php esc_html_e( 'Auto-close Inactive Sessions (minutes)', 'pax-support-pro' ); ?></label>
                    <input type="number" name="auto_close_minutes" value="<?php echo esc_attr( $settings['auto_close_minutes'] ); ?>" min="5" max="120" class="pax-input">
                </div>

                <div class="pax-form-group">
                    <label><?php esc_html_e( 'Timeout Before Auto-decline (seconds)', 'pax-support-pro' ); ?></label>
                    <input type="number" name="timeout_seconds" value="<?php echo esc_attr( $settings['timeout_seconds'] ); ?>" min="30" max="300" class="pax-input">
                </div>

                <div class="pax-form-group">
                    <label>
                        <input type="checkbox" name="cloudflare_mode" value="1" <?php checked( $settings['cloudflare_mode'] ); ?>>
                        <?php esc_html_e( 'Enable Cloudflare Compatibility Mode', 'pax-support-pro' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Adjust polling intervals for sites behind Cloudflare or similar proxies.', 'pax-support-pro' ); ?>
                    </p>
                </div>

                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( 'Save Live Agent Settings', 'pax-support-pro' ); ?>
                </button>
            </form>
        </div>
    </div>
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
        'allow_file_uploads' => isset( $_POST['allow_file_uploads'] ) && $_POST['allow_file_uploads'] === '1',
        'max_file_size_mb' => isset( $_POST['max_file_size_mb'] ) ? (int) $_POST['max_file_size_mb'] : 10,
        'sound_enabled' => isset( $_POST['sound_enabled'] ) && $_POST['sound_enabled'] === '1',
        'email_notifications' => isset( $_POST['email_notifications'] ) && $_POST['email_notifications'] === '1',
        'notification_email' => isset( $_POST['notification_email'] ) ? sanitize_email( $_POST['notification_email'] ) : get_option( 'admin_email' ),
        'button_position' => isset( $_POST['button_position'] ) ? sanitize_text_field( $_POST['button_position'] ) : 'bottom-right',
        'button_text' => isset( $_POST['button_text'] ) ? sanitize_text_field( $_POST['button_text'] ) : __( 'Live Agent', 'pax-support-pro' ),
        'welcome_message' => isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( $_POST['welcome_message'] ) : '',
        'auto_close_minutes' => isset( $_POST['auto_close_minutes'] ) ? (int) $_POST['auto_close_minutes'] : 30,
        'timeout_seconds' => isset( $_POST['timeout_seconds'] ) ? (int) $_POST['timeout_seconds'] : 60,
        'cloudflare_mode' => isset( $_POST['cloudflare_mode'] ) && $_POST['cloudflare_mode'] === '1',
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
