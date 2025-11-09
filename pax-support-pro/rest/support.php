<?php
/**
 * Additional REST endpoints for support tools.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'pax_sup_rest_require_login' ) ) {
    function pax_sup_rest_require_login() {
        return pax_sup_rest_require_read_permission();
    }
}

function pax_sup_register_support_routes() {
    register_rest_route(
        PAX_SUP_REST_NS,
        '/help-center',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => 'pax_sup_rest_require_login',
            'callback'            => 'pax_sup_rest_help_center',
        )
    );

    register_rest_route(
        PAX_SUP_REST_NS,
        '/troubleshooter',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_login',
            'callback'            => 'pax_sup_rest_troubleshooter',
        )
    );

    register_rest_route(
        PAX_SUP_REST_NS,
        '/diagnostics',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => 'pax_sup_rest_require_login',
            'callback'            => 'pax_sup_rest_diagnostics',
        )
    );

    register_rest_route(
        PAX_SUP_REST_NS,
        '/order-lookup',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_login',
            'callback'            => 'pax_sup_rest_order_lookup',
        )
    );

    register_rest_route(
        PAX_SUP_REST_NS,
        '/my-request',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => 'pax_sup_rest_require_login',
            'callback'            => 'pax_sup_rest_my_request',
        )
    );

    register_rest_route(
        PAX_SUP_REST_NS,
        '/feedback',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_login',
            'callback'            => 'pax_sup_rest_feedback',
        )
    );

    register_rest_route(
        PAX_SUP_REST_NS,
        '/donate',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_login',
            'callback'            => 'pax_sup_rest_donate',
        )
    );
}
add_action( 'rest_api_init', 'pax_sup_register_support_routes' );

function pax_sup_rest_help_center( WP_REST_Request $request ) {
    $query    = sanitize_text_field( $request->get_param( 'q' ) );
    $lang     = sanitize_text_field( $request->get_param( 'lang' ) );
    $language = pax_sup_detect_user_language( $lang );

    $cache_key = 'pax_sup_help_' . md5( $query . '|' . $language );
    $cached    = get_transient( $cache_key );

    if ( $cached ) {
        return new WP_REST_Response( $cached, 200 );
    }

    $articles = pax_sup_find_knowledge_articles( $query, $language, 6 );

    if ( empty( $articles ) ) {
        $articles = array(
            array(
                'title'   => __( 'Getting started guide', 'pax-support-pro' ),
                'summary' => __( 'Learn how to configure the chat launcher, tickets, and callback tools.', 'pax-support-pro' ),
                'url'     => home_url( '/help/' ),
            ),
        );
    }

    $response = array(
        'articles' => $articles,
        'language' => $language,
    );

    set_transient( $cache_key, $response, 10 * MINUTE_IN_SECONDS );

    return new WP_REST_Response( $response, 200 );
}

function pax_sup_rest_troubleshooter( WP_REST_Request $request ) {
    $topic = sanitize_text_field( $request->get_param( 'topic' ) );
    $notes = pax_sup_trim( $request->get_param( 'notes' ), 600 );

    if ( empty( $topic ) || empty( $notes ) ) {
        return new WP_REST_Response(
            array(
                'message' => __( 'Topic and notes are required.', 'pax-support-pro' ),
            ),
            400
        );
    }

    $steps = array();
    switch ( $topic ) {
        case 'performance':
            $steps = array(
                __( 'Purge your cache plugin and clear CDN layers.', 'pax-support-pro' ),
                __( 'Disable non-essential plugins temporarily to compare load times.', 'pax-support-pro' ),
                __( 'Check server resources in Hosting → Metrics for spikes.', 'pax-support-pro' ),
            );
            break;
        case 'errors':
            $steps = array(
                __( 'Enable WP_DEBUG_LOG and reproduce the issue.', 'pax-support-pro' ),
                __( 'Review the latest entries under Tools → Site Health.', 'pax-support-pro' ),
                __( 'Capture a screenshot of the error for the support team.', 'pax-support-pro' ),
            );
            break;
        case 'billing':
            $steps = array(
                __( 'Confirm the invoice ID and payment gateway used.', 'pax-support-pro' ),
                __( 'Verify billing address matches your payment provider.', 'pax-support-pro' ),
                __( 'Attach any receipts or bank confirmation to your ticket.', 'pax-support-pro' ),
            );
            break;
        default:
            $steps = array(
                __( 'Collect a brief summary of the problem with timestamps.', 'pax-support-pro' ),
                __( 'Share relevant URLs or IDs so the team can replicate it.', 'pax-support-pro' ),
                __( 'Submit a ticket so we can keep you posted with progress.', 'pax-support-pro' ),
            );
            break;
    }

    return new WP_REST_Response(
        array(
            'steps' => $steps,
        ),
        200
    );
}

function pax_sup_rest_diagnostics() {
    $theme      = wp_get_theme();
    $timezone   = get_option( 'timezone_string' ) ?: 'UTC';
    $memory     = ini_get( 'memory_limit' );
    $ip         = pax_sup_ip();

    $items = array(
        __( 'WordPress version', 'pax-support-pro' ) => get_bloginfo( 'version' ),
        __( 'PHP version', 'pax-support-pro' )       => PHP_VERSION,
        __( 'Active theme', 'pax-support-pro' )      => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
        __( 'Server time', 'pax-support-pro' )       => gmdate( 'Y-m-d H:i:s' ) . ' (UTC)',
        __( 'Site timezone', 'pax-support-pro' )     => $timezone,
        __( 'PHP memory limit', 'pax-support-pro' )  => $memory,
        __( 'Your IP', 'pax-support-pro' )           => $ip,
    );

    $response = array(
        'items' => $items,
    );

    return new WP_REST_Response( $response, 200 );
}

function pax_sup_rest_order_lookup( WP_REST_Request $request ) {
    $order_id = pax_sup_trim( $request->get_param( 'order_id' ), 60 );
    $email    = sanitize_email( $request->get_param( 'email' ) );

    if ( empty( $order_id ) || empty( $email ) || ! is_email( $email ) ) {
        return new WP_REST_Response(
            array(
                'message' => __( 'Order ID and billing email are required.', 'pax-support-pro' ),
            ),
            400
        );
    }

    $message = sprintf(
        /* translators: %s: order number */
        __( 'Order %s is queued for agent review. We will email you when an update is available.', 'pax-support-pro' ),
        sanitize_text_field( $order_id )
    );

    return new WP_REST_Response(
        array(
            'ok'      => true,
            'message' => $message,
        ),
        200
    );
}

function pax_sup_rest_my_request() {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_REST_Response(
            array(
                'items' => array(),
            ),
            200
        );
    }

    pax_sup_ticket_prepare_tables();

    global $wpdb;
    $table = pax_sup_get_ticket_table();

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, subject, status, updated_at FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT 12",
            $user_id
        )
    );

    $items = array();
    foreach ( (array) $rows as $row ) {
        $items[] = array(
            'id'       => (int) $row->id,
            'subject'  => $row->subject,
            'status'   => pax_sup_format_ticket_status( $row->status ),
            'rawStatus'=> $row->status,
            'updated'  => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->updated_at, false ),
        );
    }

    return new WP_REST_Response(
        array(
            'items' => $items,
        ),
        200
    );
}

function pax_sup_rest_feedback( WP_REST_Request $request ) {
    if ( ! pax_sup_rl( 'feedback:' . pax_sup_ip() . ':' . gmdate( 'YmdH' ), 10, HOUR_IN_SECONDS ) ) {
        return new WP_REST_Response(
            array(
                'message' => __( 'Too many feedback submissions. Please wait a bit.', 'pax-support-pro' ),
            ),
            429
        );
    }

    $message = pax_sup_trim( $request->get_param( 'message' ), 800 );

    if ( empty( $message ) ) {
        return new WP_REST_Response(
            array(
                'message' => __( 'Feedback message is required.', 'pax-support-pro' ),
            ),
            400
        );
    }

    $user = wp_get_current_user();
    $name = $user instanceof WP_User ? $user->display_name : __( 'Anonymous', 'pax-support-pro' );
    $email = $user instanceof WP_User ? $user->user_email : get_option( 'admin_email' );

    $body = sprintf(
        "Feedback from %s (%s)\n\n%s",
        $name,
        $email,
        $message
    );

    @wp_mail( get_option( 'admin_email' ), '[PAX] Feedback', $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );

    return new WP_REST_Response(
        array(
            'ok' => true,
        ),
        200
    );
}

function pax_sup_rest_donate() {
    $log = get_option( 'pax_sup_donate_clicks', array() );
    $log[] = array(
        'time' => time(),
        'user' => get_current_user_id(),
        'ip'   => pax_sup_ip(),
    );

    if ( count( $log ) > 25 ) {
        $log = array_slice( $log, -25 );
    }

    update_option( 'pax_sup_donate_clicks', $log, false );

    return new WP_REST_Response(
        array(
            'ok' => true,
        ),
        200
    );
}