<?php
/**
 * Live Agent File Upload REST API Endpoint
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register file upload endpoint
 */
function pax_sup_register_liveagent_file_routes() {
    register_rest_route( PAX_SUP_REST_NS, '/liveagent/file/upload', array(
        'methods' => 'POST',
        'callback' => 'pax_sup_rest_upload_file',
        'permission_callback' => 'pax_sup_check_file_permission',
    ) );
}
add_action( 'rest_api_init', 'pax_sup_register_liveagent_file_routes' );

/**
 * Upload file
 */
function pax_sup_rest_upload_file( $request ) {
    pax_sup_liveagent_nocache_headers();

    $session_id = $request->get_param( 'session_id' );
    $sender = $request->get_param( 'sender' );

    // Check settings
    $settings = get_option( 'pax_liveagent_settings', array() );
    if ( empty( $settings['allow_file_uploads'] ) ) {
        return new WP_Error( 'uploads_disabled', __( 'File uploads are disabled', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    // Verify session
    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    if ( $session['status'] !== 'active' ) {
        return new WP_Error( 'invalid_status', __( 'Session is not active', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    // Check if file was uploaded
    if ( empty( $_FILES['file'] ) ) {
        return new WP_Error( 'no_file', __( 'No file uploaded', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $file = $_FILES['file'];

    // Validate file
    $validation = pax_sup_validate_upload_file( $file, $settings );
    if ( is_wp_error( $validation ) ) {
        return $validation;
    }

    // Handle upload
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload_overrides = array(
        'test_form' => false,
        'test_type' => true,
    );

    $uploaded = wp_handle_upload( $file, $upload_overrides );

    if ( isset( $uploaded['error'] ) ) {
        return new WP_Error( 'upload_failed', $uploaded['error'], array( 'status' => 500 ) );
    }

    // Create attachment
    $attachment_data = array(
        'post_mime_type' => $uploaded['type'],
        'post_title' => sanitize_file_name( basename( $uploaded['file'] ) ),
        'post_content' => '',
        'post_status' => 'inherit',
    );

    $attachment_id = wp_insert_attachment( $attachment_data, $uploaded['file'] );

    if ( is_wp_error( $attachment_id ) ) {
        return new WP_Error( 'attachment_failed', __( 'Failed to create attachment', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    // Generate metadata
    $attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
    wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

    // Add metadata
    update_post_meta( $attachment_id, 'pax_liveagent_session', $session_id );
    update_post_meta( $attachment_id, 'pax_liveagent_sender', $sender );

    // Get file info
    $file_info = array(
        'id' => $attachment_id,
        'url' => wp_get_attachment_url( $attachment_id ),
        'filename' => basename( $uploaded['file'] ),
        'filesize' => filesize( $uploaded['file'] ),
        'mime_type' => $uploaded['type'],
    );

    return new WP_REST_Response( array(
        'success' => true,
        'attachment' => $file_info,
    ), 201 );
}

/**
 * Validate upload file
 */
function pax_sup_validate_upload_file( $file, $settings ) {
    // Check for upload errors
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'upload_error', __( 'File upload error', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    // Check file size
    $max_size = isset( $settings['max_file_size_mb'] ) ? $settings['max_file_size_mb'] : 10;
    $max_bytes = $max_size * 1024 * 1024;

    if ( $file['size'] > $max_bytes ) {
        return new WP_Error(
            'file_too_large',
            sprintf( __( 'File size exceeds maximum of %d MB', 'pax-support-pro' ), $max_size ),
            array( 'status' => 400 )
        );
    }

    // Check file type
    $allowed_types = isset( $settings['allowed_file_types'] ) ? $settings['allowed_file_types'] : array( 'jpg', 'png', 'gif', 'pdf', 'doc', 'docx' );
    $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

    if ( ! in_array( $file_ext, $allowed_types, true ) ) {
        return new WP_Error(
            'invalid_file_type',
            sprintf( __( 'File type not allowed. Allowed types: %s', 'pax-support-pro' ), implode( ', ', $allowed_types ) ),
            array( 'status' => 400 )
        );
    }

    // Sanitize filename
    $file['name'] = sanitize_file_name( $file['name'] );

    // Check for malicious content (basic check)
    $dangerous_extensions = array( 'php', 'phtml', 'php3', 'php4', 'php5', 'exe', 'bat', 'sh', 'cgi' );
    if ( in_array( $file_ext, $dangerous_extensions, true ) ) {
        return new WP_Error( 'dangerous_file', __( 'File type not allowed for security reasons', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    return true;
}

/**
 * Check file upload permission
 */
function pax_sup_check_file_permission( $request ) {
    if ( current_user_can( 'manage_pax_chats' ) ) {
        return true;
    }

    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return false;
    }

    return pax_sup_is_session_owner( $session_id );
}
