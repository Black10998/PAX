<?php
/**
 * Roles & Permissions Admin Page
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Roles & Permissions page
 */
function pax_sup_render_roles_permissions_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pax-support-pro' ) );
    }

    // Handle form submission
    if ( isset( $_POST['pax_roles_nonce'] ) && wp_verify_nonce( $_POST['pax_roles_nonce'], 'pax_roles_save' ) ) {
        pax_sup_save_roles_permissions();
    }

    // Handle reset
    if ( isset( $_POST['pax_roles_reset_nonce'] ) && wp_verify_nonce( $_POST['pax_roles_reset_nonce'], 'pax_roles_reset' ) ) {
        pax_sup_reset_roles_permissions();
    }

    $roles_config = pax_sup_get_roles_config();
    $default_role = get_option( 'pax_default_role', 'subscriber' );

    ?>
    <div class="wrap pax-modern-page">
        <div class="pax-page-header">
            <div class="pax-breadcrumb">
                <span class="dashicons dashicons-admin-home"></span>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pax-support-dashboard' ) ); ?>">
                    <?php esc_html_e( 'Dashboard', 'pax-support-pro' ); ?>
                </a>
                <span class="separator">/</span>
                <span class="current"><?php esc_html_e( 'Roles & Permissions', 'pax-support-pro' ); ?></span>
            </div>
            <h1>
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e( 'Roles & Permissions', 'pax-support-pro' ); ?>
            </h1>
            <p class="description">
                <?php esc_html_e( 'Configure role-based access control for PAX Support Pro features.', 'pax-support-pro' ); ?>
            </p>
        </div>

        <form method="post" action="" class="pax-roles-form">
            <?php wp_nonce_field( 'pax_roles_save', 'pax_roles_nonce' ); ?>

            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Support Roles Configuration', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <table class="pax-roles-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Role', 'pax-support-pro' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'pax-support-pro' ); ?></th>
                                <th><?php esc_html_e( 'Capabilities', 'pax-support-pro' ); ?></th>
                                <th><?php esc_html_e( 'Enabled', 'pax-support-pro' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $available_roles = array(
                                'support_agent' => array(
                                    'label' => __( 'Support Agent', 'pax-support-pro' ),
                                    'description' => __( 'Can view and respond to chats and tickets', 'pax-support-pro' ),
                                    'capabilities' => array(
                                        'read_pax_tickets',
                                        'reply_pax_tickets',
                                        'read_pax_chats',
                                        'reply_pax_chats',
                                    ),
                                ),
                                'support_manager' => array(
                                    'label' => __( 'Support Manager', 'pax-support-pro' ),
                                    'description' => __( 'Can assign tickets, manage callbacks, and view analytics', 'pax-support-pro' ),
                                    'capabilities' => array(
                                        'read_pax_tickets',
                                        'reply_pax_tickets',
                                        'assign_pax_tickets',
                                        'read_pax_chats',
                                        'reply_pax_chats',
                                        'manage_pax_callbacks',
                                        'view_pax_analytics',
                                    ),
                                ),
                                'support_viewer' => array(
                                    'label' => __( 'Support Viewer', 'pax-support-pro' ),
                                    'description' => __( 'Read-only access to support data', 'pax-support-pro' ),
                                    'capabilities' => array(
                                        'read_pax_tickets',
                                        'read_pax_chats',
                                        'view_pax_analytics',
                                    ),
                                ),
                            );

                            foreach ( $available_roles as $role_key => $role_data ) {
                                $enabled = isset( $roles_config[ $role_key ] ) && $roles_config[ $role_key ]['enabled'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $role_data['label'] ); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $role_data['description'] ); ?>
                                    </td>
                                    <td>
                                        <ul class="pax-capabilities-list">
                                            <?php foreach ( $role_data['capabilities'] as $cap ) : ?>
                                                <li><code><?php echo esc_html( $cap ); ?></code></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td>
                                        <label class="pax-toggle">
                                            <input type="checkbox" 
                                                   name="pax_roles[<?php echo esc_attr( $role_key ); ?>][enabled]" 
                                                   value="1" 
                                                   <?php checked( $enabled ); ?>>
                                            <span class="pax-toggle-slider"></span>
                                        </label>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pax-card">
                <div class="pax-card-header">
                    <h2><?php esc_html_e( 'Default Role Settings', 'pax-support-pro' ); ?></h2>
                </div>
                <div class="pax-card-body">
                    <div class="pax-form-group">
                        <label for="pax_default_role">
                            <?php esc_html_e( 'Default Role for New Users', 'pax-support-pro' ); ?>
                        </label>
                        <select name="pax_default_role" id="pax_default_role" class="pax-select">
                            <?php
                            $wp_roles = wp_roles()->get_names();
                            foreach ( $wp_roles as $role_key => $role_name ) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr( $role_key ),
                                    selected( $default_role, $role_key, false ),
                                    esc_html( $role_name )
                                );
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Select the default WordPress role assigned to new registered users.', 'pax-support-pro' ); ?>
                        </p>
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

        <form method="post" action="" class="pax-reset-form">
            <?php wp_nonce_field( 'pax_roles_reset', 'pax_roles_reset_nonce' ); ?>
            <button type="submit" class="button button-secondary" 
                    onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset all role configurations to defaults?', 'pax-support-pro' ) ); ?>');">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Reset to Defaults', 'pax-support-pro' ); ?>
            </button>
        </form>
    </div>
    <?php
}

/**
 * Get roles configuration
 */
function pax_sup_get_roles_config() {
    $default = array(
        'support_agent' => array( 'enabled' => false ),
        'support_manager' => array( 'enabled' => false ),
        'support_viewer' => array( 'enabled' => false ),
    );

    return get_option( 'pax_roles_config', $default );
}

/**
 * Save roles permissions
 */
function pax_sup_save_roles_permissions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $roles_config = isset( $_POST['pax_roles'] ) ? $_POST['pax_roles'] : array();
    $default_role = isset( $_POST['pax_default_role'] ) ? sanitize_text_field( $_POST['pax_default_role'] ) : 'subscriber';

    // Sanitize and save roles config
    $sanitized_config = array();
    foreach ( $roles_config as $role_key => $role_data ) {
        $sanitized_config[ sanitize_key( $role_key ) ] = array(
            'enabled' => isset( $role_data['enabled'] ) && $role_data['enabled'] === '1',
        );
    }

    update_option( 'pax_roles_config', $sanitized_config );
    update_option( 'pax_default_role', $default_role );

    // Apply capabilities to WordPress roles
    pax_sup_apply_role_capabilities( $sanitized_config );

    add_settings_error(
        'pax_roles',
        'pax_roles_updated',
        __( 'Roles and permissions updated successfully.', 'pax-support-pro' ),
        'success'
    );
}

/**
 * Reset roles permissions
 */
function pax_sup_reset_roles_permissions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    delete_option( 'pax_roles_config' );
    delete_option( 'pax_default_role' );

    // Remove all custom capabilities
    pax_sup_remove_all_role_capabilities();

    add_settings_error(
        'pax_roles',
        'pax_roles_reset',
        __( 'Roles and permissions reset to defaults.', 'pax-support-pro' ),
        'success'
    );
}

/**
 * Apply role capabilities to WordPress roles
 */
function pax_sup_apply_role_capabilities( $roles_config ) {
    $role_definitions = array(
        'support_agent' => array(
            'read_pax_tickets',
            'reply_pax_tickets',
            'read_pax_chats',
            'reply_pax_chats',
        ),
        'support_manager' => array(
            'read_pax_tickets',
            'reply_pax_tickets',
            'assign_pax_tickets',
            'read_pax_chats',
            'reply_pax_chats',
            'manage_pax_callbacks',
            'view_pax_analytics',
        ),
        'support_viewer' => array(
            'read_pax_tickets',
            'read_pax_chats',
            'view_pax_analytics',
        ),
    );

    // First, remove all PAX capabilities from all roles
    pax_sup_remove_all_role_capabilities();

    // Then add capabilities for enabled roles
    foreach ( $roles_config as $role_key => $role_data ) {
        if ( ! isset( $role_data['enabled'] ) || ! $role_data['enabled'] ) {
            continue;
        }

        $role = get_role( $role_key );
        
        // Create role if it doesn't exist
        if ( ! $role ) {
            $role = add_role(
                $role_key,
                ucwords( str_replace( '_', ' ', $role_key ) ),
                array( 'read' => true )
            );
        }

        // Add capabilities
        if ( $role && isset( $role_definitions[ $role_key ] ) ) {
            foreach ( $role_definitions[ $role_key ] as $cap ) {
                $role->add_cap( $cap );
            }
        }
    }
}

/**
 * Remove all PAX role capabilities
 */
function pax_sup_remove_all_role_capabilities() {
    $all_caps = array(
        'read_pax_tickets',
        'reply_pax_tickets',
        'assign_pax_tickets',
        'read_pax_chats',
        'reply_pax_chats',
        'manage_pax_callbacks',
        'view_pax_analytics',
    );

    $roles = wp_roles()->get_names();
    foreach ( $roles as $role_key => $role_name ) {
        $role = get_role( $role_key );
        if ( $role ) {
            foreach ( $all_caps as $cap ) {
                $role->remove_cap( $cap );
            }
        }
    }
}
