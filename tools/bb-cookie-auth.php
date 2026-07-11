<?php
/**
 * Plugin Name: BB Cookie Auth
 * Description: Maps bb_jwt cookie to HTTP_AUTHORIZATION before JWT plugin runs.
 * Version: 1.0.0
 */

// Runs at mu-plugin load time — before all regular plugins (including JWT plugin).
// Ensures $determine_current_user sees the Authorization header from the cookie.
//
// bb_jwt is issued only to administrators (see Auth_API::signin()). Never inject it on
// customer-only routes (bigboss-auth/v1/my-*) — otherwise a stale admin cookie on a shared
// browser silently authenticates a different logged-in customer's request as the admin,
// since the jwt-auth plugin trusts the Authorization header over the customer's own
// WordPress session cookie whenever that header is present.
// REST requests can arrive as pretty-permalink URIs or ?rest_route= query strings — check both.
$jn_bb_jwt_route = $_GET['rest_route'] ?? '';
$jn_bb_jwt_uri   = $_SERVER['REQUEST_URI'] ?? '';
if (
    ! empty( $_COOKIE['bb_jwt'] )
    && empty( $_SERVER['HTTP_AUTHORIZATION'] )
    && strpos( $jn_bb_jwt_route, '/bigboss-auth/v1/my-' ) === false
    && strpos( $jn_bb_jwt_uri, '/wp-json/bigboss-auth/v1/my-' ) === false
) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . sanitize_text_field( wp_unslash( $_COOKIE['bb_jwt'] ) );
}
