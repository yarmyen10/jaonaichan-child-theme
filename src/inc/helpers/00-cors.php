<?php
/**
 * CORS Headers สำหรับ REST API
 */
add_action( 'rest_api_init', function() {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

    add_filter( 'rest_pre_serve_request', function( $value ) {

        $allowed_origins = [
            'http://localhost:5173',
            'http://localhost:3000',
            'https://jaonaichan.com',
            'https://bigboss.jaonaichan.com',
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ( in_array( $origin, $allowed_origins ) ) {
            header( 'Access-Control-Allow-Origin: '      . $origin );
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
            header( 'Access-Control-Allow-Credentials: true' );
        }

        if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            header( 'HTTP/1.1 200 OK' );
            exit();
        }

        return $value;
    });
}, 15 );