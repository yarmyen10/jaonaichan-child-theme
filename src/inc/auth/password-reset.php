<?php
/**
 * Password reset: re-route reset-flow pages back to /shop-login/
 *
 * Depends on JN_SHOP_LOGIN_PATH (defined in shop-login-redirect.php).
 *
 * shop-login.php handles lostpassword / rp / resetpass natively.
 * shop-login-redirect.php redirects all wp-login.php requests to /shop-login/
 * (except logout + postpass which must go through WP's own handler).
 *
 * These hooks are safety nets for plugin/WP-core paths that bypass the redirect.
 */


/**
 * 1) Safety net: if anything (plugin, WP core) generates a reset email via the
 *    standard retrieve_password() path, swap the wp-login.php URL for /shop-login/.
 *    shop-login.php handles action=rp natively, so the link works end-to-end.
 */
add_filter( 'retrieve_password_message', function ( $message, $key, $user_login, $user_data ) {
    $new_url = add_query_arg(
        [
            'action' => 'rp',
            'key'    => $key,
            'login'  => rawurlencode( $user_login ),
        ],
        home_url( JN_SHOP_LOGIN_PATH )
    );

    // WP wraps the reset URL in angle brackets: <https://…>
    // Match generously so extra params (wp_lang, etc.) added by WP don't break the replace.
    return preg_replace(
        '|<https?://[^>]+action=rp[^>]*>|',
        '<' . $new_url . '>',
        $message
    );
}, 10, 4 );


/**
 * 2) After the user submits the "Forgot password" form on wp-login.php →
 *    redirect to /shop-login/ instead of WP's default "check your email" page.
 */
add_filter( 'lostpassword_redirect', function ( $redirect ) {
    return add_query_arg( 'jn_notice', 'password_sent', home_url( JN_SHOP_LOGIN_PATH ) );
} );


/**
 * 3) After the user successfully sets a new password → redirect to /shop-login/.
 *    WP has no dedicated redirect filter for this step; we hook after_password_reset
 *    and exit before WP renders its default "Password Reset" confirmation screen.
 */
add_action( 'after_password_reset', function ( $user, $new_pass ) {
    wp_safe_redirect( add_query_arg( 'jn_notice', 'password_reset', home_url( JN_SHOP_LOGIN_PATH ) ) );
    exit;
}, 10, 2 );
