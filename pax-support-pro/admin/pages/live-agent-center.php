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
 * Render Live Agent Center page.
 */
function pax_sup_render_live_agent_center_page() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_pax_chats' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pax-support-pro' ) );
    }

    $options            = pax_sup_get_options();
    $enabled            = ! empty( $options['live_agent_enabled'] );
    $liveagent_settings = pax_sup_get_liveagent_settings();

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

    $commit_hash = function_exists( 'pax_sup_get_current_commit_hash' ) ? pax_sup_get_current_commit_hash() : 'n/a';
    $rest_base   = trailingslashit( rest_url( 'pax/v1' ) );
    $site_domain = wp_parse_url( get_site_url(), PHP_URL_HOST );
    $site_domain = $site_domain ? $site_domain : get_site_url();
    $server_name = isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : $site_domain;
    $server_ip   = $server_name ? @gethostbyname( $server_name ) : '';
    $server_ip   = $server_ip ? $server_ip : __( 'Unavailable', 'pax-support-pro' );
    $sound_flag  = ! empty( $liveagent_settings['sound_enabled'] ) ? '1' : '0';
    ?>
    <div class="wrap pax-liveagent-app" data-sound-enabled="<?php echo esc_attr( $sound_flag ); ?>">
        <header class="pax-liveagent-header">
            <div class="pax-header-title">
                <span class="dashicons dashicons-format-chat"></span>
                <div>
                    <h1><?php esc_html_e( 'Live Agent Center', 'pax-support-pro' ); ?></h1>
                    <p><?php esc_html_e( 'Track live requests, collaborate with visitors, and hand off seamlessly to human support.', 'pax-support-pro' ); ?></p>
                </div>
            </div>
            <div class="pax-header-diagnostics">
                <div class="pax-diagnostics-grid">
                    <div>
                        <span class="pax-diagnostic-label"><?php esc_html_e( 'REST Base', 'pax-support-pro' ); ?></span>
                        <code class="pax-diagnostic-value" id="pax-liveagent-rest"><?php echo esc_html( $rest_base ); ?></code>
                    </div>
                    <div>
                        <span class="pax-diagnostic-label"><?php esc_html_e( 'Domain', 'pax-support-pro' ); ?></span>
                        <span class="pax-diagnostic-value"><?php echo esc_html( $site_domain ); ?></span>
                    </div>
                    <div>
                        <span class="pax-diagnostic-label"><?php esc_html_e( 'Server IP', 'pax-support-pro' ); ?></span>
                        <span class="pax-diagnostic-value" id="pax-liveagent-server-ip"><?php echo esc_html( $server_ip ); ?></span>
                    </div>
                </div>
                <button type="button" class="button button-secondary pax-diagnostic-ping" id="pax-liveagent-ping">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php esc_html_e( 'Ping REST', 'pax-support-pro' ); ?>
                </button>
                <span class="pax-diagnostic-status" id="pax-liveagent-ping-status" aria-live="polite"></span>
            </div>
        </header>

        <section class="pax-liveagent-shell">
            <aside class="pax-liveagent-sidebar" aria-label="<?php esc_attr_e( 'Live chat sessions', 'pax-support-pro' ); ?>">
                <nav class="pax-session-tabs" role="tablist">
                    <button type="button" class="pax-tab-button active" data-tab="pending" aria-selected="true">
                        <span class="pax-tab-icon" aria-hidden="true">🕓</span>
                        <span><?php esc_html_e( 'Pending', 'pax-support-pro' ); ?></span>
                        <span class="pax-tab-count" data-counter="pending">0</span>
                    </button>
                    <button type="button" class="pax-tab-button" data-tab="active" aria-selected="false">
                        <span class="pax-tab-icon" aria-hidden="true">✅</span>
                        <span><?php esc_html_e( 'Active', 'pax-support-pro' ); ?></span>
                        <span class="pax-tab-count" data-counter="active">0</span>
                    </button>
                    <button type="button" class="pax-tab-button" data-tab="recent" aria-selected="false">
                        <span class="pax-tab-icon" aria-hidden="true">🗂</span>
                        <span><?php esc_html_e( 'Recent', 'pax-support-pro' ); ?></span>
                    </button>
                </nav>

                <div class="pax-session-scroll">
                    <div class="pax-session-list active" data-list="pending" role="tabpanel" aria-label="<?php esc_attr_e( 'Pending chat requests', 'pax-support-pro' ); ?>">
                        <div class="pax-session-empty" data-empty="pending">
                            <span class="dashicons dashicons-sos"></span>
                            <p><?php esc_html_e( 'No pending requests right now.', 'pax-support-pro' ); ?></p>
                        </div>
                    </div>
                    <div class="pax-session-list" data-list="active" role="tabpanel" aria-label="<?php esc_attr_e( 'Active chat sessions', 'pax-support-pro' ); ?>" hidden>
                        <div class="pax-session-empty" data-empty="active">
                            <span class="dashicons dashicons-smiley"></span>
                            <p><?php esc_html_e( 'You are all caught up. Great job!', 'pax-support-pro' ); ?></p>
                        </div>
                    </div>
                    <div class="pax-session-list" data-list="recent" role="tabpanel" aria-label="<?php esc_attr_e( 'Recently closed sessions', 'pax-support-pro' ); ?>" hidden>
                        <div class="pax-session-empty" data-empty="recent">
                            <span class="dashicons dashicons-archive"></span>
                            <p><?php esc_html_e( 'No recent sessions yet.', 'pax-support-pro' ); ?></p>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="pax-liveagent-workspace">
                <div class="pax-chat-placeholder" id="pax-liveagent-empty">
                    <div class="pax-placeholder-illustration" aria-hidden="true">
                        <span>💬</span>
                    </div>
                    <h2><?php esc_html_e( 'No chat selected', 'pax-support-pro' ); ?></h2>
                    <p><?php esc_html_e( 'Select a conversation on the left to review its messages.', 'pax-support-pro' ); ?></p>
                </div>

                <div class="pax-chat-panel" id="pax-liveagent-panel" hidden>
                    <div class="pax-chat-panel-header">
                        <div class="pax-chat-session-meta">
                            <div class="pax-session-avatar" id="pax-liveagent-avatar" aria-hidden="true"></div>
                            <div>
                                <h2 id="pax-liveagent-customer-name"></h2>
                                <p id="pax-liveagent-session-meta"></p>
                                <a href="#" id="pax-liveagent-page-url" class="pax-session-link" target="_blank" rel="noopener noreferrer"></a>
                            </div>
                        </div>
                        <div class="pax-chat-header-actions" id="pax-liveagent-actions">
                            <!-- Buttons populated via JS -->
                        </div>
                    </div>

                    <div class="pax-chat-session-tags" id="pax-liveagent-session-tags"></div>

                    <div class="pax-chat-scroll" id="pax-liveagent-messages" data-scroll>
                        <!-- Messages rendered here -->
                    </div>

                    <div class="pax-chat-typing" id="pax-liveagent-typing" hidden>
                        <span class="pax-typing-dot"></span>
                        <span class="pax-typing-dot"></span>
                        <span class="pax-typing-dot"></span>
                        <span class="pax-typing-label"><?php esc_html_e( 'Visitor is typing…', 'pax-support-pro' ); ?></span>
                    </div>

                    <div class="pax-chat-composer" id="pax-liveagent-composer">
                        <button type="button" class="pax-icon-button" id="pax-liveagent-attach" title="<?php esc_attr_e( 'Attach file', 'pax-support-pro' ); ?>">
                            <span class="dashicons dashicons-paperclip"></span>
                        </button>
                        <label class="screen-reader-text" for="pax-liveagent-file"><?php esc_html_e( 'Upload file', 'pax-support-pro' ); ?></label>
                        <input type="file" id="pax-liveagent-file" hidden>

                        <textarea
                            id="pax-liveagent-input"
                            rows="1"
                            placeholder="<?php esc_attr_e( 'Type a reply…', 'pax-support-pro' ); ?>"
                            autocomplete="off"
                        ></textarea>

                        <button type="button" class="button button-primary pax-send-button" id="pax-liveagent-send">
                            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                            <span class="pax-send-label"><?php esc_html_e( 'Send', 'pax-support-pro' ); ?></span>
                        </button>
                    </div>
                </div>
            </main>
        </section>

        <footer class="pax-liveagent-footer">
            <?php
            printf(
                esc_html__( 'Live Agent Center v%s • build: %s', 'pax-support-pro' ),
                esc_html( PAX_SUP_VER ),
                esc_html( $commit_hash )
            );
            ?>
        </footer>

        <audio id="pax-liveagent-chime" preload="auto">
            <source src="<?php echo esc_url( PAX_SUP_URL . 'assets/notification.mp3' ); ?>" type="audio/mpeg">
        </audio>
    </div>
    <?php
}
