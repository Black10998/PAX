<?php
/**
 * Installation hooks.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_activate() {
    // Initialize default options
    pax_sup_update_options( array() );
    
    // Create database tables
    pax_sup_ensure_ticket_tables();
    
    // Create live agent table
    require_once PAX_SUP_DIR . 'includes/liveagent-db.php';
    pax_sup_create_liveagent_table();

    // Schedule auto-update checks
    if ( function_exists( 'pax_sup_updater' ) ) {
        pax_sup_updater()->maybe_schedule_checks();
    }

    // Schedule callback reminders
    wp_clear_scheduled_hook( 'pax_sup_schedule_reminders' );
    if ( ! wp_next_scheduled( 'pax_sup_schedule_reminders' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'pax_sup_schedule_reminders' );
    }
    
    // Set welcome notice flag
    if ( ! get_option( 'pax_sup_activation_redirect' ) ) {
        add_option( 'pax_sup_activation_redirect', true );
    }
}

register_activation_hook( PAX_SUP_FILE, 'pax_sup_activate' );

add_action( 'plugins_loaded', 'pax_sup_ensure_ticket_tables' );

function pax_sup_deactivate() {
    wp_clear_scheduled_hook( 'pax_sup_schedule_reminders' );
}

register_deactivation_hook( PAX_SUP_FILE, 'pax_sup_deactivate' );

add_action( 'pax_sup_schedule_reminders', 'pax_sup_handle_schedule_reminders' );