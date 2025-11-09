<?php
/**
 * Tickets admin tools.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_render_tickets() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        return;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=pax-support-console' ) );
    exit;
}

add_action( 'admin_post_pax_sup_ticket_action', 'pax_sup_handle_ticket_action' );

function pax_sup_handle_ticket_action() {
    if ( ! current_user_can( pax_sup_get_console_capability() ) ) {
        wp_die( esc_html__( 'You are not allowed to manage tickets.', 'pax-support-pro' ) );
    }

    $ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;
    $action    = isset( $_POST['ticket_action'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_action'] ) ) : '';

    if ( ! $ticket_id || ! $action ) {
        wp_safe_redirect( admin_url( 'admin.php?page=pax-support-console' ) );
        exit;
    }

    check_admin_referer( 'pax_sup_ticket_action_' . $ticket_id );

    pax_sup_ticket_prepare_tables();
    $ticket = pax_sup_ticket_get( $ticket_id );

    if ( ! $ticket ) {
        pax_sup_store_admin_notice( __( 'Ticket not found.', 'pax-support-pro' ), 'error' );
        wp_safe_redirect( admin_url( 'admin.php?page=pax-support-console' ) );
        exit;
    }

    switch ( $action ) {
        case 'reply':
            $message = isset( $_POST['reply_message'] ) ? wp_kses_post( wp_unslash( $_POST['reply_message'] ) ) : '';
            if ( empty( $message ) ) {
                pax_sup_store_admin_notice( __( 'Reply message cannot be empty.', 'pax-support-pro' ), 'error' );
                break;
            }
            pax_sup_ticket_add_message( $ticket_id, 'agent', $message );
            pax_sup_ticket_update_status( $ticket_id, 'answered' );
            pax_sup_store_admin_notice( __( 'Reply posted successfully.', 'pax-support-pro' ) );
            break;
        case 'freeze':
            pax_sup_ticket_update_status( $ticket_id, 'frozen' );
            pax_sup_store_admin_notice( __( 'Ticket frozen.', 'pax-support-pro' ) );
            break;
        case 'unfreeze':
            pax_sup_ticket_update_status( $ticket_id, 'open' );
            pax_sup_store_admin_notice( __( 'Ticket unfrozen.', 'pax-support-pro' ) );
            break;
        case 'delete':
            pax_sup_ticket_delete( $ticket_id );
            pax_sup_set_user_cooldown_until( $ticket->user_id, 0 );
            pax_sup_store_admin_notice( __( 'Ticket deleted.', 'pax-support-pro' ) );
            $ticket_id = 0;
            break;
    }

    $redirect = admin_url( 'admin.php?page=pax-support-console' );
    if ( $ticket_id ) {
        $redirect = add_query_arg( array( 'ticket' => $ticket_id ), $redirect );
    }

    wp_safe_redirect( $redirect );
    exit;
}

