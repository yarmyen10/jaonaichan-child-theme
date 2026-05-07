<?php
/**
 * Shop Login: route ทุก login/logout ผ่าน /shop-login/
 *
 * - บล็อก wp-login.php → redirect ไป /shop-login/ ยกเว้น action ที่จำเป็น
 * - filter ลิงก์ login/logout/lostpassword ที่ WP สร้าง
 * - หลัง logout → /shop-login/
 */

const JN_SHOP_LOGIN_PATH = '/shop-login/';

// action ที่ต้องคงให้ผ่าน wp-login.php (logout ต้องการ nonce + WP cookie clear, postpass ต้องการ WP handler)
const JN_WP_LOGIN_ALLOWED_ACTIONS = [ 'logout', 'postpass' ];


/**
 * 1) บล็อกการเข้า wp-login.php โดยตรง → redirect ไป /shop-login/
 *    - login, lostpassword, rp, resetpass → /shop-login/ (พร้อม action/key/login params)
 *    - logout, postpass → ปล่อยผ่าน wp-login.php ตามปกติ
 */
function jaonaichan_redirect_wp_login() {
    $action = $_REQUEST['action'] ?? 'login';

    if ( in_array( $action, JN_WP_LOGIN_ALLOWED_ACTIONS, true ) ) return;
    if ( wp_doing_ajax() ) return;
    if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
        || ( defined( 'REST_REQUEST' )   && REST_REQUEST ) ) return;

    $target = home_url( JN_SHOP_LOGIN_PATH );
    $args   = [];

    if ( $action === 'lostpassword' ) {
        $args['action'] = 'lostpassword';
        if ( ! empty( $_REQUEST['error'] ) ) {
            $args['error'] = sanitize_key( $_REQUEST['error'] );
        }
    } elseif ( in_array( $action, [ 'rp', 'resetpass' ], true ) ) {
        $args['action'] = $action;
        if ( ! empty( $_REQUEST['key'] ) )   $args['key']   = rawurlencode( wp_unslash( $_REQUEST['key'] ) );
        if ( ! empty( $_REQUEST['login'] ) ) $args['login'] = rawurlencode( wp_unslash( $_REQUEST['login'] ) );
    } else {
        // login และ action อื่นๆ — ส่ง redirect_to ต่อถ้ามี
        $redirect_to = ! empty( $_REQUEST['redirect_to'] )
            ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) )
            : '';
        if ( $redirect_to ) {
            $args['redirect_to'] = urlencode( $redirect_to );
        }
    }

    wp_safe_redirect( $args ? add_query_arg( $args, $target ) : $target );
    exit;
}
add_action( 'login_init', 'jaonaichan_redirect_wp_login', 1 );


/**
 * 2a) ทุกลิงก์ที่ผ่าน wp_login_url() จะชี้ไป /shop-login/
 */
function jaonaichan_filter_login_url( $login_url, $redirect, $force_reauth ) {
    $url  = home_url( JN_SHOP_LOGIN_PATH );
    $args = [];

    if ( ! empty( $redirect ) )  $args['redirect_to'] = urlencode( $redirect );
    if ( $force_reauth )         $args['reauth']      = '1';

    return $args ? add_query_arg( $args, $url ) : $url;
}
add_filter( 'login_url', 'jaonaichan_filter_login_url', 10, 3 );


/**
 * 2b) logout_url — คงต้องผ่าน wp-login.php?action=logout (ต้อง nonce + เคลียร์ cookie ของ WP)
 *      แต่บังคับ default redirect_to = /shop-login/ ถ้า caller ไม่ระบุ
 */
function jaonaichan_filter_logout_url( $logout_url, $redirect ) {
    $redirect = $redirect ?: home_url( JN_SHOP_LOGIN_PATH );
    $url      = add_query_arg(
        [ 'action' => 'logout', 'redirect_to' => urlencode( $redirect ) ],
        site_url( 'wp-login.php', 'login' )
    );
    return wp_nonce_url( $url, 'log-out' );
}
add_filter( 'logout_url', 'jaonaichan_filter_logout_url', 10, 2 );


/**
 * 2c) lostpassword_url → /shop-login/?action=lostpassword (handled by shop-login.php template)
 */
function jaonaichan_filter_lostpassword_url( $lostpassword_url, $redirect ) {
    return add_query_arg( 'action', 'lostpassword', home_url( JN_SHOP_LOGIN_PATH ) );
}
add_filter( 'lostpassword_url', 'jaonaichan_filter_lostpassword_url', 10, 2 );


/**
 * 3) หลัง logout → /shop-login/ (ถ้า caller ไม่ได้ระบุปลายทางอื่น)
 */
function jaonaichan_filter_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
    return $requested_redirect_to ?: home_url( JN_SHOP_LOGIN_PATH );
}
add_filter( 'logout_redirect', 'jaonaichan_filter_logout_redirect', 10, 3 );
