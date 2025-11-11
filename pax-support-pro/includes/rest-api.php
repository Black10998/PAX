<?php
/**
 * REST API aliases for Live Agent routes.
 *
 * Exposes /pax/v1/liveagent/* endpoints that proxy to the
 * existing Live Agent controllers to prevent 404 errors when
 * clients expect the /liveagent/ namespace.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'pax_register_rest_alias_routes' );

/**
 * Register alias routes for legacy/liveagent consumers.
 */
function pax_register_rest_alias_routes() {
	register_rest_route(
		'pax/v1',
		'/liveagent/session/accept',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'pax_rest_alias_accept_session',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'pax/v1',
		'/liveagent/message/send',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'pax_rest_alias_send_message',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'pax/v1',
		'/liveagent/status/poll',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'pax_rest_alias_poll_status',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Proxy accept session handler.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function pax_rest_alias_accept_session( WP_REST_Request $request ) {
	return pax_live_agent_accept( $request );
}

/**
 * Proxy message send handler, normalising the payload.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function pax_rest_alias_send_message( WP_REST_Request $request ) {
	// Normalise payload: support "message" key in addition to "content".
	$message = $request->get_param( 'message' );
	if ( $message && ! $request->get_param( 'content' ) ) {
		$request->set_param( 'content', $message );
	}

	$from = $request->get_param( 'from' );
	if ( $from ) {
		$request->set_param( 'sender', sanitize_text_field( $from ) );
	}

	return pax_live_agent_message( $request );
}

/**
 * Proxy poll handler that returns the standard message payload.
 *
 * @param WP_REST_Request $request Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function pax_rest_alias_poll_status( WP_REST_Request $request ) {
	return pax_live_agent_get_messages( $request );
}
