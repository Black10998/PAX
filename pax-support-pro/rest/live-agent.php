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
        'callback'            => 'pax_live_agent_messages_stream',
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

    register_rest_route( 'pax/v1', '/live/rate', array(
        'methods'             => 'POST',
        'callback'            => 'pax_live_agent_session_rate',
        'permission_callback' => '__return_true',
    ) );
}

function pax_live_agent_nocache() {
    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
}

function pax_live_agent_bootstrap_payload( $request ) {
    $page_url = isset( $request['page_url'] ) ? esc_url_raw( $request['page_url'] ) : '';
    $domain   = wp_parse_url( home_url(), PHP_URL_HOST );

    if ( function_exists( 'ppress_is_profilepress_active' ) || defined( 'PROFILEPRESS_VERSION' ) ) {
        $auth_plugin = 'profilepress';
    } elseif ( function_exists( 'wc' ) || class_exists( 'WooCommerce' ) ) {
        $auth_plugin = 'woocommerce';
    } elseif ( function_exists( 'um_user' ) || defined( 'UM_VERSION' ) ) {
        $auth_plugin = 'ultimatemember';
    } else {
        $auth_plugin = 'core';
    }

    return array(
        'page_url'    => $page_url,
        'domain'      => sanitize_text_field( $domain ),
        'auth_plugin' => $auth_plugin,
    );
}

function pax_live_agent_start( $request ) {
    global $wpdb;
    
    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    $user_meta      = $request->get_param( 'user_meta' ) ?: array();
    $current_user   = wp_get_current_user();
    $has_wp_user    = ( $current_user instanceof WP_User ) && $current_user->exists();
    $resolved_name  = $has_wp_user ? ( $current_user->display_name ?: $current_user->user_login ) : sanitize_text_field( $user_meta['name'] ?? __( 'Guest', 'pax-support-pro' ) );
    $resolved_email = $has_wp_user ? $current_user->user_email : sanitize_email( $user_meta['email'] ?? '' );

    $bootstrap = pax_live_agent_bootstrap_payload( $request );
    $now = current_time( 'mysql' );

    $session_payload = array(
        'status'      => 'pending',
        'user_id'     => $has_wp_user ? (int) $current_user->ID : 0,
        'user_name'   => $resolved_name ?: __( 'Guest', 'pax-support-pro' ),
        'user_email'  => $resolved_email,
        'user_ip'     => pax_sup_get_client_ip(),
        'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
        'page_url'    => $bootstrap['page_url'],
        'domain'      => $bootstrap['domain'],
        'auth_plugin' => $bootstrap['auth_plugin'],
        'source'      => 'widget',
        'messages'    => array(),
        'last_activity' => $now,
    );

    $session_id = pax_live_agent_session_create( $session_payload );

    if ( ! $session_id ) {
        return new WP_Error( 'db_error', 'Failed to create session', array( 'status' => 500 ) );
    }

    $session = pax_sup_get_liveagent_session( $session_id );
    if ( $session && empty( $session['user_name'] ) ) {
        $session['user_name'] = $resolved_name ?: __( 'Guest', 'pax-support-pro' );
    }

    error_log( '[PAX LIVE] create sid=' . $session_id . ' user=' . intval( $session_payload['user_id'] ) . ' status=pending' );

    pax_notify_admin_live_agent_request( $session_id, array(
        'user_name'  => $session['user_name'] ?? $session_payload['user_name'],
        'user_email' => $session['user_email'] ?? $session_payload['user_email'],
    ) );

    $summary = $session ? pax_live_agent_prepare_session_summary( $session, 'user' ) : array(
        'id'     => $session_id,
        'status' => 'pending',
    );

    return rest_ensure_response( array(
        'success'    => true,
        'session'    => $summary,
        'session_id' => $session_id,
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
    
    $session = pax_sup_get_liveagent_session( $session_id );

    error_log( '[PAX LIVE] accept sid=' . $session_id . ' agent=' . get_current_user_id() );

    return rest_ensure_response( array(
        'success'    => true,
        'session'    => $session ? pax_live_agent_prepare_session_summary( $session ) : null,
        'session_id' => $session_id,
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
    
    $session = pax_sup_get_liveagent_session( $session_id );

    error_log( '[PAX LIVE] decline sid=' . $session_id . ' agent=' . get_current_user_id() );

    return rest_ensure_response( array(
        'success'    => true,
        'session'    => $session ? pax_live_agent_prepare_session_summary( $session ) : null,
        'session_id' => $session_id,
    ) );
}

function pax_live_agent_message( $request ) {
    global $wpdb;

    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    $session_id     = (int) $request->get_param( 'session_id' );
    $raw_message    = $request->get_param( 'content' );
    if ( null === $raw_message ) {
        $raw_message = $request->get_param( 'message' );
    }
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
        'role'    => ( 'agent' === $sender ) ? 'admin' : 'user',
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

    $now = current_time( 'mysql' );
    $wpdb->update(
        $wpdb->prefix . 'pax_liveagent_sessions',
        array( 'last_activity' => $now ),
        array( 'id' => $session_id ),
        array( '%s' ),
        array( '%d' )
    );

    $updated_session = pax_sup_get_liveagent_session( $session_id );
    $messages        = $updated_session['messages'];
    $new_message     = null;
    if ( is_array( $messages ) && ! empty( $messages ) ) {
        $temp_messages = $messages;
        $new_message   = end( $temp_messages );
    }

    error_log( '[PAX LIVE] msg sid=' . $session_id . ' role=' . $message_data['role'] . ' len=' . strlen( (string) $message_text ) );

    if ( $new_message ) {
        pax_sup_notify_new_message( $session_id, $new_message, $sender );
    }

    return rest_ensure_response( array(
        'success'    => true,
        'session'    => pax_live_agent_prepare_session_summary( $updated_session ),
        'session_id' => $session_id,
        'message'    => $new_message,
    ) );
}

function pax_live_agent_messages_stream( $request ) {
    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    pax_live_agent_nocache();

    $session_id = (int) $request->get_param( 'session_id' );
    if ( ! $session_id ) {
        return rest_ensure_response( array( 'success' => false, 'messages' => array() ) );
    }

    $after = (int) $request->get_param( 'after' );
    $wait  = (int) $request->get_param( 'wait' );
    if ( $wait <= 0 ) {
        $wait = 25;
    }
    $wait = min( 25, max( 1, $wait ) );

    $start_time = time();
    $messages   = array();
    $session    = null;

    do {
        $session = pax_sup_get_liveagent_session( $session_id );

        if ( ! $session ) {
            return new WP_Error( 'not_found', __( 'Session not found.', 'pax-support-pro' ), array( 'status' => 404 ) );
        }

        if ( ! pax_live_agent_user_can_access_session( $session ) ) {
            return new WP_Error( 'forbidden', __( 'You do not have permission to access this session.', 'pax-support-pro' ), array( 'status' => 403 ) );
        }

        $all_messages = isset( $session['messages'] ) && is_array( $session['messages'] ) ? $session['messages'] : array();
        $normalized   = pax_sup_normalize_liveagent_messages( $all_messages );

        $messages = array_filter(
            $normalized,
            function( $message ) use ( $after ) {
                $seq = isset( $message['seq'] ) ? (int) $message['seq'] : 0;
                return $seq > $after;
            }
        );

        if ( ! empty( $messages ) ) {
            break;
        }

        usleep( 400000 );
    } while ( ( time() - $start_time ) < $wait );

    $typing_state = pax_live_agent_get_typing_state( $session_id );

    $messages = array_map(
        function( $message ) {
            $seq = (int) ( $message['seq'] ?? 0 );
            return array(
                'id'         => $seq,
                'seq'        => $seq,
                'role'       => $message['role'] ?? ( ( isset( $message['sender'] ) && 'agent' === $message['sender'] ) ? 'admin' : 'user' ),
                'sender'     => $message['sender'] ?? '',
                'content'    => $message['message'] ?? '',
                'timestamp'  => $message['timestamp'] ?? '',
                'created_at' => $message['created_at'] ?? ( $message['timestamp'] ?? '' ),
                'meta'       => isset( $message['meta'] ) ? $message['meta'] : array(),
            );
        },
        array_values( $messages )
    );

    $last_id = $after;
    if ( ! empty( $messages ) ) {
        $last_id = max( array_column( $messages, 'seq' ) );
    }
    $normalized_last_id = max( $last_id, (int) $after );

    $etag_seed = implode(
        '|',
        array(
            $session_id,
            $session['status'],
            $session['last_activity'],
            $normalized_last_id,
            $typing_state['agent'] ? '1' : '0',
            $typing_state['user'] ? '1' : '0',
        )
    );
    $etag = '"' . hash( 'sha256', $etag_seed ) . '"';

    $client_etag = trim( (string) $request->get_header( 'If-None-Match' ) );
    if (
        $client_etag
        && $client_etag === $etag
        && empty( $messages )
        && $normalized_last_id <= (int) $after
    ) {
        $response = new WP_REST_Response( null, 304 );
        $response->header( 'ETag', $etag );
        return $response;
    }

    $response = rest_ensure_response( array(
        'success'    => true,
        'messages'   => $messages,
        'session_id' => $session_id,
        'status'     => $session['status'],
        'last_id'    => $last_id,
        'typing'     => $typing_state,
    ) );
    $response->header( 'ETag', $etag );

    return $response;
}

function pax_live_agent_list_sessions( $request ) {
    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    nocache_headers();

    global $wpdb;

    $limit        = $request->get_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 30;
    $recent_limit = $request->get_param( 'recent_limit' ) ? intval( $request->get_param( 'recent_limit' ) ) : 10;
    $limit        = max( 1, min( 100, $limit ) );
    $recent_limit = max( 1, min( 50, $recent_limit ) );

    $viewer = ( current_user_can( 'manage_options' ) || current_user_can( 'manage_pax_chats' ) ) ? 'agent' : 'user';

    $since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
    $table = $wpdb->prefix . 'pax_liveagent_sessions';

    $pending_raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s AND last_activity >= %s ORDER BY last_activity DESC LIMIT %d",
            'pending',
            $since,
            $limit
        ),
        ARRAY_A
    );

    $active_raw = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE status IN ('active','accepted') AND last_activity >= %s ORDER BY last_activity DESC LIMIT %d",
            $since,
            $limit
        ),
        ARRAY_A
    );

    $recent_raw = pax_sup_get_recent_liveagent_sessions( $recent_limit );

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

    error_log( '[PAX LIVE] list_sessions uid=' . get_current_user_id() );

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
        'session_id' => $session_id,
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
            'last_activity' => current_time( 'mysql' ),
            'session_notes' => $notes ? sanitize_textarea_field( $notes ) : $session['session_notes'],
        ),
        array( 'id' => $session_id ),
        array( '%s', '%s', '%s', '%s' ),
        array( '%d' )
    );

    if ( false === $updated ) {
        return new WP_Error( 'update_failed', __( 'Failed to close session.', 'pax-support-pro' ), array( 'status' => 500 ) );
    }

    pax_sup_add_liveagent_message( $session_id, array(
        'sender'  => 'system',
        'message' => __( 'Chat session ended by agent.', 'pax-support-pro' ),
    ) );

    $updated_session = pax_sup_get_liveagent_session( $session_id );

    error_log( '[PAX LIVE] close sid=' . $session_id . ' agent=' . get_current_user_id() );

    return rest_ensure_response( array(
        'success'    => true,
        'session'    => $updated_session ? pax_live_agent_prepare_session_summary( $updated_session ) : null,
        'session_id' => $session_id,
    ) );
}

function pax_live_agent_session_rate( $request ) {
    if ( ! pax_live_agent_verify_nonce( $request ) ) {
        return new WP_Error( 'invalid_nonce', __( 'Security check failed.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    pax_live_agent_nocache();

    global $wpdb;

    $session_id = (int) $request->get_param( 'session_id' );
    $score      = max( 1, min( 5, (int) $request->get_param( 'score' ) ) );
    $note       = sanitize_text_field( (string) $request->get_param( 'note' ) );

    if ( ! $session_id || ! $score ) {
        return rest_ensure_response( array( 'success' => false ) );
    }

    $table = $wpdb->prefix . 'pax_liveagent_sessions';

    $updated = $wpdb->update(
        $table,
        array(
            'rating'       => $score,
            'rating_note'  => $note,
            'last_activity'=> current_time( 'mysql' ),
        ),
        array( 'id' => $session_id ),
        array( '%d', '%s', '%s' ),
        array( '%d' )
    );

    if ( false === $updated ) {
        return rest_ensure_response( array( 'success' => false ) );
    }

    return rest_ensure_response( array( 'success' => true ) );
}

function pax_live_agent_prepare_session_summary( $session, $viewer = 'agent' ) {
    $messages = array();
    if ( isset( $session['messages'] ) ) {
        if ( is_array( $session['messages'] ) ) {
            $messages = pax_sup_normalize_liveagent_messages( $session['messages'] );
        } else {
            $decoded = json_decode( $session['messages'], true );
            $messages = pax_sup_normalize_liveagent_messages( $decoded );
        }
    }

    $last_message = null;
    if ( ! empty( $messages ) ) {
        $temp_messages = $messages;
        $last_message  = end( $temp_messages );
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
        'domain'        => isset( $session['domain'] ) ? sanitize_text_field( $session['domain'] ) : '',
        'auth_plugin'   => isset( $session['auth_plugin'] ) ? sanitize_key( $session['auth_plugin'] ) : 'core',
        'user_agent'    => isset( $session['user_agent'] ) ? sanitize_text_field( $session['user_agent'] ) : '',
        'source'        => isset( $session['source'] ) ? sanitize_text_field( $session['source'] ) : '',
        'started_at'    => $session['started_at'] ?? '',
        'ended_at'      => $session['ended_at'] ?? '',
        'last_activity' => $session['last_activity'] ?? '',
        'unread_count'  => pax_live_agent_calculate_unread( $messages, $viewer ),
        'rating'        => isset( $session['rating'] ) ? (int) $session['rating'] : null,
        'rating_note'   => isset( $session['rating_note'] ) ? $session['rating_note'] : '',
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
    $nonce = $request->get_header( 'X-WP-Nonce' );

    if ( ! $nonce ) {
        $nonce = $request->get_header( 'x-wp-nonce' );
    }

    if ( ! $nonce ) {
        return false;
    }

    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return false;
    }

    $route = $request->get_route();

    if ( strpos( $route, '/live/rate' ) !== false ) {
        return true;
    }

    if ( preg_match( '#/live/(session|message|close)$#', $route ) ) {
        return true;
    }

    if ( strpos( $route, '/live/sessions' ) !== false || strpos( $route, '/live/messages' ) !== false ) {
        return is_user_logged_in();
    }

    return current_user_can( 'manage_options' );
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
