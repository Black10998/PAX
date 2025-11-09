<?php
/**
 * Plugin Update Checker Integration
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load plugin-update-checker library
require_once PAX_SUP_DIR . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class PAX_Support_Pro_Updater {

    private static $instance = null;
    
    private $update_checker = null;
    
    private $cache_dir = '';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->cache_dir = PAX_SUP_DIR . 'CheckOptData';
        
        // Ensure cache directory exists
        $this->ensure_cache_directory();
        
        // Initialize plugin-update-checker
        $this->init_update_checker();
        
        // Add REST API endpoints
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        
        // Add admin hooks
        add_action( 'admin_init', array( $this, 'maybe_schedule_checks' ) );
    }
    
    /**
     * Initialize plugin-update-checker
     */
    private function init_update_checker() {
        $this->update_checker = PucFactory::buildUpdateChecker(
            'https://github.com/Black10998/Black10998/',
            PAX_SUP_FILE,
            'pax-support-pro'
        );
        
        // Set branch to main
        $this->update_checker->setBranch( 'main' );
        
        // Get VCS API and enable release assets
        $this->update_checker->getVcsApi()->enableReleaseAssets();
    }
    
    /**
     * Ensure cache directory exists with proper permissions
     */
    private function ensure_cache_directory() {
        if ( ! file_exists( $this->cache_dir ) ) {
            wp_mkdir_p( $this->cache_dir );
            
            // Add .htaccess to protect directory
            $htaccess_file = $this->cache_dir . '/.htaccess';
            if ( ! file_exists( $htaccess_file ) ) {
                file_put_contents( $htaccess_file, "Deny from all\n" );
            }
            
            // Add index.php to prevent directory listing
            $index_file = $this->cache_dir . '/index.php';
            if ( ! file_exists( $index_file ) ) {
                file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
            }
        }
        
        // Ensure directory is writable
        if ( file_exists( $this->cache_dir ) && ! is_writable( $this->cache_dir ) ) {
            @chmod( $this->cache_dir, 0755 );
        }
    }
    
    /**
     * Get cache file path
     */
    private function get_cache_file( $name = 'status.json' ) {
        return $this->cache_dir . '/' . $name;
    }
    
    /**
     * Save update status to cache file
     */
    private function save_update_cache( $data ) {
        $cache_file = $this->get_cache_file();
        $data['cached_at'] = time();
        file_put_contents( $cache_file, json_encode( $data, JSON_PRETTY_PRINT ) );
    }
    
    /**
     * Load update status from cache file
     */
    private function load_update_cache() {
        $cache_file = $this->get_cache_file();
        if ( file_exists( $cache_file ) ) {
            $content = file_get_contents( $cache_file );
            $data = json_decode( $content, true );
            
            // Check if cache is still valid (6 hours)
            if ( isset( $data['cached_at'] ) && ( time() - $data['cached_at'] ) < 6 * HOUR_IN_SECONDS ) {
                return $data;
            }
        }
        return null;
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route(
            PAX_SUP_REST_NS,
            '/check-updates',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_check_updates' ),
                'permission_callback' => function() {
                    return current_user_can( 'update_plugins' );
                },
            )
        );
        
        register_rest_route(
            PAX_SUP_REST_NS,
            '/update-diagnostics',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_update_diagnostics' ),
                'permission_callback' => function() {
                    return current_user_can( 'update_plugins' );
                },
            )
        );
    }
    
    /**
     * REST endpoint to manually check for updates
     */
    public function rest_check_updates( $request ) {
        // Force check for updates
        $update = $this->update_checker->checkForUpdates();
        
        if ( $update !== null ) {
            $has_update = version_compare( PAX_SUP_VER, $update->version, '<' );
            
            // Save to cache
            $cache_data = array(
                'version'      => $update->version,
                'download_url' => $update->download_url,
                'homepage'     => $update->homepage ?? '',
                'changelog'    => $update->changelog ?? '',
            );
            $this->save_update_cache( $cache_data );
            
            return rest_ensure_response( array(
                'success'         => true,
                'current_version' => PAX_SUP_VER,
                'latest_version'  => $update->version,
                'has_update'      => $has_update,
                'release_url'     => $update->homepage ?? '',
                'changelog'       => $update->changelog ?? '',
                'message'         => $has_update 
                    ? sprintf( 
                        __( 'Update available: %s → %s', 'pax-support-pro' ),
                        PAX_SUP_VER,
                        $update->version
                    )
                    : __( 'You are running the latest version.', 'pax-support-pro' ),
            ) );
        }
        
        return rest_ensure_response( array(
            'success'         => true,
            'current_version' => PAX_SUP_VER,
            'latest_version'  => PAX_SUP_VER,
            'has_update'      => false,
            'message'         => __( 'You are running the latest version.', 'pax-support-pro' ),
        ) );
    }
    
    /**
     * REST endpoint for update system diagnostics
     */
    public function rest_update_diagnostics( $request ) {
        $options = pax_sup_get_options();
        $cache_file = $this->get_cache_file();
        
        $diagnostics = array(
            'cache_directory' => array(
                'path'        => $this->cache_dir,
                'exists'      => file_exists( $this->cache_dir ),
                'writable'    => is_writable( $this->cache_dir ),
                'permissions' => file_exists( $this->cache_dir ) ? substr( sprintf( '%o', fileperms( $this->cache_dir ) ), -4 ) : 'N/A',
            ),
            'cache_file' => array(
                'path'     => $cache_file,
                'exists'   => file_exists( $cache_file ),
                'size'     => file_exists( $cache_file ) ? filesize( $cache_file ) : 0,
                'modified' => file_exists( $cache_file ) ? date( 'Y-m-d H:i:s', filemtime( $cache_file ) ) : 'N/A',
            ),
            'settings' => array(
                'auto_update_enabled'    => ! empty( $options['auto_update_enabled'] ),
                'update_check_frequency' => $options['update_check_frequency'] ?? 'daily',
            ),
            'update_checker' => array(
                'library'    => 'plugin-update-checker v5.6',
                'last_check' => $this->update_checker->getLastCheckTime() ? date( 'Y-m-d H:i:s', $this->update_checker->getLastCheckTime() ) : 'Never',
            ),
            'github_connection' => array(
                'repo'    => 'Black10998/Black10998',
                'branch'  => 'main',
                'api_url' => 'https://api.github.com/repos/Black10998/Black10998/releases/latest',
            ),
            'current_version' => PAX_SUP_VER,
        );
        
        // Test GitHub connection
        $test_request = wp_remote_get(
            'https://api.github.com/repos/Black10998/Black10998/releases/latest',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'PAX-Support-Pro/' . PAX_SUP_VER,
                ),
            )
        );
        
        if ( is_wp_error( $test_request ) ) {
            $diagnostics['github_connection']['status'] = 'error';
            $diagnostics['github_connection']['error'] = $test_request->get_error_message();
        } else {
            $diagnostics['github_connection']['status'] = 'success';
            $diagnostics['github_connection']['response_code'] = wp_remote_retrieve_response_code( $test_request );
        }
        
        return rest_ensure_response( array(
            'success'     => true,
            'diagnostics' => $diagnostics,
        ) );
    }
    
    /**
     * Maybe schedule automatic update checks
     * Note: plugin-update-checker v5.6 handles check intervals automatically
     */
    public function maybe_schedule_checks() {
        // plugin-update-checker handles scheduling automatically
        // This method is kept for backward compatibility
    }
}

function pax_sup_updater() {
    return PAX_Support_Pro_Updater::instance();
}
add_action( 'plugins_loaded', 'pax_sup_updater' );
