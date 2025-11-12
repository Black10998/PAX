<?php
/**
 * Live Agent Database Schema and Operations
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create live agent sessions table
 */
function pax_sup_create_liveagent_table() {
    global $wpdb;

        $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        agent_id bigint(20) UNSIGNED DEFAULT NULL,
        status enum('pending','active','closed','declined') NOT NULL DEFAULT 'pending',
        started_at datetime NOT NULL,
        ended_at datetime DEFAULT NULL,
        messages longtext DEFAULT NULL,
        attachments_path text DEFAULT NULL,
        last_activity datetime NOT NULL,
        user_name varchar(255) DEFAULT NULL,
        user_email varchar(255) DEFAULT NULL,
        user_ip varchar(100) DEFAULT NULL,
        user_agent varchar(255) DEFAULT NULL,
        page_url text DEFAULT NULL,
        domain varchar(191) DEFAULT NULL,
        auth_plugin varchar(60) DEFAULT 'core',
        source varchar(60) DEFAULT NULL,
        session_notes text DEFAULT NULL,
        rating tinyint(3) UNSIGNED DEFAULT NULL,
        rating_note text DEFAULT NULL,
        rating_stars tinyint(3) UNSIGNED DEFAULT NULL,
        rating_comment text DEFAULT NULL,
        rated_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY agent_id (agent_id),
        KEY status (status),
        KEY last_activity (last_activity)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    maybe_add_column( $table_name, 'rating', "ALTER TABLE $table_name ADD rating TINYINT(3) UNSIGNED DEFAULT NULL AFTER session_notes" );
    maybe_add_column( $table_name, 'rating_note', "ALTER TABLE $table_name ADD rating_note text DEFAULT NULL AFTER rating" );
    maybe_add_column( $table_name, 'rating_stars', "ALTER TABLE $table_name ADD rating_stars TINYINT(3) UNSIGNED DEFAULT NULL AFTER rating_note" );
    maybe_add_column( $table_name, 'rating_comment', "ALTER TABLE $table_name ADD rating_comment text DEFAULT NULL AFTER rating_stars" );
    maybe_add_column( $table_name, 'rated_at', "ALTER TABLE $table_name ADD rated_at datetime DEFAULT NULL AFTER rating_comment" );

    // Ensure status enum includes declined state.
    $status_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM $table_name LIKE %s", 'status' ), ARRAY_A );
    if ( $status_column && isset( $status_column['Type'] ) && false === strpos( $status_column['Type'], 'declined' ) ) {
        $wpdb->query( "ALTER TABLE $table_name MODIFY status ENUM('pending','active','closed','declined') NOT NULL DEFAULT 'pending'" );
    }

    // Verify table was created
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
        error_log( 'PAX Support Pro: Failed to create live agent sessions table' );
        return false;
    }

    return true;
}

/**
 * Create a new live agent session
 */
function pax_sup_create_liveagent_session( $user_id, $args = array() ) {
    global $wpdb;

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return false;
    }

    // Get user IP (Cloudflare compatible)
    $user_ip = pax_sup_get_client_ip();

    $args     = is_array( $args ) ? $args : array();
    $page_url = ! empty( $args['page_url'] ) ? esc_url_raw( $args['page_url'] ) : '';
    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
    $domain     = wp_parse_url( home_url(), PHP_URL_HOST );

    $data = array(
        'user_id'       => $user_id,
        'status'        => 'pending',
        'started_at'    => current_time( 'mysql' ),
        'last_activity' => current_time( 'mysql' ),
        'user_name'     => $user->display_name,
        'user_email'    => $user->user_email,
        'user_ip'       => $user_ip,
        'user_agent'    => $user_agent,
        'page_url'      => $page_url,
        'domain'        => sanitize_text_field( $domain ),
        'auth_plugin'   => 'core',
        'source'        => isset( $args['source'] ) ? sanitize_text_field( $args['source'] ) : '',
        'messages'      => wp_json_encode( array() ),
    );

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    $inserted = $wpdb->insert( $table_name, $data );

    if ( $inserted ) {
        return $wpdb->insert_id;
    }

    return false;
}

/**
 * Normalize live agent messages array ensuring sequence ordering.
 *
 * @param array $messages Message array.
 * @return array
 */
function pax_sup_normalize_liveagent_messages( $messages ) {
    if ( ! is_array( $messages ) ) {
        return array();
    }

    $normalized = array();
    $max_seq    = 0;

    foreach ( $messages as $message ) {
        $seq_candidate = isset( $message['seq'] ) ? (int) $message['seq'] : 0;

        if ( $seq_candidate <= $max_seq ) {
            $seq_candidate = $max_seq + 1;
        }

        if ( $seq_candidate <= 0 ) {
            $seq_candidate = $max_seq + 1;
        }

        $max_seq = max( $max_seq, $seq_candidate );

        $message['seq'] = $seq_candidate;
        $message['id']  = $seq_candidate;

        if ( empty( $message['timestamp'] ) ) {
            $message['timestamp'] = current_time( 'mysql' );
        }

        if ( empty( $message['created_at'] ) ) {
            $message['created_at'] = $message['timestamp'];
        }

        if ( empty( $message['role'] ) && isset( $message['sender'] ) ) {
            $message['role'] = ( 'agent' === $message['sender'] ) ? 'admin' : 'user';
        }

        if ( ! isset( $message['sender'] ) && isset( $message['role'] ) ) {
            $message['sender'] = ( 'admin' === $message['role'] ) ? 'agent' : 'user';
        }

        $message['read'] = ! empty( $message['read'] );

        $normalized[] = $message;
    }

    return $normalized;
}

/**
 * Create a live agent session from payload data.
 *
 * @param array $payload Session data.
 * @return int|false
 */
function pax_live_agent_session_create( $payload ) {
    global $wpdb;

    $defaults = array(
        'status'      => 'pending',
        'user_id'     => 0,
        'user_name'   => '',
        'user_email'  => '',
        'user_ip'     => '',
        'user_agent'  => '',
        'page_url'    => '',
        'domain'      => '',
        'auth_plugin' => 'core',
        'source'      => '',
        'messages'    => array(),
    );

    $data = wp_parse_args( $payload, $defaults );

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';

    $prepared = array(
        'status'        => sanitize_key( $data['status'] ),
        'user_id'       => $data['user_id'] ? (int) $data['user_id'] : 0,
        'agent_id'      => isset( $data['agent_id'] ) ? (int) $data['agent_id'] : null,
        'started_at'    => current_time( 'mysql' ),
        'last_activity' => current_time( 'mysql' ),
        'user_name'     => sanitize_text_field( $data['user_name'] ),
        'user_email'    => sanitize_email( $data['user_email'] ),
        'user_ip'       => sanitize_text_field( $data['user_ip'] ),
        'user_agent'    => substr( sanitize_text_field( $data['user_agent'] ), 0, 255 ),
        'page_url'      => $data['page_url'] ? esc_url_raw( $data['page_url'] ) : '',
        'domain'        => sanitize_text_field( $data['domain'] ),
        'auth_plugin'   => sanitize_key( $data['auth_plugin'] ),
        'source'        => sanitize_text_field( $data['source'] ),
        'messages'      => wp_json_encode( is_array( $data['messages'] ) ? $data['messages'] : array() ),
    );

    $formats = array(
        '%s', // status
        '%d', // user_id
        '%d', // agent_id
        '%s', // started_at
        '%s', // last_activity
        '%s', // user_name
        '%s', // user_email
        '%s', // user_ip
        '%s', // user_agent
        '%s', // page_url
        '%s', // domain
        '%s', // auth_plugin
        '%s', // source
        '%s', // messages
    );

    if ( null === $prepared['agent_id'] ) {
        unset( $prepared['agent_id'] );
        unset( $formats[2] );
    }

    $inserted = $wpdb->insert( $table_name, $prepared, array_values( $formats ) );

    if ( false === $inserted ) {
        return false;
    }

    return (int) $wpdb->insert_id;
}

/**
 * Get live agent session by ID
 */
function pax_sup_get_liveagent_session( $session_id ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    $session = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $session_id
        ),
        ARRAY_A
    );

    if ( $session ) {
        if ( ! empty( $session['messages'] ) ) {
            $decoded = json_decode( $session['messages'], true );
            $session['messages'] = pax_sup_normalize_liveagent_messages( $decoded );
        } else {
            $session['messages'] = array();
        }
    }

    return $session;
}

/**
 * Get all sessions by status
 */
function pax_sup_get_liveagent_sessions_by_status( $status = 'pending' ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    if ( 'active' === $status ) {
        $statuses = array( 'active', 'accepted' );
        $placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE status IN ($placeholders) ORDER BY last_activity DESC",
            ...$statuses
        );
        $sessions = $wpdb->get_results( $query, ARRAY_A );
    } else {
        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE status = %s ORDER BY last_activity DESC",
                $status
            ),
            ARRAY_A
        );
    }

    foreach ( $sessions as &$session ) {
        if ( ! empty( $session['messages'] ) ) {
            $session['messages'] = json_decode( $session['messages'], true );
            if ( ! is_array( $session['messages'] ) ) {
                $session['messages'] = array();
            }
        }
    }

    return $sessions;
}

/**
 * Get recently closed sessions.
 *
 * @param int $limit Number of sessions to fetch.
 * @return array
 */
function pax_sup_get_recent_liveagent_sessions( $limit = 20 ) {
    global $wpdb;

    $limit = max( 1, min( 100, intval( $limit ) ) );
    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';

    $sessions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE status IN ('closed','declined') ORDER BY last_activity DESC LIMIT %d",
            $limit
        ),
        ARRAY_A
    );

    foreach ( $sessions as &$session ) {
        if ( ! empty( $session['messages'] ) ) {
            $session['messages'] = json_decode( $session['messages'], true );
            if ( ! is_array( $session['messages'] ) ) {
                $session['messages'] = array();
            }
        }
    }

    return $sessions;
}

/**
 * Update session status
 */
function pax_sup_update_liveagent_session_status( $session_id, $status, $agent_id = null ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    $data = array(
        'status' => $status,
        'last_activity' => current_time( 'mysql' ),
    );

    $format = array( '%s', '%s' );

    if ( $agent_id !== null ) {
        $data['agent_id'] = $agent_id;
        $format[] = '%d';
    }

    if ( in_array( $status, array( 'closed', 'declined' ), true ) ) {
        $data['ended_at'] = current_time( 'mysql' );
        $format[] = '%s';
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( 
            '[PAX Live Chat] Updating session %d: status=%s, agent_id=%s', 
            $session_id, 
            $status, 
            $agent_id !== null ? $agent_id : 'null' 
        ) );
    }

    $result = $wpdb->update(
        $table_name,
        $data,
        array( 'id' => $session_id ),
        $format,
        array( '%d' )
    );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        if ( $result === false ) {
            error_log( sprintf( '[PAX Live Chat] DB update failed: %s', $wpdb->last_error ) );
        } else {
            error_log( sprintf( '[PAX Live Chat] DB update result: %d rows affected', $result ) );
        }
    }

    return $result;
}

/**
 * Add message to session
 */
function pax_sup_add_liveagent_message( $session_id, $message_data ) {
    global $wpdb;

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return false;
    }

    $messages = is_array( $session['messages'] ) ? $session['messages'] : array();
    $max_seq  = 0;

    foreach ( $messages as $existing ) {
        $max_seq = max( $max_seq, (int) ( $existing['seq'] ?? 0 ) );
    }

    $timestamp = current_time( 'mysql' );
    $next_seq  = $max_seq + 1;

    if ( empty( $message_data['role'] ) ) {
        $message_data['role'] = ( isset( $message_data['sender'] ) && 'agent' === $message_data['sender'] ) ? 'admin' : 'user';
    }

    $message_data['seq']        = $next_seq;
    $message_data['id']         = $next_seq;
    $message_data['timestamp']  = $timestamp;
    $message_data['created_at'] = $timestamp;
    $message_data['read']       = false;

    $messages[] = $message_data;

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    return $wpdb->update(
        $table_name,
        array(
            'messages' => wp_json_encode( $messages ),
            'last_activity' => current_time( 'mysql' ),
        ),
        array( 'id' => $session_id ),
        array( '%s', '%s' ),
        array( '%d' )
    );
}

/**
 * Mark messages as read
 */
function pax_sup_mark_liveagent_messages_read( $session_id, $reader_type ) {
    global $wpdb;

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return false;
    }

    $messages = pax_sup_normalize_liveagent_messages( $session['messages'] );
    if ( ! is_array( $messages ) ) {
        return false;
    }

    $updated = false;
    foreach ( $messages as &$message ) {
        // Mark messages from the opposite sender as read
        if ( $reader_type === 'agent' && $message['sender'] === 'user' && ! $message['read'] ) {
            $message['read'] = true;
            $updated = true;
        } elseif ( $reader_type === 'user' && $message['sender'] === 'agent' && ! $message['read'] ) {
            $message['read'] = true;
            $updated = true;
        }
    }

    if ( $updated ) {
        $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
        return $wpdb->update(
            $table_name,
            array( 'messages' => wp_json_encode( $messages ) ),
            array( 'id' => $session_id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    return false;
}

/**
 * Get active session for user
 */
function pax_sup_get_user_active_session( $user_id ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    $session = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND status IN ('pending', 'active') 
            ORDER BY last_activity DESC 
            LIMIT 1",
            $user_id
        ),
        ARRAY_A
    );

    if ( $session ) {
        if ( ! empty( $session['messages'] ) ) {
            $decoded = json_decode( $session['messages'], true );
            $session['messages'] = pax_sup_normalize_liveagent_messages( $decoded );
        } else {
            $session['messages'] = array();
        }
    }

    return $session;
}

/**
 * Delete old closed sessions
 */
function pax_sup_cleanup_old_liveagent_sessions( $days = 30 ) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    $date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

    return $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name WHERE status = 'closed' AND ended_at < %s",
            $date
        )
    );
}

/**
 * Get client IP (Cloudflare compatible)
 */
function pax_sup_get_client_ip() {
    // Check for Cloudflare
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
    }

    // Check for other proxies
    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        return trim( $ip_list[0] );
    }

    if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
        return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
    }

    // Fallback to REMOTE_ADDR
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }

    return '0.0.0.0';
}

/**
 * Export session to JSON
 */
function pax_sup_export_liveagent_session( $session_id ) {
    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return false;
    }

    $export_data = array(
        'session_id' => $session['id'],
        'user' => array(
            'id' => $session['user_id'],
            'name' => $session['user_name'],
            'email' => $session['user_email'],
        ),
        'agent_id' => $session['agent_id'],
        'status' => $session['status'],
        'started_at' => $session['started_at'],
        'ended_at' => $session['ended_at'],
        'messages' => $session['messages'],
        'notes' => $session['session_notes'],
    );

    return wp_json_encode( $export_data, JSON_PRETTY_PRINT );
}

/**
 * Convert session to ticket
 */
function pax_sup_convert_liveagent_to_ticket( $session_id ) {
    global $wpdb;

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return false;
    }

    // Build ticket content from messages
    $content = "Live Agent Chat Transcript\n\n";
    $content .= "Session ID: {$session['id']}\n";
    $content .= "User: {$session['user_name']} ({$session['user_email']})\n";
    $content .= "Started: {$session['started_at']}\n";
    $content .= "Ended: {$session['ended_at']}\n\n";
    $content .= "Messages:\n" . str_repeat( '-', 50 ) . "\n\n";

    foreach ( $session['messages'] as $message ) {
        $sender = $message['sender'] === 'user' ? $session['user_name'] : 'Agent';
        $content .= "[{$message['timestamp']}] {$sender}:\n";
        $content .= $message['message'] . "\n\n";
    }

    // Create ticket
    $tickets_table = $wpdb->prefix . 'pax_tickets';
    $ticket_data = array(
        'user_id' => $session['user_id'],
        'subject' => 'Live Chat Session #' . $session['id'],
        'message' => $content,
        'status' => 'open',
        'priority' => 'normal',
        'created_at' => current_time( 'mysql' ),
    );

    $inserted = $wpdb->insert( $tickets_table, $ticket_data );

    if ( $inserted ) {
        return $wpdb->insert_id;
    }

    return false;
}

/**
 * Auto-close inactive sessions
 */
function pax_sup_auto_close_inactive_sessions() {
    global $wpdb;

    $settings = get_option( 'pax_liveagent_settings', array() );
    $timeout = isset( $settings['auto_close_minutes'] ) ? (int) $settings['auto_close_minutes'] : 30;

    if ( $timeout <= 0 ) {
        return 0;
    }

    $table_name = $wpdb->prefix . 'pax_liveagent_sessions';
    $cutoff_time = date( 'Y-m-d H:i:s', strtotime( "-{$timeout} minutes" ) );

    return $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table_name 
            SET status = 'closed', ended_at = NOW() 
            WHERE status IN ('pending', 'active') 
            AND last_activity < %s",
            $cutoff_time
        )
    );
}

// Schedule auto-close cron job
add_action( 'pax_liveagent_auto_close', 'pax_sup_auto_close_inactive_sessions' );

if ( ! wp_next_scheduled( 'pax_liveagent_auto_close' ) ) {
    wp_schedule_event( time(), 'hourly', 'pax_liveagent_auto_close' );
}
