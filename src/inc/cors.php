<?php
/**
 * CORS Headers สำหรับ REST API
 */

function jaonaichan_emit_cors(): void {
    static $emitted = false;
    if ( $emitted ) return;
    $emitted = true;

    $allowed_origins = [
        'http://localhost:5173',
        'http://localhost:3000',
        'https://jaonaichan.com',
        'https://bigboss.jaonaichan.com',
    ];

    $origin = get_http_origin();
    if ( in_array( $origin, $allowed_origins, true ) ) {
        header( 'Access-Control-Allow-Origin: '      . $origin );
        header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
        header( 'Access-Control-Allow-Credentials: true' );
    }
}

add_action( 'rest_api_init', function() {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

    // Fire before the route callback — ensures CORS headers are already sent
    // for callbacks that exit() early (e.g. serve_slip streams a binary file).
    add_filter( 'rest_pre_dispatch', function( $result, $server, $request ) {
        jaonaichan_emit_cors();
        return $result;
    }, 10, 3 );

    // Handles OPTIONS preflight and acts as fallback for normal JSON responses.
    add_filter( 'rest_pre_serve_request', function( $value ) {
        jaonaichan_emit_cors();
        if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            status_header( 200 );
            exit();
        }
        return $value;
    });
}, 15 );
