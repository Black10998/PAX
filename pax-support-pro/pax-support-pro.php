<?php
/**
 * Plugin Name: PAX Support Pro
 * Plugin URI: https://github.com/Black10998/PAX
 * Description: Professional support ticket system with modern admin UI, real-time chat, AJAX-powered scheduler, and comprehensive callback management. Features include ChatGPT-style reactions, customizable welcome text, smooth animations, custom menus, and advanced analytics.
 * Version: 5.9.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Ahmad AlKhalaf
 * Author URI: https://github.com/Black10998
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/Black10998/PAX
 * Text Domain: pax-support-pro
 * Domain Path: /languages
 * 
 * @package PAX_Support_Pro
 * @version 5.9.0
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'PAX_SUP_FILE' ) ) {
    define( 'PAX_SUP_FILE', __FILE__ );
}

if ( ! defined( 'PAX_SUP_DIR' ) ) {
    define( 'PAX_SUP_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'PAX_SUP_URL' ) ) {
    define( 'PAX_SUP_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'PAX_SUP_NS' ) ) {
    define( 'PAX_SUP_NS', 'pax_support' );
}

if ( ! defined( 'PAX_SUP_VER' ) ) {
    define( 'PAX_SUP_VER', '5.9.0' );
}

if ( ! defined( 'PAX_SUP_OPT_KEY' ) ) {
    define( 'PAX_SUP_OPT_KEY', 'pax_support_options_v2' );
}

if ( ! defined( 'PAX_SUP_REST_NS' ) ) {
    define( 'PAX_SUP_REST_NS', 'pax-support-pro/v1' );
}

require_once PAX_SUP_DIR . 'includes/helpers.php';
require_once PAX_SUP_DIR . 'includes/attachments.php';
require_once PAX_SUP_DIR . 'includes/install.php';
require_once PAX_SUP_DIR . 'includes/quickpanel.php';
require_once PAX_SUP_DIR . 'includes/updater.php';
require_once PAX_SUP_DIR . 'includes/export-import.php';
require_once PAX_SUP_DIR . 'includes/notifications.php';
require_once PAX_SUP_DIR . 'includes/liveagent-db.php';
require_once PAX_SUP_DIR . 'includes/liveagent-settings.php';
require_once PAX_SUP_DIR . 'includes/liveagent-capabilities.php';
require_once PAX_SUP_DIR . 'includes/rest/chat-endpoints.php';
require_once PAX_SUP_DIR . 'admin/settings.php';
require_once PAX_SUP_DIR . 'admin/console.php';
require_once PAX_SUP_DIR . 'admin/tickets.php';
require_once PAX_SUP_DIR . 'admin/scheduler.php';
require_once PAX_SUP_DIR . 'admin/dashboard-analytics-ui.php';

// Load new admin pages
require_once PAX_SUP_DIR . 'admin/pages/roles-permissions.php';
require_once PAX_SUP_DIR . 'admin/pages/analytics-dashboard.php';
require_once PAX_SUP_DIR . 'admin/pages/system-health.php';
require_once PAX_SUP_DIR . 'admin/pages/theme-settings.php';
require_once PAX_SUP_DIR . 'admin/pages/live-agent-center.php';
require_once PAX_SUP_DIR . 'admin/pages/chat-reactions.php';

// Load test page only in development
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    require_once PAX_SUP_DIR . 'admin/test-update-modals.php';
}

require_once PAX_SUP_DIR . 'public/chat.php';
// v5.4.2: Legacy Live Chat button disabled - now using unified interface
// require_once PAX_SUP_DIR . 'public/liveagent-button.php';
require_once PAX_SUP_DIR . 'rest/chat.php';
require_once PAX_SUP_DIR . 'rest/ticket.php';
require_once PAX_SUP_DIR . 'rest/agent.php';
require_once PAX_SUP_DIR . 'rest/callback.php';
require_once PAX_SUP_DIR . 'rest/support.php';
require_once PAX_SUP_DIR . 'rest/scheduler.php';
require_once PAX_SUP_DIR . 'rest/attachment.php';
require_once PAX_SUP_DIR . 'rest/system-health.php';
require_once PAX_SUP_DIR . 'rest/liveagent-session.php';
require_once PAX_SUP_DIR . 'rest/liveagent-message.php';
require_once PAX_SUP_DIR . 'rest/liveagent-status.php';
require_once PAX_SUP_DIR . 'rest/liveagent-file.php';
require_once PAX_SUP_DIR . 'rest/live-agent.php';
require_once PAX_SUP_DIR . 'rest/reactions.php';

/**
 * Force-load the latest JavaScript version to bypass cache
 */
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'pax-support-pro',
        plugin_dir_url(__FILE__) . 'public/assets.js',
        [],
        time(),
        true
    );
});

/**
 * Show welcome notice on activation
 */
function pax_sup_welcome_notice() {
    if ( get_option( 'pax_sup_activation_redirect' ) ) {
        delete_option( 'pax_sup_activation_redirect' );
        
        if ( ! isset( $_GET['activate-multi'] ) ) {
            ?>
            <div class="notice notice-success is-dismissible" style="border-left-color: #e53935;">
                <h2 style="margin-top: 1em;">
                    <span class="dashicons dashicons-yes-alt" style="color: #e53935; font-size: 24px; vertical-align: middle;"></span>
                    <?php esc_html_e( 'Welcome to PAX Support Pro!', 'pax-support-pro' ); ?>
                </h2>
                <p style="font-size: 14px;">
                    <?php esc_html_e( 'Thank you for installing PAX Support Pro. Get started by configuring your support system.', 'pax-support-pro' ); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-settings' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Go to Settings', 'pax-support-pro' ); ?>
                    </a>
                    <a href="https://github.com/Black10998/PAX" target="_blank" rel="noopener noreferrer" class="button">
                        <?php esc_html_e( 'View Documentation', 'pax-support-pro' ); ?>
                    </a>
                </p>
                <p style="font-size: 13px; color: #666; margin-top: 12px;">
                    <strong><?php esc_html_e( 'Note:', 'pax-support-pro' ); ?></strong>
                    <?php esc_html_e( 'This plugin includes optional AI features. Configure your OpenAI API key under AI Settings if you wish to enable automated responses.', 'pax-support-pro' ); ?>
                </p>
            </div>
            <?php
        }
    }
}
add_action( 'admin_notices', 'pax_sup_welcome_notice' );

/**
 * Auto-refresh functionality after plugin update
 * Clears caches and forces reload of assets
 * 
 * @since 5.7.11
 */
function pax_sup_auto_refresh_after_update( $upgrader_object, $options ) {
    // Check if this is a plugin update
    if ( $options['action'] !== 'update' || $options['type'] !== 'plugin' ) {
        return;
    }

    // Check if our plugin was updated
    if ( isset( $options['plugins'] ) ) {
        foreach ( $options['plugins'] as $plugin ) {
            if ( $plugin === plugin_basename( __FILE__ ) ) {
                // Clear plugin update transients
                delete_site_transient( 'update_plugins' );
                
                // Flush rewrite rules
                flush_rewrite_rules();
                
                // Clear any plugin-specific caches
                wp_cache_flush();
                
                // Set a flag to force asset reload
                set_transient( 'pax_sup_force_reload', true, 300 ); // 5 minutes
                
                break;
            }
        }
    }
}
add_action( 'upgrader_process_complete', 'pax_sup_auto_refresh_after_update', 10, 2 );

/**
 * Force reload of assets after update
 * Appends version query to all enqueued assets
 * 
 * @since 5.7.11
 */
function pax_sup_force_asset_reload() {
    if ( get_transient( 'pax_sup_force_reload' ) ) {
        // This will be handled by the version constant in asset enqueuing
        // The version is already set to PAX_SUP_VER which changes with each update
        delete_transient( 'pax_sup_force_reload' );
    }
}
add_action( 'init', 'pax_sup_force_asset_reload' );
