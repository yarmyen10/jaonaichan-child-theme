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

        $origin = get_http_origin(); // ✅ ใช้ WP function แทน $_SERVER โดยตรง

        if ( in_array( $origin, $allowed_origins, true ) ) { // ✅ เพิ่ม strict true
            header( 'Access-Control-Allow-Origin: '      . $origin );
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' ); // ✅ เพิ่ม PATCH
            header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
            header( 'Access-Control-Allow-Credentials: true' );
        }

        if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            status_header( 200 ); // ✅ ใช้ WP function แทน header() โดยตรง
            exit();
        }

        return $value;
    });
}, 15 );