<?php
/**
 * REST endpoints for attachment operations.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register attachment REST routes.
 */
function pax_sup_register_attachment_routes() {
    register_rest_route(
        PAX_SUP_REST_NS,
        '/attachment/(?P<id>\d+)',
        array(
            'methods'             => WP_REST_Server::DELETABLE,
            'permission_callback' => 'pax_sup_attachment_delete_permission',
            'callback'            => 'pax_sup_rest_delete_attachment',
        )
    );

    register_rest_route(
        PAX_SUP_REST_NS,
        '/attachment/(?P<id>\d+)/download',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => 'pax_sup_attachment_download_permission',
            'callback'            => 'pax_sup_rest_download_attachment',
        )
    );
}
add_action( 'rest_api_init', 'pax_sup_register_attachment_routes' );

/**
 * Check if user can delete attachment.
 *
 * @param WP_REST_Request $request Request object.
 * @return bool
 */
function pax_sup_attachment_delete_permission( $request ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $attachment_id = (int) $request['id'];
    global $wpdb;
    $table      = pax_sup_get_attachments_table();
    $attachment = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $attachment_id ),
        ARRAY_A
    );

    if ( ! $attachment ) {
        return false;
    }

    $user_id = get_current_user_id();

    // User can delete their own attachments
    if ( (int) $attachment['user_id'] === $user_id ) {
        return true;
    }

    // Admins can delete any attachment
    return current_user_can( pax_sup_get_console_capability() );
}

/**
 * Check if user can download attachment.
 *
 * @param WP_REST_Request $request Request object.
 * @return bool
 */
function pax_sup_attachment_download_permission( $request ) {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $attachment_id = (int) $request['id'];
    global $wpdb;
    $table      = pax_sup_get_attachments_table();
    $attachment = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $attachment_id ),
        ARRAY_A
    );

    if ( ! $attachment ) {
        return false;
    }

    // Get ticket to check ownership
    $ticket_table = pax_sup_get_ticket_table();
    $ticket       = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$ticket_table} WHERE id = %d", $attachment['ticket_id'] )
    );

    if ( ! $ticket ) {
        return false;
    }

    $user_id = get_current_user_id();

    // User can download attachments from their own tickets
    if ( (int) $ticket->user_id === $user_id ) {
        return true;
    }

    // Admins can download any attachment
    return current_user_can( pax_sup_get_console_capability() );
}

/**
 * Delete attachment endpoint.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function pax_sup_rest_delete_attachment( $request ) {
    $attachment_id = (int) $request['id'];

    $deleted = pax_sup_delete_attachment( $attachment_id );

    if ( ! $deleted ) {
        return new WP_Error(
            'delete_failed',
            __( 'Failed to delete attachment.', 'pax-support-pro' ),
            array( 'status' => 500 )
        );
    }

    return new WP_REST_Response(
        array(
            'ok'      => true,
            'message' => __( 'Attachment deleted successfully.', 'pax-support-pro' ),
        ),
        200
    );
}

/**
 * Download attachment endpoint.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function pax_sup_rest_download_attachment( $request ) {
    $attachment_id = (int) $request['id'];

    global $wpdb;
    $table      = pax_sup_get_attachments_table();
    $attachment = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $attachment_id ),
        ARRAY_A
    );

    if ( ! $attachment ) {
        return new WP_Error(
            'not_found',
            __( 'Attachment not found.', 'pax-support-pro' ),
            array( 'status' => 404 )
        );
    }

    $upload_dir = pax_sup_get_upload_dir();
    $file_path  = $upload_dir . '/' . $attachment['file_path'];

    if ( ! file_exists( $file_path ) ) {
        return new WP_Error(
            'file_not_found',
            __( 'File not found on server.', 'pax-support-pro' ),
            array( 'status' => 404 )
        );
    }

    // Return file info for client-side download
    return new WP_REST_Response(
        array(
            'url'       => pax_sup_get_upload_url() . '/' . $attachment['file_path'],
            'file_name' => $attachment['file_name'],
            'file_type' => $attachment['file_type'],
            'file_size' => (int) $attachment['file_size'],
        ),
        200
    );
}
