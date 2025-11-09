<?php
/**
 * Front-end chat output and assets.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_register_shortcode() {
    add_shortcode(
        'pax_support',
        function () {
            return '<div id="pax-support-root" style="display:contents"></div>';
        }
    );
}
add_action( 'init', 'pax_sup_register_shortcode' );

function pax_sup_enqueue_public_assets() {
    if ( is_admin() ) {
        return;
    }

    $options = pax_sup_get_options();

    if ( empty( $options['enabled'] ) || empty( $options['enable_chat'] ) ) {
        return;
    }

    wp_enqueue_style( 'dashicons' );
    
    // v5.7.8: Register inline CSS variables first (loads before other stylesheets)
    wp_register_style( 'pax-css-variables', false );
    wp_enqueue_style( 'pax-css-variables' );
    
    // v5.1.4: Add Google Fonts for modern design (Orbitron + Tajawal)
    wp_enqueue_style( 'pax-google-fonts', 'https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap', array(), null );
    
    // v5.1.4: Add unified Live Chat styles (depends on CSS variables)
    wp_enqueue_style( 'pax-livechat-unified', PAX_SUP_URL . 'public/css/livechat-unified.css', array( 'pax-css-variables', 'pax-google-fonts' ), PAX_SUP_VER );
    
    wp_enqueue_style( 'pax-support-pro', PAX_SUP_URL . 'public/assets.css', array( 'dashicons', 'pax-google-fonts', 'pax-livechat-unified' ), PAX_SUP_VER );
    wp_enqueue_script( 'pax-support-pro', PAX_SUP_URL . 'public/assets.js', array(), PAX_SUP_VER, true );
    
    // v5.4.2: Add unified chat engine
    wp_enqueue_script( 'pax-unified-chat', PAX_SUP_URL . 'assets/js/pax-unified-chat.js', array( 'pax-support-pro' ), PAX_SUP_VER, true );

    $position = $options['launcher_position'];
    $reaction_color = ! empty( $options['reaction_btn_color'] ) ? $options['reaction_btn_color'] : '#e53935';
    $reaction_rgb = sscanf( $reaction_color, '#%02x%02x%02x' );
    
    $css      = ':root{' .
        '--pax-bg:' . esc_html( $options['color_bg'] ) . ';' .
        '--pax-panel:' . esc_html( $options['color_panel'] ) . ';' .
        '--pax-border:' . esc_html( $options['color_border'] ) . ';' .
        '--pax-text:' . esc_html( $options['color_text'] ) . ';' .
        '--pax-sub:' . esc_html( $options['color_sub'] ) . ';' .
        '--pax-accent:' . esc_html( $options['color_accent'] ) . ';' .
        '--pax-reaction-bg:rgba(' . implode( ',', $reaction_rgb ) . ',0.9);' .
        '--pax-reaction-bg-hover:' . esc_html( $reaction_color ) . ';' .
        '--pax-reaction-border:rgba(' . implode( ',', $reaction_rgb ) . ',0.5);' .
        '--pax-reaction-border-hover:rgba(' . implode( ',', $reaction_rgb ) . ',0.7);';

    switch ( $position ) {
        case 'bottom-left':
            $css .= '--pax-launcher-left:16px;--pax-launcher-right:auto;--pax-launcher-top:auto;--pax-launcher-bottom:16px;';
            $css .= '--pax-chat-left:14px;--pax-chat-right:auto;--pax-chat-top:auto;--pax-chat-bottom:90px;';
            break;
        case 'top-left':
            $css .= '--pax-launcher-left:16px;--pax-launcher-right:auto;--pax-launcher-top:16px;--pax-launcher-bottom:auto;';
            $css .= '--pax-chat-left:14px;--pax-chat-right:auto;--pax-chat-top:90px;--pax-chat-bottom:auto;';
            break;
        case 'top-right':
            $css .= '--pax-launcher-left:auto;--pax-launcher-right:16px;--pax-launcher-top:16px;--pax-launcher-bottom:auto;';
            $css .= '--pax-chat-left:auto;--pax-chat-right:14px;--pax-chat-top:90px;--pax-chat-bottom:auto;';
            break;
        default:
            $css .= '--pax-launcher-left:auto;--pax-launcher-right:16px;--pax-launcher-top:auto;--pax-launcher-bottom:16px;';
            $css .= '--pax-chat-left:auto;--pax-chat-right:14px;--pax-chat-top:auto;--pax-chat-bottom:90px;';
            break;
    }

    $css .= '}';

    wp_add_inline_style( 'pax-css-variables', $css );

    $current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
    $login_url    = wp_login_url( home_url( $current_path ?: '/' ) );

    $current_user = wp_get_current_user();
    $scheduler_settings = pax_sup_get_scheduler_settings();

    $menu_items = isset( $options['chat_menu_items'] ) && is_array( $options['chat_menu_items'] )
        ? $options['chat_menu_items']
        : pax_sup_default_menu_items();

    // v5.1.4: Hide Live Chat menu item entirely (now integrated into main chat)
    if ( isset( $menu_items['livechat'] ) ) {
        $menu_items['livechat']['visible'] = 0;
    }

    $menu_icons_map = array(
        'chat'           => 'dashicons-format-chat',
        'ticket'         => 'dashicons-tickets-alt',
        'help'           => 'dashicons-editor-help',
        'livechat'       => 'dashicons-businessman',
        'whatsnew'       => 'dashicons-megaphone',
        'troubleshooter' => 'dashicons-admin-tools',
        'diag'           => 'dashicons-chart-line',
        'callback'       => 'dashicons-phone',
        'order'          => 'dashicons-cart',
        'myreq'          => 'dashicons-list-view',
        'feedback'       => 'dashicons-star-filled',
        'donate'         => 'dashicons-heart',
    );

    $localize = array(
        'options' => array(
            'toggle_on_click'      => ! empty( $options['toggle_on_click'] ),
            'enable_offline_guard' => ! empty( $options['enable_offline_guard'] ),
            'chat_access_control'  => $options['chat_access_control'] ?? 'everyone',
            'disable_chat_menu'    => ! empty( $options['disable_chat_menu'] ),
            'welcome_message'      => $options['welcome_message'] ?? '',
            'enable_reply_to'      => ! empty( $options['enable_reply_to'] ),
            'enable_quick_actions' => ! empty( $options['enable_quick_actions'] ),
            // v5.3.0: Removed enable_customization (feature removed)
            // v5.1.4: Live Agent enabled flag
            'live_agent_enabled'   => ! empty( $options['live_agent_enabled'] ),
            // v5.7.8: Pass color settings to JavaScript
            'color_accent'         => $options['color_accent'] ?? '#e53935',
            'color_bg'             => $options['color_bg'] ?? '#ffffff',
            'color_panel'          => $options['color_panel'] ?? '#f5f5f5',
            'color_border'         => $options['color_border'] ?? '#e0e0e0',
            'color_text'           => $options['color_text'] ?? '#212121',
            'color_sub'            => $options['color_sub'] ?? '#757575',
            // Chat Reactions
            'chat_reactions'       => array(
                'enable_copy'    => ! empty( $options['chat_reactions_enable_copy'] ?? 1 ),
                'enable_like'    => ! empty( $options['chat_reactions_enable_like'] ?? 1 ),
                'enable_dislike' => ! empty( $options['chat_reactions_enable_dislike'] ?? 1 ),
            ),
            // Chat Customization
            'chat_welcome_text'    => $options['chat_welcome_text'] ?? '',
            // Chat Animations
            'chat_animations'      => array(
                'enabled'  => ! empty( $options['chat_animations_enabled'] ?? 1 ),
                'duration' => (int) ( $options['chat_animation_duration'] ?? 300 ),
                'easing'   => $options['chat_animation_easing'] ?? 'ease',
            ),
            // Welcome Message Settings
            'welcome_settings'     => array(
                'placement'  => $options['welcome_placement'] ?? 'banner',
                'alignment'  => $options['welcome_alignment'] ?? 'left',
                'style'      => $options['welcome_style'] ?? 'subtle',
                'display_rule' => $options['welcome_display_rule'] ?? 'always',
                'max_lines'  => (int) ( $options['welcome_max_lines'] ?? 3 ),
                'show_icon'  => ! empty( $options['welcome_show_icon'] ?? 1 ),
                'animation'  => $options['welcome_animation'] ?? 'fade',
                'animation_duration' => (int) ( $options['welcome_animation_duration'] ?? 300 ),
            ),
        ),
        'menuItems' => $menu_items,
        'menuIcons' => $menu_icons_map,
        'customMenus' => isset( $options['pax_chat_custom_menus'] ) && is_array( $options['pax_chat_custom_menus'] )
            ? array_values( array_filter( $options['pax_chat_custom_menus'], function( $menu ) {
                return ! empty( $menu['enabled'] ) && ! empty( $menu['name'] ) && ! empty( $menu['url'] );
            } ) )
            : array(),
        'rest'    => array(
            'chat'     => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/chat' ) ),
            'ai'       => esc_url_raw( rest_url( 'pax-support/v1/ai-chat' ) ),
            'cooldown' => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/ticket-cooldown' ) ),
            'agent'    => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/live-agent' ) ),
            'callback' => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/callback' ) ),
            'schedule' => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/schedule' ) ),
            'scheduleBase' => esc_url_raw( trailingslashit( rest_url( PAX_SUP_REST_NS . '/schedule' ) ) ),
            'ticket'   => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/ticket' ) ),
            'tickets'  => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/tickets' ) ),
            'help'     => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/help-center' ) ),
            'knowledge'=> esc_url_raw( rest_url( PAX_SUP_REST_NS . '/help-center' ) ),
            'trouble'  => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/troubleshooter' ) ),
            'diagnostics' => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/diagnostics' ) ),
            'order'    => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/order-lookup' ) ),
            'my_request' => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/my-request' ) ),
            'feedback' => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/feedback' ) ),
            'donate'   => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/donate' ) ),
            'reactions' => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/reactions' ) ),
            // v5.1.4: Live Agent REST endpoints
            'liveagent' => array(
                'create'  => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/liveagent/session/create' ) ),
                'poll'    => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/liveagent/status/poll' ) ),
                'send'    => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/liveagent/message/send' ) ),
                'close'   => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/liveagent/session/close' ) ),
                'status'  => esc_url_raw( rest_url( PAX_SUP_REST_NS . '/liveagent/status/agent-online' ) ),
            ),
        ),
        'links'   => array(
            'help'      => esc_url( $options['help_center_url'] ),
            'whatsNew'  => esc_url( $options['whats_new_url'] ?? '' ),
            'donate'    => esc_url( $options['donate_url'] ?? 'https://www.paypal.me/AhmadAlkhalaf29' ),
            'feedback'  => esc_url( admin_url( 'admin.php?page=pax-support-feedback' ) ),
        ),
        'strings' => array(
            'welcome' => ! empty( $options['welcome_message'] ) ? $options['welcome_message'] : __( '👋 Welcome! How can I help you today?', 'pax-support-pro' ),
            'loginRequired' => __( 'Please log in to use the chat system.', 'pax-support-pro' ),
            'comingSoon' => __( 'Coming soon!', 'pax-support-pro' ),
            'chatDisabled' => $options['chat_disabled_message'] ?? 'Chat is currently disabled. Please try again later.',
            'copy' => __( 'Copy', 'pax-support-pro' ),
            'like' => __( 'Like', 'pax-support-pro' ),
            'dislike' => __( 'Dislike', 'pax-support-pro' ),
            'copiedToClipboard' => __( 'Copied to clipboard', 'pax-support-pro' ),
            'liked' => __( 'Liked!', 'pax-support-pro' ),
            'feedbackNoted' => __( 'Feedback noted', 'pax-support-pro' ),
            // v5.1.4: Live Agent strings
            'liveagent' => array(
                'welcomeTitle' => __( '👋 Welcome to Support', 'pax-support-pro' ),
                'welcomeSubtitle' => __( 'How can we help you today?', 'pax-support-pro' ),
                'chatWithAssistant' => __( '💬 Chat with Assistant', 'pax-support-pro' ),
                'connectLiveAgent' => __( '🎧 Connect with Live Agent', 'pax-support-pro' ),
                'agentOnline' => __( 'Agent Online', 'pax-support-pro' ),
                'agentOffline' => __( 'Agent Offline', 'pax-support-pro' ),
                'connecting' => __( 'Connecting to agent...', 'pax-support-pro' ),
                'connected' => __( 'Connected to agent', 'pax-support-pro' ),
                'agentTyping' => __( 'Agent is typing...', 'pax-support-pro' ),
                'sessionEnded' => __( 'Chat session ended', 'pax-support-pro' ),
                'endSession' => __( 'End Session', 'pax-support-pro' ),
                'confirmEnd' => __( 'Are you sure you want to end this chat session?', 'pax-support-pro' ),
                'typeMessage' => __( 'Type your message...', 'pax-support-pro' ),
                'send' => __( 'Send', 'pax-support-pro' ),
                'loginToChat' => __( 'Please log in to start a chat.', 'pax-support-pro' ),
                'loginButton' => __( 'Log In', 'pax-support-pro' ),
            ),
        ),
        'nonce'  => wp_create_nonce( 'wp_rest' ),
        'isLoggedIn' => is_user_logged_in(),
        'loginUrl'   => esc_url_raw( $login_url ),
        'aiEnabled'  => ! empty( $options['ai_assistant_enabled'] ),
        'locale'     => determine_locale(),
        'siteLocale' => get_locale(),
        'user'       => array(
            'name'  => $current_user instanceof WP_User ? $current_user->display_name : '',
            'email' => $current_user instanceof WP_User ? $current_user->user_email : '',
        ),
        'scheduler'  => array(
            'timezone'      => $scheduler_settings['timezone'],
            'hours'         => array(
                'start' => $scheduler_settings['hours']['start'],
                'end'   => $scheduler_settings['hours']['end'],
            ),
            'slots_per_hour' => (int) $scheduler_settings['slots_per_hour'],
            'reminder_lead'  => (int) $scheduler_settings['reminder_lead'],
        ),
    );

    wp_localize_script( 'pax-support-pro', 'paxSupportPro', $localize );
    
    // v5.5.3: Add inline script to dispatch settings-ready event
    $inline_script = "
    (function() {
        if (typeof window.paxSupportPro !== 'undefined') {
            window.paxSupportPro.settingsLoaded = true;
            document.dispatchEvent(new CustomEvent('paxSettingsReady', { 
                detail: window.paxSupportPro 
            }));
            console.log('PAX-SETTINGS: Settings loaded and paxSettingsReady event dispatched');
        }
    })();
    ";
    wp_add_inline_script( 'pax-support-pro', $inline_script, 'after' );
}
add_action( 'wp_enqueue_scripts', 'pax_sup_enqueue_public_assets' );

function pax_sup_render_frontend_markup() {
    if ( is_admin() ) {
        return;
    }

    $options = pax_sup_get_options();
    
    // v5.5.6: Only check if plugin is enabled
    // Access control and chat enable/disable handled by JavaScript
    if ( empty( $options['enabled'] ) ) {
        return; // Plugin disabled, don't render anything
    }

    // prepare default menu items if not set
    $menu_items = isset( $options['chat_menu_items'] ) && is_array( $options['chat_menu_items'] )
        ? $options['chat_menu_items']
        : pax_sup_default_menu_items();
    ?>
    <div id="pax-chat-overlay"></div>

    <!-- v5.4.3: Unified Chat Launcher (Integrated into Core System) -->
    <button id="pax-unified-launcher" 
            class="pax-unified-launcher" 
            data-position="<?php echo esc_attr( $options['launcher_position'] ?? 'bottom-right' ); ?>"
            title="<?php echo esc_attr__( 'Open Chat', 'pax-support-pro' ); ?>" 
            aria-label="<?php echo esc_attr__( 'Toggle Chat Window', 'pax-support-pro' ); ?>">
        <?php if ( ! empty( $options['custom_launcher_icon'] ) ) : ?>
            <img src="<?php echo esc_url( $options['custom_launcher_icon'] ); ?>" 
                 alt="<?php esc_attr_e( 'Chat', 'pax-support-pro' ); ?>" 
                 class="pax-launcher-icon">
        <?php else : ?>
            <svg class="pax-launcher-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
            </svg>
        <?php endif; ?>
    </button>

    <div id="pax-chat" role="dialog" aria-modal="true" class="modal-mode mode-assistant">
        <div class="pax-header" id="pax-head">
            <span class="pax-led"></span>
            <div>
                <div class="pax-title"><?php echo esc_html( $options['brand_name'] ); ?></div>
                <div class="pax-sub"><?php esc_html_e( 'Assistant', 'pax-support-pro' ); ?></div>
            </div>
            <!-- Mode switcher will be inserted here by pax-unified-chat.js -->
            <span class="pax-offline" id="pax-offline"><?php esc_html_e( 'Offline', 'pax-support-pro' ); ?></span>
            <div class="pax-spacer"></div>
            <button class="pax-iconbtn" id="pax-menu-btn" type="button" title="<?php esc_attr_e( 'Menu', 'pax-support-pro' ); ?>"><svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg></button>
            <button class="pax-iconbtn" id="pax-head-more" type="button" title="<?php esc_attr_e( 'Quick Actions', 'pax-support-pro' ); ?>"><svg viewBox="0 0 24 24"><path d="M12 7a2 2 0 110-4 2 2 0 010 4zm0 7a2 2 0 110-4 2 2 0 010 4zm0 7a2 2 0 110-4 2 2 0 010 4z"/></svg></button>
            <button class="pax-iconbtn" id="pax-close" type="button" title="<?php esc_attr_e( 'Close', 'pax-support-pro' ); ?>"><svg viewBox="0 0 24 24"><path d="M6.7 5.3 5.3 6.7 10.6 12l-5.3 5.3 1.4 1.4L12 13.4l5.3 5.3 1.4-1.4L13.4 12l5.3-5.3-1.4-1.4L12 10.6z"/></svg></button>

            <div id="pax-head-menu" role="menu">
                <?php
                $menu_icons_map = array(
                    'chat'           => 'dashicons-format-chat',
                    'ticket'         => 'dashicons-tickets-alt',
                    'help'           => 'dashicons-editor-help',
                    'speed'          => 'dashicons-performance',
                    'livechat'       => 'dashicons-businessman',
                    'agent'          => 'dashicons-admin-users',
                    'whatsnew'       => 'dashicons-megaphone',
                    'troubleshooter' => 'dashicons-admin-tools',
                    'diag'           => 'dashicons-chart-line',
                    'callback'       => 'dashicons-phone',
                    'order'          => 'dashicons-cart',
                    'myreq'          => 'dashicons-list-view',
                    'feedback'       => 'dashicons-star-filled',
                    'donate'         => 'dashicons-heart',
                );

                if ( empty( $menu_items ) || ! is_array( $menu_items ) ) {
                    $menu_items = pax_sup_default_menu_items();
                }

                foreach ( $menu_items as $key => $item ) :
                    if ( empty( $item['visible'] ) ) {
                        continue;
                    }
                    $label = isset( $item['label'] ) ? $item['label'] : ucfirst( $key );
                    $icon_class = isset( $menu_icons_map[ $key ] ) ? $menu_icons_map[ $key ] : 'dashicons-admin-generic';
                ?>
                <div class="pax-item" data-act="<?php echo esc_attr( $key ); ?>">
                    <span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
                    <span><?php echo esc_html( $label ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Unified message container -->
        <div id="pax-messages" class="pax-messages"></div>
        
        <!-- Reply-to indicator (hidden by default) -->
        <div class="pax-reply-indicator" style="display: none;"></div>
        
        <!-- Input area -->
        <div class="pax-input-area">
            <input id="pax-input" type="text" placeholder="<?php esc_attr_e( 'Ask me anything...', 'pax-support-pro' ); ?>">
            <button id="pax-send" type="button">
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
        </div>
        <div class="pax-footer-signature" data-protected="true">
            <span class="pax-signature-text">P A X</span>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'pax_sup_render_frontend_markup' );
