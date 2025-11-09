<?php
/**
 * Live Agent Capabilities
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add Live Agent capabilities on activation
 */
function pax_sup_add_liveagent_capabilities() {
    $capabilities = array(
        'manage_pax_chats',   // Full access to Live Agent Center
        'view_pax_chats',     // Read-only access
        'accept_pax_chats',   // Can accept/decline requests
    );

    // Add to administrator
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        foreach ( $capabilities as $cap ) {
            $admin->add_cap( $cap );
        }
    }

    // Add to Support Manager (if exists from roles-permissions feature)
    $manager = get_role( 'support_manager' );
    if ( $manager ) {
        foreach ( $capabilities as $cap ) {
            $manager->add_cap( $cap );
        }
    }

    // Add to Support Agent (if exists)
    $agent = get_role( 'support_agent' );
    if ( $agent ) {
        $agent->add_cap( 'view_pax_chats' );
        $agent->add_cap( 'accept_pax_chats' );
    }
}
register_activation_hook( PAX_SUP_FILE, 'pax_sup_add_liveagent_capabilities' );

/**
 * Remove Live Agent capabilities on deactivation
 */
function pax_sup_remove_liveagent_capabilities() {
    $capabilities = array(
        'manage_pax_chats',
        'view_pax_chats',
        'accept_pax_chats',
    );

    $roles = wp_roles()->get_names();
    foreach ( $roles as $role_key => $role_name ) {
        $role = get_role( $role_key );
        if ( $role ) {
            foreach ( $capabilities as $cap ) {
                $role->remove_cap( $cap );
            }
        }
    }
}
register_deactivation_hook( PAX_SUP_FILE, 'pax_sup_remove_liveagent_capabilities' );
