<?php
/**
 * REST endpoint for live agent requests.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_register_agent_route() {
    register_rest_route(
        PAX_SUP_REST_NS,
        '/live-agent',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_read_permission',
            'callback'            => 'pax_sup_rest_live_agent',
        )
    );
}
add_action( 'rest_api_init', 'pax_sup_register_agent_route' );

function pax_sup_rest_live_agent( WP_REST_Request $request ) {
    $options = pax_sup_get_options();

    if ( empty( $options['live_agent_email'] ) ) {
        return new WP_REST_Response(
            array(
                'error'   => 'disabled',
                'message' => __( 'Live agent email not set.', 'pax-support-pro' ),
            ),
            400
        );
    }

    $ip = pax_sup_ip();
    if ( ! pax_sup_rl( 'agent:' . $ip . ':' . gmdate( 'YmdH' ), 6, HOUR_IN_SECONDS + 30 ) ) {
        return new WP_REST_Response(
            array(
                'error'   => 'rate',
                'message' => __( 'Too many requests.', 'pax-support-pro' ),
            ),
            429
        );
    }

    $name  = pax_sup_trim( $request->get_param( 'name' ), 120 );
    $email = sanitize_email( $request->get_param( 'email' ) );
    $issue = pax_sup_trim( $request->get_param( 'issue' ), 1200 );

    if ( ! $name || ! $email || ! $issue || ! is_email( $email ) ) {
        return new WP_REST_Response(
            array(
                'error'   => 'missing',
                'message' => __( 'All fields required.', 'pax-support-pro' ),
            ),
            400
        );
    }

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    $message = "Live Agent request\n\nFrom: {$name} <{$email}>\nIP: {$ip}\n\nIssue:\n{$issue}\n";
    @wp_mail( $options['live_agent_email'], '[PAX] Live Agent request', $message, $headers );

    return new WP_REST_Response( array( 'ok' => true ), 200 );
}