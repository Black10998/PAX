<?php
/**
 * File attachment handling for PAX Support Pro.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get allowed file types for attachments.
 *
 * @return array
 */
function pax_sup_get_allowed_file_types() {
    return apply_filters(
        'pax_sup_allowed_file_types',
        array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt'  => 'text/plain',
            'zip'  => 'application/zip',
        )
    );
}

/**
 * Get maximum file size in bytes.
 *
 * @return int
 */
function pax_sup_get_max_file_size() {
    return apply_filters( 'pax_sup_max_file_size', 5 * MB_IN_BYTES );
}

/**
 * Get upload directory for attachments.
 *
 * @return string
 */
function pax_sup_get_upload_dir() {
    $upload_dir = wp_upload_dir();
    $pax_dir    = trailingslashit( $upload_dir['basedir'] ) . 'pax-support-pro/attachments';

    if ( ! file_exists( $pax_dir ) ) {
        wp_mkdir_p( $pax_dir );
        
        // Create .htaccess for security
        $htaccess_file = $pax_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|txt|zip)$\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            file_put_contents( $htaccess_file, $htaccess_content );
        }

        // Create index.php for security
        $index_file = $pax_dir . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden.' );
        }
    }

    return $pax_dir;
}

/**
 * Get upload URL for attachments.
 *
 * @return string
 */
function pax_sup_get_upload_url() {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['baseurl'] ) . 'pax-support-pro/attachments';
}

/**
 * Validate uploaded file.
 *
 * @param array $file File array from $_FILES.
 * @return array|WP_Error Array with file info or WP_Error on failure.
 */
function pax_sup_validate_file( $file ) {
    if ( empty( $file ) || ! isset( $file['name'] ) ) {
        return new WP_Error( 'no_file', __( 'No file provided.', 'pax-support-pro' ) );
    }

    if ( ! empty( $file['error'] ) ) {
        return new WP_Error( 'upload_error', __( 'File upload error.', 'pax-support-pro' ) );
    }

    $file_size = isset( $file['size'] ) ? (int) $file['size'] : 0;
    $max_size  = pax_sup_get_max_file_size();

    if ( $file_size > $max_size ) {
        return new WP_Error(
            'file_too_large',
            sprintf(
                /* translators: %s: maximum file size in MB */
                __( 'File size exceeds maximum allowed size of %s MB.', 'pax-support-pro' ),
                number_format( $max_size / MB_IN_BYTES, 1 )
            )
        );
    }

    if ( $file_size <= 0 ) {
        return new WP_Error( 'empty_file', __( 'File is empty.', 'pax-support-pro' ) );
    }

    $file_name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
    $file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
    $allowed   = pax_sup_get_allowed_file_types();

    if ( ! isset( $allowed[ $file_ext ] ) ) {
        return new WP_Error(
            'invalid_file_type',
            sprintf(
                /* translators: %s: comma-separated list of allowed extensions */
                __( 'File type not allowed. Allowed types: %s', 'pax-support-pro' ),
                implode( ', ', array_keys( $allowed ) )
            )
        );
    }

    $file_type = isset( $file['type'] ) ? $file['type'] : '';
    if ( $file_type && $file_type !== $allowed[ $file_ext ] ) {
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        if ( $finfo ) {
            $detected_type = finfo_file( $finfo, $file['tmp_name'] );
            finfo_close( $finfo );
            
            if ( $detected_type && $detected_type !== $allowed[ $file_ext ] ) {
                return new WP_Error( 'mime_mismatch', __( 'File type does not match extension.', 'pax-support-pro' ) );
            }
        }
    }

    return array(
        'name' => $file_name,
        'ext'  => $file_ext,
        'size' => $file_size,
        'type' => $allowed[ $file_ext ],
    );
}

/**
 * Handle file upload using WordPress functions.
 *
 * @param array $file File array from $_FILES.
 * @param int   $ticket_id Ticket ID.
 * @param int   $user_id User ID.
 * @return array|WP_Error Array with file info or WP_Error on failure.
 */
function pax_sup_handle_file_upload( $file, $ticket_id, $user_id ) {
    $validation = pax_sup_validate_file( $file );
    
    if ( is_wp_error( $validation ) ) {
        return $validation;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $upload_dir = pax_sup_get_upload_dir();
    
    // Generate unique filename
    $file_name = $validation['name'];
    $file_ext  = $validation['ext'];
    $base_name = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
    $timestamp = time();
    $unique_name = $base_name . '-' . $timestamp . '-' . wp_generate_password( 8, false ) . '.' . $file_ext;
    
    $upload_overrides = array(
        'test_form' => false,
        'mimes'     => pax_sup_get_allowed_file_types(),
    );

    // Temporarily override upload directory
    add_filter( 'upload_dir', 'pax_sup_custom_upload_dir' );
    
    $uploaded_file = wp_handle_upload( $file, $upload_overrides );
    
    remove_filter( 'upload_dir', 'pax_sup_custom_upload_dir' );

    if ( isset( $uploaded_file['error'] ) ) {
        return new WP_Error( 'upload_failed', $uploaded_file['error'] );
    }

    if ( ! isset( $uploaded_file['file'] ) || ! file_exists( $uploaded_file['file'] ) ) {
        return new WP_Error( 'upload_failed', __( 'File upload failed.', 'pax-support-pro' ) );
    }

    // Rename to unique name
    $new_path = $upload_dir . '/' . $unique_name;
    if ( ! rename( $uploaded_file['file'], $new_path ) ) {
        @unlink( $uploaded_file['file'] );
        return new WP_Error( 'rename_failed', __( 'Failed to process uploaded file.', 'pax-support-pro' ) );
    }

    return array(
        'file_name' => $file_name,
        'file_path' => $unique_name,
        'file_type' => $validation['type'],
        'file_size' => $validation['size'],
        'full_path' => $new_path,
        'url'       => pax_sup_get_upload_url() . '/' . $unique_name,
    );
}

/**
 * Custom upload directory filter.
 *
 * @param array $dirs Upload directory array.
 * @return array
 */
function pax_sup_custom_upload_dir( $dirs ) {
    $custom_dir = 'pax-support-pro/attachments';
    
    $dirs['path']   = $dirs['basedir'] . '/' . $custom_dir;
    $dirs['url']    = $dirs['baseurl'] . '/' . $custom_dir;
    $dirs['subdir'] = '/' . $custom_dir;
    
    return $dirs;
}

/**
 * Save attachment to database.
 *
 * @param int   $ticket_id Ticket ID.
 * @param int   $message_id Message ID.
 * @param int   $user_id User ID.
 * @param array $file_info File information.
 * @return int|false Attachment ID or false on failure.
 */
function pax_sup_save_attachment( $ticket_id, $message_id, $user_id, $file_info ) {
    global $wpdb;
    
    $table = pax_sup_get_attachments_table();
    
    $inserted = $wpdb->insert(
        $table,
        array(
            'ticket_id'  => (int) $ticket_id,
            'message_id' => (int) $message_id,
            'user_id'    => (int) $user_id,
            'file_name'  => $file_info['file_name'],
            'file_path'  => $file_info['file_path'],
            'file_type'  => $file_info['file_type'],
            'file_size'  => (int) $file_info['file_size'],
            'created_at' => current_time( 'mysql' ),
        ),
        array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
    );
    
    if ( false === $inserted ) {
        return false;
    }
    
    return (int) $wpdb->insert_id;
}

/**
 * Get attachments for a ticket.
 *
 * @param int $ticket_id Ticket ID.
 * @return array
 */
function pax_sup_get_ticket_attachments( $ticket_id ) {
    global $wpdb;
    
    $table = pax_sup_get_attachments_table();
    
    $attachments = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY created_at ASC",
            $ticket_id
        ),
        ARRAY_A
    );
    
    $upload_url = pax_sup_get_upload_url();
    
    foreach ( $attachments as &$attachment ) {
        $attachment['url'] = $upload_url . '/' . $attachment['file_path'];
        $attachment['is_image'] = in_array(
            $attachment['file_type'],
            array( 'image/jpeg', 'image/png', 'image/gif' ),
            true
        );
    }
    
    return $attachments;
}

/**
 * Get attachments for a message.
 *
 * @param int $message_id Message ID.
 * @return array
 */
function pax_sup_get_message_attachments( $message_id ) {
    global $wpdb;
    
    $table = pax_sup_get_attachments_table();
    
    $attachments = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE message_id = %d ORDER BY created_at ASC",
            $message_id
        ),
        ARRAY_A
    );
    
    $upload_url = pax_sup_get_upload_url();
    
    foreach ( $attachments as &$attachment ) {
        $attachment['url'] = $upload_url . '/' . $attachment['file_path'];
        $attachment['is_image'] = in_array(
            $attachment['file_type'],
            array( 'image/jpeg', 'image/png', 'image/gif' ),
            true
        );
    }
    
    return $attachments;
}

/**
 * Delete attachment file and database record.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function pax_sup_delete_attachment( $attachment_id ) {
    global $wpdb;
    
    $table      = pax_sup_get_attachments_table();
    $attachment = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $attachment_id ),
        ARRAY_A
    );
    
    if ( ! $attachment ) {
        return false;
    }
    
    $upload_dir = pax_sup_get_upload_dir();
    $file_path  = $upload_dir . '/' . $attachment['file_path'];
    
    if ( file_exists( $file_path ) ) {
        @unlink( $file_path );
    }
    
    $deleted = $wpdb->delete(
        $table,
        array( 'id' => $attachment_id ),
        array( '%d' )
    );
    
    return false !== $deleted;
}

/**
 * Format file size for display.
 *
 * @param int $bytes File size in bytes.
 * @return string
 */
function pax_sup_format_file_size( $bytes ) {
    $bytes = (int) $bytes;
    
    if ( $bytes >= 1073741824 ) {
        return number_format( $bytes / 1073741824, 2 ) . ' GB';
    } elseif ( $bytes >= 1048576 ) {
        return number_format( $bytes / 1048576, 2 ) . ' MB';
    } elseif ( $bytes >= 1024 ) {
        return number_format( $bytes / 1024, 2 ) . ' KB';
    }
    
    return $bytes . ' bytes';
}
