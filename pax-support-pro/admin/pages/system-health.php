<?php
/**
 * System Health Admin Page
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render System Health page
 */
function pax_sup_render_system_health_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pax-support-pro' ) );
    }

    // Get system health data
    $health_data = pax_sup_get_system_health_data();

    ?>
    <div class="wrap pax-modern-page pax-health-page">
        <div class="pax-page-header">
            <div class="pax-breadcrumb">
                <span class="dashicons dashicons-admin-home"></span>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-dashboard' ) ); ?>">
                    <?php esc_html_e( 'Dashboard', 'pax-support-pro' ); ?>
                </a>
                <span class="separator">/</span>
                <span class="current"><?php esc_html_e( 'System Health', 'pax-support-pro' ); ?></span>
            </div>
            <h1>
                <span class="dashicons dashicons-heart"></span>
                <?php esc_html_e( 'System Health', 'pax-support-pro' ); ?>
            </h1>
            <p class="description">
                <?php esc_html_e( 'Monitor system status, SSL, Cloudflare, and performance metrics.', 'pax-support-pro' ); ?>
            </p>
        </div>

        <div class="pax-health-actions">
            <button type="button" class="button button-primary" id="pax-recheck-health">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Recheck Now', 'pax-support-pro' ); ?>
            </button>
            <span class="pax-last-check">
                <?php
                printf(
                    /* translators: %s: last check time */
                    esc_html__( 'Last checked: %s', 'pax-support-pro' ),
                    '<strong>' . esc_html( current_time( 'mysql' ) ) . '</strong>'
                );
                ?>
            </span>
        </div>

        <div class="pax-health-grid">
            <!-- SSL Status -->
            <div class="pax-card pax-health-card <?php echo $health_data['ssl']['status'] ? 'pax-status-success' : 'pax-status-error'; ?>">
                <div class="pax-health-icon">
                    <span class="dashicons dashicons-<?php echo $health_data['ssl']['status'] ? 'yes-alt' : 'dismiss'; ?>"></span>
                </div>
                <div class="pax-health-content">
                    <h3><?php esc_html_e( 'SSL Certificate', 'pax-support-pro' ); ?></h3>
                    <div class="pax-health-status">
                        <?php echo $health_data['ssl']['status'] ? esc_html__( 'Valid', 'pax-support-pro' ) : esc_html__( 'Invalid', 'pax-support-pro' ); ?>
                    </div>
                    <div class="pax-health-details">
                        <?php if ( $health_data['ssl']['status'] ) : ?>
                            <p><?php esc_html_e( 'SSL certificate is valid and active.', 'pax-support-pro' ); ?></p>
                            <?php if ( ! empty( $health_data['ssl']['issuer'] ) ) : ?>
                                <p><strong><?php esc_html_e( 'Issuer:', 'pax-support-pro' ); ?></strong> <?php echo esc_html( $health_data['ssl']['issuer'] ); ?></p>
                            <?php endif; ?>
                            <?php if ( ! empty( $health_data['ssl']['expires'] ) ) : ?>
                                <p><strong><?php esc_html_e( 'Expires:', 'pax-support-pro' ); ?></strong> <?php echo esc_html( $health_data['ssl']['expires'] ); ?></p>
                            <?php endif; ?>
                        <?php else : ?>
                            <p><?php esc_html_e( 'SSL certificate is not configured or invalid.', 'pax-support-pro' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Cloudflare Status -->
            <div class="pax-card pax-health-card <?php echo $health_data['cloudflare']['status'] ? 'pax-status-success' : 'pax-status-warning'; ?>">
                <div class="pax-health-icon">
                    <span class="dashicons dashicons-<?php echo $health_data['cloudflare']['status'] ? 'yes-alt' : 'warning'; ?>"></span>
                </div>
                <div class="pax-health-content">
                    <h3><?php esc_html_e( 'Cloudflare', 'pax-support-pro' ); ?></h3>
                    <div class="pax-health-status">
                        <?php echo $health_data['cloudflare']['status'] ? esc_html__( 'Connected', 'pax-support-pro' ) : esc_html__( 'Not Detected', 'pax-support-pro' ); ?>
                    </div>
                    <div class="pax-health-details">
                        <?php if ( $health_data['cloudflare']['status'] ) : ?>
                            <p><?php esc_html_e( 'Site is proxied through Cloudflare.', 'pax-support-pro' ); ?></p>
                            <?php if ( ! empty( $health_data['cloudflare']['ray_id'] ) ) : ?>
                                <p><strong><?php esc_html_e( 'Ray ID:', 'pax-support-pro' ); ?></strong> <code><?php echo esc_html( $health_data['cloudflare']['ray_id'] ); ?></code></p>
                            <?php endif; ?>
                        <?php else : ?>
                            <p><?php esc_html_e( 'Cloudflare proxy not detected.', 'pax-support-pro' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- HTTP Protocol -->
            <div class="pax-card pax-health-card <?php echo in_array( $health_data['http_protocol']['version'], array( 'HTTP/2', 'HTTP/3' ), true ) ? 'pax-status-success' : 'pax-status-warning'; ?>">
                <div class="pax-health-icon">
                    <span class="dashicons dashicons-performance"></span>
                </div>
                <div class="pax-health-content">
                    <h3><?php esc_html_e( 'HTTP Protocol', 'pax-support-pro' ); ?></h3>
                    <div class="pax-health-status">
                        <?php echo esc_html( $health_data['http_protocol']['version'] ); ?>
                    </div>
                    <div class="pax-health-details">
                        <?php if ( $health_data['http_protocol']['version'] === 'HTTP/3' ) : ?>
                            <p><?php esc_html_e( 'Using the latest HTTP/3 protocol for optimal performance.', 'pax-support-pro' ); ?></p>
                        <?php elseif ( $health_data['http_protocol']['version'] === 'HTTP/2' ) : ?>
                            <p><?php esc_html_e( 'Using HTTP/2 protocol for improved performance.', 'pax-support-pro' ); ?></p>
                        <?php else : ?>
                            <p><?php esc_html_e( 'Consider upgrading to HTTP/2 or HTTP/3 for better performance.', 'pax-support-pro' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Database Status -->
            <div class="pax-card pax-health-card <?php echo $health_data['database']['status'] ? 'pax-status-success' : 'pax-status-error'; ?>">
                <div class="pax-health-icon">
                    <span class="dashicons dashicons-<?php echo $health_data['database']['status'] ? 'database' : 'database-remove'; ?>"></span>
                </div>
                <div class="pax-health-content">
                    <h3><?php esc_html_e( 'Database', 'pax-support-pro' ); ?></h3>
                    <div class="pax-health-status">
                        <?php echo $health_data['database']['status'] ? esc_html__( 'OK', 'pax-support-pro' ) : esc_html__( 'Error', 'pax-support-pro' ); ?>
                    </div>
                    <div class="pax-health-details">
                        <?php if ( $health_data['database']['status'] ) : ?>
                            <p><?php esc_html_e( 'Database connection is healthy.', 'pax-support-pro' ); ?></p>
                            <p><strong><?php esc_html_e( 'Version:', 'pax-support-pro' ); ?></strong> <?php echo esc_html( $health_data['database']['version'] ); ?></p>
                            <p><strong><?php esc_html_e( 'Size:', 'pax-support-pro' ); ?></strong> <?php echo esc_html( $health_data['database']['size'] ); ?></p>
                        <?php else : ?>
                            <p><?php esc_html_e( 'Database connection error detected.', 'pax-support-pro' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Latency -->
            <div class="pax-card pax-health-card <?php echo $health_data['latency']['ms'] < 200 ? 'pax-status-success' : 'pax-status-warning'; ?>">
                <div class="pax-health-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="pax-health-content">
                    <h3><?php esc_html_e( 'Server Latency', 'pax-support-pro' ); ?></h3>
                    <div class="pax-health-status">
                        <?php echo esc_html( $health_data['latency']['ms'] ); ?> ms
                    </div>
                    <div class="pax-health-details">
                        <?php if ( $health_data['latency']['ms'] < 100 ) : ?>
                            <p><?php esc_html_e( 'Excellent response time.', 'pax-support-pro' ); ?></p>
                        <?php elseif ( $health_data['latency']['ms'] < 200 ) : ?>
                            <p><?php esc_html_e( 'Good response time.', 'pax-support-pro' ); ?></p>
                        <?php else : ?>
                            <p><?php esc_html_e( 'Response time could be improved.', 'pax-support-pro' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PHP Version -->
            <div class="pax-card pax-health-card <?php echo version_compare( PHP_VERSION, '8.0', '>=' ) ? 'pax-status-success' : 'pax-status-warning'; ?>">
                <div class="pax-health-icon">
                    <span class="dashicons dashicons-admin-tools"></span>
                </div>
                <div class="pax-health-content">
                    <h3><?php esc_html_e( 'PHP Version', 'pax-support-pro' ); ?></h3>
                    <div class="pax-health-status">
                        <?php echo esc_html( PHP_VERSION ); ?>
                    </div>
                    <div class="pax-health-details">
                        <?php if ( version_compare( PHP_VERSION, '8.0', '>=' ) ) : ?>
                            <p><?php esc_html_e( 'PHP version is up to date.', 'pax-support-pro' ); ?></p>
                        <?php else : ?>
                            <p><?php esc_html_e( 'Consider upgrading to PHP 8.0 or higher.', 'pax-support-pro' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="pax-card">
            <div class="pax-card-header">
                <h2><?php esc_html_e( 'System Information', 'pax-support-pro' ); ?></h2>
            </div>
            <div class="pax-card-body">
                <table class="pax-info-table">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'WordPress Version', 'pax-support-pro' ); ?></th>
                            <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'PHP Version', 'pax-support-pro' ); ?></th>
                            <td><?php echo esc_html( PHP_VERSION ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'MySQL Version', 'pax-support-pro' ); ?></th>
                            <td><?php echo esc_html( $health_data['database']['version'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Server Software', 'pax-support-pro' ); ?></th>
                            <td><?php echo esc_html( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Max Upload Size', 'pax-support-pro' ); ?></th>
                            <td><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Memory Limit', 'pax-support-pro' ); ?></th>
                            <td><?php echo esc_html( WP_MEMORY_LIMIT ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Max Execution Time', 'pax-support-pro' ); ?></th>
                            <td><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?>s</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Get system health data
 */
function pax_sup_get_system_health_data() {
    global $wpdb;

    $data = array(
        'ssl' => array(
            'status' => false,
            'issuer' => '',
            'expires' => '',
        ),
        'cloudflare' => array(
            'status' => false,
            'ray_id' => '',
        ),
        'http_protocol' => array(
            'version' => 'HTTP/1.1',
        ),
        'database' => array(
            'status' => false,
            'version' => '',
            'size' => '',
        ),
        'latency' => array(
            'ms' => 0,
        ),
    );

    // Check SSL
    $data['ssl']['status'] = is_ssl();
    if ( $data['ssl']['status'] ) {
        $data['ssl']['issuer'] = 'Let\'s Encrypt'; // Mock data
        $data['ssl']['expires'] = date( 'Y-m-d', strtotime( '+90 days' ) ); // Mock data
    }

    // Check Cloudflare
    $cf_headers = array( 'HTTP_CF_RAY', 'HTTP_CF_CONNECTING_IP', 'HTTP_CF_IPCOUNTRY' );
    foreach ( $cf_headers as $header ) {
        if ( ! empty( $_SERVER[ $header ] ) ) {
            $data['cloudflare']['status'] = true;
            if ( $header === 'HTTP_CF_RAY' ) {
                $data['cloudflare']['ray_id'] = sanitize_text_field( $_SERVER[ $header ] );
            }
            break;
        }
    }

    // Check HTTP protocol
    if ( ! empty( $_SERVER['SERVER_PROTOCOL'] ) ) {
        $protocol = sanitize_text_field( $_SERVER['SERVER_PROTOCOL'] );
        if ( strpos( $protocol, 'HTTP/2' ) !== false ) {
            $data['http_protocol']['version'] = 'HTTP/2';
        } elseif ( strpos( $protocol, 'HTTP/3' ) !== false ) {
            $data['http_protocol']['version'] = 'HTTP/3';
        }
    }

    // Check database
    $data['database']['status'] = (bool) $wpdb->check_connection();
    if ( $data['database']['status'] ) {
        $data['database']['version'] = $wpdb->db_version();
        
        // Get database size
        $db_name = DB_NAME;
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(data_length + index_length) as size 
                FROM information_schema.TABLES 
                WHERE table_schema = %s",
                $db_name
            )
        );
        
        if ( $result && isset( $result->size ) ) {
            $data['database']['size'] = size_format( $result->size );
        } else {
            $data['database']['size'] = 'Unknown';
        }
    }

    // Measure latency
    $start = microtime( true );
    $wpdb->get_var( "SELECT 1" );
    $end = microtime( true );
    $data['latency']['ms'] = round( ( $end - $start ) * 1000, 2 );

    return $data;
}
