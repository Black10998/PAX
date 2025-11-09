<?php
/**
 * REST endpoint for AI chat handling.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_register_chat_route() {
    // v5.5.0: Register chat endpoint with multiple methods
    register_rest_route(
        PAX_SUP_REST_NS,
        '/chat',
        array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'permission_callback' => 'pax_sup_rest_require_read_permission',
                'callback'            => 'pax_sup_rest_chat',
            ),
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback'            => 'pax_sup_rest_chat_ping',
            ),
        )
    );

    register_rest_route(
        'pax-support/v1',
        '/ai-chat',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => 'pax_sup_rest_require_read_permission',
            'callback'            => 'pax_sup_rest_ai_chat',
        )
    );
}
add_action( 'rest_api_init', 'pax_sup_register_chat_route' );

/**
 * Handle chat ping requests (GET/HEAD).
 * 
 * @since 5.5.0
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response
 */
function pax_sup_rest_chat_ping( WP_REST_Request $request ) {
    // Check if this is a ping request
    $ping = $request->get_param( 'ping' );
    
    if ( $ping ) {
        return new WP_REST_Response(
            array(
                'status' => 'ok',
                'time'   => time(),
            ),
            200
        );
    }
    
    // If not a ping, return basic status
    return new WP_REST_Response(
        array(
            'status'  => 'online',
            'version' => PAX_SUP_VER,
        ),
        200
    );
}

function pax_sup_rest_chat( WP_REST_Request $request ) {
    return pax_sup_handle_ai_chat_request( $request );
}

function pax_sup_rest_ai_chat( WP_REST_Request $request ) {
    return pax_sup_handle_ai_chat_request( $request );
}

function pax_sup_handle_ai_chat_request( WP_REST_Request $request ) {
    $options = pax_sup_get_options();
    $params  = (array) $request->get_json_params();
    $ip      = pax_sup_ip();

    $message = isset( $params['message'] ) ? $params['message'] : ( $params['q'] ?? '' );
    $message = pax_sup_trim( $message, 1500 );

    if ( '' === $message ) {
        return new WP_REST_Response(
            array(
                'reply'       => __( 'Please type something.', 'pax-support-pro' ),
                'status'      => 'empty',
                'suggestions' => array(),
            ),
            200
        );
    }

    $lang_param = isset( $params['lang'] ) ? sanitize_text_field( $params['lang'] ) : '';
    $language   = pax_sup_detect_user_language( $lang_param );

    $bad_phrases = array( '<script', '</script', '<?php', 'union select', 'drop table', 'base64_' );
    $lower       = mb_strtolower( $message );

    foreach ( $bad_phrases as $phrase ) {
        if ( false !== strpos( $lower, $phrase ) ) {
            return new WP_REST_Response(
                array(
                    'reply'       => __( 'Blocked input.', 'pax-support-pro' ),
                    'status'      => 'blocked',
                    'suggestions' => array(),
                    'language'    => $language,
                ),
                200
            );
        }
    }

    $keywords    = pax_sup_extract_keywords( $message );
    $suggestions = array();
    foreach ( $keywords as $keyword ) {
        $suggestions = array_merge( $suggestions, pax_sup_find_knowledge_articles( $keyword, $language, 2 ) );
    }
    if ( empty( $suggestions ) ) {
        $suggestions = pax_sup_find_knowledge_articles( $message, $language, 3 );
    }

    if ( $suggestions ) {
        $unique = array();
        foreach ( $suggestions as $suggestion ) {
            $hash = md5( $suggestion['url'] );
            if ( ! isset( $unique[ $hash ] ) ) {
                $unique[ $hash ] = $suggestion;
            }
        }
        $suggestions = array_slice( array_values( $unique ), 0, 5 );
    }

    if ( ! pax_sup_rl( 'chat:' . $ip . ':' . gmdate( 'YmdHi' ), 40, MINUTE_IN_SECONDS + 5 ) ) {
        return new WP_REST_Response(
            array(
                'error'       => 'rate',
                'reply'       => __( 'Too many requests. Slow down.', 'pax-support-pro' ),
                'status'      => 'rate_limited',
                'suggestions' => $suggestions,
                'language'    => $language,
            ),
            429
        );
    }

    $session_id = isset( $params['session'] ) ? sanitize_text_field( $params['session'] ) : '';
    $history    = array();
    if ( ! empty( $params['history'] ) && is_array( $params['history'] ) ) {
        foreach ( $params['history'] as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            $role    = isset( $entry['role'] ) ? strtolower( sanitize_text_field( $entry['role'] ) ) : 'user';
            $content = isset( $entry['content'] ) ? pax_sup_trim( $entry['content'], 1200 ) : '';
            if ( ! in_array( $role, array( 'user', 'assistant' ), true ) || '' === $content ) {
                continue;
            }
            $history[] = array(
                'role'    => $role,
                'content' => $content,
            );
        }
    }
    $history = array_slice( $history, -6 );

    $key = $options['openai_key'];
    if ( defined( 'PXA_OPENAI_API_KEY' ) && PXA_OPENAI_API_KEY ) {
        $key = PXA_OPENAI_API_KEY;
    }

    if ( empty( $options['ai_assistant_enabled'] ) || empty( $options['openai_enabled'] ) || empty( $key ) ) {
        return new WP_REST_Response(
            array(
                'reply'       => __( 'Assistant is offline. Enable OpenAI key in settings.', 'pax-support-pro' ),
                'status'      => 'offline',
                'suggestions' => $suggestions,
                'language'    => $language,
            ),
            200
        );
    }

    $context_key = 'pax_ai_ctx_' . md5( $language );
    $context     = get_transient( $context_key );

    if ( false === $context ) {
        $ids = get_posts(
            array(
                'post_type'      => array( 'pax_kb', 'faq', 'page', 'post' ),
                'post_status'    => 'publish',
                'numberposts'    => 15,
                'orderby'        => 'modified',
                'fields'         => 'ids',
                'suppress_filters' => false,
            )
        );

        $buffer = array();
        foreach ( $ids as $id ) {
            $title   = get_the_title( $id );
            $content = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $id, true ) ), 40, '…' );
            if ( $title && $content ) {
                $buffer[] = $title . ': ' . $content;
            }
        }

        $context = implode( "\n\n", $buffer );
        set_transient( $context_key, $context, 6 * HOUR_IN_SECONDS );
    }

    $recent_context = '';
    $user_id        = get_current_user_id();
    if ( $user_id > 0 ) {
        $recent_sessions = pax_sup_get_recent_ai_sessions( $user_id, 2 );
        $snippets        = array();
        foreach ( $recent_sessions as $session ) {
            if ( isset( $session['question'], $session['reply'] ) ) {
                $snippets[] = 'Q: ' . wp_strip_all_tags( (string) $session['question'] ) . "\nA: " . wp_strip_all_tags( (string) $session['reply'] );
            }
        }
        if ( $snippets ) {
            $recent_context = "\n\n" . __( 'Recent conversations:', 'pax-support-pro' ) . "\n" . implode( "\n\n", array_slice( $snippets, 0, 3 ) );
        }
    }

    $resource_summary = '';
    if ( $suggestions ) {
        $lines = array();
        $index = 1;
        foreach ( $suggestions as $suggestion ) {
            $lines[] = sprintf( '%1$d. %2$s — %3$s (%4$s)', $index, wp_strip_all_tags( $suggestion['title'] ), wp_strip_all_tags( $suggestion['summary'] ), esc_url_raw( $suggestion['url'] ) );
            $index++;
        }
        $resource_summary = "\n\n" . __( 'Relevant resources:', 'pax-support-pro' ) . "\n" . implode( "\n", $lines );
    }

    $system_prompt = sprintf(
        /* translators: 1: language code */
        __( 'You are PAX SUPPORT — concise, helpful assistant. Reply in %1$s. Use this site info if helpful:', 'pax-support-pro' ),
        $language
    );

    $system_prompt .= "\n\n" . $context . $recent_context . $resource_summary;

    $messages = array(
        array(
            'role'    => 'system',
            'content' => $system_prompt,
        ),
    );

    foreach ( $history as $entry ) {
        $messages[] = $entry;
    }

    $messages[] = array(
        'role'    => 'user',
        'content' => $message,
    );

    $payload = array(
        'model'       => ( $options['openai_model'] ? $options['openai_model'] : 'gpt-4o-mini' ),
        'temperature' => floatval( $options['openai_temperature'] ),
        'messages'    => $messages,
    );

    $response = wp_remote_post(
        'https://api.openai.com/v1/chat/completions',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 20,
        )
    );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response(
            array(
                'reply'       => sprintf( __( 'Server error: %s', 'pax-support-pro' ), $response->get_error_message() ),
                'status'      => 'error',
                'suggestions' => $suggestions,
                'language'    => $language,
            ),
            200
        );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( $code < 200 || $code >= 300 ) {
        return new WP_REST_Response(
            array(
                'reply'       => sprintf( __( 'AI error %d', 'pax-support-pro' ), $code ),
                'status'      => 'error',
                'suggestions' => $suggestions,
                'language'    => $language,
            ),
            200
        );
    }

    $json  = json_decode( $body, true );
    $reply = isset( $json['choices'][0]['message']['content'] ) ? $json['choices'][0]['message']['content'] : __( 'No response.', 'pax-support-pro' );

    $log_payload = array(
        'history'     => $history,
        'question'    => $message,
        'reply'       => $reply,
        'language'    => $language,
        'suggestions' => $suggestions,
        'timestamp'   => time(),
    );

    pax_sup_store_ai_session( $user_id, $session_id, $language, $log_payload, $keywords );

    return new WP_REST_Response(
        array(
            'reply'       => $reply,
            'status'      => 'online',
            'language'    => $language,
            'session'     => $session_id,
            'suggestions' => $suggestions,
        ),
        200
    );
}

function pax_sup_rest_security_filter( $result, $handler, WP_REST_Request $request ) {
    try {
        $route = $request->get_route();
        $watch = array(
            '/' . PAX_SUP_REST_NS . '/chat',
            '/' . PAX_SUP_REST_NS . '/ticket',
            '/' . PAX_SUP_REST_NS . '/live-agent',
            '/' . PAX_SUP_REST_NS . '/callback',
            '/pax-support/v1/ai-chat',
        );

        $matched = false;
        foreach ( $watch as $target ) {
            if ( 0 === strpos( $route, $target ) ) {
                $matched = true;
                break;
            }
        }

        if ( ! $matched ) {
            return $result;
        }

        $length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( $length > 256 * 1024 ) {
            return new WP_REST_Response(
                array(
                    'error'   => 'payload',
                    'message' => __( 'Payload too large.', 'pax-support-pro' ),
                ),
                413
            );
        }

        if ( in_array( $route, array( '/' . PAX_SUP_REST_NS . '/chat', '/' . PAX_SUP_REST_NS . '/live-agent', '/' . PAX_SUP_REST_NS . '/callback', '/pax-support/v1/ai-chat' ), true ) ) {
            $content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if ( false === strpos( $content_type, 'application/json' ) ) {
                return new WP_REST_Response(
                    array(
                        'error'   => 'ctype',
                        'message' => __( 'Content-Type must be application/json.', 'pax-support-pro' ),
                    ),
                    415
                );
            }
        }
    } catch ( Throwable $e ) {
        // Silence.
    }

    return $result;
}
add_filter( 'rest_request_before_callbacks', 'pax_sup_rest_security_filter', 9, 3 );