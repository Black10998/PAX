<?php
/**
 * Theme Settings Admin Page
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Theme Settings page
 */
function pax_sup_render_theme_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pax-support-pro' ) );
    }

    // Handle form submission
    if ( isset( $_POST['pax_theme_nonce'] ) && wp_verify_nonce( $_POST['pax_theme_nonce'], 'pax_theme_save' ) ) {
        pax_sup_save_theme_settings();
    }

    $theme_settings = pax_sup_get_theme_settings();

    ?>
    <div class="wrap pax-modern-page pax-theme-page">
        <div class="pax-page-header">
            <div class="pax-breadcrumb">
                <span class="dashicons dashicons-admin-home"></span>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-dashboard' ) ); ?>">
                    <?php esc_html_e( 'Dashboard', 'pax-support-pro' ); ?>
                </a>
                <span class="separator">/</span>
                <span class="current"><?php esc_html_e( 'Theme Settings', 'pax-support-pro' ); ?></span>
            </div>
            <h1>
                <span class="dashicons dashicons-admin-appearance"></span>
                <?php esc_html_e( 'Theme Settings', 'pax-support-pro' ); ?>
            </h1>
            <p class="description">
                <?php esc_html_e( 'Configure frontend theme options and user personalization settings.', 'pax-support-pro' ); ?>
            </p>
        </div>

        <form method="post" action="" class="pax-theme-form">
            <?php wp_nonce_field( 'pax_theme_save', 'pax_theme_nonce' ); ?>

            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Default Theme', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <div class="pax-form-group">
                        <label for="pax_default_theme">
                            <?php esc_html_e( 'Default Theme Mode', 'pax-support-pro' ); ?>
                        </label>
                        <select name="pax_default_theme" id="pax_default_theme" class="pax-select">
                            <option value="neon" <?php selected( $theme_settings['default_theme'], 'neon' ); ?>>
                                <?php esc_html_e( 'Neon Mode (Default)', 'pax-support-pro' ); ?>
                            </option>
                            <option value="light" <?php selected( $theme_settings['default_theme'], 'light' ); ?>>
                                <?php esc_html_e( 'Light Mode', 'pax-support-pro' ); ?>
                            </option>
                            <option value="dark" <?php selected( $theme_settings['default_theme'], 'dark' ); ?>>
                                <?php esc_html_e( 'Dark Mode', 'pax-support-pro' ); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Select the default theme mode for new users. Users can override this in their preferences.', 'pax-support-pro' ); ?>
                        </p>
                    </div>

                    <div class="pax-form-group">
                        <label>
                            <input type="checkbox" 
                                   name="pax_allow_theme_switching" 
                                   value="1" 
                                   <?php checked( $theme_settings['allow_theme_switching'] ); ?>>
                            <?php esc_html_e( 'Allow users to switch themes', 'pax-support-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Enable the theme switcher in the chat widget for users.', 'pax-support-pro' ); ?>
                        </p>
                    </div>

                    <div class="pax-form-group">
                        <label>
                            <input type="checkbox" 
                                   name="pax_remember_theme_preference" 
                                   value="1" 
                                   <?php checked( $theme_settings['remember_theme_preference'] ); ?>>
                            <?php esc_html_e( 'Remember user theme preference', 'pax-support-pro' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Save user theme preference in localStorage for future visits.', 'pax-support-pro' ); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Theme Preview', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <div class="pax-theme-preview-grid">
                        <div class="pax-theme-preview pax-theme-neon">
                            <div class="pax-preview-header">
                                <span class="dashicons dashicons-admin-appearance"></span>
                                <h3><?php esc_html_e( 'Neon Mode', 'pax-support-pro' ); ?></h3>
                            </div>
                            <div class="pax-preview-body">
                                <div class="pax-preview-chat">
                                    <div class="pax-preview-message pax-preview-user">
                                        <?php esc_html_e( 'Hello! I need help.', 'pax-support-pro' ); ?>
                                    </div>
                                    <div class="pax-preview-message pax-preview-agent">
                                        <?php esc_html_e( 'How can I assist you?', 'pax-support-pro' ); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="pax-preview-footer">
                                <span class="pax-preview-badge"><?php esc_html_e( 'Default', 'pax-support-pro' ); ?></span>
                            </div>
                        </div>

                        <div class="pax-theme-preview pax-theme-light">
                            <div class="pax-preview-header">
                                <span class="dashicons dashicons-admin-appearance"></span>
                                <h3><?php esc_html_e( 'Light Mode', 'pax-support-pro' ); ?></h3>
                            </div>
                            <div class="pax-preview-body">
                                <div class="pax-preview-chat">
                                    <div class="pax-preview-message pax-preview-user">
                                        <?php esc_html_e( 'Hello! I need help.', 'pax-support-pro' ); ?>
                                    </div>
                                    <div class="pax-preview-message pax-preview-agent">
                                        <?php esc_html_e( 'How can I assist you?', 'pax-support-pro' ); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="pax-preview-footer">
                                <span class="pax-preview-badge"><?php esc_html_e( 'Clean', 'pax-support-pro' ); ?></span>
                            </div>
                        </div>

                        <div class="pax-theme-preview pax-theme-dark">
                            <div class="pax-preview-header">
                                <span class="dashicons dashicons-admin-appearance"></span>
                                <h3><?php esc_html_e( 'Dark Mode', 'pax-support-pro' ); ?></h3>
                            </div>
                            <div class="pax-preview-body">
                                <div class="pax-preview-chat">
                                    <div class="pax-preview-message pax-preview-user">
                                        <?php esc_html_e( 'Hello! I need help.', 'pax-support-pro' ); ?>
                                    </div>
                                    <div class="pax-preview-message pax-preview-agent">
                                        <?php esc_html_e( 'How can I assist you?', 'pax-support-pro' ); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="pax-preview-footer">
                                <span class="pax-preview-badge"><?php esc_html_e( 'Elegant', 'pax-support-pro' ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Custom Colors', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <div class="pax-color-grid">
                        <div class="pax-form-group">
                            <label for="pax_neon_primary">
                                <?php esc_html_e( 'Neon Primary Color', 'pax-support-pro' ); ?>
                            </label>
                            <input type="color" 
                                   name="pax_neon_primary" 
                                   id="pax_neon_primary" 
                                   value="<?php echo esc_attr( $theme_settings['neon_primary'] ); ?>" 
                                   class="pax-color-picker">
                        </div>

                        <div class="pax-form-group">
                            <label for="pax_neon_secondary">
                                <?php esc_html_e( 'Neon Secondary Color', 'pax-support-pro' ); ?>
                            </label>
                            <input type="color" 
                                   name="pax_neon_secondary" 
                                   id="pax_neon_secondary" 
                                   value="<?php echo esc_attr( $theme_settings['neon_secondary'] ); ?>" 
                                   class="pax-color-picker">
                        </div>

                        <div class="pax-form-group">
                            <label for="pax_light_primary">
                                <?php esc_html_e( 'Light Primary Color', 'pax-support-pro' ); ?>
                            </label>
                            <input type="color" 
                                   name="pax_light_primary" 
                                   id="pax_light_primary" 
                                   value="<?php echo esc_attr( $theme_settings['light_primary'] ); ?>" 
                                   class="pax-color-picker">
                        </div>

                        <div class="pax-form-group">
                            <label for="pax_dark_primary">
                                <?php esc_html_e( 'Dark Primary Color', 'pax-support-pro' ); ?>
                            </label>
                            <input type="color" 
                                   name="pax_dark_primary" 
                                   id="pax_dark_primary" 
                                   value="<?php echo esc_attr( $theme_settings['dark_primary'] ); ?>" 
                                   class="pax-color-picker">
                        </div>
                    </div>
                </div>
            </div>

            <div class="pax-form-actions">
                <button type="submit" class="button button-primary button-hero">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e( 'Save Changes', 'pax-support-pro' ); ?>
                </button>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Get theme settings
 */
function pax_sup_get_theme_settings() {
    $defaults = array(
        'default_theme' => 'neon',
        'allow_theme_switching' => true,
        'remember_theme_preference' => true,
        'neon_primary' => '#e53935',
        'neon_secondary' => '#2196F3',
        'light_primary' => '#1976D2',
        'dark_primary' => '#BB86FC',
    );

    return wp_parse_args( get_option( 'pax_theme_settings', array() ), $defaults );
}

/**
 * Save theme settings
 */
function pax_sup_save_theme_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings = array(
        'default_theme' => isset( $_POST['pax_default_theme'] ) ? sanitize_text_field( $_POST['pax_default_theme'] ) : 'neon',
        'allow_theme_switching' => isset( $_POST['pax_allow_theme_switching'] ) && $_POST['pax_allow_theme_switching'] === '1',
        'remember_theme_preference' => isset( $_POST['pax_remember_theme_preference'] ) && $_POST['pax_remember_theme_preference'] === '1',
        'neon_primary' => isset( $_POST['pax_neon_primary'] ) ? sanitize_hex_color( $_POST['pax_neon_primary'] ) : '#e53935',
        'neon_secondary' => isset( $_POST['pax_neon_secondary'] ) ? sanitize_hex_color( $_POST['pax_neon_secondary'] ) : '#2196F3',
        'light_primary' => isset( $_POST['pax_light_primary'] ) ? sanitize_hex_color( $_POST['pax_light_primary'] ) : '#1976D2',
        'dark_primary' => isset( $_POST['pax_dark_primary'] ) ? sanitize_hex_color( $_POST['pax_dark_primary'] ) : '#BB86FC',
    );

    update_option( 'pax_theme_settings', $settings );

    add_settings_error(
        'pax_theme',
        'pax_theme_updated',
        __( 'Theme settings updated successfully.', 'pax-support-pro' ),
        'success'
    );
}
