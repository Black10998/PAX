<?php
/**
 * Export/Import Settings Functionality
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle settings export
 */
function pax_sup_handle_settings_export() {
    if ( ! isset( $_POST['pax_export_settings_nonce'] ) || ! wp_verify_nonce( $_POST['pax_export_settings_nonce'], 'pax_export_settings' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to export settings.', 'pax-support-pro' ) );
    }

    // Get all PAX Support Pro options
    $export_data = array(
        'version' => PAX_SUP_VER,
        'export_date' => current_time( 'mysql' ),
        'site_url' => get_site_url(),
        'options' => array(
            'pax_support_options_v2' => get_option( 'pax_support_options_v2', array() ),
            'pax_roles_config' => get_option( 'pax_roles_config', array() ),
            'pax_default_role' => get_option( 'pax_default_role', 'subscriber' ),
            'pax_theme_settings' => get_option( 'pax_theme_settings', array() ),
            'pax_notification_email' => get_option( 'pax_notification_email', '' ),
            'pax_enable_email_alerts' => get_option( 'pax_enable_email_alerts', false ),
            'pax_enable_realtime_notifications' => get_option( 'pax_enable_realtime_notifications', false ),
        ),
    );

    // Generate JSON
    $json = wp_json_encode( $export_data, JSON_PRETTY_PRINT );

    // Set headers for download
    header( 'Content-Type: application/json' );
    header( 'Content-Disposition: attachment; filename="pax-support-pro-settings-' . date( 'Y-m-d-His' ) . '.json"' );
    header( 'Content-Length: ' . strlen( $json ) );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    echo $json;
    exit;
}
add_action( 'admin_init', 'pax_sup_handle_settings_export' );

/**
 * Handle settings import
 */
function pax_sup_handle_settings_import() {
    if ( ! isset( $_POST['pax_import_settings_nonce'] ) || ! wp_verify_nonce( $_POST['pax_import_settings_nonce'], 'pax_import_settings' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to import settings.', 'pax-support-pro' ) );
    }

    // Check if file was uploaded
    if ( ! isset( $_FILES['pax_import_file'] ) || $_FILES['pax_import_file']['error'] !== UPLOAD_ERR_OK ) {
        add_settings_error(
            'pax_import',
            'pax_import_error',
            __( 'No file was uploaded or an error occurred during upload.', 'pax-support-pro' ),
            'error'
        );
        return;
    }

    // Read file contents
    $file_content = file_get_contents( $_FILES['pax_import_file']['tmp_name'] );
    
    if ( empty( $file_content ) ) {
        add_settings_error(
            'pax_import',
            'pax_import_error',
            __( 'The uploaded file is empty.', 'pax-support-pro' ),
            'error'
        );
        return;
    }

    // Decode JSON
    $import_data = json_decode( $file_content, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        add_settings_error(
            'pax_import',
            'pax_import_error',
            __( 'Invalid JSON file. Please upload a valid PAX Support Pro settings file.', 'pax-support-pro' ),
            'error'
        );
        return;
    }

    // Validate import data structure
    if ( ! isset( $import_data['version'] ) || ! isset( $import_data['options'] ) ) {
        add_settings_error(
            'pax_import',
            'pax_import_error',
            __( 'Invalid settings file format.', 'pax-support-pro' ),
            'error'
        );
        return;
    }

    // Import options
    $imported_count = 0;
    foreach ( $import_data['options'] as $option_name => $option_value ) {
        if ( update_option( $option_name, $option_value ) ) {
            $imported_count++;
        }
    }

    // Apply role capabilities if roles config was imported
    if ( isset( $import_data['options']['pax_roles_config'] ) ) {
        pax_sup_apply_role_capabilities( $import_data['options']['pax_roles_config'] );
    }

    add_settings_error(
        'pax_import',
        'pax_import_success',
        sprintf(
            /* translators: %d: number of imported settings */
            __( 'Settings imported successfully. %d options were updated.', 'pax-support-pro' ),
            $imported_count
        ),
        'success'
    );
}
add_action( 'admin_init', 'pax_sup_handle_settings_import' );

/**
 * Render Export/Import section in settings
 */
function pax_sup_render_export_import_section() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="pax-card">
        <div class="pax-card-header">
            <h2>
                <span class="dashicons dashicons-database-export"></span>
                <?php esc_html_e( 'Export / Import Settings', 'pax-support-pro' ); ?>
            </h2>
        </div>
        <div class="pax-card-body">
            <div class="pax-export-import-grid">
                <!-- Export Section -->
                <div class="pax-export-section">
                    <h3><?php esc_html_e( 'Export Settings', 'pax-support-pro' ); ?></h3>
                    <p class="description">
                        <?php esc_html_e( 'Export all PAX Support Pro settings to a JSON file. This includes plugin configuration, roles, theme settings, and notification preferences.', 'pax-support-pro' ); ?>
                    </p>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'pax_export_settings', 'pax_export_settings_nonce' ); ?>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e( 'Export Settings', 'pax-support-pro' ); ?>
                        </button>
                    </form>
                </div>

                <!-- Import Section -->
                <div class="pax-import-section">
                    <h3><?php esc_html_e( 'Import Settings', 'pax-support-pro' ); ?></h3>
                    <p class="description">
                        <?php esc_html_e( 'Import settings from a previously exported JSON file. This will overwrite your current settings.', 'pax-support-pro' ); ?>
                    </p>
                    <form method="post" action="" enctype="multipart/form-data" id="pax-import-form">
                        <?php wp_nonce_field( 'pax_import_settings', 'pax_import_settings_nonce' ); ?>
                        <div class="pax-file-upload">
                            <input type="file" name="pax_import_file" id="pax_import_file" accept=".json" required>
                            <label for="pax_import_file" class="button">
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e( 'Choose File', 'pax-support-pro' ); ?>
                            </label>
                            <span class="pax-file-name"><?php esc_html_e( 'No file chosen', 'pax-support-pro' ); ?></span>
                        </div>
                        <button type="submit" class="button button-primary" id="pax-import-button">
                            <span class="dashicons dashicons-database-import"></span>
                            <?php esc_html_e( 'Import Settings', 'pax-support-pro' ); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="pax-import-warning">
                <span class="dashicons dashicons-warning"></span>
                <strong><?php esc_html_e( 'Warning:', 'pax-support-pro' ); ?></strong>
                <?php esc_html_e( 'Importing settings will overwrite your current configuration. Make sure to export your current settings before importing.', 'pax-support-pro' ); ?>
            </div>
        </div>
    </div>

    <style>
    .pax-export-import-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 20px;
    }

    .pax-export-section,
    .pax-import-section {
        padding: 20px;
        background: #f9f9f9;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .pax-export-section h3,
    .pax-import-section h3 {
        margin-top: 0;
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }

    .pax-export-section .description,
    .pax-import-section .description {
        margin-bottom: 16px;
        font-size: 13px;
        line-height: 1.6;
    }

    .pax-file-upload {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .pax-file-upload input[type="file"] {
        display: none;
    }

    .pax-file-upload label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .pax-file-name {
        font-size: 13px;
        color: #666;
        font-style: italic;
    }

    .pax-import-warning {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        color: #856404;
        font-size: 13px;
        line-height: 1.6;
    }

    .pax-import-warning .dashicons {
        color: #ffc107;
        flex-shrink: 0;
        margin-top: 2px;
    }

    @media (max-width: 782px) {
        .pax-export-import-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Update file name display
        $('#pax_import_file').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $('.pax-file-name').text(fileName || '<?php esc_html_e( 'No file chosen', 'pax-support-pro' ); ?>');
        });

        // Confirm import
        $('#pax-import-form').on('submit', function(e) {
            if (!confirm('<?php echo esc_js( __( 'Are you sure you want to import these settings? This will overwrite your current configuration.', 'pax-support-pro' ) ); ?>')) {
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
    <?php
}
