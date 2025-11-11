<?php
/**
 * Live Agent REST API Endpoints
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'pax_register_live_agent_routes' );

function pax_register_live_agent_routes() {
    register_rest_route( 'pax/v1', '/live/start', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_start',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'pax/v1', '/live/session', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_create_session',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'pax/v1', '/live/status', array(
        'methods'             => 'GET',
        'callback'            => 'pax_live_agent_status',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'pax/v1', '/live/accept', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_accept',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' );
        },
    ) );

    register_rest_route( 'pax/v1', '/live/decline', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_decline',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' );
        },
    ) );

    register_rest_route( 'pax/v1', '/live/message', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_message',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'pax/v1', '/live/messages', array(
        'methods'             => 'GET',
        'callback'            => 'pax_live_agent_get_messages',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'pax/v1', '/live/sessions', array(
        'methods'             => 'GET',
        'callback'            => 'pax_live_agent_list_sessions',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' );
        },
    ) );

    register_rest_route( 'pax/v1', '/live/session/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'pax_live_agent_get_session',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' );
        },
    ) );

    register_rest_route( 'pax/v1', '/live/close', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_close',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' );
        },
    ) );
}

function pax_live_agent_start( $request ) {
    global $wpdb;
    
    $user_meta     = $request->get_param( 'user_meta' ) ?: array();
    $current_user  = wp_get_current_user();
    $has_wp_user   = ( $current_user instanceof WP_User ) && $current_user->exists();
    $resolved_name = $has_wp_user ? $current_user->display_name : sanitize_text_field( $user_meta['name'] ?? 'Guest' );
    $resolved_email = $has_wp_user ? $current_user->user_email : sanitize_email( $user_meta['email'] ?? '' );

    $page_url = $request->get_param( 'page_url' );
    $session_data = array(
        'user_id'       => $has_wp_user ? (int) $current_user->ID : (int) get_current_user_id(),
        'status'        => 'pending',
        'started_at'    => current_time( 'mysql' ),
        'user_name'     => $resolved_name ? sanitize_text_field( $resolved_name ) : 'Guest',
        'user_email'    => $resolved_email,
        'user_ip'       => pax_sup_get_client_ip(),
        'page_url'      => $page_url ? esc_url_raw( $page_url ) : '',
        'messages'      => wp_json_encode( array() ),
        'live_agent'    => 1,
        'last_activity' => current_time( 'mysql' ),
    );
    
    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    $inserted = $wpdb->insert( $table, $session_data );
    
    if ( ! $inserted ) {
        return new WP_Error( 'db_error', 'Failed to create session', array( 'status' => 500 ) );
    }
    
    $session_id = $wpdb->insert_id;
    
    pax_notify_admin_live_agent_request( $session_id, $session_data );
    
    return rest_ensure_response( array(
        'session_id' => $session_id,
        'status'     => 'pending',
        'rest_base'  => rest_url( 'pax/v1/' ),
    ) );
}

function pax_live_agent_create_session( $request ) {
    return pax_live_agent_start( $request );
}

function pax_live_agent_status( $request ) {
    global $wpdb;

    if ( $request->get_param( 'healthcheck' ) ) {
        return rest_ensure_response( array(
            'status'    => 'ok',
            'timestamp' => current_time( 'mysql' ),
        ) );
    }

    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', 'Session ID required', array( 'status' => 400 ) );
    }

    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    $session = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $session_id
        ),
        ARRAY_A
    );

    if ( ! $session ) {
        return new WP_Error( 'not_found', 'Session not found', array( 'status' => 404 ) );
    }

    $status = $session['status'];
    if ( 'accepted' === $status ) {
        $status = 'active';
    }

    $response_status = $status;
    if ( 'active' === $status ) {
        $response_status = 'accepted';
    } elseif ( 'closed' === $status ) {
        $response_status = $session['agent_id'] ? 'closed' : 'declined';
    }

    $response = array(
        'status' => $response_status,
    );

    if ( $session['agent_id'] ) {
        $agent = get_userdata( $session['agent_id'] );
        if ( $agent ) {
            $response['agent'] = array(
                'id'   => $agent->ID,
                'name' => $agent->display_name,
            );
        }
    }

    return rest_ensure_response( $response );
}

function pax_live_agent_accept( $request ) {
    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }
    
    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', 'Session ID required', array( 'status' => 400 ) );
    }
    
    $agent_id = get_current_user_id();

    $updated = pax_sup_update_liveagent_session_status( $session_id, 'active', $agent_id );
    
    if ( false === $updated ) {
        return new WP_Error( 'db_error', 'Failed to accept session', array( 'status' => 500 ) );
    }
    
    return rest_ensure_response( array(
        'success' => true,
        'status'  => 'accepted',
    ) );
}

function pax_live_agent_decline( $request ) {
    global $wpdb;
    
    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }
    
    $session_id = $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', 'Session ID required', array( 'status' => 400 ) );
    }
    
    $table = $wpdb->prefix . 'pax_liveagent_sessions';
    
    $updated = $wpdb->update(
        $table,
        array(
            'status'        => 'closed',
            'last_activity' => current_time( 'mysql' ),
            'ended_at'      => current_time( 'mysql' ),
        ),
        array( 'id' => $session_id ),
        array( '%s', '%s', '%s' ),
        array( '%d' )
    );
    
    if ( $updated === false ) {
        return new WP_Error( 'db_error', 'Failed to decline session', array( 'status' => 500 ) );
    }
    
    return rest_ensure_response( array(
        'success' => true,
        'status'  => 'declined',
    ) );
}

function pax_live_agent_message( $request ) {
    $session_id     = (int) $request->get_param( 'session_id' );
    $raw_message    = $request->get_param( 'message' );
    $reply_to       = $request->get_param( 'reply_to' );
    $attachment_id  = $request->get_param( 'attachment_id' );

    if ( ! $session_id || ( '' === trim( (string) $raw_message ) && empty( $attachment_id ) ) ) {
        return new WP_Error( 'missing_param', 'Session ID and message required', array( 'status' => 400 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', 'Session not found', array( 'status' => 404 ) );
    }

    $current_status = $session['status'];
    if ( 'accepted' === $current_status ) {
        $current_status = 'active';
    }

    $current_user_id = get_current_user_id();
    $is_agent        = current_user_can( 'manage_pax_chats' );
    $sender          = $is_agent ? 'agent' : 'user';

    if ( $is_agent ) {
        if ( empty( $session['agent_id'] ) ) {
            pax_sup_update_liveagent_session_status( $session_id, 'active', $current_user_id );
            $session['agent_id'] = $current_user_id;
            $current_status      = 'active';
        } elseif ( (int) $session['agent_id'] !== $current_user_id ) {
            return new WP_Error( 'not_assigned', __( 'This session is assigned to another agent.', 'pax-support-pro' ), array( 'status' => 403 ) );
        }

        if ( 'active' !== $current_status ) {
            return new WP_Error( 'invalid_status', __( 'Session is not active.', 'pax-support-pro' ), array( 'status' => 400 ) );
        }
    } else {
        if ( 'closed' === $current_status ) {
            return new WP_Error( 'invalid_status', __( 'Session is closed.', 'pax-support-pro' ), array( 'status' => 400 ) );
        }
    }

    $message_text = sanitize_textarea_field( $raw_message );
    if ( '' === $message_text && empty( $attachment_id ) ) {
        return new WP_Error( 'invalid_message', __( 'Message cannot be empty.', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $message_data = array(
        'sender'  => $sender,
        'message' => $message_text,
    );

    if ( $reply_to ) {
        $message_data['reply_to'] = sanitize_text_field( $reply_to );
    }

    if ( $attachment_id ) {
        $attachment_id = (int) $attachment_id;
        $attachment    = get_post( $attachment_id );

        if ( $attachment && 'attachment' === $attachment->post_type ) {
            $message_data['attachment'] = array(
                'id'       => $attachment_id,
                'url'      => wp_get_attachment_url( $attachment_id ),
                'filename' => basename( get_attached_file( $attachment_id ) ),
            );
        }
    }

    if ( 'agent' === $sender ) {
        $message_data['agent_id'] = $current_user_id;
    } elseif ( ! empty( $session['user_id'] ) ) {
        $message_data['user_id'] = (int) $session['user_id'];
    }

    $added = pax_sup_add_liveagent_message( $session_id, $message_data );

    if ( ! $added ) {
        return new WP_Error( 'db_error', __( 'Failed to save message.', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    $updated_session = pax_sup_get_liveagent_session( $session_id );
    $messages        = $updated_session['messages'];
    $new_message     = end( $messages );

    pax_sup_notify_new_message( $session_id, $new_message, $sender );

    return rest_ensure_response( array(
        'success' => true,
        'message' => $new_message,
    ) );
}

function pax_live_agent_get_messages( $request ) {
    $session_id = (int) $request->get_param( 'session_id' );

    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', __( 'Session ID required.', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found.', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    if ( ! pax_live_agent_user_can_access_session( $session ) ) {
        return new WP_Error( 'forbidden', __( 'You do not have permission to access this session.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    $messages = is_array( $session['messages'] ) ? $session['messages'] : array();
    $after    = $request->get_param( 'after' );

    if ( $after ) {
        $start_index = 0;
        foreach ( $messages as $index => $message ) {
            if ( isset( $message['id'] ) && $message['id'] === $after ) {
                $start_index = $index + 1;
                break;
            }
        }
        $messages = array_slice( $messages, $start_index );
    }

    $session_messages = array();
    if ( isset( $session['messages'] ) && is_array( $session['messages'] ) ) {
        $session_messages = $session['messages'];
    }

    $last_message = end( $session_messages );
    if ( ! empty( $session_messages ) ) {
        reset( $session_messages );
    }

    return rest_ensure_response( array(
        'success'   => true,
        'messages'  => array_values( $messages ),
        'total'     => isset( $session['messages'] ) && is_array( $session['messages'] ) ? count( $session['messages'] ) : 0,
        'last_id'   => $last_message['id'] ?? null,
        'session'   => pax_live_agent_prepare_session_summary( $session ),
        'typing'    => pax_live_agent_get_typing_state( $session_id ),
        'timestamp' => current_time( 'mysql' ),
    ) );
}

function pax_live_agent_list_sessions( $request ) {
    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    $limit        = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 20;
    $recent_limit = $request->get_param( 'recent_limit' ) ? intval( $request->get_param( 'recent_limit' ) ) : 10;
    $limit        = max( 1, min( 100, $limit ) );
    $recent_limit = max( 1, min( 50, $recent_limit ) );

    $viewer = ( current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' ) ) ? 'agent' : 'user';

    $pending_raw = pax_sup_get_liveagent_sessions_by_status( 'pending' );
    $active_raw  = pax_sup_get_liveagent_sessions_by_status( 'active' );
    $recent_raw  = pax_sup_get_recent_liveagent_sessions( $recent_limit );

    $pending = array_map(
        function( $session ) use ( $viewer ) {
            return pax_live_agent_prepare_session_summary( $session, $viewer );
        },
        array_slice( $pending_raw, 0, $limit )
    );

    $active = array_map(
        function( $session ) use ( $viewer ) {
            return pax_live_agent_prepare_session_summary( $session, $viewer );
        },
        array_slice( $active_raw, 0, $limit )
    );

    $recent = array_map(
        function( $session ) use ( $viewer ) {
            return pax_live_agent_prepare_session_summary( $session, $viewer );
        },
        $recent_raw
    );

    return rest_ensure_response( array(
        'success' => true,
        'pending' => array_values( $pending ),
        'active'  => array_values( $active ),
        'recent'  => array_values( $recent ),
        'meta'    => array(
            'counts' => array(
                'pending' => count( $pending_raw ),
                'active'  => count( $active_raw ),
                'recent'  => count( $recent_raw ),
            ),
            'timestamp' => current_time( 'mysql' ),
        ),
    ) );
}

function pax_live_agent_get_session( $request ) {
    $session_id = (int) $request->get_param( 'id' );

    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', __( 'Session ID required.', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found.', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    if ( ! pax_live_agent_user_can_access_session( $session ) ) {
        return new WP_Error( 'forbidden', __( 'You do not have permission to access this session.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    return rest_ensure_response( array(
        'success' => true,
        'session' => pax_live_agent_prepare_session_summary( $session ),
    ) );
}

function pax_live_agent_close( $request ) {
    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    $session_id = (int) $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return new WP_Error( 'missing_param', __( 'Session ID required.', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( ! $session ) {
        return new WP_Error( 'not_found', __( 'Session not found.', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    global $wpdb;

    $notes   = $request->get_param( 'notes' );
    $updated = $wpdb->update(
        $wpdb->prefix . 'pax_liveagent_sessions',
        array(
            'status'        => 'closed',
            'ended_at'      => current_time( 'mysql' ),
            'session_notes' => $notes ? sanitize_textarea_field( $notes ) : $session['session_notes'],
        ),
        array( 'id' => $session_id ),
        array( '%s', '%s', '%s' ),
        array( '%d' )
    );

    if ( false === $updated ) {
        return new WP_Error( 'update_failed', __( 'Failed to close session.', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    pax_sup_add_liveagent_message( $session_id, array(
        'sender'  => 'system',
        'message' => __( 'Chat session ended by agent.', 'pax-support-pro' ),
    ) );

    return rest_ensure_response( array(
        'success' => true,
        'status'  => 'closed',
    ) );
}

function pax_live_agent_prepare_session_summary( $session, $viewer = 'agent' ) {
    $messages = array();
    if ( isset( $session['messages'] ) ) {
        if ( is_array( $session['messages'] ) ) {
            $messages = $session['messages'];
        } else {
            $decoded = json_decode( $session['messages'], true );
            if ( is_array( $decoded ) ) {
                $messages = $decoded;
            }
        }
    }

    $last_message = null;
    if ( ! empty( $messages ) ) {
        $last_message = end( $messages );
        reset( $messages );
    }

    return array(
        'id'            => (int) $session['id'],
        'user_id'       => isset( $session['user_id'] ) ? (int) $session['user_id'] : 0,
        'agent_id'      => isset( $session['agent_id'] ) ? (int) $session['agent_id'] : 0,
        'status'        => pax_live_agent_normalize_status( $session['status'] ?? 'pending' ),
        'user_name'     => $session['user_name'] ?? '',
        'user_email'    => $session['user_email'] ?? '',
        'page_url'      => $session['page_url'] ?? '',
        'user_ip'       => $session['user_ip'] ?? '',
        'started_at'    => $session['started_at'] ?? '',
        'ended_at'      => $session['ended_at'] ?? '',
        'last_activity' => $session['last_activity'] ?? '',
        'unread_count'  => pax_live_agent_calculate_unread( $messages, $viewer ),
        'avatar'        => function_exists( 'get_avatar' ) ? get_avatar( $session['user_id'] ?? 0, 48 ) : '',
        'last_message'  => $last_message ? array(
            'id'        => $last_message['id'] ?? '',
            'sender'    => $last_message['sender'] ?? '',
            'excerpt'   => isset( $last_message['message'] ) ? wp_trim_words( wp_strip_all_tags( $last_message['message'] ), 18 ) : '',
            'timestamp' => $last_message['timestamp'] ?? '',
        ) : null,
    );
}

function pax_live_agent_calculate_unread( $messages, $viewer ) {
    if ( empty( $messages ) ) {
        return 0;
    }

    $unread = 0;
    $target = ( 'agent' === $viewer ) ? 'user' : 'agent';

    foreach ( $messages as $message ) {
        if ( isset( $message['sender'], $message['read'] ) && $message['sender'] === $target && empty( $message['read'] ) ) {
            $unread++;
        }
    }

    return $unread;
}

function pax_live_agent_get_typing_state( $session_id ) {
    return array(
        'agent' => (bool) get_transient( "pax_typing_{$session_id}_agent" ),
        'user'  => (bool) get_transient( "pax_typing_{$session_id}_user" ),
    );
}

function pax_live_agent_verify_nonce( $request ) {
    $nonce = $request->get_header( 'x-wp-nonce' );

    if ( ! $nonce && isset( $_REQUEST['_wpnonce'] ) ) {
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    }

    if ( ! $nonce ) {
        return false;
    }

    return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
}

function pax_live_agent_user_can_access_session( $session ) {
    if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' ) ) {
        return true;
    }

    $current_user = get_current_user_id();
    if ( ! $current_user ) {
        return false;
    }

    return isset( $session['user_id'] ) && intval( $session['user_id'] ) === $current_user;
}

function pax_live_agent_normalize_status( $status ) {
    if ( 'accepted' === $status ) {
        return 'active';
    }

    return $status;
}

function pax_notify_admin_live_agent_request( $session_id, $session_data ) {
    $admin_email = get_option( 'admin_email' );
    $site_name = get_bloginfo( 'name' );
    $rest_url = rest_url( 'pax/v1/live/accept' );
    
    $subject = sprintf( '[%s] New Live Agent Request', $site_name );
    
    $message = sprintf(
        "A new live agent request has been received.\n\n" .
        "User: %s\n" .
        "Email: %s\n" .
        "Session ID: %d\n\n" .
        "To accept this request, use the Live Agent Center in your WordPress admin.\n\n" .
        "REST API URL: %s\n" .
        "Session Link: %s",
        $session_data['user_name'],
        $session_data['user_email'],
        $session_id,
        $rest_url,
        admin_url( 'admin.php?page=pax-support-live-agent&session=' . $session_id )
    );
    
    wp_mail( $admin_email, $subject, $message );
    
    set_transient( 'pax_live_agent_pending_' . $session_id, array(
        'session_id' => $session_id,
        'user_name'  => $session_data['user_name'],
        'timestamp'  => time(),
    ), HOUR_IN_SECONDS );
}
