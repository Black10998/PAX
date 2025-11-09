<?php
/**
 * Real-Time Notifications System
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Send email notification for new ticket
 */
function pax_sup_notify_new_ticket( $ticket_id, $ticket_data ) {
    if ( ! get_option( 'pax_enable_email_alerts', false ) ) {
        return;
    }

    $admin_email = get_option( 'pax_notification_email', get_option( 'admin_email' ) );
    
    if ( empty( $admin_email ) || ! is_email( $admin_email ) ) {
        return;
    }

    $subject = sprintf(
        /* translators: %s: ticket ID */
        __( '[PAX Support] New Ticket #%s', 'pax-support-pro' ),
        $ticket_id
    );

    $message = sprintf(
        __( 'A new support ticket has been created.', 'pax-support-pro' ) . "\n\n" .
        __( 'Ticket ID: %s', 'pax-support-pro' ) . "\n" .
        __( 'Subject: %s', 'pax-support-pro' ) . "\n" .
        __( 'From: %s', 'pax-support-pro' ) . "\n" .
        __( 'Email: %s', 'pax-support-pro' ) . "\n\n" .
        __( 'View ticket: %s', 'pax-support-pro' ),
        $ticket_id,
        $ticket_data['subject'] ?? __( 'No subject', 'pax-support-pro' ),
        $ticket_data['name'] ?? __( 'Unknown', 'pax-support-pro' ),
        $ticket_data['email'] ?? __( 'No email', 'pax-support-pro' ),
        admin_url( 'admin.php?page=pax-support-tickets' )
    );

    wp_mail( $admin_email, $subject, $message );
}

/**
 * Send email notification for new chat message
 */
function pax_sup_notify_new_chat_message( $chat_id, $message_data ) {
    if ( ! get_option( 'pax_enable_email_alerts', false ) ) {
        return;
    }

    $admin_email = get_option( 'pax_notification_email', get_option( 'admin_email' ) );
    
    if ( empty( $admin_email ) || ! is_email( $admin_email ) ) {
        return;
    }

    $subject = sprintf(
        /* translators: %s: chat ID */
        __( '[PAX Support] New Chat Message #%s', 'pax-support-pro' ),
        $chat_id
    );

    $message = sprintf(
        __( 'A new chat message has been received.', 'pax-support-pro' ) . "\n\n" .
        __( 'Chat ID: %s', 'pax-support-pro' ) . "\n" .
        __( 'From: %s', 'pax-support-pro' ) . "\n" .
        __( 'Message: %s', 'pax-support-pro' ) . "\n\n" .
        __( 'View console: %s', 'pax-support-pro' ),
        $chat_id,
        $message_data['sender'] ?? __( 'Unknown', 'pax-support-pro' ),
        wp_trim_words( $message_data['message'] ?? '', 20 ),
        admin_url( 'admin.php?page=pax-support-console' )
    );

    wp_mail( $admin_email, $subject, $message );
}

/**
 * Send email notification for new callback request
 */
function pax_sup_notify_new_callback( $callback_id, $callback_data ) {
    if ( ! get_option( 'pax_enable_email_alerts', false ) ) {
        return;
    }

    $admin_email = get_option( 'pax_notification_email', get_option( 'admin_email' ) );
    
    if ( empty( $admin_email ) || ! is_email( $admin_email ) ) {
        return;
    }

    $subject = sprintf(
        /* translators: %s: callback ID */
        __( '[PAX Support] New Callback Request #%s', 'pax-support-pro' ),
        $callback_id
    );

    $message = sprintf(
        __( 'A new callback request has been received.', 'pax-support-pro' ) . "\n\n" .
        __( 'Callback ID: %s', 'pax-support-pro' ) . "\n" .
        __( 'Name: %s', 'pax-support-pro' ) . "\n" .
        __( 'Phone: %s', 'pax-support-pro' ) . "\n" .
        __( 'Preferred Time: %s', 'pax-support-pro' ) . "\n\n" .
        __( 'View scheduler: %s', 'pax-support-pro' ),
        $callback_id,
        $callback_data['name'] ?? __( 'Unknown', 'pax-support-pro' ),
        $callback_data['phone'] ?? __( 'No phone', 'pax-support-pro' ),
        $callback_data['preferred_time'] ?? __( 'Not specified', 'pax-support-pro' ),
        admin_url( 'admin.php?page=pax-support-scheduler' )
    );

    wp_mail( $admin_email, $subject, $message );
}

/**
 * Render notification settings section
 */
function pax_sup_render_notification_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle form submission
    if ( isset( $_POST['pax_notification_nonce'] ) && wp_verify_nonce( $_POST['pax_notification_nonce'], 'pax_notification_save' ) ) {
        $notification_email = isset( $_POST['pax_notification_email'] ) ? sanitize_email( $_POST['pax_notification_email'] ) : '';
        $enable_email_alerts = isset( $_POST['pax_enable_email_alerts'] ) && $_POST['pax_enable_email_alerts'] === '1';
        $enable_realtime = isset( $_POST['pax_enable_realtime_notifications'] ) && $_POST['pax_enable_realtime_notifications'] === '1';

        if ( ! empty( $notification_email ) && ! is_email( $notification_email ) ) {
            add_settings_error(
                'pax_notifications',
                'invalid_email',
                __( 'Please enter a valid email address.', 'pax-support-pro' ),
                'error'
            );
        } else {
            update_option( 'pax_notification_email', $notification_email );
            update_option( 'pax_enable_email_alerts', $enable_email_alerts );
            update_option( 'pax_enable_realtime_notifications', $enable_realtime );

            add_settings_error(
                'pax_notifications',
                'settings_updated',
                __( 'Notification settings updated successfully.', 'pax-support-pro' ),
                'success'
            );
        }
    }

    $notification_email = get_option( 'pax_notification_email', get_option( 'admin_email' ) );
    $enable_email_alerts = get_option( 'pax_enable_email_alerts', false );
    $enable_realtime = get_option( 'pax_enable_realtime_notifications', false );
    ?>
    <div class="pax-card">
        <div class="pax-card-header">
            <h2>
                <span class="dashicons dashicons-email-alt"></span>
                <?php esc_html_e( 'Email Notification Settings', 'pax-support-pro' ); ?>
            </h2>
        </div>
        <div class="pax-card-body">
            <form method="post" action="">
                <?php wp_nonce_field( 'pax_notification_save', 'pax_notification_nonce' ); ?>

                <div class="pax-form-group">
                    <label for="pax_notification_email">
                        <?php esc_html_e( 'Admin Notification Email', 'pax-support-pro' ); ?>
                    </label>
                    <input type="email" 
                           name="pax_notification_email" 
                           id="pax_notification_email" 
                           value="<?php echo esc_attr( $notification_email ); ?>" 
                           class="pax-input"
                           placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                    <p class="description">
                        <?php esc_html_e( 'Email address to receive notifications for new tickets, chats, and callback requests.', 'pax-support-pro' ); ?>
                    </p>
                </div>

                <div class="pax-form-group">
                    <label>
                        <input type="checkbox" 
                               name="pax_enable_email_alerts" 
                               value="1" 
                               <?php checked( $enable_email_alerts ); ?>>
                        <?php esc_html_e( 'Enable Email Alerts', 'pax-support-pro' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Send email notifications for new tickets, chat messages, and callback requests.', 'pax-support-pro' ); ?>
                    </p>
                </div>

                <div class="pax-form-group">
                    <label>
                        <input type="checkbox" 
                               name="pax_enable_realtime_notifications" 
                               value="1" 
                               <?php checked( $enable_realtime ); ?>>
                        <?php esc_html_e( 'Enable Real-Time Notifications', 'pax-support-pro' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Show toast notifications in the admin panel for new support events (requires page to be open).', 'pax-support-pro' ); ?>
                    </p>
                </div>

                <div class="pax-notification-events">
                    <h4><?php esc_html_e( 'Notification Events', 'pax-support-pro' ); ?></h4>
                    <ul>
                        <li>
                            <span class="dashicons dashicons-tickets-alt"></span>
                            <?php esc_html_e( 'New ticket created', 'pax-support-pro' ); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-format-chat"></span>
                            <?php esc_html_e( 'New chat message received', 'pax-support-pro' ); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-phone"></span>
                            <?php esc_html_e( 'New callback request submitted', 'pax-support-pro' ); ?>
                        </li>
                    </ul>
                </div>

                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( 'Save Notification Settings', 'pax-support-pro' ); ?>
                </button>
            </form>
        </div>
    </div>

    <style>
    .pax-notification-events {
        margin-top: 20px;
        padding: 16px;
        background: #f9f9f9;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .pax-notification-events h4 {
        margin-top: 0;
        margin-bottom: 12px;
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }

    .pax-notification-events ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .pax-notification-events li {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 0;
        font-size: 13px;
        color: #666;
    }

    .pax-notification-events .dashicons {
        color: var(--pax-primary, #e53935);
    }
    </style>
    <?php
}
