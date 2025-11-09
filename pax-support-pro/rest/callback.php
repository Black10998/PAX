<?php
/**
 * REST endpoint for callback requests.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_register_callback_route() {
    register_rest_route(
        PAX_SUP_REST_NS,
        '/callback',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_read_permission',
            'callback'            => 'pax_sup_rest_callback',
        )
    );
}
add_action( 'rest_api_init', 'pax_sup_register_callback_route' );

function pax_sup_rest_callback( WP_REST_Request $request ) {
    $options = pax_sup_get_options();
    
    // Check if callback is enabled
    if ( empty( $options['callback_enabled'] ) ) {
        return new WP_REST_Response(
            array(
                'error'   => 'disabled',
                'message' => __( 'Callback feature is currently disabled.', 'pax-support-pro' ),
            ),
            403
        );
    }
    
    $recipient = ! empty( $options['live_agent_email'] ) ? $options['live_agent_email'] : get_option( 'admin_email' );

    $name = pax_sup_trim( $request->get_param( 'name' ), 120 );
    $phone = pax_sup_trim( $request->get_param( 'phone' ), 40 );
    $note = pax_sup_trim( $request->get_param( 'note' ), 240 );

    if ( ! pax_sup_rl( 'callback:' . pax_sup_ip() . ':' . gmdate( 'YmdH' ), 5, HOUR_IN_SECONDS ) ) {
        return new WP_REST_Response(
            array(
                'error'   => 'rate',
                'message' => __( 'Too many requests. Please wait before retrying.', 'pax-support-pro' ),
            ),
            429
        );
    }

    if ( ! $name || ! $phone ) {
        return new WP_REST_Response(
            array(
                'error'   => 'missing',
                'message' => __( 'Name & phone required.', 'pax-support-pro' ),
            ),
            400
        );
    }

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    $message = "Name: {$name}\nPhone: {$phone}\nNote: {$note}\n";
    @wp_mail( $recipient, '[PAX] Callback request', $message, $headers );

    return new WP_REST_Response( array( 'ok' => true ), 200 );
}