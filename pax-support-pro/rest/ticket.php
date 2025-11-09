<?php
/**
 * REST endpoints for ticket operations.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_ticket_rest_require_login() {
    return pax_sup_rest_require_read_permission();
}

function pax_sup_ticket_rest_require_console() {
    return current_user_can( pax_sup_get_console_capability() );
}

add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            PAX_SUP_REST_NS,
            '/ticket',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'permission_callback' => 'pax_sup_ticket_rest_require_login',
                    'callback'            => 'pax_sup_rest_ticket_create',
                ),
            )
        );

        register_rest_route(
            PAX_SUP_REST_NS,
            '/tickets',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => 'pax_sup_ticket_rest_require_login',
                'callback'            => 'pax_sup_rest_ticket_list',
            )
        );

        register_rest_route(
            PAX_SUP_REST_NS,
            '/ticket/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'permission_callback' => 'pax_sup_ticket_rest_require_login',
                    'callback'            => 'pax_sup_rest_ticket_view',
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'permission_callback' => 'pax_sup_ticket_rest_require_login',
                    'callback'            => 'pax_sup_rest_ticket_delete',
                ),
            )
        );

        register_rest_route(
            PAX_SUP_REST_NS,
            '/ticket/(?P<id>\d+)/reply',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => 'pax_sup_ticket_rest_require_console',
                'callback'            => 'pax_sup_rest_ticket_reply',
            )
        );

        register_rest_route(
            PAX_SUP_REST_NS,
            '/ticket/(?P<id>\d+)/freeze',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => 'pax_sup_ticket_rest_require_console',
                'callback'            => 'pax_sup_rest_ticket_freeze',
            )
        );

        register_rest_route(
            PAX_SUP_REST_NS,
            '/ticket-cooldown',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => 'pax_sup_ticket_rest_require_login',
                'callback'            => 'pax_sup_rest_ticket_cooldown',
            )
        );
    }
);

function pax_sup_ticket_prepare_tables() {
    pax_sup_ensure_ticket_tables();
}

function pax_sup_rest_ticket_create( WP_REST_Request $request ) {
    pax_sup_ticket_prepare_tables();

    $options   = pax_sup_get_options();
    $user_id   = get_current_user_id();
    $cool_days = isset( $options['ticket_cooldown_days'] ) ? max( 0, (int) $options['ticket_cooldown_days'] ) : 0;
    $now       = current_time( 'timestamp' );

    if ( $cool_days <= 0 ) {
        $cool_days = 3;
    }

    $cooldown_until = pax_sup_get_user_cooldown_until( $user_id );

    if ( $cool_days > 0 && $cooldown_until && $now < $cooldown_until ) {
        return new WP_REST_Response(
            array(
                'error'   => 'cooldown',
                'until'   => $cooldown_until,
                'seconds' => max( 0, $cooldown_until - $now ),
                'message' => __( 'Cooldown active. Please wait before submitting another ticket.', 'pax-support-pro' ),
            ),
            429
        );
    }

    $name    = pax_sup_trim( $request->get_param( 'name' ), 120 );
    $email   = sanitize_email( $request->get_param( 'email' ) );
    $subject = pax_sup_trim( $request->get_param( 'subject' ), 160 );
    $message = wp_kses_post( $request->get_param( 'message' ) );

    if ( empty( $subject ) || empty( $message ) ) {
        return new WP_REST_Response(
            array(
                'error'   => 'missing',
                'message' => __( 'Subject and message are required.', 'pax-support-pro' ),
            ),
            400
        );
    }

    if ( empty( $email ) || ! is_email( $email ) ) {
        $user  = wp_get_current_user();
        $email = $user instanceof WP_User ? $user->user_email : '';
    }

    if ( empty( $name ) ) {
        $user = wp_get_current_user();
        $name = $user instanceof WP_User ? $user->display_name : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    }

    if ( empty( $email ) || ! is_email( $email ) ) {
        return new WP_REST_Response(
            array(
                'error'   => 'email',
                'message' => __( 'A valid email address is required.', 'pax-support-pro' ),
            ),
            400
        );
    }

    global $wpdb;
    $table    = pax_sup_get_ticket_table();
    $created  = current_time( 'mysql' );
    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'    => $user_id,
            'subject'    => $subject,
            'message'    => wp_kses_post( $message ),
            'status'     => 'open',
            'created_at' => $created,
            'updated_at' => $created,
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( false === $inserted ) {
        return new WP_REST_Response(
            array(
                'error'   => 'db',
                'message' => __( 'Unable to create ticket.', 'pax-support-pro' ),
            ),
            500
        );
    }

    $ticket_id = (int) $wpdb->insert_id;

    $message_id = pax_sup_ticket_add_message( $ticket_id, 'user', $message );

    // Handle file attachments
    $attachments = array();
    if ( ! empty( $_FILES ) ) {
        foreach ( $_FILES as $file_key => $file ) {
            if ( strpos( $file_key, 'attachment' ) === 0 && ! empty( $file['name'] ) ) {
                $upload_result = pax_sup_handle_file_upload( $file, $ticket_id, $user_id );
                
                if ( ! is_wp_error( $upload_result ) ) {
                    $attachment_id = pax_sup_save_attachment( $ticket_id, $message_id, $user_id, $upload_result );
                    if ( $attachment_id ) {
                        $attachments[] = array(
                            'id'        => $attachment_id,
                            'file_name' => $upload_result['file_name'],
                            'file_size' => $upload_result['file_size'],
                            'file_type' => $upload_result['file_type'],
                            'url'       => $upload_result['url'],
                        );
                    }
                }
            }
        }
    }

    $until = $cool_days > 0 ? $now + ( $cool_days * DAY_IN_SECONDS ) : 0;
    if ( $until > 0 ) {
        pax_sup_set_user_cooldown_until( $user_id, $until );
    }

    $admin_email = get_option( 'admin_email' );
    if ( $admin_email ) {
        $body  = '<h2>' . esc_html__( 'New Support Ticket', 'pax-support-pro' ) . '</h2>';
        $body .= '<p><b>' . esc_html__( 'From:', 'pax-support-pro' ) . '</b> ' . esc_html( $name ) . ' &lt;' . esc_html( $email ) . '&gt;</p>';
        $body .= '<p><b>' . esc_html__( 'Subject:', 'pax-support-pro' ) . '</b> ' . esc_html( $subject ) . '</p>';
        $body .= '<hr><div>' . wpautop( wp_kses_post( $message ) ) . '</div>';
        $body .= '<p>' . esc_html__( 'Manage the ticket from the WordPress admin console.', 'pax-support-pro' ) . '</p>';
        @wp_mail( $admin_email, '[PAX] Ticket: ' . $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    $response = array(
        'ok'             => true,
        'id'             => $ticket_id,
        'status'         => 'open',
        'cooldown_until' => $until,
        'attachments'    => $attachments,
    );

    return new WP_REST_Response( $response, 200 );
}

function pax_sup_rest_ticket_list() {
    pax_sup_ticket_prepare_tables();

    global $wpdb;
    $table   = pax_sup_get_ticket_table();
    $user_id = get_current_user_id();

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, subject, status, updated_at FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT 12",
            $user_id
        )
    );

    $items = array();
    foreach ( (array) $rows as $row ) {
        $items[] = array(
            'id'      => (int) $row->id,
            'subject' => $row->subject,
            'status'  => pax_sup_format_ticket_status( $row->status ),
            'rawStatus' => $row->status,
            'updated' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->updated_at, false ),
        );
    }

    return new WP_REST_Response(
        array(
            'items' => $items,
        ),
        200
    );
}

function pax_sup_ticket_can_view( $ticket ) {
    if ( ! $ticket ) {
        return false;
    }

    $user_id = get_current_user_id();

    if ( $user_id && (int) $ticket->user_id === (int) $user_id ) {
        return true;
    }

    return current_user_can( pax_sup_get_console_capability() );
}

function pax_sup_rest_ticket_view( WP_REST_Request $request ) {
    pax_sup_ticket_prepare_tables();

    $ticket = pax_sup_ticket_get( (int) $request['id'] );

    if ( ! $ticket ) {
        return new WP_Error( 'rest_not_found', __( 'Ticket not found.', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    if ( ! pax_sup_ticket_can_view( $ticket ) ) {
        return new WP_Error( 'rest_forbidden', __( 'You are not allowed to view this ticket.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    $data = pax_sup_ticket_to_array( $ticket, true );

    return new WP_REST_Response( $data, 200 );
}

function pax_sup_rest_ticket_delete( WP_REST_Request $request ) {
    pax_sup_ticket_prepare_tables();

    $ticket = pax_sup_ticket_get( (int) $request['id'] );

    if ( ! $ticket ) {
        return new WP_Error( 'rest_not_found', __( 'Ticket not found.', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    if ( ! pax_sup_ticket_can_view( $ticket ) ) {
        return new WP_Error( 'rest_forbidden', __( 'You are not allowed to delete this ticket.', 'pax-support-pro' ), array( 'status' => 403 ) );
    }

    pax_sup_ticket_delete( (int) $ticket->id );
    pax_sup_set_user_cooldown_until( $ticket->user_id, 0 );

    return new WP_REST_Response( array( 'ok' => true ), 200 );
}

function pax_sup_rest_ticket_reply( WP_REST_Request $request ) {
    pax_sup_ticket_prepare_tables();

    $ticket = pax_sup_ticket_get( (int) $request['id'] );

    if ( ! $ticket ) {
        return new WP_Error( 'rest_not_found', __( 'Ticket not found.', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    $message = wp_kses_post( $request->get_param( 'message' ) );

    if ( empty( $message ) ) {
        return new WP_Error( 'rest_missing_callback_param', __( 'Reply message is required.', 'pax-support-pro' ), array( 'status' => 400 ) );
    }

    pax_sup_ticket_add_message( (int) $ticket->id, 'agent', $message );
    pax_sup_ticket_update_status( (int) $ticket->id, 'answered' );

    $user = get_userdata( (int) $ticket->user_id );
    if ( $user instanceof WP_User && $user->user_email ) {
        $subject = sprintf( __( 'Ticket #%d update', 'pax-support-pro' ), (int) $ticket->id );
        $body    = sprintf(
            "%s\n\n%s",
            __( 'An agent replied to your ticket:', 'pax-support-pro' ),
            wp_strip_all_tags( $message )
        );
        @wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
    }

    $updated_ticket = pax_sup_ticket_get( (int) $ticket->id );

    return new WP_REST_Response( pax_sup_ticket_to_array( $updated_ticket, true ), 200 );
}

function pax_sup_rest_ticket_freeze( WP_REST_Request $request ) {
    pax_sup_ticket_prepare_tables();

    $ticket = pax_sup_ticket_get( (int) $request['id'] );

    if ( ! $ticket ) {
        return new WP_Error( 'rest_not_found', __( 'Ticket not found.', 'pax-support-pro' ), array( 'status' => 404 ) );
    }

    $freeze = filter_var( $request->get_param( 'freeze' ), FILTER_VALIDATE_BOOLEAN );
    $status = $freeze ? 'frozen' : 'open';

    pax_sup_ticket_update_status( (int) $ticket->id, $status );

    $updated_ticket = pax_sup_ticket_get( (int) $ticket->id );

    return new WP_REST_Response( pax_sup_ticket_to_array( $updated_ticket, false ), 200 );
}

function pax_sup_rest_ticket_cooldown() {
    $options  = pax_sup_get_options();
    $user_id  = get_current_user_id();
    $cooldays = isset( $options['ticket_cooldown_days'] ) ? max( 0, (int) $options['ticket_cooldown_days'] ) : 0;

    if ( $cooldays <= 0 ) {
        $cooldays = 3;
    }

    $until   = pax_sup_get_user_cooldown_until( $user_id );
    $now     = current_time( 'timestamp' );
    $seconds = $until > $now ? $until - $now : 0;

    return new WP_REST_Response(
        array(
            'enabled' => $cooldays > 0,
            'until'   => $until,
            'seconds' => $seconds,
        ),
        200
    );
}

function pax_sup_ticket_get( $id ) {
    global $wpdb;
    $table = pax_sup_get_ticket_table();

    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
}

function pax_sup_ticket_get_messages( $ticket_id ) {
    global $wpdb;
    $table = pax_sup_get_ticket_messages_table();

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, sender, note, created_at FROM {$table} WHERE ticket_id = %d ORDER BY created_at ASC",
            $ticket_id
        )
    );
}

function pax_sup_ticket_add_message( $ticket_id, $sender, $note ) {
    global $wpdb;

    $ticket_id = (int) $ticket_id;
    if ( $ticket_id <= 0 || empty( $note ) ) {
        return 0;
    }

    $sender = in_array( $sender, array( 'agent', 'user' ), true ) ? $sender : 'user';

    $messages = pax_sup_get_ticket_messages_table();
    $created  = current_time( 'mysql' );

    $wpdb->insert(
        $messages,
        array(
            'ticket_id'  => $ticket_id,
            'sender'     => $sender,
            'note'       => wp_kses_post( $note ),
            'created_at' => $created,
        ),
        array( '%d', '%s', '%s', '%s' )
    );

    pax_sup_ticket_touch( $ticket_id, $created );
    
    return (int) $wpdb->insert_id;
}

function pax_sup_ticket_touch( $ticket_id, $time = '' ) {
    global $wpdb;
    $table = pax_sup_get_ticket_table();

    if ( empty( $time ) ) {
        $time = current_time( 'mysql' );
    }

    $wpdb->update(
        $table,
        array( 'updated_at' => $time ),
        array( 'id' => $ticket_id ),
        array( '%s' ),
        array( '%d' )
    );
}

function pax_sup_ticket_update_status( $ticket_id, $status ) {
    global $wpdb;
    $table = pax_sup_get_ticket_table();

    $allowed = array( 'open', 'frozen', 'closed', 'answered' );
    if ( ! in_array( $status, $allowed, true ) ) {
        $status = 'open';
    }

    $wpdb->update(
        $table,
        array(
            'status'     => $status,
            'updated_at' => current_time( 'mysql' ),
        ),
        array( 'id' => $ticket_id ),
        array( '%s', '%s' ),
        array( '%d' )
    );
}

function pax_sup_ticket_delete( $ticket_id ) {
    global $wpdb;
    $tickets  = pax_sup_get_ticket_table();
    $messages = pax_sup_get_ticket_messages_table();

    $wpdb->delete( $messages, array( 'ticket_id' => $ticket_id ), array( '%d' ) );
    $wpdb->delete( $tickets, array( 'id' => $ticket_id ), array( '%d' ) );
}

function pax_sup_ticket_to_array( $ticket, $with_messages = false ) {
    if ( ! $ticket ) {
        return array();
    }

    $data = array(
        'id'        => (int) $ticket->id,
        'user_id'   => (int) $ticket->user_id,
        'subject'   => $ticket->subject,
        'message'   => $ticket->message,
        'status'    => pax_sup_format_ticket_status( $ticket->status ),
        'rawStatus' => $ticket->status,
        'created'   => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ticket->created_at, false ),
        'updated'   => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ticket->updated_at, false ),
    );

    if ( $with_messages ) {
        $messages = pax_sup_ticket_get_messages( (int) $ticket->id );
        $thread   = array();
        foreach ( (array) $messages as $msg ) {
            $message_attachments = pax_sup_get_message_attachments( (int) $msg->id );
            $thread[] = array(
                'id'          => (int) $msg->id,
                'sender'      => $msg->sender,
                'note'        => wp_kses_post( $msg->note ),
                'created'     => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $msg->created_at, false ),
                'attachments' => $message_attachments,
            );
        }
        $data['messages'] = $thread;
    }

    $data['attachments'] = pax_sup_get_ticket_attachments( (int) $ticket->id );

    return $data;
}