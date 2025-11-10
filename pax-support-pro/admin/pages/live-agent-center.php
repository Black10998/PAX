<?php
/**
 * Live Agent Center Admin Page
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Live Agent Center page
 */
function pax_sup_render_live_agent_center_page() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_pax_chats' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pax-support-pro' ) );
    }

    $options = pax_sup_get_options();
    $settings = pax_sup_get_liveagent_settings();
    $enabled = ! empty( $options['live_agent_enabled'] );

    if ( ! $enabled ) {
        ?>
        <div class="wrap pax-modern-page">
            <div class="pax-liveagent-disabled">
                <span class="dashicons dashicons-warning"></span>
                <h2><?php esc_html_e( 'Live Agent System is Disabled', 'pax-support-pro' ); ?></h2>
                <p><?php esc_html_e( 'Please enable the Live Agent system in Settings to use this feature.', 'pax-support-pro' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-settings' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Go to Settings', 'pax-support-pro' ); ?>
                </a>
            </div>
        </div>
        <?php
        return;
    }

    // Get current agent ID
    $agent_id = get_current_user_id();

    // Get sessions
    $pending_sessions = pax_sup_get_liveagent_sessions_by_status( 'pending' );
    $active_sessions = pax_sup_get_liveagent_sessions_by_status( 'active' );

    // Get selected session from query param
    $selected_session_id = isset( $_GET['session'] ) ? (int) $_GET['session'] : null;
    $selected_session = null;
    $last_message_id = null;

    if ( $selected_session_id ) {
        $selected_session = pax_sup_get_liveagent_session( $selected_session_id );
    } elseif ( ! empty( $active_sessions ) ) {
        $selected_session = $active_sessions[0];
    } elseif ( ! empty( $pending_sessions ) ) {
        $selected_session = $pending_sessions[0];
    }

    if ( $selected_session && ! empty( $selected_session['messages'] ) && is_array( $selected_session['messages'] ) ) {
        $last_message = end( $selected_session['messages'] );
        if ( isset( $last_message['id'] ) ) {
            $last_message_id = $last_message['id'];
        }
        reset( $selected_session['messages'] );
    }

    ?>
    <div class="wrap pax-liveagent-center pax-unified-admin">
        <div class="pax-liveagent-container">
            <!-- Sidebar -->
            <div class="pax-liveagent-sidebar">
                <div class="pax-liveagent-sidebar-header">
                    <h2>
                        <span class="dashicons dashicons-format-chat"></span>
                        <?php esc_html_e( 'Live Agent Center', 'pax-support-pro' ); ?>
                    </h2>
                    <div class="pax-header-actions">
                        <button class="pax-refresh-sessions" title="<?php esc_attr_e( 'Refresh', 'pax-support-pro' ); ?>">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    </div>
                </div>

                <!-- Pending Sessions -->
                <div class="pax-session-group">
                    <div class="pax-session-group-header">
                        <span class="dashicons dashicons-clock"></span>
                        <?php esc_html_e( 'Pending', 'pax-support-pro' ); ?>
                        <span class="pax-session-count"><?php echo count( $pending_sessions ); ?></span>
                    </div>
                    <div class="pax-session-list" id="pax-pending-sessions">
                        <?php if ( empty( $pending_sessions ) ) : ?>
                            <div class="pax-no-sessions">
                                <?php esc_html_e( 'No pending requests', 'pax-support-pro' ); ?>
                            </div>
                        <?php else : ?>
                            <?php foreach ( $pending_sessions as $session ) : ?>
                                <?php pax_sup_render_session_item( $session, $selected_session ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Active Sessions -->
                <div class="pax-session-group">
                    <div class="pax-session-group-header">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Active', 'pax-support-pro' ); ?>
                        <span class="pax-session-count"><?php echo count( $active_sessions ); ?></span>
                    </div>
                    <div class="pax-session-list" id="pax-active-sessions">
                        <?php if ( empty( $active_sessions ) ) : ?>
                            <div class="pax-no-sessions">
                                <?php esc_html_e( 'No active chats', 'pax-support-pro' ); ?>
                            </div>
                        <?php else : ?>
                            <?php foreach ( $active_sessions as $session ) : ?>
                                <?php pax_sup_render_session_item( $session, $selected_session ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="pax-liveagent-main">
                <?php if ( $selected_session ) : ?>
                    <?php pax_sup_render_chat_area( $selected_session, $agent_id ); ?>
                <?php else : ?>
                    <div class="pax-no-session-selected">
                        <span class="dashicons dashicons-format-chat"></span>
                        <h3><?php esc_html_e( 'No Chat Selected', 'pax-support-pro' ); ?></h3>
                        <p><?php esc_html_e( 'Select a chat from the sidebar to start.', 'pax-support-pro' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sound notification -->
        <audio id="pax-notification-sound" preload="auto">
            <source src="<?php echo esc_url( PAX_SUP_URL . 'assets/notification.mp3' ); ?>" type="audio/mpeg">
        </audio>
    </div>

    <script>
    // Pass data to JavaScript
    window.paxLiveAgent = {
        ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
        restUrl: '<?php echo esc_js( rest_url( 'pax/v1' ) ); ?>',
        nonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
        agentId: <?php echo (int) $agent_id; ?>,
        selectedSessionId: <?php echo $selected_session ? (int) $selected_session['id'] : 'null'; ?>,
        refreshInterval: <?php echo max( 5, (int) $settings['poll_interval'] ) * 1000; ?>,
        soundEnabled: <?php echo ! empty( $settings['sound_enabled'] ) ? 'true' : 'false'; ?>,
        lastMessageId: <?php echo $last_message_id ? "'" . esc_js( $last_message_id ) . "'" : 'null'; ?>,
        strings: {
            newMessage: '<?php echo esc_js( __( 'New message received', 'pax-support-pro' ) ); ?>',
            newRequest: '<?php echo esc_js( __( 'New chat request', 'pax-support-pro' ) ); ?>',
            sessionClosed: '<?php echo esc_js( __( 'Chat session closed', 'pax-support-pro' ) ); ?>',
            confirmClose: '<?php echo esc_js( __( 'Are you sure you want to close this chat?', 'pax-support-pro' ) ); ?>',
            confirmDecline: '<?php echo esc_js( __( 'Are you sure you want to decline this request?', 'pax-support-pro' ) ); ?>',
            attachment: '<?php echo esc_js( __( 'Attachment', 'pax-support-pro' ) ); ?>',
        }
    };
    </script>
    <?php
}

/**
 * Render session item in sidebar
 */
function pax_sup_render_session_item( $session, $selected_session ) {
    $is_selected = $selected_session && $selected_session['id'] === $session['id'];
    $unread_count = 0;
    $last_message_preview = '';
    $last_message_id = '';

    // Count unread messages
    if ( ! empty( $session['messages'] ) && is_array( $session['messages'] ) ) {
        foreach ( $session['messages'] as $message ) {
            if ( $message['sender'] === 'user' && ! $message['read'] ) {
                $unread_count++;
            }
        }

        $last_message = end( $session['messages'] );
        if ( $last_message ) {
            $last_message_preview = isset( $last_message['message'] ) ? $last_message['message'] : ( $last_message['text'] ?? '' );
            if ( isset( $last_message['id'] ) ) {
                $last_message_id = $last_message['id'];
            }
        }
        reset( $session['messages'] );
    }

    $time_ago = human_time_diff( strtotime( $session['last_activity'] ), current_time( 'timestamp' ) );
    ?>
    <div class="pax-session-item <?php echo $is_selected ? 'active' : ''; ?>" 
         data-session-id="<?php echo esc_attr( $session['id'] ); ?>"
         data-last-message-id="<?php echo esc_attr( $last_message_id ); ?>">
        <div class="pax-session-avatar">
            <?php echo get_avatar( $session['user_id'], 40 ); ?>
            <span class="pax-session-status <?php echo esc_attr( $session['status'] ); ?>"></span>
        </div>
        <div class="pax-session-info">
            <div class="pax-session-name">
                <?php echo esc_html( $session['user_name'] ); ?>
            </div>
            <div class="pax-session-preview">
                <?php
                    if ( $last_message_preview ) {
                        echo esc_html( wp_trim_words( $last_message_preview, 8 ) );
                    } else {
                    esc_html_e( 'New chat request', 'pax-support-pro' );
                }
                ?>
            </div>
        </div>
        <div class="pax-session-meta">
            <div class="pax-session-time"><?php echo esc_html( $time_ago ); ?></div>
            <?php if ( $unread_count > 0 ) : ?>
                <span class="pax-unread-badge"><?php echo esc_html( $unread_count ); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render main chat area
 */
function pax_sup_render_chat_area( $session, $agent_id ) {
    $user = get_userdata( $session['user_id'] );
    $is_pending = $session['status'] === 'pending';
    $is_active = $session['status'] === 'active';
    $is_closed = $session['status'] === 'closed';
    ?>
    <div class="pax-chat-container" data-session-id="<?php echo esc_attr( $session['id'] ); ?>">
        <!-- Chat Header -->
        <div class="pax-chat-header">
            <div class="pax-chat-user-info">
                <div class="pax-chat-avatar">
                    <?php echo get_avatar( $session['user_id'], 48 ); ?>
                    <?php if ( $is_active ) : ?>
                        <span class="pax-online-badge"></span>
                    <?php endif; ?>
                </div>
                <div class="pax-chat-user-details">
                    <h3><?php echo esc_html( $session['user_name'] ); ?></h3>
                    <div class="pax-chat-user-meta">
                        <?php echo esc_html( $session['user_email'] ); ?>
                        <span class="separator">•</span>
                        <span class="pax-session-status-text">
                            <?php
                            if ( $is_pending ) {
                                esc_html_e( 'Waiting for response', 'pax-support-pro' );
                            } elseif ( $is_active ) {
                                esc_html_e( 'Active', 'pax-support-pro' );
                            } else {
                                esc_html_e( 'Closed', 'pax-support-pro' );
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="pax-chat-actions">
                <?php if ( $is_pending ) : ?>
                    <button class="button button-primary pax-accept-chat" data-session-id="<?php echo esc_attr( $session['id'] ); ?>">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e( 'Accept', 'pax-support-pro' ); ?>
                    </button>
                    <button class="button pax-decline-chat" data-session-id="<?php echo esc_attr( $session['id'] ); ?>">
                        <span class="dashicons dashicons-no"></span>
                        <?php esc_html_e( 'Decline', 'pax-support-pro' ); ?>
                    </button>
                <?php elseif ( $is_active ) : ?>
                    <button class="button pax-convert-ticket" data-session-id="<?php echo esc_attr( $session['id'] ); ?>" title="<?php esc_attr_e( 'Convert to Ticket', 'pax-support-pro' ); ?>">
                        <span class="dashicons dashicons-tickets-alt"></span>
                    </button>
                    <button class="button pax-export-chat" data-session-id="<?php echo esc_attr( $session['id'] ); ?>" title="<?php esc_attr_e( 'Export Chat', 'pax-support-pro' ); ?>">
                        <span class="dashicons dashicons-download"></span>
                    </button>
                    <button class="button button-secondary pax-close-chat" data-session-id="<?php echo esc_attr( $session['id'] ); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                        <?php esc_html_e( 'End Chat', 'pax-support-pro' ); ?>
                    </button>
                <?php else : ?>
                    <button class="button pax-export-chat" data-session-id="<?php echo esc_attr( $session['id'] ); ?>">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Export', 'pax-support-pro' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="pax-chat-messages" id="pax-chat-messages">
            <?php if ( ! empty( $session['messages'] ) && is_array( $session['messages'] ) ) : ?>
                <?php foreach ( $session['messages'] as $message ) : ?>
                    <?php pax_sup_render_message_bubble( $message, $session, $agent_id ); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="pax-chat-empty">
                    <span class="dashicons dashicons-format-chat"></span>
                    <p><?php esc_html_e( 'No messages yet', 'pax-support-pro' ); ?></p>
                </div>
            <?php endif; ?>
            <div class="pax-typing-indicator" style="display: none;">
                <div class="pax-typing-dot"></div>
                <div class="pax-typing-dot"></div>
                <div class="pax-typing-dot"></div>
            </div>
        </div>

        <!-- Input Area -->
        <?php if ( $is_active ) : ?>
            <div class="pax-chat-input-area">
                <form class="pax-chat-form" id="pax-chat-form">
                    <input type="hidden" name="session_id" value="<?php echo esc_attr( $session['id'] ); ?>">
                    <div class="pax-input-wrapper">
                        <button type="button" class="pax-emoji-button" title="<?php esc_attr_e( 'Emoji', 'pax-support-pro' ); ?>">
                            <span class="dashicons dashicons-smiley"></span>
                        </button>
                        <textarea 
                            name="message" 
                            id="pax-message-input" 
                            placeholder="<?php esc_attr_e( 'Type your message...', 'pax-support-pro' ); ?>"
                            rows="1"></textarea>
                        <button type="button" class="pax-attach-button" title="<?php esc_attr_e( 'Attach file', 'pax-support-pro' ); ?>">
                            <span class="dashicons dashicons-paperclip"></span>
                        </button>
                        <input type="file" id="pax-file-input" style="display: none;" accept="image/*,.pdf,.doc,.docx">
                    </div>
                    <button type="submit" class="pax-send-button">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </form>
            </div>
        <?php elseif ( $is_pending ) : ?>
            <div class="pax-chat-pending-notice">
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e( 'Accept this chat request to start messaging', 'pax-support-pro' ); ?>
            </div>
        <?php else : ?>
            <div class="pax-chat-closed-notice">
                <span class="dashicons dashicons-lock"></span>
                <?php esc_html_e( 'This chat session has been closed', 'pax-support-pro' ); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render message bubble
 */
function pax_sup_render_message_bubble( $message, $session, $agent_id ) {
    $is_agent = $message['sender'] === 'agent';
    $body = isset( $message['message'] ) ? $message['message'] : ( $message['text'] ?? '' );
    $time = ! empty( $message['timestamp'] ) ? date( 'g:i A', strtotime( $message['timestamp'] ) ) : '';
    $attachment = isset( $message['attachment'] ) ? $message['attachment'] : null;
    $message_id = isset( $message['id'] ) ? $message['id'] : '';
    ?>
    <div class="pax-message <?php echo $is_agent ? 'pax-message-agent' : 'pax-message-user'; ?>" data-message-id="<?php echo esc_attr( $message_id ); ?>">
        <div class="pax-message-bubble">
            <div class="pax-message-content">
                <?php echo wp_kses_post( nl2br( esc_html( $body ) ) ); ?>
                <?php if ( $attachment && ! empty( $attachment['url'] ) ) : ?>
                    <div class="pax-message-attachment">
                        <span class="dashicons dashicons-paperclip" aria-hidden="true"></span>
                        <a href="<?php echo esc_url( $attachment['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html( $attachment['filename'] ?? __( 'Attachment', 'pax-support-pro' ) ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="pax-message-meta">
                <span class="pax-message-time"><?php echo esc_html( $time ); ?></span>
                <?php if ( $is_agent && ! empty( $message['read'] ) ) : ?>
                    <span class="pax-message-read">
                        <span class="dashicons dashicons-yes"></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
