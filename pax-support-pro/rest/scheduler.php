<?php
/**
 * Scheduler REST endpoints.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_rest_can_manage_schedule() {
    return current_user_can( pax_sup_get_console_capability() );
}

function pax_sup_rest_prepare_schedule_response( array $schedule ) {
    $schedule = pax_sup_prepare_schedule_row( $schedule );

    $agent = $schedule['agent_id'] ? get_userdata( $schedule['agent_id'] ) : null;
    $user  = $schedule['user_id'] ? get_userdata( $schedule['user_id'] ) : null;

    return array(
        'id'        => (int) $schedule['id'],
        'date'      => $schedule['schedule_date'],
        'time'      => $schedule['schedule_time'],
        'timezone'  => $schedule['timezone'],
        'status'    => $schedule['status'],
        'note'      => $schedule['note'],
        'contact'   => $schedule['contact'],
        'timestamp' => (int) $schedule['timestamp'],
        'agent'     => $agent instanceof WP_User ? array(
            'id'    => (int) $agent->ID,
            'name'  => $agent->display_name,
            'email' => $agent->user_email,
        ) : null,
        'user'      => $user instanceof WP_User ? array(
            'id'    => (int) $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
        ) : null,
    );
}

function pax_sup_rest_schedule_create( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response( array( 'error' => 'auth', 'message' => __( 'Please sign in to schedule a callback.', 'pax-support-pro' ) ), 401 );
    }

    $options = pax_sup_get_options();
    if ( empty( $options['callback_enabled'] ) ) {
        return new WP_REST_Response( array( 'error' => 'disabled', 'message' => __( 'Callback scheduling is currently disabled.', 'pax-support-pro' ) ), 400 );
    }

    if ( ! pax_sup_rl( 'schedule:' . pax_sup_ip() . ':' . gmdate( 'YmdH' ), 8, HOUR_IN_SECONDS ) ) {
        return new WP_REST_Response( array( 'error' => 'rate', 'message' => __( 'Too many requests. Please wait before retrying.', 'pax-support-pro' ) ), 429 );
    }

    $date     = sanitize_text_field( $request->get_param( 'date' ) );
    $time     = sanitize_text_field( $request->get_param( 'time' ) );
    $timezone = sanitize_text_field( $request->get_param( 'timezone' ) );
    $note     = isset( $request['note'] ) ? pax_sup_trim( $request['note'], 400 ) : '';
    $contact  = pax_sup_trim( sanitize_text_field( $request->get_param( 'contact' ) ), 180 );
    $name     = isset( $request['name'] ) ? pax_sup_trim( $request['name'], 120 ) : '';

    if ( empty( $contact ) ) {
        $contact = pax_sup_trim( sanitize_text_field( $request->get_param( 'phone' ) ), 180 );
    }

    if ( $name ) {
        if ( empty( $contact ) ) {
            $contact = $name;
        } elseif ( false === strpos( $contact, $name ) ) {
            $contact = $name . ' — ' . $contact;
        }
        if ( $note ) {
            $note = $name . ' — ' . $note;
        }
    }

    if ( empty( $contact ) ) {
        return new WP_REST_Response( array( 'error' => 'contact', 'message' => __( 'Please add contact details.', 'pax-support-pro' ) ), 400 );
    }

    $note = pax_sup_trim( $note, 400 );

    $settings = pax_sup_get_scheduler_settings();

    if ( empty( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return new WP_REST_Response( array( 'error' => 'date', 'message' => __( 'Please choose a valid date.', 'pax-support-pro' ) ), 400 );
    }

    if ( empty( $time ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
        return new WP_REST_Response( array( 'error' => 'time', 'message' => __( 'Please choose a valid time.', 'pax-support-pro' ) ), 400 );
    }

    if ( empty( $timezone ) || ! pax_sup_is_valid_timezone( $timezone ) ) {
        $timezone = isset( $settings['timezone'] ) ? $settings['timezone'] : wp_timezone_string();
    }

    if ( ! pax_sup_schedule_within_hours( $time, $settings ) ) {
        return new WP_REST_Response( array( 'error' => 'hours', 'message' => __( 'Selected time is outside working hours.', 'pax-support-pro' ) ), 400 );
    }

    $timestamp = pax_sup_schedule_datetime_to_timestamp( $date, $time, $timezone );

    if ( ! $timestamp || $timestamp < time() + ( 5 * MINUTE_IN_SECONDS ) ) {
        return new WP_REST_Response( array( 'error' => 'past', 'message' => __( 'Please select a future time slot.', 'pax-support-pro' ) ), 400 );
    }

    if ( ! pax_sup_schedule_slots_available( $date, $time ) ) {
        return new WP_REST_Response( array( 'error' => 'capacity', 'message' => __( 'This time slot is fully booked. Please choose another.', 'pax-support-pro' ) ), 409 );
    }

    $agent_id = pax_sup_schedule_assign_agent( $settings );
    $user_id  = get_current_user_id();

    global $wpdb;
    $table = pax_sup_get_schedules_table();

    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'       => $user_id,
            'agent_id'      => $agent_id,
            'schedule_date' => $date,
            'schedule_time' => $time,
            'timezone'      => $timezone,
            'contact'       => $contact,
            'note'          => $note,
            'status'        => 'pending',
            'created_at'    => current_time( 'mysql' ),
            'updated_at'    => current_time( 'mysql' ),
        ),
        array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( false === $inserted ) {
        return new WP_REST_Response( array( 'error' => 'db', 'message' => __( 'Unable to create the schedule.', 'pax-support-pro' ) ), 500 );
    }

    $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id ), ARRAY_A );

    if ( $schedule ) {
        pax_sup_notify_schedule_event( $schedule, 'created' );
    }

    return new WP_REST_Response(
        array(
            'ok'       => true,
            'schedule' => $schedule ? pax_sup_rest_prepare_schedule_response( $schedule ) : null,
            'message'  => __( 'Callback booked successfully.', 'pax-support-pro' ),
        ),
        201
    );
}

function pax_sup_rest_schedule_list( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response( array( 'error' => 'auth', 'message' => __( 'Please sign in to view callbacks.', 'pax-support-pro' ) ), 401 );
    }

    $user_id  = get_current_user_id();
    $settings = pax_sup_get_scheduler_settings();
    global $wpdb;
    $table = pax_sup_get_schedules_table();

    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY schedule_date ASC, schedule_time ASC", $user_id ), ARRAY_A );
    $items = array();

    foreach ( (array) $rows as $row ) {
        $items[] = pax_sup_rest_prepare_schedule_response( $row );
    }

    return new WP_REST_Response(
        array(
            'items'    => $items,
            'timezone' => $settings['timezone'],
            'now'      => time(),
        ),
        200
    );
}

function pax_sup_rest_schedule_cancel( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response( array( 'error' => 'auth', 'message' => __( 'Please sign in to manage callbacks.', 'pax-support-pro' ) ), 401 );
    }

    $schedule_id = (int) $request->get_param( 'id' );

    if ( ! $schedule_id ) {
        return new WP_REST_Response( array( 'error' => 'missing', 'message' => __( 'Invalid schedule request.', 'pax-support-pro' ) ), 400 );
    }

    global $wpdb;
    $table    = pax_sup_get_schedules_table();
    $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $schedule_id ), ARRAY_A );

    if ( empty( $schedule ) || (int) $schedule['user_id'] !== get_current_user_id() ) {
        return new WP_REST_Response( array( 'error' => 'not_found', 'message' => __( 'Schedule not found.', 'pax-support-pro' ) ), 404 );
    }

    if ( 'canceled' === $schedule['status'] ) {
        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    $wpdb->update(
        $table,
        array(
            'status'       => 'canceled',
            'updated_at'   => current_time( 'mysql' ),
            'reminder_sent'=> 1,
        ),
        array( 'id' => $schedule_id ),
        array( '%s', '%s', '%d' ),
        array( '%d' )
    );

    $schedule['status'] = 'canceled';
    $schedule['reminder_sent'] = 1;
    pax_sup_notify_schedule_event( $schedule, 'canceled' );

    return new WP_REST_Response( array( 'ok' => true, 'message' => __( 'Callback canceled.', 'pax-support-pro' ) ), 200 );
}

function pax_sup_rest_schedule_update( WP_REST_Request $request ) {
    if ( ! pax_sup_rest_can_manage_schedule() ) {
        return new WP_REST_Response( array( 'error' => 'auth', 'message' => __( 'Insufficient permissions.', 'pax-support-pro' ) ), 403 );
    }

    $schedule_id = (int) $request->get_param( 'id' );
    $status      = sanitize_text_field( $request->get_param( 'status' ) );
    $allowed     = array( 'pending', 'confirmed', 'done', 'canceled' );

    if ( ! $schedule_id || ! in_array( $status, $allowed, true ) ) {
        return new WP_REST_Response( array( 'error' => 'invalid', 'message' => __( 'Invalid request.', 'pax-support-pro' ) ), 400 );
    }

    global $wpdb;
    $table    = pax_sup_get_schedules_table();
    $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $schedule_id ), ARRAY_A );

    if ( empty( $schedule ) ) {
        return new WP_REST_Response( array( 'error' => 'not_found', 'message' => __( 'Schedule not found.', 'pax-support-pro' ) ), 404 );
    }

    $wpdb->update(
        $table,
        array(
            'status'       => $status,
            'updated_at'   => current_time( 'mysql' ),
            'reminder_sent'=> ( 'pending' === $status || 'confirmed' === $status ) ? (int) $schedule['reminder_sent'] : 1,
        ),
        array( 'id' => $schedule_id ),
        array( '%s', '%s', '%d' ),
        array( '%d' )
    );

    $schedule['status'] = $status;
    $schedule['reminder_sent'] = ( 'pending' === $status || 'confirmed' === $status ) ? (int) $schedule['reminder_sent'] : 1;
    pax_sup_notify_schedule_event( $schedule, 'canceled' === $status ? 'canceled' : 'updated' );

    return new WP_REST_Response( array( 'ok' => true, 'schedule' => pax_sup_rest_prepare_schedule_response( $schedule ) ), 200 );
}

add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            PAX_SUP_REST_NS,
            '/schedule',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => 'pax_sup_rest_schedule_list',
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => 'pax_sup_rest_schedule_create',
                ),
            )
        );

        register_rest_route(
            PAX_SUP_REST_NS,
            '/schedule/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'permission_callback' => 'pax_sup_rest_can_manage_schedule',
                    'callback'            => 'pax_sup_rest_schedule_update',
                ),
            )
        );

        register_rest_route(
            PAX_SUP_REST_NS,
            '/schedule/(?P<id>\d+)/cancel',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'permission_callback' => '__return_true',
                    'callback'            => 'pax_sup_rest_schedule_cancel',
                ),
            )
        );
    }
);